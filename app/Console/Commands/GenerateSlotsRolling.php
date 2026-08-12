<?php

namespace App\Console\Commands;

use App\Domain\Booking\Services\SlotGenerator;
use Illuminate\Console\Command;

/**
 * Genera/poda las franjas hasta el HORIZONTE de venta (hoy → hoy+`purchaseHorizonMonths`),
 * usando las plantillas semanales y el horario efectivo de cada día. Idempotente y con poda
 * segura (no borra franjas con ventas). Pensado para:
 *  - el SCHEDULER diario (auditoría Fase 1: evita que el calendario "se agote"), y
 *  - el DESPLIEGUE / arranque (poblar el calendario antes del primer tick del cron) y para
 *    poblarlo a mano en desarrollo: `php artisan slots:generate-rolling`.
 *
 * Es un envoltorio fino de {@see SlotGenerator::generateRollingHorizon()} (la lógica vive ahí).
 */
class GenerateSlotsRolling extends Command
{
    protected $signature = 'slots:generate-rolling';

    protected $description = 'Genera/poda franjas hasta el horizonte de venta (hoy → hoy+horizonte). Idempotente.';

    public function handle(SlotGenerator $generator): int
    {
        $result = $generator->generateRollingHorizon();

        $this->info("Franjas generadas/actualizadas: {$result['generated']}; borradas: {$result['deleted']}; cerradas: {$result['closed']}.");

        return self::SUCCESS;
    }
}
