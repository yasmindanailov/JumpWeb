<?php

namespace App\Http\Instancia;

use App\Http\Controllers\Api\V1\AttractionsFactsController;
use App\Http\Controllers\Api\V1\CatalogProductsController;
use App\Http\Controllers\Api\V1\CatalogZonesController;
use App\Http\Controllers\Api\V1\FaqsFactsController;
use App\Http\Controllers\Api\V1\PricesFactsController;
use App\Http\Controllers\Api\V1\RulesFactsController;
use App\Http\Controllers\Api\V1\ScheduleFactsController;
use App\Http\Controllers\Api\V1\SiteFactsController;
use App\Http\Controllers\Api\V1\SocialProofFactsController;
use Illuminate\Http\Request;
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
        'faqs' => [FaqsFactsController::class, '__invoke', true],
        'social_proof' => [SocialProofFactsController::class, '__invoke', false],
        'zones' => [CatalogZonesController::class, 'index', false],
        'products' => [CatalogProductsController::class, 'index', false],
    ];

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

            [$controlador, $metodo, $conIdioma] = self::HECHOS[$nombre];
            $peticion = Request::create('/', 'GET', $conIdioma ? ['lang' => $idioma] : []);
            $recurso = app()->call([app($controlador), $metodo], ['request' => $peticion]);

            $hechos[$nombre] = $recurso->toResponse($peticion)->getData(true);
        }

        app()->setLocale($idioma);

        return $hechos;
    }
}
