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
use App\Domain\Booking\Services\MixedPartySettings;
use App\Domain\Booking\Services\MixedPartySurcharge;
use App\Domain\Booking\Services\OrderItemEditor;
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

        return $order->items()->create([
            'ticket_type_id' => $this->kids->id, 'slot_id' => $this->slot->id,
            'quantity' => $guests, 'unit_price' => 1800, 'seats' => $guests,
        ]);
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

    /**
     * El corte entre DOS PETICIONES, que en un test no existe y sin el cual estas guardas nacen
     * ciegas.
     *
     * ⚠️⚠️ `GuestAgeMixReader` va en `scoped` y memoiza la familia y los precios del día para no
     * consultarlos una vez por invitado. En producción eso vive lo que vive una petición; dentro de
     * un test es UN proceso, así que un caso que cambia el catálogo y vuelve a guardar seguiría
     * leyendo los valores de antes y **pasaría en verde con el defecto puesto**. Le pasó a la sonda
     * que encontró todo esto: el precio se borró de la base y el veredicto lo seguía viendo.
     */
    private function nextRequest(): void
    {
        $this->app->forgetScopedInstances();
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

    // ─── El RECIBO: lo escrito deja de seguir al catálogo (§12.2) ────────────────

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
        $this->nextRequest();
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

        $this->nextRequest();
        $item = $this->declareAges($item, [4, 9, 8]);

        // 2 × 7,00 € (el unitario comunicado), no 2 × 12,00 €. La CANTIDAD sigue al hecho; el
        // UNITARIO, al recibo.
        $this->assertSame(1400, $this->financials($item)->aCobrarPuerta);
        $this->assertSame(2, (int) $this->surchargeLines($item)->first()->quantity);
        $this->assertSame(700, (int) $this->surchargeLines($item)->first()->unit_price);
    }

    public function test_moving_the_reservation_to_another_day_does_reprice(): void
    {
        // El CONTROL de que el recibo no ha congelado de más: mover la reserva de día es un HECHO,
        // y la diferencia sale del catálogo de ESE día — el mismo criterio que `PAY-18` aplica al
        // precio de la propia reserva. Sin este caso, «no seguir al catálogo» podría implementarse
        // congelándolo TODO y nada se pondría rojo.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        $this->jump->prices()->update(['amount_cents' => 3000]);

        $otherDay = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(27)->toDateString(),
            'start_time' => '11:00:00', 'end_time' => '13:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);

        $this->nextRequest();
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

    public function test_the_receipt_records_the_two_facts_behind_the_price(): void
    {
        // La forma del recibo es contrato: `unitFor()` decide con estas dos claves si el unitario
        // escrito manda o si hay que volver al catálogo. Si alguien renombra una, el importe
        // dejaría de congelarse y NADA fallaría — se limitaría a seguir al catálogo otra vez.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        $mark = OrderAdjustment::where('order_id', $item->order_id)->get()
            ->firstWhere(fn (OrderAdjustment $a) => is_array($a->context) && isset($a->context['mixed_party']))
            ->context['mixed_party'];

        $this->assertSame((int) $this->kids->id, $mark['booked_type_id'], 'bajo qué pack se reservó');
        $this->assertSame($this->slot->date->toDateString(), $mark['priced_on'], 'con el catálogo de qué día');
        $this->assertSame(700, $mark['unit_cents']);
    }

    public function test_a_line_written_before_the_receipt_is_sealed_without_moving_the_amount(): void
    {
        // Las líneas escritas antes de esta tanda no llevan recibo. Se heredan igual —preferir el
        // catálogo de hoy para ellas movería justo el dinero que esto protege— y se sellan en su
        // primera pasada, sin tocar un céntimo.
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        $adjustment = OrderAdjustment::where('order_id', $item->order_id)->get()
            ->firstWhere(fn (OrderAdjustment $a) => is_array($a->context) && isset($a->context['mixed_party']));
        $legacy = $adjustment->context;
        unset($legacy['mixed_party']['booked_type_id'], $legacy['mixed_party']['priced_on']);
        $adjustment->forceFill(['context' => $legacy])->save();

        $this->jump->prices()->update(['amount_cents' => 3000]);

        $this->nextRequest();
        $item = $this->declareAges($item, [4, 5, 8]);

        $this->assertSame(700, app(MixedPartySurcharge::class)->written($item)['cents'], 'sin recibo también se hereda');
        $this->assertSame(
            (int) $this->kids->id,
            $adjustment->fresh()->context['mixed_party']['booked_type_id'],
            'y queda sellada para la próxima',
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
        $this->nextRequest();
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

        $this->nextRequest();
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

        $this->nextRequest();
        app(MixedPartySurcharge::class)->reconcile($item->fresh(), $this->staff(), 'panel_item_edit');

        $this->assertSame(700, $this->financials($item->fresh())->aCobrarPuerta);
    }

    public function test_retiring_the_age_family_does_not_erase_the_charge(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        // Configuración, no hecho: alguien desconecta el pack de su familia. El veredicto pasa a
        // «no aplica» — que no es «no hay suplemento», es «ya no sé decirlo».
        $this->kids->forceFill(['guest_age_family' => null])->save();

        $this->nextRequest();
        $item = $this->declareAges($item, [4, 5, 8]);

        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);
    }

    public function test_narrowing_a_band_does_not_erase_the_charge(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);

        // El tramo de JUMP se estrecha y el invitado de 8 se queda sin pack que lo cubra: un HUECO
        // DE CONFIGURACIÓN, que el veredicto ya sabe contar aparte (`outOfRange`).
        $this->jump->forceFill(['guest_age_min' => 9])->save();

        $this->nextRequest();
        $item = $this->declareAges($item, [4, 5, 8]);

        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);
    }

    public function test_an_incomplete_verdict_is_silent_towards_the_customer(): void
    {
        $item = $this->declareAges($this->reservation(3), [4, 5, 8]);
        Notification::fake();
        AuditLog::query()->delete();

        $this->nextRequest();
        $this->declareAges($item, [null, null, null]);

        // Abstenerse no es un cambio: ni correo que contradiga al anterior, ni fila de auditoría
        // que anuncie un movimiento que no ha ocurrido.
        Notification::assertNothingSent();
        $this->assertSame(0, AuditLog::where('action', 'orders.mixed_party_surcharge_synced')->count());
    }

    public function test_a_partial_verdict_can_still_grow(): void
    {
        // La asimetría es deliberada: DECLARAR la edad que faltaba es un dato nuevo y legítimo, así
        // que el importe sube mientras el cliente rellena. Solo se le niega el camino de vuelta.
        $item = $this->declareAges($this->reservation(3), [8, null, null]);
        $this->assertSame(700, $this->financials($item)->aCobrarPuerta);

        $this->nextRequest();
        $item = $this->declareAges($item, [8, 9, null]);

        $this->assertSame(1400, $this->financials($item)->aCobrarPuerta, 'dos invitados por encima del tramo');
    }
}
