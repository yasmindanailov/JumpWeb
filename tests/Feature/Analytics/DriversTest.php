<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Drivers;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **La herramienta de análisis, por instalación** (`specs/analitica.md` §4.3, T3a·2): el driver es un ajuste
 * que, incompleto, es «ninguno»; sus orígenes entran en la CSP solo con él activo; el `<body>` publica sus
 * datos solo con él, y la persona opaca solo con sesión Y la categoría `analytics`; `/cookies` lo nombra al
 * pintar; y el id de una persona es un HMAC, nunca el id.
 */
class DriversTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'phc_abcdefghijklmnopqrstuvwxyz0123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    private function setting(string $key, ?string $value): void
    {
        if ($value === null) {
            Setting::where('key', $key)->delete();
        } else {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'analytics']);
        }
        Setting::flushMemo();
    }

    private function posthog(): void
    {
        $this->setting(Drivers::KEY_DRIVER, Drivers::POSTHOG);
        $this->setting(Drivers::KEY_POSTHOG_PROJECT, self::TOKEN);
    }

    private function consent(bool $analytics): static
    {
        return $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, CookieConsent::encode(['analytics' => $analytics]));
    }

    // ─── El ajuste ──────────────────────────────────────────────────────────────────────────────

    public function test_without_a_driver_there_is_nothing_and_an_incomplete_one_is_none(): void
    {
        $this->assertSame(Drivers::NONE, Drivers::active());
        $this->assertNull(Drivers::config());
        $this->assertSame([], Drivers::csp());
        $this->assertNull(Drivers::name());

        $this->setting(Drivers::KEY_DRIVER, Drivers::POSTHOG);   // sin token
        $this->assertSame(Drivers::NONE, Drivers::active());
        $this->setting(Drivers::KEY_POSTHOG_PROJECT, 'no-es-un-token');
        $this->assertSame(Drivers::NONE, Drivers::active());

        $this->setting(Drivers::KEY_DRIVER, Drivers::MATOMO);   // sin host ni sitio
        $this->assertSame(Drivers::NONE, Drivers::active());
        $this->setting(Drivers::KEY_MATOMO_HOST, 'https://stats.parque.es');
        $this->assertSame(Drivers::NONE, Drivers::active(), 'sin id de sitio, nada');

        $this->setting(Drivers::KEY_DRIVER, 'inventado');
        $this->assertSame(Drivers::NONE, Drivers::active());
    }

    public function test_posthog_complete_is_active_with_its_eu_host_and_its_origins(): void
    {
        $this->posthog();

        $this->assertSame(Drivers::POSTHOG, Drivers::active());
        $this->assertSame(['driver' => 'posthog', 'key' => self::TOKEN, 'host' => 'https://eu.i.posthog.com'], Drivers::config());
        $this->assertSame('PostHog', Drivers::name());
        $this->assertSame(
            ['script-src' => ['https://*.posthog.com'], 'connect-src' => ['https://*.posthog.com'], 'img-src' => ['https://*.posthog.com']],
            Drivers::csp(),
        );
    }

    public function test_the_matomo_host_is_rebuilt_as_an_origin_and_anything_else_is_refused(): void
    {
        $this->assertSame('https://stats.parque.es', Drivers::matomoHost('https://stats.parque.es'));
        $this->assertSame('https://stats.parque.es', Drivers::matomoHost(' https://Stats.Parque.es/ '));
        $this->assertSame('https://stats.parque.es', Drivers::matomoHost('https://stats.parque.es/matomo/index.php?x=1'), 'la ruta y la query no viajan a la CSP');
        $this->assertSame('https://stats.parque.es:8443', Drivers::matomoHost('https://stats.parque.es:8443'));

        foreach (['http://stats.parque.es', 'stats.parque.es', 'https://user:pw@stats.parque.es', 'https://', 'https://localhost', "https://stats.parque.es'; script-src *", '', null, 12] as $bad) {
            $this->assertNull(Drivers::matomoHost($bad), var_export($bad, true));
        }

        $this->setting(Drivers::KEY_DRIVER, Drivers::MATOMO);
        $this->setting(Drivers::KEY_MATOMO_HOST, 'https://stats.parque.es/');
        $this->setting(Drivers::KEY_MATOMO_SITE_ID, '7');
        $this->assertSame(['driver' => 'matomo', 'key' => '7', 'host' => 'https://stats.parque.es'], Drivers::config());
        $this->assertSame(['https://stats.parque.es'], Drivers::csp()['connect-src']);
        $this->assertSame('Matomo', Drivers::name());

        $this->assertNull(Drivers::matomoSiteId('0'));
        $this->assertNull(Drivers::matomoSiteId('7a'));
        $this->assertSame('12', Drivers::matomoSiteId(12));
    }

    public function test_the_person_id_is_an_hmac_stable_per_user_and_never_the_id(): void
    {
        $a = Drivers::personId(7);
        $b = Drivers::personId(8);

        $this->assertSame($a, Drivers::personId(7));
        $this->assertNotSame($a, $b);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a);
        $this->assertStringNotContainsString('7', substr($a, 0, 0).'', 'vacío a propósito: el id no está en claro (se prueba por forma)');
        $this->assertSame(hash_hmac('sha256', '7', (string) config('app.key')), $a);
    }

    // ─── La CSP y el body ──────────────────────────────────────────────────────────────────────

    public function test_the_csp_opens_the_driver_origins_only_with_a_driver(): void
    {
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('posthog', $csp);

        $this->posthog();
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');
        foreach (['script-src', 'connect-src', 'img-src'] as $directive) {
            $this->assertMatchesRegularExpression('/'.$directive.' [^;]*https:\/\/\*\.posthog\.com/', $csp, $directive);
        }
        $this->assertDoesNotMatchRegularExpression('/frame-src [^;]*posthog/', $csp, 'ni un frame');
        $this->assertDoesNotMatchRegularExpression('/style-src [^;]*posthog/', $csp);
    }

    /**
     * ⚠️ Dos casos y no uno, a propósito: en un test, la sesión de `actingAs` Y la cookie de
     * `withUnencryptedCookie` se QUEDAN para las peticiones siguientes, así que «anónimo» después de
     * `actingAs` no lo es y «sin categoría» después de consentir tampoco. Cada caso va del menos al más.
     */
    public function test_the_body_carries_the_driver_only_when_active_and_an_anonymous_visitor_is_never_a_person(): void
    {
        $this->get('/')->assertOk()->assertDontSee('data-analytics-driver', false);

        $this->posthog();

        // Anónimo, sin consentir: el driver viaja (el cargador decide por la categoría), la persona no.
        $this->get('/')->assertOk()
            ->assertSee('data-analytics-driver="posthog"', false)
            ->assertSee('data-analytics-key="'.self::TOKEN.'"', false)
            ->assertSee('data-analytics-host="https://eu.i.posthog.com"', false)
            ->assertDontSee('data-analytics-person', false);

        // Anónimo CON la categoría: ninguna persona (no hay a quién identificar).
        $this->consent(true)->get('/')->assertOk()->assertDontSee('data-analytics-person', false);
    }

    public function test_the_person_needs_the_session_and_the_category_and_is_the_opaque_id(): void
    {
        $this->posthog();
        $user = User::factory()->create();

        // Con sesión pero SIN la categoría: ninguna persona.
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('data-analytics-driver="posthog"', false)
            ->assertDontSee('data-analytics-person', false);

        // Sesión Y categoría: la persona opaca, y no el id.
        $this->actingAs($user)->consent(true)->get('/')->assertOk()
            ->assertSee('data-analytics-person="'.Drivers::personId((int) $user->id).'"', false)
            ->assertDontSee('data-analytics-person="'.$user->id.'"', false);
    }

    public function test_the_cookie_policy_names_the_active_tool_at_render_time(): void
    {
        $this->withSession(['locale' => 'es'])->get('/cookies')->assertOk()->assertDontSee('data-analytics-tool', false);

        $this->posthog();
        $this->withSession(['locale' => 'es'])->get('/cookies')->assertOk()
            ->assertSee('data-analytics-tool="posthog"', false)
            ->assertSee('PostHog')
            ->assertSee('Unión Europea');
        $this->withSession(['locale' => 'en'])->get('/cookies')->assertOk()->assertSee('Usage analytics tool active on this site');
        $this->withSession(['locale' => 'fr'])->get('/cookies')->assertOk()->assertSee('Outil d\'analyse d\'usage actif');

        $this->setting(Drivers::KEY_DRIVER, Drivers::MATOMO);
        $this->setting(Drivers::KEY_MATOMO_HOST, 'https://stats.parque.es');
        $this->setting(Drivers::KEY_MATOMO_SITE_ID, '3');
        $this->withSession(['locale' => 'es'])->get('/cookies')->assertOk()->assertSee('Matomo (instalación propia en https://stats.parque.es)');

        // El texto guardado no nombra a nadie: la página de privacidad no cambia.
        $this->withSession(['locale' => 'es'])->get('/privacidad')->assertOk()->assertDontSee('data-analytics-tool', false);
    }
}
