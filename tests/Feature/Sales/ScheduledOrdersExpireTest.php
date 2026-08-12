<?php

namespace Tests\Feature\Sales;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit hardening #113 (A1, 2026-05-28) — Scheduler para `orders:expire`.
 *
 * Sin scheduler, el comando solo correría manualmente. El test verifica que está REGISTRADO
 * en `routes/console.php` con la cadencia esperada. En PRODUCCIÓN (Fase 9), además, el cron
 * del servidor debe llamar a `php artisan schedule:run` cada minuto — verificable en
 * `docs/PLAN-REDSYS.md §14` y `docs/ESTADO.md`.
 */
class ScheduledOrdersExpireTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_expire_command_is_scheduled_every_five_minutes(): void
    {
        $schedule = app(Schedule::class);

        $matching = collect($schedule->events())->filter(function ($event): bool {
            return str_contains((string) $event->command, 'orders:expire');
        });

        $this->assertCount(1, $matching, 'orders:expire debe estar programado exactamente UNA vez');

        $event = $matching->first();
        // Cron expression de "everyFiveMinutes" en Laravel = "*/5 * * * *".
        $this->assertSame('*/5 * * * *', $event->expression,
            'orders:expire debe correr cada 5 minutos');
    }
}
