<?php

namespace Tests\Feature\Cookies;

use App\Domain\Identity\Models\CookieConsentLog;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Services\Analytics\Visitor;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * #219 — Endpoint que registra la decisión de consentimiento: escribe la cookie canónica + deja
 * una fila de PRUEBA (acreditación, RGPD art. 5.2/7.1). Funciona para anónimos y autenticados.
 *
 * T3a de la analítica: la decisión lleva las CUATRO categorías de `CookieConsent::OPTIONAL`, todas
 * obligatorias — un cliente que mande solo dos (el banner de antes) no decide nada.
 */
class CookieConsentEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_ON = ['maps' => true, 'social' => true, 'analytics' => true, 'marketing' => true];

    private const ALL_OFF = ['maps' => false, 'social' => false, 'analytics' => false, 'marketing' => false];

    public function test_records_consent_and_sets_unencrypted_cookie_for_anonymous(): void
    {
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
        $this->assertNull($log->visitor_id, 'sin cookie del visitante, la prueba no lo lleva');
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
        $this->assertNull(CookieConsentLog::latest('id')->firstOrFail()->visitor_id);
    }

    public function test_cookie_value_round_trips_to_state(): void
    {
        $response = $this->postJson('/cookies/consentimiento', self::ALL_ON);

        $value = $response->getCookie(CookieConsent::COOKIE_NAME, false)->getValue(); // sin descifrar
        $state = CookieConsent::state(Request::create('/', 'GET', [], [CookieConsent::COOKIE_NAME => $value]));

        $this->assertTrue($state['decided']);
        foreach (CookieConsent::OPTIONAL as $category) {
            $this->assertTrue($state[$category], $category);
        }
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

        $value = $this->postJson('/cookies/consentimiento', ['maps' => false, 'social' => false, 'analytics' => true, 'marketing' => false])
            ->assertOk()
            ->getCookie(CookieConsent::COOKIE_NAME, false)
            ->getValue();

        $this->withUnencryptedCookie(CookieConsent::COOKIE_NAME, $value)->get('/')->assertOk()
            ->assertSee('data-cookie-decided="1"', false)
            ->assertSee('data-cookie-analytics="1"', false)
            ->assertSee('data-cookie-marketing=""', false)
            ->assertSee('data-cookie-maps=""', false)
            ->assertSee('data-consent-categories="maps,social,analytics,marketing"', false);
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
