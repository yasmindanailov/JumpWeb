<?php

namespace Tests\Feature\Queue;

use App\Mail\ContactMessageMail;
use App\Mail\PaymentIncidentMail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

/**
 * Cola de emails (D, 2026-06-15, `DECISIONES #243`) — contrato: NINGÚN email transaccional se
 * envía de forma síncrona dentro de la petición del usuario. Todas las notificaciones y los
 * mailables son `ShouldQueue`, y hay un worker que vacía la cola desde el `schedule:run` del cron.
 */
class QueuedEmailsTest extends TestCase
{
    public function test_all_notifications_implement_should_queue(): void
    {
        $files = glob(app_path('Notifications/*.php'));
        $this->assertNotEmpty($files, 'no se encontraron notificaciones');

        foreach ($files as $file) {
            $class = 'App\\Notifications\\'.basename($file, '.php');
            $this->assertTrue(
                is_subclass_of($class, ShouldQueue::class),
                "{$class} debe implementar ShouldQueue: ningún email se manda síncrono (bloquearía la petición con el SMTP)."
            );
        }
    }

    public function test_transactional_mailables_implement_should_queue(): void
    {
        $this->assertTrue(is_subclass_of(ContactMessageMail::class, ShouldQueue::class));
        $this->assertTrue(is_subclass_of(PaymentIncidentMail::class, ShouldQueue::class));
    }

    public function test_a_queue_worker_is_scheduled_to_drain_the_queue(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event): string => (string) $event->command)
            ->implode(' || ');

        // Sin worker, marcar ShouldQueue dejaría los emails atascados en `jobs` sin enviarse.
        $this->assertStringContainsString('queue:work', $commands);
        $this->assertStringContainsString('--stop-when-empty', $commands);
    }
}
