<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Models\Faq;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\Api\V1\FaqsFactsResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `GET /api/v1/faqs?lang=` — las DUDAS que el negocio contesta (F5 · el menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * ⚠️ **El idioma es parte de la PETICIÓN, no del visitante**, igual que en `/rules` y por lo mismo: la
 * respuesta se cachea en público, y una respuesta cacheable tiene que ser función de su URL. Sacarlo de la
 * sesión o de `Accept-Language` haría que la primera caché sirviera francés a quien pidió español.
 *
 * ⚠️ **Solo las ACTIVAS.** Una duda desactivada en el panel es una duda retirada: publicarla por la API la
 * devolvería a la web por la puerta de atrás, y quien la desactivó creería que ya no está.
 *
 * ⚠️⚠️ **El desempate por `id` no es decoración.** `position` **no es única** —el panel no lo impide y su
 * migración no lo declara—, así que con dos dudas empatadas el orden lo decide el motor y **dos peticiones
 * idénticas pueden devolver dos órdenes distintas**. Una respuesta que se cachea en público y lleva `ETag`
 * no puede barajar: el `ETag` cambiaría sin que nadie tocara el panel. Medido en esta instalación: las doce
 * dudas van de 1 a 12 sin empates, o sea que hoy no se nota — que es exactamente por lo que conviene fijarlo
 * antes de que alguien duplique una posición desde el panel.
 */
class FaqsFactsController extends Controller
{
    public function __invoke(Request $request): FaqsFactsResource
    {
        $datos = $request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale($datos['lang']);

        return new FaqsFactsResource(
            Faq::query()->where('is_active', true)->orderBy('position')->orderBy('id')->get()
        );
    }
}
