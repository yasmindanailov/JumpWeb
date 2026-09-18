<?php

namespace Tests\Feature\Invitation;

use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\GuestCountPolicy;
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
