<?php

namespace Tests\Feature;

use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4.5.0 — Cabeceras de seguridad (regla 9 de docs/SEGURIDAD.md, #54).
 * Comprueba que el middleware SecurityHeaders aplica a las respuestas web.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_home_sends_the_base_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), browsing-topics=()');
    }

    public function test_admin_panel_login_also_sends_the_base_security_headers(): void
    {
        // Auditoría Fase 1 (A6): el panel Filament define su PROPIO stack de middleware y NO heredaba
        // el grupo `web`, así que /admin/login (la superficie que gestiona PII/reembolsos/roles) iba SIN
        // cabeceras. Ahora SecurityHeaders está en el stack del panel → las emite igual que la web.
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Content-Security-Policy'), 'el panel debe llevar CSP');
    }

    public function test_content_security_policy_locks_framing_and_allows_real_origins(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);

        // Defensas clave: nada de framing ajeno, base al propio origen, sin objetos.
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);

        // `form-action`: 'self' + el origen Redsys del ENTORNO CONFIGURADO (#113 B2). En
        // entorno por defecto (test) debe permitir SOLO sandbox; el de producción se prueba
        // aparte (`test_form_action_switches_to_production_url_when_redsys_is_live`). Defensa
        // en profundidad: en producción con setting mal configurado a 'test', el navegador
        // rechaza el envío y alerta visible inmediata (en lugar de redirigir al sandbox real).
        $this->assertMatchesRegularExpression(
            "/form-action 'self' https:\/\/sis-t\.redsys\.es:25443[^a-z]/",
            $csp,
        );
        $this->assertStringNotContainsString('https://sis.redsys.es', $csp); // no incluir prod en sandbox
        $this->assertStringNotContainsString('form-action *', $csp);  // nunca wildcard

        // Orígenes externos realmente usados por el sitio.
        $this->assertStringContainsString('https://fonts.bunny.net', $csp);
        $this->assertStringContainsString('https://challenges.cloudflare.com', $csp);

        // En entorno de pruebas (no local) no debe colarse el dev-server de Vite. Se comprueba
        // contra el puerto REALMENTE configurado: aseverar un literal (`5173`) se cumpliría solo
        // desde el día que el puerto cambió, que es una aserción muerta disfrazada de verde.
        $this->assertStringNotContainsString('http://localhost:'.config('app.vite_dev_port'), $csp);
        $this->assertStringNotContainsString('ws://', $csp);
    }

    /**
     * En local SÍ se permite el dev-server, y con el puerto que dice la configuración.
     *
     * Estuvo quemado a 5173 mientras `.env` fijaba 5374 y `.env.example` 5274 — tres valores a la
     * vez—, así que la propia CSP bloqueaba el HMR sin que nada lo dijera. Este test es lo que
     * impide que vuelva a quemarse: si alguien escribe un literal, el puerto inventado de aquí
     * deja de aparecer.
     */
    public function test_the_csp_allows_the_configured_vite_dev_server_when_running_locally(): void
    {
        config(['app.vite_dev_port' => 5999]);
        $this->app['env'] = 'local';

        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);

        foreach (['localhost', '127.0.0.1'] as $host) {
            $this->assertStringContainsString("http://{$host}:5999", $csp, 'falta el origen del HMR');
            $this->assertStringContainsString("ws://{$host}:5999", $csp, 'falta el websocket del HMR');
        }

        $this->assertStringNotContainsString('5173', $csp, 'el puerto sigue quemado en alguna parte');
    }

    public function test_form_action_switches_to_production_url_when_redsys_is_live(): void
    {
        // Audit hardening #113 (B2): la CSP debe cambiar al origen de PRODUCCIÓN cuando
        // `redsys_environment=live`. Defensa adicional: si por error alguien configurara
        // sandbox en producción, el navegador rechazaría el form antes de enviarlo.
        Setting::updateOrCreate(['key' => 'redsys_environment'], ['value' => 'live', 'group' => 'payment']);

        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString('https://sis.redsys.es', $csp);
        // Sandbox NO debe aparecer en producción.
        $this->assertStringNotContainsString('sis-t.redsys.es', $csp);
    }

    public function test_headers_apply_to_other_web_routes(): void
    {
        // Una página legal (contenido desde la tabla `pages`) también recibe las cabeceras.
        $this->get('/privacidad')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
