<?php

namespace Tests\Feature\Invitation;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\PersonNameKey;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **ANULAR el enlace de una invitación digital** (T4·4 de
 * `docs/specs/celebracion-e-invitacion.md` §4.5·11 y §4.8; `DECISIONES #576`).
 *
 * Ese enlace se reparte **a un grupo de clase entero**, así que es la clase de credencial que hay que
 * poder cerrar antes de que caduque — y la única palanca es ésta, porque el token no es una credencial
 * de la cuenta y `RGPD-06` no lo alcanza.
 *
 * Hermano de `GuestFormLinkCopyTest`, y vigila lo mismo que aquél:
 *  1. que rote de verdad —el enlace viejo deja de valer—;
 *  2. que el permiso se re-exija **al ejecutar** (`SEC-04`) y el intento **quede auditado**: un bloqueo
 *     silencioso no deja ver que alguien lo intentó;
 *  3. que el rastro **no lleve el token**, que es la credencial (`RGPD-02`);
 *  4. y que rotar **no deshaga una gestión**: lo que los padres ya contestaron sigue ahí.
 */
class InvitationLinkRotationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 12:00:00', 'UTC'));
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_the_operator_can_void_the_link_and_it_is_audited_without_the_token(): void
    {
        [$order, $item, $invitation] = $this->reservationWithInvitation();
        $before = (string) $invitation->token;

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('rotateInvitationLink', arguments: ['item' => $item->id]);

        $after = (string) $invitation->refresh()->token;

        $this->assertNotSame($before, $after, 'el enlace viejo tiene que dejar de valer');
        $this->assertSame(PartyInvitation::TOKEN_LENGTH, mb_strlen($after));
        $this->assertDatabaseHas('audit_logs', ['action' => 'orders.invitation_link_rotated']);

        // `RGPD-02`: ni el token viejo ni el nuevo pueden aparecer en el rastro.
        $payloads = (string) DB::table('audit_logs')
            ->where('action', 'orders.invitation_link_rotated')
            ->value('payload');

        $this->assertStringNotContainsString($before, $payloads, 'el token viejo no puede quedar en claro');
        $this->assertStringNotContainsString($after, $payloads, 'ni el nuevo: es la credencial');
    }

    /**
     * `SEC-04`: el permiso se re-exige en el MOMENTO de ejecutar. Un empleado sin
     * `orders.edit_guest_data` no anula aunque fuerce la acción, y el intento **queda auditado**.
     */
    public function test_without_the_permission_it_neither_rotates_nor_stays_silent(): void
    {
        [$order, $item, $invitation] = $this->reservationWithInvitation();
        $before = (string) $invitation->token;

        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $staff->roles()->first()->permissions()->detach(
            Permission::where('name', 'orders.edit_guest_data')->value('id')
        );

        Livewire::actingAs($staff)
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('rotateInvitationLink', arguments: ['item' => $item->id]);

        $this->assertSame($before, (string) $invitation->refresh()->token, 'sin permiso no se anula nada');
        $this->assertDatabaseHas('audit_logs', ['action' => 'orders.invitation_link_rotate_blocked']);
    }

    /** Una reserva SIN invitación no tiene enlace que anular, y el intento también deja rastro. */
    public function test_a_reservation_without_an_invitation_has_nothing_to_void(): void
    {
        [$order, $item] = $this->reservationWithInvitation(withInvitation: false);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('rotateInvitationLink', arguments: ['item' => $item->id]);

        $this->assertSame(0, PartyInvitation::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'orders.invitation_link_rotate_blocked']);
    }

    /**
     * ⚠️ **Rotar retira una CREDENCIAL, no deshace una GESTIÓN.** Lo que los padres ya contestaron
     * sigue ahí: quitar una respuesta es otro gesto del anfitrión («no lo apuntes»).
     */
    public function test_voiding_the_link_never_erases_what_parents_already_answered(): void
    {
        [$order, $item, $invitation] = $this->reservationWithInvitation();
        InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $item->getKey(),
            'attending' => true,
            'child_name' => 'Hugo Ruiz',
            'child_key' => PersonNameKey::for('Hugo Ruiz'),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('rotateInvitationLink', arguments: ['item' => $item->id]);

        $this->assertSame(1, InvitationReply::query()->count());
        $this->assertSame('Hugo Ruiz', (string) InvitationReply::query()->value('child_name'));
    }

    // ─── Fixtures ─────────────────────────────────────────────────────────────

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $admin;
    }

    /** @return array{0: Order, 1: OrderItem, 2: PartyInvitation|null} */
    private function reservationWithInvitation(bool $withInvitation = true): array
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Cumpleaños'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => $withInvitation,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Homenajeado']],
            ],
        ]);
        $slot = Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => '2026-11-14', 'start_time' => '17:00:00'],
            ['end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200],
        );
        $order = Order::create([
            'user_id' => User::factory()->create(['name' => 'Marta Anfitriona'])->id,
            'code' => 'R-'.mb_strtoupper(mb_substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 6, 'unit_price' => 500, 'seats' => 6,
            'event_data' => ['celebrant' => 'Lucía'],
        ]);

        $invitation = app(PartyInvitations::class)->forReservation($item->fresh(['ticketType', 'slot', 'order']));

        return [$order, $item, $invitation];
    }
}
