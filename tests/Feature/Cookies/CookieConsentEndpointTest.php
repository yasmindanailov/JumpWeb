<?php

namespace Tests\Feature\Cookies;

use App\Domain\Identity\Models\CookieConsentLog;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CookieConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * #219 — Endpoint que registra la decisión de consentimiento: escribe la cookie canónica + deja
 * una fila de PRUEBA (acreditación, RGPD art. 5.2/7.1). Funciona para anónimos y autenticados.
 */
class CookieConsentEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_consent_and_sets_unencrypted_cookie_for_anonymous(): void
    {
        $response = $this->postJson('/cookies/consentimiento', ['maps' => true, 'social' => false]);

        $response->assertOk()->assertJson(['ok' => true]);
        $response->assertCookie(CookieConsent::COOKIE_NAME);

        $this->assertDatabaseHas('cookie_consent_logs', [
            'user_id' => null,
            'version' => CookieConsent::POLICY_VERSION,
        ]);

        $log = CookieConsentLog::firstOrFail();
        $this->assertSame(['maps' => true, 'social' => false], $log->categories);
        $this->assertNotNull($log->accepted_at);
    }

    public function test_cookie_value_round_trips_to_state(): void
    {
        $response = $this->postJson('/cookies/consentimiento', ['maps' => true, 'social' => true]);

        $value = $response->getCookie(CookieConsent::COOKIE_NAME, false)->getValue(); // sin descifrar
        $state = CookieConsent::state(Request::create('/', 'GET', [], [CookieConsent::COOKIE_NAME => $value]));

        $this->assertTrue($state['decided']);
        $this->assertTrue($state['maps']);
        $this->assertTrue($state['social']);
    }

    public function test_records_user_id_when_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/cookies/consentimiento', ['maps' => false, 'social' => true])
            ->assertOk();

        $this->assertDatabaseHas('cookie_consent_logs', ['user_id' => $user->id]);
    }

    public function test_rejection_is_also_recorded(): void
    {
        $this->postJson('/cookies/consentimiento', ['maps' => false, 'social' => false])->assertOk();

        $log = CookieConsentLog::firstOrFail();
        $this->assertSame(['maps' => false, 'social' => false], $log->categories);
    }

    public function test_validation_requires_booleans(): void
    {
        $this->postJson('/cookies/consentimiento', ['maps' => 'yes'])->assertStatus(422);
        $this->postJson('/cookies/consentimiento', [])->assertStatus(422);
    }

    public function test_old_logs_are_prunable(): void
    {
        $base = ['user_id' => null, 'categories' => ['maps' => true, 'social' => false], 'version' => CookieConsent::POLICY_VERSION];
        $old = CookieConsentLog::create($base + ['accepted_at' => now()->subMonths(25)]);
        $recent = CookieConsentLog::create($base + ['accepted_at' => now()->subDays(3)]);

        $this->artisan('model:prune', ['--model' => [CookieConsentLog::class]])->assertExitCode(0);

        $this->assertDatabaseMissing('cookie_consent_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('cookie_consent_logs', ['id' => $recent->id]);
    }
}
