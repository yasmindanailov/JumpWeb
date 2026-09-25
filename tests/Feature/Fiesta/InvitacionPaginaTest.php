<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * LA INVITACIÓN Y SU RECIBO del sistema nuevo (`specs/fiesta-sistema-nuevo.md` §4.1 y §4.2, T2; `#743`, `#744`): la
 * página lee SOLO el modelo de página y el modelo dice lo que el diseño pinta con lo que HAY.
 *
 * ⚠️ Se afirma el MARCADO que la página necesita para funcionar sin JavaScript (el formulario con sus dos botones, el
 * nombre del campo de siempre, las marcas `data-*`) y lo que `#744` decidió (el recibo directo, el nombre de pila de
 * quien organiza, la descripción del pack), no subcadenas que pasarían con cualquier piel (`#553`). Lo que la spec
 * hermana protege (la hoja en blanco, `og:*` sin token, `no-referrer`) sigue en `InvitationPageTest` y
 * `InvitationSharingTest`, re-apuntadas a esta piel.
 */
class InvitacionPaginaTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    public function test_the_invitation_is_the_new_skin_with_its_theme_and_its_bar(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation] = $this->mountParty();
        $this->replyOf($invitation, $reservation, 'Hugo Ruiz');

        $html = $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))->assertOk()->getContent();

        $this->assertStringContainsString('data-invitation-page', $html, 'la página es la del sistema nuevo');
        $this->assertStringContainsString('data-theme="confeti"', $html, 'el tema por defecto pinta la página');
        $this->assertStringContainsString('data-invitation-card', $html);
        $this->assertStringContainsString('cumple 8 años y te invita a saltar', $html, 'la frase del brief, entera');
        $this->assertStringContainsString('Te invita Marta', $html, 'lo que escribió el anfitrión, tal cual');
        // La barra: un formulario de verdad con dos botones de enviar, el contrato del controlador de siempre.
        $this->assertStringContainsString('data-rsvp', $html);
        $this->assertStringContainsString('name="child_name"', $html);
        $this->assertStringContainsString('name="attending" value="1"', $html);
        $this->assertStringContainsString('name="attending" value="0"', $html);
        $this->assertStringContainsString('Confirma antes del', $html, 'el plazo, escrito en la barra');
        // El idioma: el desplegable nativo con los tres del sitio, y los enlaces para quien no tiene JavaScript.
        $this->assertStringContainsString('data-idioma-select', $html);
        $this->assertSame(3, substr_count($html, '<option value='), 'los tres idiomas del sitio');
        $this->assertStringContainsString('/lang/en', $html);
        // Quien organiza, por su nombre de pila (`#744`); y la hoja en blanco: ni un invitado.
        $this->assertStringContainsString('Marta verá el nombre de tu hijo', $html);
        $this->assertStringNotContainsString('Hugo', $html, 'un «sí» de otro padre se coló en la invitación');
        // «¿Vas tú con él?» no existe en ninguna pantalla (`#743`·5).
        $this->assertStringNotContainsString('name="companion"', $html);
    }

    public function test_the_party_text_is_the_public_description_of_the_pack(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation] = $this->mountParty();
        $url = route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]);

        // Sin descripción, sin bloque.
        $this->assertStringNotContainsString('class="inv-que"', (string) $this->get($url)->assertOk()->getContent());

        TicketType::query()->whereKey($reservation->ticket_type_id)->update([
            'description' => json_encode(['es' => "Dos horas saltando en su zona.\n\nLos padres podéis quedaros en la cafetería."]),
        ]);

        $html = (string) $this->get($url)->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, '<p class="inv-texto">'), 'un párrafo por bloque de la descripción');
        $this->assertStringContainsString('Dos horas saltando en su zona.', $html);
    }

    public function test_saying_yes_lands_on_the_receipt_with_the_card_the_sheet_and_the_offer(): void
    {
        ['invitation' => $invitation] = $this->mountParty();

        $vuelta = $this->post(route('invitation.reply', ['token' => $invitation->token]), ['child_name' => 'Hugo Ruiz', 'attending' => '1']);
        $vuelta->assertRedirectContains('/invitacion/recibo/');

        $html = (string) $this->get((string) $vuelta->headers->get('Location'))->assertOk()->getContent();

        $this->assertStringContainsString('data-receipt="si"', $html);
        $this->assertStringContainsString('¡Contamos con vosotros!', $html, 'el titular de la tarjeta');
        $this->assertStringContainsString('Nos vemos el', $html, 'cuándo, en la frase del recibo');
        $this->assertStringContainsString('data-invitation-calendar', $html, 'tras el sí, el calendario a la vista');
        // «Su ficha», opcional: las columnas del pack menos el nombre, contra la MISMA URL firmada.
        $this->assertStringContainsString('data-receipt-fields', $html);
        $this->assertStringContainsString('name="guest_data[', $html);
        $this->assertStringNotContainsString('name="guest_data[name]"', $html, 'el nombre ya se contestó: no se pide dos veces');
        // La autorización como oferta sin pregunta: «Firmar» con la respuesta atada, y la frase que quita la duda.
        $this->assertStringContainsString('data-receipt-authorization', $html);
        $this->assertStringContainsString('data-receipt-firmar', $html);
        $this->assertStringContainsString('invitation_reply_id=', $html, 'la firma va ATADA a la respuesta (`#576`)');
        $this->assertStringNotContainsString('name="companion"', $html);
        // «Contestar por otro hijo» vuelve a la invitación con el foco en el nombre; y la línea de después, con las 24 h.
        $this->assertStringContainsString('#rsvp-nino"', $html);
        $this->assertStringContainsString('El recibo caduca a las 24 horas', $html);
        $this->assertStringContainsString('sin firma, la autorización se hace en la puerta', $html);
        // Su nombre, solo en el título del documento: la tarjeta es la del diseño.
        $this->assertStringContainsString('<title>Contamos con Hugo Ruiz', $html);
    }

    public function test_saying_no_lands_on_the_receipt_without_the_sheet(): void
    {
        ['invitation' => $invitation] = $this->mountParty();

        $html = (string) $this->followingRedirects()
            ->post(route('invitation.reply', ['token' => $invitation->token]), ['child_name' => 'Lía Fernández', 'attending' => '0'])
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-receipt="no"', $html);
        $this->assertStringContainsString('Gracias por avisar.', $html);
        $this->assertStringContainsString('Marta lo verá en su lista.', $html);
        $this->assertStringNotContainsString('data-receipt-fields', $html, 'tras un «no» no hay ficha que dejar');
        $this->assertStringNotContainsString('data-receipt-authorization', $html, 'ni autorización que ofrecer');
        $this->assertStringContainsString('data-receipt-after', $html, 'la línea de después sí');
    }

    public function test_the_sheet_saves_by_fetch_and_answers_json(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation] = $this->mountParty();
        $reply = $this->replyOf($invitation, $reservation, 'Hugo Ruiz');
        $url = app(PartyInvitations::class)->receiptUrl($reply);

        $this->postJson($url, ['guest_data' => ['allergy' => 'Frutos secos']])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertSame('Frutos secos', $reply->fresh()?->data['allergy'] ?? null);

        // Sin JavaScript, el mismo POST vuelve al recibo y lo dice.
        $this->post($url, ['guest_data' => ['allergy' => 'Gluten']])->assertRedirect($url);
        $this->assertStringContainsString('Guardado', (string) $this->get($url)->assertOk()->getContent());
    }

    public function test_past_the_deadline_the_bar_closes_with_the_hosts_name_and_the_call(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $invitation->forceFill(['show_host_phone' => true])->save();
        // Un corte más largo que los días que faltan: el plazo ya pasó y la fiesta sigue por delante.
        Setting::query()->updateOrCreate(['key' => GuestCountPolicy::SETTING_CUTOFF_HOURS], ['value' => (string) (24 * 30)]);
        Setting::flushMemo();

        $html = (string) $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))->assertOk()->getContent();

        $this->assertStringContainsString('El plazo pasó: habla con Marta.', $html);
        $this->assertStringContainsString('data-invitation-call', $html, 'y «Llamar», con el teléfono de la cuenta');
        $this->assertStringContainsString('href="tel:600111222"', $html);
        $this->assertStringNotContainsString('name="child_name"', $html, 'cerrada, la barra no tiene campo');
        $this->assertStringContainsString('data-invitation-card', $html, 'la tarjeta se ve igual (§7.2·R8)');
    }

    public function test_the_language_switch_comes_back_to_the_invitation_without_a_referer(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $url = route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]);

        // La página no manda `Referer` (`no-referrer`): `lang.switch` vuelve por la URL anterior de la SESIÓN.
        $this->get($url)->assertOk();
        $this->get(route('lang.switch', ['locale' => 'en']))->assertRedirect($url);

        $html = (string) $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('turns 8 and invites you to jump', $html, 'la invitación, en inglés');
        $this->assertStringContainsString('<html lang="en"', $html);
    }
}
