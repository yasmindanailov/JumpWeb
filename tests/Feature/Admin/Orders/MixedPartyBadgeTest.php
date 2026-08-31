<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AgeFamilySealer;
use App\Domain\Booking\Services\MixedPartySettings;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Lo que el OPERADOR ve de una fiesta MIXTA en la ficha del pedido
 * (`docs/specs/cumple-mixto.md` §9·5-7).
 *
 * ⚠️ Este caso existe porque el veredicto en verde **no demuestra que se pinte**: entre
 * `GuestAgeMixReader` y la pantalla hay una plantilla con una clave de idioma, un `@if` y una
 * variable, y las tres se rompen en silencio. Conduce la página REAL por HTTP, como la ve el
 * empleado.
 *
 * ⚠️ Y comprueba las TRES respuestas que el owner tiene que poder distinguir: hay suplemento ·
 * no hay mezcla · el veredicto todavía es parcial porque faltan edades. Un test que solo mirase
 * la primera dejaría pasar una pantalla que grita «MIXTA» sobre datos a medias.
 */
class MixedPartyBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $kids;

    private TicketType $jump;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
        $this->zone = Zone::create(['slug' => 'cumples', 'name' => ['es' => 'Cumpleaños']]);

        $this->kids = $this->pack('Cumpleaños Kids', 1, 6, 1800, $rate);
        $this->jump = $this->pack('Cumpleaños Jump', 7, 99, 2500, $rate);
    }

    private function pack(string $name, int $min, int $max, int $cents, RateType $rate, string $family = 'cumple'): TicketType
    {
        // T6: la familia entra como PARÁMETRO — la versión anterior creaba los packs tri-familia
        // como `cumple` (Mini 0–2 PISABA a Kids 1–6) y los re-familiaba después, un solape
        // transitorio que el guardián de dominio prohíbe con razón. El fixture se legaliza,
        // no se excepciona el guardián.
        $pack = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1,
            'min_qty' => 1, 'max_qty' => 30,
            'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
            'guest_fields' => [
                ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => true, 'label' => ['es' => 'Edad']],
            ],
            'guest_age_family' => $family, 'guest_age_min' => $min, 'guest_age_max' => $max,
        ]);

        Price::create([
            'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
            'rate_type_id' => $rate->id, 'amount_cents' => $cents, 'currency' => 'EUR',
        ]);

        return $pack;
    }

    /** @param  list<int|null>  $ages */
    private function paidPartyWith(array $ages, ?TicketType $booked = null): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-MX'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1800 * count($ages), 'tax' => 0, 'total' => 1800 * count($ages),
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $order->total, 'currency' => 'EUR', 'provider' => 'redsys',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
            'gateway_order' => str_pad((string) (100000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => '11:00:00', 'end_time' => '11:59:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => ($booked ?? $this->kids)->id, 'slot_id' => $slot->id,
            'quantity' => count($ages), 'seats' => count($ages), 'unit_price' => 1800,
        ]);
        // El sello que `OrderCreator` pone al nacer (`specs/cumple-mixto.md` §21).
        app(AgeFamilySealer::class)->seal($item, $booked ?? $this->kids, $slot->date);

        // ⚠️ Por la puerta REAL y no escribiendo `guest_data` a mano: es el guardado del post-form
        // el que reconcilia el suplemento, y lo que la pantalla enseña es lo ESCRITO. Un fixture que
        // se saltara ese paso probaría una pantalla que en producción no existe.
        $rows = [];
        foreach ($ages as $i => $age) {
            $row = ['name' => 'Invitado '.($i + 1)];
            if ($age !== null) {
                $row['edad'] = (string) $age;
            }
            $rows[] = $row;
        }
        $item->submitGuestForm($rows, [], 'signed_link');

        return $order;
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $u->roles->first()->permissions()->sync(Permission::whereIn('name', ['orders.view'])->pluck('id'));

        return $u;
    }

    public function test_the_panel_shows_the_badge_and_the_proposed_surcharge(): void
    {
        $order = $this->paidPartyWith([4, 5, 8]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('tickets.mixed_party_badge'))
            ->assertSee(__('admin.orders.mixed_party.title'))
            // 25,00 − 18,00 = 7,00 € por UN invitado. El importe es lo que el operador cobra en
            // caja: si la plantilla lo perdiera, la pantalla seguiría pareciendo correcta.
            ->assertSee(__('admin.orders.mixed_party.applied', ['amount' => '7,00 €']))
            // Y no hay desfase: lo escrito y lo derivado coinciden justo después de guardar.
            ->assertDontSee(__('admin.orders.mixed_party.missing_carrier'));
    }

    public function test_a_party_within_the_range_shows_nothing(): void
    {
        $order = $this->paidPartyWith([4, 5, 6]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertDontSee(__('tickets.mixed_party_badge'))
            ->assertDontSee(__('admin.orders.mixed_party.title'));
    }

    public function test_missing_ages_are_announced_as_a_partial_verdict(): void
    {
        $order = $this->paidPartyWith([4, null, null]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            // Sin edad no se supone que nadie sea mayor: no hay etiqueta…
            ->assertDontSee(__('tickets.mixed_party_badge'))
            // …pero el operador tiene que saber que el veredicto aún puede cambiar.
            ->assertSee(__('admin.orders.mixed_party.without_age', ['count' => 2]));
    }

    public function test_the_panel_shows_the_cheaper_direction_as_money_in_favour(): void
    {
        // El pack CARO con dos invitados que corresponden al barato, pagado 100 % online: desde la
        // T4 (`[DECIDIDO owner]` D5, §20/§24) el descuento es real, pero sin puerta que lo absorba
        // NO se escribe — el operador ve el «a favor del cliente», que es lo que liquida en mano.
        $order = $this->paidPartyWith([9, 4, 3], $this->jump);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('tickets.mixed_party_badge'))
            // 2 × (25,00 − 18,00) = 14,00 €.
            ->assertSee(__('admin.orders.mixed_party.in_favour', ['amount' => '14,00 €']))
            // ⚠️ Y NUNCA como cargo: si esto apareciera, el operador cobraría lo que no se debe.
            ->assertDontSee(__('admin.orders.mixed_party.applied', ['amount' => '14,00 €']))
            // T5 (§25.6·2, guarda H): la dirección barata ya no pinta su «0,00 € por invitado» —
            // pegado al «14,00 € a favor» era la contradicción del T0 con el signo cambiado, y su
            // historia la cuentan el descuento y el «a tu favor». Mutación: quitar el filtro de
            // `visibleUpgrades()` (o pintar `upgrades` entero) vuelve a enseñarlo.
            ->assertDontSee('0,00 € por invitado');
    }

    /**
     * T5 (§25.6·2, guarda H — la otra mitad): el MISMO precio (`diff = 0`) SÍ conserva su línea —
     * «es mixta y no cuesta nada» es información, no ruido (docblock de `GuestAgeMix`). Es lo que
     * separa el filtro correcto («fuera la dirección barata») del filtro perezoso («fuera todo
     * cero»), y la mutación que lo distingue: filtrar por `unit_cents === 0` pone esto en rojo.
     */
    public function test_a_same_price_mix_keeps_its_zero_line_as_information(): void
    {
        Price::where('priceable_type', $this->jump->getMorphClass())
            ->where('priceable_id', $this->jump->id)
            ->update(['amount_cents' => 1800]);
        $order = $this->paidPartyWith([4, 5, 8]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.title'))
            ->assertSee(__('admin.orders.mixed_party.conditions_line', [
                'count' => 1, 'name' => 'Cumpleaños Jump', 'unit' => '0,00 €',
            ]))
            ->assertDontSee(__('admin.orders.mixed_party.applied', ['amount' => '0,00 €']));
    }

    /**
     * T5 (§25.6·1, guarda I — el hallazgo del T0): lo ESCRITO primero y las condiciones DESPUÉS,
     * con su etiqueta de origen. El owner tuvo que preguntar por qué «2 × Jump · 9,00 €» convivía
     * con «Suplemento aplicado: 8,00 €»: la ficha es la única superficie que mezcla derivado y
     * escrito, y sin etiqueta no se explicaba sola. Mutación: devolver el `@foreach` a su sitio
     * de antes del título invierte las posiciones.
     */
    public function test_the_written_amount_is_printed_before_the_labelled_conditions(): void
    {
        $order = $this->paidPartyWith([4, 5, 8]);

        $response = $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.conditions_line', [
                'count' => 1, 'name' => 'Cumpleaños Jump', 'unit' => '7,00 €',
            ]));

        $html = $response->getContent();
        $applied = mb_strpos($html, 'Suplemento aplicado:');
        $conditions = mb_strpos($html, 'Según las condiciones de esta reserva:');

        $this->assertNotFalse($applied);
        $this->assertNotFalse($conditions);
        $this->assertLessThan($conditions, $applied, 'lo escrito va ANTES que la línea de condiciones');
    }

    /**
     * T5 (§25.6·3, guarda J): los avisos van por LADOS y los lados son INDEPENDIENTES — el
     * portador del descuento puede faltar Y el cargo estar en desfase A LA VEZ. Hasta hoy la
     * cadena `@elseif` única se tragaba el desfase (medido en §25.2: `missing_credit_carrier` iba
     * antes que `drift` y hablan de lados distintos). Mutación: restaurar la cadena única.
     */
    public function test_a_charge_drift_is_still_told_when_the_credit_carrier_is_missing(): void
    {
        // Familia propia de TRES tramos para tener las dos direcciones a la vez: un invitado de 9
        // sube (jump-tri, +7,00), uno de 1 baja (mini, «a favor» 3,00) y el de 4 queda en rango.
        // T6: nacen ya en su familia — crearlos como `cumple` y re-familiarlos creaba un solape
        // transitorio (Mini 0–2 sobre Kids 1–6) que el guardián de dominio prohíbe.
        $rate = RateType::firstOrFail();
        $mini = $this->pack('Cumpleaños Mini', 0, 2, 1500, $rate, family: 'cumple-tri');
        $kidsTri = $this->pack('Cumpleaños Kids Tri', 3, 6, 1800, $rate, family: 'cumple-tri');
        $jumpTri = $this->pack('Cumpleaños Jump Tri', 7, 99, 2500, $rate, family: 'cumple-tri');

        // El portador del DESCUENTO falta ANTES del reconcile — el orden importa y lo enseñó la
        // primera versión de esta guarda: con el portador vivo, EL PROPIO CARGO de la pasada cuenta
        // como cobertura (T4 §24.3) y el descuento se escribe (credit 3,00, inFavour 0), así que
        // `missing_credit_carrier` jamás podía ser verdad. ⚠️ Y `Setting::value` memoiza la tabla
        // entera en un estático: sin `flushMemo()` el reconcile leería la foto de antes del borrado.
        Setting::where('key', MixedPartySettings::CREDIT_PRODUCT_KEY)->delete();
        Setting::flushMemo();

        $order = $this->paidPartyWith([9, 1, 4], $kidsTri);
        $item = OrderItem::where('order_id', $order->id)->whereNull('parent_item_id')->firstOrFail();

        // El DESFASE del cargo se fabrica por fuera (como el sello caducado de al lado): doblar la
        // cantidad de la línea escrita deja escrito 14,00 contra un derivado de 7,00.
        OrderItem::where('parent_item_id', $item->id)->where('is_credit', false)->update(['quantity' => 2]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.drift', ['written' => '14,00 €', 'derived' => '7,00 €']))
            ->assertSee(__('admin.orders.mixed_party.missing_credit_carrier'));
    }

    /**
     * T5 (§25.6·4, guarda K): `frozen` habla en NEUTRO — desde la T4 lo congelado puede ser un
     * descuento, y «Suplemento congelado» sobre un descuento afirmaba lo contrario de lo que
     * había. La clave la comparten la ficha y la pestaña del modal, así que la regla se asevera
     * sobre el TEXTO (las dos formas del plural).
     */
    public function test_the_frozen_line_speaks_neutrally_about_the_amount(): void
    {
        foreach ([1, 2] as $count) {
            $text = trans_choice('admin.orders.mixed_party.frozen', $count, ['count' => $count]);
            $this->assertStringNotContainsString('Suplemento congelado', $text);
            $this->assertStringContainsString('Importe por edades congelado', $text);
        }
    }

    /**
     * T5 (§25.6·7, guarda M — cazado por el OJO del owner en mitad de la tanda): las líneas ↳ del
     * desglose de puerta llevan signo CONSCIENTE. El `+` clavado era de cuando toda línea era un
     * cargo; con el descuento de la T4 pintaba «+-14,00 €». Mutación: restaurar el `+` fijo en
     * cualquiera de los dos partials.
     */
    public function test_the_gate_breakdown_prints_the_credit_line_with_a_clean_sign(): void
    {
        $order = $this->paidPartyWith([9, 4, 3], $this->jump);
        $item = OrderItem::where('order_id', $order->id)->whereNull('parent_item_id')->firstOrFail();

        // Cobertura de puerta REAL (un cargo de edición) y re-guardado completo: el reconciliador
        // escribe el descuento —min(14,00, cobertura)— con su `extra_due` gemelo NEGATIVO, que es
        // la línea ↳ que hasta hoy salía «+-14,00 €».
        $order->applyExtraDue($item, 5000, $this->staff(), 'ajuste de sonda');
        $item->refresh()->submitGuestForm([
            ['name' => 'Invitado 1', 'edad' => '9'],
            ['name' => 'Invitado 2', 'edad' => '4'],
            ['name' => 'Invitado 3', 'edad' => '3'],
        ], [], 'signed_link');

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertDontSee('+-14,00')
            ->assertDontSee('+-4,00')
            ->assertSee('−14,00 €');
    }

    public function test_a_frozen_surcharge_is_announced_with_how_many_ages_are_missing(): void
    {
        // El dinero solo se mueve con TODAS las edades (`#285` §20.6): el operador tiene que ver que
        // el cargo está congelado y cuántas faltan, no un «el veredicto puede cambiar» genérico.
        $order = $this->paidPartyWith([4, 5, 8]);
        $item = OrderItem::where('order_id', $order->id)->whereNull('parent_item_id')->firstOrFail();
        $item->submitGuestForm([
            ['name' => 'Invitado 1', 'edad' => '4'], ['name' => 'Invitado 2'], ['name' => 'Invitado 3', 'edad' => '8'],
        ], [], 'signed_link');

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.applied', ['amount' => '7,00 €']))
            ->assertSee(trans_choice('admin.orders.mixed_party.frozen', 1, ['count' => 1]))
            ->assertSee('falta 1 edad por declarar')
            ->assertDontSee(__('admin.orders.mixed_party.without_age', ['count' => 1]));
    }

    public function test_an_age_without_a_product_is_explained_as_a_park_matter(): void
    {
        // D6: no es «revisa los tramos en el catálogo» (con el sello el catálogo no es la causa):
        // es una edad sin producto en las condiciones de ESTA reserva, que se resuelve en el parque.
        // Y el resto de la fiesta se tarifica: el de 8 paga, el de 0 no genera nada.
        $order = $this->paidPartyWith([4, 0, 8]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.out_of_range', ['count' => 1]))
            ->assertSee(__('admin.orders.mixed_party.applied', ['amount' => '7,00 €']));
    }

    public function test_retiring_the_family_in_the_catalogue_changes_nothing_on_screen(): void
    {
        // Hasta el 2026-08-31 este caso probaba el HUÉRFANO por catálogo: retirar la familia dejaba
        // el cargo sin veredicto. Con el SELLO (`specs/cumple-mixto.md` §21, `DECISIONES #284` D2)
        // el catálogo ya no manda sobre una fiesta vendida: el bloque sigue diciendo lo mismo que
        // antes del cambio, con su veredicto y su importe — y sin ningún aviso, porque no hay nada
        // que avisar.
        $order = $this->paidPartyWith([4, 5, 8]);
        $this->kids->forceFill(['guest_age_family' => null])->save();

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.title'))
            ->assertSee(__('admin.orders.mixed_party.applied', ['amount' => '7,00 €']))
            ->assertDontSee(__('admin.orders.mixed_party.orphaned'))
            ->assertDontSee(__('admin.orders.mixed_party.stale_seal'));
    }

    public function test_a_charge_on_a_reservation_without_a_seal_is_still_explained(): void
    {
        // ⚠️⚠️ El bloque entero colgaba de `$mix->applies`, así que un cargo sin veredicto dejaba al
        // operador con un importe en «a cobrar en el parque» y CERO explicación en pantalla. Hoy
        // eso solo lo produce una reserva SIN sello (anterior a él, `D3`): el dinero escrito manda
        // sobre si esto se pinta, y se le dice de dónde sale y por qué no hay veredicto.
        $order = $this->paidPartyWith([4, 5, 8]);
        OrderItem::where('order_id', $order->id)->whereNull('parent_item_id')->update(['age_family_seal' => null]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.applied', ['amount' => '7,00 €']))
            ->assertSee(__('admin.orders.mixed_party.orphaned'))
            // Y sin fingir un veredicto que no se puede derivar: nada de «hay invitados de otro
            // tramo», porque sin sello el sistema no sabe decirlo.
            ->assertDontSee(__('admin.orders.mixed_party.title'));
    }

    public function test_a_seal_that_does_not_match_the_row_is_shouted_in_red(): void
    {
        // Ningún camino del producto lo produce (el editor re-sella en la misma transacción): si
        // aparece, alguien movió la fila por fuera. Es un dato inconsistente, no un estado del
        // negocio, y el operador tiene que verlo ANTES de cobrar nada.
        $order = $this->paidPartyWith([4, 5, 8]);
        OrderItem::where('order_id', $order->id)->whereNull('parent_item_id')->update(['ticket_type_id' => $this->jump->id]);

        $this->actingAs($this->staff())
            ->get('/admin/orders/'.$order->code)
            ->assertOk()
            ->assertSee(__('admin.orders.mixed_party.stale_seal'))
            ->assertDontSee(__('admin.orders.mixed_party.orphaned'))
            ->assertDontSee(__('admin.orders.mixed_party.title'));
    }
}
