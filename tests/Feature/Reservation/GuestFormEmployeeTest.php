<?php

namespace Tests\Feature\Reservation;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ReservationSlip;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Notifications\GuestFormRequest;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Post-form de datos por invitado (#217, iter. 3) — cara EMPLEADO: badge de estado en el feed del
 * calendario, reenvío del email (RESEND_TYPE_GUEST_FORM) y tabla por-niño en el PDF de la hoja.
 */
class GuestFormEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $pack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'color' => '#FF5B22']);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 2,
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'allergy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergia']],
            ],
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function paidPackOrder(?array $guestData, int $qty = 2, ?Slot $slot = null): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => $slot?->id, 'quantity' => $qty,
            'unit_price' => 1000, 'seats' => $qty, 'guest_data' => $guestData,
        ]);

        return $order;
    }

    // ─── Calendario: badge formStatus en el feed ─────────────────────────────

    public function test_feed_emits_form_status_pending_for_incomplete_pack(): void
    {
        $date = Carbon::today()->toDateString();
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        $this->paidPackOrder(guestData: null, slot: $slot); // sin datos → pendiente

        $from = Carbon::parse($date)->subDay()->toDateString();
        $to = Carbon::parse($date)->addDay()->toDateString();

        $this->actingAs($this->admin())
            ->getJson("/admin/calendario/eventos?start={$from}&end={$to}")
            ->assertOk()
            ->assertJsonPath('0.extendedProps.formStatus', 'pending');
    }

    public function test_feed_emits_form_status_ok_when_complete(): void
    {
        $date = Carbon::today()->toDateString();
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        $this->paidPackOrder(guestData: [['name' => 'Ana'], ['name' => 'Leo']], slot: $slot);

        $from = Carbon::parse($date)->subDay()->toDateString();
        $to = Carbon::parse($date)->addDay()->toDateString();

        $this->actingAs($this->admin())
            ->getJson("/admin/calendario/eventos?start={$from}&end={$to}")
            ->assertJsonPath('0.extendedProps.formStatus', 'ok');
    }

    public function test_feed_form_status_is_null_for_entry(): void
    {
        $entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $date = Carbon::today()->toDateString();
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $entry->id, 'slot_id' => $slot->id, 'quantity' => 1, 'unit_price' => 1000, 'seats' => 1,
        ]);

        $from = Carbon::parse($date)->subDay()->toDateString();
        $to = Carbon::parse($date)->addDay()->toDateString();

        $this->actingAs($this->admin())
            ->getJson("/admin/calendario/eventos?start={$from}&end={$to}")
            ->assertJsonPath('0.extendedProps.formStatus', null);
    }

    // ─── Reenviar email ──────────────────────────────────────────────────────

    public function test_paid_pack_order_offers_guest_form_resend(): void
    {
        $order = $this->paidPackOrder(guestData: null)->load('items.ticketType');

        $this->assertContains(Order::RESEND_TYPE_GUEST_FORM, $order->availableResendEmailTypes());
    }

    public function test_resend_dispatches_guest_form_request(): void
    {
        Notification::fake();
        $order = $this->paidPackOrder(guestData: null);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_GUEST_FORM]);

        Notification::assertSentTo($order->user, GuestFormRequest::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'orders.email_resent']);
    }

    public function test_resend_sends_one_email_per_pack_reservation(): void
    {
        // Individualizado POR RESERVA (#217): un pedido con DOS cumpleaños reenvía DOS emails, cada
        // uno con el enlace a SU post-form (no un único email del pedido).
        Notification::fake();
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'total' => 2000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $packB = TicketType::create([
            'name' => ['es' => 'Cumpleaños Kids'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 90, 'min_qty' => 2, 'max_qty' => 15, 'seats_per_unit' => 1, 'is_sellable' => true,
            'is_active' => true, 'position' => 3,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        $order->items()->create(['ticket_type_id' => $this->pack->id, 'quantity' => 2, 'unit_price' => 1000, 'seats' => 2]);
        $order->items()->create(['ticket_type_id' => $packB->id, 'quantity' => 3, 'unit_price' => 800, 'seats' => 3]);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('resendEmail', data: ['email_type' => Order::RESEND_TYPE_GUEST_FORM]);

        Notification::assertSentToTimes($order->user, GuestFormRequest::class, 2);
    }

    // ─── PDF: tabla por-niño ─────────────────────────────────────────────────

    public function test_slip_presenter_builds_guest_rows(): void
    {
        $order = $this->paidPackOrder(guestData: [['name' => 'Ana', 'allergy' => 'Gluten'], ['name' => 'Leo']]);
        $item = $order->items()->first();

        $slip = ReservationSlip::make($order, $item);

        $this->assertSame(['name', 'allergy'], array_column($slip->guestColumns(), 'key'));
        $this->assertTrue($slip->guestFormComplete());
        // Una fila por invitado (= cantidad), en orden de columnas. ▶ Desde la T6·4 cada fila dice
        // además si lo que trae viene de la invitación **sin repasar** por el cliente (`#711`).
        $this->assertSame([
            ['cells' => ['Ana', 'Gluten'], 'proposed' => false],
            ['cells' => ['Leo', ''], 'proposed' => false],
        ], $slip->guestRows());
    }

    public function test_slip_pads_blank_rows_when_incomplete(): void
    {
        $order = $this->paidPackOrder(guestData: [['name' => 'Ana']], qty: 3); // faltan 2
        $item = $order->items()->first();

        $slip = ReservationSlip::make($order, $item);

        $this->assertFalse($slip->guestFormComplete());
        // 3 filas: la 1.ª con datos, las 2 restantes en blanco para rellenar a mano.
        $this->assertSame([
            ['cells' => ['Ana', ''], 'proposed' => false],
            ['cells' => ['', ''], 'proposed' => false],
            ['cells' => ['', ''], 'proposed' => false],
        ], $slip->guestRows());
    }

    public function test_slip_view_renders_guest_form_section(): void
    {
        App::setLocale('es');
        $order = $this->paidPackOrder(guestData: [['name' => 'Ana'], ['name' => 'Leo']]);
        $item = $order->items()->first();
        $slip = ReservationSlip::make($order, $item);

        $html = view('pdf.reservation-slip', ['slip' => $slip])->render();

        $this->assertStringContainsString(__('admin.orders.slip.guests_heading'), $html);
        $this->assertStringContainsString('Ana', $html);
        $this->assertStringContainsString('Leo', $html);
        // No saca la clave i18n cruda (el PDF va en español, sin claves sin traducir).
        $this->assertStringNotContainsString('admin.orders.slip.', $html);
    }
}
