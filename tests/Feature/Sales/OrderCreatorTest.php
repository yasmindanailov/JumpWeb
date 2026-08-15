<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 5.3 — La cesta se convierte en un pedido `pending` (reserva pendiente de pago) con
 * re-validación de aforo en servidor. El pedido retiene la plaza y no caduca solo
 * (`expires_at` nulo). Importes calculados en servidor.
 */
class OrderCreatorTest extends TestCase
{
    use RefreshDatabase;

    private OrderCreator $creator;

    private User $user;

    private Zone $zone;

    private TicketType $h1;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = app(OrderCreator::class);
        $this->user = User::factory()->create();

        // Solo tarifa normal (weekdays null) → cualquier día resuelve a normal.
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00', '12:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id,
                'date' => $this->date,
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 10,
                'online_capacity' => 10,
            ]);
        }

        $this->h1 = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'],
            'zone_id' => $this->zone->id,
            'duration_min' => 60,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 1,
        ]);
        $this->h1->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'),
            'amount_cents' => 1000,
        ]);
    }

    /** @return array{ticket_type_id:int, date:string, time:string, qty:int} */
    private function line(string $time, int $qty, ?int $typeId = null): array
    {
        return ['ticket_type_id' => $typeId ?? $this->h1->id, 'date' => $this->date, 'time' => $time, 'qty' => $qty];
    }

    private function occupyWithPaidOrder(string $time, int $seats): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
        ]);
        $slot = Slot::where('zone_id', $this->zone->id)->where('date', $this->date)->where('start_time', $time)->first();
        $order->items()->create([
            'ticket_type_id' => $this->h1->id,
            'slot_id' => $slot->id,
            'quantity' => $seats,
            'unit_price' => 1000,
            'seats' => $seats,
        ]);
    }

    public function test_creates_a_pending_order_that_holds_the_seat_and_does_not_expire(): void
    {
        $order = $this->creator->createPendingOrder($this->user, [$this->line('10:00:00', 2)]);

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertNull($order->expires_at);                 // retiene plaza, no caduca sola
        $this->assertStringStartsWith(PaymentSettings::ORDER_PREFIX_DEFAULT, $order->code);
        $this->assertSame(2000, $order->subtotal);
        $this->assertSame(2000, $order->total);

        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertSame(2, $item->quantity);
        $this->assertSame(1000, $item->unit_price);
        $this->assertSame(2, $item->seats);
    }

    public function test_order_code_uses_the_configurable_prefix_setting(): void
    {
        // DECISIONES #12.b: prefijo por instalación, data-driven; default R- sin fila.
        Setting::create(['key' => 'sales.order_prefix', 'value' => 'acme-', 'group' => 'payment']);
        Setting::flushMemo();

        $order = $this->creator->createPendingOrder($this->user, [$this->line('10:00:00', 1)]);

        // Se normaliza a mayúsculas y precede al sufijo aleatorio de 6.
        $this->assertMatchesRegularExpression('/^ACME-[A-Z0-9]{6}$/', $order->code);
    }

    public function test_order_prefix_falls_back_to_default_when_the_setting_is_corrupt(): void
    {
        // Un setting roto (espacios, demasiado largo) NUNCA rompe la emisión de códigos.
        Setting::create(['key' => 'sales.order_prefix', 'value' => 'con espacios y muy largo', 'group' => 'payment']);
        Setting::flushMemo();

        $order = $this->creator->createPendingOrder($this->user, [$this->line('11:00:00', 1)]);

        $this->assertStringStartsWith(PaymentSettings::ORDER_PREFIX_DEFAULT, $order->code);
    }

    public function test_can_create_a_provisional_held_order(): void
    {
        $order = $this->creator->createPendingOrder($this->user, [$this->line('10:00:00', 1)], OrderCreator::verificationHoldUntil());

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertNotNull($order->expires_at);        // provisional: retiene la plaza
        $this->assertTrue($order->expires_at->isFuture());
    }

    public function test_rejects_a_line_below_the_product_min_advance(): void
    {
        // Backstop de checkout (auditoría Fase 1): antelación mínima 5 días; la fecha del setUp
        // (hoy+2) no llega → ReservationException y NADA persistido (la oferta ya la oculta; esto
        // blinda contra peticiones forjadas/obsoletas).
        $this->h1->update(['min_advance_value' => 5, 'min_advance_unit' => TicketType::UNIT_DAYS]);

        try {
            $this->creator->createPendingOrder($this->user, [$this->line('10:00:00', 1)]);
            $this->fail('Debía rechazar la línea por no cumplir la antelación mínima.');
        } catch (ReservationException) {
            // esperado
        }

        $this->assertSame(0, Order::count(), 'no se persiste nada cuando falla la validación');
    }

    public function test_generates_unique_codes(): void
    {
        $a = $this->creator->createPendingOrder($this->user, [$this->line('10:00:00', 1)]);
        $b = $this->creator->createPendingOrder($this->user, [$this->line('11:00:00', 1)]);

        $this->assertNotSame($a->code, $b->code);
    }

    public function test_empty_cart_is_rejected(): void
    {
        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, []);
    }

    public function test_rejects_when_the_slot_is_already_full(): void
    {
        $this->occupyWithPaidOrder('10:00:00', 10); // online_capacity = 10 → lleno

        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line('10:00:00', 1)]);
    }

    public function test_rejects_when_cart_lines_together_exceed_capacity(): void
    {
        // 6 + 6 = 12 plazas en la misma franja (aforo 10) → no cabe.
        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line('10:00:00', 6), $this->line('10:00:00', 6)]);
    }

    public function test_does_not_create_anything_when_validation_fails(): void
    {
        $this->occupyWithPaidOrder('10:00:00', 10);

        try {
            $this->creator->createPendingOrder($this->user, [$this->line('10:00:00', 1)]);
        } catch (ReservationException) {
            // esperado
        }

        $this->assertSame(0, Order::where('user_id', $this->user->id)->count()); // transacción intacta
    }

    public function test_supports_multiple_zones_in_one_order(): void
    {
        $kids = Zone::create(['slug' => 'kids', 'name' => ['es' => 'KIDS']]);
        Slot::create([
            'zone_id' => $kids->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);
        $kidsType = TicketType::create([
            'name' => ['es' => 'Kids'], 'zone_id' => $kids->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
        ]);
        $kidsType->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 800]);

        $order = $this->creator->createPendingOrder($this->user, [
            $this->line('10:00:00', 1),
            $this->line('10:00:00', 1, $kidsType->id),
        ]);

        $this->assertCount(2, $order->items);
        $this->assertSame(1800, $order->total);
    }

    public function test_rejects_when_the_product_has_no_price_for_the_day(): void
    {
        // Tipo vendible PERO sin precio para la tarifa del día → no debe crear un pedido a 0 €.
        $noPrice = TicketType::create([
            'name' => ['es' => 'Sin tarifa'],
            'zone_id' => $this->zone->id,
            'duration_min' => 60,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => 9,
        ]);

        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line('10:00:00', 1, $noPrice->id)]);
    }

    /** Crea un complemento aplicable a la entrada h1 (pivote). */
    private function makeAddon(int $price = 300): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 50,
        ]);
        $addon->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => $price]);
        DB::table('product_addons')->insert(['product_id' => $this->h1->id, 'addon_id' => $addon->id, 'position' => 0]);

        return $addon;
    }

    public function test_creates_a_nested_addon_grouped_under_its_product(): void
    {
        $addon = $this->makeAddon(300);

        $order = $this->creator->createPendingOrder($this->user, [[
            'ticket_type_id' => $this->h1->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1,
            'addons' => [['ticket_type_id' => $addon->id, 'qty' => 2]],
        ]]);

        $this->assertCount(2, $order->items);
        $this->assertSame(1600, $order->total);            // 1000 entrada + 2 × 300 complemento

        $productItem = $order->items->firstWhere('ticket_type_id', $this->h1->id);
        $addonItem = $order->items->firstWhere('ticket_type_id', $addon->id);
        $this->assertNull($addonItem->slot_id);            // sin franja
        $this->assertSame(0, $addonItem->seats);           // no consume aforo
        $this->assertSame($productItem->id, $addonItem->parent_item_id); // agrupado bajo su producto
    }

    public function test_rejects_a_nested_addon_not_applicable_to_the_product(): void
    {
        // Complemento sin asociar a h1 en el pivote → no aplica a esa entrada.
        $addon = TicketType::create([
            'name' => ['es' => 'Tarta'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 51,
        ]);
        $addon->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 2500]);

        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [[
            'ticket_type_id' => $this->h1->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1,
            'addons' => [['ticket_type_id' => $addon->id, 'qty' => 1]],
        ]]);
    }

    public function test_rejects_a_cart_with_more_than_max_lines(): void
    {
        // Auditoría Fase 1 (L6): el cap de líneas es un INVARIANTE de servidor (regla 12). Una cesta
        // forjada con más de MAX_LINES_PER_CART líneas se rechaza al crear el pedido —antes de bloquear
        // decenas de franjas (contención de BD)—, aunque la UI (`addToCart`) ya lo compruebe.
        // ⚠️ El tope se lee de `OrderCreator`, que es quien lo DEFINE y el sujeto de este test. Se leía
        // del componente Livewire —que solo lo refleja para la UI—, y eso ataba un test de servidor a
        // una clase de interfaz condenada (Fase 4 · paso 4.7·2b).
        $cart = [];
        for ($i = 0; $i <= OrderCreator::MAX_LINES_PER_CART; $i++) {
            $cart[] = $this->line('10:00:00', 1);
        }

        $this->expectException(ReservationException::class);
        $this->expectExceptionMessage('tickets.errors.cart_too_large');
        $this->creator->createPendingOrder($this->user, $cart);
    }

    public function test_rejects_a_today_slot_whose_start_time_already_passed(): void
    {
        // Auditoría Fase 1 (H4 · corte intra-día): el checkout es la última línea de defensa
        // (regla 12). La oferta ya no muestra una franja de HOY cuya hora pasó, pero una cesta
        // OBSOLETA del mismo día (o una petición forjada) podría intentar reservarla. El backstop
        // de OrderCreator la rechaza con la MISMA fuente que la oferta (`SlotOffer::passesIntradayFloor`).
        Carbon::setTestNow('2026-06-15 18:00:00'); // tarde; en zona Madrid son las 20:00
        try {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => '2026-06-15',
                'start_time' => '09:00:00', 'end_time' => '10:00:00',
                'capacity' => 10, 'online_capacity' => 10,
            ]);

            $this->expectException(ReservationException::class);
            $this->creator->createPendingOrder($this->user, [[
                'ticket_type_id' => $this->h1->id, 'date' => '2026-06-15', 'time' => '09:00:00', 'qty' => 1,
            ]]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_slot_lock_uses_literal_zones_and_precedes_consistent_reads(): void
    {
        // Auditoría Fase 1 (H2) + #246 (sobreventa por snapshot, REPRODUCIDA con `purchase:verify-oversell`):
        // bajo REPEATABLE READ el snapshot de las lecturas consistentes se fija en la PRIMERA de ellas.
        // Para que el recuento de aforo vea lo que un rival commiteó, el `FOR UPDATE` de franjas debe ser
        // la primera operación de la transacción Y NO contener una subconsulta —que sería ELLA MISMA una
        // lectura consistente que fija el snapshot ANTES del lock → sobreventa (#246)—. Las zonas se
        // resuelven FUERA de la transacción para poder lockear con literales. En SQLite el lock es no-op,
        // pero el ORDEN y la ausencia de subconsulta —lo que evita la sobreventa— se preservan igual.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->creator->createPendingOrder($this->user, [$this->line('10:00:00', 1)]);
        $queries = collect(DB::getQueryLog())->map(fn ($q) => strtolower($q['query']))->values();
        DB::disableQueryLog();

        // (a) El lock de franjas usa zonas LITERALES, SIN anidar una subconsulta a `ticket_types` (#246).
        $slotsQuery = $queries->first(fn (string $sql): bool => (bool) preg_match('/from ["`]slots["`]/', $sql));
        $this->assertNotNull($slotsQuery, 'debe existir el SELECT de franjas (lock)');
        $this->assertStringNotContainsString('ticket_types', $slotsQuery,
            'el lock de franjas debe usar zonas LITERALES, sin subconsulta a ticket_types (#246: la subconsulta fija el snapshot antes del lock → sobreventa)');

        // (b) Ese lock precede a la carga CONSISTENTE de TicketType vendible (1ª lectura consistente
        // DENTRO de la transacción): así su snapshot se fija ya con el lock en mano.
        $slotsIdx = $queries->search(fn (string $sql): bool => (bool) preg_match('/from ["`]slots["`]/', $sql));
        $sellableIdx = $queries->search(fn (string $sql): bool => str_contains($sql, 'is_sellable'));
        $this->assertNotFalse($sellableIdx, 'debe cargar TicketType vendible');
        $this->assertLessThan($sellableIdx, $slotsIdx,
            'el lock de franjas debe ir ANTES de la carga consistente de TicketType vendible');
    }
}
