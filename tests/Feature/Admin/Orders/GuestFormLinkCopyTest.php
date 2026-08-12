<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «Enlace del formulario» POR PRODUCTO (DECISIONES #263): un icono de enlace en la fila de acciones de
 * cada reserva de cumpleaños con post-form abre un modal con su enlace firmado, para que el operador lo
 * copie y lo mande por WhatsApp/SMS (útil sobre todo si el cliente no tiene email). El enlace es la
 * MISMA fuente que el email (`OrderItem::guestFormSignedUrl`): acceso sin sesión, caduca tras el evento.
 * No se ofrece en entradas/complementos ni en pedidos no pagados.
 */
class GuestFormLinkCopyTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $pack;

    private TicketType $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ],
        ]);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function orderWith(TicketType $type, string $status = Order::STATUS_PAID): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => $status,
            'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => null, 'quantity' => 2,
            'unit_price' => 9000, 'seats' => 2, 'event_data' => ['celebrant' => 'Mara'],
        ]);

        return $order;
    }

    private function reservation(Order $order): OrderItem
    {
        return $order->items()->whereNull('parent_item_id')->firstOrFail();
    }

    /** Gating del enlace (método público de la página) sobre una instancia montada. */
    private function linkFor(Order $order, OrderItem $item): ?string
    {
        return Livewire::actingAs($this->admin())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->instance()
            ->guestFormLinkForManageItem($item);
    }

    public function test_signed_url_opens_the_post_form_without_session(): void
    {
        // El enlace que copia el operador debe abrir el formulario sin login (acceso por firma).
        $order = $this->orderWith($this->pack);
        $reservation = $this->reservation($order);

        $url = $reservation->guestFormSignedUrl();
        $this->assertStringContainsString('/reserva/'.$reservation->id.'/', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);

        $this->get($url)->assertOk()->assertSee('Cumpleaños Jump');
    }

    public function test_link_is_offered_for_a_paid_guest_form_reservation(): void
    {
        $order = $this->orderWith($this->pack);
        $reservation = $this->reservation($order);

        $url = $this->linkFor($order, $reservation);

        $this->assertNotNull($url);
        $this->assertStringContainsString('/reserva/'.$reservation->id.'/', $url);
    }

    public function test_no_link_for_a_plain_entry(): void
    {
        // Una entrada normal no tiene post-form → no se ofrece el enlace.
        $order = $this->orderWith($this->entry);

        $this->assertNull($this->linkFor($order, $this->reservation($order)));
    }

    public function test_no_link_when_order_is_not_paid(): void
    {
        // El endpoint del post-form exige pedido PAGADO → no ofrecemos el enlace si no lo está.
        $order = $this->orderWith($this->pack, Order::STATUS_PENDING);

        $this->assertNull($this->linkFor($order, $this->reservation($order)));
    }

    public function test_modal_content_view_renders_the_signed_link(): void
    {
        // La vista del modal (server-rendered) lleva el enlace EN EL HTML (`value="..."`), no vacío —
        // corrige el bug del intento anterior (un campo `->default()` dentro de un modal con `fillForm`
        // salía vacío). Se renderiza la vista directamente (el cuerpo del modal Filament se monta
        // client-side, así que `assertSee` sobre el componente no lo ve — ver `manage_item` testing).
        $order = $this->orderWith($this->pack);
        $reservation = $this->reservation($order);
        $url = $reservation->guestFormSignedUrl();

        $html = view('filament.orders.partials.guest-form-link', ['url' => $url])->render();

        $this->assertStringContainsString('/reserva/'.$reservation->id.'/datos-invitados', $html);
        $this->assertStringContainsString('value="'.e($url).'"', $html);
    }

    // ─── #264-audit: defensa IDOR (item de otro pedido) ───────────────────────

    public function test_no_link_for_an_item_of_another_order(): void
    {
        // Estando en el pedido A, NO se acuña un enlace firmado a la reserva (PII de menores) del
        // pedido B aunque se fuerce el id (gate `order_id` en guestFormLinkForManageItem).
        $orderA = $this->orderWith($this->pack);
        $orderB = $this->orderWith($this->pack);
        $reservationB = $this->reservation($orderB);

        $this->assertNull($this->linkFor($orderA, $reservationB));
    }
}
