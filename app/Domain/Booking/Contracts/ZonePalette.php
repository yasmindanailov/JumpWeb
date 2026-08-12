<?php

namespace App\Domain\Booking\Contracts;

/**
 * El color propio de una zona, por su token de acento (Fase 2, paso 7).
 *
 * La zona es un concepto de BOOKING (tiene aforo, productos y franjas), pero su color lo pinta
 * Content: `ThemeSettings` lo leía con una consulta directa a `zones`. Una sola lectura, un solo
 * método — extraído literalmente de esa llamada.
 *
 * Implementación: `App\Domain\Booking\Services\ZonePaletteReader`.
 */
interface ZonePalette
{
    /** Color `#rrggbb` de la zona con ese acento, o `null` si no hay zona o no tiene color. */
    public function colorFor(string $accent): ?string;
}
