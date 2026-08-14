<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\CartLineValidation;
use App\Http\Api\CartPayload;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CartLineVerdictResource;
use Illuminate\Http\Request;

/**
 * Fase 4 · paso 4.0b·6 — **¿puedo añadir esta línea a mi cesta?** (`sidebar-spa.md` §4.4.2,
 * `DECISIONES #38(f)`).
 *
 * Es el hueco que ninguno de los cinco diseños del paso vio, y el único de toda la revisión que —de
 * ignorarse— se descubre en producción y no en la suite. Hoy la compra web valida en el servidor en
 * cada clic de «añadir»; con la cesta de la SPA en `localStorage` **no queda ninguna ida y vuelta**,
 * así que la alternativa a este endpoint era transcribir la regla a JavaScript.
 *
 * ⚠️ **El caso que lo justifica solo**: el saneo de un campo `number` aplica
 * `preg_replace('/\D+/', '')`, así que la EDAD contestada «cinco» el servidor la ve **vacía** y
 * cualquier validación ingenua en el cliente la ve contestada. Sin este endpoint, el cliente lo
 * descubriría cinco pasos después, ya identificado, con un 422 que ni siquiera nombra los campos.
 *
 * **Responde 200 aunque la línea no sirva**, con el veredicto en el cuerpo. Es el mismo patrón que
 * `GET me/reservation-eligibility`: preguntar «¿puedo?» y que te digan «no, y por esto» no es un
 * error de la petición. Lo que sí es 422 es la FORMA (una fecha que no es fecha), y eso lo decide
 * `CartPayload`, que es donde vive la forma de una línea para toda la API.
 *
 * **Público**, como el presupuesto y la disponibilidad: la web deja llegar hasta el pago sin cuenta,
 * y exigir identidad para preguntar si una línea cabe cerraría ese flujo. Al ser pública, el
 * `throttle:api` del grupo sí la cuenta.
 *
 * **No retiene nada.** Lo que dice es cierto en el instante en que se dice; el juez final sigue
 * siendo `OrderCreator` bajo lock (`AFORO-01`), que revalida todo al crear el pedido. Este endpoint
 * ahorra el viaje en balde, no sustituye al checkout.
 */
class CartLineController extends Controller
{
    public function __invoke(Request $request, CartLineValidation $lines): CartLineVerdictResource
    {
        $validated = $request->validate([
            'line' => ['required', 'array'],
            // La MISMA forma que una línea de cesta, `CartLine` en el contrato: un cliente guarda
            // una sola idea de qué es una línea. Lo que este endpoint añade no es otra forma, es el
            // veredicto de NEGOCIO sobre ella.
            ...CartPayload::lineRules('line'),
            // La cesta ACTUAL, sin la candidata: es lo que ya retiene cupo. Opcional porque la
            // primera línea de una compra se añade sobre una cesta vacía.
            ...CartPayload::rules(requireItems: false),
        ]);

        return new CartLineVerdictResource($lines->validate(
            CartPayload::toCart($validated['items'] ?? []),
            CartPayload::toLine($validated['line']),
        ));
    }
}
