<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Carbon;

/**
 * **El plazo de cambio y cancelación de un producto, ya redactado** (T4a·1, `DECISIONES #699`).
 *
 * Se guarda en HORAS (`ticket_types.cancellation_cutoff_hours`) y se escribe como lo dice una persona: en horas por
 * debajo de dos días («hasta 24 h antes») y en días a partir de ahí, si son días justos («hasta 3 días antes»).
 * Un plazo que no sea de días justos se queda en horas: redondear sería prometer otro.
 *
 * ⚠️ **Lo INFORMA, no lo aplica**: los cambios y las cancelaciones los hace el personal (las condiciones lo
 * dicen). Los «3 días naturales» de las condiciones son 72 h contadas desde la hora reservada: la misma fecha
 * límite, y una sola cifra que el panel cambia en un sitio.
 *
 * ▶ Desde la T5b (`#775`, Mi cuenta en la isla) también da su **tramo** («24 h», «3 días»: «Quedan menos de…») y la
 * **fecha límite** de una reserva concreta. Las tres salen de la MISMA cifra y la misma regla de horas o días.
 */
final class CancellationCutoffRule
{
    /** Por debajo de esto, en horas: «hasta 24 h antes» se lee mejor que «hasta 1 día antes». */
    private const HORAS_ANTES_DE_DIAS = 48;

    public function written(?int $hours): ?string
    {
        if ($hours === null) {
            return null;
        }

        if ($hours === 0) {
            return __('landing.products.cancellation_at_start');
        }

        if ($this->enDias($hours)) {
            return __('landing.products.cancellation_days', ['n' => intdiv($hours, 24)]);
        }

        return __('landing.products.cancellation_hours', ['n' => $hours]);
    }

    /**
     * El TRAMO del plazo, sin el «hasta … antes»: «24 h», «3 días» (con espacio duro, `#763`). Lo que Mi cuenta
     * dice cuando ya ha pasado: «Quedan menos de 24 h: ya no se puede cambiar ni cancelar». `null` sin plazo, o con
     * uno de CERO horas (hasta la hora reservada no queda un tramo que nombrar).
     */
    public function span(?int $hours): ?string
    {
        if ($hours === null || $hours === 0) {
            return null;
        }

        return $this->enDias($hours)
            ? __('landing.products.cancellation_span_days', ['n' => intdiv($hours, 24)])
            : __('landing.products.cancellation_span_hours', ['n' => $hours]);
    }

    /**
     * **La fecha límite de ESTA reserva**: el inicio de su franja, en la zona del parque, menos el plazo de su
     * producto. `null` si el producto no publica plazo o la reserva no tiene franja (una franja siempre lleva día y
     * hora: son columnas NOT NULL).
     */
    public function deadlineFor(OrderItem $item): ?Carbon
    {
        $hours = $item->ticketType?->cancellation_cutoff_hours;
        $slot = $item->slot;

        if ($hours === null || $slot === null) {
            return null;
        }

        return Carbon::parse($slot->date->format('Y-m-d').' '.$slot->start_time, DisplayTime::timezone())->subHours($hours);
    }

    private function enDias(int $hours): bool
    {
        return $hours >= self::HORAS_ANTES_DE_DIAS && $hours % 24 === 0;
    }
}
