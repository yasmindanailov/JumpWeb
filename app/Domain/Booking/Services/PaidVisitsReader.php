<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\PaidVisits;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Database\Eloquent\Builder;

/**
 * La implementación de {@see PaidVisits} (`docs/specs/encuestas.md` §4.7, T5): una consulta por pregunta, sobre la
 * regla de siempre (`OrderItem::paidScheduledPrincipal()`) acotada al titular. Solo lee.
 */
final class PaidVisitsReader implements PaidVisits
{
    public function kindOn(int $userId, string $day): ?string
    {
        $items = $this->paidOf($userId)
            ->slotDateBetween($day, $day)
            ->with(['ticketType' => static fn ($query) => $query->select(['id', 'type'])->withCount('priceTiers')])
            ->get(['id', 'ticket_type_id']);

        if ($items->isEmpty()) {
            return null;
        }
        if ($items->contains(static fn (OrderItem $item): bool => $item->ticketType?->type === TicketType::TYPE_PACK)) {
            return self::KIND_PARTY;
        }
        if ($items->contains(static fn (OrderItem $item): bool => (int) ($item->ticketType?->getAttribute('price_tiers_count') ?? 0) > 0)) {
            return self::KIND_GROUP;
        }

        return self::KIND_ENTRY;
    }

    public function anyBefore(int $userId, string $day): bool
    {
        return Slot::query()->whereIn('id', $this->paidOf($userId)->select('slot_id'))->where('date', '<', $day)->exists();
    }

    public function firstBetween(int $userId, string $after, string $until): ?string
    {
        $first = Slot::query()->whereIn('id', $this->paidOf($userId)->select('slot_id'))
            ->where('date', '>', $after)
            ->where('date', '<=', $until)
            ->min('date');

        return $first === null ? null : substr((string) $first, 0, 10);
    }

    public function firstPaidDays(array $userIds): array
    {
        $first = [];
        // En tandas: una lista de clientes de un trimestre no cabe entera en un `IN` de SQLite.
        foreach (array_chunk(array_values(array_unique($userIds)), 500) as $chunk) {
            $items = OrderItem::query()
                ->paidScheduledPrincipal()
                ->whereHas('order', static fn ($query) => $query->whereIn('user_id', $chunk))
                ->with(['order:id,user_id', 'slot:id,date'])
                ->get(['id', 'order_id', 'slot_id']);
            foreach ($items as $item) {
                if ($item->order === null || $item->slot === null) {
                    continue;
                }
                $userId = (int) $item->order->user_id;
                $day = substr((string) $item->slot->getRawOriginal('date'), 0, 10);
                if (! isset($first[$userId]) || $day < $first[$userId]) {
                    $first[$userId] = $day;
                }
            }
        }

        return $first;
    }

    /** @return Builder<OrderItem> */
    private function paidOf(int $userId): Builder
    {
        return OrderItem::query()
            ->paidScheduledPrincipal()
            ->whereHas('order', static fn ($query) => $query->where('user_id', $userId));
    }
}
