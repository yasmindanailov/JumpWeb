<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AgeFamilySealer;
use App\Domain\Booking\Services\GuestAgeMix;
use App\Domain\Booking\Services\GuestAgeMixReader;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * El VEREDICTO de fiesta MIXTA (`docs/specs/cumple-mixto.md` §9·4): a qué producto de la familia le
 * toca cada invitado por su edad, y cuánto costaría regularizarlo.
 *
 * ⚠️ Lo que estos casos protegen NO es «sabe sumar»: es que el veredicto **se DERIVA y no se sella**
 * —el post-form es editable hasta el evento y la etiqueta tiene que ir y venir sola (§4)— y que las
 * tres respuestas que NO son un importe (sin edad · fuera de rango · sin precio ese día) se
 * distinguen entre sí y de «no aplica». Un veredicto que devolviera 0 en los cuatro casos pasaría
 * una suite ingenua y mentiría en el panel.
 *
 * ▶ Desde el 2026-08-31 deriva del SELLO de la reserva y no del catálogo (§21): el helper sella
 * cada reserva como lo hace `OrderCreator`, y los tres «no aplica» —sin sello, sello caducado, sello
 * sin familia— tienen su caso porque el reconciliador los trata distinto.
 */
class GuestAgeMixTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private Slot $slot;

    private RateType $rate;

    /** Fecha de la franja: día fijo (no `today()`) — el fixture tiene calendario (`TESTING.md` §2). */
    private const SLOT_DATE = '2026-06-10';

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'cumples', 'name' => ['es' => 'Cumpleaños'], 'accent' => 'cumpleanos',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => self::SLOT_DATE,
            'start_time' => '11:00:00', 'end_time' => '12:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);
        $this->rate = RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'is_special' => false, 'weekdays' => null, 'priority' => 0, 'is_active' => true,
        ]);
    }

    /**
     * Un pack de la familia. `$priceCents` a `null` = producto SIN precio ese día, que es el caso
     * que deja el suplemento sin tarificar.
     */
    private function pack(string $name, ?string $family, ?int $min, ?int $max, ?int $priceCents, bool $withAgeField = true): TicketType
    {
        $guestFields = [
            ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
        ];
        if ($withAgeField) {
            $guestFields[] = ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => true, 'label' => ['es' => 'Edad']];
        }

        $pack = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1,
            'min_qty' => 1, 'max_qty' => 30,
            'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
            'guest_fields' => $guestFields,
            'guest_age_family' => $family,
            'guest_age_min' => $min,
            'guest_age_max' => $max,
        ]);

        if ($priceCents !== null) {
            Price::create([
                'priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id,
                'rate_type_id' => $this->rate->id, 'amount_cents' => $priceCents, 'currency' => 'EUR',
            ]);
        }

        return $pack;
    }

    /**
     * @param  list<int|null>  $ages  una edad por invitado (`null` = ficha sin edad declarada)
     * @param  bool  $sealed  con el SELLO que `OrderCreator` pone al nacer (§21); `false` reproduce
     *                        una línea anterior al sello, que para el veredicto es silencio
     */
    private function reservation(TicketType $pack, array $ages, bool $withSlot = true, bool $sealed = true): OrderItem
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => Order::STATUS_PAID,
        ]);

        $guestData = [];
        foreach ($ages as $i => $age) {
            $guestData[] = array_filter([
                'name' => 'Invitado '.($i + 1),
                'edad' => $age === null ? null : (string) $age,
            ], fn ($v): bool => $v !== null);
        }

        $item = $order->items()->create([
            'ticket_type_id' => $pack->id,
            'slot_id' => $withSlot ? $this->slot->id : null,
            'quantity' => count($ages), 'unit_price' => 1800, 'seats' => count($ages),
            'guest_data' => $guestData,
        ]);

        if ($sealed) {
            // Se sella para el día de la franja aunque la reserva no la tenga: así el caso «sin
            // franja» prueba lo que hoy significa — un sello que no casa con la fila.
            app(AgeFamilySealer::class)->seal($item, $pack, $this->slot->date);
        }

        return $item->fresh();
    }

    private function read(OrderItem $item): GuestAgeMix
    {
        return app(GuestAgeMixReader::class)->for($item->fresh(['ticketType', 'slot']));
    }

    // ─── El caso del encargo ─────────────────────────────────────────────────────

    public function test_a_guest_above_the_range_makes_the_party_mixed_and_prices_the_difference(): void
    {
        $kids = $this->pack('Cumpleaños Kids', 'cumple', 1, 6, 1800);
        $this->pack('Cumpleaños Jump', 'cumple', 7, 99, 2500);

        $mix = $this->read($this->reservation($kids, [4, 5, 8]));

        $this->assertTrue($mix->applies);
        $this->assertTrue($mix->mixed);
        $this->assertSame(1, $mix->upgradedGuests(), 'solo el de 8 cambia de régimen');
        // 25,00 − 18,00 = 7,00 € por cabeza, y UNA cabeza: el encargo del owner es por persona,
        // no por fiesta (spec §8.2 — cambiar el producto cobraría la diferencia por los tres).
        $this->assertSame(700, $mix->surchargeCents);
        $this->assertTrue($mix->isComplete());
    }

    public function test_the_border_is_inclusive_on_both_ends(): void
    {
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 99, 2500);

        // ⚠️ «No es mixta» NO basta como aserción, y lo demostró una mutación: con el borde
        // superior exclusivo (`>=` en vez de `>`) el niño de 6 deja de pertenecer a KIDS… y a
        // ningún otro pack, así que sale «fuera de rango» y `mixed` sigue siendo `false`. Dos
        // mundos distintos bajo la misma aserción. Lo que hay que afirmar es el hecho POSITIVO:
        // el de 6 está CUBIERTO por el pack que ya tiene.
        $dentro = $this->read($this->reservation($kids, [6, 6]));
        $this->assertFalse($dentro->mixed);
        $this->assertSame(0, $dentro->outOfRange, 'el de 6 pertenece a KIDS, no se queda sin pack');
        $this->assertTrue($dentro->isComplete());

        $fuera = $this->read($this->reservation($kids, [6, 7]));
        $this->assertTrue($fuera->mixed);
        $this->assertSame(1, $fuera->upgradedGuests(), 'solo el de 7 cambia de régimen');
        $this->assertSame(0, $fuera->outOfRange);
    }

    public function test_every_guest_above_the_range_counts_once(): void
    {
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 99, 2500);

        $mix = $this->read($this->reservation($kids, [8, 9, 3, 12]));

        $this->assertSame(3, $mix->upgradedGuests());
        $this->assertSame(2100, $mix->surchargeCents, '3 × 7,00 €');
    }

    // ─── Las respuestas que NO son un importe ────────────────────────────────────

    public function test_a_pack_without_family_does_not_apply_at_all(): void
    {
        $solo = $this->pack('Excursión de colegio', null, null, null, 1800);

        $mix = $this->read($this->reservation($solo, [4, 15]));

        $this->assertFalse($mix->applies, 'sin familia declarada, esto está apagado entero');
        $this->assertFalse($mix->mixed);
    }

    public function test_a_pack_without_an_age_field_does_not_apply(): void
    {
        // La familia y el tramo están, pero su post-form no pide la edad: no hay dato del que
        // derivar nada, y fingir un veredicto sería inventárselo.
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800, withAgeField: false);
        $this->pack('Jump', 'cumple', 7, 99, 2500);

        $this->assertFalse($this->read($this->reservation($kids, [8]))->applies);
    }

    public function test_guests_without_a_declared_age_are_counted_not_assumed(): void
    {
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 99, 2500);

        $mix = $this->read($this->reservation($kids, [4, null, null]));

        $this->assertFalse($mix->mixed, 'sin edad no se supone que sea mayor');
        $this->assertSame(2, $mix->withoutAge);
        $this->assertFalse($mix->isComplete(), 'el veredicto es PARCIAL y quien lo pinta debe saberlo');
    }

    public function test_an_age_no_product_covers_is_reported_as_a_configuration_hole(): void
    {
        $kids = $this->pack('Kids', 'cumple', 4, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 12, 2500);

        // Nadie ha declarado quién atiende a un niño de 2 ni a uno de 15.
        $mix = $this->read($this->reservation($kids, [5, 2, 15]));

        $this->assertSame(2, $mix->outOfRange);
        $this->assertFalse($mix->mixed);
        $this->assertFalse($mix->isComplete());
    }

    public function test_without_a_price_for_that_day_the_party_is_still_mixed_but_unpriced(): void
    {
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 99, null); // sin precio ese día

        $mix = $this->read($this->reservation($kids, [8]));

        $this->assertTrue($mix->mixed, 'la etiqueta describe un HECHO, no un cobro');
        $this->assertNull($mix->surchargeCents);
        $this->assertFalse($mix->isPriceable());
    }

    public function test_two_products_at_the_same_price_are_mixed_with_a_zero_surcharge(): void
    {
        // El caso de la instalación de desarrollo (spec §8.8): los dos packs valen lo mismo.
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 99, 1800);

        $mix = $this->read($this->reservation($kids, [8]));

        $this->assertTrue($mix->mixed);
        $this->assertSame(0, $mix->surchargeCents, 'mixta sin cargo es un estado VÁLIDO');
        $this->assertTrue($mix->isPriceable());
    }

    public function test_moving_down_a_regime_never_credits_money(): void
    {
        // Un niño de 3 en una fiesta JUMP: la fiesta es mixta de verdad, pero el pack que le toca
        // es MÁS BARATO. Devolver dinero sería un movimiento de caja que nadie ha pedido.
        $this->pack('Kids', 'cumple', 1, 6, 1800);
        $jump = $this->pack('Jump', 'cumple', 7, 99, 2500);

        $mix = $this->read($this->reservation($jump, [9, 3]));

        $this->assertTrue($mix->mixed);
        $this->assertSame(0, $mix->surchargeCents);
    }

    // ─── Las TRES formas de «no aplica», que no son intercambiables (§21.5) ──────

    public function test_a_reservation_without_a_seal_is_silent(): void
    {
        // Una línea anterior al sello (o una entrada, o un complemento): no se sabe con qué
        // condiciones se vendió, así que el veredicto no afirma nada — ni etiqueta, ni importe — y
        // deja claro que NO es un sello diciendo «sin condiciones».
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 99, 2500);

        $mix = $this->read($this->reservation($kids, [8], sealed: false));

        $this->assertFalse($mix->applies);
        $this->assertFalse($mix->mixed);
        $this->assertFalse($mix->sealed, 'un silencio no es una afirmación');
        $this->assertFalse($mix->staleSeal);
    }

    public function test_a_seal_that_does_not_match_the_row_is_silent_and_flagged(): void
    {
        // El sello se hizo para el día de la franja y la fila no tiene franja: los dos hechos del
        // recibo (pack + día) ya no describen la fila. Derivar de él pondría precio a condiciones
        // que no son las suyas, así que calla — y lo DICE, para que la ficha lo enseñe en rojo.
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 99, 2500);

        $mix = $this->read($this->reservation($kids, [8], withSlot: false));

        $this->assertFalse($mix->applies);
        $this->assertTrue($mix->staleSeal);
        $this->assertFalse($mix->sealed);
    }

    public function test_a_seal_without_a_family_is_an_affirmation(): void
    {
        // Se vendió SIN condiciones por edad: «no aplica» sale del sello y por eso AFIRMA — es lo
        // que permite retirar un suplemento cuando el operador cambia el pack a uno sin familia.
        $solo = $this->pack('Excursión de colegio', null, null, null, 1800);

        $mix = $this->read($this->reservation($solo, [4, 15]));

        $this->assertFalse($mix->applies);
        $this->assertTrue($mix->sealed, 'con sello, «no aplica» es una afirmación');
        $this->assertFalse($mix->staleSeal);
    }

    // ─── El veredicto es DERIVADO: va y viene con el post-form ───────────────────

    public function test_correcting_an_age_removes_the_verdict_with_nothing_to_undo(): void
    {
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 99, 2500);
        $item = $this->reservation($kids, [8]);

        $this->assertTrue($this->read($item)->mixed);

        // El cliente corrige la edad desde el post-form, por la MISMA puerta que usa de verdad.
        $item->submitGuestForm([['name' => 'Invitado 1', 'edad' => '6']], [], 'signed_link');

        $mix = $this->read($item);
        $this->assertFalse($mix->mixed, 'la etiqueta desaparece sola: no hay sello que deshacer');
        $this->assertSame(0, $mix->upgradedGuests());
    }

    // ─── El saneo de la edad, de la que sale un cobro ────────────────────────────

    public function test_an_impossible_age_is_stored_as_missing_not_as_a_number(): void
    {
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 99, 2500);
        $item = $this->reservation($kids, [4]);

        $item->submitGuestForm([['name' => 'Invitado 1', 'edad' => '999']], [], 'signed_link');

        $this->assertArrayNotHasKey('edad', $item->fresh()->guestData()[0], 'fuera de cota = no respondido');
        $mix = $this->read($item);
        $this->assertSame(1, $mix->withoutAge);
        $this->assertFalse($mix->mixed, 'un valor imposible no puede disparar un cargo');
    }

    public function test_an_age_written_with_noise_is_normalised(): void
    {
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $this->pack('Jump', 'cumple', 7, 99, 2500);
        $item = $this->reservation($kids, [4]);

        $item->submitGuestForm([['name' => 'Invitado 1', 'edad' => ' 08 años ']], [], 'signed_link');

        // «08» y «8» tienen que ser la misma edad: la comparación con el tramo es numérica.
        $this->assertSame('8', $item->fresh()->guestData()[0]['edad']);
        $this->assertTrue($this->read($item)->mixed);
    }

    // ─── La familia es una CLASIFICACIÓN, no una oferta ──────────────────────────

    public function test_a_sibling_taken_off_sale_still_classifies(): void
    {
        $kids = $this->pack('Kids', 'cumple', 1, 6, 1800);
        $jump = $this->pack('Jump', 'cumple', 7, 99, 2500);
        $jump->forceFill(['is_sellable' => false, 'is_active' => false])->save();

        $mix = $this->read($this->reservation($kids, [8]));

        // Si al despublicar el hermano el niño de 8 pasara a «fuera de rango», el veredicto
        // cambiaría bajo los pies de reservas ya vendidas.
        $this->assertTrue($mix->mixed);
        $this->assertSame(0, $mix->outOfRange);
        $this->assertSame(700, $mix->surchargeCents);
    }

    public function test_a_different_family_is_not_a_sibling(): void
    {
        $kids = $this->pack('Cumple Kids', 'cumple', 1, 6, 1800);
        $this->pack('Campamento juvenil', 'campamento', 7, 99, 2500);

        $mix = $this->read($this->reservation($kids, [8]));

        $this->assertFalse($mix->mixed);
        $this->assertSame(1, $mix->outOfRange, 'nadie de SU familia cubre esa edad');
    }
}
