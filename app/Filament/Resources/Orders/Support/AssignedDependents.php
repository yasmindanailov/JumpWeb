<?php

namespace App\Filament\Resources\Orders\Support;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverStatus;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;

/**
 * Fase 6 · menores a cargo, tanda 5 — el READ-MODEL de «para quién es cada entrada» que enseña la ficha
 * del pedido (`docs/specs/menores-a-cargo.md` §9.10.2 D14·1).
 *
 * Vive en la capa de entrega porque COMPONE dos módulos que no pueden mirarse (Booking no ve a
 * Identity): las líneas del pedido (Booking) con las asignaciones y las firmas (Identity). Es la misma
 * forma que `OrderEventDataResource` en la API (D7), aquí para el panel.
 *
 *  - Solo líneas de ENTRADA: un pack pide a sus invitados por el post-form y nunca lleva menores.
 *  - La EDAD es la del día de la visita (D13), no la de hoy: es la que importa en la puerta.
 *  - El estado de la exención solo existe en modo interno (`waiver` = `null` fuera de él).
 *  - Presupuesto CONSTANTE: las asignaciones (con sus menores) + las firmas por lotes, sea cual sea
 *    el número de líneas o de menores — probado.
 *  - El nombre lo ve el operador porque ya lo ve en el registro del waiver (`#198`); la puerta no.
 */
final class AssignedDependents
{
    public const WAIVER_CURRENT = 'current';

    public const WAIVER_OUTDATED = 'outdated';

    public const WAIVER_MISSING = 'missing';

    /**
     * @return array<int, list<array{id:int, name:string, age:int, waiver:?string, removed:bool}>> por `order_item_id`; solo ítems con algo asignado
     */
    public static function forOrder(Order $order): array
    {
        $items = $order->items
            ->whereNull('parent_item_id')
            ->filter(fn (OrderItem $item): bool => $item->ticketType?->type === TicketType::TYPE_ENTRY)
            ->values();
        if ($items->isEmpty()) {
            return [];
        }

        $byItem = app(DependentAssigner::class)->forOrderItems(
            $items->map(fn (OrderItem $item): int => (int) $item->getKey())->all(),
            $items->mapWithKeys(fn (OrderItem $item): array => [(int) $item->getKey() => (int) $item->quantity])->all(),
        );
        if ($byItem === []) {
            return [];
        }

        $statuses = [];
        if (WaiverSettings::isInternal()) {
            $statuses = WaiverStatus::forDependents(
                collect($byItem)->flatten(1)->unique(fn (Dependent $d): int => (int) $d->getKey()),
            );
        }

        $out = [];
        foreach ($byItem as $itemId => $dependents) {
            $item = $items->firstWhere('id', $itemId);
            $day = $item?->slot?->date !== null
                ? CarbonImmutable::parse($item->slot->date)
                : DisplayTime::today();

            foreach ($dependents as $dependent) {
                $status = $statuses[(int) $dependent->getKey()] ?? null;
                $row = [
                    'id' => (int) $dependent->getKey(),
                    'name' => (string) $dependent->name,
                    'age' => $dependent->ageOn($day),
                    'waiver' => $status === null ? null : self::waiverState($status),
                    'removed' => $dependent->isRemoved(),
                ];
                $row['label'] = self::label($row);
                $out[(int) $itemId][] = $row;
            }
        }

        return $out;
    }

    /**
     * «Lucas (9 años · exención ✓ · retirado de la cuenta)» — compuesto AQUÍ y no en la vista: Livewire
     * intercala marcadores de bloque en cada `@if`, y un rótulo partido en tres no se puede afirmar
     * (ni leer) de una pieza.
     *
     * @param  array{name:string, age:int, waiver:?string, removed:bool}  $row
     */
    public static function label(array $row): string
    {
        $parts = [__('admin.orders.dependents.age', ['age' => $row['age']])];
        if ($row['waiver'] !== null) {
            $parts[] = __('admin.orders.dependents.waiver_'.$row['waiver']);
        }
        if ($row['removed']) {
            $parts[] = __('admin.orders.dependents.removed');
        }

        return $row['name'].' ('.implode(' · ', $parts).')';
    }

    private static function waiverState(WaiverStatus $status): string
    {
        if (! $status->signed) {
            return self::WAIVER_MISSING;
        }

        return $status->isOutdated() ? self::WAIVER_OUTDATED : self::WAIVER_CURRENT;
    }
}
