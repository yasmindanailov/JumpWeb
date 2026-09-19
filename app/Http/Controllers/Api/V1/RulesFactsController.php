<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Models\VenueRule;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\Api\V1\RulesFactsResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `GET /api/v1/rules?lang=` — las NORMAS del recinto (F5 · T3 del menú).
 *
 * ⚠️ **El idioma es parte de la PETICIÓN, no del visitante**, y por el mismo motivo que en `/sidebar/boot`:
 * la respuesta se cachea en público, y una respuesta cacheable tiene que ser función de su URL. Sacarlo de la
 * sesión o de `Accept-Language` haría que la primera caché sirviera francés a quien pidió español.
 *
 * ⚠️ **Solo las normas ACTIVAS.** Una norma desactivada en el panel es una norma retirada: publicarla por la
 * API la devolvería a la web por la puerta de atrás.
 */
class RulesFactsController extends Controller
{
    public function __invoke(Request $request): RulesFactsResource
    {
        $datos = $request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale($datos['lang']);

        return new RulesFactsResource(
            VenueRule::query()->where('is_active', true)->orderBy('position')->get()
        );
    }
}
