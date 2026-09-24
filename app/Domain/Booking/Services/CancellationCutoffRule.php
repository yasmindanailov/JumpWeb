<?php

namespace App\Domain\Booking\Services;

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

        if ($hours >= self::HORAS_ANTES_DE_DIAS && $hours % 24 === 0) {
            return __('landing.products.cancellation_days', ['n' => intdiv($hours, 24)]);
        }

        return __('landing.products.cancellation_hours', ['n' => $hours]);
    }
}
