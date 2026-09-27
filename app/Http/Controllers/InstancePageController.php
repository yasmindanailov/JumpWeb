<?php

namespace App\Http\Controllers;

use App\Http\Instancia\InstancePage;
use App\Http\Instancia\InstancePages;
use App\Http\Instancia\InstanceViews;
use App\Http\Instancia\PageFacts;
use Illuminate\Contracts\View\View;

/**
 * **Sirve una página que declara el paquete de la instancia** (T4b de `isla-y-landing-nueva.md` §4.2 y §4.12;
 * `DECISIONES #681`).
 *
 * El controlador no sabe qué es «Kids»: recibe el slug de la ruta, busca la página en {@see InstancePages} y le pasa
 * a su vista dos cosas —`pagina` (lo que la instancia declaró) y `hechos` (lo que pidió, con el MISMO JSON que la
 * API, {@see PageFacts})—. El marcado, los textos y las piezas son de la instancia.
 */
class InstancePageController extends Controller
{
    public function __invoke(string $pagina, InstancePages $paginas, PageFacts $hechos): View
    {
        $declarada = $paginas->una($pagina);

        // (La portada declarada no llega aquí: no tiene ruta propia, `InstancePages::registrarRutas`.)
        abort_if($declarada === null, 404);

        return self::pintar($declarada, $hechos, route($declarada->ruta()));
    }

    /**
     * **La vista de una página declarada**, con su contrato (`InstanceViews::CONTRATO_DE_PAGINA`: `pagina` y `hechos`).
     * Una sola mano: la usan su ruta y, para la PORTADA declarada, `HomeController` (T6a de §4.17), que es quien sabe si
     * la petición es una puerta de entrar (`noindex`: `/login` y compañía no se indexan nunca, `SeoTest`).
     */
    public static function pintar(InstancePage $pagina, PageFacts $hechos, string $url, bool $noindex = false): View
    {
        return view(InstanceViews::NAMESPACE.'::'.$pagina->vista, [
            'pagina' => [
                'slug' => $pagina->slug,
                'url' => $url,
                'noindex' => $noindex,
            ],
            'hechos' => $hechos->resolver($pagina->hechos),
        ]);
    }
}
