<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\CartPricing;
use App\Http\Api\CartPayload;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QuoteResource;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 4a — el PRESUPUESTO de una cesta (`docs/specs/api-v1.md` §4.4 y §4.6.4).
 *
 * Sigue sin haber endpoints de carrito —el carrito es estado del cliente—, pero el servidor sí
 * participa en él, y este es uno de los tres momentos en que lo hace: **el precio**. Sin este
 * endpoint, un cliente que quiera enseñar el total antes de comprar solo tiene dos salidas, y las
 * dos son malas: crear un pedido (que bloquea aforo) o reimplementar `RateResolver` +
 * `AddonResolver` + `depositCents()`, que es la segunda fuente de verdad que §4.6.4 quiere evitar.
 *
 * El controlador **no suma nada**: pide a `Booking\Contracts\CartPricing`, el mismo contrato que
 * consume el sidebar de la web desde este paso. Que los dos importes que devuelve son los que se
 * cobrarán no es una promesa de este docblock: `CartPricerTest` crea el pedido de verdad con la
 * misma cesta y compara.
 *
 * **Público y sin estado.** Público porque la web deja llegar hasta el pago como invitado y pedir
 * identidad aquí cerraría ese flujo. Sin estado porque un presupuesto no reserva: no bloquea aforo,
 * no comprueba disponibilidad y no admite la reserva. Un precio calculado a las 12:00 puede no
 * poder comprarse a las 12:05, y quien decide eso es `OrderCreator` bajo lock (`AFORO-01`).
 *
 * **Por qué es `POST` si no cambia nada**: la cesta no cabe con garantías en una URL —líneas,
 * complementos anidados y respuestas del evento— y meterla en la query string la dejaría escrita en
 * los logs de cualquier proxy. El método dice cómo viaja el cuerpo, no que haya efectos.
 *
 * ⚠️ Se llama `Quote*` a propósito: ese prefijo dispara el gate de concurrencia del `pre-push`
 * (`INVARIANTES §6`). Este endpoint no toca aforo, pero vive en el camino del dinero y comparte
 * contrato con el checkout; el criterio de nombres del paso 1a (`MeOrdersController`) es el mismo
 * al revés — lo que roza el dinero entra en el gate, aunque cueste dos verificadores.
 */
class QuoteController extends Controller
{
    public function __invoke(Request $request, CartPricing $pricing): QuoteResource
    {
        // La FORMA del cuerpo se valida aquí y no se sanea en silencio: el dominio descarta las
        // líneas que no encajan —lo correcto para una cesta de sesión heredada—, pero a un cliente
        // de API hay que decirle qué campo trae mal, no devolverle un presupuesto con menos líneas.
        $validated = $request->validate(CartPayload::rules());

        return new QuoteResource($pricing->quote(CartPayload::toCart($validated['items'])));
    }
}
