<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Resolución del idioma activo (middleware SetLocale): elección de sesión > usuario
 * autenticado > auto-detección por `Accept-Language` del navegador > fallback config.
 *
 * Diseño: la elección manual del usuario (session('locale')) es sagrada — una vez
 * fijada, ni el auto-detect ni el perfil del usuario la pisan en peticiones siguientes.
 */
class LocaleDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_auto_detects_french_from_accept_language_on_first_visit(): void
    {
        $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.7'])
            ->get('/')
            ->assertOk();

        $this->assertSame('fr', session('locale'));
        $this->assertSame('fr', app()->getLocale());
    }

    public function test_auto_detects_english_when_french_is_not_preferred(): void
    {
        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/')
            ->assertOk();

        $this->assertSame('en', session('locale'));
    }

    public function test_falls_back_to_default_when_accept_language_has_no_supported_match(): void
    {
        // Idiomas no soportados (de/it). getPreferredLanguage devuelve el primero de la
        // lista pasada (es) como fallback de Symfony — comportamiento aceptado: queda en es.
        $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9,it;q=0.5'])
            ->get('/')
            ->assertOk();

        $this->assertContains(session('locale'), ['es', 'en', 'fr']);
    }

    public function test_manual_choice_in_session_is_never_overridden_by_browser(): void
    {
        // El cliente eligió EN manualmente. Su navegador pide FR. La elección manda.
        $this->withSession(['locale' => 'en'])
            ->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->get('/')
            ->assertOk();

        $this->assertSame('en', session('locale'));
        $this->assertSame('en', app()->getLocale());
    }

    public function test_authenticated_user_locale_wins_over_accept_language(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get('/')
            ->assertOk();

        $this->assertSame('fr', session('locale'));
    }

    public function test_lang_switch_route_overrides_previous_choice(): void
    {
        $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->get('/')->assertOk();
        $this->assertSame('fr', session('locale'));

        // Cambio manual: gana sobre auto-detect previo.
        $this->get(route('lang.switch', 'en'))->assertRedirect();
        $this->assertSame('en', session('locale'));
    }

    public function test_lang_switch_returns_to_a_same_host_referer(): void
    {
        // Auditoría Fase 1 · Sistema 6 · W7: un `Referer` del MISMO host es un destino legítimo.
        $this->get(route('lang.switch', 'en'), ['Referer' => url('/precios')])
            ->assertRedirect(url('/precios'));
    }

    public function test_lang_switch_ignores_an_external_referer_open_redirect(): void
    {
        // Auditoría Fase 1 · Sistema 6 · W7: `back()` priorizaba el `Referer` controlado por el cliente
        // sobre la sesión → open-redirect (phishing con lavado por el dominio de confianza). Ahora un
        // `Referer` de OTRO host se descarta y se redirige a la home, nunca al destino externo.
        $response = $this->get(route('lang.switch', 'en'), ['Referer' => 'https://evil.example/phish']);

        $response->assertStatus(302);
        $this->assertStringNotContainsString('evil.example', (string) $response->headers->get('Location'));
        $response->assertRedirect(url('/'));
    }
}
