<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

/**
 * **Lo que Mi cuenta dice de cada reserva** (T5b de `docs/specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #775`,
 * contrato 1.34.0): el plazo de cambio de ESTA reserva con su fecha límite, si es hoy, su producto y el aviso de sus
 * complementos. Viajan en `OrderItem` (y en `OrderItemAddon`), así que valen en `/me/reservations/{scope}` y en
 * `/me/orders`; aquí se miden por el primero, que es el que lee Mi cuenta.
 *
 * ⚠️ **Reloj congelado** a las 12:00 UTC del 15 de junio, las 14:00 en el parque (Madrid, +02:00): las fechas límite
 * se escriben en la zona del PARQUE, no en la del servidor.
 */
class MeReservationChangeFactsTest extends ApiTestCase
{
    private const NOW = '2026-06-15 12:00:00';

    private Zone $zone;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);
        $this->zone = Zone::create(['slug' => 'kids', 'name' => ['es' => 'Kids'], 'accent' => 'kids', 'color' => '#00AEEF', 'position' => 1, 'is_active' => true]);
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_product_without_a_published_cutoff_says_nothing_about_changing(): void
    {
        $this->reservation(date: '2026-06-18', cutoff: null);

        $this->upcoming()->assertValidResponse(200)->assertJsonPath('data.0.reservation.cancellation', null);
    }

    public function test_the_deadline_is_the_slot_start_minus_the_cutoff_in_the_park_zone(): void
    {
        $item = $this->reservation(date: '2026-06-18', start: '17:00:00', cutoff: 24);

        $this->upcoming()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.reservation.product_id', $item->ticket_type_id)
            ->assertJsonPath('data.0.reservation.cancellation.cutoff_hours', 24)
            ->assertJsonPath('data.0.reservation.cancellation.written', "hasta 24\u{00A0}h antes")
            ->assertJsonPath('data.0.reservation.cancellation.span', "24\u{00A0}h")
            ->assertJsonPath('data.0.reservation.cancellation.until', '2026-06-17T17:00:00+02:00')
            ->assertJsonPath('data.0.reservation.cancellation.open', true);
    }

    public function test_past_the_deadline_the_reservation_is_still_upcoming_but_no_longer_open(): void
    {
        // Mañana a las 10:00 del parque: el corte de 24 h fue hoy a las 10:00 (08:00 UTC), y ya son las 14:00.
        $this->reservation(date: '2026-06-16', start: '10:00:00', cutoff: 24);

        $this->upcoming()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reservation.cancellation.until', '2026-06-15T10:00:00+02:00')
            ->assertJsonPath('data.0.reservation.cancellation.open', false);
    }

    public function test_whole_days_are_written_in_days_and_their_span_too(): void
    {
        $this->reservation(date: '2026-06-20', start: '17:00:00', cutoff: 72);

        $this->upcoming()
            ->assertJsonPath('data.0.reservation.cancellation.written', "hasta 3\u{00A0}días antes")
            ->assertJsonPath('data.0.reservation.cancellation.span', "3\u{00A0}días")
            ->assertJsonPath('data.0.reservation.cancellation.until', '2026-06-17T17:00:00+02:00');
    }

    public function test_a_zero_cutoff_has_no_span_to_name(): void
    {
        $this->reservation(date: '2026-06-18', cutoff: 0);

        $this->upcoming()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.reservation.cancellation.written', 'hasta la hora reservada')
            ->assertJsonPath('data.0.reservation.cancellation.span', null);
    }

    /**
     * La devolución de la señal es una PROMESA de dinero: viaja solo si el producto la enciende (la instalación la
     * promete en sus condiciones) Y la reserva nació con señal (el hecho `deposit_split` del libro, no el catálogo).
     */
    public function test_the_deposit_is_said_to_be_refunded_only_with_the_switch_and_a_deposit(): void
    {
        $conTodo = $this->reservation(date: '2026-06-20', cutoff: 72, pack: true, refundable: true);
        $this->depositSplit($conTodo);
        $this->assertTrue($this->cancellationOf($conTodo)['deposit_refundable'], 'interruptor y señal: se dice');

        $sinSenal = $this->reservation(date: '2026-06-21', cutoff: 72, pack: true, refundable: true);
        $this->assertFalse($this->cancellationOf($sinSenal)['deposit_refundable'], 'sin señal no hay nada que devolver');

        $apagado = $this->reservation(date: '2026-06-22', cutoff: 72, pack: true, refundable: false);
        $this->depositSplit($apagado);
        $this->assertFalse($this->cancellationOf($apagado)['deposit_refundable'], 'con señal pero sin la promesa del producto');
    }

    /**
     * A las 22:30 UTC del 15 en el parque ya es el 16 (las 00:30 en Madrid): «hoy» es el 16. Con el día del servidor
     * sería el 15, y el QR grande saldría la víspera y no el día de la visita.
     */
    public function test_today_is_the_park_day_not_the_server_day(): void
    {
        Carbon::setTestNow('2026-06-15 22:30:00');
        $hoy = $this->reservation(date: '2026-06-16', start: '18:00:00', cutoff: null);
        $manana = $this->reservation(date: '2026-06-17', start: '18:00:00', cutoff: null);

        $cards = collect($this->upcoming()->json('data'))->keyBy('reservation.id');

        $this->assertTrue($cards[$hoy->id]['reservation']['today']);
        $this->assertFalse($cards[$manana->id]['reservation']['today']);
    }

    /** `#775`: el aviso de un complemento lo escribe el panel, con su cantidad; sin él, `null` (la isla lo nombra). */
    public function test_an_addon_carries_its_written_note_with_the_quantity_or_null(): void
    {
        $item = $this->reservation(date: '2026-06-18', cutoff: 24);
        $this->addon($item, 'Calcetines', 2, ['es' => 'Tenéis :n pares de calcetines comprados; os los damos en la puerta.', 'en' => 'You have :n pairs of socks; we hand them over at the door.']);
        $this->addon($item, 'Menú', 3, null);

        $addons = collect($this->upcoming()->assertValidResponse(200)->json('data.0.reservation.addons'))->keyBy('product_name');
        $this->assertSame('Tenéis 2 pares de calcetines comprados; os los damos en la puerta.', $addons['Calcetines']['note']);
        $this->assertNull($addons['Menú']['note']);

        $en = collect($this->actingAs($this->user)->withHeader('Accept-Language', 'en')->getJson(self::ROOT.'/me/reservations/upcoming')->json('data.0.reservation.addons'))->keyBy('product_name');
        $this->assertSame('You have 2 pairs of socks; we hand them over at the door.', $en['Calcetines']['note'], 'en el idioma de la petición');
    }

    // ── Andamiaje ───────────────────────────────────────────────────────────────────────────────

    private function upcoming()
    {
        return $this->actingAs($this->user)->getJson(self::ROOT.'/me/reservations/upcoming?per_page=50');
    }

    /** @return array<string, mixed> */
    private function cancellationOf(OrderItem $item): array
    {
        return collect($this->upcoming()->json('data'))->firstWhere('reservation.id', $item->id)['reservation']['cancellation'];
    }

    private function reservation(string $date, ?int $cutoff, string $start = '17:00:00', bool $pack = false, bool $refundable = false): OrderItem
    {
        $order = Order::create([
            'user_id' => $this->user->id, 'code' => 'R-'.Str::upper(Str::random(8)), 'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $slot = Slot::firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => $date, 'start_time' => $start],
            ['end_time' => '23:00:00', 'capacity' => 50, 'online_capacity' => 50],
        );
        $product = TicketType::create([
            'name' => ['es' => 'Producto '.Str::random(4)], 'type' => $pack ? TicketType::TYPE_PACK : TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1, 'cancellation_cutoff_hours' => $cutoff,
            'deposit_refundable_in_time' => $refundable,
        ]);

        return $order->items()->create(['ticket_type_id' => $product->id, 'slot_id' => $slot->id, 'quantity' => 1, 'unit_price' => 1000, 'seats' => 1]);
    }

    /** La señal nace como hecho del libro, por línea: así la crea el pedido real (`SidebarAccountParityTest`). */
    private function depositSplit(OrderItem $item): void
    {
        OrderAdjustment::create([
            'order_id' => $item->order_id, 'order_item_id' => $item->id, 'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT,
            'amount_cents' => 500, 'currency' => 'EUR', 'applied_by' => $this->user->id,
        ]);
    }

    /** @param array<string, string>|null $note */
    private function addon(OrderItem $parent, string $name, int $quantity, ?array $note): void
    {
        $product = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1, 'reservation_note' => $note,
        ]);

        OrderItem::create([
            'order_id' => $parent->order_id, 'parent_item_id' => $parent->id, 'ticket_type_id' => $product->id,
            'slot_id' => $parent->slot_id, 'quantity' => $quantity, 'unit_price' => 200, 'seats' => 0,
        ]);
    }
}
