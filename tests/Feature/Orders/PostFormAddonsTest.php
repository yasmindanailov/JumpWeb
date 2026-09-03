<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Balance;
use App\Domain\Booking\Services\LineFacts;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **El dominio de los complementos de venta POSTERIOR** (`specs/complementos-post-reserva.md` §4.5,
 * T2 de `DECISIONES #413`).
 *
 * Lo que estas guardas protegen no es «que funcione»: es que **el LIBRO siga cerrando después de
 * cada gesto**. Las dos formas de romperlo son simétricas y las dos dejan el pedido «en revisión» —
 * o sea al cliente sin su desglose (`#132`)— por haber tocado un cubo de refrescos:
 *
 *  · **bajar sin escribir el hecho** desplaza `nac` y rompe `I1`;
 *  · **retirar escribiendo el hecho** resta DOS veces, porque el libro ya emite su `−fila`.
 *
 * Por eso hay un caso por gesto y `assertBookCloses()` **después** de cada uno, no solo al final.
 */
class PostFormAddonsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $pack;

    private TicketType $drinks;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);

        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);

        $this->drinks = $this->addon('Cubo de refrescos', 1200);
    }

    private function addon(string $name, int $cents, int $position = 20): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => $position,
        ]);
        $this->priceIt($addon, $cents);

        return $addon;
    }

    private function priceIt(TicketType $type, int $cents): void
    {
        $rate = RateType::where('key', RateType::KEY_NORMAL)->firstOrFail();
        Price::updateOrCreate(
            ['priceable_type' => $type->getMorphClass(), 'priceable_id' => $type->id, 'rate_type_id' => $rate->id],
            ['amount_cents' => $cents, 'currency' => 'EUR'],
        );
    }

    /** El enganche de venta posterior: con tope y con plazo, que es lo que el guard exige. */
    private function attach(TicketType $addon, int $cutoffHours = 48, int $maxQty = 10): void
    {
        $this->pack->configurableAddons()->attach($addon->id, [
            'position' => 1, 'quantity_mode' => ProductAddon::MODE_FIXED,
            'stage' => ProductAddon::STAGE_POSTFORM,
            'postform_cutoff_hours' => $cutoffHours, 'max_qty' => $maxQty,
        ]);
        $this->pack->refresh();
    }

    /** Una fiesta pagada de 4 invitados a 25,00 €, con su franja en el futuro. */
    private function party(int $unit = 2500, int $qty = 4, int $daysAhead = 10): OrderItem
    {
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays($daysAhead)->toDateString(),
            'start_time' => '11:00:00', 'end_time' => '13:00:00', 'capacity' => 20, 'online_capacity' => 20,
        ]);

        $total = $unit * $qty;
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-PF'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (400000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => $slot->id, 'quantity' => $qty,
            'unit_price' => $unit, 'seats' => $qty, 'event_data' => ['celebrant' => 'Mara'],
        ]);

        return $this->reservation($order);
    }

    private function reservation(Order $order): OrderItem
    {
        return Order::with(['items.children', 'items.slot', 'items.ticketType.addons', 'adjustments', 'payments.refunds'])
            ->findOrFail($order->id)
            ->items->firstWhere('parent_item_id', null);
    }

    private function service(): PostFormAddons
    {
        return app(PostFormAddons::class);
    }

    private function book(OrderItem $item): OrderBook
    {
        $order = Order::with(['items.children', 'items.slot', 'items.ticketType', 'adjustments', 'payments.refunds'])
            ->findOrFail($item->order_id);

        return OrderBook::forOrder($order);
    }

    private function assertBookCloses(OrderItem $item, string $label): OrderBook
    {
        $book = $this->book($item);

        $this->assertTrue($book->isConsistent, "{$label} · las cuatro identidades cierran");
        $this->assertSame($book->totalCents, $book->movementsSumCents(), "{$label} · I3 · Σ líneas de valor == Total");
        $this->assertNotSame(Balance::KIND_UNDER_REVIEW, $book->balance->kind, "{$label} · el pedido no queda «en revisión»");

        return $book;
    }

    private function liveChild(OrderItem $item, TicketType $addon): ?OrderItem
    {
        return $item->fresh(['children'])->children
            ->reject(fn (OrderItem $c): bool => $c->isCancelled())
            ->firstWhere('ticket_type_id', $addon->id);
    }

    // ── Las TRES escrituras, con el libro cerrando DESPUÉS de cada una ─────────────────────────

    /**
     * El ALTA: la línea nace con `nac = 0` —no aportó nada al cobro online— y el saldo pasa a «A
     * pagar en el parque» por su importe exacto. Es la propiedad de §1.3, medida y no supuesta.
     */
    public function test_adding_creates_a_line_that_was_born_at_zero_and_owes_at_the_park(): void
    {
        $this->attach($this->drinks);
        $item = $this->party();

        $this->assertBookCloses($item, 'antes');

        $changes = $this->service()->reconcile($item, [$this->drinks->id => 2], 'signed_link');

        $this->assertTrue($changes->changed());
        $this->assertSame(2400, $changes->deltaCents);

        $child = $this->liveChild($item, $this->drinks);
        $this->assertNotNull($child);
        $this->assertSame(2400, $child->chargedSubtotalCents());
        $this->assertNull($child->slot_id, 'neutro al aforo');
        $this->assertSame(0, (int) $child->seats);

        $order = Order::with('adjustments')->findOrFail($item->order_id);
        $facts = LineFacts::forItem($order, $child);
        $this->assertSame(0, $facts->birthValue(), 'nació DESPUÉS del pedido');
        $this->assertSame(0, $facts->onlineAtBirth(), 'no aportó nada al cobro online');

        $book = $this->assertBookCloses($item, 'tras añadir');
        $this->assertSame(12400, $book->totalCents);
        $this->assertSame(Balance::KIND_PAY_AT_PARK, $book->balance->kind);
        $this->assertSame(2400, $book->balance->cents);
    }

    /** SUBIR: se escribe el delta, y `nac` sigue en 0. */
    public function test_raising_writes_its_delta_and_the_book_still_closes(): void
    {
        $this->attach($this->drinks);
        $item = $this->party();
        $this->service()->reconcile($item, [$this->drinks->id => 2], 'signed_link');

        $changes = $this->service()->reconcile($item->fresh(), [$this->drinks->id => 3], 'signed_link');

        $this->assertSame(1200, $changes->deltaCents);
        $book = $this->assertBookCloses($item, 'tras subir');
        $this->assertSame(3600, $book->balance->cents);

        $order = Order::with('adjustments')->findOrFail($item->order_id);
        $this->assertSame(0, LineFacts::forItem($order, $this->liveChild($item, $this->drinks))->birthValue());
    }

    /**
     * ❗ **BAJAR SÍ escribe movimiento.** Sin él, `nac` de esa línea caería a **−12,00 €** y la
     * identidad de nacimiento (`I1`) dejaría de cerrar. Una de las lentes de la revisión propuso lo
     * contrario y lo resolvió esta aritmética.
     */
    public function test_lowering_writes_its_negative_delta_or_the_birth_value_drifts(): void
    {
        $this->attach($this->drinks);
        $item = $this->party();
        $this->service()->reconcile($item, [$this->drinks->id => 3], 'signed_link');

        $changes = $this->service()->reconcile($item->fresh(), [$this->drinks->id => 2], 'signed_link');

        $this->assertSame(-1200, $changes->deltaCents);
        $book = $this->assertBookCloses($item, 'tras bajar');
        $this->assertSame(2400, $book->balance->cents);

        $order = Order::with('adjustments')->findOrFail($item->order_id);
        $this->assertSame(0, LineFacts::forItem($order, $this->liveChild($item, $this->drinks))->birthValue(),
            'el valor de nacimiento sigue en 0: si la bajada no escribiera su hecho, sería −1200');
    }

    /**
     * ❗❗ **RETIRAR no escribe NINGÚN movimiento**, y ahí está la asimetría: `chargedSubtotalCents()`
     * no mira `cancelled_at`, así que el libro emite su propio `−fila`. Escribir además un
     * `recordEdit` restaría dos veces y rompería `I1` e `I3`.
     *
     * Y la propiedad que lo justifica todo: el saldo vuelve **al céntimo** al valor previo, **sin
     * generar ninguna devolución**.
     */
    public function test_removing_returns_the_balance_to_the_cent_without_owing_a_refund(): void
    {
        $this->attach($this->drinks);
        $item = $this->party();
        $before = $this->assertBookCloses($item, 'antes');

        $this->service()->reconcile($item, [$this->drinks->id => 2], 'signed_link');
        $this->assertSame(2400, $this->book($item)->balance->cents);

        $this->service()->reconcile($item->fresh(), [$this->drinks->id => 0], 'signed_link');

        $after = $this->assertBookCloses($item, 'tras quitar');
        $this->assertSame($before->totalCents, $after->totalCents, 'el Total vuelve exactamente a donde estaba');
        $this->assertSame(Balance::KIND_SETTLED, $after->balance->kind, 'ninguna devolución que hacer');
        $this->assertSame(0, $after->balance->cents);
        $this->assertNull($this->liveChild($item, $this->drinks));

        // Y ni un solo movimiento de valor sobrante: el ajuste del alta y la cancelación se anulan.
        $this->assertSame(1, OrderAdjustment::where('order_id', $item->order_id)->count(),
            'la retirada NO escribe un segundo hecho: el libro ya emite su −fila');
    }

    // ── R0 · la puerta es el HECHO de la línea, no el eje del catálogo ─────────────────────────

    /**
     * ❗❗❗ **El cambio de diseño que trajo la revisión.** El eje es configuración MUTABLE: pasar
     * «Tarta» de venta al reservar a venta posterior es lo primero que el parque hará. Si la puerta
     * fuera el eje, toda fiesta ya vendida con tarta comprada en el embudo habría quedado en manos
     * del cliente — y quitarla SÍ debe dinero, **con el libro cerrando en verde**.
     */
    public function test_a_line_born_with_the_order_is_not_governable_even_if_its_link_becomes_post_form(): void
    {
        // La tarta se vendió AL RESERVAR: su línea nace con el pedido.
        $cake = $this->addon('Tarta', 2500, 21);
        $item = $this->party();
        $item->children()->create([
            'order_id' => $item->order_id, 'ticket_type_id' => $cake->id, 'slot_id' => null,
            'quantity' => 1, 'free_quantity' => 0, 'unit_price' => 2500, 'seats' => 0,
        ]);
        Order::whereKey($item->order_id)->update(['total' => 12500, 'subtotal' => 12500]);
        Payment::where('payable_id', $item->order_id)->update(['amount' => 12500]);

        // Y AHORA el parque la pasa a venta posterior.
        $this->attach($cake);
        $item = $item->fresh(['children']);

        $order = Order::with('adjustments')->findOrFail($item->order_id);
        $child = $this->liveChild($item, $cake);
        $this->assertGreaterThan(0, LineFacts::forItem($order, $child)->birthValue(), 'nació CON el pedido');

        $changes = $this->service()->reconcile($item, [$cake->id => 0], 'signed_link');

        $this->assertFalse($changes->changed(), 'no se toca lo que se cobró al reservar');
        $this->assertSame([['addon_id' => $cake->id, 'reason' => 'sold_at_booking']], $changes->blocked);
        $this->assertNotNull($this->liveChild($item, $cake), 'la línea sigue viva');
        $this->assertBookCloses($item, 'tras el intento bloqueado');
    }

    // ── R1 · el plazo, y la trampa de los campos deshabilitados ────────────────────────────────

    /**
     * ⚠️⚠️ **La trampa que se habría construido**: los `<input disabled>` **no se envían**. Con
     * «tapas 48 h» y «cubo 2 h» sobre la misma reserva, a 24 h el guardado normal llega **sin las
     * tapas** — y si el estado deseado gobernase todo lo ofrecido, se cancelarían solas, justo lo
     * contrario de D5. Fuera de plazo no cuenta como ofrecido, así que se CONSERVA.
     */
    public function test_an_addon_out_of_its_window_is_preserved_when_the_body_omits_it(): void
    {
        $tapas = $this->addon('Tapas', 2500, 21);
        $this->attach($this->drinks, cutoffHours: 2);
        $this->attach($tapas, cutoffHours: 48);

        // La fiesta es dentro de 24 h: el cubo (2 h) sigue abierto, las tapas (48 h) ya no.
        $item = $this->party(daysAhead: 1);
        $this->service()->reconcile($item, [$this->drinks->id => 1, $tapas->id => 1], 'signed_link');

        // Con las tapas ya cerradas: se sembraron antes de que el plazo pasara.
        $this->assertNull($this->liveChild($item->fresh(), $tapas), 'las tapas ya no se podían añadir');

        // Y un guardado normal, que llega SIN las tapas, no puede tocar nada suyo.
        $changes = $this->service()->reconcile($item->fresh(), [$this->drinks->id => 2], 'signed_link');
        $this->assertSame(1200, $changes->deltaCents);
        $this->assertBookCloses($item, 'tras el guardado parcial');
    }

    /** Y pedirlo explícitamente fuera de plazo tampoco lo mueve — pero se DICE, no se calla. */
    public function test_asking_for_an_addon_out_of_its_window_is_blocked_and_said(): void
    {
        $this->attach($this->drinks, cutoffHours: 48);
        $item = $this->party(daysAhead: 1);

        $changes = $this->service()->reconcile($item, [$this->drinks->id => 2], 'signed_link');

        $this->assertFalse($changes->changed());
        $this->assertSame([['addon_id' => $this->drinks->id, 'reason' => 'not_offerable']], $changes->blocked);
    }

    /**
     * ❗❗ **El plazo se mide con el reloj del PARQUE, no con el del contenedor.**
     *
     * Las franjas guardan hora de PARED del parque, y `isFinishedInPractice()` las parsea como UTC —
     * por eso declara terminada una reserva 1–2 h tarde (§4.9, ficha en `DEUDA.md`). Heredar ese
     * reloj aquí dejaría **quitar un extra ya consumido**, que es justo lo que el plazo existe para
     * impedir.
     *
     * El caso está construido para que la diferencia MUERDA: el corte vence hace 10 minutos en hora
     * del parque, pero con el reloj torcido —que va 1 o 2 h por detrás— aún parecería abierto. Sin
     * este margen, una fiesta a diez días da lo mismo con los dos relojes y el caso no vigilaría nada.
     */
    public function test_the_cutoff_is_measured_with_the_park_clock_and_not_the_container_one(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 09:00:00', 'UTC'));

        $parkNow = DisplayTime::now();
        // El corte (inicio − 2 h) vence hace 10 minutos en hora de pared del parque.
        $start = $parkNow->copy()->addMinutes(110);

        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $parkNow->toDateString(),
            'start_time' => $start->format('H:i:s'), 'end_time' => $start->copy()->addHours(2)->format('H:i:s'),
            'capacity' => 20, 'online_capacity' => 20,
        ]);
        $this->attach($this->drinks, cutoffHours: 2);

        $item = $this->party();
        $item->forceFill(['slot_id' => $slot->id])->save();
        $item = $item->fresh(['slot', 'ticketType.addons', 'order', 'children']);

        $changes = $this->service()->reconcile($item, [$this->drinks->id => 2], 'signed_link');

        $this->assertFalse($changes->changed(), 'el corte venció hace 10 minutos en hora del parque');
        $this->assertSame([['addon_id' => $this->drinks->id, 'reason' => 'not_offerable']], $changes->blocked);
    }

    // ── R2 · la línea conserva su precio ───────────────────────────────────────────────────────

    /**
     * Subir de 2 a 3 cobra la tercera **al precio de la LÍNEA**, no al del catálogo de hoy: es lo
     * que se le comunicó al cliente. Solo una línea NUEVA se tarifica a hoy.
     */
    public function test_raising_charges_the_line_price_not_todays_catalogue_price(): void
    {
        $this->attach($this->drinks);
        $item = $this->party();
        $this->service()->reconcile($item, [$this->drinks->id => 2], 'signed_link');

        $this->priceIt($this->drinks, 1400); // el parque sube el cubo a 14,00 €

        $changes = $this->service()->reconcile($item->fresh(), [$this->drinks->id => 3], 'signed_link');

        $this->assertSame(1200, $changes->deltaCents, 'la tercera unidad va al precio de la línea (12,00), no a 14,00');
        $this->assertSame(1200, (int) $this->liveChild($item, $this->drinks)->unit_price);
        $this->assertBookCloses($item, 'tras subir con el catálogo cambiado');
    }

    // ── R4 · idempotencia ──────────────────────────────────────────────────────────────────────

    public function test_an_identical_save_writes_nothing_and_does_not_audit(): void
    {
        $this->attach($this->drinks);
        $item = $this->party();
        $this->service()->reconcile($item, [$this->drinks->id => 2], 'signed_link');
        $adjustments = OrderAdjustment::where('order_id', $item->order_id)->count();

        $changes = $this->service()->reconcile($item->fresh(), [$this->drinks->id => 2], 'signed_link');

        $this->assertFalse($changes->changed());
        $this->assertSame($adjustments, OrderAdjustment::where('order_id', $item->order_id)->count());
        $this->assertSame(1, AuditLog::where('action', 'orders.postform_addons_changed')->count(),
            'el segundo guardado no deja rastro: no movió nada');
    }

    // ── El token optimista, la atribución y el rótulo ──────────────────────────────────────────

    /**
     * El lock SERIALIZA las dos escrituras, pero **no decide cuál gana**: sin token, el operador sube
     * «Tarta» a 3 por teléfono y el cliente, con la pantalla cargada antes, la baja a 1 borrando su
     * trabajo en silencio y con un movimiento de dinero.
     */
    public function test_a_stale_token_blocks_everything_and_writes_nothing(): void
    {
        $this->attach($this->drinks);
        $item = $this->party();

        $changes = $this->service()->reconcile($item, [$this->drinks->id => 2], 'signed_link', null, 'un-token-viejo');

        $this->assertFalse($changes->changed());
        $this->assertSame([['addon_id' => $this->drinks->id, 'reason' => 'stale']], $changes->blocked);
        $this->assertNull($this->liveChild($item, $this->drinks));

        // Control: con el token bueno, el mismo guardado sí escribe.
        $good = PostFormAddons::versionOf($item->fresh());
        $this->assertTrue($this->service()->reconcile($item->fresh(), [$this->drinks->id => 2], 'signed_link', null, $good)->changed());
    }

    /**
     * ⚠️⚠️ **El hecho se ata a la HIJA, nunca al principal.** Atarlo al principal deja las cuatro
     * identidades cerrando —son sumas y no ven una permuta entre líneas— y hace que el panel ofrezca
     * **devolver el importe de un extra que nadie pagó online**.
     */
    public function test_the_fact_is_attached_to_the_child_so_nothing_becomes_refundable(): void
    {
        $this->attach($this->drinks);
        $item = $this->party();
        $this->service()->reconcile($item, [$this->drinks->id => 2], 'signed_link');

        $child = $this->liveChild($item, $this->drinks);
        $adjustment = OrderAdjustment::where('order_id', $item->order_id)->latest('id')->firstOrFail();

        $this->assertSame($child->id, (int) $adjustment->order_item_id);
        $this->assertSame(OrderAdjustment::TYPE_EDIT, $adjustment->type);

        $order = Order::with(['payments.refunds', 'adjustments', 'items.children'])->findOrFail($item->order_id);
        $this->assertSame(0, $order->itemRefundableRemainderCents($child),
            'nadie pagó online por este extra: no hay nada que devolver');
    }

    /**
     * El libro tiene que saber NOMBRAR el gesto. Con `addon_change` no sabe —no lee las bajadas ni la
     * clave `removed`— y saldría «Cambios en Cumpleaños Jump» bajo un −12,00 €; con
     * `quantity_change` sobre la hija dice «Cubo de refrescos: 3 → 2».
     */
    public function test_the_book_names_the_gesture_with_the_addon_and_its_quantities(): void
    {
        $this->attach($this->drinks);
        $item = $this->party();
        $this->service()->reconcile($item, [$this->drinks->id => 3], 'signed_link');
        $this->service()->reconcile($item->fresh(), [$this->drinks->id => 2], 'signed_link');

        $labels = array_map(fn ($m): string => $m->label, $this->book($item)->movements);

        $this->assertContains('Cubo de refrescos: 0 → 3', $labels);
        $this->assertContains('Cubo de refrescos: 3 → 2', $labels);
    }

    // ── Bordes ─────────────────────────────────────────────────────────────────────────────────

    /** Un complemento GRATIS no revienta: `Order::recordEdit()` LANZA con delta cero. */
    public function test_a_free_addon_does_not_blow_up_the_save(): void
    {
        $balloons = $this->addon('Globos', 0, 22);
        $this->attach($balloons);
        $item = $this->party();

        $changes = $this->service()->reconcile($item, [$balloons->id => 2], 'signed_link');

        $this->assertTrue($changes->changed());
        $this->assertSame(0, $changes->deltaCents);
        $this->assertSame(0, OrderAdjustment::where('order_id', $item->order_id)->count(), 'sin importe no hay hecho que escribir');
        $this->assertBookCloses($item, 'con un extra gratis');
    }

    /** El tope del enganche es autoridad de servidor, no una pista de la pantalla. */
    public function test_the_attachment_cap_is_enforced_on_the_server(): void
    {
        $this->attach($this->drinks, maxQty: 3);
        $item = $this->party();

        $changes = $this->service()->reconcile($item, [$this->drinks->id => 99], 'signed_link');

        $this->assertSame(3, (int) $this->liveChild($item, $this->drinks)->quantity);
        $this->assertSame(3600, $changes->deltaCents);
    }

    /** `SEC-04` aplicado al tiempo: pasada la fiesta, nada se mueve — se re-comprueba bajo el lock. */
    public function test_a_finished_reservation_blocks_everything(): void
    {
        $this->attach($this->drinks);
        $item = $this->party(daysAhead: -5);

        $changes = $this->service()->reconcile($item, [$this->drinks->id => 2], 'signed_link');

        $this->assertFalse($changes->changed());
        $this->assertSame([['addon_id' => $this->drinks->id, 'reason' => 'closed']], $changes->blocked);
    }

    /** Y el rastro no lleva PII (`RGPD-02`): qué reserva, por dónde y cuánto se movió. */
    public function test_the_audit_trail_carries_no_personal_data(): void
    {
        $this->attach($this->drinks);
        $item = $this->party();
        $this->service()->reconcile($item, [$this->drinks->id => 2], 'account');

        $row = AuditLog::where('action', 'orders.postform_addons_changed')->firstOrFail();
        $payload = is_array($row->payload) ? $row->payload : [];

        $this->assertSame('account', $payload['via'] ?? null);
        $this->assertSame(2400, $payload['delta_cents'] ?? null);
        $this->assertStringNotContainsString('Mara', json_encode($payload) ?: '', 'ni un nombre en el rastro');
    }
}
