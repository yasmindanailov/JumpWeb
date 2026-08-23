<?php

namespace Tests\Feature\Site;

use App\Domain\Platform\Models\Setting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #216 — Detalles transversales de la web: CTAs de contacto data-driven, «ver mis reservas» en el
 * sidebar de compra, CTA «Registro» externo (con fallback), footer (5 legales + contacto + registro)
 * y página 404.
 */
class Detalles216Test extends TestCase
{
    use RefreshDatabase;

    private function set(string $key, string $value, string $group = 'contact'): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }

    // ── (1) CTAs de contacto data-driven ────────────────────────────────────

    public function test_contact_shows_quick_ctas_when_data_present(): void
    {
        $this->set('contact.phone', '600 11 22 33');
        $this->set('contact.whatsapp', '34600112233');
        $this->set('address.maps_url', 'https://maps.google.com/?q=saltopark', 'contact');

        $res = $this->get('/contacto')->assertOk();
        $res->assertSee('tel:600112233', false);            // teléfono saneado a dígitos
        $res->assertSee('https://wa.me/34600112233', false); // WhatsApp
        $res->assertSee('https://maps.google.com/?q=saltopark', false); // ubicación
    }

    public function test_contact_hides_quick_ctas_when_no_data(): void
    {
        // Sin teléfono/whatsapp/maps → no aparece la fila de CTAs rápidos.
        $this->get('/contacto')->assertOk()->assertDontSee('wa.me', false);
    }

    // ── (3) «Ver mis reservas» en el sidebar de compra ──────────────────────

    // ⚠️⚠️ **Aquí vivía `test_sidecart_my_reservations_link_for_guest_and_auth`, y se RETIRÓ el
    // 2026-08-23** (`specs/account-context-vue.md` §4.9). Aseveraba sobre el HTML servido que el
    // «Mis reservas» del bloque de cuenta llevaba a entrar sin sesión y a los pedidos con ella; el
    // bloque lo pinta ahora Vue, así que ese marcado ya no viaja en la página.
    // ▶ **No se perdió su sujeto**: los dos botones de invitado los CUENTA
    // `AccountDoorWiringTest::test_a_guest_now_gets_wiring_because_signing_in_lives_in_the_drawer`,
    // el destino con sesión lo asevera el mismo fichero, y qué pinta cada cara,
    // `account/panel.test.js`. Se comprobó antes de borrar, que es lo que `CONVENCIONES §3.quater`
    // pide y lo que `DECISIONES #112` costó no hacer.

    // ── (6a) CTA «Registro» del header ──────────────────────────────────────

    public function test_register_cta_uses_external_url_when_configured(): void
    {
        $this->set('registration.url', 'https://registro.ejemplo.com/alta', 'registration');
        $this->set('registration.label.es', 'Registro', 'registration');

        $this->get('/')
            ->assertOk()
            ->assertSee('https://registro.ejemplo.com/alta', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_external_register_subtitle_is_hidden_when_empty(): void
    {
        // #retoque 2026-06-14: con la URL externa configurada pero el SUBTÍTULO vacío, NO se muestra
        // nada (antes caía a un fallback i18n «Para entrar al parque»).
        $this->set('registration.url', 'https://registro.ejemplo.com/alta', 'registration');
        $this->set('registration.label.es', 'Registro del Parque', 'registration');
        // (no fijamos registration.subtitle.es → queda vacío)

        $this->get('/')
            ->assertOk()
            ->assertSee('Registro del Parque')          // la etiqueta sí (tiene fallback propio)
            ->assertDontSee('Para entrar al parque')    // el subtítulo NO cae al fallback
            ->assertDontSee('cta-ghost__s', false);     // ni se renderiza el span del subtítulo
    }

    public function test_external_register_subtitle_is_shown_when_set(): void
    {
        $this->set('registration.url', 'https://registro.ejemplo.com/alta', 'registration');
        $this->set('registration.label.es', 'Registro del Parque', 'registration');
        $this->set('registration.subtitle.es', 'Rellena el waiver antes de venir', 'registration');

        $this->get('/')
            ->assertOk()
            ->assertSee('Rellena el waiver antes de venir')
            ->assertSee('cta-ghost__s', false);
    }

    /**
     * **Sin URL externa configurada, el CTA cae al alta INTERNA** — que es la mitad del `#216` que
     * este caso vigila: sin ella, quitar la URL del panel dejaría el botón sin destino.
     *
     * ⚠️ Ese destino era el modal de registro y desde el 2026-08-23 es la **zona de alta del cajón**
     * (`DECISIONES #122`). Cambia a dónde cae, no que caiga: por eso el caso se re-apunta en vez de
     * irse (`CONVENCIONES §3.quater`).
     */
    public function test_register_cta_falls_back_to_the_internal_signup_when_no_url(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee("openAccount(\$event, 'register')", false)
            ->assertSee('href="'.route('registro').'"', false);
    }

    // ── (7) Footer: 5 legales + contacto + registro ─────────────────────────

    public function test_footer_lists_all_legal_pages_plus_contact(): void
    {
        $res = $this->get('/')->assertOk();
        foreach (['legal.aviso-legal', 'legal.privacidad', 'legal.condiciones', 'legal.cookies', 'legal.waiver'] as $name) {
            $res->assertSee(route($name), false);
        }
        $res->assertSee(route('contacto'), false);
    }

    public function test_footer_shows_external_register_with_configured_title(): void
    {
        $this->set('registration.url', 'https://registro.ejemplo.com/alta', 'registration');
        $this->set('registration.label.es', 'Alta socios', 'registration');

        // El enlace del pie usa el título configurado en Ajustes (#216 pto.1), no un texto fijo.
        $this->get('/')->assertOk()
            ->assertSee('https://registro.ejemplo.com/alta', false)
            ->assertSee('Alta socios');
    }

    public function test_footer_hides_waiver_when_disabled(): void
    {
        // Por defecto (waiver ON) el pie incluye el enlace al waiver.
        $this->get('/')->assertOk()->assertSee(route('legal.waiver'), false);

        // Desactivado (#216 pto.3) → desaparece del pie, pero los OTROS legales se conservan.
        $this->set('puerta.waiver_check_enabled', '0', 'puerta');
        $res = $this->get('/')->assertOk()->assertDontSee(route('legal.waiver'), false);
        foreach (['legal.aviso-legal', 'legal.privacidad', 'legal.condiciones', 'legal.cookies'] as $name) {
            $res->assertSee(route($name), false);
        }
    }

    public function test_safe_external_url_blocks_dangerous_schemes(): void
    {
        $this->assertSame('https://x.com/a', AppServiceProvider::safeExternalUrl('https://x.com/a'));
        $this->assertSame('http://x.com', AppServiceProvider::safeExternalUrl('http://x.com'));
        $this->assertNull(AppServiceProvider::safeExternalUrl('javascript:alert(1)'));
        $this->assertNull(AppServiceProvider::safeExternalUrl('data:text/html,x'));
        $this->assertNull(AppServiceProvider::safeExternalUrl(''));
        $this->assertNull(AppServiceProvider::safeExternalUrl(null));
    }

    public function test_social_urls_are_sanitized_in_footer_render(): void
    {
        // Sistema 5 (auditoría Fase 1): instagram/tiktok pasan por `safeExternalUrl` en el composer
        // (paridad con maps/registration) → un esquema peligroso inyectado (p. ej. por BD directa) NO
        // llega al `href` del footer; cae a '#'. Una URL http(s) legítima sí se conserva.
        $this->set('contact.instagram', 'javascript:alert(document.cookie)', 'contact');
        $this->set('contact.tiktok', 'https://www.tiktok.com/@saltopark', 'contact');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringContainsString('https://www.tiktok.com/@saltopark', $html);
    }

    // ── (5) Página 404 ──────────────────────────────────────────────────────

    public function test_unknown_route_renders_branded_404(): void
    {
        $this->get('/esta-pagina-no-existe-'.uniqid())
            ->assertNotFound()
            ->assertSee(__('site.e404_title'));
    }
}
