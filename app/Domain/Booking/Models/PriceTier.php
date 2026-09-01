<?php

namespace App\Domain\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * `#324` — un TRAMO de precio por cantidad (`docs/specs/precio-por-tramo.md`).
 *
 * «Desde `min_qty` unidades, cada una cuesta `amount_cents`», dentro de una tarifa concreta. El
 * tramo llega hasta que empieza el siguiente: **no hay máximo**, y por eso no puede haber huecos ni
 * solapes (spec §4.1).
 *
 * El precio es UNIFORME, no escalonado (`[DECIDIDO owner]`, preguntado con los dos números delante):
 * 70 personas a 13 € son **910 €**, no 30×15 + 40×13 = 970 €.
 */
class PriceTier extends Model
{
    protected $guarded = [];

    protected $casts = [
        'min_qty' => 'integer',
        'amount_cents' => 'integer',
    ];

    /**
     * `#324` — la OTRA MITAD de la guarda del cruce con el sello (`TicketType::booted()` cierra la
     * dirección «poner familia de edades a un producto con tramos»; ésta cierra «poner tramos a un
     * producto con familia de edades»).
     *
     * ⚠️ Hacen falta las DOS. Una sola deja la puerta entreabierta por el otro lado y no lo ve nadie:
     * es la lección de `#301`, donde un arreglo sobrevivió a la guarda que lo protegía porque solo
     * vigilaba un sentido.
     */
    protected static function booted(): void
    {
        static::saving(function (self $tier): void {
            $type = $tier->ticketType ?? TicketType::find($tier->ticket_type_id);

            if ($type?->guestAgeFamily() !== null) {
                throw new \InvalidArgumentException(
                    'Un producto con familia de edades no puede tener tramos de precio por cantidad: '
                    .'el sello congelaría un precio que el tramo movería después.'
                );
            }
        });
    }

    /** @return BelongsTo<TicketType, $this> */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /** @return BelongsTo<RateType, $this> */
    public function rateType(): BelongsTo
    {
        return $this->belongsTo(RateType::class);
    }

    /**
     * El precio del tramo que aplica a `$quantity` dentro de una colección YA CARGADA, o `null` si
     * ninguno lo cubre (y entonces manda el precio de siempre, `prices`).
     *
     * **Es el de mayor `min_qty` que no supera la cantidad.** Vive aquí y recibe la colección —en vez
     * de consultar— porque los seis sitios que resuelven precio tienen sus propias estrategias de
     * carga: `CartPricer` y `AvailabilityReader` leen relaciones precargadas a propósito para no
     * consultar por línea, y `RateResolver` consulta. **Una sola regla, dos formas de alimentarla**;
     * si la regla viviera en cada llamante serían seis copias de la misma aritmética de dinero.
     *
     * @param  Collection<int, PriceTier>  $tiers  tramos de UN producto (se filtra la tarifa aquí)
     */
    public static function resolve(Collection $tiers, int $rateTypeId, int $quantity): ?int
    {
        $winner = $tiers
            ->where('rate_type_id', $rateTypeId)
            ->where('min_qty', '<=', $quantity)
            ->sortByDesc('min_qty')
            ->first();

        return $winner?->amount_cents;
    }
}
