<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\SpecialDate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Decide qué tarifa (`rate_type`) aplica a una fecha y devuelve el precio de un
 * producto para ese día (#59). Orden de resolución:
 *   1) Si `special_dates` marca ese día con una tarifa → esa (festivo/víspera).
 *   2) Si el día de la semana activa una tarifa (p. ej. finde) → la de mayor prioridad.
 *   3) Si no → `normal`.
 * Ver docs/04-MODELO-DATOS.md (§4bis).
 */
class RateResolver
{
    /** Tarifa aplicable a la fecha dada. */
    public function for(CarbonInterface $date): RateType
    {
        $special = SpecialDate::with('rateType')
            ->where('date', $date->toDateString())
            ->first();

        if ($special && $special->rateType) {
            return $special->rateType;
        }

        $weekday = $date->dayOfWeek; // 0=domingo .. 6=sábado

        // Las tarifas no cambian dentro de una misma petición: se memorizan (el calendario
        // resuelve la tarifa de muchos días seguidos).
        $rateTypes = once(fn () => RateType::where('is_active', true)->orderByDesc('priority')->get());

        $byWeekday = $rateTypes->first(
            fn (RateType $rate) => is_array($rate->weekdays) && in_array($weekday, $rate->weekdays, true)
        );

        return $byWeekday
            ?? $rateTypes->firstWhere('key', RateType::KEY_NORMAL)
            ?? RateType::where('key', RateType::KEY_NORMAL)->firstOrFail();
    }

    /**
     * Precio (céntimos) de un producto para la fecha dada; null si no hay precio
     * definido para la tarifa aplicable. El producto debe exponer la relación `prices`
     * (p. ej. `TicketType`).
     */
    public function priceCents(Model $priceable, CarbonInterface $date): ?int
    {
        $rate = $this->for($date);

        /** @var int|null $cents */
        $cents = $priceable->prices()
            ->where('rate_type_id', $rate->id)
            ->value('amount_cents');

        return $cents;
    }
}
