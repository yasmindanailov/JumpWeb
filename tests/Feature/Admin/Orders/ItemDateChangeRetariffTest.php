<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\RateResolver;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Mover la FECHA de una reserva RE-TARIFICA al precio del día destino** (`DECISIONES #127(d)`).
 *
 * ⚠️⚠️ Antes conservaba la tarifa pagada, y eso **no era una política: era un arbitraje abierto**.
 * Comprar el día barato y llamar para cambiarlo al sábado salía gratis, y el descuento era
 * exactamente la diferencia de tarifa. Medido antes del arreglo: 16,00 € en las dos direcciones, con
 * el previo del operador **afirmando** «sin cambio de precio».
 *
 * La regla, en sus tres direcciones:
 *  1. el precio es el de HOY del **día destino** — «pagas el precio del día que elijas»;
 *  2. si **sube**, la diferencia se cobra **en el parque**, como cualquier otra subida (no toca la
 *     pasarela: el segundo cobro online se descartó por su fricción);
 *  3. si **baja**, se le **abona** y aflora como «pendiente de devolución», como cualquier bajada.
 *
 * ⚠️ Y su LÍMITE, que es la mitad del contrato: **solo re-tarifica el cambio de FECHA**. Una edición
 * que no mueve el día conserva la tarifa histórica del ítem.
 */
class ItemDateChangeRetariffTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private string $saturday = '2026-06-06';   // tarifa fin de semana: 20,00

    private string $sunday = '2026-06-07';     // mismo TIPO de día que el sábado: 20,00

    private string $monday = '2026-06-08';     // tarifa normal: 12,00

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00'));
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Notification::fake();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true]);
        RateType::create(['key' => 'weekend', 'label' => ['es' => 'Fin de semana'],
            'weekdays' => [0, 6], 'priority' => 10, 'is_active' => true]);   // 0=dom, 6=sáb

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entry->prices()->create([
            'rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'),
            'amount_cents' => 1200,
        ]);
        $this->entry->prices()->create([
            'rate_type_id' => RateType::where('key', 'weekend')->value('id'),
            'amount_cents' => 2000,
        ]);

        foreach ([$this->saturday, $this->sunday, $this->monday] as $d) {
            foreach (range(9, 14) as $h) {
                Slot::create([
                    'zone_id' => $this->zone->id, 'date' => $d,
                    'start_time' => sprintf('%02d:00:00', $h),
                    'end_time' => sprintf('%02d:00:00', $h + 1),
                    'capacity' => 60, 'online_capacity' => 60,
                    'online_sales_open' => true, 'status' => Slot::STATUS_OPEN,
                ]);
            }
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_moving_to_a_pricier_day_charges_the_difference_at_the_gate(): void
    {
        [$order, $item] = $this->paidOrderOn($this->monday, qty: 2);   // 2 × 12,00 = 24,00

        $this->moveTo($order, $item, $this->saturday);

        $item->refresh();
        $this->assertSame(2000, (int) $item->unit_price, 'la reserva tiene que pasar a la tarifa del sábado');

        $summary = $this->fresh($order)->financialSummary();
        $this->assertSame(4000, $summary->totalFinalNeto(), 'el valor pasa a 2 × 20,00');
        $this->assertSame(
            1600, $summary->pendingAtGate(),
            'la diferencia (2 × 8,00) se cobra EN EL PARQUE, como cualquier otra subida',
        );
        $this->assertSame(2400, $summary->pagadoOnline(), 'lo pagado por web no se toca');
        $this->assertSame(0, $summary->pendienteDevolucion());
    }

    public function test_moving_to_a_cheaper_day_credits_the_difference_back(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 2);   // 2 × 20,00 = 40,00

        $this->moveTo($order, $item, $this->monday);

        $item->refresh();
        $this->assertSame(1200, (int) $item->unit_price, 'la reserva pasa a la tarifa del lunes');

        $summary = $this->fresh($order)->financialSummary();
        $this->assertSame(2400, $summary->totalFinalNeto(), 'el valor baja a 2 × 12,00');
        $this->assertSame(
            1600, $summary->pendienteDevolucion(),
            'la diferencia se le ABONA y queda pendiente de devolver, como cualquier bajada',
        );
        $this->assertSame(0, $summary->pendingAtGate());
    }

    /**
     * **El ajuste que nace de mover la fecha DICE que fue la fecha** (`DECISIONES #145`).
     *
     * ⚠️ Este caso conduce la ACCIÓN REAL del panel a propósito, y no comprueba `breakdownLabel()`
     * sobre un ajuste montado a mano: el defecto no estaba en el helper —que ya sabía leer
     * `changes`— sino en **quién lo alimenta**. `executeItemEdit` filtraba el contexto a
     * `product_change` y `quantity_change`, así que un cambio de franja llegaba con
     * `context = {"changes": []}`. Un test sobre el helper suelto habría salido verde con el
     * defecto vivo: es la lección que `#127` pagó midiendo por mutación.
     *
     * ▶ El filtro es del commit fundacional, cuando mover la fecha NO re-tarificaba; `PAY-18`
     * (2026-08-24) creó la causa nueva y nadie lo extendió. Medido en `R-S9XDYB` (staging): tres
     * líneas de ajuste con la MISMA etiqueta muda.
     */
    public function test_a_date_move_leaves_a_trace_of_why_the_money_changed(): void
    {
        // ⚠️ Se mueve a un día MÁS CARO a propósito. La subida crea siempre un `extra_due`, que es
        // la fila cuya etiqueta el operador lee. La bajada solo crea ajuste si hay dinero de puerta
        // contra el que acreditar: en un pedido pagado íntegro online aflora como «pendiente de
        // devolución» y NO deja fila — ver la nota al final de esta clase.
        [$order, $item] = $this->paidOrderOn($this->monday, qty: 2);

        $this->moveTo($order, $item, $this->saturday);

        $adjustment = $this->fresh($order)->adjustments()
            ->where('order_item_id', $item->id)
            ->where('amount_cents', '>', 0)
            ->latest('id')
            ->first();

        $this->assertNotNull($adjustment, 'una subida de precio deja un ajuste a cobrar en puerta');

        $this->assertArrayHasKey(
            'slot_change',
            $adjustment->context['changes'] ?? [],
            'El ajuste tiene que llevar la CAUSA. Sin ella el contexto llega vacío y la etiqueta '
            .'del desglose cae al texto de respaldo, que es lo que el operador leía tres veces seguidas.',
        );

        $this->assertSame(
            __('tickets.gate_change_line_slot', [
                'when' => $adjustment->context['changes']['slot_change']['new'],
            ]),
            $adjustment->breakdownLabel(),
            'Y el desglose «A cobrar en el parque» tiene que decir que fue un cambio de fecha, '
            .'no repetir el nombre del producto.',
        );
    }

    /**
     * ⚠️ El EFECTO LATERAL aceptado a sabiendas (`DECISIONES #127(d)`): mover de un sábado a otro día
     * del MISMO tipo de tarifa aplica igualmente el precio de catálogo de HOY. Si el parque subió
     * precios desde la compra, se cobra la subida. Se descartó la alternativa —cobrar solo la
     * diferencia entre tipos de día— por ser más difícil de explicar y de vigilar.
     */
    public function test_moving_within_the_same_rate_applies_todays_catalogue_price(): void
    {
        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 1);
        // El cliente compró con un precio viejo; hoy el catálogo del fin de semana son 20,00.
        $item->forceFill(['unit_price' => 1800])->save();
        $this->fresh($order)->update(['subtotal' => 1800, 'total' => 1800]);
        $order->payments()->update(['amount' => 1800]);

        $this->moveTo($order, $item, $this->sunday);   // mismo tipo de día

        $this->assertSame(2000, (int) $item->fresh()->unit_price);
        $this->assertSame(
            200, $this->fresh($order)->financialSummary()->pendingAtGate(),
            'se cobra la subida de catálogo, aunque no cambie el tipo de día',
        );
    }

    /**
     * ⚠️⚠️ **EL LÍMITE de la regla, y es la mitad del contrato**: una edición que NO mueve el día
     * conserva la tarifa histórica del ítem. Aplicar el catálogo actual a toda edición sería un
     * cambio mucho mayor que nadie ha pedido — y le cobraría al cliente una subida de precios por
     * añadir una unidad.
     */
    public function test_an_edit_that_does_not_move_the_day_keeps_the_historic_rate(): void
    {
        [$order, $item] = $this->paidOrderOn($this->monday, qty: 1);
        $item->forceFill(['unit_price' => 900])->save();   // compró con un precio viejo/descuento
        $this->fresh($order)->update(['subtotal' => 900, 'total' => 900]);
        $order->payments()->update(['amount' => 900]);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item->fresh('slot'), ['quantity' => 2]),
                arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $this->assertSame(900, (int) $item->fresh()->unit_price, 'subir la cantidad no puede re-tarificar');
        $this->assertSame(
            900, $this->fresh($order)->financialSummary()->pendingAtGate(),
            'la unidad extra se cobra a SU tarifa, no a la de catálogo',
        );
    }

    /** Control: cambiar de PRODUCTO sigue tarificando por el catálogo del día, como siempre. */
    public function test_changing_the_product_still_prices_from_the_catalogue(): void
    {
        $other = TicketType::create([
            'name' => ['es' => 'Jump alt'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 2,
        ]);
        $other->prices()->create(['rate_type_id' => RateType::where('key', 'weekend')->value('id'), 'amount_cents' => 2500]);
        $other->prices()->create(['rate_type_id' => RateType::where('key', RateType::KEY_NORMAL)->value('id'), 'amount_cents' => 1500]);

        [$order, $item] = $this->paidOrderOn($this->saturday, qty: 1);

        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->callAction('manageItem',
                data: $this->editData($item->fresh('slot'), ['product_id' => $other->id]),
                arguments: ['item' => $item->id])
            ->assertHasNoActionErrors();

        $this->assertSame(2500, (int) $item->fresh()->unit_price);
    }

    /**
     * **Lo que el OPERADOR ve antes de guardar tiene que ser lo que se cobra.**
     *
     * ⚠️ Hasta el arreglo, el previo **afirmaba** «sin cambio de precio» (diferencia 0,00 €) cuando la
     * diferencia real de catálogo eran 16,00 €. No era silencio: era una afirmación falsa, y el
     * operador movía la reserva creyéndola. El previo y el guardado comparten `computeEditPricing`
     * precisamente para que no puedan divergir.
     */
    public function test_the_operator_preview_shows_the_real_difference(): void
    {
        [$order, $item] = $this->paidOrderOn($this->monday, qty: 2);

        $page = new ViewOrder;
        $page->record = $this->fresh($order);
        $page->calendarSelectedDate = $this->saturday;

        $ref = new \ReflectionMethod(ViewOrder::class, 'computeEditPricing');
        $ref->setAccessible(true);
        $pricing = $ref->invoke($page, $item->fresh('slot', 'ticketType'), (int) $item->ticket_type_id, 2, $this->saturday);

        $this->assertSame(2400, $pricing['old']);
        $this->assertSame(4000, $pricing['new'], 'el previo tiene que tarificar al día destino');
        $this->assertSame(1600, $pricing['diff'], 'y enseñar la diferencia REAL, no «sin cambio»');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Mueve la reserva de día por el CALENDARIO, que es como lo hace el operador. */
    private function moveTo(Order $order, OrderItem $item, string $date): void
    {
        Livewire::actingAs($this->staff())
            ->test(ViewOrder::class, ['record' => $order->code])
            ->mountAction('manageItem', arguments: ['item' => $item->id])
            ->set('calendarSelectedDate', $date)
            ->set('calendarSelectedTime', '10:00:00')
            ->callMountedAction($this->editData($item->fresh('slot')))
            ->assertHasNoActionErrors();
    }

    /** @return array{0: Order, 1: OrderItem} */
    private function paidOrderOn(string $date, int $qty): array
    {
        $unit = (int) app(RateResolver::class)
            ->priceCents($this->entry, Carbon::parse($date));
        $total = $unit * $qty;

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-TAR'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => $total, 'total' => $total,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) ($this->counter + 400000), 10, '0', STR_PAD_LEFT),
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->entry->id,
            'slot_id' => Slot::where('zone_id', $this->zone->id)->where('date', $date)
                ->where('start_time', '10:00:00')->firstOrFail()->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);

        return [$this->fresh($order), $item->fresh('slot', 'ticketType')];
    }

    private function fresh(Order $order): Order
    {
        return Order::with(['items.children.ticketType', 'items.ticketType', 'items.slot',
            'payments.refunds', 'adjustments'])->findOrFail($order->id);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(
            Permission::whereIn('name', ['orders.view', 'orders.edit_item'])->pluck('id')
        );

        return $u;
    }

    /** @return array<string,mixed> */
    private function editData(OrderItem $item, array $overrides = []): array
    {
        $slot = $item->slot;

        return array_merge([
            'optimistic_token' => (string) ($item->updated_at?->getTimestamp() ?? ''),
            'product_id' => (int) $item->ticket_type_id,
            'quantity' => (int) $item->quantity,
            'slot_date' => $slot?->date?->toDateString() ?? '',
            'slot_time' => $slot?->start_time ?? '',
        ], $overrides);
    }
}
