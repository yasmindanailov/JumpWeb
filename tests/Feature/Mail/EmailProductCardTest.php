<?php

namespace Tests\Feature\Mail;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\GuestFormRequest;
use App\Notifications\OrderConfirmation;
use App\Notifications\OrderRefunded;
use App\Support\EmailProductCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Mejora visual de los correos (#251): la SUBCARD de producto (icono emoji + meta + complementos
 * + color de la zona) que `App\Support\EmailProductCard` inyecta en los correos de producto, SIN
 * tocar su lógica de textos condicionales. Email-safe (tablas + estilos inline; NO SVG).
 */
class EmailProductCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        App::setLocale('es');
    }

    /** @return array{0: Order, 1: OrderItem, 2: User} */
    private function packReservation(): array
    {
        $zone = Zone::create(['slug' => 'cumple', 'name' => ['es' => 'Cumpleaños'], 'color' => '#FF5B22', 'is_active' => true]);
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Kids'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 90, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'guest_fields' => [['key' => 'n', 'label' => ['es' => 'Nombre'], 'type' => 'text', 'required' => true]],
        ]);
        $addon = TicketType::create([
            'name' => ['es' => 'Tarta'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => now()->addDays(5)->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '20:00:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);
        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-CARD01', 'status' => Order::STATUS_PAID,
            'total' => 13000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'quantity' => 8, 'seats' => 8, 'unit_price' => 1500,
        ]);
        $item->children()->create([
            'order_id' => $order->id, 'ticket_type_id' => $addon->id, 'quantity' => 1, 'seats' => 0, 'unit_price' => 2000,
        ]);

        return [$order->fresh(), $item->fresh(), $user];
    }

    public function test_confirmation_renders_product_card_with_icon_addons_and_zone_color(): void
    {
        [$order, , $user] = $this->packReservation();

        $html = (new OrderConfirmation($order))->toMail($user)->render();

        $this->assertStringContainsString('product-card', $html);          // la subcard
        $this->assertStringContainsString('🎂', $html);                     // emoji de cumpleaños (no SVG)
        $this->assertStringContainsString('Cumpleaños Kids', $html);        // nombre del producto
        $this->assertStringContainsString('Tarta', $html);                 // complemento anidado
        $this->assertStringContainsString('8 invitados', $html);           // sub-línea de invitados
        $this->assertStringContainsString('#FF5B22', $html);               // color de la zona (borde superior)
        // La lógica condicional NO se rompió: el pack con guest_fields sigue prometiendo el post-form.
        $this->assertStringContainsString('datos de los invitados', $html);
    }

    public function test_entry_card_uses_ticket_emoji_and_quantity_title(): void
    {
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'color' => '#1FA2A6', 'is_active' => true]);
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => now()->addDays(3)->toDateString(),
            'start_time' => '12:00:00', 'end_time' => '13:00:00', 'capacity' => 50, 'online_capacity' => 50,
        ]);
        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-ENT01', 'status' => Order::STATUS_PAID,
            'total' => 2400, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $entry->id, 'slot_id' => $slot->id, 'quantity' => 2, 'seats' => 2, 'unit_price' => 1200,
        ]);

        $html = (new OrderConfirmation($order->fresh()))->toMail($user)->render();

        $this->assertStringContainsString('🎟️', $html);        // emoji de entrada
        $this->assertStringContainsString('2× Entrada 1h', $html); // cantidad delante (como en «Mis pedidos»)
        $this->assertStringContainsString('#1FA2A6', $html);     // color de la zona Jump
    }

    public function test_guest_form_request_includes_the_reservation_card(): void
    {
        [, $item, $user] = $this->packReservation();

        $html = (new GuestFormRequest($item))->toMail($user)->render();

        $this->assertStringContainsString('product-card', $html);
        $this->assertStringContainsString('🎂', $html);
        $this->assertStringContainsString('8 invitados', $html);
    }

    public function test_order_refund_shows_all_products_including_a_pre_cancelled_one(): void
    {
        // Bug JJ-YJDCVM: un reembolso del PEDIDO total cubre también un producto que se canceló antes
        // sin devolver. El correo «devolución del total» debe listar AMBOS — el cancelado, marcado.
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'color' => '#1FA2A6', 'is_active' => true]);
        $cumple = Zone::create(['slug' => 'cumple', 'name' => ['es' => 'Cumpleaños'], 'color' => '#FF5B22', 'is_active' => true]);
        $entry = TicketType::create(['name' => ['es' => 'Jump · 2 horas'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $jump->id, 'duration_min' => 120, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1]);
        $pack = TicketType::create(['name' => ['es' => 'Cumpleaños Kids'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $cumple->id, 'duration_min' => 120, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1]);
        $slot = Slot::create(['zone_id' => $jump->id, 'date' => now()->addDays(4)->toDateString(), 'start_time' => '10:00:00', 'end_time' => '20:00:00', 'capacity' => 50, 'online_capacity' => 50]);
        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create(['user_id' => $user->id, 'code' => 'JJ-REF01', 'status' => Order::STATUS_CANCELLED, 'total' => 18520, 'currency' => 'EUR', 'paid_at' => now(), 'refunded_at' => now(), 'refund_amount_cents' => 4800]);
        // Producto cancelado ANTES (sin devolver) + producto activo (cae con el pedido).
        $order->items()->create(['ticket_type_id' => $entry->id, 'slot_id' => $slot->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 1800, 'cancelled_at' => now()]);
        $order->items()->create(['ticket_type_id' => $pack->id, 'slot_id' => $slot->id, 'quantity' => 8, 'seats' => 8, 'unit_price' => 1590]);

        $html = (new OrderRefunded($order->fresh(), null, true))->toMail($user)->render();

        $this->assertStringContainsString('Jump · 2 horas', $html);                  // el cancelado SÍ aparece
        $this->assertStringContainsString('Cumpleaños Kids', $html);                 // y el activo
        $this->assertStringContainsString(__('account.orders.item_cancelled'), $html); // marcado «Cancelado»
        $this->assertStringContainsString('line-through', $html);                    // tachado
    }

    public function test_footer_has_policy_and_contact_links(): void
    {
        // #251: enlaces a políticas + contacto en el footer (compartido → todos los correos).
        [$order, , $user] = $this->packReservation();

        $html = (new OrderConfirmation($order))->toMail($user)->render();

        $this->assertStringContainsString(route('legal.privacidad'), $html);
        $this->assertStringContainsString(route('legal.condiciones'), $html);
        $this->assertStringContainsString(route('legal.cookies'), $html);
        $this->assertStringContainsString(route('contacto'), $html);
    }

    public function test_helper_is_defensive_and_never_throws_on_empty_or_broken_data(): void
    {
        // Pedido SIN ítems → forOrder devuelve HtmlString vacío, sin excepción.
        $user = User::factory()->create(['locale' => 'es']);
        $empty = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-EMPTY1', 'status' => Order::STATUS_PAID,
            'total' => 0, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $this->assertSame('', EmailProductCard::forOrder($empty)->toHtml());

        // Item de un tipo SIN zona → la tarjeta usa el color de marca por defecto, sin reventar.
        $type = TicketType::create([
            'name' => ['es' => 'Sin zona'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => null,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
        ]);
        $item = $empty->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => null, 'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
        $this->assertStringContainsString('product-card', EmailProductCard::forItem($item->fresh())->toHtml());
    }
}
