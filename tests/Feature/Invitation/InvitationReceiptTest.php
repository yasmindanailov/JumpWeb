<?php

namespace Tests\Feature\Invitation;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\PersonNameKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **El RECIBO de una respuesta** (T5·3 de `docs/specs/celebracion-e-invitacion.md` §4.5·6;
 * `DECISIONES #703`): las dos ofertas que se le hacen a quien acaba de decir que su hijo viene.
 *
 * Lo que vigila, en orden de importancia:
 *
 *  1. ❗❗ **DOS HORAS, y no es un enlace de edición** (D9). Un enlace permanente convertiría cada
 *     respuesta en una credencial viva sobre los datos de un menor, repartida por un chat de padres.
 *  2. ⚠️ **Su alcance es UNA respuesta, no la fiesta.** El token de la invitación abre la fiesta
 *     entera; esto abre una sola respuesta. Mezclarlos daría los datos de todos los niños a cualquiera
 *     con el enlace.
 *  3. **La atadura con el justificante** viaja DENTRO de la firma: medido, pegar un parámetro a una
 *     URL ya firmada la invalida, y el padre habría recibido un 403 justo después de decir que sí.
 *  4. Todo es OPCIONAL: quien cierra la pestaña sin tocar nada ha terminado bien.
 */
class InvitationReceiptTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ❗❗ El plazo (`#743`·4, el diseño del 24-09): 24 horas abre; pasadas, la firma caduca y el recibo DEVUELVE a la
     * invitación con su aviso en la barra («el recibo caduca a las 24 horas y la respuesta no se edita»). No es un
     * 403: la invitación sigue sirviendo para la hora y el sitio. Una firma que no cuadra sí es 403 (el caso de abajo).
     */
    public function test_the_receipt_lasts_a_day_and_then_sends_back_to_the_invitation(): void
    {
        [, $invitation, $reply] = $this->partyWithReply();
        $url = app(PartyInvitations::class)->receiptUrl($reply);

        $this->assertSame(24, PartyInvitations::RECEIPT_HOURS);
        $this->get($url)->assertOk()->assertSee('Contamos con vosotros')->assertSee('Contamos con Hugo Ruiz');

        // A las 24 horas y un minuto, la firma ya no vale: de vuelta a la invitación, y se dice.
        $this->travel(PartyInvitations::RECEIPT_HOURS)->hours();
        $this->travel(1)->minutes();

        $this->get($url)
            ->assertRedirect(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))
            ->assertSessionHas('invitation_status', 'expired');
        $this->followingRedirects()->get($url)->assertOk()->assertSee('El recibo caduca a las 24 horas');
    }

    /**
     * ⚠️⚠️ **La URL del recibo NO se puede fabricar desde el token de la invitación.** Son dos
     * alcances distintos: quien tiene el enlace de la fiesta —un grupo de clase entero— no puede
     * llegar a los datos que dejó otro padre.
     */
    public function test_the_receipt_cannot_be_reached_without_its_own_signature(): void
    {
        [, , $reply] = $this->partyWithReply();

        // Sin firma.
        $this->get('/invitacion/recibo/'.$reply->getKey())->assertForbidden();

        // Con la firma de OTRA respuesta.
        [, , $otra] = $this->partyWithReply();
        $urlAjena = app(PartyInvitations::class)->receiptUrl($otra);
        $query = (string) parse_url($urlAjena, PHP_URL_QUERY);

        $this->get('/invitacion/recibo/'.$reply->getKey().'?'.$query)->assertForbidden();
    }

    /** Las dos ofertas se guardan, y son opcionales: se puede dejar solo una. */
    public function test_the_two_offers_are_saved_and_either_one_can_be_left_out(): void
    {
        [, , $reply] = $this->partyWithReply();
        $url = app(PartyInvitations::class)->receiptUrl($reply);

        // Solo la compañía.
        $this->post($url, ['companion' => InvitationReply::COMPANION_ALONE])->assertRedirect();
        $this->assertSame(InvitationReply::COMPANION_ALONE, $reply->fresh()?->companion);

        // Y luego solo los datos: la segunda pasada NO borra la primera.
        $this->post($url, ['guest_data' => ['allergy' => 'Frutos secos']])->assertRedirect();

        $fresh = $reply->fresh();
        $this->assertSame(InvitationReply::COMPANION_ALONE, $fresh?->companion, 'la compañía se perdió al guardar los datos');
        $this->assertSame('Frutos secos', ($fresh?->data['allergy'] ?? null));
    }

    /**
     * ❗❗ **La atadura con el justificante viaja DENTRO de la firma.** Medido: añadir un parámetro a
     * una URL ya firmada la invalida, así que componerla concatenando habría llevado a un 403 **al
     * padre que acaba de decir que su hijo viene** — el peor momento posible.
     *
     * ❗❗ Y el nombre **se le ENSEÑA, no se le prerrellena** (`[DECIDIDO owner]`, `#706`): nombre y
     * apellidos son dos campos (`#236`) y la invitación los pide en uno, así que **cualquier reparto
     * automático adivina** — volcarlo entero deja el apellido obligatorio vacío, y partirlo por el
     * primer espacio recorta «María del Carmen» a «María» dentro de un documento que se firma. El
     * único que sabe dónde acaba su nombre es quien lo escribió.
     */
    public function test_lo_dejo_y_me_voy_lands_on_a_waiver_that_opens_with_the_reply_tied(): void
    {
        [$reservation, , $reply] = $this->partyWithReply();

        $html = (string) $this->get(app(PartyInvitations::class)->receiptUrl($reply))
            ->assertOk()->getContent();

        $this->assertTrue(
            (bool) preg_match('#href="([^"]*autorizacion[^"]*)"#', $html, $m),
            'el recibo no ofrece el salto al justificante'
        );

        $waiver = html_entity_decode($m[1]);

        // La firma del enlace TIENE que valer: es lo que este caso existe para probar.
        $html = (string) $this->get($waiver)
            ->assertOk()
            ->assertSee('value="'.$reply->getKey().'"', escape: false)
            // Lo que escribió, ENSEÑADO…
            ->assertSee('Hugo Ruiz')
            ->getContent();

        // …y las dos casillas VACÍAS: que el reparto lo haga él.
        $this->assertStringContainsString('name="minor_name" type="text" required autocomplete="off"', $html);
        $this->assertStringNotContainsString('value="Hugo Ruiz"', $html, 'el nombre se volcó en una casilla en vez de enseñarse');
        $this->assertStringContainsString('data-from-invitation', $html, 'no se le dice de dónde viene ni qué escribió');

        $this->assertNotNull($reservation->fresh());
    }

    /**
     * ❗ **«¿Vas tú con él?» ya no se pregunta** (`#743`·5, el diseño del 24-09): la autorización es una oferta sin
     * pregunta y la puerta se queda con dos estados. El caso que vigilaba el `:has()` de `companion` (`#703`) se retiró
     * con la pregunta; lo que se afirma ahora es que el recibo no la hace y sigue ofreciendo firmar.
     */
    public function test_the_receipt_no_longer_asks_whether_the_parent_stays(): void
    {
        [, , $reply] = $this->partyWithReply();
        $html = (string) $this->get(app(PartyInvitations::class)->receiptUrl($reply))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="companion"', $html, 'la pregunta volvió al recibo');
        $this->assertStringContainsString('data-receipt-firmar', $html, 'sin la pregunta, la oferta de firmar tiene que seguir');
        $this->assertStringContainsString('Firmarla no te compromete', $html, 'la frase que quita la duda va junto al botón');
    }

    /** Una respuesta que el anfitrión DESCARTÓ no se resucita por el recibo. */
    public function test_a_dropped_reply_has_no_receipt_page(): void
    {
        [, , $reply] = $this->partyWithReply();
        $url = app(PartyInvitations::class)->receiptUrl($reply);

        $reply->forceFill(['dismissed_at' => now()])->save();

        $this->get($url)->assertNotFound();
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    /** @return array{0: OrderItem, 1: PartyInvitation, 2: InvitationReply} */
    private function partyWithReply(): array
    {
        // ⚠️ El justificante NO existe sin una versión del texto publicada (su controlador aborta con
        // 404): la maquinaria puede estar montada antes que el texto legal. Sin esto, el caso del
        // salto medía un 404 y lo habría llamado un defecto del enlace.
        // Y el modo del waiver tiene que ser INTERNO: en `externo` esta instalación no gestiona
        // justificantes y la pantalla no existe (`AuthorizesGuardianAuthorization`).
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
                // El producto OFRECE el justificante: sin eso no hay pantalla que abrir. `optional` y
                // no `required`, que es la combinación que el guard de `#575` prohíbe con invitación.
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

        return [$reservation->fresh(['ticketType', 'slot', 'order.user']) ?? $reservation, $invitation, $reply];
    }
}
