<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Temporada: un tramo de fechas [start_date, end_date] con un horario propio
 * (`open_time`/`close_time`) que SUSTITUYE al horario semanal base mientras está vigente.
 * Una temporada activa abre todos los días de su rango con ese horario (#207).
 * Resolución del horario efectivo: ver App\Support\ParkSchedule.
 */
class Season extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    /**
     * Temporadas activas vigentes en una fecha (rango inclusivo). Si varias solapan, el
     * llamador decide (ParkSchedule toma la de inicio más temprano).
     *
     * @param  Builder<Season>  $query
     * @return Builder<Season>
     */
    public function scopeActiveOn(Builder $query, CarbonInterface $date): Builder
    {
        $day = $date->toDateString();

        return $query->where('is_active', true)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day);
    }
}
