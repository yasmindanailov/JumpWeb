<?php

namespace App\Http\Instancia;

use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\CatalogProduct;
use App\Domain\Booking\Contracts\OfferedTime;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Domain\Platform\Services\DisplayTime;
use App\Http\Api\ApiCollection;
use App\Http\Controllers\Api\V1\AttractionsFactsController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\CatalogProductsController;
use App\Http\Controllers\Api\V1\CatalogZonesController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\FaqsFactsController;
use App\Http\Controllers\Api\V1\PricesFactsController;
use App\Http\Controllers\Api\V1\PromotionsFactsController;
use App\Http\Controllers\Api\V1\ReviewsFactsController;
use App\Http\Controllers\Api\V1\RulesFactsController;
use App\Http\Controllers\Api\V1\ScheduleFactsController;
use App\Http\Controllers\Api\V1\SiteFactsController;
use App\Http\Controllers\Api\V1\SocialProofFactsController;
use App\Http\Resources\Api\V1\OfferedTimeResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * **Los hechos de una página de la instancia, los MISMOS que sirve la API** (T4b de `isla-y-landing-nueva.md`
 * §4.12; `DECISIONES #681` y `#761`).
 *
 * Una página declara qué hechos consume (`config/paginas.php` de su paquete) y el producto se los resuelve EN EL
 * SERVIDOR invocando el controlador de la API de cada uno: la vista recibe **el mismo JSON** que recibiría una
 * landing por HTTP. Sin una copia de la lógica que pueda divergir: si la API cambia, la página lo ve (§4.2, «los
 * datos que recibe una vista son los de los recursos públicos del menú»).
 *
 * ⚠️ Es una llamada INTERNA, no una petición: no pasa por el middleware de la API (limitador, `ETag`, CORS). El
 * idioma es el de la visita, que ya fijó `SetLocale`; los controladores que lo piden por `?lang=` lo reciben, y si
 * alguno lo moviera, se devuelve al terminar.
 */
final class PageFacts
{
    /**
     * Los hechos que una página PUEDE pedir: nombre => [controlador, método, ¿pide `?lang=`?]. Es una lista blanca:
     * un nombre que no esté aquí no es un hecho, y la página que lo pida no se registra ({@see InstancePages}).
     *
     * @var array<string, array{0: class-string, 1: string, 2: bool}>
     */
    public const HECHOS = [
        'site' => [SiteFactsController::class, '__invoke', false],
        'schedule' => [ScheduleFactsController::class, 'schedule', false],
        'schedule_now' => [ScheduleFactsController::class, 'now', false],
        'prices' => [PricesFactsController::class, '__invoke', true],
        'attractions' => [AttractionsFactsController::class, '__invoke', true],
        'rules' => [RulesFactsController::class, '__invoke', true],
        // Las PROMOCIONES de hoy (`specs/promociones.md` §4.3): la página las reparte por los sitios de su objetivo.
        'promotions' => [PromotionsFactsController::class, '__invoke', true],
        // Las OPINIONES publicadas, cada una con sus páginas (`#771`): la página toma las de su etiqueta.
        'reviews' => [ReviewsFactsController::class, '__invoke', true],
        'faqs' => [FaqsFactsController::class, '__invoke', true],
        'social_proof' => [SocialProofFactsController::class, '__invoke', false],
        'zones' => [CatalogZonesController::class, 'index', false],
        'products' => [CatalogProductsController::class, 'index', false],
        // Las FICHAS del catálogo (T4c·8, `#763`): una por producto, en el orden de `products`. El precio de un
        // complemento («2 € el par» de calcetines) solo vive aquí (`addons[].price_cents`).
        'product_details' => [CatalogProductsController::class, 'show', false],
        // Las HORAS DE HOY de cada ENTRADA (T4e): de aquí salen «Quedan huecos esta tarde», «Reservar para hoy» y «Hoy,
        // 1 hora cuesta…» de la cabecera, «dónde y cuándo», el cierre y la isla. Se resuelve en {@see horasDeHoy()}.
        'availability_today' => [AvailabilityController::class, 'times', false],
        // Los próximos días de FIN DE SEMANA con hueco de cada zona de packs (T6b·2): «Próximos fines de semana con
        // hueco» de la página de cumpleaños. Se resuelve en {@see finesDeSemanaConHueco()}.
        'availability_weekends' => [AvailabilityController::class, 'dates', false],
        // La configuración pública (T6b·2): hasta cuándo se ajustan los invitados de una fiesta
        // (`guest_count_cutoff_hours`), lo mismo que ya dice la isla al elegir cuántos niños.
        'config' => [ConfigController::class, '__invoke', false],
    ];

    /** Cuántos días de fin de semana con hueco se buscan por zona, y en cuántas semanas como mucho. */
    private const FINDE_DIAS = 4;

    private const FINDE_SEMANAS = 8;

    /** Segundos que vale la respuesta de los fines de semana con hueco (ver {@see finesDeSemanaConHueco()}). */
    private const FINDE_CACHE_S = 300;

    /**
     * Los hechos que son una FICHA por producto del catálogo: se invoca su controlador una vez por producto, con el
     * mismo JSON que `GET /catalog/products/{id}`, y llegan en una lista en el orden del catálogo.
     *
     * @var list<string>
     */
    private const POR_PRODUCTO = ['product_details'];

    /**
     * @param  list<string>  $nombres
     * @return array<string, mixed> nombre => el cuerpo JSON de su recurso, ya decodificado
     */
    public function resolver(array $nombres): array
    {
        $idioma = app()->getLocale();
        $hechos = [];

        foreach ($nombres as $nombre) {
            if (! isset(self::HECHOS[$nombre])) {
                throw new LogicException("«{$nombre}» no es un hecho que una página pueda pedir");
            }

            if ($nombre === 'availability_today' || $nombre === 'availability_weekends') {
                $hechos[$nombre] = $nombre === 'availability_today' ? $this->horasDeHoy() : $this->finesDeSemanaConHueco();

                continue;
            }

            [$controlador, $metodo, $conIdioma] = self::HECHOS[$nombre];
            $peticion = Request::create('/', 'GET', $conIdioma ? ['lang' => $idioma] : []);
            $pedir = fn (array $argumentos = []): array => app()
                ->call([app($controlador), $metodo], ['request' => $peticion, ...$argumentos])
                ->toResponse($peticion)->getData(true);

            $hechos[$nombre] = in_array($nombre, self::POR_PRODUCTO, true)
                ? array_map(fn (CatalogProduct $producto): array => $pedir(['product' => $producto->id]), app(ProductCatalog::class)->products(null))
                : $pedir();
        }

        app()->setLocale($idioma);

        return $hechos;
    }

    /**
     * **Las horas de hoy de cada entrada**, con el MISMO JSON que `POST /availability/{id}/times` (la colección de la
     * API con su recurso) sin la cesta —es la oferta para quien llega— y con la fecha de hoy DEL PARQUE
     * (`DisplayTime::today()`: entre las dos medianoches la del contenedor no es la misma). Una fila por entrada, en el
     * orden del catálogo: `{product_id, data}`.
     *
     * ⚠️ Es el ÚNICO hecho que no pasa por su controlador, y es medido: el controlador busca el producto con
     * `ProductCatalog::product()`, que no memoriza, y así eran 176 consultas y 160–180 ms por visita para las nueve
     * fichas; el servicio directo sobre las cinco entradas, 15–22 ms (25-09). Lo que se salta —validar la fecha y el
     * 404 de un producto que no existe— no aplica: la fecha es la de hoy y los productos, los del catálogo.
     *
     * @return list<array{product_id: int, data: list<array<string, mixed>>}>
     */
    private function horasDeHoy(): array
    {
        $hoy = DisplayTime::today()->toDateString();
        $oferta = app(AvailabilityOffer::class);
        $peticion = Request::create('/', 'GET');
        $entradas = array_filter(app(ProductCatalog::class)->products(null), fn (CatalogProduct $p): bool => $p->type === 'entry');

        return array_values(array_map(fn (CatalogProduct $p): array => [
            'product_id' => $p->id,
            'data' => (new ApiCollection($oferta->times($p->id, $hoy), OfferedTimeResource::class))->toResponse($peticion)->getData(true)['data'] ?? [],
        ], $entradas));
    }

    /**
     * **Los próximos días de fin de semana con hueco de cada zona de PACKS** (T6b·2 de `isla-y-landing-nueva.md` §4.18,
     * `#834`), con las MISMAS dos preguntas que la API —los días que se venden (`GET /availability/{id}/dates`) y sus
     * horas (`POST /availability/{id}/times`)—: un día cuenta si ALGÚN pack de la zona tiene una hora a la venta. Una
     * fila por zona, en el orden del catálogo: `{zone, dates}`, con las fechas (`Y-m-d`) de menor a mayor.
     *
     * ⚠️ Medido (28-09): cada día cuesta ~55 ms y ~42 consultas —es `times()`, el motor de aforo, que no se toca por
     * esto—; por eso se para en cuanto hay {@see FINDE_DIAS}, el siguiente pack solo se mira si el anterior no tiene
     * hueco, no se pasa de {@see FINDE_SEMANAS} semanas y la respuesta vale {@see FINDE_CACHE_S} segundos. Es una
     * PISTA: quien pulsa un día llega a la calculadora, que vuelve a preguntar; un día que se llenó entre medias sale
     * allí sin horas, y nunca se vende de más (`AFORO-01`: lo garantiza `OrderCreator` bajo lock).
     *
     * @return list<array{zone: string, dates: list<string>}>
     */
    private function finesDeSemanaConHueco(): array
    {
        $hoy = DisplayTime::today();

        return Cache::remember('instancia:hechos:availability_weekends:'.$hoy->toDateString(), self::FINDE_CACHE_S, function () use ($hoy): array {
            $oferta = app(AvailabilityOffer::class);
            $hasta = $hoy->copy()->addWeeks(self::FINDE_SEMANAS)->toDateString();
            $packs = array_filter(app(ProductCatalog::class)->products(null), fn (CatalogProduct $p): bool => $p->isPack() && $p->zone !== null);
            $zonas = [];

            foreach ($packs as $pack) {
                // Los días de fin de semana en los que ESTE pack se vende, dentro del horizonte.
                foreach ($oferta->dates($pack->id) as $dia) {
                    if ($dia->date <= $hasta && CarbonImmutable::parse($dia->date)->isWeekend()) {
                        $zonas[$pack->zone->slug][$dia->date][] = $pack->id;
                    }
                }
                $zonas[$pack->zone->slug] ??= [];
            }

            $filas = [];
            foreach ($zonas as $zona => $dias) {
                ksort($dias);
                $conHueco = [];
                foreach ($dias as $dia => $idsPacks) {
                    $hayHueco = collect($idsPacks)->contains(fn (int $id): bool => collect($oferta->times($id, (string) $dia))
                        ->contains(fn (OfferedTime $hora): bool => $hora->sellable));
                    if (! $hayHueco) {
                        continue;
                    }
                    $conHueco[] = (string) $dia;
                    if (count($conHueco) === self::FINDE_DIAS) {
                        break;
                    }
                }
                $filas[] = ['zone' => (string) $zona, 'dates' => $conHueco];
            }

            return $filas;
        });
    }
}
