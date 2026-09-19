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

    /**
     * @param  list<int>  $reservationIds
     * @return array<int, list<array{reply_id: int, name: string, key: string, companion: string|null, pending: bool}>>
     */
    public function partyGuestsIn(array $reservationIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(intval(...), $reservationIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        // UNA consulta para todas las reservas: el presupuesto de la puerta no admite una por fiesta.
        $replies = InvitationReply::query()
            ->whereIn('order_item_id', $ids)
            ->where('attending', true)
            ->whereNull('dismissed_at')
            ->orderBy('id')
            ->get(['id', 'order_item_id', 'child_name', 'child_key', 'adopted_at', 'adopted_name_key', 'companion']);

        $out = [];

        foreach ($replies->groupBy('order_item_id') as $reservationId => $group) {
            $out[(int) $reservationId] = $group
                // Una por niño y la MÁS RECIENTE, como el resto de este lector (V6).
                ->keyBy('child_key')
                ->map(static fn (InvitationReply $reply): array => [
                    'reply_id' => (int) $reply->getKey(),
                    'name' => (string) $reply->child_name,
                    'key' => (string) ($reply->adopted_name_key ?? $reply->child_key),
                    'companion' => $reply->companion === null ? null : (string) $reply->companion,
                    'pending' => $reply->adopted_at === null,
                ])
                ->values()
                ->all();
        }

        return $out;
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
