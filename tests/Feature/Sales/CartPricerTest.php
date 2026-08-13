<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\CartPricing;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 3 · paso 4a — la TARIFICACIÓN de la cesta (`Booking\Contracts\CartPricing`).
 *
 * Lo que de verdad prueba este fichero es una sola cosa: **que el presupuesto y el cobro son el
 * mismo número**. Mientras la aritmética vivió en tres métodos de `Livewire\Tickets\Purchase`, que
 * coincidiera con `OrderCreator` era una promesa escrita en un comentario —«ESPEJO EXACTO»— sin
 * nada que la sostuviera: si alguien tocaba una de las dos, el cliente veía un importe en la
 * pantalla de pago y otro en el TPV, y ningún test caía.
 *
 * Aquí se crea el pedido de VERDAD con la misma cesta y se comparan los dos pares de importes.
 * Con una cesta MIXTA y no vacía, que es la condición que el spec §6.3 exige después de descubrir
 * que el test de paridad de la v1 pasaba por construcción; y con su mutación, para que no vuelva a
 * pasar en vacío.
 */
class CartPricerTest extends TestCase
{
    use RefreshDatabase;

    private CartPricing $pricing;

    private OrderCreator $creator;

    private User $user;

    private Zone $zone;

    /** 180,00 € · señal fija de 30,00 € */
    private TicketType $deposit;

    /** 40,00 € · sin señal (se cobra entero online) */
    private TicketType $full;

    private string $date;

    private int $normalRateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricing = app(CartPricing::class);
        $this->creator = app(OrderCreator::class);
        $this->user = User::factory()->create();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->normalRateId = (int) RateType::where('key', 'normal')->value('id');

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00', '12:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 50, 'online_capacity' => 50,
            ]);
        }

        $this->deposit = $this->makeProduct('Con señal', 18000, TicketType::DEPOSIT_FIXED, 3000);
        $this->full = $this->makeProduct('Sin señal', 4000, TicketType::DEPOSIT_NONE, 0);
    }

    // ── El espejo: presupuesto == cobro ───────────────────────────────────────────────────────

    public function test_the_quote_of_a_mixed_cart_matches_what_the_order_will_charge(): void
    {
        // Cesta MIXTA y con complementos en las dos ramas de la Opción A (#225): un producto con
        // señal —cuyo complemento va íntegro al parque— y otro sin ella —cuyo complemento se cobra
        // online—. Es la cesta que distingue las dos reglas; con una sola línea, cualquier
        // aritmética habría pasado.
        $socks = $this->attachAddon($this->deposit, 'Calcetines', 2000);
        $drink = $this->attachAddon($this->full, 'Bebida', 500);

        $cart = [
            $this->line($this->deposit, '10:00:00', 1, [['ticket_type_id' => $socks->id, 'qty' => 1]]),
            $this->line($this->full, '11:00:00', 2, [['ticket_type_id' => $drink->id, 'qty' => 3]]),
        ];

        $quote = $this->pricing->quote($cart);
        $order = $this->creator->createPendingOrder($this->user, $cart);

        $this->assertSame(
            (int) $order->total, $quote->totalCents,
            'el total presupuestado no es el valor del pedido creado con la misma cesta'
        );
        $this->assertSame(
            $order->onlineDueCents(), $quote->onlineAmountCents,
            'lo que se anuncia como «a pagar ahora» no es lo que la pasarela va a cobrar'
        );

        // Y los números, escritos: 180 + 20 (complemento) + 2×40 + 3×5 (complemento) = 295,00 €;
        // online = 30 (señal) + 80 + 15 = 125,00 €.
        $this->assertSame(29500, $quote->totalCents);
        $this->assertSame(12500, $quote->onlineAmountCents);
    }

    /**
     * Control de la prueba anterior: si la cesta cambia, los importes tienen que cambiar. Sin este
     * control, un `quote()` que devolviera ceros pasaría el test del espejo comparando 0 con 0 —que
     * es exactamente cómo el test de paridad de la v1 del spec pasaba con la cesta vacía.
     */
    public function test_changing_the_cart_changes_both_amounts(): void
    {
        $one = $this->pricing->quote([$this->line($this->full, '10:00:00', 1)]);
        $two = $this->pricing->quote([$this->line($this->full, '10:00:00', 2)]);

        $this->assertNotSame($one->totalCents, $two->totalCents);
        $this->assertNotSame($one->onlineAmountCents, $two->onlineAmountCents);
        $this->assertSame(8000, $two->totalCents);
        $this->assertSame(8000, $two->onlineAmountCents);
    }

    /**
     * Regla de dinero que NO es obvia y que este test fija: una señal **fija** se cobra una vez por
     * LÍNEA, no por unidad. Duplicar la cantidad duplica el valor de la reserva pero no lo que se
     * cobra online. Lo hace así `TicketType::depositCents()`, que recibe el subtotal de la línea, y
     * `OrderCreator` la aplica igual —se comprueba creando el pedido—, así que el carrito y el TPV
     * dicen lo mismo. Si algún día se decide que la señal escale con las unidades, será una decisión
     * de producto y este test es el que obliga a tomarla en voz alta.
     */
    public function test_a_fixed_deposit_is_charged_once_per_line_not_per_unit(): void
    {
        $cart = [$this->line($this->deposit, '10:00:00', 2)];

        $quote = $this->pricing->quote($cart);
        $order = $this->creator->createPendingOrder($this->user, $cart);

        $this->assertSame(36000, $quote->totalCents);       // 2 × 180,00 €
        $this->assertSame(3000, $quote->onlineAmountCents); // la señal, UNA vez
        $this->assertSame((int) $order->total, $quote->totalCents);
        $this->assertSame($order->onlineDueCents(), $quote->onlineAmountCents);
    }

    // ── La señal, línea a línea (#225) ────────────────────────────────────────────────────────

    public function test_the_gate_remainder_of_a_deposit_line_includes_its_addons(): void
    {
        // Opción A: de un producto con señal se cobra online SOLO la señal del principal; el resto
        // y los complementos enteros se cobran en el parque.
        $socks = $this->attachAddon($this->deposit, 'Calcetines', 2000);

        $quote = $this->pricing->quote([
            $this->line($this->deposit, '10:00:00', 1, [['ticket_type_id' => $socks->id, 'qty' => 1]]),
        ]);

        $line = $quote->lines[0];
        $this->assertTrue($line->hasDeposit);
        $this->assertSame(3000, $line->depositCents);
        $this->assertSame(17000, $line->gateRemainderCents);   // (180 − 30) + 20
        $this->assertSame(18000, $line->subtotalCents);        // el principal, SIN complementos
        $this->assertSame(2000, $line->addonsSubtotalCents());
    }

    public function test_a_line_without_deposit_leaves_nothing_for_the_gate(): void
    {
        $quote = $this->pricing->quote([$this->line($this->full, '10:00:00', 1)]);

        $line = $quote->lines[0];
        $this->assertFalse($line->hasDeposit);
        $this->assertSame(4000, $line->depositCents);   // sin señal, «lo online» es la línea entera
        $this->assertSame(0, $line->gateRemainderCents);
        $this->assertFalse($quote->hasGateRemainder());
    }

    /**
     * Invariante de coherencia de la cesta entera: lo que no se cobra online es exactamente lo que
     * queda para el parque. Si un día alguien suma mal una de las tres cifras, esto lo destapa sin
     * tener que saber cuál de ellas era.
     */
    public function test_what_is_not_charged_online_is_what_the_gate_will_charge(): void
    {
        $socks = $this->attachAddon($this->deposit, 'Calcetines', 2000);

        $quote = $this->pricing->quote([
            $this->line($this->deposit, '10:00:00', 2, [['ticket_type_id' => $socks->id, 'qty' => 2]]),
            $this->line($this->full, '11:00:00', 1),
        ]);

        $gate = array_sum(array_map(fn ($line): int => $line->gateRemainderCents, $quote->lines));

        $this->assertSame($quote->totalCents - $quote->onlineAmountCents, $gate);
        $this->assertTrue($quote->hasGateRemainder());
    }

    // ── Diferencias deliberadas con el checkout ───────────────────────────────────────────────

    /**
     * Un producto sin precio para la tarifa del día NO es vendible ese día (`PAY-12`), y el
     * checkout lo rechaza. El presupuesto, en cambio, no puede reventar: es lo que se pinta en el
     * carrito, y romperlo dejaría al cliente sin poder ni siquiera quitar la línea. Lo dice con
     * `unitPriceCents` a null, que es distinto de un 0 (producto gratuito intencionado).
     */
    public function test_a_line_with_no_price_for_the_day_is_quoted_as_null_instead_of_throwing(): void
    {
        $priceless = TicketType::create([
            'name' => ['es' => 'Sin tarifa'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 5,
        ]);

        $quote = $this->pricing->quote([$this->line($priceless, '10:00:00', 3)]);

        $this->assertNull($quote->lines[0]->unitPriceCents);
        $this->assertSame(0, $quote->lines[0]->subtotalCents);
        $this->assertSame(0, $quote->totalCents);

        // Y el contraste que da sentido al null: el checkout SÍ rechaza esa misma cesta.
        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [$this->line($priceless, '10:00:00', 3)]);
    }

    public function test_a_zero_price_is_quoted_as_zero_and_not_as_missing(): void
    {
        $free = $this->makeProduct('Gratuito', 0, TicketType::DEPOSIT_NONE, 0);

        $quote = $this->pricing->quote([$this->line($free, '10:00:00', 2)]);

        $this->assertSame(0, $quote->lines[0]->unitPriceCents);
        $this->assertSame(0, $quote->totalCents);
    }

    /**
     * Una selección de complementos imposible (uno que ya no cuelga del producto) tarifica la línea
     * sin ellos en vez de romper. El error correcto lo dará el checkout, que es quien decide si la
     * reserva entra.
     */
    public function test_an_impossible_addon_selection_prices_the_line_without_addons(): void
    {
        $orphan = $this->attachAddon($this->full, 'Huérfano', 700);
        DB::table('product_addons')->where('addon_id', $orphan->id)->delete();

        $quote = $this->pricing->quote([
            $this->line($this->full, '10:00:00', 1, [['ticket_type_id' => $orphan->id, 'qty' => 1]]),
        ]);

        $this->assertSame([], $quote->lines[0]->addons);
        $this->assertSame(4000, $quote->totalCents);
    }

    // ── Qué entra en la cesta y qué no ────────────────────────────────────────────────────────

    /**
     * P8: una línea cuyo producto dejó de venderse no se tarifica ni se lista — mostrarla sería
     * anunciar algo que el checkout va a rechazar. El `index` de las que sobreviven se conserva:
     * es lo que permite al llamante señalar ESTA línea de la cesta que envió.
     */
    public function test_a_line_of_an_unsellable_product_is_dropped_keeping_the_index_of_the_rest(): void
    {
        $retired = $this->makeProduct('Retirado', 1500, TicketType::DEPOSIT_NONE, 0);
        $retired->update(['is_sellable' => false]);

        $quote = $this->pricing->quote([
            $this->line($retired, '10:00:00', 1),
            $this->line($this->full, '11:00:00', 1),
        ]);

        $this->assertCount(1, $quote->lines);
        $this->assertSame(1, $quote->lines[0]->index, 'el índice debe ser el de la cesta enviada, no el de la lista devuelta');
        $this->assertSame(4000, $quote->totalCents);
    }

    public function test_a_line_of_a_product_in_a_deactivated_zone_is_dropped(): void
    {
        $this->zone->update(['is_active' => false]);

        $quote = $this->pricing->quote([$this->line($this->full, '10:00:00', 1)]);

        $this->assertSame([], $quote->lines);
        $this->assertSame(0, $quote->totalCents);
    }

    public function test_a_malformed_line_is_discarded_by_the_canonical_cart_shape(): void
    {
        // Misma normalización que aplica `OrderCreator`: no puede haber dos ideas de qué es una
        // línea válida. Una línea sin fecha no lo es.
        $quote = $this->pricing->quote([
            ['ticket_type_id' => $this->full->id, 'qty' => 1],
            $this->line($this->full, '10:00:00', 1),
        ]);

        $this->assertCount(1, $quote->lines);
        $this->assertSame(4000, $quote->totalCents);
    }

    /**
     * Una cesta vacía no consulta tarifas. No es una optimización: `RateResolver::for()` lanza si la
     * instalación todavía no tiene ninguna tarifa configurada, así que resolverlas por adelantado
     * rompía pantallas que funcionaban (lo destapó la suite al extraer esto).
     */
    public function test_an_empty_cart_quotes_to_zero_without_resolving_any_rate(): void
    {
        RateType::query()->delete();

        $quote = $this->pricing->quote([]);

        $this->assertSame([], $quote->lines);
        $this->assertSame(0, $quote->totalCents);
        $this->assertSame(0, $quote->onlineAmountCents);
        $this->assertFalse($quote->hasGateRemainder());
    }

    // ── Complementos: lo resuelto, no lo pedido ───────────────────────────────────────────────

    /**
     * El complemento que se tarifica es el que `AddonResolver` decide —el mismo que cobrará
     * `OrderCreator`—, no el que el cliente pidió: las unidades incluidas salen gratis y el
     * excedente se cobra. Que la cantidad viaje resuelta es lo que permite al carrito decir
     * «incluido» en vez de cobrar dos veces.
     */
    public function test_addons_are_quoted_as_the_resolver_settles_them_not_as_requested(): void
    {
        $meal = $this->attachAddon($this->full, 'Comida', 1000, included: true, includedQuantity: 1);

        $quote = $this->pricing->quote([
            $this->line($this->full, '10:00:00', 1, [['ticket_type_id' => $meal->id, 'qty' => 3]]),
        ]);

        $addon = $quote->lines[0]->addons[0];
        $this->assertSame('Comida', $addon->name);
        $this->assertSame(3, $addon->quantity);
        $this->assertSame(1, $addon->freeQuantity);
        $this->assertSame(2000, $addon->subtotalCents);   // (3 − 1) × 10,00 €
        $this->assertSame(6000, $quote->totalCents);      // 40,00 € + 20,00 €
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────────────────

    private function makeProduct(string $name, int $priceCents, string $depositType, int $depositValue): TicketType
    {
        $type = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'position' => 1, 'deposit_type' => $depositType, 'deposit_value' => $depositValue,
        ]);
        $type->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $priceCents]);

        return $type;
    }

    private function attachAddon(TicketType $product, string $name, int $priceCents, bool $included = false, int $includedQuantity = 1): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 90,
        ]);
        $addon->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $priceCents]);

        DB::table('product_addons')->insert([
            'product_id' => $product->id, 'addon_id' => $addon->id, 'position' => 0,
            'is_included' => $included, 'included_quantity' => $includedQuantity, 'is_mandatory' => false,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true, 'choice_group' => null,
        ]);

        return $addon;
    }

    /**
     * @param  array<int, array{ticket_type_id:int, qty:int}>  $addons
     * @return array{ticket_type_id:int, date:string, time:string, qty:int, addons:array<int, array{ticket_type_id:int, qty:int}>}
     */
    private function line(TicketType $type, string $time, int $qty, array $addons = []): array
    {
        return [
            'ticket_type_id' => $type->id,
            'date' => $this->date,
            'time' => $time,
            'qty' => $qty,
            'addons' => $addons,
        ];
    }
}
