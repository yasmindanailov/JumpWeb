<?php

namespace App\Domain\Booking\Models;

use App\Domain\Booking\Services\WristbandWheel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * **Un color de pulsera del PARQUE** (`docs/specs/puerta-nueva.md` §4.4, la P2; D10).
 *
 * El producto no nombra colores (white-label): los pone cada parque en «Ajustes → Pulseras», con la FRASE que lee el
 * empleado en singular y en plural («pulsera lila» / «pulseras lilas») —la palabra y su concordancia son del parque, no
 * del producto— y su hex. Los que van «en la rueda», en su orden, se reparten las horas ({@see WristbandWheel});
 * un producto puede tener uno FIJO que gana a la rueda (`ticket_types.wristband_color_id`: la ilimitada, un cumpleaños).
 *
 * @property int $id
 * @property string $name_one
 * @property string $name_other
 * @property string $hex
 * @property bool $in_wheel
 * @property int $position
 */
class WristbandColor extends Model
{
    /** El hex que se guarda y el único que se pinta: va en un `style`, así que nada que no sea `#rrggbb` (D15). */
    public const HEX_RE = '/^#[0-9a-fA-F]{6}$/';

    protected $guarded = [];

    protected $casts = [
        'in_wheel' => 'boolean',
        'position' => 'integer',
    ];

    /**
     * Los productos que lo llevan FIJO. Un color en uso no se borra (la FK es `nullOnDelete`: borrarlo dejaría a esos
     * productos en la rueda sin que nadie lo pidiera).
     *
     * @return HasMany<TicketType, $this>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    /** La frase para `$count` pulseras: «pulsera lila» con una, «pulseras lilas» con varias. */
    public function phrase(int $count): string
    {
        return $count === 1 ? $this->name_one : $this->name_other;
    }

    /** ¿Su hex es pintable? Uno corrupto (escrito saltándose el panel) es una loseta neutra, nunca un `style` roto. */
    public function hasValidHex(): bool
    {
        return preg_match(self::HEX_RE, (string) $this->hex) === 1;
    }
}
