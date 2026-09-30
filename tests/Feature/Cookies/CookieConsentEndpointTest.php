<?php

namespace Tests\Feature\Cookies;

use App\Domain\Identity\Models\CookieConsentLog;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Pixels;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Http\Legal\CookieInventory;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * #219 — Endpoint que registra la decisión de consentimiento: escribe la cookie canónica + deja
 * una fila de PRUEBA (acreditación, RGPD art. 5.2/7.1). Funciona para anónimos y autenticados.
 *
 * T3a de la analítica: la decisión lleva las categorías, todas obligatorias — un cliente que mande solo dos (el banner
 * de antes) no decide nada. Desde `#860`, las OFRECIDAS (`CookieInventory::offered()`: solo lo encendido): lo demás se
 * guarda en `false` diga lo que diga la petición, y la cookie anota lo preguntado.
 */
class CookieConsentEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_ON = ['maps' => true, 'social' => true, 'analytics' => true, 'marketing' => true];

    private const ALL_OFF = ['maps' => false, 'social' => false, 'analytics' => false, 'marketing' => false];

    /** El mapa y un píxel: se ofrece todo lo que hoy puede ofrecerse (`social` no, `#309`). */
    private function switchEverythingOn(): void
    {
        Setting::query()->updateOrCreate(['key' => 'address.maps_embed_url'], ['value' => 'https://www.google.com/maps/embed?pb=!1m18!1m12']);
        Setting::query()->updateOrCreate(['key' => Pixels::KEY_META_PIXEL_ID], ['value' => '1234567890123']);
    }

    private function cookieFrom(TestResponse $response): string
    {
        return $response->getCookie(CookieConsent::COOKIE_NAME, false)->getValue(); // sin descifrar
    }

    private function requestWith(string $cookie): Request
    {
        return Request::create('/', 'GET', [], [CookieConsent::COOKIE_NAME => $cookie]);
    }

    public function test_records_consent_and_sets_unencrypted_cookie_for_anonymous(): void
    {
        $this->switchEverythingOn();
        $decision = ['maps' => true, 'social' => false, 'analytics' => true, 'marketing' => false];
        $response = $this->postJson('/cookies/consentimiento', $decision);

        $response->assertOk()->assertJson(['ok' => true]);
        $response->assertCookie(CookieConsent::COOKIE_NAME);

        $this->assertDatabaseHas('cookie_consent_logs', [
            'user_id' => null,
            'version' => CookieConsent::POLICY_VERSION,
        ]);

        $log = CookieConsentLog::firstOrFail();
        $this->assertSame($decision, $log->categories);
        $this->assertNotNull($log->accepted_at);

        // T5a (`#737`): la web acuña la cookie del visitante ANTES de atender la petición, así que la PRUEBA de
        // quien decide sin cookie lleva ya el id que su misma respuesta le pone — y la decisión se puede releer
        // esa noche por `ConsentLedger`. Hasta la T5a esta prueba iba sin visitante.
        $minted = collect($response->headers->getCookies())->first(fn ($c): bool => $c->getName() === Visitor::COOKIE);
        $this->assertNotNull($minted, 'la respuesta acuña la cookie del visitante');
        $this->assertSame($minted->getValue(), $log->visitor_id, 'la prueba lleva el visitante que la misma respuesta acuña');
    }

    /**
     * T3b·2: la prueba lleva al VISITANTE del libro de eventos cuando la petición trae su cookie, y es lo que
     * permite releer su decisión viva desde la cola; una cookie con otra forma no vale.
     */
    public function test_the_log_carries_the_visitor_of_the_request(): void
    {
        $visitor = Visitor::mint();
        $decision = ['maps' => false, 'social' => false, 'analytics' => false, 'marketing' => true];

        // ⚠️ `postJson` no manda cookies sin `withCredentials()` (la trampa de la T1 y de la T3a·3, otra vez).
        $this->withCredentials()->withUnencryptedCookie(Visitor::COOKIE, $visitor)->postJson('/cookies/consentimiento', $decision)->assertOk();
        $this->assertSame($visitor, CookieConsentLog::latest('id')->firstOrFail()->visitor_id);

        $this->withCredentials()->withUnencryptedCookie(Visitor::COOKIE, 'no-es-un-ulid')->postJson('/cookies/consentimiento', $decision)->assertOk();
        // T5a (`#737`): una cookie que no es un ULID vale como ninguna, y la web acuña una NUEVA antes de atender la
        // petición: la prueba lleva ese id recién acuñado (válido), nunca la basura que llegó.
        $reminted = CookieConsentLog::latest('id')->firstOrFail()->visitor_id;
        $this->assertTrue(Visitor::isValid($reminted), 'la prueba lleva el visitante recién acuñado, no la basura');
        $this->assertNotSame($visitor, $reminted);
    }

    public function test_cookie_value_round_trips_to_state(): void
    {
        $this->switchEverythingOn();
        $value = $this->cookieFrom($this->postJson('/cookies/consentimiento', self::ALL_ON));
        $state = CookieConsent::state($this->requestWith($value));

        $this->assertTrue($state['decided']);
        foreach (CookieInventory::offered() as $category) {
            $this->assertTrue($state[$category], $category);
        }
        $this->assertFalse($state['social'], 'lo que no se ofrece no se acepta');
        $this->assertSame(['maps', 'analytics', 'marketing'], CookieConsent::asked($this->requestWith($value)));
    }

    /** `#860`: nadie acepta lo que no se le preguntó — aunque la petición diga «sí» a todo. */
    public function test_what_was_not_offered_is_stored_as_refused_whatever_the_request_says(): void
    {
        $value = $this->cookieFrom($this->postJson('/cookies/consentimiento', self::ALL_ON)->assertOk());

        $this->assertSame(['maps' => false, 'social' => false, 'analytics' => true, 'marketing' => false], CookieConsentLog::firstOrFail()->categories);
        $this->assertSame(['analytics'], CookieConsent::asked($this->requestWith($value)));
        $this->assertFalse(CookieConsent::state($this->requestWith($value))['marketing']);
    }

    /** `#860`: sin mapa ni píxeles, decidir es contestar lo único que se pregunta. */
    public function test_a_bare_installation_decides_with_the_analytics_answer_alone(): void
    {
        $this->postJson('/cookies/consentimiento', ['analytics' => false])->assertOk();
        $this->postJson('/cookies/consentimiento', [])->assertStatus(422)->assertJsonValidationErrors(['analytics']);
    }

    /**
     * `#860`: encender algo DESPUÉS es una pregunta nueva — el aviso vuelve, con lo ya contestado marcado, y la nueva
     * sigue apagada hasta contestarla. Sin subir `POLICY_VERSION`.
     */
    public function test_a_category_switched_on_later_asks_again_keeping_what_was_answered(): void
    {
        $this->seed(LandingContentSeeder::class);
        $before = $this->cookieFrom($this->postJson('/cookies/consentimiento', ['analytics' => true])->assertOk());

        $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, $before)->get('/')->assertOk()
            ->assertSee('data-cookie-decided="1"', false);

        Setting::query()->updateOrCreate(['key' => Pixels::KEY_META_PIXEL_ID], ['value' => '1234567890123']);

        $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, $before)->get('/')->assertOk()
            ->assertSee('data-cookie-decided=""', false)
            ->assertSee('data-consent-categories="analytics,marketing"', false)
            ->assertSee('data-cookie-analytics="1"', false)
            ->assertSee('data-cookie-marketing=""', false);

        $after = $this->cookieFrom($this->postJson('/cookies/consentimiento', ['analytics' => true, 'marketing' => false])->assertOk());
        $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, $after)->get('/')->assertOk()
            ->assertSee('data-cookie-decided="1"', false);
    }

    /** Una decisión de esta versión sin la lista es de antes de `#860`: entonces se preguntaban siempre las cuatro. */
    public function test_a_decision_from_before_860_counts_as_having_asked_all_four(): void
    {
        $legacy = base64_encode((string) json_encode(['v' => CookieConsent::POLICY_VERSION, 'cats' => self::ALL_OFF]));

        $this->assertSame(CookieConsent::OPTIONAL, CookieConsent::asked($this->requestWith($legacy)));
        $this->assertTrue(CookieConsent::state($this->requestWith($legacy))['decided']);
        $this->assertSame([], CookieConsent::asked($this->requestWith('no-es-base64-json')));
    }

    public function test_records_user_id_when_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/cookies/consentimiento', ['maps' => false, 'social' => true, 'analytics' => false, 'marketing' => false])
            ->assertOk();

        $this->assertDatabaseHas('cookie_consent_logs', ['user_id' => $user->id]);
    }

    public function test_rejection_is_also_recorded(): void
    {
        $this->postJson('/cookies/consentimiento', self::ALL_OFF)->assertOk();

        $log = CookieConsentLog::firstOrFail();
        $this->assertSame(self::ALL_OFF, $log->categories);
    }

    public function test_validation_requires_a_boolean_per_category(): void
    {
        $this->switchEverythingOn();
        $this->postJson('/cookies/consentimiento', ['maps' => 'yes'])->assertStatus(422);
        $this->postJson('/cookies/consentimiento', [])->assertStatus(422);

        // El banner de la v2 mandaba dos: sin las otras dos no hay decisión (ni «no», ni «sí»).
        $this->postJson('/cookies/consentimiento', ['maps' => true, 'social' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['analytics', 'marketing']);

        $this->assertDatabaseCount('cookie_consent_logs', 0);
    }

    /** De punta a punta (spec §4.3): POST → cookie → `state()` → los `data-*` del `<body>` que lee el almacén. */
    public function test_the_decision_reaches_the_body_attributes_of_the_next_page(): void
    {
        $this->seed(LandingContentSeeder::class);
        $this->switchEverythingOn();

        $value = $this->postJson('/cookies/consentimiento', ['maps' => false, 'social' => false, 'analytics' => true, 'marketing' => false])
            ->assertOk()
            ->getCookie(CookieConsent::COOKIE_NAME, false)
            ->getValue();

        $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, $value)->get('/')->assertOk()
            ->assertSee('data-cookie-decided="1"', false)
            ->assertSee('data-cookie-analytics="1"', false)
            ->assertSee('data-cookie-marketing=""', false)
            ->assertSee('data-cookie-maps=""', false)
            ->assertSee('data-consent-categories="maps,analytics,marketing"', false);
    }

    public function test_old_logs_are_prunable(): void
    {
        $base = ['user_id' => null, 'categories' => self::ALL_OFF, 'version' => CookieConsent::POLICY_VERSION];
        $old = CookieConsentLog::create($base + ['accepted_at' => now()->subMonths(25)]);
        $recent = CookieConsentLog::create($base + ['accepted_at' => now()->subDays(3)]);

        $this->artisan('model:prune', ['--model' => [CookieConsentLog::class]])->assertExitCode(0);

        $this->assertDatabaseMissing('cookie_consent_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('cookie_consent_logs', ['id' => $recent->id]);
    }
}
