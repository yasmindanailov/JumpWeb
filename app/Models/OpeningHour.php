<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Horario semanal del parque (recurrente), por día de la semana (Carbon dayOfWeek:
 * 0=domingo..6=sábado). Las franjas a la venta solo existen dentro de [open_time, close_time].
 * Las excepciones puntuales viven en `special_dates` y tienen prioridad (ver App\Support\ParkSchedule).
 * Transversal §9.1 del plan; valores reales [PENDIENTE], editables en el panel (Fase 7).
 */
class OpeningHour extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weekday' => 'integer',
        'is_closed' => 'boolean',
    ];
}
