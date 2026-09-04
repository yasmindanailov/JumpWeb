<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Contracts\AddonDateChange;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AddonDateReconciler;
use App\Domain\Booking\Services\MixedPartySettings;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **MOVER EL DÍA MUEVE TAMBIÉN LAS CONDICIONES DE LOS COMPLEMENTOS** (`specs/hora-extra.md` §9,
 * `DECISIONES #417`).
 *
 * Lo que estas guardas protegen no es «que se retire una línea»: es que **el LIBRO siga cerrando
 * después**, porque las dos escrituras son asimétricas y equivocarse en cualquiera de las dos deja
 * el pedido «en revisión» — o sea al cliente sin su desglose (`#132`) — por haber movido una fecha:
 *
 *  · **retirar** escribe `markCancelled()` y **ningún** hecho: el libro ya emite su `−fila`;
 *  · **re-tarificar** escribe `unit_price` **y su `recordEdit(±Δ)`**: sin él, `nac` se desplaza.
 *
 * Por eso hay `assertBookCloses()` después de cada gesto, y no solo al final.
 */
class AddonDateReconcilerTest extends TestCase
{
    use RefreshDatabase;

    private const SATURDAY = '2026-09-12';   // tarifa `special`

    private const TUESDAY = '2026-09-15';    // tarifa `normal`

    private Zone $zone;

    private TicketType $entry;

    /** Solo se vende en `special`: es la hora extra. */
    private TicketType $weekendOnly;

    /** Mismo precio los dos días: el CONTROL de que esto es no-op para lo que no depende del día. */
    private TicketType $flat;

    /** Ocupante que SÍ se vende los dos días: da la franja de aterrizaje que la retirada no debe heredar. */
    private TicketType $alwaysOccupant;

    private User $user;

    private User $operator;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        RateType::create(['key' => 'special', 'label' => ['es' => 'Findes'], 'is_special' => true, 'weekdays' => [5, 6, 0], 'priority' => 10]);

        $this->user = User::factory()->create();
        $this->operator = $this->staffWithEditPermission();

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 2 horas'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 120, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->price($this->entry, 'normal', 1800);
        $this->price($this->entry, 'special', 2200);

        $this->weekendOnly = $this->addon('Hora extra', 30, occupies: true);
        $this->price($this->weekendOnly, 'special', 800);

        $this->flat = $this->addon('Calcetines', 31);
        $this->price($this->flat, 'normal', 300);
        $this->price($this->flat, 'special', 300);

        // Un SEGUNDO ocupante que sí se vende los dos días: sin él no existe el escenario en que una
        // hija sobrevive y otra no, que es el único donde se puede distinguir si las retiradas se
        // mudan con el padre (lo pidió el arnés).
        $this->alwaysOccupant = $this->addon('Hora extra siempre', 32, occupies: true);
        $this->price($this->alwaysOccupant, 'normal', 400);
        $this->price($this->alwaysOccupant, 'special', 400);

        foreach ([$this->weekendOnly, $this->flat, $this->alwaysOccupant] as $i => $addon) {
            $this->entry->configurableAddons()->attach($addon->id, [
                'position' => $i + 1, 'quantity_mode' => ProductAddon::MODE_FIXED,
                'stage' => ProductAddon::STAGE_BOOKING,
            ]);
        }
        $this->entry->refresh();

        foreach ([self::SATURDAY, self::TUESDAY] as $date) {
            foreach (['11:00:00', '13:00:00', '17:00:00', '19:00:00'] as $start) {
                Slot::create([
                    'zone_id' => $this->zone->id, 'date' => $date, 'start_time' => $start,
                    'end_time' => Carbon::parse($start)->addHours(2)->format('H:i:s'),
                    'capacity' => 50, 'online_capacity' => 50,
                ]);
            }
        }
    }

    private function staffWithEditPermission(): User
    {
        $role = Role::firstOrCreate(['name' => 'staff'], ['label' => 'Staff']);
        foreach (['orders.edit_item', 'orders.view'] as $name) {
            $perm = Permission::firstOrCreate(['name' => $name], ['label' => $name]);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }
        $u = User::factory()->create();
        $u->roles()->sync([$role->id]);

        return $u->fresh();
    }

    private function addon(string $name, int $position, bool $occupies = false): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => $position,
        ] + ($occupies ? ['occupies_after_parent' => true, 'duration_min' => 60] : []));
    }

    private function price(TicketType $type, string $rateKey, int $cents): void
    {
        $rate = RateType::where('key', $rateKey)->firstOrFail();
        Price::updateOrCreate(
            ['priceable_type' => $type->getMorphClass(), 'priceable_id' => $type->id, 'rate_type_id' => $rate->id],
            ['amount_cents' => $cents, 'currency' => 'EUR'],
        );
    }

    /** Un pedido PAGADO del sábado con los complementos que se le pidan. */
    private function soldOnSaturday(array $addons, string $time = '11:00:00'): Order
    {
        $order = app(OrderCreator::class)->createPendingOrder($this->user, [[
            'ticket_type_id' => $this->entry->id, 'date' => self::SATURDAY, 'time' => $time,
            'qty' => 2, 'addons' => $addons,
        ]]);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->onlineDueCents(), 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (770000 + ++$this->counter), 10, '0', STR_PAD_LEFT),
        ]);

        return $this->reload($order->id);
    }

    private function reload(int $orderId): Order
    {
        return Order::with(['items.children', 'items.slot', 'items.ticketType.addons', 'adjustments', 'payments.refunds'])
            ->findOrFail($orderId);
    }

    private function moveTo(Order $order, string $date, string $time = '11:00:00'): object
    {
        $order = $this->reload($order->id);
        $item = $order->items->firstWhere('parent_item_id', null);

        return app(OrderItemEditor::class)->edit(
            order: $order, item: $item, newDate: $date, newTime: $time, slotChanged: true,
            newProductId: (int) $this->entry->id, newQty: 2, eventData: null, addonEdits: [],
            optimisticToken: (string) $item->updated_at->getTimestamp(), by: $this->operator,
        );
    }

    private function assertBookCloses(int $orderId, string $context): OrderBook
    {
        $book = OrderBook::forOrder($this->reload($orderId));
        $this->assertTrue(
            $book->isConsistent,
            "el libro dejó de cerrar {$context}: el pedido queda «en revisión» y el cliente sin su desglose (#132)",
        );

        return $book;
    }

    /** El caso del encargo: el día nuevo no vende ese producto → se retira y queda a devolver. */
    public function test_a_child_not_sold_on_the_new_day_is_withdrawn_and_the_book_still_closes(): void
    {
        $order = $this->soldOnSaturday([['ticket_type_id' => $this->weekendOnly->id, 'qty' => 1]]);
        $this->assertSame(5200, (int) $order->total, '2 × 22,00 € + 8,00 € de hora extra');
        $this->assertBookCloses($order->id, 'al nacer');

        $this->assertTrue($this->moveTo($order, self::TUESDAY, '17:00:00')->ok, 'el movimiento tiene que pasar');

        $after = $this->reload($order->id);
        $child = $after->items->firstWhere('ticket_type_id', $this->weekendOnly->id);

        $this->assertNotNull($child->cancelled_at, 'la hora extra tiene que retirarse: ese día no se vende');
        $this->assertNull($child->slot_id, 'una hija retirada no puede seguir ocupando su franja');
        $this->assertSame(0, (int) $child->seats, 'ni sus plazas');

        // ⚠️ El total que vale es el del LIBRO, no `orders.total`: esa columna es lo FACTURADO al
        // nacer y no se reescribe (`#305`). Aseverarla decía «no cambió nada» con la retirada hecha.
        $book = $this->assertBookCloses($order->id, 'tras retirar la hora extra');
        $this->assertSame(3600, $book->totalCents, '2 × 18,00 € del padre re-tarificado; la hora extra ya no suma');
        $this->assertSame('refund_at_park', $book->balance->kind, 'lo pagado de más se devuelve EN EL PARQUE (#244)');
    }

    /**
     * ⚠️⚠️ **La guarda de §9.8·H1**: el movimiento NO puede bloquearse por el aforo de una hija que se
     * iba a retirar igualmente. Se deja la franja de aterrizaje del destino SIN plazas: con el orden
     * viejo —validar antes de decidir— esto devolvía `addon_occupancy_at_destination`.
     */
    public function test_a_full_landing_slot_does_not_block_a_move_whose_child_gets_withdrawn(): void
    {
        $order = $this->soldOnSaturday([['ticket_type_id' => $this->weekendOnly->id, 'qty' => 1]]);

        Slot::where('zone_id', $this->zone->id)->whereDate('date', self::TUESDAY)
            ->where('start_time', '19:00:00')
            ->update(['capacity' => 0, 'online_capacity' => 0]);

        $outcome = $this->moveTo($order, self::TUESDAY, '17:00:00');

        $this->assertTrue(
            $outcome->ok,
            'bloqueado por un aforo que nadie va a consumir: esa hija se retira ese día (§9.8·H1)',
        );
        $this->assertNotNull(
            $this->reload($order->id)->items->firstWhere('ticket_type_id', $this->weekendOnly->id)->cancelled_at,
        );
    }

    /**
     * ⚠️⚠️ **La guarda de §9.8·H2, y es la que más protege.** Los portadores de fiesta mixta no
     * tienen precio en catálogo NINGÚN día, así que la regla «sin precio ⇒ retirar» los retiraría en
     * **todos** los cambios de fecha — y `MixedPartySurcharge` los gobierna en el mismo post-commit:
     * dos servicios peleando por la misma línea, con el dinero moviéndose dos veces.
     */
    public function test_the_mixed_party_carriers_are_never_governed_by_this_service(): void
    {
        $carrier = MixedPartySettings::surchargeProduct();
        $this->assertNotNull($carrier, 'el portador tiene que existir para que este caso tenga sujeto');
        $this->assertSame(0, $carrier->prices()->count(), 'el portador no tiene precio: es lo que lo hace peligroso aquí');

        $order = $this->soldOnSaturday([]);
        $item = $this->reload($order->id)->items->firstWhere('parent_item_id', null);

        // Una línea de portador colgando de la reserva, como la escribe `MixedPartySurcharge`.
        $item->children()->create([
            'order_id' => $order->id,       // ⚠️ la relación `children` no lo rellena: es NOT NULL
            'ticket_type_id' => $carrier->id, 'quantity' => 1, 'unit_price' => 700,
            'seats' => 0, 'free_quantity' => 0,
        ]);

        $plan = app(AddonDateReconciler::class)->plan($this->reload($order->id)->items->firstWhere('parent_item_id', null), Carbon::parse(self::TUESDAY));

        $this->assertSame([], $plan->changes, 'el portador no puede aparecer en el plan');
        $this->assertTrue($plan->isEmpty());

        // Y sobrevive a un movimiento real.
        $this->moveTo($order, self::TUESDAY, '17:00:00');
        $carrierLine = $this->reload($order->id)->items->firstWhere('ticket_type_id', $carrier->id);
        $this->assertNull($carrierLine->cancelled_at, 'el portador NO puede retirarse al mover la fecha');
        $this->assertSame(700, (int) $carrierLine->unit_price, 'ni cambiar de precio');
    }

    /** El CONTROL: un complemento de precio plano no se mueve. Es el criterio de no-op del despliegue. */
    public function test_a_flat_priced_child_is_untouched(): void
    {
        $order = $this->soldOnSaturday([['ticket_type_id' => $this->flat->id, 'qty' => 2]]);
        $before = (int) $this->reload($order->id)->items->firstWhere('ticket_type_id', $this->flat->id)->unit_price;

        $this->assertTrue($this->moveTo($order, self::TUESDAY, '17:00:00')->ok);

        $child = $this->reload($order->id)->items->firstWhere('ticket_type_id', $this->flat->id);
        $this->assertNull($child->cancelled_at, 'un complemento que sí se vende ese día no se retira');
        $this->assertSame($before, (int) $child->unit_price, 'ni cambia de precio: es no-op');
        $this->assertBookCloses($order->id, 'con un complemento de precio plano');
    }

    /**
     * La re-tarificación escribe SU HECHO, y sin él el libro deja de cerrar. Se comprueba con el
     * complemento plano al que se le cambia el precio del día normal: mismo producto, otro importe.
     */
    public function test_a_repriced_child_writes_its_fact_and_the_book_closes(): void
    {
        $this->price($this->flat, 'normal', 100);       // 3,00 € en finde · 1,00 € entre semana
        $order = $this->soldOnSaturday([['ticket_type_id' => $this->flat->id, 'qty' => 2]]);
        $totalBefore = OrderBook::forOrder($order)->totalCents;

        $this->assertTrue($this->moveTo($order, self::TUESDAY, '17:00:00')->ok);

        $after = $this->reload($order->id);
        $child = $after->items->firstWhere('ticket_type_id', $this->flat->id);
        $this->assertSame(100, (int) $child->unit_price, 'se re-tarifica al precio del día de la VISITA');
        $this->assertNull($child->cancelled_at, 'se re-tarifica, no se retira');

        // 2 × 3,00 € → 2 × 1,00 €: el complemento baja 4,00 €, y el padre 8,00 € (22 → 18).
        $book = $this->assertBookCloses($order->id, 'tras re-tarificar un complemento');
        $this->assertSame($totalBefore - 400 - 800, $book->totalCents);
        $this->assertSame('refund_at_park', $book->balance->kind);

        $this->assertTrue(
            $after->adjustments->contains(fn ($a): bool => ($a->context['product_id'] ?? null) === (int) $this->flat->id),
            'la re-tarificación tiene que dejar su hecho: sin él el libro no cerraría',
        );
    }

    /**
     * ⚠️ `free_quantity` sale del PIVOTE, no del día: re-tarificar un complemento INCLUIDO cambia su
     * `unit_price`, pero lo cobrado sigue siendo cero. Sin esta guarda, una re-tarificación podría
     * empezar a cobrar unidades que el pack regala.
     */
    public function test_repricing_never_touches_the_free_units(): void
    {
        $this->price($this->flat, 'normal', 100);
        $order = $this->soldOnSaturday([['ticket_type_id' => $this->flat->id, 'qty' => 2]]);

        $child = $this->reload($order->id)->items->firstWhere('ticket_type_id', $this->flat->id);
        $child->forceFill(['free_quantity' => 2])->save();     // todo gratis
        $freeBefore = (int) $child->free_quantity;

        $plan = app(AddonDateReconciler::class)
            ->plan($this->reload($order->id)->items->firstWhere('parent_item_id', null), Carbon::parse(self::TUESDAY));

        $change = collect($plan->changes)->firstWhere('productId', (int) $this->flat->id);
        $this->assertSame(AddonDateChange::REPRICE, $change->action);
        $this->assertSame(0, $change->chargedDeltaCents, 'con todas las unidades gratis, cambiar el precio no mueve dinero');

        $this->moveTo($order, self::TUESDAY, '17:00:00');
        $this->assertSame(
            $freeBefore,
            (int) $this->reload($order->id)->items->firstWhere('ticket_type_id', $this->flat->id)->free_quantity,
            'las unidades gratis no son del día: no se tocan',
        );
    }

    /** `plan()` es lectura pura: llamarlo no puede cambiar ni una fila. */
    public function test_plan_is_a_pure_read(): void
    {
        $order = $this->soldOnSaturday([['ticket_type_id' => $this->weekendOnly->id, 'qty' => 1]]);
        $item = $this->reload($order->id)->items->firstWhere('parent_item_id', null);
        $before = $this->reload($order->id)->items->map(fn ($i) => [$i->id, $i->unit_price, $i->cancelled_at, $i->slot_id])->toArray();

        $plan = app(AddonDateReconciler::class)->plan($item, Carbon::parse(self::TUESDAY));
        $this->assertCount(1, $plan->withdrawals(), 'el plan tiene que VER la retirada…');

        $after = $this->reload($order->id)->items->map(fn ($i) => [$i->id, $i->unit_price, $i->cancelled_at, $i->slot_id])->toArray();
        $this->assertSame($before, $after, '…pero no escribir nada al verla');
    }

    /**
     * **El OTRO camino público del editor.** `changeSlot()` mueve la franja sin re-tarificar el
     * padre, y hoy el despachador solo lo elige cuando la tarifa no cambia — pero es una entrada
     * pública del servicio y tiene que ser correcta por sí sola, no por quién la llama.
     *
     * ⚠️ Lo escribió el ARNÉS: la mutación «las hijas retiradas se mudan con el padre» no moría con
     * ningún caso, porque todos los demás entran por `edit()`. *Una rama sin caso propio está sin
     * red aunque el resto del servicio esté cubierto.*
     */
    public function test_change_slot_also_withdraws_a_child_the_new_day_does_not_sell(): void
    {
        // ⚠️ DOS hijas ocupantes —una que sobrevive y otra que no— y no una sola: con una sola no
        // quedan supervivientes, así que no hay franja de aterrizaje y darle `null` a la retirada
        // es indistinguible de no dársela. El defecto solo se ve cuando hay una franja que heredar.
        $order = $this->soldOnSaturday([
            ['ticket_type_id' => $this->weekendOnly->id, 'qty' => 1],      // el martes NO se vende
            ['ticket_type_id' => $this->alwaysOccupant->id, 'qty' => 1],   // el martes SÍ
        ]);
        $item = $this->reload($order->id)->items->firstWhere('parent_item_id', null);

        $outcome = app(OrderItemEditor::class)->changeSlot(
            order: $this->reload($order->id), item: $item,
            newDate: self::TUESDAY, newTime: '17:00:00',
            optimisticToken: (string) $item->updated_at->getTimestamp(),
            eventData: null, by: $this->operator,
        );
        $this->assertTrue($outcome->ok, 'el movimiento tiene que pasar');

        $after = $this->reload($order->id);
        $child = $after->items->firstWhere('ticket_type_id', $this->weekendOnly->id);
        $survivor = $after->items->firstWhere('ticket_type_id', $this->alwaysOccupant->id);

        $this->assertNotNull($survivor->slot_id, 'la superviviente aterriza, y con ella hay franja que heredar');
        $this->assertNotNull($child->cancelled_at, 'ese día no se vende: se retira');
        $this->assertNull($child->slot_id, 'y NO puede quedarse con la franja de la superviviente');
        $this->assertSame(0, (int) $child->seats, 'ni con plazas: estaría ocupando aforo que nadie usa');

        $this->assertBookCloses($order->id, 'tras un `changeSlot` con retirada');
    }

    /**
     * ⚠️⚠️ **Con DOS hijas ocupantes —una que sobrevive y otra que no— es donde se ve si las
     * retiradas se mudan con el padre.** Con una sola no se puede distinguir: al no quedar
     * supervivientes no hay franja de aterrizaje, así que asignarla es asignar `null` y el defecto
     * queda invisible. **Lo dijo el arnés**, que no lograba matar esa línea con ningún otro caso.
     */
    public function test_a_withdrawn_child_never_inherits_the_landing_slot_of_a_surviving_one(): void
    {
        $order = $this->soldOnSaturday([
            ['ticket_type_id' => $this->weekendOnly->id, 'qty' => 1],      // el martes NO se vende
            ['ticket_type_id' => $this->alwaysOccupant->id, 'qty' => 1],   // el martes SÍ
        ]);

        $this->assertTrue($this->moveTo($order, self::TUESDAY, '17:00:00')->ok);

        $after = $this->reload($order->id);
        $withdrawn = $after->items->firstWhere('ticket_type_id', $this->weekendOnly->id);
        $survivor = $after->items->firstWhere('ticket_type_id', $this->alwaysOccupant->id);

        $this->assertNotNull($survivor->slot_id, 'la superviviente sí aterriza…');
        $this->assertNotNull($withdrawn->cancelled_at, '…y la otra se retira');
        $this->assertNull(
            $withdrawn->slot_id,
            'una hija retirada NO puede heredar la franja de la superviviente: ocuparía plazas que nadie usa',
        );
        $this->assertSame(0, (int) $withdrawn->seats);
        $this->assertBookCloses($order->id, 'con una hija retirada y otra superviviente');
    }

    /**
     * **El AVISO al operador, antes de confirmar** (`[DECIDIDO owner, 2026-09-04]`). Retirar una
     * línea con devolución mientras se cambia una fecha es un efecto que el operador no pidió: lo ve
     * con el importe delante y puede elegir otro día.
     *
     * ⚠️⚠️ **Se renderiza el PARTIAL, no la página**, y no es comodidad: el contenido de un modal de
     * Filament **no aparece en el HTML del componente** — medido sobre esta misma pantalla, ni el
     * calendario ni su resumen de selección salen en `->html()`. Un `assertSee` ahí pasa en VACÍO,
     * que es la trampa de `#161`. Por eso el aviso vive en un partial suelto: para poder aseverarlo.
     */
    public function test_the_notice_tells_the_operator_what_the_move_will_do(): void
    {
        $order = $this->soldOnSaturday([['ticket_type_id' => $this->weekendOnly->id, 'qty' => 1]]);
        $item = $this->reload($order->id)->items->firstWhere('parent_item_id', null);

        $plan = app(AddonDateReconciler::class)->plan($item, Carbon::parse(self::TUESDAY));
        $this->assertCount(1, $plan->withdrawals(), 'el plan tiene que ver la retirada, o el aviso no tendría sujeto');

        $html = view('filament.orders.partials.addon-date-notice', [
            'plan' => $plan,
            'currency' => 'EUR',
        ])->render();

        $this->assertStringContainsString(__('admin.orders.manage_item.addon_date_heading'), $html);
        $this->assertStringContainsString('Hora extra', $html, 'el aviso tiene que NOMBRAR el extra que se retira');
        $this->assertStringContainsString('8,00', $html, 'y decir su importe: es lo que queda a devolver');
    }

    /**
     * La otra mitad: que el DATO llegue al calendario. El aviso más bonito no sirve si el modal no
     * le pasa el plan — y son dos fallos distintos, así que dos casos.
     *
     * ⚠️ Con la MISMA fecha no hay nada que avisar: un aviso ahí sería ruido en cada apertura.
     */
    public function test_the_calendar_carries_the_plan_only_when_the_day_changes(): void
    {
        $order = $this->soldOnSaturday([['ticket_type_id' => $this->weekendOnly->id, 'qty' => 1]]);
        $item = $this->reload($order->id)->items->firstWhere('parent_item_id', null);

        $page = new ViewOrder;
        $page->record = $this->reload($order->id);

        $ver = function (?string $fecha) use ($page, $item) {
            $m = new \ReflectionMethod($page, 'addonDatePlanFor');
            $m->setAccessible(true);

            return $m->invoke($page, $item, $fecha);
        };

        $this->assertNotNull($ver(self::TUESDAY), 'con día nuevo que no vende el extra, el calendario lleva plan');
        $this->assertNull($ver(self::SATURDAY), 'con el MISMO día no hay nada que avisar');
        $this->assertNull($ver(null), 'sin fecha elegida tampoco');
    }
}
