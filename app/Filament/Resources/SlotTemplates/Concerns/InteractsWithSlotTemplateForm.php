<?php

namespace App\Filament\Resources\SlotTemplates\Concerns;

use App\Domain\Booking\Models\SlotTemplate;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Fase 7.7 iter.3 — Normalización + invariantes server-side al crear/editar una plantilla:
 * normaliza la hora a 'H:i:s', coacciona enteros (el form devuelve strings), exige
 * `online_capacity ≤ capacity`, y garantiza la unicidad (zona, día, hora) además del índice
 * de BD (mensaje claro antes de chocar con la restricción).
 */
trait InteractsWithSlotTemplateForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prepareTemplateData(array $data, ?int $ignoreId = null): array
    {
        if (! empty($data['start_time'])) {
            $data['start_time'] = Carbon::parse((string) $data['start_time'])->format('H:i:s');
        }

        $data['weekday'] = (int) ($data['weekday'] ?? 0);
        $data['duration_min'] = (int) ($data['duration_min'] ?? 0);
        $data['capacity'] = (int) ($data['capacity'] ?? 0);
        $data['online_capacity'] = (int) ($data['online_capacity'] ?? 0);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        if ($data['online_capacity'] > $data['capacity']) {
            $this->failTemplate(__('admin.slot_templates.errors.online_above_total'));
        }

        $duplicate = SlotTemplate::query()
            ->where('zone_id', $data['zone_id'])
            ->where('weekday', $data['weekday'])
            ->where('start_time', $data['start_time'])
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($duplicate) {
            $this->failTemplate(__('admin.slot_templates.errors.duplicate'));
        }

        return $data;
    }

    private function failTemplate(string $message): void
    {
        Notification::make()->title($message)->danger()->send();

        throw new Halt;
    }
}
