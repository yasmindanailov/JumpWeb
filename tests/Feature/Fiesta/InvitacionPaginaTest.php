<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Content\Services\CopiedRating;
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
        $this->assertIdiomaAbajo($html, 'es', 'data-invitation-privacy');
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
        // La autorización como oferta sin pregunta: desde F6a la firma DENTRO, con la respuesta atada, y la frase que
        // quita la duda.
        $this->assertStringContainsString('data-receipt-authorization', $html);
        $this->assertStringContainsString('data-receipt-firma', $html);
        $this->assertStringContainsString('Firmarla no te compromete', $html);
        $this->assertStringContainsString('invitation_reply_id=', $html, 'la firma va ATADA a la respuesta (`#576`)');
        $this->assertStringNotContainsString('name="companion"', $html);
        // «Contestar por otro hijo» vuelve a la invitación con el foco en el nombre; y la línea de después, con las 24 h.
        $this->assertStringContainsString('#rsvp-nino"', $html);
        $this->assertStringContainsString('El recibo caduca a las 24 horas', $html);
        $this->assertStringContainsString('sin firma, la autorización se hace en la puerta', $html);
        // Su nombre, solo en el título del documento: la tarjeta es la del diseño.
        $this->assertStringContainsString('<title>Contamos con Hugo Ruiz', $html);
        $this->assertIdiomaAbajo($html, 'es', 'data-receipt-after');
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
        $this->assertIdiomaAbajo($html, 'en', 'data-invitation-privacy');
    }

    public function test_on_the_first_visit_the_invitation_speaks_the_language_of_the_browser(): void
    {
        ['invitation' => $invitation] = $this->mountParty();

        // `#748`: la invitación elige el idioma SOLA, como la web (`SetLocale`): el padre que abre el enlace con el
        // teléfono en francés la lee en francés sin tocar nada.
        $html = (string) $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.5'])
            ->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="fr"', $html);
        $this->assertStringContainsString('fête ses 8 ans', $html, 'la invitación, en francés');
        $this->assertIdiomaAbajo($html, 'fr', 'data-invitation-privacy');
    }

    /**
     * El idioma de la fiesta (`#748`, el owner): NADA en la cabecera y, abajo del todo —después de `$ultimo`—, una línea
     * de texto con los tres del sitio: el que se lee sin enlace y con `aria-current`, los otros enlaces a `lang.switch`.
     */
    private function assertIdiomaAbajo(string $html, string $actual, string $ultimo): void
    {
        // La cabecera es `inv-cab` > `inv-top`, sin otro `div` dentro: el primer `</div>` la cierra.
        $cabecera = strpos($html, 'class="inv-cab"');
        $this->assertNotFalse($cabecera);
        $fin = (int) strpos($html, '</div>', $cabecera);
        $this->assertStringContainsString('class="inv-top"', substr($html, $cabecera, $fin - $cabecera));
        $this->assertStringNotContainsString('/lang/', substr($html, $cabecera, $fin - $cabecera), 'el idioma volvió a la cabecera');
        $this->assertStringNotContainsString('<select', substr($html, $cabecera, $fin - $cabecera));

        $pie = strpos($html, 'data-idiomas');
        $this->assertNotFalse($pie, 'sin la línea del idioma');
        $antes = strpos($html, $ultimo);
        $this->assertNotFalse($antes);
        $this->assertGreaterThan($antes, $pie, 'la línea del idioma va ABAJO, después de '.$ultimo);
        $linea = substr($html, $pie, (int) strpos($html, '</nav>', $pie) - $pie);

        $nombres = ['es' => 'Español', 'en' => 'English', 'fr' => 'Français'];
        foreach ($nombres as $clave => $nombre) {
            if ($clave === $actual) {
                $this->assertStringContainsString('<span lang="'.$clave.'" aria-current="true">'.$nombre.'</span>', $linea, 'el que se lee, sin enlace');
                $this->assertStringNotContainsString('/lang/'.$clave.'"', $linea);
            } else {
                $this->assertStringContainsString('/lang/'.$clave.'" hreflang="'.$clave.'" lang="'.$clave.'">'.$nombre.'</a>', $linea, 'cada otro idioma, un enlace con su nombre');
            }
        }
    }

    public function test_without_a_park_video_there_is_no_pill_and_no_viewer(): void
    {
        ['invitation' => $invitation] = $this->mountParty();

        $html = (string) $this->get(route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-invitation-park', $html, 'sin vídeo en los ajustes no hay «Ver el parque»');
        $this->assertStringNotContainsString('<video', $html);
    }

    public function test_the_park_video_opens_from_the_pill_with_the_google_rating_and_lets_you_answer(): void
    {
        ['reservation' => $reservation, 'invitation' => $invitation] = $this->mountParty();
        Setting::query()->updateOrCreate(['key' => 'party.park_video'], ['value' => 'https://cdn.example.com/portada.mp4', 'group' => 'party']);
        Setting::query()->updateOrCreate(['key' => 'party.park_video_poster'], ['value' => 'videos/header_poster.jpg', 'group' => 'party']);
        Setting::flushMemo();
        app(CopiedRating::class)->put(4.9, 155, 'https://maps.example/parque', now());
        Setting::flushMemo();
        $url = route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]);

        $html = (string) $this->get($url)->assertOk()->getContent();

        // La píldora de la cabecera: la foto en el aro, el play, «Ver el parque» y la nota de Google en la misma píldora.
        $this->assertMatchesRegularExpression('#<button type="button" class="inv-historia"[^>]*data-visor-abrir[^>]*data-invitation-park>#', $html);
        $this->assertStringContainsString('<span class="inv-historia-aro"><img src="'.asset('videos/header_poster.jpg').'" alt="">', $html, 'la foto, servida por la instalación');
        $this->assertStringContainsString('4,9</span></button>', $html, 'la nota copiada de la ficha de Google (`#771`), con coma');
        // El visor: oculto hasta tocar, el vídeo sin descargar hasta entonces, y «Vamos» que lleva a contestar.
        $this->assertMatchesRegularExpression('#<div class="inv-visor" role="dialog" aria-modal="true" aria-label="[^"]+" hidden data-visor#', $html);
        $this->assertStringContainsString('<video data-visor-video src="https://cdn.example.com/portada.mp4" poster="'.asset('videos/header_poster.jpg').'" playsinline loop preload="none"', $html);
        $this->assertStringContainsString('href="#rsvp-nino"', $html);
        $this->assertStringContainsString('data-visor-accion', $html, '«Vamos», con las respuestas abiertas');
        $this->assertStringContainsString('★ 4,9 en Google · 155 reseñas', $html, 'la prueba, bajo el botón');

        // En el RECIBO la píldora sigue (es la cabecera del parque) pero no hay nada que contestar: sin «Vamos».
        $vuelta = $this->post(route('invitation.reply', ['token' => $invitation->token]), ['child_name' => 'Hugo Ruiz', 'attending' => '1']);
        $recibo = (string) $this->get((string) $vuelta->headers->get('Location'))->assertOk()->getContent();
        $this->assertStringContainsString('data-invitation-park>', $recibo);
        $this->assertStringNotContainsString('data-visor-accion', $recibo);
        $this->assertNotNull($reservation->fresh());
    }

    public function test_the_words_of_the_family_and_the_gift_hints_are_painted_when_the_host_wrote_them(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $url = route(PartyInvitations::PUBLIC_ROUTE, ['token' => $invitation->token]);

        // Sin dato, sin bloque (la tarjeta no pinta ni la burbuja ni la línea del regalo). Y la tarjeta pública NUNCA lleva
        // la maquinaria de la vista previa en vivo de la lista (F2): ni marcas ni nada oculto.
        $sin = (string) $this->get($url)->assertOk()->getContent();
        $this->assertStringNotContainsString('<blockquote', $sin);
        $this->assertStringNotContainsString(__('fiesta.invitacion.gifts').':', $sin);
        $this->assertStringNotContainsString('data-inv-vivo', $sin);
        $this->assertStringNotContainsString('data-inv-plantilla', $sin);

        $invitation->forceFill(['family_words' => 'Traed ganas de saltar', 'gift_hints' => 'Le encantan los libros de animales'])->save();

        $con = (string) $this->get($url)->assertOk()->getContent();
        $this->assertStringContainsString('Traed ganas de saltar</blockquote>', $con, 'la burbuja con las palabras (F1a)');
        $this->assertStringContainsString(__('fiesta.invitacion.gifts').': Le encantan los libros de animales', $con, 'la línea del regalo');
    }
}
