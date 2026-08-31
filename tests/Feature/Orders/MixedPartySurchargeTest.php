<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AgeFamilySealer;
use App\Domain\Booking\Services\MixedPartySettings;
use App\Domain\Booking\Services\MixedPartySurcharge;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Booking\Services\OrderLedger;
use App\Domain\Booking\Services\ReservationFinancials;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Notifications\MixedPartySurchargeChanged;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * El SUPLEMENTO de una fiesta MIXTA, que es dinero (`docs/specs/cumple-mixto.md` §12).
 *
 * `[DECIDIDO owner, 2026-08-29]` el importe sigue solo a las edades declaradas, sin aprobación: aquí
 * nada se cobra online, se cobra en el parque, y un cargo pendiente puede recalcularse mientras
 * nadie lo haya cobrado.
 *
 * ⚠️⚠️ **Lo que estos casos protegen no es «suma bien»: es que el desglose siga CUADRANDO.** La
 * forma «obvia» —un `extra_due` suelto— está medida y NO cobra: mueve dinero de «pagado online» a
 * «a cobrar en el parque» y deja el valor igual (§8.3). Por eso hay un caso que asevera los TRES
 * canales a la vez; sin él, un refactor podría volver a la forma que no cobra y todo lo demás
 * seguiría verde.
 *
 * ⚠️ Y protegen la otra mitad, que es la que un modelo automático rompe primero: **reconciliar, no
 * acumular**. Guardar dos veces no puede sumar dos suplementos.
 */
class MixedPartySurchargeTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private Slot $slot;

    private TicketType $kids;

    private TicketType $jump;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
        $this->zone = Zone::create(['slug' => 'cumples', 'name' => ['es' => 'Cumpleaños']]);
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(20)->toDateString(),
            'start_time' => '11:00:00', 'end_time' => '13:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);

        $this->kids = $this->pack('Cumpleaños Kids', 1, 6, 1800, $rate);
        $this->jump = $this->pack('Cumpleaños Jump', 7, 99, 2500, $rate);
    }

    private function pack(string $name, int $min, int $max, int $cents, RateType $rate): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1,
            'min_qty' => 1, 'max_qty' => 30, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
            'guest_fields' => [
                ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => true, 'label' => ['es' => 'Edad']],
            ],
            'guest_age_family' => 'cumple', 'guest_age_min' => $min, 'guest_age_max' => $max,
        ]);
        Price::create([
            'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
            'rate_type_id' => $rate->id, 'amount_cents' => $cents, 'currency' => 'EUR',
        ]);

        return $pack;
    }

    /** Una reserva PAGADA de N invitados del pack infantil, sin datos por-niño todavía. */
    private function reservation(int $guests = 3): OrderItem
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-MS'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'subtotal' => 1800 * $guests, 'tax' => 0, 'total' => 1800 * $guests, 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (200000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);

        $item = $order->items()->create([
            'ticket_type_id' => $this->kids->id, 'slot_id' => $this->slot->id,
            'quantity' => $guests, 'unit_price' => 1800, 'seats' => $guests,
        ]);

        // El SELLO que en producción pone `OrderCreator` al nacer (`specs/cumple-mixto.md` §21):
        // sin él la reserva no participa y ninguno de estos casos escribiría nada.
        app(AgeFamilySealer::class)->seal($item, $this->kids, $this->slot->date);

        return $item->fresh();
    }

    /**
     * Guarda el post-form por la MISMA puerta que usan la web y la API. No se llama al
     * reconciliador a mano: si un día dejara de estar enganchado ahí, estos casos tienen que caer.
     *
     * @param  list<int|null>  $ages
     */
    private function declareAges(OrderItem $item, array $ages): OrderItem
    {
        $rows = [];
        foreach ($ages as $i => $age) {
            $row = ['name' => 'Invitado '.($i + 1)];
            if ($age !== null) {
                $row['edad'] = (string) $age;
            }
            $rows[] = $row;
        }
        $item->submitGuestForm($rows, [], 'signed_link');

        return $item->fresh(['ticketType', 'slot', 'order']);
    }

    /**
     * Las líneas de suplemento VIVAS de una reserva. Se identifican por el ajuste que las marca y
     * no por su producto: sin portador configurado, `where('ticket_type_id', null)` compilaría a
     * `is null` y devolvería 0 por accidente, no por la razón que el caso quiere probar.
     *
     * @return Collection<int, OrderItem>
     */
    private function surchargeLines(OrderItem $item): Collection
    {
        $marked = OrderAdjustment::query()
            ->where('order_id', $item->order_id)
            ->get()
            ->filter(fn (OrderAdjustment $a): bool => is_array($a->context) && isset($a->context['mixed_party']))
            ->pluck('order_item_id')
            ->all();

        return $item->children()->whereNull('cancelled_at')->whereIn('id', $marked)->get();
    }

    private function financials(OrderItem $item): ReservationFinancials
    {
        $order = $item->order()->with(['items.ticketType', 'adjustments', 'payments'])->first();

        return ReservationFinancials::make($order, $order->items->firstWhere('id', $item->id));
    }

    /** Un empleado con lo justo para editar una reserva desde el panel. */
    private function staff(): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $staff->roles->first()->permissions()->sync(
            Permission::whereIn('name', ['orders.view', 'orders.edit_item'])->pluck('id'),
        );

        return $staff;
    }

    // ─── Que el dinero CUADRE, que es lo que la forma «obvia» rompía ─────────────

    public function test_a_guest_above_the_range_adds_value_and_is_due_at_the_park(): void
    {
        $item = $this->reservation(3);
        $before = $this->financials($item);

        $item = $this->declareAges($item, [4, 5, 8]);
        $after = $this->financials($item);

        // 25,00 − 18,00 = 7,00 € por el invitado de 8.
        $this->assertSame(700, $after->valor - $before->valor, 'la fiesta vale 7,00 € más');
        $this->assertSame(0, $after->pagadoOnline - $before->pagadoOnline, 'no se cobró nada nuevo online');
        $this->assertSame(700, $after->aCobrarPuerta - $before->aCobrarPuerta, 'se debe en el parque');
        // ⚠️ Y NO abre capacidad de reembolso: de esta línea no se cobró nunca nada online, así que
        // no puede aparecer como dinero que devolver. Sin esta aserción, una línea mal construida
        // inflaría el techo de reembolso del pedido sin que nada más se pusiera rojo.
        $this->assertSame(0, $after->pendienteReembolso - $before->pendienteReembolso);
    }

    public function test_the_surcharge_is_one_line_with_its_gate_charge(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        $lines = $this->surchargeLines($item);
        $this->assertCount(1, $lines);
        $this->assertSame(1, (int) $lines[0]->quantity);
        $this->assertSame(700, (int) $lines[0]->unit_price);

        // ⚠️ Sin plazas y sin franja: es lo que la mantiene FUERA de toda consulta de aforo, que
        // cruzan siempre por `slots` (§12).
        $this->assertSame(0, (int) $lines[0]->seats);
        $this->assertNull($lines[0]->slot_id);

        $adjustment = OrderAdjustment::where('order_item_id', $lines[0]->id)->firstOrFail();
        $this->assertSame(OrderAdjustment::TYPE_EXTRA_DUE, $adjustment->type);
        $this->assertSame(700, (int) $adjustment->amount_cents);
        $this->assertSame($this->jump->id, (int) $adjustment->context['mixed_party']['target_type_id']);
    }

    public function test_the_client_reads_a_line_that_explains_itself(): void
    {
        $item = $this->declareAges($this->reservation(4), [4, 8, 9, 5]);
        $order = $item->order()->with(['items.ticketType', 'adjustments'])->first();

        $labels = array_column($order->pendingAtGateLines(), 'label');

        // Sin su rama propia caería al respaldo, que dice el nombre del producto portador sin
        // explicar de dónde sale el cargo — el defecto que `#131` corrigió en la otra rama muda.
        //
        // ⚠️ `trans_choice` y con el NOMBRE del pack destino (`#247`): la frase concuerda en número
        // —decía «1 invitadoS»— y dice a qué régimen corresponden, que es lo que el owner pidió.
        $this->assertContains(
            trans_choice('tickets.gate_mixed_party_line_named', 2, ['count' => 2, 'target' => 'Cumpleaños Jump']),
            $labels,
        );
    }

    // ─── Reconciliar, no acumular ────────────────────────────────────────────────

    public function test_saving_twice_does_not_add_two_surcharges(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $item = $this->declareAges($item, [4, 5, 8]);

        $this->assertCount(1, $this->surchargeLines($item));
        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);
    }

    public function test_more_guests_above_the_range_update_the_same_line(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $item = $this->declareAges($item, [9, 5, 8]);

        $lines = $this->surchargeLines($item);
        $this->assertCount(1, $lines, 'se ACTUALIZA la línea, no se añade otra');
        $this->assertSame(2, (int) $lines[0]->quantity);
        $this->assertSame(1400, $this->financials($item)->aCobrarPuerta);
    }

    public function test_correcting_the_age_takes_the_surcharge_back_to_zero(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);

        $item = $this->declareAges($item, [4, 5, 6]);

        $this->assertCount(0, $this->surchargeLines($item), 'la línea se retira');
        $this->assertSame(0, $this->financials($item)->aCobrarPuerta);
        $this->assertSame(0, $this->financials($item)->valor - 3 * 1800, 'el valor vuelve al del pack');
    }

    // ─── El aviso al titular ─────────────────────────────────────────────────────

    public function test_the_customer_is_told_when_the_amount_changes(): void
    {
        $item = $this->reservation(3);
        $user = $item->order->user;

        $this->declareAges($item, [4, 5, 8]);
        Notification::assertSentTo($user, MixedPartySurchargeChanged::class);
    }

    public function test_a_save_that_does_not_move_the_amount_sends_nothing(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $user = $item->order->user;
        Notification::fake(); // descarta el correo de la primera vez

        // Solo cambia un nombre: el post-form está hecho para editarse durante días y no puede
        // mandar un correo por cada guardado.
        $item->submitGuestForm([
            ['name' => 'Otro nombre', 'edad' => '4'],
            ['name' => 'Invitado 2', 'edad' => '5'],
            ['name' => 'Invitado 3', 'edad' => '8'],
        ], [], 'signed_link');

        Notification::assertNothingSentTo($user);
    }

    // ─── El rastro, que es lo único que cierra el hueco de bajar la edad ─────────

    public function test_every_change_leaves_a_trace_without_pii(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->declareAges($item, [4, 5, 6]);

        $logs = AuditLog::where('action', 'orders.mixed_party_surcharge_synced')->get();

        $this->assertCount(2, $logs, 'la subida y la bajada, las dos');
        $this->assertSame([0, 700], $logs->pluck('payload.old_cents')->all());
        $this->assertSame([700, 0], $logs->pluck('payload.new_cents')->all());
        // `RGPD-02`: ni edades ni nombres de menores en el rastro.
        $this->assertStringNotContainsString('Invitado', json_encode($logs->pluck('payload')->all()));
    }

    // ─── Los casos en los que NO se escribe ──────────────────────────────────────

    public function test_without_a_carrier_product_nothing_is_written(): void
    {
        Setting::where('key', MixedPartySettings::SURCHARGE_PRODUCT_KEY)->delete();
        Setting::flushMemo();

        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        // No se inventa una línea sin producto — y el panel lo grita en rojo (§12): un cobro que
        // deja de aplicarse en silencio es el modo de fallo que había que evitar.
        $this->assertCount(0, $this->surchargeLines($item));
        $this->assertSame(0, $this->financials($item)->aCobrarPuerta);
    }

    public function test_two_packs_at_the_same_price_write_nothing(): void
    {
        $this->jump->prices()->update(['amount_cents' => 1800]);

        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        // Mixta sí (la etiqueta describe un hecho), cargo no: una línea de 0,00 € en el desglose
        // del cliente es ruido, no información.
        $this->assertCount(0, $this->surchargeLines($item));
    }

    public function test_a_cancelled_reservation_is_left_alone(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $operator = User::factory()->create();
        $item->markCancelled($operator);

        $before = $this->surchargeLines($item->fresh())->count();
        app(MixedPartySurcharge::class)->reconcile($item->fresh(), $operator, 'test');

        $this->assertSame($before, $this->surchargeLines($item->fresh())->count());
    }

    // ─── El HECHO cambia desde el panel, no solo desde el cliente ───────────────

    public function test_lowering_the_guest_count_from_the_panel_shrinks_the_surcharge(): void
    {
        // ⚠️ Conduce el EDITOR REAL y no el reconciliador: lo que este caso protege no es la
        // aritmética —eso ya lo cubren los de arriba— sino que la reconciliación siga ENGANCHADA a
        // `OrderItemEditor`. Llamando a `reconcile()` a mano, desenchufarla del panel no rompería
        // nada y la línea se quedaría cobrando por niños que ya no están en la fiesta.
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        // El ORDEN de las fichas es parte del caso: al bajar la cantidad, `sanitizeGuestData`
        // conserva las N PRIMERAS. Con los dos mayores al principio, bajar a 2 no quitaría a
        // ninguno y el caso no probaría nada.
        $item = $this->declareAges($this->reservation(4), [8, 4, 5, 9]);
        $this->assertSame(1400, $this->financials($item)->aCobrarPuerta, 'los de 8 y 9');

        $staff = $this->staff();

        $item = $item->fresh(['ticketType', 'slot']);
        $outcome = app(OrderItemEditor::class)->edit(
            $item->order, $item, '', '', false,
            (int) $item->ticket_type_id, 2, null,
            ['edits' => [], 'adds' => []],
            (string) $item->updated_at->getTimestamp(),
            $staff,
        );

        $this->assertFalse($outcome->isBlocked(), (string) $outcome->reason);
        // Quedan las fichas de 8 y 4: un solo invitado por encima del tramo.
        $this->assertSame(700, $this->financials($item->fresh())->aCobrarPuerta);
    }

    // ─── Lo escrito no sigue al catálogo (§12.2) — desde el 2026-08-31 por el SELLO (§21) ────
    //
    // Estos casos nacieron con el RECIBO de `#270` (dos hechos en el `context` del ajuste) y se
    // conservan tal cual con el sello: lo que protegen es la conducta —el cargo no se mueve porque
    // el parque retoque una tarifa—, no el mecanismo. El mecanismo lo prueban `AgeFamilySealTest`
    // (nacimiento, re-sello, sello caducado) y los casos de abajo que cambian de día y de pack.

    public function test_the_written_amount_survives_a_catalogue_price_change(): void
    {
        // ⚠️⚠️ **Este caso nació CIEGO y es la lección más cara de la revisión.** Aseveraba el
        // importe JUSTO DESPUÉS de subir el precio, sin volver a guardar — y ahí no había nada que
        // probar, porque nada dispara una reconciliación. El defecto vivía en el guardado
        // SIGUIENTE: el reconciliador re-derivaba del catálogo de hoy, así que el cliente
        // corrigiendo un NOMBRE se llevaba su cargo de 7,00 € a 12,00 €. La guarda pasaba en verde
        // con el fallo puesto y por eso llegó a `main`.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);

        // El parque sube el precio de JUMP: CONFIGURACIÓN, no hecho.
        $this->jump->prices()->update(['amount_cents' => 3000]);
        Notification::fake();
        AuditLog::query()->delete();

        // Y el cliente hace lo que ese formulario invita a hacer durante días: tocar un dato. Las
        // edades no cambian; el hecho es el mismo.
        $item = $this->declareAges($item, [4, 5, 8]);

        $this->assertSame(700, $this->financials($item)->aCobrarPuerta, 'lo escrito es lo que se le comunicó');
        $this->assertSame(700, app(MixedPartySurcharge::class)->written($item)['cents']);
        // Y si el importe no se mueve, tampoco hay nada que anunciarle ni que auditar.
        Notification::assertNothingSent();
        $this->assertSame(0, AuditLog::where('action', 'orders.mixed_party_surcharge_synced')->count());
    }

    public function test_a_guest_added_after_a_price_rise_pays_the_communicated_price(): void
    {
        // `[DECIDIDO owner, 2026-08-29]`, la única pregunta que el recibo abría: si el parque sube
        // la tarifa y DESPUÉS el cliente declara otro invitado mayor, ese invitado entra al precio
        // que se le comunicó. «14,00 €, no 24,00 €.»
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);

        $this->jump->prices()->update(['amount_cents' => 3000]); // derivado de hoy: 12,00 € por cabeza

        $item = $this->declareAges($item, [4, 9, 8]);

        // 2 × 7,00 € (el unitario comunicado), no 2 × 12,00 €. La CANTIDAD sigue al hecho; el
        // UNITARIO, a las condiciones selladas — que son las que se le comunicaron.
        $this->assertSame(1400, $this->financials($item)->aCobrarPuerta);
        $this->assertSame(2, (int) $this->surchargeLines($item)->first()->quantity);
        $this->assertSame(700, (int) $this->surchargeLines($item)->first()->unit_price);
    }

    public function test_moving_the_reservation_to_another_day_does_reprice(): void
    {
        // El CONTROL de que el sello no ha congelado de más: mover la reserva de día es un HECHO,
        // y la diferencia sale del catálogo de ESE día — el mismo criterio que `PAY-18` aplica al
        // precio de la propia reserva (con el sello: el editor lo RE-PRECIA para el día destino,
        // §21.4). Sin este caso, «no seguir al catálogo» podría implementarse congelándolo TODO y
        // nada se pondría rojo.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->jump->prices()->update(['amount_cents' => 3000]);

        $otherDay = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(27)->toDateString(),
            'start_time' => '11:00:00', 'end_time' => '13:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);

        $staff = $this->staff();
        $item = $item->fresh(['ticketType', 'slot', 'order']);
        $outcome = app(OrderItemEditor::class)->changeSlot(
            $item->order, $item, $otherDay->date->toDateString(), '11:00:00',
            (string) $item->updated_at->getTimestamp(), null, $staff,
        );
        // ⚠️ La hora va con SEGUNDOS: `resolveSlotForItem` compara `start_time` por igualdad exacta,
        // así que «11:00» no resuelve ninguna franja y el editor devuelve `invalid_slot_selection`.
        $this->assertFalse($outcome->isBlocked(), 'bloqueado: '.json_encode($outcome));

        // 30,00 − 18,00 = 12,00 €: el día nuevo se tarifica con el catálogo del día nuevo.
        $this->assertSame(1200, app(MixedPartySurcharge::class)->written($item->fresh())['cents']);
    }

    public function test_the_two_facts_behind_the_price_live_in_the_seal_not_in_the_adjustment(): void
    {
        // Entre el 29 y el 31 el `context` del ajuste llevaba un RECIBO (`booked_type_id` +
        // `priced_on`) que `unitFor()` comparaba con la fila para heredar el unitario escrito. El
        // sello de la reserva lo SUBSUME (§21.6): los dos hechos viven ahí, una sola vez, y el
        // contexto ya no los copia — dos fuentes de verdad para lo mismo era el defecto. Si alguien
        // volviera a escribirlos aquí, este caso lo dice.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        $mark = OrderAdjustment::where('order_id', $item->order_id)->get()
            ->firstWhere(fn (OrderAdjustment $a) => is_array($a->context) && isset($a->context['mixed_party']))
            ->context['mixed_party'];

        $this->assertArrayNotHasKey('booked_type_id', $mark);
        $this->assertArrayNotHasKey('priced_on', $mark);
        $this->assertSame(700, $mark['unit_cents'], 'lo que se le dijo al cliente sí se guarda');

        $seal = $item->ageFamilySeal();
        $this->assertNotNull($seal);
        $this->assertSame((int) $this->kids->id, $seal->bookedTypeId, 'bajo qué pack se reservó');
        $this->assertSame($this->slot->date->toDateString(), $seal->pricedOn, 'con el catálogo de qué día');
    }

    public function test_a_line_without_a_seal_is_left_alone(): void
    {
        // Una reserva anterior al sello (o cuyo sello se perdió) es SILENCIO: no se sabe con qué
        // condiciones se vendió, así que ni se le escribe un suplemento nuevo ni se le retira el
        // que tuviera. `D3` dice que en producción no existe ninguna; esta es la red por si acaso.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);

        OrderItem::whereKey($item->id)->update(['age_family_seal' => null]);
        Notification::fake();

        $item = $this->declareAges($item, [4, 5, 6]); // corregida a la baja: sin sello no se retira

        $this->assertSame(700, $this->financials($item)->aCobrarPuerta, 'sin sello, lo escrito se conserva');
        $this->assertCount(1, $this->surchargeLines($item));
        Notification::assertNothingSent();
    }

    // ─── La línea del suplemento no la gobierna el operador (§12.4) ──────────────

    public function test_the_operator_cannot_zero_the_surcharge_line_from_the_addons_tab(): void
    {
        // ⚠️⚠️ **El defecto, medido conduciendo el editor REAL**: el operador ponía la línea a 0 en
        // «Gestionar producto» → Complementos, el editor lo aceptaba, y la reconciliación
        // POST-COMMIT la volvía a crear EN LA MISMA PULSACIÓN — con dos correos al cliente que se
        // contradicen. Ahora la fila se descarta en el servidor (regla 12), así que el gesto ni se
        // acepta ni se deshace: simplemente no existe.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $line = $this->surchargeLines($item)->first();
        $this->assertNotNull($line);

        $staff = $this->staff();
        Notification::fake();

        $item = $item->fresh(['ticketType', 'slot', 'order']);
        $outcome = app(OrderItemEditor::class)->edit(
            $item->order, $item, '', '', false,
            (int) $item->ticket_type_id, (int) $item->quantity, null,
            ['edits' => [['child_id' => $line->id, 'quantity' => 0]], 'adds' => []],
            (string) $item->updated_at->getTimestamp(),
            $staff,
        );

        $this->assertFalse($outcome->isBlocked(), (string) $outcome->reason);
        $this->assertSame(700, $this->financials($item->fresh())->aCobrarPuerta, 'la línea sigue en pie');
        $this->assertSame($line->id, $this->surchargeLines($item->fresh())->first()?->id, 'y es LA MISMA, no una resucitada');
        // Y sin el correo que anunciaba el cargo recién perdonado.
        Notification::assertNotSentTo($item->order->user, MixedPartySurchargeChanged::class);
    }

    public function test_a_change_made_by_the_park_does_not_tell_the_customer_he_made_it(): void
    {
        // El texto decía «Has actualizado las edades de los invitados» también cuando la
        // reconciliación la disparaba el PANEL. Atribuirle al cliente algo que no hizo, en el correo
        // que le anuncia un cargo, es donde peor sienta.
        $item = $this->declareAges($this->reservation(4), [8, 4, 5, 9]);
        $staff = $this->staff();
        Notification::fake();

        // El camino REAL: el operador baja los invitados desde el panel, y eso mueve el importe.
        $item = $item->fresh(['ticketType', 'slot', 'order']);
        app(OrderItemEditor::class)->edit(
            $item->order, $item, '', '', false,
            (int) $item->ticket_type_id, 2, null,
            ['edits' => [], 'adds' => []],
            (string) $item->updated_at->getTimestamp(),
            $staff,
        );

        Notification::assertSentTo(
            $item->order->user,
            MixedPartySurchargeChanged::class,
            function (MixedPartySurchargeChanged $n) use ($item): bool {
                $mail = $n->toMail($item->order->user);

                return $n->byCustomer === false
                    && in_array(__('emails.mixed_party_surcharge.intro_by_park', [
                        'code' => $item->order->code, 'product' => 'Cumpleaños Kids',
                    ]), $mail->introLines, true);
            },
        );
    }

    // ─── Una AUSENCIA no es una CORRECCIÓN (§12.2.bis) ───────────────────────────
    //
    // Las cuatro formas de que falte un dato, y las cuatro borraban un cargo real. Se miden por
    // `aCobrarPuerta` y no solo por `written()`: lo que importa no es que quede la fila, es que el
    // cliente siga debiendo ese dinero en el desglose que él ve.

    public function test_blanking_the_ages_does_not_erase_the_charge(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);

        // El propio cliente vuelve al formulario y BORRA las edades. Es el peor de los cuatro
        // caminos: no necesita más que vaciar tres casillas, y lo dispara el interesado.
        $item = $this->declareAges($item, [null, null, null]);

        $this->assertSame(700, $this->financials($item)->aCobrarPuerta, 'borrar la edad no borra el cargo');
        $this->assertCount(1, $this->surchargeLines($item));
    }

    public function test_blanking_one_age_does_not_shrink_the_charge(): void
    {
        // ⚠️ La mitad que la retirada no cubre: aquí la línea NO desaparece, ENCOGE. Es además el
        // abuso realista —se vacía UNA casilla, no las cuatro— y por importe es la misma pérdida.
        $item = $this->declareAges($this->reservation(4), [8, 9, 4, 5]);
        $this->assertSame(1400, $this->financials($item)->aCobrarPuerta, 'los de 8 y 9');

        $item = $this->declareAges($item, [8, null, 4, 5]);

        $this->assertSame(1400, $this->financials($item)->aCobrarPuerta, 'vaciar una edad no rebaja el cargo');
        $this->assertSame(2, (int) $this->surchargeLines($item)->first()->quantity);
    }

    public function test_anonymising_the_customer_does_not_erase_the_charge(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        // `RGPD-01`: el derecho al olvido vacía `guest_data`. Borra los datos del cliente; la
        // contabilidad del parque sobrevive por diseño (la FK es RESTRICT, la factura sigue atada).
        OrderItem::whereKey($item->id)->update(['guest_data' => null]);

        app(MixedPartySurcharge::class)->reconcile($item->fresh(), $this->staff(), 'panel_item_edit');

        $this->assertSame(700, $this->financials($item->fresh())->aCobrarPuerta);
    }

    public function test_retiring_the_age_family_does_not_erase_the_charge(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        // Configuración, no hecho: alguien desconecta el pack de su familia. El veredicto pasa a
        // «no aplica» — que no es «no hay suplemento», es «ya no sé decirlo».
        $this->kids->forceFill(['guest_age_family' => null])->save();

        $item = $this->declareAges($item, [4, 5, 8]);

        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);
    }

    public function test_narrowing_a_band_does_not_erase_the_charge(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        // El tramo de JUMP se estrecha y el invitado de 8 se queda sin pack que lo cubra: un HUECO
        // DE CONFIGURACIÓN, que el veredicto ya sabe contar aparte (`outOfRange`).
        $this->jump->forceFill(['guest_age_min' => 9])->save();

        $item = $this->declareAges($item, [4, 5, 8]);

        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);
    }

    public function test_an_incomplete_verdict_is_silent_towards_the_customer(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        Notification::fake();
        AuditLog::query()->delete();

        $this->declareAges($item, [null, null, null]);

        // Abstenerse no es un cambio: ni correo que contradiga al anterior, ni fila de auditoría
        // que anuncie un movimiento que no ha ocurrido.
        Notification::assertNothingSent();
        $this->assertSame(0, AuditLog::where('action', 'orders.mixed_party_surcharge_synced')->count());
    }

    // ─── El dinero solo se mueve al guardar COMPLETO (`#285` §20.6, §22.3) ─────────

    public function test_a_partial_save_writes_nothing_in_either_direction(): void
    {
        // `[DECIDIDO owner, 2026-08-31]`: hasta el 31 un guardado parcial SUBÍA el cargo (`#268`,
        // «declarar es un dato nuevo»). Ya no: para cobrar de más basta una ficha, para devolver
        // hacen falta todas, y descontar con la foto a medias es la puerta del abuso — así que una
        // sola regla simétrica: con alguna edad en blanco no se escribe NADA.
        // Mutación: quitar la puerta de `reconcile()` → 7,00 € escritos con dos edades en blanco.
        $item = $this->declareAges($this->reservation(3), [8, null, null]);

        $this->assertSame(0, $this->financials($item)->aCobrarPuerta, 'con edades en blanco no se escribe');
        $this->assertCount(0, $this->surchargeLines($item));
        Notification::assertNothingSent();

        // El guardado COMPLETO es el que mueve el dinero — y mueve todo lo que corresponde.
        $item = $this->declareAges($item, [8, 9, 4]);
        $this->assertSame(1400, $this->financials($item)->aCobrarPuerta, 'dos invitados por encima del tramo');

        // Y otro completo, a la baja, también.
        $item = $this->declareAges($item, [8, 5, 4]);
        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);
    }

    public function test_an_age_without_a_product_does_not_freeze_the_money(): void
    {
        // El hueco C de `#284` (D6): medido antes, un invitado de 0 años —que ningún pack cubre—
        // dejaba el veredicto «incompleto» y desde `#268` eso impedía que el cargo BAJARA aunque el
        // cliente corrigiera las demás edades. Una edad sin producto es un estado CONOCIDO: esa
        // ficha no genera nada y las otras se tarifican, arriba y abajo.
        // Mutación: `derivationGoverns` sobre `isComplete()` → la bajada no se escribe, rojo.
        $item = $this->declareAges($this->reservation(3), [4, 0, 8]);
        $this->assertSame(700, $this->financials($item)->aCobrarPuerta, 'el de 8 sí; el de 0 no genera nada');

        $item = $this->declareAges($item, [4, 0, 6]);
        $this->assertSame(0, $this->financials($item)->aCobrarPuerta, 'el de 0 no congela la bajada');
        $this->assertCount(0, $this->surchargeLines($item));
    }

    public function test_a_panel_edit_with_blank_ages_leaves_the_surcharge_as_written(): void
    {
        // `[DECIDIDO owner]` (§22.8 Q1): UNA regla también para el panel. Con edades en blanco, el
        // operador baja invitados y lo escrito se queda como está —congelado y dicho en la ficha—
        // hasta que el cliente complete (o el operador lo haga por él, T3).
        $item = $this->declareAges($this->reservation(4), [8, 9, 4, 5]);
        $this->assertSame(1400, $this->financials($item)->aCobrarPuerta);

        $item = $this->declareAges($item, [8, 9, 4, null]); // el cliente deja una en blanco: congelado
        $this->assertSame(1400, $this->financials($item)->aCobrarPuerta);

        $staff = $this->staff();
        Notification::fake();
        $item = $item->fresh(['ticketType', 'slot', 'order']);
        $outcome = app(OrderItemEditor::class)->edit(
            $item->order, $item, '', '', false,
            (int) $item->ticket_type_id, 2, null,
            ['edits' => [], 'adds' => []],
            (string) $item->updated_at->getTimestamp(),
            $staff,
        );

        $this->assertFalse($outcome->isBlocked(), (string) $outcome->reason);
        // Quedan las fichas de 8 y 9 (dos por encima), pero NO se recalcula: sigue lo escrito.
        $this->assertSame(1400, $this->financials($item->fresh())->aCobrarPuerta, 'congelado también desde el panel');
        Notification::assertNotSentTo($item->order->user, MixedPartySurchargeChanged::class);
    }

    // ─── T4 · el −X €: el ESPEJO acotado a puerta (`specs/cumple-mixto.md` §20 y §24) ───────────

    /**
     * Una fiesta del pack CARO (Jump) con señal opcional: el escenario del descuento. El resto de
     * la señal es la COBERTURA de puerta de la que el espejo puede restar.
     *
     * @param  list<int|null>  $ages
     */
    private function jumpParty(array $ages, int $depositRemainderCents = 0): OrderItem
    {
        $guests = count($ages);
        $subtotal = 2500 * $guests;
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-MC'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'paid_at' => now(),
            'subtotal' => $subtotal - $depositRemainderCents, 'tax' => 0,
            'total' => $subtotal - $depositRemainderCents, 'currency' => 'EUR',
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (250000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $item = $order->items()->create([
            'ticket_type_id' => $this->jump->id, 'slot_id' => $this->slot->id,
            'quantity' => $guests, 'unit_price' => 2500, 'seats' => $guests,
        ]);
        if ($depositRemainderCents > 0) {
            OrderAdjustment::create([
                'order_id' => $order->id, 'order_item_id' => $item->id,
                'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
                'amount_cents' => $depositRemainderCents, 'currency' => 'EUR',
                'applied_by' => $order->user_id,
            ]);
        }
        app(AgeFamilySealer::class)->seal($item, $this->jump, $this->slot->date);

        return $this->declareAges($item->fresh(), $ages);
    }

    /** La línea de crédito VIVA de la reserva, o `null`. */
    private function creditLine(OrderItem $item): ?OrderItem
    {
        return $item->children()->whereNull('cancelled_at')->where('is_credit', true)->first();
    }

    /** @return array{cents:int, charge_cents:int, credit_cents:int, guests:int, lines:array, credit:?array} */
    private function writtenOf(OrderItem $item): array
    {
        return app(MixedPartySurcharge::class)->written($item->fresh(['ticketType', 'slot', 'order', 'children']));
    }

    /** PAY-16 por reserva + ningún canal negativo — el contrato que el espejo no puede romper. */
    private function assertChannelsClose(OrderItem $item, string $label): void
    {
        $rf = $this->financials($item->fresh());
        $this->assertSame(
            $rf->valor,
            $rf->pagadoOnline + $rf->pendienteOnline + $rf->aCobrarPuerta + $rf->cobradoPuerta + $rf->compensado,
            "$label · PAY-16 por reserva",
        );
        foreach (['pagadoOnline', 'pendienteOnline', 'aCobrarPuerta', 'cobradoPuerta', 'compensado'] as $canal) {
            $this->assertGreaterThanOrEqual(0, $rf->{$canal}, "$label · «{$canal}» negativo");
        }
    }

    public function test_a_cheaper_guest_writes_the_mirrored_discount(): void
    {
        // Jump 3 × 25,00 con señal (resto 50,00 en puerta); el de 4 años corresponde a Kids
        // (−7,00). El espejo: línea `is_credit` + `extra_due` gemelo NEGATIVO del mismo importe.
        $item = $this->jumpParty([8, 9, 4], depositRemainderCents: 5000);

        $credit = $this->creditLine($item);
        $this->assertNotNull($credit, 'el descuento se escribe de verdad (`[DECIDIDO owner]` D5)');
        $this->assertTrue((bool) $credit->is_credit);
        $this->assertSame(-700, $credit->chargedSubtotalCents(), 'el subtotal RESTA');
        $this->assertSame(0, (int) $credit->seats);
        $this->assertNull($credit->slot_id);

        $adjustment = OrderAdjustment::where('order_item_id', $credit->id)->firstOrFail();
        $this->assertSame(OrderAdjustment::TYPE_EXTRA_DUE, $adjustment->type);
        $this->assertSame(-700, (int) $adjustment->amount_cents);
        $this->assertTrue($adjustment->context['mixed_party']['credit']);

        $rf = $this->financials($item);
        $this->assertSame(4300, $rf->aCobrarPuerta, '50,00 − 7,00: en la puerta le pedirán 43,00');
        $this->assertSame(2500 * 3 - 5000, $rf->pagadoOnline, 'la señal no se toca');
        $this->assertSame(0, $rf->pendienteReembolso, 'un descuento de puerta no es deuda bancaria');
        $this->assertChannelsClose($item, 'descuento con señal');
        $this->assertSame(-700, $this->writtenOf($item)['cents'], 'el neto es el descuento');
    }

    public function test_the_discount_is_capped_by_the_gate_coverage(): void
    {
        // CASO C (§19.4): la puerta solo tiene 3,00 € → se escriben 3,00 de los 7,00 derivados y
        // los 4,00 restantes son el «a tu favor» — enseñado, no escrito (§20.4).
        $item = $this->jumpParty([8, 9, 4], depositRemainderCents: 300);

        $written = $this->writtenOf($item);
        $this->assertSame(300, $written['credit_cents'], 'el tope deja escribir solo la cobertura');
        $this->assertSame(400, app(MixedPartySurcharge::class)->inFavourCents($item->fresh(['ticketType', 'slot', 'order', 'children'])));
        $this->assertSame(0, $this->financials($item)->aCobrarPuerta, 'la puerta queda en cero exacto, no en negativo');
        $this->assertChannelsClose($item, 'tope de cobertura');
    }

    public function test_fully_online_writes_no_discount_and_shows_it_in_favour(): void
    {
        // CASO B (§20.2 fase 2): sin puerta que absorber NO se escribe nada — escribirlo dejaría
        // la puerta en negativo y el cinturón mordería. El importe entero queda «a tu favor».
        // Mutación obligatoria (§20.8): quitar el `min()` del tope pone esto en rojo.
        $item = $this->jumpParty([8, 9, 4]);

        $this->assertNull($this->creditLine($item), 'sin cobertura, el descuento no se escribe');
        $this->assertSame(0, $this->writtenOf($item)['credit_cents']);
        $this->assertSame(700, app(MixedPartySurcharge::class)->inFavourCents($item->fresh(['ticketType', 'slot', 'order', 'children'])));
        $this->assertSame(0, $this->financials($item)->aCobrarPuerta);
        $this->assertChannelsClose($item, '100 % online');
    }

    public function test_the_discount_follows_the_ages_back_up(): void
    {
        // La simetría del owner: si la edad vuelve a subir, el descuento se retira solo — el mismo
        // camino que el cargo, con el signo cambiado.
        $item = $this->jumpParty([8, 9, 4], depositRemainderCents: 5000);
        $this->assertSame(700, $this->writtenOf($item)['credit_cents']);

        $item = $this->declareAges($item, [8, 9, 10]);

        $this->assertNull($this->creditLine($item), 'ya nadie está por debajo: el descuento se retira');
        $this->assertSame(5000, $this->financials($item)->aCobrarPuerta, 'la puerta vuelve a su señal');
        $this->assertChannelsClose($item, 'edades de vuelta arriba');
    }

    public function test_a_blank_age_freezes_the_discount_too(): void
    {
        // §20.6: UNA regla para las dos direcciones. Con una edad en blanco, el descuento escrito
        // ni crece, ni encoge, ni se retira — y no se le anuncia nada a nadie.
        $item = $this->jumpParty([8, 9, 4], depositRemainderCents: 5000);
        $this->assertSame(700, $this->writtenOf($item)['credit_cents']);
        Notification::fake();
        AuditLog::query()->delete();

        $item = $this->declareAges($item, [8, null, 4]);

        $this->assertSame(700, $this->writtenOf($item)['credit_cents'], 'congelado');
        $this->assertSame(0, AuditLog::where('action', 'orders.mixed_party_surcharge_synced')->count());
        Notification::assertNothingSent();
    }

    public function test_silence_does_not_move_the_discount_in_either_direction(): void
    {
        // El silencio (sin sello) deja al CARGO crecer (`#268`), pero el CRÉDITO no se mueve en
        // NINGUNA dirección (§24.3·7): crearlo o crecerlo en silencio regala dinero; encogerlo lo
        // quita. Mutación: `applyCredit` sin la condición `derivationGoverns` — con el sello
        // quitado, el veredicto «no aplica» derivaría crédito 0 y RETIRARÍA la línea.
        $item = $this->jumpParty([8, 9, 4], depositRemainderCents: 5000);
        $this->assertSame(700, $this->writtenOf($item)['credit_cents']);

        $item->fresh()->forceFill(['age_family_seal' => null])->save(); // el silencio de «sin sello»
        $item = $this->declareAges($item, [8, 9, 4]); // el cliente toca el formulario

        $this->assertSame(700, $this->writtenOf($item)['credit_cents'], 'el silencio no lo toca');
        $this->assertChannelsClose($item, 'silencio');
    }

    public function test_charges_feed_the_coverage_of_the_discount(): void
    {
        // Familia de TRES: reservado el del medio (Jump 25,00), un invitado por ENCIMA (Teens
        // 30,00 → cargo +5,00) y otro por DEBAJO (Kids 18,00 → descuento −7,00). El cargo de la
        // misma pasada ES cobertura: sin señal, el tope deja escribir 5,00 de los 7,00 — y los
        // 2,00 restantes quedan «a tu favor». La puerta cierra en CERO exacto.
        // T6: PRIMERO se encoge Jump y DESPUÉS nace Teens — al revés había un solape transitorio
        // (Teens 12–99 sobre Jump 7–99) que el guardián de dominio prohíbe con razón (§26).
        $this->jump->forceFill(['guest_age_max' => 11])->save();
        $this->pack('Cumpleaños Teens', 12, 99, 3000, RateType::firstOrFail());

        $item = $this->jumpParty([8, 13, 4]);

        $written = $this->writtenOf($item);
        $this->assertSame(500, $written['charge_cents'], 'el de 13 sube a Teens');
        $this->assertSame(500, $written['credit_cents'], 'el de 4 descuenta hasta donde la puerta llega');
        $this->assertSame(200, app(MixedPartySurcharge::class)->inFavourCents($item->fresh(['ticketType', 'slot', 'order', 'children'])));
        $this->assertSame(0, $this->financials($item)->aCobrarPuerta);
        $this->assertChannelsClose($item, 'cargo y descuento a la vez');
    }

    public function test_the_credit_context_never_carries_changes(): void
    {
        // La trampa MEDIDA de §16.5.bis, ahora aseverada: con un `changes.quantity_change` en el
        // context, `itemOriginalOnlineCents` «reconstruye» un original y el eje de caja inventa un
        // «pendiente de devolución» fantasma. Mutación: meter `changes` → la segunda aserción cae.
        $item = $this->jumpParty([8, 9, 4], depositRemainderCents: 5000);

        $adjustment = OrderAdjustment::where('order_item_id', $this->creditLine($item)->id)->firstOrFail();
        $this->assertArrayNotHasKey('changes', $adjustment->context);
        $this->assertSame(0, $this->financials($item)->pendienteReembolso, 'el descuento no inventa deuda bancaria');
    }

    public function test_without_the_credit_carrier_nothing_is_written(): void
    {
        Setting::where('key', MixedPartySettings::CREDIT_PRODUCT_KEY)->delete();
        Setting::flushMemo();

        $item = $this->jumpParty([8, 9, 4], depositRemainderCents: 5000);

        $this->assertNull($this->creditLine($item), 'sin portador no se inventa una línea');
        $this->assertSame(5000, $this->financials($item)->aCobrarPuerta, 'y lo demás queda como estaba');
        $this->assertChannelsClose($item, 'sin portador del descuento');
    }

    public function test_the_email_speaks_with_the_discount_voice(): void
    {
        Notification::fake();
        $item = $this->jumpParty([8, 9, 4], depositRemainderCents: 5000);

        Notification::assertSentTo(
            $item->order->user,
            MixedPartySurchargeChanged::class,
            function (MixedPartySurchargeChanged $n) use ($item): bool {
                $mail = $n->toMail($item->order->user);

                // El neto pasó de 0 a −7,00: la voz es la del DESCUENTO, no un «suplemento −7,00».
                return $n->oldCents === 0 && $n->newCents === -700
                    && in_array(__('emails.mixed_party_surcharge.credit_added', ['amount' => '7,00']), $mail->introLines, true)
                    && in_array(__('emails.mixed_party_surcharge.where_discounted'), $mail->introLines, true)
                    && ! in_array(__('emails.mixed_party_surcharge.where_to_pay'), $mail->introLines, true);
            },
        );
    }

    public function test_the_ledger_publishes_the_in_favour_hint_with_null_as_its_condition(): void
    {
        // El patrón de `invoiced_hint` (`L6`): la frase compuesta por el dominio Y su condición.
        // Mutación (§24.7·G): publicar siempre `null` deja al cliente sin saber que tiene dinero
        // a su favor — y este caso en rojo.
        $with = $this->jumpParty([8, 9, 4]); // 100 % online: 7,00 € a favor, nada escrito
        $withLedger = OrderLedger::forReservation(
            $with->order()->with(['items.ticketType', 'items.slot', 'adjustments', 'payments.refunds'])->first(),
            $with->fresh(['ticketType', 'slot', 'order', 'children']),
        );
        $this->assertSame(
            __('tickets.ledger_in_favour', ['amount' => '7,00 €']),
            $withLedger->inFavourHint,
        );

        // Y el agregado del pedido dice lo mismo que su única reserva.
        $orderLedger = OrderLedger::forOrder(
            $with->order()->with(['items.ticketType', 'items.slot', 'items.children', 'adjustments', 'payments.refunds'])->first(),
        );
        $this->assertSame($withLedger->inFavourHint, $orderLedger->inFavourHint);

        // La condición: con el descuento ABSORBIDO entero por la puerta no hay exceso ni frase.
        $without = $this->jumpParty([8, 9, 4], depositRemainderCents: 5000);
        $this->assertNull(OrderLedger::forReservation(
            $without->order()->with(['items.ticketType', 'items.slot', 'adjustments', 'payments.refunds'])->first(),
            $without->fresh(['ticketType', 'slot', 'order', 'children']),
        )->inFavourHint);
    }
}
