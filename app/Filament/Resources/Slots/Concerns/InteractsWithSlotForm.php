<?php

namespace App\Filament\Resources\Slots\Concerns;

use App\Domain\Booking\Models\Slot;
use App\Filament\Resources\Slots\SlotResource;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Fase 7.7 iter.3 — Normalización + invariantes server-side al guardar una franja:
 *
 *  - El toggle de venta gobierna AMBOS mecanismos de cierre en tándem (coherente con
 *    `Slot::scopeSellableOnline`): ON → online_sales_open=true + status=open (reabre incluso
 *    una franja cerrada por la poda); OFF → online_sales_open=false + status=closed.
 *  - El aforo online solo se edita en zonas de entradas (la de cumpleaños va por cupo): se
 *    valida 0 ≤ aforo ≤ aforo total y aforo ≥ ocupación viva (no se puede dejar por debajo de
 *    lo ya vendido). Si el aforo cambia respecto a lo guardado, se marca `capacity_overridden`
 *    para que «Regenerar franjas» no lo pise.
 */
trait InteractsWithSlotForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prepareSlotData(array $data): array
    {
        /** @var Slot $slot */
        $slot = $this->record;

        $open = (bool) ($data['online_sales_open'] ?? true);
        $data['online_sales_open'] = $open;
        $data['status'] = $open ? Slot::STATUS_OPEN : Slot::STATUS_CLOSED;

        if (SlotResource::isPackZone($slot)) {
            // El aforo de packs va por cupo: nunca se edita por franja.
            unset($data['online_capacity']);
            $data['capacity_overridden'] = false;

            return $data;
        }

        $new = (int) ($data['online_capacity'] ?? $slot->online_capacity);

        if ($new < 0) {
            $this->failSlot(__('admin.slots.errors.negative'));
        }
        if ($new > $slot->capacity) {
            $this->failSlot(__('admin.slots.errors.above_total', ['total' => $slot->capacity]));
        }
        $occupancy = SlotResource::liveOccupancy($slot);
        if ($new < $occupancy) {
            $this->failSlot(__('admin.slots.errors.below_occupancy', ['occupancy' => $occupancy]));
        }

        $data['online_capacity'] = $new;

        // Pin: si el operador cambia el aforo, queda "ajustado a mano" y la regeneración lo respeta.
        if ($new !== (int) $slot->online_capacity) {
            $data['capacity_overridden'] = true;
        }

        return $data;
    }

    private function failSlot(string $message): void
    {
        Notification::make()->title($message)->danger()->send();

        throw new Halt;
    }
}
