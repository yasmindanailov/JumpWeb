<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReservationEligibilityResource;
use Illuminate\Http\Request;

/**
 * Fase 4 · paso 4.0b — el aviso TEMPRANO de «¿puedo reservar?» (`docs/specs/sidebar-spa.md` §4.4).
 *
 * La web lo usa desde siempre: al pasar del carrito al paso de pago, el sidebar pregunta antes de
 * llevar al cliente a una pantalla donde se va a estrellar. La API no lo tenía — solo `POST /orders`,
 * que **crea el pedido y retiene aforo**—, así que la SPA de Fase 4 no podía avisar de nada sin
 * comprometerse a comprar.
 *
 * ⚠️ **Llama a `mayReserve()`, que NO consume ficha, y esa es toda la razón de que este endpoint
 * exista.** `admitReservation()` gasta un intento del limitador, y usarlo aquí rompería una conducta
 * con test: la SEGUNDA compra del mismo minuto dejaría de poder confirmarse por el simple hecho de
 * que el cliente pasó dos veces por la pantalla de la cesta. Es el hallazgo que el paso 2 de Fase 3
 * ya había pagado una vez —«el limitador contaba pantallas en vez de reservas»— y que
 * `CheckoutSequenceTest` impide ahora que se repita: `admitReservation` está prohibido fuera de
 * `app/Domain`.
 *
 * **200 aunque el veredicto sea que no.** Un «no puedes reservar» es una consulta que salió BIEN;
 * devolverlo como error obligaría al cliente a tratar como fallo una respuesta perfectamente útil, y
 * a distinguirla de los errores de verdad por el `code`. El motivo va dentro del cuerpo.
 *
 * **Sin parámetros: ni ruta, ni query, ni cuerpo.** Es media defensa anti-oráculo — la única fuente
 * de identidad es el guard, así que no hay forma de preguntar por la elegibilidad de otro. La otra
 * mitad es lo que el recurso NO devuelve.
 *
 * **Sin límite propio, y es una decisión**: el suelo de `throttle:api` ya lo cubre, y un limitador
 * más estrecho aquí castigaría justo al cliente que navega mucho — que es la conducta que este
 * endpoint existe para hacer agradable. Tampoco necesita `RequiresStatefulSession`: no toca
 * `session()` en ningún punto; sin `Origin` *stateful* no hay sesión, el guard no encuentra usuario
 * y responde 401, que es la verdad.
 */
class MeReservationEligibilityController extends Controller
{
    public function __invoke(Request $request, ReservationAdmission $admission): ReservationEligibilityResource
    {
        /** @var User $user */
        $user = $request->user();

        return new ReservationEligibilityResource(
            $admission->mayReserve((int) $user->getAuthIdentifier())
        );
    }
}
