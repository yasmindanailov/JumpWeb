<?php

namespace App\Domain\Booking\Models;

use App\Domain\Content\Models\Attraction;
use App\Domain\Platform\Concerns\HasTranslations;
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

    public function attractions(): HasMany
    {
        return $this->hasMany(Attraction::class)->orderBy('position');
    }
}
