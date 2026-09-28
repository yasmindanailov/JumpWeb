<?php

namespace App\Http\Instancia;

use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\CatalogProduct;
use App\Domain\Booking\Contracts\OfferedDate;
use App\Domain\Booking\Contracts\OfferedTime;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Domain\Platform\Services\DisplayTime;
use App\Http\Api\ApiCollection;
use App\Http\Controllers\Api\V1\AttractionsFactsController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BarFactsController;
use App\Http\Controllers\Api\V1\CatalogProductsController;
use App\Http\Controllers\Api\V1\CatalogZonesController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\FaqsFactsController;
use App\Http\Controllers\Api\V1\LegalWaiverController;
use App\Http\Controllers\Api\V1\PricesFactsController;
use App\Http\Controllers\Api\V1\PromotionsFactsController;
use App\Http\Controllers\Api\V1\ReviewsFactsController;
use App\Http\Controllers\Api\V1\RulesFactsController;
use App\Http\Controllers\Api\V1\ScheduleFactsController;
use App\Http\Controllers\Api\V1\ServicesFactsController;
use App\Http\Controllers\Api\V1\SiteFactsController;
use App\Http\Controllers\Api\V1\SocialProofFactsController;
use App\Http\Resources\Api\V1\OfferedTimeResource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
        // La CAFETERÍA (T6d·1): su nombre, su entradilla, si se entra sin entrada y su FOTO con su `alt` —lo que publica
        // `/bar`, que se retira en la T6f—; de aquí la pinta Visítanos.
        'bar' => [BarFactsController::class, '__invoke', true],
        // El DESCARGO (T6e, `#842`): la versión vigente con sus secciones —lo mismo que `GET /legal/waiver`, público—; de
        // aquí lee Normas su hoja «Leer el descargo». El idioma, el de la visita (el controlador no pide `?lang=`).
        'waiver' => [LegalWaiverController::class, 'show', false],
        // Los SERVICIOS (su mitad editorial, `#671`): de aquí saca Colegios (T6c) el horario de excursiones, su duración y
        // su grupo, que el parque escribe en el panel y no son el horario de la zona.
        'services' => [ServicesFactsController::class, '__invoke', true],
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
        // Su hermano de CUALQUIER día (T6c·2): «Próximos días con hueco» de la página de colegios, cada día con su tarifa
        // (el punto de la especial). Se resuelve en {@see diasConHueco()}.
        'availability_days' => [AvailabilityController::class, 'dates', false],
        // La configuración pública (T6b·2): hasta cuándo se ajustan los invitados de una fiesta
        // (`guest_count_cutoff_hours`), lo mismo que ya dice la isla al elegir cuántos niños.
        'config' => [ConfigController::class, '__invoke', false],
    ];

    /** Cuántos días de fin de semana con hueco se buscan por zona, y en cuántas semanas como mucho. */
    private const FINDE_DIAS = 4;

    private const FINDE_SEMANAS = 8;

    /** Cuántos días (cualquiera) con hueco se buscan por zona —los seis del diseño de colegios—, y en cuántas semanas. */
    private const DIAS_DIAS = 6;

    private const DIAS_SEMANAS = 3;

    /**
     * Segundos que valen las respuestas de los días con hueco (ver {@see conHuecoPorZona()}): frescas cinco minutos y,
     * hasta media hora, se sirven como están y se rehacen DESPUÉS de responder (`Cache::flexible`). Medido (28-09, local):
     * `/colegios` tarda ~0,71 s en frío y ~0,19 s con lo guardado; en una página de poco tráfico casi toda visita llegaba
     * con lo guardado caducado. Así, la que lo encuentra pasado lo sirve y la siguiente ya lo tiene fresco.
     */
    private const CON_HUECO_CACHE_S = [300, 1800];

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

            if (in_array($nombre, ['availability_today', 'availability_weekends', 'availability_days'], true)) {
                $hechos[$nombre] = match ($nombre) {
                    'availability_today' => $this->horasDeHoy(),
                    'availability_weekends' => $this->finesDeSemanaConHueco(),
                    'availability_days' => $this->diasConHueco(),
                };

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
     * `#834`): «Próximos fines de semana con hueco» de cumpleaños. Una fila por zona, en el orden del catálogo: `{zone,
     * dates}`, con las fechas (`Y-m-d`) de menor a mayor; como mucho {@see FINDE_DIAS} en {@see FINDE_SEMANAS} semanas.
     * La regla y su coste, en {@see conHuecoPorZona()}.
     *
     * @return list<array{zone: string, dates: list<string>}>
     */
    private function finesDeSemanaConHueco(): array
    {
        $hoy = DisplayTime::today();

        return Cache::flexible('instancia:hechos:availability_weekends:'.$hoy->toDateString(), self::CON_HUECO_CACHE_S, fn (): array => array_map(
            fn (array $fila): array => ['zone' => $fila['zone'], 'dates' => array_map(fn (OfferedDate $dia): string => $dia->date, $fila['dates'])],
            $this->conHuecoPorZona($hoy, self::FINDE_SEMANAS, self::FINDE_DIAS, soloFinDeSemana: true),
        ));
    }

    /**
     * **Los próximos días —CUALQUIERA— con hueco de cada zona de PACKS** (T6c·2 de `isla-y-landing-nueva.md` §4.19): el
     * hermano de {@see finesDeSemanaConHueco()} para «Próximos días con hueco» de colegios, que va todos los días. Cada
     * fecha lleva su tarifa (`rate_key`, la de `GET /availability/{id}/dates`) para el punto de la especial: en
     * excursiones cambia el precio por alumno. Como mucho {@see DIAS_DIAS} en {@see DIAS_SEMANAS} semanas: el tope y el
     * horizonte son los que pagan (en local, 28-09: ~50 ms y ~54 consultas por día de un pack de excursión).
     *
     * ⚠️ Por ZONA y no por pack, como su hermano: el diseño los recalcula con la duración elegida (2 o 3 horas), y por pack
     * costaba el doble (~860 ms en frío, medido) para decir lo que la calculadora vuelve a preguntar al pulsar el día.
     *
     * @return list<array{zone: string, dates: list<array{date: string, rate_key: string}>}>
     */
    private function diasConHueco(): array
    {
        $hoy = DisplayTime::today();

        return Cache::flexible('instancia:hechos:availability_days:'.$hoy->toDateString(), self::CON_HUECO_CACHE_S, fn (): array => array_map(
            fn (array $fila): array => ['zone' => $fila['zone'], 'dates' => array_map(fn (OfferedDate $dia): array => ['date' => $dia->date, 'rate_key' => $dia->rateKey], $fila['dates'])],
            $this->conHuecoPorZona($hoy, self::DIAS_SEMANAS, self::DIAS_DIAS, soloFinDeSemana: false),
        ));
    }

    /**
     * **Los próximos días con hueco de cada zona de PACKS**, con las MISMAS dos preguntas que la API —los días que se
     * venden (`GET /availability/{id}/dates`) y sus horas (`POST /availability/{id}/times`)—: un día cuenta si ALGÚN pack
     * de la zona tiene una hora a la venta, y lleva lo que ESE pack dice del día (su tarifa). Una fila por zona, en el
     * orden del catálogo, con los días de menor a mayor.
     *
     * ⚠️ Medido (28-09): cada día cuesta ~55 ms y ~42 consultas —es `times()`, el motor de aforo, que no se toca por
     * esto—; por eso se para en cuanto hay `$cuantos`, el siguiente pack solo se mira si el anterior no tiene hueco, no
     * se pasa de `$semanas` y quien lo llama lo guarda ({@see CON_HUECO_CACHE_S}). Es una PISTA: quien pulsa un día
     * llega a la calculadora, que vuelve a preguntar; un día que se llenó entre medias sale allí sin horas, y nunca se
     * vende de más (`AFORO-01`: lo garantiza `OrderCreator` bajo lock).
     *
     * @return list<array{zone: string, dates: list<OfferedDate>}>
     */
    private function conHuecoPorZona(CarbonInterface $hoy, int $semanas, int $cuantos, bool $soloFinDeSemana): array
    {
        $oferta = app(AvailabilityOffer::class);
        $hasta = $hoy->copy()->addWeeks($semanas)->toDateString();
        $packs = array_filter(app(ProductCatalog::class)->products(null), fn (CatalogProduct $p): bool => $p->isPack() && $p->zone !== null);
        $zonas = [];

        foreach ($packs as $pack) {
            // Los días en los que ESTE pack se vende, dentro del horizonte (y de fin de semana, si se piden así).
            foreach ($oferta->dates($pack->id) as $dia) {
                if ($dia->date <= $hasta && (! $soloFinDeSemana || CarbonImmutable::parse($dia->date)->isWeekend())) {
                    $zonas[$pack->zone->slug][$dia->date][] = [$pack->id, $dia];
                }
            }
            $zonas[$pack->zone->slug] ??= [];
        }

        $filas = [];
        foreach ($zonas as $zona => $dias) {
            ksort($dias);
            $conHueco = [];
            foreach ($dias as $packsDelDia) {
                $conHora = collect($packsDelDia)->first(fn (array $par): bool => collect($oferta->times($par[0], $par[1]->date))
                    ->contains(fn (OfferedTime $hora): bool => $hora->sellable));
                if ($conHora === null) {
                    continue;
                }
                $conHueco[] = $conHora[1];
                if (count($conHueco) === $cuantos) {
                    break;
                }
            }
            $filas[] = ['zone' => (string) $zona, 'dates' => $conHueco];
        }

        return $filas;
    }
}
