<?php

namespace App\Support;

use App\Models\TicketType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Ventana de disponibilidad de un PRODUCTO dentro del horario del día (transversal §9.2).
 *
 * Dos niveles, ambos contra el horario EFECTIVO del día (`ParkSchedule`: fecha especial →
 * temporada → semanal):
 *  1. **Ventana base**: la franja debe empezar dentro de `[apertura, cierre)` del día. Se
 *     aplica SIEMPRE (es la misma ventana que usa el generador de franjas `slots:generate`).
 *  2. **Offsets del producto** (restricción ADICIONAL, default 0 = sin offset extra):
 *     disponible desde apertura+available_after_open_min hasta cierre−available_before_close_min.
 *
 * Si el día no tiene un extremo configurado (apertura/cierre nulo) ese lado no se ancla y no
 * restringe (fallback no destructivo). El aforo/preparación de packs es de la Capa 2 y no se
 * trata aquí.
 *
 * **#208:** antes la ventana base solo se aplicaba si el producto tenía offsets, así que con
 * los offsets a 0 (el default) una franja fuera del horario del día se ofrecía igual (la
 * reserva no respetaba la franja horaria). Ahora la ventana base se enforce siempre.
 */
class ProductAvailability
{
    public function __construct(private ParkSchedule $schedule) {}

    /** ¿Se puede comprar/entrar $product en una franja que empieza a $startTime ('H:i:s') el día $date? */
    public function allowsStart(TicketType $product, CarbonInterface $date, string $startTime): bool
    {
        $hours = $this->schedule->effectiveFor($date);
        if (! $hours['is_open']) {
            return false;
        }

        // 1) Ventana base del día [apertura, cierre): la franja debe empezar dentro del horario,
        // independientemente de los offsets del producto. Misma ventana que `slots:generate`.
        if ($hours['open'] !== null && $startTime < $hours['open']) {
            return false;
        }
        if ($hours['close'] !== null && $startTime >= $hours['close']) {
            return false;
        }

        // 2) Offsets del producto (restricción adicional sobre la ventana base).
        if ($hours['open'] !== null && $product->available_after_open_min) {
            $min = Carbon::parse($hours['open'])->addMinutes($product->available_after_open_min)->format('H:i:s');
            if ($startTime < $min) {
                return false;
            }
        }

        if ($hours['close'] !== null && $product->available_before_close_min) {
            $max = Carbon::parse($hours['close'])->subMinutes($product->available_before_close_min)->format('H:i:s');
            if ($startTime > $max) {
                return false;
            }
        }

        return true;
    }
}
