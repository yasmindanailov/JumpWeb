<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\ZonePalette;
use App\Domain\Booking\Models\Zone;

/**
 * Implementa `ZonePalette`. Es exactamente la consulta que `Content\Services\ThemeSettings`
 * hacía a mano sobre `zones`, ahora del lado que posee la tabla.
 */
class ZonePaletteReader implements ZonePalette
{
    public function colorFor(string $accent): ?string
    {
        return Zone::query()->where('accent', $accent)->value('color');
    }
}
