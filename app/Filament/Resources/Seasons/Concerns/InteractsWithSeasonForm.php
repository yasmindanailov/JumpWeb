<?php

namespace App\Filament\Resources\Seasons\Concerns;

use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Fase 7.7 — Validación server-side compartida por crear y editar una temporada (defensa
 * sobre los `->after()` del form, que no siempre resuelven el campo hermano bajo el
 * statePath de Filament): la fecha de fin no puede ser anterior a la de inicio y el cierre
 * debe ser posterior a la apertura.
 */
trait InteractsWithSeasonForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function validateSeasonData(array $data): array
    {
        $start = $data['start_date'] ?? null;
        $end = $data['end_date'] ?? null;
        if ($start && $end && Carbon::parse((string) $end)->lessThan(Carbon::parse((string) $start))) {
            $this->fail(__('admin.seasons.end_before_start'));
        }

        $open = $data['open_time'] ?? null;
        $close = $data['close_time'] ?? null;
        if ($open && $close && Carbon::parse((string) $close)->lessThanOrEqualTo(Carbon::parse((string) $open))) {
            $this->fail(__('admin.seasons.close_before_open'));
        }

        // Normaliza las horas a 'H:i:s' (el TimePicker entrega 'H:i'): datos consistentes con
        // el horario semanal/seeder y con lo que consume OperatingSchedule.
        foreach (['open_time', 'close_time'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = Carbon::parse((string) $data[$field])->format('H:i:s');
            }
        }

        return $data;
    }

    private function fail(string $message): void
    {
        Notification::make()->title($message)->danger()->send();

        throw new Halt;
    }
}
