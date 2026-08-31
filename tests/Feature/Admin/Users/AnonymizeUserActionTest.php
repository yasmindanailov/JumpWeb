<?php

namespace Tests\Feature\Admin\Users;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Users\Pages\ViewUser;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.5 — Acción "Anonimizar" de `ViewUser` (decisión #180).
 *
 * Solo sobre clientes (no admin/staff, no uno mismo, no ya anonimizada). El audit
 * guarda el HASH del email + el motivo + conteos, nunca PII en claro.
 */
class AnonymizeUserActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function customerWithConsents(): User
    {
        $u = $this->userWithRole('customer');
        foreach (['privacy', 'terms', 'waiver'] as $type) {
            $u->consents()->create([
                'type' => $type,
                'accepted_at' => now(),
                'ip' => '127.0.0.1',
                'version' => Consent::CURRENT_VERSION,
            ]);
        }

        return $u;
    }

    public function test_anonymize_visible_on_customer_for_admin(): void
    {
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $this->userWithRole('customer')->id])
            ->assertActionVisible('anonymizeUser');
    }

    public function test_anonymize_hidden_on_admin_target(): void
    {
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $this->userWithRole('admin')->id])
            ->assertActionHidden('anonymizeUser');
    }

    public function test_anonymize_hidden_on_staff_target(): void
    {
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $this->userWithRole('staff')->id])
            ->assertActionHidden('anonymizeUser');
    }

    public function test_anonymize_hidden_on_self(): void
    {
        $admin = $this->userWithRole('admin');

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $admin->id])
            ->assertActionHidden('anonymizeUser');
    }

    public function test_anonymize_hidden_on_already_anonymized(): void
    {
        $customer = $this->userWithRole('customer');
        $customer->anonymize();

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertActionHidden('anonymizeUser');
    }

    public function test_anonymize_happy_path_neutralizes_and_audits(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = $this->customerWithConsents();
        $originalEmail = $customer->email;

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $customer->id])
            ->callAction('anonymizeUser', data: ['reason' => 'Solicitud RGPD #42']);

        $customer->refresh();
        $this->assertTrue($customer->isAnonymized());
        $this->assertSame('Cliente eliminado', $customer->name);
        $this->assertNull($customer->phone);
        $this->assertSame(0, $customer->consents()->count());
        $this->assertSame(0, $customer->roles()->count());

        $log = AuditLog::where('action', 'users.anonymized')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($customer->id, (int) $log->target_id);
        $this->assertSame('Solicitud RGPD #42', $log->payload['reason']);
        $this->assertSame(hash('sha256', $originalEmail), $log->payload['email_hash']);
        $this->assertArrayNotHasKey('email', $log->payload);
        $this->assertSame(3, $log->payload['consents_deleted']);
    }

    /**
     * ⚠️⚠️ T5 · D8 (`cumple-mixto.md` §25.4, `[DECIDIDO owner]` §25.9 Q2: las TRES vías): con una
     * reserva POR CELEBRAR tampoco el operador puede anonimizar — si pudiera, una reserva viva
     * quedaría anónima con su importe congelado y la ficha del «techo tras anonimizar» seguiría
     * abierta. Su escape es cancelar primero (el control de abajo).
     *
     * Mutación que la valida: quitar la re-comprobación de `hasUpcomingFor` en la acción.
     */
    public function test_anonymize_is_blocked_while_the_customer_has_an_upcoming_reservation(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = $this->customerWithConsents();
        $this->paidReservationFor($customer, now()->addDays(10)->toDateString());

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $customer->id])
            ->callAction('anonymizeUser', data: ['reason' => 'Solicitud RGPD #43']);

        $customer->refresh();
        $this->assertFalse($customer->isAnonymized(), 'la puerta de D8 no frenó al panel');

        $log = AuditLog::where('action', 'users.anonymize_blocked')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('upcoming_reservations', $log->payload['reason']);
        $this->assertNull(AuditLog::where('action', 'users.anonymized')->first());
    }

    /** El control de la puerta: con la reserva ya CELEBRADA, la misma acción sí anonimiza. */
    public function test_anonymize_works_once_the_reservation_has_passed(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = $this->customerWithConsents();
        $this->paidReservationFor($customer, now()->subDays(10)->toDateString());

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $customer->id])
            ->callAction('anonymizeUser', data: ['reason' => 'Solicitud RGPD #44']);

        $this->assertTrue($customer->refresh()->isAnonymized());
    }

    /** Reserva pagada mínima (principal con franja) para ejercitar la puerta de D8. */
    private function paidReservationFor(User $user, string $slotDate): void
    {
        $zone = Zone::create(['slug' => 'z-'.Str::lower(Str::random(5)), 'name' => ['es' => 'Zona']]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => $slotDate,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 50, 'online_capacity' => 50,
        ]);
        $type = TicketType::create([
            'name' => ['es' => 'Cumple'], 'zone_id' => $zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JW-D8-'.Str::upper(Str::random(5)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 9000, 'tax' => 0, 'total' => 9000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 9000, 'seats' => 1,
        ]);
    }

    public function test_anonymize_scrubs_ip_and_user_agent_from_actor_audit_rows(): void
    {
        // RGPD (recomendación A, #246/sesión): las filas de `audit_logs` donde el titular fue el
        // ACTOR (`user_id`) llevan su IP/user-agent (dato personal, Breyer) → al anonimizar deben
        // nulificarse (art. 17). Las filas donde es el TARGET (actor = operador) conservan la IP del
        // operador (no es PII del titular).
        $admin = $this->userWithRole('admin');
        $customer = $this->customerWithConsents();

        $actorRow = AuditLog::create([
            'user_id' => $customer->id, 'action' => 'orders.guest_form_submitted',
            'ip' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0 cliente',
            'payload_hash' => hash('sha256', 'a'), 'created_at' => now(),
        ]);
        $targetRow = AuditLog::create([
            'user_id' => $admin->id, 'action' => 'users.anonymized',
            'target_type' => (new User)->getMorphClass(), 'target_id' => $customer->id,
            'ip' => '198.51.100.9', 'user_agent' => 'panel admin',
            'payload_hash' => hash('sha256', 'b'), 'created_at' => now(),
        ]);

        $customer->anonymize();

        $actorRow->refresh();
        $this->assertNull($actorRow->ip, 'la IP del titular-actor se nulifica');
        $this->assertNull($actorRow->user_agent, 'el user-agent del titular-actor se nulifica');

        $targetRow->refresh();
        $this->assertSame('198.51.100.9', $targetRow->ip, 'la IP del operador (actor) se conserva');
    }
}
