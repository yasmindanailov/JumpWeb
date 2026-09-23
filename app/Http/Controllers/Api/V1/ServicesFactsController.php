<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Content\Models\LandingService;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\Api\V1\ServicesFactsResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `GET /api/v1/services?lang=` — las SECCIONES de servicios, como hechos (F5 · el menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * ⚠️ **Solo las ACTIVAS**, igual que normas y dudas: `is_active` es el interruptor con el que el negocio
 * retira una sección, y publicarla por la API la devolvería a la web por la puerta de atrás.
 *
 * ⚠️⚠️ **El desempate por `id`, otra vez y por el mismo motivo que en `/faqs`**: `position` no es única
 * —la migración la declara `unsignedInteger` con `default(0)`, sin índice—, así que sin segundo criterio
 * el orden de un empate lo decide el motor y una respuesta cacheada con `ETag` podría barajar.
 * ▶ Aquí **no** se usa `scopeOrdered()` a propósito: ordena solo por `position`, y cambiarlo tocaría
 * también la página que lo comparte. Lo que esta ruta necesita es determinismo, y se lo da aquí.
 *
 * ⚠️ **`products.zone` y `products.prices` se cargan de golpe** porque `isSellablePackForLanding()` los
 * mira uno a uno (`zone.is_active` y un precio positivo): sin esto, una instalación con veinte servicios
 * haría dos consultas por servicio para contestar «¿tiene algo que vender?».
 */
class ServicesFactsController extends Controller
{
    public function __invoke(Request $request): ServicesFactsResource
    {
        $datos = $request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale($datos['lang']);

        return new ServicesFactsResource(
            LandingService::query()
                ->active()
                ->with(['products.zone', 'products.prices'])
                ->orderBy('position')
                ->orderBy('id')
                ->get()
        );
    }
}
