<?php

namespace App\Support;

/**
 * Formato humanizado de duraciones en minutos para la operativa del panel
 * admin (decisión #148 iter): el empleado piensa en horas, no en minutos
 * ("3h", "1h 30min", "45 min") porque las entradas y los packs están
 * organizados por horas. Es lo natural en el flujo de puerta.
 *
 * Centralizar aquí evita drift entre el partial del modal (resumen
 * operativo) y la sub-card de la lista de productos.
 */
class Duration
{
    /**
     * Formatea minutos como texto humano dependiendo del rango:
     *  - < 60 min  → "45 min"
     *  - múltiplo exacto de hora → "1h", "3h"
     *  - mixto → "1h 30min"
     *  - null o no positivo → null
     */
    public static function formatHumane(?int $minutes): ?string
    {
        if ($minutes === null || $minutes <= 0) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours === 0) {
            return __('admin.orders.item_detail.duration.minutes_only', ['minutes' => $mins]);
        }

        if ($mins === 0) {
            return __('admin.orders.item_detail.duration.hours_only', ['hours' => $hours]);
        }

        return __('admin.orders.item_detail.duration.hours_and_minutes', [
            'hours' => $hours,
            'minutes' => $mins,
        ]);
    }
}
