<?php

namespace App\Http\Controllers;

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

        abort_if($declarada === null, 404);

        return view(InstanceViews::NAMESPACE.'::'.$declarada->vista, [
            'pagina' => [
                'slug' => $declarada->slug,
                'url' => route($declarada->ruta()),
            ],
            'hechos' => $hechos->resolver($declarada->hechos),
        ]);
    }
}
