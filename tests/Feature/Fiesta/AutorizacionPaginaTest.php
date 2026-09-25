<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * LA AUTORIZACIÓN del sistema nuevo (`specs/fiesta-sistema-nuevo.md` §4.1 y §4.2, T3; `#743`, `#745`): la página lee SOLO
 * el modelo de página y el modelo dice lo que el diseño pinta con lo que HAY, más lo que `#745` decidió mantener.
 *
 * ⚠️ Se afirma el MARCADO que la página necesita para firmar sin JavaScript (los `name` del controlador de siempre, las
 * marcas `data-*`) y lo que `#745` decidió: el adulto en UNA casilla, el teléfono obligatorio, nacimiento y relación se
 * quedan, el descargo en el flujo. La hoja en blanco, los cinco desenlaces y las defensas siguen en
 * `GuardianAuthorizationScreenTest` y `GuestMinorAuthorizationTest`; la piel, en `GuardianSkinTest`.
 */
class AutorizacionPaginaTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    public function test_the_page_is_the_new_skin_with_the_card_and_the_form(): void
    {
        ['reservation' => $reservation] = $this->mountParty();

        $html = (string) $this->get($reservation->guardianAuthorizationSignedUrl())->assertOk()->getContent();

        $this->assertStringContainsString('data-authorization-page', $html, 'la página es la del sistema nuevo');
        $this->assertStringContainsString('data-theme="confeti"', $html, 'el tema de la invitación pinta la página');
        $this->assertStringContainsString('Autorización para la fiesta de Lucía', $html, 'el titular del brief, con quien cumple');
        $this->assertMatchesRegularExpression('#data-guardian-what>[^<]*17:00[^<]*Parque Verify[^<]*</p>#', $html, 'cuándo y dónde, en una línea');
        $this->assertStringContainsString('a cargo de Marta', $html, 'quien organiza, por su nombre de pila (`#744`)');
        // Quién responde del menor, con su teléfono (§12.4 de la hermana), en la ranura de la tarjeta.
        $this->assertStringContainsString('Marta Anfitriona', $html);
        $this->assertStringContainsString('href="tel:600111222"', $html);
        // El formulario del brief, con los `name` de siempre; el adulto en UNA casilla y el teléfono sin «opcional».
        $this->assertStringContainsString('data-firma', $html);
        foreach (['minor_name', 'minor_surname', 'minor_born_on', 'guardian_name', 'guardian_relationship', 'guardian_phone', 'accept_waiver', 'guardian_email', 'document_id', 'contact_ref'] as $campo) {
            $this->assertStringContainsString('name="'.$campo.'"', $html, $campo);
        }
        $this->assertStringNotContainsString('name="guardian_surname"', $html, 'el adulto escribe nombre y apellidos en UNA casilla (`#745`)');
        $this->assertStringNotContainsString('name="companion"', $html);
        $this->assertSame(1, substr_count($html, '<span class="pz-campo__opt">'), 'solo el correo es opcional');
        $this->assertSame(1, preg_match('#<select id="aut-relacion" name="guardian_relationship"[^>]*>(.*?)</select>#s', $html, $relacion));
        $this->assertSame(6, substr_count($relacion[1], '<option value='), 'la relación: el «elige» y las cinco de la lista cerrada');
        // El descargo se PRESENTA en el flujo (`waiver-probatorio.md` §4.4) y «Leer el descargo» lleva a él.
        $this->assertMatchesRegularExpression('#id="descargo"[^>]*>.*?Exención de responsabilidad.*?Saltar en camas elásticas#s', $html);
        $this->assertStringContainsString('href="#descargo"', $html);
        $this->assertStringContainsString('Marta verá el nombre de tu hijo y que su autorización está firmada', $html);
        $this->assertStringContainsString('data-idioma-select', $html);
    }

    public function test_a_parent_signs_with_one_name_field_and_a_phone_and_sees_the_done_block(): void
    {
        ['reservation' => $reservation, 'document' => $document] = $this->mountParty();

        $vuelta = $this->post($this->signedAuthorizationStoreUrl($reservation), $this->authorizationPayload($document, [
            'guardian_name' => 'Marta Ruiz Díaz', 'guardian_surname' => '', 'guardian_email' => '',
        ]));
        $vuelta->assertRedirect()->assertSessionHas('guardian_status', 'signed');

        $this->assertDatabaseHas('guardian_authorizations', [
            'order_item_id' => $reservation->getKey(), 'minor_name' => 'Ana', 'minor_surname' => 'Gómez Ruiz',
            'guardian_name' => 'Marta Ruiz Díaz', 'guardian_surname' => '', 'guardian_phone' => '600111222',
        ]);

        // El Listo del brief: firmada, con el niño y quien firma; sin formulario.
        $html = (string) $this->get((string) $vuelta->headers->get('Location'))->assertOk()->getContent();
        $this->assertStringContainsString('data-guardian-outcome="signed"', $html);
        $this->assertStringContainsString('<p class="aut-quien">Ana Gómez Ruiz<span>Marta Ruiz Díaz · 600111222</span></p>', $html);
        $this->assertStringNotContainsString('data-firma', $html);
    }

    public function test_without_a_phone_nothing_is_written_and_the_error_has_the_briefs_words(): void
    {
        ['reservation' => $reservation, 'document' => $document] = $this->mountParty();

        $this->post($this->signedAuthorizationStoreUrl($reservation), $this->authorizationPayload($document, ['guardian_phone' => '']))
            ->assertRedirect()
            ->assertSessionHasErrors(['guardian_phone' => 'Escribe tu teléfono.']);

        $this->assertDatabaseCount('guardian_authorizations', 0);
    }

    /**
     * ⚠️ El bag de errores se mete en la sesión con la FORMA con la que viaja de verdad (`session.serialization = json`:
     * `Store::marshalErrorBag()`), no con un POST previo: en el arnés (driver `array`, atributos en memoria) el bag del
     * POST llega VACÍO a la petición siguiente aunque `assertSessionHasErrors` lo vea, y ninguna guarda de la piel vieja
     * lo pintó nunca. El flujo real sí lo pinta: medido con `sonda-aut.sh` (curl con cookies: 1 error, 1 campo en rojo).
     */
    public function test_a_rejected_form_paints_the_error_next_to_its_field_and_keeps_what_was_written(): void
    {
        ['reservation' => $reservation] = $this->mountParty();

        $html = (string) $this->withSession([
            'errors' => ['default' => ['format' => ':message', 'messages' => ['guardian_phone' => ['Escribe tu teléfono.']]]],
            '_old_input' => ['minor_name' => 'Ana', 'guardian_name' => 'Marta Ruiz'],
        ])->get($reservation->guardianAuthorizationSignedUrl())->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div class="pz-campo pz-campo--error">.*?name="guardian_phone".*?<span id="[^"]+" class="pz-campo__error">Escribe tu teléfono\.</span>#s', $html, 'el error, con las palabras del brief, junto a su campo');
        $this->assertStringContainsString('value="Ana"', $html, 'lo que escribió sigue puesto');
        $this->assertStringContainsString('value="Marta Ruiz"', $html);
        $this->assertStringContainsString('data-firma', $html);
    }

    public function test_an_unpaid_reservation_says_why_with_the_shape_of_the_dead_link_and_no_form(): void
    {
        ['reservation' => $reservation, 'order' => $order] = $this->mountParty();
        $order->forceFill(['status' => Order::STATUS_PENDING, 'paid_at' => null])->save();

        $html = (string) $this->get($reservation->guardianAuthorizationSignedUrl())->assertOk()->getContent();

        $this->assertStringContainsString('data-guardian-blocked="not_paid"', $html);
        $this->assertStringContainsString(__('guardian.blocked.not_paid'), $html);
        $this->assertStringNotContainsString('data-firma', $html, 'bloqueada, no hay formulario que enviar');
    }
}
