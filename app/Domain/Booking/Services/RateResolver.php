<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Models\TicketType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        $key = $date->toDateString();

        return $this->forDates([$key])[$key];
    }

    /**
     * Tarifa aplicable a CADA una de las fechas dadas, con **una sola consulta** de excepciones.
     *
     * Existe porque un calendario resuelve decenas de días seguidos y `for()` consulta
     * `special_dates` por llamada: pintar un mes costaba una consulta por celda. Es la misma regla
     * —de hecho `for()` delega aquí—, no una copia rápida para el calendario: dos resoluciones de
     * tarifa que puedan divergir serían dos precios distintos para el mismo día.
     *
     * @param  array<int, string>  $dates  fechas en formato `Y-m-d`
     * @return array<string, RateType> indexado por esa misma fecha
     */
    public function forDates(array $dates): array
    {
        $dates = array_values(array_unique($dates));

        if ($dates === []) {
            return [];
        }

        $specials = SpecialDate::with('rateType')
            ->whereIn('date', $dates)
            ->get()
            ->keyBy(fn (SpecialDate $special): string => $special->date->toDateString());

        // Las tarifas no cambian dentro de una misma petición: se memorizan.
        $rateTypes = once(fn () => RateType::where('is_active', true)->orderByDesc('priority')->get());

        $resolved = [];
        foreach ($dates as $date) {
            // Una excepción SIN tarifa asociada (p. ej. un día cerrado) no cambia el precio: cae al
            // día de la semana, igual que antes de existir este método.
            $special = $specials->get($date)?->rateType;

            if ($special) {
                $resolved[$date] = $special;

                continue;
            }

            $weekday = Carbon::parse($date)->dayOfWeek; // 0=domingo .. 6=sábado

            $resolved[$date] = $rateTypes->first(
                fn (RateType $rate) => is_array($rate->weekdays) && in_array($weekday, $rate->weekdays, true)
            )
                ?? $rateTypes->firstWhere('key', RateType::KEY_NORMAL)
                ?? RateType::where('key', RateType::KEY_NORMAL)->firstOrFail();
        }

        return $resolved;
    }

    /**
     * Precio (céntimos) de un producto para la fecha dada; null si no hay precio
     * definido para la tarifa aplicable. El producto debe exponer la relación `prices`
     * (p. ej. `TicketType`).
     */
    /**
     * Precio unitario del producto ese día, en céntimos.
     *
     * ▶ `#324` — admite la CANTIDAD (`docs/specs/precio-por-tramo.md`). Si el producto declara tramos
     * de volumen y alguno cubre esa cantidad, manda el tramo; si no, el precio de siempre. El default
     * a 1 deja idéntica la conducta de todo llamante que no la pase, que es lo que permitió
     * convertirlos uno a uno con su caso en vez de en un solo commit a ciegas.
     *
     * ⚠️ Los tramos son de `TicketType` y no de cualquier `priceable`: un complemento no se vende por
     * volumen. Preguntarlo por `instanceof` y no por la relación evita que un `Attraction` o un
     * `ProductAddon` acabe con una tabla de tramos que nadie diseñó.
     */
    public function priceCents(Model $priceable, CarbonInterface $date, int $quantity = 1): ?int
    {
        $rate = $this->for($date);

        // ⚠️ La regla de tramos (a quién se le preguntan, y con qué cantidad) vive ENTERA en
        // `TicketType::tierPriceCents()`: la exclusión de los complementos y el suelo de la escala
        // en el mínimo contratable (`#327`). Estaba escrita aquí y en `priceCentsForRate()`, que son
        // los dos caminos por los que se tarifica una línea de verdad — dos copias de una regla de
        // dinero es un sitio donde puede divergir sin que falle nada.
        if ($priceable instanceof TicketType) {
            $tier = $priceable->tierPriceCents((int) $rate->id, $quantity);
            if ($tier !== null) {
                return $tier;
            }
        }

        /** @var int|null $cents */
        $cents = $priceable->prices()
            ->where('rate_type_id', $rate->id)
            ->value('amount_cents');

        return $cents;
    }
}
