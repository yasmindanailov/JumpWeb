<?php

namespace Tests\Feature\Site;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Support\CustomerAccountContext;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cuenta migrada al sidebar (#221): el servicio `CustomerAccountContext` (saludo + próxima
 * reserva + formularios #217 pendientes) y el render del bloque del sidecart, el icono del nav
 * y el logout en /mi-cuenta. El grueso es sobre el servicio (locale-independiente); unos pocos
 * comprueban el render real.
 */
class CustomerAccountContextTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function pack(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_NONE, 'deposit_value' => 0,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ],
        ]);
    }

    private function entry(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60,
            'seats_per_unit' => 1, 'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
    }

    /**
     * Crea una reserva pagada (pedido + item principal + franja). Devuelve el item.
     *
     * @param  array<int, array<string, mixed>>  $guestData
     */
    private function reservation(
        User $user,
        TicketType $type,
        string $date,
        int $qty = 2,
        array $guestData = [],
        bool $cancelled = false,
        string $status = Order::STATUS_PAID,
    ): OrderItem {
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => $status, 'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);

        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => '10:00:00', 'end_time' => '20:00:00',
            'capacity' => 50, 'online_capacity' => 50,
        ]);

        return $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'parent_item_id' => null,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => 1000,
            'guest_data' => $guestData === [] ? null : $guestData,
            'cancelled_at' => $cancelled ? now() : null,
        ]);
    }

    private function ctx(User $user): array
    {
        return app(CustomerAccountContext::class)->for($user);
    }

    // ─── Servicio: saludo y vacío seguro ─────────────────────────────────────

    public function test_first_name_is_the_first_token_of_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Mara López']);

        $this->assertSame('Mara', $user->firstName());
        $this->assertSame('Mara', $this->ctx($user)['firstName']);
    }

    public function test_user_without_orders_gets_a_safe_empty_context(): void
    {
        $user = User::factory()->create(['name' => 'Mara']);

        $ctx = $this->ctx($user);

        $this->assertSame('Mara', $ctx['firstName']);
        $this->assertNull($ctx['nextReservation']);
        $this->assertSame([], $ctx['pendingForms']);
        $this->assertSame(0, $ctx['pendingFormsCount']);
        $this->assertFalse($ctx['hasPendingForm']);
    }

    // ─── Servicio: próxima reserva ───────────────────────────────────────────

    public function test_next_reservation_returns_future_paid_principal_with_product_and_window(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'));

        $next = $this->ctx($user)['nextReservation'];

        $this->assertNotNull($next);
        $this->assertSame('Cumpleaños Jump', $next['productName']);
        $this->assertNotSame('', $next['dateLabel']);
        $this->assertStringContainsString('10:00', (string) $next['timeWindow']);
        $this->assertStringContainsString('12:00', (string) $next['timeWindow']); // 10:00 + 120 min
    }

    public function test_finished_reservation_is_excluded(): void
    {
        $user = User::factory()->create();
        // Franja de ayer: end_time ya pasó → isFinishedInPractice().
        $item = $this->reservation($user, $this->pack(), now()->subDays(2)->format('Y-m-d'));
        $item->slot->update(['end_time' => '11:00:00']);

        $this->assertNull($this->ctx($user)['nextReservation']);
    }

    public function test_cancelled_item_is_excluded(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), cancelled: true);

        $this->assertNull($this->ctx($user)['nextReservation']);
    }

    public function test_addon_child_is_not_treated_as_a_reservation(): void
    {
        $user = User::factory()->create();
        $principal = $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'));
        // Un addon (parent_item_id no nulo) nunca es "la próxima reserva".
        $principal->order->items()->create([
            'ticket_type_id' => $this->entry()->id, 'slot_id' => $principal->slot_id,
            'parent_item_id' => $principal->id, 'quantity' => 1, 'seats' => 0, 'unit_price' => 500,
        ]);

        $next = $this->ctx($user)['nextReservation'];
        $this->assertSame('Cumpleaños Jump', $next['productName']); // el principal, no el addon
    }

    public function test_earliest_future_reservation_is_chosen(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(10)->format('Y-m-d'));
        $this->reservation($user, $this->entry(), now()->addDays(2)->format('Y-m-d'));

        $this->assertSame('Entrada 1h', $this->ctx($user)['nextReservation']['productName']);
    }

    public function test_only_paid_orders_are_considered(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), status: Order::STATUS_PENDING);

        $this->assertNull($this->ctx($user)['nextReservation']);
    }

    public function test_upcoming_count_counts_only_future_principal_reservations(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'));
        $this->reservation($user, $this->entry(), now()->addDays(5)->format('Y-m-d'));
        $past = $this->reservation($user, $this->entry(), now()->subDays(2)->format('Y-m-d'));
        $past->slot->update(['end_time' => '11:00:00']);   // finalizada → no cuenta

        $this->assertSame(2, $this->ctx($user)['upcomingCount']);
    }

    // ─── Servicio: formularios pendientes (#217) ─────────────────────────────

    public function test_pending_guest_form_is_flagged_with_product_and_url(): void
    {
        $user = User::factory()->create();
        // Pack con guest_fields y SIN datos → formulario pendiente.
        $item = $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), qty: 2);

        $ctx = $this->ctx($user);

        $this->assertTrue($ctx['hasPendingForm']);
        $this->assertSame(1, $ctx['pendingFormsCount']);
        $this->assertSame('Cumpleaños Jump', $ctx['pendingForms'][0]['productName']);
        // Individualizado POR RESERVA (#217): la url apunta al post-form de ESA reserva (el OrderItem),
        // no al pedido.
        $this->assertSame(route('reservation.guests', $item), $ctx['pendingForms'][0]['url']);
    }

    public function test_two_packs_in_one_order_yield_two_pending_forms(): void
    {
        // Individualizado POR RESERVA (#217): un pedido con DOS cumpleaños pendientes produce DOS
        // avisos (uno por reserva), cada uno con su url. (Antes el servicio emitía solo uno por pedido.)
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-MULTI', 'status' => Order::STATUS_PAID, 'paid_at' => now(),
        ]);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '10:00:00', 'end_time' => '20:00:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);
        $packB = TicketType::create([
            'name' => ['es' => 'Cumpleaños Kids'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 90, 'min_qty' => 2, 'max_qty' => 15, 'seats_per_unit' => 1, 'is_sellable' => true,
            'is_active' => true, 'position' => 10,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        $a = $order->items()->create(['ticket_type_id' => $this->pack()->id, 'slot_id' => $slot->id, 'quantity' => 2, 'seats' => 2, 'unit_price' => 1000]);
        $b = $order->items()->create(['ticket_type_id' => $packB->id, 'slot_id' => $slot->id, 'quantity' => 3, 'seats' => 3, 'unit_price' => 800]);

        $ctx = $this->ctx($user);

        $this->assertSame(2, $ctx['pendingFormsCount']);
        $urls = array_column($ctx['pendingForms'], 'url');
        $this->assertContains(route('reservation.guests', $a), $urls);
        $this->assertContains(route('reservation.guests', $b), $urls);
    }

    public function test_completed_guest_form_is_not_pending(): void
    {
        $user = User::factory()->create();
        $this->reservation(
            $user, $this->pack(), now()->addDays(3)->format('Y-m-d'),
            qty: 2, guestData: [['name' => 'Ana'], ['name' => 'Leo']],
        );

        $ctx = $this->ctx($user);
        $this->assertFalse($ctx['hasPendingForm']);
        $this->assertSame(0, $ctx['pendingFormsCount']);
    }

    public function test_entry_without_guest_fields_never_pends_a_form(): void
    {
        $user = User::factory()->create();
        $this->reservation($user, $this->entry(), now()->addDays(3)->format('Y-m-d'));

        $this->assertFalse($this->ctx($user)['hasPendingForm']);
    }

    public function test_finished_pack_does_not_flag_a_pending_form(): void
    {
        // Simétrico a test_finished_reservation_is_excluded pero en el eje de formularios: un pack
        // pagado con el formulario sin rellenar y la franja YA pasada (cumpleaños celebrado) NO
        // debe dejar el puntito/aviso encendidos para siempre (#221, hallazgo de la revisión).
        $user = User::factory()->create();
        $item = $this->reservation($user, $this->pack(), now()->subDays(2)->format('Y-m-d'), qty: 2);
        $item->slot->update(['end_time' => '11:00:00']);

        $ctx = $this->ctx($user);
        $this->assertFalse($ctx['hasPendingForm']);
        $this->assertSame(0, $ctx['pendingFormsCount']);
    }

    public function test_cancelled_pack_does_not_flag_a_pending_form(): void
    {
        // Bug clienta (2026-06-15): una reserva CANCELADA debe dejar de avisar de su post-form en el
        // sidebar/nav. Es el eje de formularios, simétrico a `test_cancelled_item_is_excluded` (que solo
        // cubría `nextReservation`). El item cancelado lo excluye `Order::guestFormItems()`.
        $user = User::factory()->create();
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), qty: 2, cancelled: true);

        $ctx = $this->ctx($user);
        $this->assertFalse($ctx['hasPendingForm']);
        $this->assertSame(0, $ctx['pendingFormsCount']);
        $this->assertSame([], $ctx['pendingForms']);
    }

    public function test_cancelled_order_does_not_flag_a_pending_form(): void
    {
        // Cancelar el PEDIDO entero (status=CANCELLED; sin marcar los items uno a uno) también retira
        // el aviso: la consulta de formularios pendientes solo considera pedidos PAGADOS.
        $user = User::factory()->create();
        $item = $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), qty: 2);
        $item->order->update(['status' => Order::STATUS_CANCELLED]);

        $this->assertFalse($this->ctx($user)['hasPendingForm']);
    }

    public function test_first_name_trims_surrounding_whitespace(): void
    {
        $this->assertSame('Mara', User::factory()->create(['name' => '  Mara López '])->firstName());
        // Nombre solo-espacios → cadena vacía sin espacios colgando (no «Hola,    »).
        $this->assertSame('', User::factory()->create(['name' => '   '])->firstName());
    }

    // ─── Render: bloque del sidecart, icono del nav, logout ──────────────────

    public function test_guest_sidecart_shows_login_and_reservations(): void
    {
        $this->seed(LandingContentSeeder::class);

        $response = $this->withSession(['locale' => 'es'])->get('/')
            ->assertOk()
            ->assertSee('Iniciar sesión')                    // acct__btn--primary (invitado)
            ->assertSee('Ver mis reservas')                  // tickets.my_reservations (es)
            ->assertSee(__('account.sidecart.guest_hello')); // saludo genérico de invitado

        // AMBOS botones del bloque de invitado («Iniciar sesión» y «Ver mis reservas») abren el modal
        // de login → se BLOQUEAN en el paso de identificación del flujo (paso 5): cada uno lleva el
        // `disabled` atado a `$store.purchase.identifying` + guard defensivo en el click.
        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, 'x-bind:disabled="$store.purchase.identifying"'));
        $this->assertSame(2, substr_count($html, 'if (! $store.purchase.identifying)'));
    }

    public function test_authenticated_account_page_shows_block_icon_and_logout(): void
    {
        $user = User::factory()->create(['name' => 'Mara', 'locale' => 'es']);
        $this->reservation($user, $this->pack(), now()->addDays(3)->format('Y-m-d'), qty: 2);

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertSee('Hola, Mara')                       // saludo (chip del nav + bloque del sidecart)
            ->assertSee('nav__acct-greet', false)           // «Hola, nombre» junto al icono del nav
            ->assertSee('Tienes el')                        // próxima reserva
            ->assertSee('Cumpleaños Jump')
            ->assertSee('Tienes pendiente un formulario')   // aviso de formulario
            ->assertSee('acct__avatar', false)              // avatar con la inicial (mockup)
            ->assertSee('acct__count', false)               // contador de reservas próximas
            ->assertSee('nav__acct-icon', false)            // icono de cuenta en el nav
            ->assertSee('nav__acct-dot', false)             // puntito (hay form pendiente)
            ->assertSee('Cerrar sesión')                    // logout (bloque + /mi-cuenta)
            ->assertSee(route('logout'), false);
    }

    public function test_account_icon_has_no_dot_without_pending_form(): void
    {
        $user = User::factory()->create(['name' => 'Mara', 'locale' => 'es']);
        // Reserva sin formulario pendiente (entrada, sin guest_fields).
        $this->reservation($user, $this->entry(), now()->addDays(3)->format('Y-m-d'));

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertSee('nav__acct-icon', false)
            ->assertDontSee('nav__acct-dot', false);
    }
}
