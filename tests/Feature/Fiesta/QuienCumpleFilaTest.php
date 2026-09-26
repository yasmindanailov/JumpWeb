<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Contracts\ReservationPlacesTaken;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GuestCardOrder;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\DeclaresDependents;
use Tests\TestCase;

/**
 * **QUIEN CUMPLE, LA PRIMERA FILA DE LA LISTA** (F3a de `specs/fiesta-sistema-nuevo.md` §4.8, `[DECIDIDO owner]` `#747`).
 *
 * Una reserva de 10 es quien cumple + 9 invitados, si el pack lo dice (`honoree_counts`) y la reserva lo selló al nacer
 * (`honoree_row`). Su ficha es la 0 de `guest_data`, como la de cualquier invitado —así la ven la hoja de sala, la puerta,
 * el panel, la API y la línea de edades—, y lo que aquí se vigila es lo que la hace distinta:
 *
 *  1. ❗ **El sello nace con la reserva, por la puerta real de la compra**, y las de antes no cambian (`#747`·3).
 *  2. ❗ **El espejo con la invitación, en los dos sentidos**: su nombre y su edad «se escriben una vez».
 *  3. ❗❗ **La ficha está CLAVADA**: al bajar el número no se mueve ni se pierde, y ninguna respuesta cae en ella.
 *  4. ❗❗ **Ocupa su plaza**: el suelo del número y las plazas de firma la cuentan (aforo).
 *  5. La API lo dice, y la lista la pinta primero, con «Personalizar» como espejo de su fila.
 */
class QuienCumpleFilaTest extends TestCase
{
    use DeclaresDependents;
    use RefreshDatabase;

    // ── 1 · El sello ─────────────────────────────────────────────────────────

    /**
     * Por `OrderCreator`, que es la puerta de la web, la API y el alta manual del panel: si el sello dejara de ponerse
     * ahí, la lista seguiría pidiendo una ficha de más sin que nada avisara. Y lo que se sella no cambia con el pack.
     */
    public function test_a_pack_that_counts_the_honoree_seals_its_reservations_at_booking(): void
    {
        Notification::fake();
        [$zone, $slot, $rate] = $this->bookable();
        $cuenta = $this->bookablePack($zone, $rate, 'Pack Kids', true);
        $noCuenta = $this->bookablePack($zone, $rate, 'Pack Jump', false);

        $order = app(OrderCreator::class)->createPendingOrder(User::factory()->create(), [
            ['ticket_type_id' => $cuenta->id, 'date' => $slot->date->toDateString(), 'time' => '11:00:00', 'qty' => 8],
            ['ticket_type_id' => $noCuenta->id, 'date' => $slot->date->toDateString(), 'time' => '11:00:00', 'qty' => 8],
        ]);
        $items = $order->items()->whereNull('parent_item_id')->get()->keyBy('ticket_type_id');

        $this->assertTrue((bool) $items[$cuenta->id]->honoree_row, 'el pack que cuenta a quien cumple no selló la reserva');
        $this->assertFalse((bool) $items[$noCuenta->id]->honoree_row, 'un pack que no lo cuenta selló la reserva');

        // El sello es de la RESERVA: apagar el ajuste del pack mañana no cambia lo que significa su número.
        $cuenta->forceFill(['honoree_counts' => false])->save();
        $this->assertTrue($items[$cuenta->id]->fresh()?->hasHonoreeRow() ?? false);
    }

    /** El ajuste pide un pack con columna de nombre, como la invitación: la ficha de quien cumple es una ficha más. */
    public function test_counting_the_honoree_needs_a_pack_with_a_name_column(): void
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $pack = $this->pack($zone, true);
        $this->assertTrue($pack->countsHonoree());

        $sinNombre = TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Sin fichas'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'position' => 9, 'honoree_counts' => true,
        ]);
        $this->assertFalse($sinNombre->countsHonoree(), 'sin columna de nombre no hay ficha de quien cumple');

        $entrada = TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY, 'name' => ['es' => 'Entrada'],
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 10,
            'honoree_counts' => true, 'guest_fields' => $pack->guest_fields,
        ]);
        $this->assertFalse($entrada->countsHonoree(), 'una entrada no es una fiesta');
    }

    // ── 2 · El espejo con la invitación ──────────────────────────────────────

    public function test_personalizing_the_honoree_writes_his_row_and_nothing_else(): void
    {
        [$reservation, $invitation] = $this->party();
        $reservation->submitGuestForm([['name' => 'Lucía', 'age' => '7'], ['name' => 'Mateo', 'age' => '6', 'allergy' => 'Huevo']], null, 'account');

        app(PartyInvitations::class)->personalize($invitation->fresh() ?? $invitation, ['honoree_name' => 'Lucía Pérez', 'honoree_age' => 8]);

        $rows = $reservation->fresh()?->guestData() ?? [];
        $this->assertSame(['name' => 'Lucía Pérez', 'age' => '8'], $rows[0], 'la ficha 0 no recogió el nombre y la edad de la invitación');
        $this->assertSame(['name' => 'Mateo', 'age' => '6', 'allergy' => 'Huevo'], $rows[1], 'el espejo tocó a un invitado');

        // CONTROL: cambiar el TEMA no toca la reserva (su `updated_at` es el testigo de los extras).
        $antes = $reservation->fresh()?->updated_at?->toIso8601String();
        $this->travel(1)->minutes();
        app(PartyInvitations::class)->personalize($invitation->fresh() ?? $invitation, ['theme' => PartyInvitation::THEMES[1] ?? PartyInvitation::THEME_DEFAULT]);
        $this->assertSame($antes, $reservation->fresh()?->updated_at?->toIso8601String(), 'cambiar el tema movió la reserva');
    }

    /** Las reservas de antes (`honoree_row = false`) no se enteran: su ficha 0 es de un invitado. */
    public function test_a_reservation_without_the_seal_keeps_its_first_card_for_a_guest(): void
    {
        [$reservation, $invitation] = $this->party(honoreeRow: false);
        $reservation->submitGuestForm([['name' => 'Mateo', 'age' => '6']], null, 'account');

        app(PartyInvitations::class)->personalize($invitation->fresh() ?? $invitation, ['honoree_name' => 'Lucía Pérez', 'honoree_age' => 8]);

        $this->assertSame(['name' => 'Mateo', 'age' => '6'], $reservation->fresh()?->guestData()[0] ?? null);
        $this->assertSame(0, app(ReservationPlacesTaken::class)->takenIn((int) $reservation->getKey()));
    }

    public function test_saving_the_list_writes_the_honoree_to_the_public_card(): void
    {
        [$reservation, $invitation] = $this->party();

        $reservation->submitGuestForm([['name' => 'Vera', 'age' => '9', 'allergy' => 'Gluten'], ['name' => 'Mateo']], null, 'account');
        $inv = $invitation->fresh();
        $this->assertSame('Vera', $inv?->honoree_name, 'la tarjeta no dice el nombre que se escribió en su fila');
        $this->assertSame(9, $inv?->honoree_age);

        // Un nombre con un enlace NO se publica (`PublicFreeText`), y una ficha sin edad no borra la de la tarjeta.
        $reservation->fresh()?->submitGuestForm([['name' => 'Vera www.regalo.example'], ['name' => 'Mateo']], null, 'account');
        $inv = $invitation->fresh();
        $this->assertSame('Vera', $inv?->honoree_name, 'la tarjeta publicó un enlace');
        $this->assertSame(9, $inv?->honoree_age, 'una ficha sin edad borró la de la tarjeta');
    }

    // ── 3 · La ficha clavada ─────────────────────────────────────────────────

    /**
     * ❗❗ Al bajar el número los confirmados van primero (`#718`); sin clavarla, un invitado confirmado adelantaría a quien
     * cumple y el recorte se la llevaría a ella.
     */
    public function test_the_honoree_row_stays_first_when_the_cards_are_compacted(): void
    {
        [$reservation, $invitation] = $this->party();
        $this->reply($reservation, $invitation, 'Hugo Ruiz', true);

        $ordenadas = app(GuestCardOrder::class)->confirmedFirst($reservation, [
            ['name' => 'Lucía'], ['name' => 'Mateo'], ['name' => 'Hugo Ruiz'], [],
        ]);

        $this->assertSame(['Lucía', 'Hugo Ruiz', 'Mateo'], array_values(array_filter(array_column($ordenadas, 'name'))), 'quien cumple dejó de ser la primera');
    }

    /** Ninguna respuesta cae en su ficha, ni la marca un «no» con su nombre, ni está «sin contestar». */
    public function test_no_reply_lands_on_or_marks_the_honoree_row(): void
    {
        [$reservation, $invitation] = $this->party();
        // La ficha 0 aún SIN nombre (la primera pantalla no se ha hecho): es justo cuando parecería un hueco libre.
        $reservation->submitGuestForm([[], ['name' => 'Mateo']], null, 'account');
        $reservation = $reservation->fresh() ?? $reservation;
        $this->reply($reservation, $invitation, 'Pablo Gil', true);

        $propuesta = collect(app(PartyInvitations::class)->proposalsFor($reservation))->firstWhere('child_name', 'Pablo Gil');
        $this->assertNotNull($propuesta);
        $this->assertSame(2, $propuesta['slot_index'], 'una respuesta cayó en la ficha de quien cumple');

        // Con su nombre ya escrito: no se le recuerda que conteste. ⚠️ ANTES de que exista un «no» con su nombre: con él,
        // Lucía ya contaría como contestada y la aserción pasaría por casualidad (lo cazó el arnés).
        $reservation->submitGuestForm([['name' => 'Lucía'], ['name' => 'Mateo']], null, 'account');
        $reservation = $reservation->fresh() ?? $reservation;
        $this->assertSame(['Mateo'], app(PartyInvitations::class)->awaitingNamesIn($reservation), 'se le recuerda a quien cumple que conteste');

        // Y un «no» con el mismo nombre no marca su ficha.
        $this->reply($reservation, $invitation, 'Lucía', false);
        $no = collect(app(PartyInvitations::class)->declinedPendingIn($reservation))->firstWhere('child_name', 'Lucía');
        $this->assertIsArray($no, 'el «no» no se ve');
        $this->assertNull($no['slot_index'], 'un «no» marcó la ficha de quien cumple');
    }

    // ── 4 · Su plaza ─────────────────────────────────────────────────────────

    /**
     * ❗❗ AFORO: quien cumple ocupa una plaza con dueño. Sin ella, una reserva de 10 con 9 «sí» dejaría bajar a 9 y el
     * recorte se llevaría a un confirmado, y dejaría firmar a un décimo invitado.
     */
    public function test_the_honoree_takes_a_place_in_the_floor(): void
    {
        [$reservation, $invitation] = $this->party(quantity: 10);
        foreach (range(1, 9) as $i) {
            $this->reply($reservation, $invitation, 'Invitado '.$i, true);
        }

        $this->assertSame(10, app(ReservationPlacesTaken::class)->takenIn((int) $reservation->getKey()), 'la plaza de quien cumple no cuenta');
        $this->assertSame(10, app(GuestCountPolicy::class)->floorFor($reservation->fresh() ?? $reservation), 'el suelo deja bajar por debajo de quien cumple y los confirmados');
    }

    // ── 5 · La API y la lista ────────────────────────────────────────────────

    public function test_the_api_says_whether_the_first_card_is_the_honoree(): void
    {
        [$reservation] = $this->party();
        [$antigua] = $this->party(honoreeRow: false);

        $this->getJson($reservation->guestFormApiUrls()['show'])->assertOk()->assertJsonPath('honoree_row', true);
        $this->getJson($antigua->guestFormApiUrls()['show'])->assertOk()->assertJsonPath('honoree_row', false);
    }

    /**
     * La lista abre con su fila («Es su cumple»), con el nombre y la edad de la invitación hasta el primer guardado, y
     * «Personalizar» los enseña como ESPEJO: sin `name`, para que no haya dos campos para el mismo dato en el formulario.
     */
    public function test_the_list_opens_with_the_honoree_and_personalize_mirrors_his_row(): void
    {
        [$reservation, , $host] = $this->party();

        $pagina = $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk();
        $html = (string) $pagina->getContent();
        // Cuenta en el número, no entre las respuestas (el `cuentas()` del diseño).
        $m = $pagina->viewData('m');
        $this->assertSame(['confirmados' => 0, 'en_lista' => 1], ['confirmados' => $m['cuentas']['confirmados'], 'en_lista' => $m['cuentas']['en_lista']]);

        $this->assertStringContainsString('data-filas-cumple', $html, 'la lista no abre con quien cumple');
        $this->assertStringContainsString('Es su cumple', $html);
        $this->assertMatchesRegularExpression('#data-filas-cumple.*?name="guests\[0\]\[name\]"[^>]*value="Lucía"#s', $html, 'su fila no trae el nombre de la invitación');
        $this->assertMatchesRegularExpression('#id="pli-quien"(?![^>]*name=)[^>]*data-cumple-espejo="name"#', $html, '«Personalizar» no es un espejo de su fila');
        $this->assertStringNotContainsString('name="honoree_name"', $html);

        // Guardar la lista escribe su ficha Y la tarjeta.
        $this->actingAs($host)->post(route('reservation.guests.store', ['reservation' => $reservation]), [
            'guests' => [['name' => 'Lucía María', 'age' => '7'], ['name' => 'Mateo']],
        ])->assertRedirect();
        $this->assertSame('Lucía María', $reservation->fresh()?->guestData()[0]['name'] ?? null);
        $this->assertSame('Lucía María', PartyInvitation::query()->where('order_item_id', $reservation->getKey())->value('honoree_name'));
    }

    /**
     * F3b: la firma de quien cumple es la de su ficha de MENOR A CARGO del anfitrión, no un justificante de invitado. Sin
     * esto su fila decía «Falta» aunque el anfitrión hubiera firmado por él. ⚠️ Con su CONTROL: antes de firmar, «Falta».
     */
    public function test_the_honoree_row_is_signed_by_his_dependent_waiver(): void
    {
        [$reservation, , $host] = $this->party();
        $lucia = $this->declareLegacyDependent($host, 'Lucía', '2019-05-04', 'Pérez');
        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::flushMemo();
        $version = app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
        $ficha0 = fn (): array => $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->viewData('m')['ninos'][0];

        $this->assertSame('cumple', $ficha0()['origen']);
        $this->assertFalse($ficha0()['firmada'], 'CONTROL: sin firmar, su fila tiene que decir «Falta»');

        app(WaiverSigner::class)->sign($host, $version, new WaiverSignatureRequest(
            channel: WaiverSignature::CHANNEL_WEB, ip: '10.0.0.7', userAgent: 'test',
            subjectType: WaiverSignature::SUBJECT_DEPENDENT, subjectId: (int) $lucia->getKey(),
        ));

        $this->assertTrue($ficha0()['firmada'], 'su fila no ve la firma de su ficha de menor a cargo');
    }

    /** CONTROL: una reserva de antes pinta la lista de siempre, con «Personalizar» escribiendo la invitación. */
    public function test_a_reservation_without_the_seal_keeps_the_list_of_before(): void
    {
        [$reservation, , $host] = $this->party(honoreeRow: false);

        $html = (string) $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $reservation]))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-filas-cumple', $html);
        $this->assertStringNotContainsString('data-cumple-espejo', $html);
        $this->assertStringContainsString('name="honoree_name"', $html);
    }

    // ── Fixture ──────────────────────────────────────────────────────────────

    private function pack(Zone $zone, bool $counts): TicketType
    {
        return TicketType::firstOrCreate(
            ['zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'position' => $counts ? 1 : 2],
            [
                'name' => ['es' => $counts ? 'Pack Kids' : 'Pack Viejo'], 'duration_min' => 120, 'seats_per_unit' => 1,
                'min_qty' => 1, 'max_qty' => 30, 'is_sellable' => true, 'is_active' => true,
                'guest_invitation' => true, 'honoree_counts' => $counts,
                'guest_fields' => [
                    ['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']],
                    ['key' => 'age', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => false, 'label' => ['es' => 'Edad']],
                    ['key' => 'allergy', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => false, 'label' => ['es' => 'Alergias']],
                ],
                'event_fields' => [
                    ['key' => 'celebrant', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Quién cumple']],
                    ['key' => 'celebrant_age', 'type' => TicketType::FIELD_TYPE_CELEBRANT_AGE, 'required' => false, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Su edad']],
                ],
            ],
        );
    }

    /**
     * Una fiesta pagada con su invitación materializada (quien cumple, «Lucía», 7), sellada o no.
     *
     * @return array{0: OrderItem, 1: PartyInvitation, 2: User}
     */
    private function party(bool $honoreeRow = true, int $quantity = 6): array
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = $this->pack($zone, $honoreeRow);
        $slot = Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => now()->addMonth()->toDateString(), 'start_time' => '17:00:00'],
            ['end_time' => '19:00:00', 'capacity' => 200, 'online_capacity' => 200],
        );
        $host = User::factory()->create(['name' => 'Marta Anfitriona', 'phone' => '600111222']);
        $order = Order::create([
            'user_id' => $host->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $reservation = $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => $quantity, 'unit_price' => 500, 'seats' => $quantity,
            'event_data' => ['celebrant' => 'Lucía', 'celebrant_age' => 7],
            'honoree_row' => $honoreeRow,
        ]);
        $reservation = $reservation->fresh(['ticketType', 'slot', 'order.user']) ?? $reservation;
        $invitation = app(PartyInvitations::class)->forReservation($reservation);
        $this->assertNotNull($invitation);

        return [$reservation, $invitation, $host];
    }

    private function reply(OrderItem $reservation, PartyInvitation $invitation, string $child, bool $attending): InvitationReply
    {
        return InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $reservation->getKey(),
            'attending' => $attending,
            'child_name' => $child,
            'child_key' => PersonNameKey::for($child),
        ]);
    }

    /** @return array{0: Zone, 1: Slot, 2: RateType} */
    private function bookable(): array
    {
        $rate = RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);
        $zone = Zone::create(['slug' => 'cumples', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false]);
        $slot = Slot::create(['zone_id' => $zone->id, 'date' => now()->addDays(20)->toDateString(), 'start_time' => '11:00:00', 'end_time' => '13:00:00', 'capacity' => 200, 'online_capacity' => 200]);
        foreach ([PackAvailability::SETTING_MAX_PER_SLOT => '5', PackAvailability::SETTING_MAX_GUESTS_PER_SLOT => '60', PackAvailability::SETTING_PREP_BLOCKS_CUPO => '0'] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['key' => $key, 'value' => $value, 'group' => 'packs']);
        }
        Setting::flushMemo();

        return [$zone, $slot, $rate];
    }

    private function bookablePack(Zone $zone, RateType $rate, string $name, bool $counts): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'seats_per_unit' => 1, 'duration_min' => 120, 'min_qty' => 1, 'max_qty' => 30,
            'is_sellable' => true, 'is_active' => true, 'position' => (int) TicketType::max('position') + 1,
            'honoree_counts' => $counts,
            'guest_fields' => [['key' => 'name', 'type' => TicketType::FIELD_TYPE_TEXT, 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        Price::create(['priceable_type' => $pack->getMorphClass(), 'priceable_id' => $pack->id, 'rate_type_id' => $rate->id, 'amount_cents' => 1800, 'currency' => 'EUR']);

        return $pack;
    }
}
