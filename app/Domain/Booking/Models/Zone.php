<?php

namespace App\Domain\Booking\Models;

use App\Domain\Content\Models\Attraction;
use App\Domain\Platform\Concerns\HasTranslations;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'subtitle' => 'array',
        'description' => 'array',
        'age_label' => 'array',
        'age_range' => 'array',
        /*
         * La REGLA DE ALTURA (`#478`). Enteros en centímetros, y **`null` significa «esta zona no
         * restringe por altura»**, no cero: es el caso normal fuera de un parque de saltos, y la
         * sección pregunta por el dato antes de dibujar la barra.
         * ⚠️ Dos columnas porque la misma cifra significa lo contrario según la zona: 130 es «al
         * menos» en la grande y «hasta» en la pequeña.
         */
        'height_min_cm' => 'integer',
        'height_max_cm' => 'integer',
        'is_active' => 'boolean',
        'show_in_landing' => 'boolean',
        // Cupo de packs POR ZONA (override del ajuste global; null = usa el global).
        'max_per_slot' => 'integer',
        'max_guests_per_slot' => 'integer',
        'prep_blocks_cupo' => 'boolean',
        // HORARIO POR ZONA (`#322`, `specs/horario-por-zona.md`): el MISMO contrato que la línea de
        // arriba —`null` = hereda el recinto—. ⚠️ `opens_at`/`closes_at` NO se castean a fecha: son
        // horas 'H:i:s' y `OperatingSchedule` las compara como cadenas contra las del recinto, que
        // vienen así de `opening_hours`. Castearlas a `datetime` las convertiría en un día concreto
        // y la comparación dejaría de ser la misma en los dos lados.
        'ignores_venue_closure' => 'boolean',
    ];

    /**
     * `#322` — las horas propias de la zona se normalizan a `H:i:s` AL ESCRIBIR, venga el valor de
     * donde venga (panel, seeder, importación).
     *
     * ⚠️⚠️ **No es cosmético, es un defecto de borde real.** `OperatingSchedule` compara estas horas
     * con las del recinto **como CADENAS** (`$endStr > $hours['close']`), y `opening_hours` las
     * guarda con segundos. El `TimePicker` del panel, con `seconds(false)`, escribía `'15:00'`: en la
     * comparación `'15:00:00' > '15:00'` es **verdadero** —una cadena más larga con el mismo prefijo
     * es mayor—, así que una franja que acaba EXACTAMENTE a la hora de cierre quedaba fuera. Lo
     * destapó la guarda del formulario, no una lectura.
     *
     * Se normaliza en el MODELO y no en el campo del panel para que ninguna otra superficie pueda
     * reintroducir el formato corto por su cuenta.
     */
    protected function opensAt(): Attribute
    {
        return self::normalizedTime();
    }

    protected function closesAt(): Attribute
    {
        return self::normalizedTime();
    }

    private static function normalizedTime(): Attribute
    {
        return Attribute::make(
            set: function (mixed $value): ?string {
                $raw = trim((string) ($value ?? ''));

                return $raw === '' ? null : CarbonImmutable::parse($raw)->format('H:i:s');
            },
        );
    }

    public function attractions(): HasMany
    {
        return $this->hasMany(Attraction::class)->orderBy('position');
    }
}
