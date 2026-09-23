<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Services\BarPage;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\Api\V1\BarFactsResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `GET /api/v1/bar?lang=` — el BAR, como hechos (F5 · el menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * ⚠️⚠️ **Este plato es el primero del menú que lee `settings`, y NO declara lista blanca: delega en
 * `BarPage`.** No es un rodeo de `PublicFactsBoundaryTest` — es la vía que su propio docblock prescribe:
 * *«un hecho público se pide por `PublicFacts` con la lista de ese recurso; lo demás lo sirve un servicio
 * de dominio, que es quien sabe qué significa su ajuste»*. `BarPage` es exactamente ese servicio: sabe que
 * el NOMBRE gatea la publicación, que `bar.free_entry` solo admite `yes`/`no` y cuál es el respaldo de
 * idioma. Duplicar esas tres reglas en el recurso para poder declarar diez claves sería cambiar una fuente
 * única por dos que hay que mantener de acuerdo.
 *
 * ⚠️ **El idioma va en la URL** como en el resto del menú traducible: la respuesta se cachea y tiene que
 * ser función de su URL.
 */
class BarFactsController extends Controller
{
    public function __invoke(Request $request): BarFactsResource
    {
        $datos = $request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale($datos['lang']);

        return new BarFactsResource(null);
    }
}
