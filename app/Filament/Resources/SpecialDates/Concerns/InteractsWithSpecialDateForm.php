<?php

namespace App\Filament\Resources\SpecialDates\Concerns;

use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Fase 7.7 — Lógica de formulario COMPARTIDA por crear (`CreateSpecialDate`) y editar
 * (`EditSpecialDate`) una fecha especial (mismo patrón que los traits del catálogo y de
 * las tarifas).
 *
 * Cubre las transformaciones independientes de la operación:
 *  - **Nota i18n** `{es,en,fr}`: descarta idiomas vacíos (es solo etiqueta interna).
 *  - **Coherencia de un día cerrado**: si `is_closed`, no tiene sentido una ventana horaria
 *    ni una tarifa → se anulan `open_time`/`close_time`/`rate_type_id` para que el dato
 *    quede limpio (un día cerrado no se vende). Defensa sobre el ocultado del form.
 */
trait InteractsWithSpecialDateForm
{
    /**
     * Deja `$data` listo para persistir.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function normalizeSpecialDateData(array $data): array
    {
        if (array_key_exists('note', $data)) {
            $data['note'] = $this->compactNote($data['note']);
        }

        $data['is_closed'] = (bool) ($data['is_closed'] ?? false);

        // Un día cerrado no tiene ni ventana ni precio: se limpia para no dejar datos inertes
        // (defensa sobre los `->visible()` del form, que solo ocultan, no dehidratan).
        if ($data['is_closed']) {
            $data['open_time'] = null;
            $data['close_time'] = null;
            $data['rate_type_id'] = null;

            return $data;
        }

        // Día abierto con ventana: el cierre debe ser posterior a la apertura (guarda
        // server-side, defensa sobre el `->after()` del form, que no siempre resuelve el
        // campo hermano bajo el statePath de Filament).
        $open = $data['open_time'] ?? null;
        $close = $data['close_time'] ?? null;
        if ($open !== null && $open !== '' && $close !== null && $close !== ''
            && Carbon::parse((string) $close)->lessThanOrEqualTo(Carbon::parse((string) $open))) {
            Notification::make()
                ->title(__('admin.special_dates.close_before_open'))
                ->danger()
                ->send();

            throw new Halt;
        }

        return $data;
    }

    /**
     * Compacta la nota i18n `{es,en,fr}`: descarta idiomas vacíos; null si todos lo están
     * (la columna es nullable).
     *
     * @return array<string,string>|null
     */
    protected function compactNote(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $clean = [];
        foreach ($value as $locale => $text) {
            $text = trim((string) $text);
            if ($text !== '') {
                $clean[$locale] = $text;
            }
        }

        return $clean === [] ? null : $clean;
    }
}
