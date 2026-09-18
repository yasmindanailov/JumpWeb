<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Models\InvitationReply;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación de {@see PartyGuests} (`specs/celebracion-e-invitacion.md` §4.5·8, `DECISIONES #576`).
 *
 * ⚠️ **Una por niño.** Dos respuestas con el mismo `child_key` son el mismo niño —un padre que contesta
 * dos veces, o los dos progenitores— y ocupan **una** plaza (V6). Se agrupa por clave y se devuelve la
 * más RECIENTE de cada grupo, que es la que manda en la propuesta al anfitrión (§4.5·5).
 *
 * ⚠️ Un «no» no aparece nunca: no ocupa nada (D3). Y una respuesta DESCARTADA por el anfitrión tampoco
 * —«no lo apuntes» es justamente la palanca de §7.2·R11 para poder bajar invitados—.
 */
final class PartyGuestsReader implements PartyGuests
{
    /** @return list<int> */
    public function committedReplyIdsIn(int $reservationId): array
    {
        return $this->liveYesQuery($reservationId)
            ->orderBy('id')
            ->get(['id', 'child_key'])
            // La MÁS RECIENTE de cada niño: `keyBy` conserva la última al repetirse la clave, y la
            // consulta va ordenada por id ascendente.
            ->keyBy('child_key')
            ->map(static fn (InvitationReply $reply): int => (int) $reply->getKey())
            ->values()
            ->all();
    }

    public function isCommittedReply(int $replyId, int $reservationId): bool
    {
        return $this->liveYesQuery($reservationId)->whereKey($replyId)->exists();
    }

    /** @return Builder<InvitationReply> */
    private function liveYesQuery(int $reservationId)
    {
        return InvitationReply::query()
            ->where('order_item_id', $reservationId)
            ->where('attending', true)
            ->whereNull('dismissed_at');
    }
}
