<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **LA INVITACIÓN EN LA FICHA DEL PEDIDO** (T6·5, `docs/specs/celebracion-e-invitacion.md` §4.8).
 *
 * El panel solo pinta lo que ya existe, y aun así trae la única palanca que faltaba: **anular el
 * enlace**. Lo que se vigila:
 *
 *  · el **resumen** en la línea, para que el operador sepa si la lista está lista sin abrir nada;
 *  · **copiar el enlace** —el de la tarjeta pública, no el del formulario—, y solo si existe y se
 *    puede repartir;
 *  · **anular**: rota el token, deja **rastro sin el token** y **no borra** lo que ya contestaron;
 *  · y que el panel **no materialice** la invitación: nace en el GET del anfitrión (§4.5·1).
 */
class InvitationPanelTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $pack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true, 'position' => 1]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => true,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Quién cumple']],
            ],
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ],
        ]);
    }

    // ─── El enlace ───────────────────────────────────────────────────────────────────

    public function test_the_panel_offers_the_public_link_of_the_party(): void
    {
        $item = $this->party();
        $invitation = app(PartyInvitations::class)->forReservation($item);

        $url = $this->page($item->order)->invitationLinkForManageItem($item);

        $this->assertNotNull($url);
        $this->assertStringContainsString('/invitacion/'.$invitation->token, $url, 'el panel reparte el enlace PÚBLICO, no el del formulario');
    }

    public function test_the_panel_never_materialises_the_invitation(): void
    {
        // ❗ Nace en el GET del ANFITRIÓN y con una razón (§4.5·1). Si la creara el panel, el operador
        // tendría un enlace que repartir antes de que el cliente hubiera visto su propio formulario.
        $item = $this->party();

        $this->assertNull($this->page($item->order)->invitationLinkForManageItem($item));
        $this->assertSame(0, PartyInvitation::query()->count());
    }

    public function test_without_a_celebrant_name_there_is_no_link_to_copy(): void
    {
        $item = $this->party(celebrant: null);
        app(PartyInvitations::class)->forReservation($item);

        // La misma regla que en la pantalla del cliente: sin nombre, la tarjeta no dice de quién es la
        // fiesta y no se reparte (§4.5·2).
        $this->assertNull($this->page($item->order)->invitationLinkForManageItem($item));
    }

    public function test_an_unpaid_order_offers_nothing(): void
    {
        // ⚠️ Lo niega el DOMINIO (`isShareable` → `isOpenFor`), no una comprobación de esta página: el
        // arnés enseñó que la copia de aquí no la podía tumbar ninguna prueba y se retiró (`#704`).
        $item = $this->party(paid: false);
        app(PartyInvitations::class)->forReservation($item);

        $this->assertNull($this->page($item->order)->invitationLinkForManageItem($item));
    }

    public function test_the_link_of_another_order_is_never_minted(): void
    {
        // IDOR: el id de la reserva llega por `mountAction`, así que puede forzarse. La fiesta de otro
        // pedido no se sirve aunque su enlace exista y sea perfectamente compartible.
        $mine = $this->party();
        $theirs = $this->party();
        app(PartyInvitations::class)->forReservation($theirs);

        $this->assertNull($this->page($mine->order)->invitationLinkForManageItem($theirs));
    }

    // ─── El resumen ──────────────────────────────────────────────────────────────────

    public function test_the_line_says_how_the_replies_are_going(): void
    {
        $item = $this->party();
        $this->reply($item, 'Martina Serra');
        $this->reply($item, 'Pablo Ortiz', attending: false);

        $summary = $this->page($item->order)->invitationSummaryFor($item);

        $this->assertSame(['yes' => 1, 'no' => 1, 'pending' => 2], $summary);
    }

    public function test_without_an_invitation_there_is_no_summary(): void
    {
        $item = $this->party();

        $this->assertNull($this->page($item->order)->invitationSummaryFor($item), 'sin invitación no hay nada que resumir');
    }

    // ─── Los botones ─────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **Lo que esta tanda añade de verdad es el BOTÓN.**
     *
     * La acción de anular existe —con su audit y sus casos— **desde `#576`** (`rotateInvitationLink`),
     * y su propio docblock decía que el botón lo pintaba la T6. Sin él era una acción a la que no se
     * podía llegar: el operador no tenía forma de cerrar un enlace repartido a un grupo de clase.
     */
    public function test_the_line_paints_both_buttons_once_the_invitation_exists(): void
    {
        $item = $this->party();
        app(PartyInvitations::class)->forReservation($item);

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $item->order->code])
            ->assertSeeHtml("mountAction('copyInvitationLink', { item: {$item->id} })")
            ->assertSeeHtml("mountAction('rotateInvitationLink', { item: {$item->id} })");
    }

    public function test_an_operator_without_the_permission_does_not_even_see_the_void_button(): void
    {
        $item = $this->party();
        app(PartyInvitations::class)->forReservation($item);

        Livewire::actingAs($this->operatorWithoutGuestData())
            ->test(ViewOrder::class, ['record' => $item->order->code])
            // Copiar sí: es información del pedido que ya está viendo.
            ->assertSeeHtml("mountAction('copyInvitationLink', { item: {$item->id} })")
            ->assertDontSeeHtml("mountAction('rotateInvitationLink', { item: {$item->id} })");
    }

    public function test_without_an_invitation_neither_button_is_painted(): void
    {
        $item = $this->party();

        Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $item->order->code])
            ->assertDontSeeHtml("mountAction('copyInvitationLink', { item: {$item->id} })")
            ->assertDontSeeHtml("mountAction('rotateInvitationLink', { item: {$item->id} })");
    }

    // ─── Fixture ─────────────────────────────────────────────────────────────────────

    private function page(Order $order): ViewOrder
    {
        return Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->instance();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $user;
    }

    /**
     * Un operador que VE el pedido y **no gobierna el formulario de invitados**.
     *
     * ⚠️ No vale el rol `puerta`: ése ni siquiera abre la ficha del pedido, así que el caso moriría
     * montando la página y no probaría el permiso. Se construye un rol con lo justo para entrar.
     */
    private function operatorWithoutGuestData(): User
    {
        $role = Role::firstOrCreate(['name' => 'sala'], ['label' => 'Sala']);
        $role->permissions()->sync(Permission::whereIn('name', ['orders.view', 'calendar.view'])->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        $user = $user->fresh();

        $this->assertTrue($user->hasPermission('orders.view'), 'el fixture necesita poder ABRIR la ficha');
        $this->assertFalse($user->hasPermission('orders.edit_guest_data'), 'y NO tener el permiso que se prueba');

        return $user;
    }

    private function reply(OrderItem $item, string $childName, bool $attending = true): InvitationReply
    {
        $invitation = app(PartyInvitations::class)->forReservation($item);
        $this->assertInstanceOf(PartyInvitation::class, $invitation);

        $outcome = app(PartyInvitations::class)->reply($invitation, $childName, $attending);
        $this->assertTrue($outcome->accepted, 'el fixture no pudo contestar: '.($outcome->reason ?? '—'));

        return $outcome->reply;
    }

    private function party(?string $celebrant = 'Lucía', bool $paid = true): OrderItem
    {
        $slot = Slot::firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => Carbon::today()->addDays(10)->toDateString(), 'start_time' => '17:00:00'],
            ['end_time' => '19:00:00', 'capacity' => 40, 'online_capacity' => 40],
        );

        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => $paid ? Order::STATUS_PAID : Order::STATUS_PENDING,
            'paid_at' => $paid ? now() : null,
            'subtotal' => 6000, 'tax' => 0, 'total' => 6000, 'currency' => 'EUR',
        ]);

        if ($paid) {
            Payment::create([
                'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'cash',
                'amount' => 6000, 'currency' => 'EUR', 'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            ]);
        }

        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => $slot->id, 'quantity' => 4,
            'unit_price' => 1500, 'seats' => 4,
            'event_data' => $celebrant === null ? [] : ['celebrant' => $celebrant],
        ]);

        return $order->fresh()->items()->whereNull('parent_item_id')->with(['ticketType', 'slot', 'order'])->firstOrFail();
    }
}
