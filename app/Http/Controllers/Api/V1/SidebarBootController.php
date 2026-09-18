<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\GoogleAuth;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Sidebar\SidebarBoot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * F4 · T1 — **el arranque del cajón por la API** (`docs/specs/cajon-empaquetable.md` §4.5, `#631`).
 *
 * El layout del producto pinta el arranque en un atributo (`data-boot`). Una página que NO pinte
 * Blade —la landing a mano de una instancia— lo pide aquí, y recibe **exactamente lo mismo**: las dos
 * rutas son el segundo transporte de `Http\Sidebar\SidebarBoot`, no una segunda redacción.
 *
 * Son dos lecturas y no una porque no se pueden cachear igual:
 *  · `boot` no depende de quién mira. Por eso el idioma viaja EN LA URL (`?lang=`) y no sale de la
 *    sesión ni de `Accept-Language`, que es lo que hace `ApiLocale` en el resto de la API: una
 *    respuesta cacheable tiene que ser función de su URL, o una caché serviría francés a quien pidió
 *    español. Son 18 kB de rótulos que solo cambian al desplegar.
 *  · `session` es de quien mira, `no-store`, y **leerla consume** el desenlace de un pago pendiente
 *    (`SidebarEntry`), igual que lo consume pintar la página.
 */
class SidebarBootController extends Controller
{
    public function boot(Request $request): JsonResponse
    {
        $this->useRequestedLocale($request);

        // El subgrupo de la pantalla que completa un alta con Google viaja en el layout solo en SU
        // puerta, que aquí no existe: quien pide el arranque por la API no dice en qué página está.
        // Viaja si la instalación ofrece Google — son ~380 B, y sin ellos esa zona se pinta muda.
        return response()->json(SidebarBoot::shared(withGoogleSignup: GoogleAuth::enabled()));
    }

    public function session(Request $request): JsonResponse
    {
        $this->useRequestedLocale($request);

        $personal = SidebarBoot::personal();

        // ⚠️ Un grupo VACÍO de PHP es `[]` en JSON y uno lleno es `{}`: el mismo campo cambiaría de
        // tipo según haya sesión o no, y el cliente tendría que defenderse de su propia API. Aquí son
        // siempre diccionarios. (`SidebarBoot` los deja como arrays porque el layout los funde en PHP.)
        $personal['account'] = (object) $personal['account'];
        $personal['urls'] = (object) $personal['urls'];
        // Y `locales` va SIEMPRE, vacío sin sesión: en el layout la clave no viaja para el anónimo
        // (son bytes en cada página pública), pero una respuesta de API con forma fija es un campo
        // menos que el cliente tiene que comprobar antes de leer — y el contrato lo exige.
        $personal['locales'] ??= [];

        return response()->json($personal);
    }

    /** El idioma es parte de la petición, no del visitante: ver la cabecera de la clase. */
    private function useRequestedLocale(Request $request): void
    {
        $data = $request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale($data['lang']);
    }
}
