<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\SlotTemplate;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Generador de franjas (`slots`) por zona a partir de las plantillas semanales
 * (`slot_templates`), respetando el HORARIO EFECTIVO de cada día ({@see OperatingSchedule}:
 * `special_dates` → temporada → semanal). Fuente ÚNICA compartida por el comando CLI
 * `slots:generate` y el botón «Regenerar franjas» del panel (Fase 7.7 iter.3) — así la
 * lógica de generación no diverge entre la consola y la UI.
 *
 * Es idempotente (clave única `zone_id+date+start_time`). Dos opciones gobiernan la
 * robustez frente a ediciones del operador:
 *
 *  - `$preserveOverrides` (default true): NO reescribe el aforo (`capacity`/`online_capacity`)
 *    de las franjas marcadas a mano (`capacity_overridden`). `online_sales_open`/`status`
 *    NUNCA se tocan al actualizar (un cierre manual siempre sobrevive).
 *
 *  - `$prune` (default false): además de crear/actualizar, NEUTRALIZA las franjas obsoletas
 *    (las que ya no caben en el horario o no tienen plantilla activa) en las fechas ≥ hoy
 *    del rango. Para no perder ventas por el `cascadeOnDelete` de `order_items.slot_id`, una
 *    franja obsoleta solo se BORRA si no tiene NINGÚN `order_item` apuntándola; si tiene
 *    alguno (vivo o cancelado) se CIERRA (`online_sales_open=false`, `status=closed`). Jamás
 *    toca fechas pasadas (histórico intacto).
 */
class SlotGenerator
{
    public function __construct(private readonly OperatingSchedule $schedule) {}

    /**
     * Genera/actualiza (y opcionalmente poda) las franjas del rango [from, to] inclusive.
     *
     * @return array{generated:int, deleted:int, closed:int}
     */
    public function generate(CarbonInterface $from, CarbonInterface $to, bool $prune = false, bool $preserveOverrides = true): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $templates = SlotTemplate::where('is_active', true)->get();
        // "Hoy" en la zona OPERATIVA del parque (no UTC): la frontera de la poda no debe desfasar
        // cerca de medianoche (auditoría Fase 1). Se compara por fecha (string) para evitar TZ.
        $today = DisplayTime::today()->toDateString();

        $generated = 0;
        $deleted = 0;
        $closed = 0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $hours = $this->schedule->effectiveFor($day);

            /** @var array<string,true> $wanted claves "zone_id|start_time" que SÍ deben existir este día */
            $wanted = [];

            if ($hours['is_open']) {
                foreach ($templates->where('weekday', $day->dayOfWeek) as $template) {
                    $start = Carbon::parse($template->start_time);
                    $startStr = $start->format('H:i:s');
                    $endStr = $start->copy()->addMinutes($template->duration_min)->format('H:i:s');

                    // Franja inválida que cruza medianoche (fin ≤ inicio): no se genera (el parque
                    // no opera pasada la medianoche y un end_time < start_time rompería cálculos).
                    if ($endStr <= $startStr) {
                        continue;
                    }

                    // La franja debe caber dentro de la ventana del día (si está definida).
                    if ($hours['open'] !== null && $startStr < $hours['open']) {
                        continue;
                    }
                    if ($hours['close'] !== null && $endStr > $hours['close']) {
                        continue;
                    }

                    $this->upsertSlot($template, $day->toDateString(), $startStr, $endStr, $preserveOverrides);
                    $generated++;
                    $wanted[$template->zone_id.'|'.$startStr] = true;
                }
            }

            // La poda solo actúa de hoy en adelante (nunca reescribe el histórico).
            if ($prune && $day->toDateString() >= $today) {
                [$d, $c] = $this->pruneDay($day->toDateString(), $wanted);
                $deleted += $d;
                $closed += $c;
            }
        }

        return ['generated' => $generated, 'deleted' => $deleted, 'closed' => $closed];
    }

    /**
     * Regeneración RODANTE para el scheduler diario (auditoría Fase 1): mantiene las franjas
     * alineadas con el horizonte de venta [hoy, hoy+horizonte] y poda lo obsoleto. Sin esto las
     * franjas se "agotan" (solo existían hasta hoy+N días del último seed/generación manual) y el
     * calendario público se queda corto mientras el panel ofrecía días sin franjas.
     *
     * @return array{generated:int, deleted:int, closed:int}
     */
    public function generateRollingHorizon(): array
    {
        $from = DisplayTime::today();
        $to = DisplayTime::today()->addMonths(PaymentSettings::purchaseHorizonMonths());

        return $this->generate($from, $to, prune: true);
    }

    /**
     * Crea la franja si no existe, o la actualiza respetando el aforo ajustado a mano.
     */
    private function upsertSlot(SlotTemplate $template, string $date, string $startStr, string $endStr, bool $preserveOverrides): void
    {
        $slot = Slot::where('zone_id', $template->zone_id)
            ->where('date', $date)
            ->where('start_time', $startStr)
            ->first();

        if ($slot === null) {
            Slot::create([
                'zone_id' => $template->zone_id,
                'date' => $date,
                'start_time' => $startStr,
                'end_time' => $endStr,
                'capacity' => $template->capacity,
                'online_capacity' => $template->online_capacity,
            ]);

            return;
        }

        // El horario (end_time) siempre sigue a la plantilla; el aforo solo si no está
        // ajustado a mano. `online_sales_open`/`status` no se tocan aquí (cierre manual sobrevive).
        $slot->end_time = $endStr;
        if (! ($preserveOverrides && $slot->capacity_overridden)) {
            $slot->capacity = $template->capacity;
            $slot->online_capacity = $template->online_capacity;
        }
        $slot->save();
    }

    /**
     * Neutraliza las franjas obsoletas de un día (las que no están en $wanted): borra las que
     * no tienen reservas, cierra las que sí (para no perder ventas con el cascadeOnDelete).
     *
     * @param  array<string,true>  $wanted  claves "zone_id|start_time" vigentes este día
     * @return array{0:int,1:int} [borradas, cerradas]
     */
    private function pruneDay(string $date, array $wanted): array
    {
        $slots = Slot::where('date', $date)->get();
        if ($slots->isEmpty()) {
            return [0, 0];
        }

        $obsolete = $slots->reject(fn (Slot $s): bool => isset($wanted[$s->zone_id.'|'.$s->start_time]));
        if ($obsolete->isEmpty()) {
            return [0, 0];
        }

        // Qué franjas obsoletas tienen DEPENDIENTES que el cascadeOnDelete destruiría: tanto
        // `order_items.slot_id` como `tickets.slot_id` son cascade. Un slot puede tener tickets
        // emitidos pero ya ningún order_item (p. ej. tras reasignar la franja de un item en el
        // panel, que mueve el order_item pero NO sus tickets), así que hay que mirar AMBAS tablas.
        $ids = $obsolete->pluck('id');
        $withDependents = array_flip(array_values(array_unique(array_merge(
            OrderItem::whereIn('slot_id', $ids)->distinct()->pluck('slot_id')->all(),
            Ticket::whereIn('slot_id', $ids)->distinct()->pluck('slot_id')->all(),
        ))));

        $deleted = 0;
        $closed = 0;

        foreach ($obsolete as $slot) {
            if (isset($withDependents[$slot->id])) {
                // Tiene ventas/tickets → cerrar, no borrar (borrar destruiría esas filas por FK cascade).
                if ($slot->online_sales_open !== false || $slot->status !== Slot::STATUS_CLOSED) {
                    $slot->online_sales_open = false;
                    $slot->status = Slot::STATUS_CLOSED;
                    $slot->save();
                    $closed++;
                }

                continue;
            }

            // Candidata a BORRAR. El check de dependientes de arriba fue SIN lock (autocommit): un
            // checkout en vuelo (`OrderCreator`) pudo crear un `order_item` apuntando a esta franja
            // obsoleta-aún-comprable entre aquel SELECT y este borrado, y el `cascadeOnDelete` la
            // destruiría (auditoría Fase 1, M3 · TOCTOU). Serializamos con el checkout: bloqueamos la
            // franja (`lockForUpdate` espera al OrderCreator, que bloquea todas las franjas de la
            // zona/día) y RE-comprobamos dependientes DENTRO de la transacción; si apareció una venta,
            // CERRAMOS en vez de borrar. Transacción corta e independiente por franja → no acumula locks.
            $outcome = DB::transaction(function () use ($slot): string {
                $locked = Slot::whereKey($slot->id)->lockForUpdate()->first();
                if ($locked === null) {
                    return 'gone'; // otra poda concurrente ya la borró.
                }

                $hasDependents = OrderItem::where('slot_id', $locked->id)->exists()
                    || Ticket::where('slot_id', $locked->id)->exists();

                if ($hasDependents) {
                    if ($locked->online_sales_open !== false || $locked->status !== Slot::STATUS_CLOSED) {
                        $locked->online_sales_open = false;
                        $locked->status = Slot::STATUS_CLOSED;
                        $locked->save();

                        return 'closed';
                    }

                    return 'noop';
                }

                $locked->delete();

                return 'deleted';
            });

            if ($outcome === 'deleted') {
                $deleted++;
            } elseif ($outcome === 'closed') {
                $closed++;
            }
        }

        return [$deleted, $closed];
    }
}
