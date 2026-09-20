<?php

namespace Tests\Feature\Invitation;

use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GuestCountAdjuster;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Exceptions\GuardianAuthorizationRefusedException;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\GuardianPlaces;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **Las PLAZAS CON DUEÑO de una invitación y la excepción del firmador** (T4·4 de
 * `docs/specs/celebracion-e-invitacion.md` §4.5·7 y §4.5·8; `DECISIONES #576`).
 *
 * ❗❗ **El caso que ordena la tanda**: el padre que dijo «sí» con la lista completa **tiene que poder
 * firmar**. Sin la excepción, su propio «sí» —el que le reservó el sitio— le cerraría la puerta del
 * justificante, que es la peor forma de fallar que tiene esta feature: le deja al niño sin entrar por
 * haber avisado.
 *
 * Y la otra mitad: un «sí» pendiente **cuenta como plaza con dueño** (V4), para que el anfitrión no
 * pueda bajar los invitados por debajo de quien ya le confirmó.
 *
 * ⚠️ La frontera: las respuestas son de **Booking** y las firmas de **Identity**, así que el contrato
 * publica IDS y la resta la hace `GuardianPlaces`, el único sitio donde coexisten.
 */
class InvitationPlacesTest extends TestCase
{
    use RefreshDatabase;

    private const VISIT = '2026-11-14';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 12:00:00', 'UTC'));

        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::flushMemo();
    }

    // ─── 1 · El contrato: qué publica Booking ─────────────────────────────────

    public function test_only_live_yeses_are_published_one_per_child(): void
    {
        $reservation = $this->reservation(quantity: 6);
        $invitation = $this->invitationFor($reservation);

        $yes = $this->reply($invitation, 'Hugo Ruiz', true);
        $this->reply($invitation, 'Ana Gil', false);                       // un «no» no ocupa
        $repeated = $this->reply($invitation, 'hugo  RUIZ', true);         // el mismo niño otra vez
        $dismissed = $this->reply($invitation, 'Leo Sanz', true);
        $dismissed->forceFill(['dismissed_at' => now()])->save();          // «no lo apuntes»

        $ids = app(PartyGuests::class)->committedReplyIdsIn((int) $reservation->getKey());

        $this->assertSame([$repeated->getKey()], $ids, 'una plaza por niño, y la más reciente de ese niño');
        $this->assertNotContains($yes->getKey(), $ids, 'la anterior del mismo niño no suma otra plaza');
    }

    /** ⚠️ Seguridad: un id de OTRA fiesta no puede servir de llave en ésta. */
    public function test_a_reply_from_another_reservation_is_not_committed_here(): void
    {
        $mine = $this->reservation();
        $other = $this->reservation();
        $theirReply = $this->reply($this->invitationFor($other), 'Hugo Ruiz', true);

        $guests = app(PartyGuests::class);

        $this->assertTrue($guests->isCommittedReply((int) $theirReply->getKey(), (int) $other->getKey()));
        $this->assertFalse($guests->isCommittedReply((int) $theirReply->getKey(), (int) $mine->getKey()));
    }

    // ─── 2 · El suelo: un «sí» es una plaza con dueño ─────────────────────────

    public function test_a_pending_yes_counts_as_a_place_with_an_owner(): void
    {
        $reservation = $this->reservation(quantity: 6);
        $invitation = $this->invitationFor($reservation);

        $this->assertSame(0, app(GuardianPlaces::class)->takenIn((int) $reservation->getKey()));

        $this->reply($invitation, 'Hugo Ruiz', true);
        $this->reply($invitation, 'Ana Gil', true);

        $this->assertSame(2, app(GuardianPlaces::class)->takenIn((int) $reservation->getKey()));
    }

    /** Y con ello el CLIENTE no puede bajar los invitados por debajo de quien ya le confirmó (`#444`). */
    public function test_the_host_cannot_drop_below_the_children_who_already_confirmed(): void
    {
        $reservation = $this->reservation(quantity: 6);
        $invitation = $this->invitationFor($reservation);
        $this->reply($invitation, 'Hugo Ruiz', true);
        $this->reply($invitation, 'Ana Gil', true);
        $this->reply($invitation, 'Leo Sanz', true);

        $this->assertSame(3, app(GuestCountPolicy::class)->assignedFloorFor($reservation->fresh(['ticketType', 'slot', 'order'])));
    }

    /**
     * ⚠️⚠️⚠️ **EL BORDE ABIERTO §7.1·5, REPRODUCIDO: el suelo cuenta PLAZAS, no POSICIONES.**
     *
     * El suelo impide bajar por DEBAJO de cuántos confirmaron, pero no dice CUÁLES. Y el recorte
     * se lleva las filas del FINAL (`sanitizeGuestData` conserva las primeras `quantity`). Así que
     * con los confirmados en las últimas fichas y las primeras vacías, una bajada permitida —por
     * encima del suelo— **borra justo a los niños que el suelo prometía proteger** y deja en pie
     * fichas vacías. El anfitrión no se entera: la vista pinta `quantity` filas.
     */
    public function test_lowering_the_count_drops_empty_cards_before_the_children_who_confirmed(): void
    {
        $reservation = $this->reservation(quantity: 8);

        $this->readyForCountChange($reservation);

        $invitation = $this->invitationFor($reservation);
        $this->reply($invitation, 'Hugo Ruiz', true);
        $this->reply($invitation, 'Ana Gil', true);

        // El anfitrión los adopta en las DOS ÚLTIMAS fichas; las seis primeras siguen vacías.
        $rows = array_fill(0, 8, []);
        $rows[6] = ['name' => 'Hugo Ruiz'];
        $rows[7] = ['name' => 'Ana Gil'];
        $reservation->forceFill(['guest_data' => $rows])->save();

        $fresh = $reservation->fresh(['ticketType', 'slot', 'order']);

        // El suelo son 2 —los dos «sí»—, así que bajar a 3 está PERMITIDO y no es un abuso.
        $this->assertSame(2, app(GuestCountPolicy::class)->assignedFloorFor($fresh));

        $change = app(GuestCountAdjuster::class)->adjust($fresh, 3, 'signed_link');
        $this->assertTrue($change->applied, 'bajar por encima del suelo se permite; bloqueó por: '.var_export($change->reason, true));

        // Lo que sobrevive al siguiente guardado del anfitrión.
        $after = $reservation->fresh();
        $kept = $after->ticketType->sanitizeGuestData($after->guestData(), (int) $after->quantity);
        $names = array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['name'] ?? '')),
            $kept,
        )));

        $this->assertContains('Hugo Ruiz', $names, 'el niño que confirmó NO puede desaparecer de la lista');
        $this->assertContains('Ana Gil', $names, 'ni el segundo: el suelo los contó como plazas con dueño');

        // Y el ORDEN que eligió el anfitrión se conserva entre ellos: se compacta, no se baraja.
        $this->assertSame(['Hugo Ruiz', 'Ana Gil'], $names, 'lo que sobra son las VACÍAS, y en su orden');
        $this->assertCount(3, $kept, 'la tercera ficha sigue existiendo, vacía: bajó a 3');

        // ⚠️ Y la respuesta sigue VIVA: si su ficha hubiera caído, `reconcileAdopted()` la habría
        // descartado y el suelo se habría deshecho solo.
        $this->assertSame(2, app(GuardianPlaces::class)->takenIn((int) $reservation->getKey()));
    }

    /**
     * ▶ **El CONTROL**: sin nadie que haya confirmado, bajar sigue haciendo lo de siempre — se pierden
     * las fichas del final. La compactación no puede reordenar la lista de quien no tiene invitación.
     */
    public function test_control_without_confirmed_children_the_last_cards_are_still_the_ones_lost(): void
    {
        $reservation = $this->reservation(quantity: 8);
        $this->readyForCountChange($reservation);

        // Tres fichas escritas por el anfitrión, ninguna con respuesta detrás.
        $rows = array_fill(0, 8, []);
        $rows[0] = ['name' => 'Primero'];
        $rows[5] = ['name' => 'Sexto'];
        $rows[7] = ['name' => 'Octavo'];
        $reservation->forceFill(['guest_data' => $rows])->save();

        $change = app(GuestCountAdjuster::class)
            ->adjust($reservation->fresh(['ticketType', 'slot', 'order']), 2, 'signed_link');
        $this->assertTrue($change->applied);

        $after = $reservation->fresh();
        $kept = $after->ticketType->sanitizeGuestData($after->guestData(), (int) $after->quantity);
        $names = array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['name'] ?? '')),
            $kept,
        )));

        // Las tres escritas se compactan al principio, así que sobreviven las DOS primeras y se
        // pierde la tercera: una ficha con datos sigue perdiéndose cuando no caben todas.
        $this->assertSame(['Primero', 'Sexto'], $names);
        $this->assertSame(1, $change->discardedForms, 'y el aviso cuenta esa, no las vacías');
    }

    /**
     * ❗❗ **La promesa entera del suelo, y el caso que el ARNÉS pidió**: cuando NO caben todas las
     * fichas escritas, el que confirmó gana al que solo está apuntado.
     *
     * Sin este caso, dos mutaciones sobrevivían —«tratar a los confirmados como una ficha escrita
     * más» y «proteger a cualquiera que tenga nombre»— porque en los otros casos los confirmados
     * eran las ÚNICAS fichas con datos y los tres grupos daban el mismo resultado. El suelo dice
     * que hay 2 plazas con dueño: son ESAS dos las que no se pueden perder, no dos cualesquiera.
     */
    public function test_a_child_who_confirmed_outranks_one_the_host_merely_wrote_down(): void
    {
        $reservation = $this->reservation(quantity: 8);
        $this->readyForCountChange($reservation);

        $invitation = $this->invitationFor($reservation);
        $this->reply($invitation, 'Hugo Ruiz', true);
        $this->reply($invitation, 'Ana Gil', true);

        // Seis apuntados a mano por el anfitrión, y los DOS que confirmaron al final del todo.
        $rows = [];
        foreach (range(1, 6) as $i) {
            $rows[] = ['name' => 'Apuntado '.$i];
        }
        $rows[] = ['name' => 'Hugo Ruiz'];
        $rows[] = ['name' => 'Ana Gil'];
        $reservation->forceFill(['guest_data' => $rows])->save();

        $fresh = $reservation->fresh(['ticketType', 'slot', 'order']);
        $this->assertSame(2, app(GuestCountPolicy::class)->assignedFloorFor($fresh));

        // Bajar a 3: las ocho fichas están escritas, así que cinco se pierden SÍ o SÍ. Lo que no
        // puede pasar es que se pierdan las dos que el suelo contó.
        $change = app(GuestCountAdjuster::class)->adjust($fresh, 3, 'signed_link');
        $this->assertTrue($change->applied);

        $after = $reservation->fresh();
        $kept = $after->ticketType->sanitizeGuestData($after->guestData(), (int) $after->quantity);
        $names = array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['name'] ?? '')),
            $kept,
        )));

        $this->assertSame(['Hugo Ruiz', 'Ana Gil', 'Apuntado 1'], $names);
        $this->assertSame(5, $change->discardedForms, 'y se le dicen las cinco que de verdad pierde');
        $this->assertSame(2, app(GuardianPlaces::class)->takenIn((int) $reservation->getKey()));
    }

    /**
     * ❗❗❗ **LA COSTURA, ANDADA POR HTTP — y el caso que descubrió que el arreglo no bastaba.**
     *
     * Los casos de arriba llaman a `GuestCountAdjuster` a pelo, y así el borde parecía cerrado. Por
     * el camino REAL no lo estaba: el controlador ajusta **y después** guarda las fichas que mandó
     * el navegador, **en el orden viejo** (§4.7·2 del post-form: la cantidad va primero a propósito).
     * El saneo recorta ese envío a la cantidad nueva y volvía a tirar a los confirmados, deshaciendo
     * la compactación que el ajuste acababa de escribir.
     *
     * *Ningún test de dominio podía verlo: los dos lados estaban bien por separado.*
     */
    public function test_the_whole_path_over_http_keeps_the_children_who_confirmed(): void
    {
        $reservation = $this->reservation(quantity: 8);
        $this->readyForCountChange($reservation);

        $invitation = $this->invitationFor($reservation);
        $this->reply($invitation, 'Hugo Ruiz', true);
        $this->reply($invitation, 'Ana Gil', true);

        $rows = [];
        foreach (range(1, 6) as $i) {
            $rows[] = ['name' => 'Apuntado '.$i];
        }
        $rows[] = ['name' => 'Hugo Ruiz'];
        $rows[] = ['name' => 'Ana Gil'];
        $reservation->forceFill(['guest_data' => $rows])->save();

        // El navegador manda las OCHO fichas tal y como las pintó, y baja la cantidad a 3.
        $this->actingAs($reservation->order->user)
            ->post(route('reservation.guests.store', ['reservation' => $reservation]), [
                'guest_count' => 3,
                'guests' => $rows,
            ])->assertRedirect();

        $names = array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['name'] ?? '')),
            $reservation->fresh()->guestData(),
        )));

        $this->assertSame(3, (int) $reservation->fresh()->quantity);
        $this->assertContains('Hugo Ruiz', $names, 'por HTTP tampoco puede perderse quien confirmó');
        $this->assertContains('Ana Gil', $names);
        $this->assertSame(2, app(GuardianPlaces::class)->takenIn((int) $reservation->getKey()));
    }

    /**
     * ⚠️⚠️ **Lo que NO puede pasar: contar dos veces al mismo niño.** Cuando su padre firma desde la
     * respuesta, la plaza pasa a contarla el justificante y el «sí» deja de sumar aparte.
     */
    public function test_a_yes_that_became_a_signed_waiver_is_counted_once(): void
    {
        $reservation = $this->reservation(quantity: 6);
        $invitation = $this->invitationFor($reservation);
        $reply = $this->reply($invitation, 'Hugo Ruiz', true);

        $this->assertSame(1, app(GuardianPlaces::class)->takenIn((int) $reservation->getKey()));

        $this->signFor($reservation, 'Hugo', 'Ruiz', (int) $reply->getKey());

        $this->assertSame(
            1, app(GuardianPlaces::class)->takenIn((int) $reservation->getKey()),
            'el mismo niño no puede ocupar dos plazas por haber confirmado Y firmado',
        );
    }

    /**
     * Y el doble conteo que SÍ existe, **declarado** (§4.5·8): un justificante SUELTO de un niño que
     * además dijo «sí». Su firma no viene atada, y saber que son el mismo niño exigiría comparar
     * nombres —que es justo lo que `#328` decidió no hacer—. El suelo sale alto: protege de más.
     */
    public function test_an_unlinked_waiver_for_a_child_who_also_said_yes_counts_twice_and_that_is_declared(): void
    {
        $reservation = $this->reservation(quantity: 6);
        $this->reply($this->invitationFor($reservation), 'Hugo Ruiz', true);

        $this->signFor($reservation, 'Hugo', 'Ruiz', null);

        $this->assertSame(2, app(GuardianPlaces::class)->takenIn((int) $reservation->getKey()));
    }

    // ─── 3 · La EXCEPCIÓN del firmador ────────────────────────────────────────

    /**
     * ❗❗ **El caso que ordena la tanda.** Lista completa por los «sí», y el padre que dijo «sí» llega a
     * firmar: tiene que poder, porque esa plaza ya es suya.
     */
    public function test_a_parent_who_said_yes_can_sign_even_with_the_list_full(): void
    {
        $reservation = $this->reservation(quantity: 1);
        $reply = $this->reply($this->invitationFor($reservation), 'Hugo Ruiz', true);

        // La lista está completa: ese único sitio ya tiene dueño.
        $this->assertSame(0, app(GuardianPlaces::class)->freeIn($this->context($reservation)));

        $result = $this->signFor($reservation, 'Hugo', 'Ruiz', (int) $reply->getKey());

        $this->assertTrue($result['created']);
        $this->assertSame((int) $reply->getKey(), (int) $result['authorization']->invitation_reply_id, 'la firma queda atada a su «sí»');
    }

    /** Sin la respuesta atada, la lista completa se respeta como siempre. */
    public function test_without_the_reply_the_full_list_still_refuses(): void
    {
        $reservation = $this->reservation(quantity: 1);
        $this->reply($this->invitationFor($reservation), 'Hugo Ruiz', true);

        $this->expectException(GuardianAuthorizationRefusedException::class);
        $this->signFor($reservation, 'Otro', 'Niño', null);
    }

    /**
     * ❗❗ **Un «sí» abre la puerta UNA vez, y hasta `#579` la abría tantas como quisieras.**
     *
     * El atajo del firmador comprobaba que la respuesta fuera un «sí» vivo de esta reserva, pero **no
     * que siguiera sin usar**. Con la lista llena, el mismo `invitation_reply_id` levantaba el tope
     * una y otra vez: N justificantes por encima de lo comprado, que es exactamente lo que el tope
     * existe para impedir —«que nadie autorice a más gente de la que se ha comprado»—.
     *
     * ⚠️ Y el que lo explota no necesita ser malicioso: le basta reenviar a otro padre el enlace del
     * justificante que le llegó a él, porque ese enlace lleva dentro la respuesta atada.
     */
    public function test_a_yes_lifts_the_cap_once_and_only_once(): void
    {
        $reservation = $this->reservation(quantity: 1);
        $reply = $this->reply($this->invitationFor($reservation), 'Hugo Ruiz', true);

        $first = $this->signFor($reservation, 'Hugo', 'Ruiz', (int) $reply->getKey());
        $this->assertTrue($first['created'], 'el padre que avisó tiene que poder firmar');

        // El MISMO «sí», otro menor: la plaza que protegía ya la está usando el primero.
        $this->expectException(GuardianAuthorizationRefusedException::class);
        $this->signFor($reservation, 'Otro', 'Niño', (int) $reply->getKey());
    }

    /**
     * ⚠️⚠️ **Y un id de OTRA fiesta no abre ésta.** Si el firmador se creyera el parámetro en vez de
     * preguntarle al contrato, cualquiera con un enlace de otra invitación se saltaría el tope.
     */
    public function test_a_reply_id_from_another_party_does_not_lift_the_cap(): void
    {
        $reservation = $this->reservation(quantity: 1);
        $this->reply($this->invitationFor($reservation), 'Hugo Ruiz', true);

        $other = $this->reservation(quantity: 6);
        $foreign = $this->reply($this->invitationFor($other), 'Ana Gil', true);

        $this->expectException(GuardianAuthorizationRefusedException::class);
        $this->signFor($reservation, 'Otro', 'Niño', (int) $foreign->getKey());
    }

    // ─── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * **Deja la reserva en condiciones de que `GuestCountAdjuster` pueda tocarla, y lo COMPRUEBA.**
     *
     * ⚠️⚠️ El instrumento se arma y se verifica antes de fiarse de él. La rejilla de este fichero no
     * basta para esta superficie: su zona no declara aforo y solo existe la franja de las 17:00,
     * mientras el pack dura 120 min — la fiesta no cabe en el horario y `availableGuestsFor`
     * devuelve 0. El ajuste revalida el cupo **también al BAJAR**, así que las dos cosas salían
     * como `sold_out`: el FIXTURE hablando, no el producto. Y re-tarifica, así que sin `RateType`
     * revienta en `RateResolver`.
     */
    private function readyForCountChange(OrderItem $reservation): void
    {
        $zone = $reservation->slot->zone;
        $zone->forceFill(['max_per_slot' => 5, 'max_guests_per_slot' => 100])->save();
        Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => self::VISIT, 'start_time' => '18:00:00'],
            ['end_time' => '19:00:00', 'capacity' => 200, 'online_capacity' => 200],
        );

        $rate = RateType::firstOrCreate(
            ['key' => RateType::KEY_NORMAL],
            ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true],
        );
        $reservation->ticketType->prices()->create(['rate_type_id' => $rate->id, 'amount_cents' => 500]);

        $this->assertGreaterThanOrEqual(
            (int) $reservation->quantity,
            app(PackAvailability::class)->availableGuestsFor(
                $reservation->slot->fresh('zone'), $reservation->ticketType, [], (int) $reservation->getKey(),
            ),
            'sin esto el caso mediría el fixture y no la conducta',
        );
    }

    private function context($reservation)
    {
        return app(AuthorizableReservations::class)->find((int) $reservation->getKey());
    }

    /** @return array{authorization: GuardianAuthorization, signature: mixed, created: bool} */
    private function signFor(OrderItem $reservation, string $name, string $surname, ?int $replyId): array
    {
        return app(GuardianAuthorizationSigner::class)->sign(
            $reservation->order->user,
            (int) $reservation->getKey(),
            $this->version(),
            [
                'minor_name' => $name, 'minor_surname' => $surname, 'minor_born_on' => '2017-05-04',
                'guardian_name' => 'Ana', 'guardian_surname' => 'Gil', 'guardian_relationship' => 'mother',
                'guardian_email' => null, 'guardian_phone' => null,
            ],
            WaiverSignatureRequest::web('127.0.0.1', 'tests'),
            $replyId,
        );
    }

    private function version(): LegalDocumentVersion
    {
        return LegalDocumentVersion::query()->latest('id')->first()
            ?? app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
                'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
            ])->first();
    }

    private function reservation(int $quantity = 4): OrderItem
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Cumpleaños'],
            'duration_min' => 120, 'seats_per_unit' => 1, 'min_qty' => 1, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_invitation' => true,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Homenajeado']],
            ],
        ]);
        // ⚠️ `firstOrCreate`: `slots` tiene un único por (zona, día, hora) y este fichero crea DOS
        // reservas en varios casos. Compartir franja es además lo realista —dos fiestas a la misma
        // hora en una sala de 200— y no cambia nada de lo que se mide aquí.
        $slot = Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => self::VISIT, 'start_time' => '17:00:00'],
            ['end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200],
        );
        $order = Order::create([
            'user_id' => User::factory()->create(['name' => 'Marta Anfitriona'])->id,
            'code' => 'R-'.mb_strtoupper(mb_substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);

        return $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => $quantity, 'unit_price' => 500, 'seats' => $quantity,
            'event_data' => ['celebrant' => 'Lucía'],
        ]);
    }

    private function invitationFor(OrderItem $reservation): PartyInvitation
    {
        return app(PartyInvitations::class)->forReservation($reservation->fresh(['ticketType', 'slot', 'order']));
    }

    private function reply(PartyInvitation $invitation, string $childName, bool $attending): InvitationReply
    {
        return InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $invitation->order_item_id,
            'attending' => $attending,
            'child_name' => $childName,
            'child_key' => PersonNameKey::for($childName),
        ]);
    }
}
