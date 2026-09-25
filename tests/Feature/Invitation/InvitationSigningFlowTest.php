<?php

namespace Tests\Feature\Invitation;

use App\Domain\Booking\Contracts\SignedInvitationReplies;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * **EL FLUJO firmar ↔ invitación, de punta a punta** (T5·5 de
 * `docs/specs/celebracion-e-invitacion.md` §10.6; `DECISIONES #704`).
 *
 * Lo levantó el owner viendo funcionar la T5·3: las piezas estaban bien **por separado** y el camino
 * completo no cerraba. Por eso este fichero no monta escenarios a mano — **camina la pantalla**:
 * recibo → enlace del justificante → envío → vuelta. Un caso que fabricara la autorización con el
 * firmador probaría el dominio, que ya está probado, y **se perdería justo lo que falla, que es la
 * costura**.
 *
 * Los tres defectos que cierra, y el orden es el de lo que le pasa a un padre:
 *
 *  1. **C · el recibo le ofrecía firmar a quien ya había firmado.** La pregunta cruza una frontera —las
 *     firmas son de Identity y el recibo es de Booking—, así que va **por contrato**
 *     ({@see SignedInvitationReplies}).
 *  2. **D · tras firmar, el padre se quedaba mirando la misma hoja que acababa de enviar.** Ahora
 *     vuelve a SU recibo, que es donde estaba, y allí ve que ya está firmado (el defecto 1 otra vez,
 *     del otro lado).
 *  3. **El camino de ERROR perdía la atadura con la invitación**, y ése es el caro: la vuelta de un
 *     formulario rechazado se componía **sin** los extras firmados, así que el segundo intento ya no
 *     iba atado a la respuesta, **cobraba plaza** y podía acabar en «no quedan plazas» — exactamente el
 *     fallo que `#576` existe para impedir.
 *
 * ⚠️ Los tres se miden sobre el HTML que se sirve, no sobre el estado interno: lo que falla aquí es lo
 * que el padre ve.
 */
class InvitationSigningFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * **C · una respuesta que ya tiene justificante no vuelve a ofrecer firmar.**
     *
     * ⚠️ La pregunta es por la ATADURA (`invitation_reply_id`), no por el nombre: comparar nombres es
     * justo lo que `#328` descartó, y aquí no hace falta — la firma que nace desde el recibo queda
     * atada a esa respuesta y eso es un hecho, no un parecido.
     */
    public function test_a_reply_that_already_has_a_waiver_is_not_offered_to_sign_again(): void
    {
        [, , $reply] = $this->partyWithReply();

        // Antes de firmar: el recibo ofrece el salto.
        $this->get(app(PartyInvitations::class)->receiptUrl($reply))
            ->assertOk()
            ->assertSee('autorizacion', escape: false);

        $this->signFromTheReceipt($reply);

        // Después: ni el salto ni el botón, y se dice por qué.
        $html = (string) $this->get(app(PartyInvitations::class)->receiptUrl($reply))
            ->assertOk()->getContent();

        // ⚠️ La marca del botón es `data-receipt-firmar`, no `data-receipt-sign`: esa es SUBCADENA de `data-receipt-signed`.
        $this->assertStringNotContainsString('data-receipt-firmar', $html, 'el recibo sigue ofreciendo firmar a quien ya firmó');
        $this->assertStringContainsString('data-receipt-signed', $html, 'el recibo no dice que ya está firmado');
    }

    /**
     * **D · tras firmar, el padre vuelve a SU recibo** y el flujo se cierra.
     *
     * ⚠️⚠️ La vuelta se decide con el `invitation_reply_id` que viaja **dentro de la firma** de la URL,
     * no con el del cuerpo: el del cuerpo lo manda el navegador y el dominio lo desconfía a propósito
     * (lo contrasta con el contrato). Un recibo es una credencial de dos horas sobre los datos de un
     * menor, así que **no se emite a partir de un número que cualquiera puede escribir**.
     */
    public function test_after_signing_from_the_invitation_the_parent_lands_back_on_his_receipt(): void
    {
        [, , $reply] = $this->partyWithReply();

        $this->signFromTheReceipt($reply)
            ->assertRedirectContains('/invitacion/recibo/'.$reply->getKey());

        $this->assertDatabaseHas('guardian_authorizations', [
            'invitation_reply_id' => $reply->getKey(),
            'minor_name' => 'Hugo',
        ]);
    }

    /**
     * ❗❗ **El camino de error NO puede perder la atadura.** Un padre que se equivoca en la fecha de
     * nacimiento vuelve al formulario: si la vuelta se compone sin los extras firmados, su segundo
     * intento ya no va atado a la respuesta —**cobra plaza**— y con la reserva llena acaba en «no
     * quedan plazas», que es el fallo que `#576` existe para impedir.
     */
    public function test_a_rejected_form_comes_back_with_the_invitation_still_tied(): void
    {
        [, , $reply] = $this->partyWithReply();

        $accion = $this->waiverFormAction($reply);

        // Una fecha de nacimiento futura: la rechaza el validador, no el dominio.
        $respuesta = $this->post($accion, $this->payload([
            'minor_born_on' => now()->addDay()->toDateString(),
        ]));
        $respuesta->assertSessionHasErrors('minor_born_on');

        $vuelta = (string) ($respuesta->headers->get('Location') ?? '');

        // Los dos extras vuelven DENTRO de la firma del enlace de vuelta: es lo que hace que el
        // segundo intento siga atado —y que un recargado más tarde, sin sesión, siga prerrellenado—.
        $this->assertStringContainsString('invitation_reply_id='.$reply->getKey(), $vuelta, 'la vuelta perdió la atadura con la invitación');
        $this->assertStringContainsString('minor=Hugo', $vuelta, 'la vuelta perdió el nombre que traía la invitación');

        $html = (string) $this->get($vuelta)->assertOk()->getContent();

        $this->assertStringContainsString(
            'name="invitation_reply_id" value="'.$reply->getKey().'"',
            $html,
            'el formulario de la segunda oportunidad ya no lleva la atadura'
        );
        // ⚠️ Y lo que se pinta es lo que ESCRIBIÓ, no el prerrelleno: `old()` manda. Un formulario que
        // le devolviera «Hugo Ruiz» encima de su «Hugo» le estaría corrigiendo el nombre de su hijo.
        $this->assertStringContainsString('value="Hugo"', $html, 'la segunda oportunidad perdió lo que el padre había escrito');

        // Y el segundo intento, ya correcto, entra ATADO: sin esto el arreglo sería cosmético.
        $this->post($accion, $this->payload())->assertRedirectContains('/invitacion/recibo/');
        $this->assertDatabaseHas('guardian_authorizations', ['invitation_reply_id' => $reply->getKey()]);
    }

    /**
     * ❗❗ **Sin la FIRMA de la URL no se emite recibo**, aunque quien pregunte tenga derecho a estar en
     * esta pantalla. El recibo es una credencial de dos horas sobre los datos de un menor, y la firma es
     * lo único que prueba que ese enlace lo compuso esta casa para ESA respuesta.
     *
     * ⚠️ Se ejerce por la puerta del TITULAR, que es la otra forma legítima de entrar y la única sin
     * firma: tiene sesión, así que su sitio es el panel, no una credencial pensada para un adulto sin
     * cuenta. **Y el id va en la QUERY**, que es el único sitio donde el controlador lo lee: puesto en
     * el cuerpo, este caso pasaría sin ejercer nada (lo enseñó el arnés, no una relectura).
     */
    public function test_without_a_signed_url_no_receipt_is_minted(): void
    {
        [$reservation, , $reply] = $this->partyWithReply();

        $titular = $reservation->order?->user;
        $this->assertNotNull($titular);

        $sinFirma = route('reservation.authorization.store', ['reservation' => $reservation])
            .'?invitation_reply_id='.$reply->getKey();

        $destino = (string) ($this->actingAs($titular)->post($sinFirma, $this->payload())
            ->headers->get('Location') ?? '');

        $this->assertStringNotContainsString('/invitacion/recibo/', $destino, 'se emitió un recibo sin firma en la URL');
        // Y la firma sí se escribió: lo que se niega es la credencial, no el trámite.
        $this->assertDatabaseHas('guardian_authorizations', ['order_item_id' => $reservation->getKey()]);
    }

    /**
     * ❗ **Un recibo se emite solo para una respuesta DE ESTA reserva.** La firma de la URL la pone esta
     * casa, así que no alcanza para saber de qué fiesta es la respuesta: quien emite tiene que
     * comprobarlo. Defensa en profundidad — hoy el único que compone ese enlace es el recibo, con su
     * propio id.
     */
    public function test_a_reply_from_another_party_gets_no_receipt(): void
    {
        [$reservation] = $this->partyWithReply();
        [, , $ajena] = $this->partyWithReply();

        $accionAjena = URL::temporarySignedRoute(
            'reservation.authorization.store',
            now()->addDays(7),
            ['reservation' => $reservation, 'invitation_reply_id' => $ajena->getKey()],
        );

        $destino = (string) ($this->post($accionAjena, $this->payload())->headers->get('Location') ?? '');

        $this->assertStringNotContainsString(
            '/invitacion/recibo/'.$ajena->getKey(),
            $destino,
            'la respuesta de otra fiesta abrió su recibo'
        );
    }

    // ── El camino que anda un padre ───────────────────────────────────────────

    /** El enlace al justificante **tal y como lo pinta el recibo**, con sus extras dentro de la firma. */
    private function waiverLink(InvitationReply $reply): string
    {
        $html = (string) $this->get(app(PartyInvitations::class)->receiptUrl($reply))
            ->assertOk()->getContent();

        $this->assertTrue(
            (bool) preg_match('#href="([^"]*autorizacion[^"]*)"#', $html, $m),
            'el recibo no ofrece el salto al justificante'
        );

        return html_entity_decode($m[1]);
    }

    /** El `action` del formulario **tal y como lo sirve la pantalla**: es donde viven los extras firmados. */
    private function waiverFormAction(InvitationReply $reply): string
    {
        $html = (string) $this->get($this->waiverLink($reply))->assertOk()->getContent();

        $this->assertTrue(
            (bool) preg_match('#<form class="gf-form" method="POST" action="([^"]+)"#', $html, $m),
            'el formulario del justificante no se pintó'
        );

        return html_entity_decode($m[1]);
    }

    private function signFromTheReceipt(InvitationReply $reply): TestResponse
    {
        return $this->post($this->waiverFormAction($reply), $this->payload());
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'document_id' => LegalDocumentVersion::query()->orderByDesc('id')->firstOrFail()->getKey(),
            'accept_waiver' => '1',
            'invitation_reply_id' => (string) (InvitationReply::query()->orderByDesc('id')->firstOrFail()->getKey()),
            'minor_name' => 'Hugo',
            'minor_surname' => 'Ruiz',
            'minor_born_on' => now()->subYears(8)->toDateString(),
            'guardian_name' => 'Marta',
            'guardian_surname' => 'Ruiz Díaz',
            'guardian_relationship' => 'mother',
            'guardian_email' => 'marta@example.com',
            'guardian_phone' => '600111222',
        ], $overrides);
    }

    // ── Fixture ───────────────────────────────────────────────────────────────

    /** @return array{0: OrderItem, 1: PartyInvitation, 2: InvitationReply} */
    private function partyWithReply(): array
    {
        // ⚠️ Sin versión publicada del texto el justificante responde 404, y sin el modo INTERNO
        // tampoco existe: las dos cosas antes de nada (la trampa que pagó `InvitationReceiptTest`).
        Setting::query()->updateOrCreate(['key' => 'waiver.mode'], ['value' => WaiverSettings::MODE_INTERNAL]);

        if (LegalDocumentVersion::query()->count() === 0) {
            app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
                'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
            ]);
        }

        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(
            ['zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK],
            [
                'name' => ['es' => 'Cumpleaños'], 'duration_min' => 120, 'seats_per_unit' => 1,
                'min_qty' => 1, 'max_qty' => 20, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
                'guest_invitation' => true,
                'guardian_authorization' => TicketType::GUARDIAN_OPTIONAL,
                'guest_fields' => [
                    ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                    ['key' => 'allergy', 'type' => 'text', 'required' => false, 'label' => ['es' => 'Alergias']],
                ],
                'event_fields' => [
                    ['key' => 'celebrant', 'type' => 'text', 'required' => true,
                        'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Homenajeado']],
                ],
            ]
        );
        $slot = Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => now()->addMonth()->toDateString(), 'start_time' => '17:00:00'],
            ['end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200],
        );
        $user = User::factory()->create(['name' => 'Marta Anfitriona', 'phone' => '600111222']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $reservation = $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 6, 'unit_price' => 500, 'seats' => 6,
            'event_data' => ['celebrant' => 'Lucía'],
        ]);

        $invitation = app(PartyInvitations::class)
            ->forReservation($reservation->fresh(['ticketType', 'slot', 'order.user']) ?? $reservation);

        $reply = InvitationReply::query()->create([
            'party_invitation_id' => $invitation->getKey(),
            'order_item_id' => $reservation->getKey(),
            'attending' => true,
            'child_name' => 'Hugo Ruiz',
            'child_key' => PersonNameKey::for('Hugo Ruiz'),
        ]);

        GuardianAuthorization::query()->where('order_item_id', $reservation->getKey())->delete();

        return [$reservation->fresh(['ticketType', 'slot', 'order.user']) ?? $reservation, $invitation, $reply];
    }
}
