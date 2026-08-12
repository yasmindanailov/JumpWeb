<?php

namespace App\Console\Commands;

use App\Domain\Booking\Services\SlotGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Genera las franjas concretas (`slots`) por zona a partir de las plantillas
 * (`slot_templates`) para un rango de fechas. Idempotente (no duplica). Respeta el
 * HORARIO EFECTIVO de cada día (`OperatingSchedule`: `special_dates` → temporada → `opening_hours`):
 * salta los días cerrados y solo crea franjas que caben dentro de [apertura, cierre]. Si un
 * día no tiene horario configurado, no se restringe (fallback no destructivo).
 *
 * La lógica vive en {@see SlotGenerator} (fuente única compartida con el botón «Regenerar
 * franjas» del panel, Fase 7.7 iter.3). Por defecto preserva el aforo ajustado a mano y NO
 * poda; usa `--prune` para limpiar también las franjas obsoletas (≥ hoy, sin borrar las que
 * tengan ventas).
 */
class GenerateSlots extends Command
{
    protected $signature = 'slots:generate {from : Fecha inicial (Y-m-d)} {to : Fecha final (Y-m-d)} {--prune : Limpia también las franjas obsoletas (≥ hoy) sin reservas}';

    protected $description = 'Genera franjas (slots) por zona desde las plantillas, para el rango de fechas dado.';

    public function handle(SlotGenerator $generator): int
    {
        $from = Carbon::parse($this->argument('from'))->startOfDay();
        $to = Carbon::parse($this->argument('to'))->startOfDay();

        if ($to->lt($from)) {
            $this->error('La fecha "to" debe ser igual o posterior a "from".');

            return self::FAILURE;
        }

        $result = $generator->generate($from, $to, prune: (bool) $this->option('prune'));

        $this->info("Franjas generadas/actualizadas: {$result['generated']}.");
        if ($this->option('prune')) {
            $this->info("Franjas obsoletas borradas: {$result['deleted']}; cerradas (con reservas): {$result['closed']}.");
        }

        return self::SUCCESS;
    }
}
