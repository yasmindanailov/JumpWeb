<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use App\Http\Controllers\Controller;
use App\Http\Cuenta\AntesDeVenir;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * **«Antes de venir» de UNA reserva del titular** (T5c de `specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #776`): sus
 * tareas con su plazo real, ya escritas en el idioma de la petición (`Http\Cuenta\AntesDeVenir`, que no decide reglas:
 * las pregunta a cada servicio).
 *
 * ❗ **Es su propia ruta y no un campo de `OrderItem`**, por dos razones que ya se pagaron en el justificante
 * (`OrderGuestMinorsController`): (1) cuesta sus consultas —respuestas, extras, firmas— y `OrderItem` viaja en listas de
 * hasta 50 reservas; Mi cuenta la pide solo para la que enseña. (2) «Compartir por WhatsApp» lleva el ENLACE de la
 * invitación, que es la credencial con la que se contesta: no se siembra en ninguna página, solo sale de aquí, a su
 * titular y sin caché (`NoStoreWhenAuthenticated`, `RGPD-04`).
 *
 * Una reserva ajena responde igual que una inexistente (404): un 403 confirmaría que existe. Una de un pedido sin pagar,
 * cancelada o ya celebrada responde con la lista VACÍA: no tiene nada pendiente.
 */
class MeReservationBeforeVisitController extends Controller
{
    public function __invoke(Request $request, int $reservation, AntesDeVenir $antes): JsonResponse
    {
        $item = OrderItem::query()
            ->whereKey($reservation)
            ->whereNull('parent_item_id')
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->getAuthIdentifier()))
            ->with(['ticketType', 'slot', 'order'])
            ->first();

        abort_if($item === null, 404);

        return response()->json(['data' => [
            'reservation_id' => (int) $item->getKey(),
            'tasks' => $antes->tareasDe($item),
        ]]);
    }
}
