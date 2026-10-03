<?php

namespace Tests\Feature\Account;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * **Los datos de los invitados, 14 días después de la visita** (`#863`, `specs/textos-legales.md` §4.4, `RGPD-01`): la
 * privacidad promete el plazo y esto es lo que lo cumple. Corre el COMANDO, no el método: una tarea que no esté en el
 * planificador es una promesa que nadie cumple.
 */
class GuestDataRetentionTest extends TestCase
{
    use RefreshDatabase;

    private const GUESTS = [['name' => 'Hugo Ruiz', 'allergies' => 'frutos secos']];

    public function test_guest_data_is_forgotten_fourteen_days_after_the_visit_and_not_before(): void
    {
        // 23:30 en UTC ya es el 2 en el parque (UTC+2): el plazo se cuenta en la zona del PARQUE.
        $this->travelTo(Carbon::parse('2026-10-01 23:30:00', 'UTC'));

        $old = $this->reservation('2026-09-17');     // en el parque es el 2-10: la visita fue hace 15 días
        $recent = $this->reservation('2026-09-18');  // hace 14: su enlace del post-form aún abre hoy

        $this->assertSame(0, Artisan::call('guest-data:forget'));

        $old->refresh();
        $recent->refresh();
        $this->assertNull($old->guest_data, 'pasado el plazo, los invitados se borran');
        $this->assertNull($old->event_data, 'y las respuestas del evento (el nombre del homenajeado)');
        $this->assertSame(10, (int) $old->quantity, 'el pedido conserva sus cantidades');
        $this->assertSame(500, (int) $old->unit_price, 'y sus importes');
        $this->assertSame(self::GUESTS, $recent->guest_data, 'dentro del plazo no se toca (el control)');
        $this->assertSame(['celebrant' => 'Lucía'], $recent->event_data);
    }

    public function test_the_legacy_audit_trail_of_a_forgotten_visit_loses_the_honorees_name(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00', 'UTC'));
        $old = $this->reservation('2026-09-10');
        $recent = $this->reservation('2026-09-30');
        $legacyOld = $this->legacyTrail($old);
        $legacyRecent = $this->legacyTrail($recent);

        Artisan::call('guest-data:forget');

        $this->assertNull($legacyOld->fresh()->payload, 'el rastro viejo llevaba el nombre de un menor');
        $this->assertSame('x', $legacyOld->fresh()->payload_hash, 'la huella del rastro se queda');
        $this->assertNotNull($legacyRecent->fresh()->payload, 'el de una visita dentro del plazo, no (el control)');
    }

    public function test_the_task_is_scheduled_and_the_deploy_expects_it(): void
    {
        $this->assertStringContainsString("Schedule::command('guest-data:forget')", (string) file_get_contents(base_path('routes/console.php')));
        $this->assertStringContainsString('(esperadas 14)', (string) file_get_contents(base_path('scripts/deploy.sh')));
    }

    private function reservation(string $date): OrderItem
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $product = TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Cumpleaños'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 2, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => $date,
            'start_time' => '17:00:00', 'end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200,
        ]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'R-'.mb_strtoupper(mb_substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $product->id, 'slot_id' => $slot->id,
            'quantity' => 10, 'unit_price' => 500, 'seats' => 10,
        ]);
        $item->forceFill(['guest_data' => self::GUESTS, 'event_data' => ['celebrant' => 'Lucía']])->saveQuietly();

        return $item;
    }

    private function legacyTrail(OrderItem $item): AuditLog
    {
        return AuditLog::create([
            'action' => 'order_items.event_data_updated',
            'target_type' => (new OrderItem)->getMorphClass(),
            'target_id' => $item->id,
            'payload' => ['diff' => ['changed' => ['celebrant' => ['Antiguo', 'Lucía']]]],
            'payload_hash' => 'x',
            'created_at' => now(),
        ]);
    }
}
