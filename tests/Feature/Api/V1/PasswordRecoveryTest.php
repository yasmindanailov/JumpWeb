<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PasswordRecovery;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 3c — `POST /api/v1/auth/password/forgot` y `password/reset`.
 *
 * Aquí la anti-enumeración es ESTRICTA, al contrario que en el alta: pedir el enlace responde igual
 * exista o no la cuenta, y un token inválido, uno caducado y un correo inexistente devuelven el
 * mismo error. No es incoherencia con el registro —allí la clienta decidió avisar porque hay
 * alguien intentando comprar—: es que aquí no se gana nada diciéndolo y sí se regala un oráculo.
 */
class PasswordRecoveryTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        RateLimiter::clear('forgot:127.0.0.1');
        RateLimiter::clear('reset-password|127.0.0.1');
    }

    private function customer(): User
    {
        return User::factory()->create(['email' => 'cliente@jumpweb.test']);
    }

    /** @param  array<string, mixed>  $payload */
    private function send(string $path, array $payload): TestResponse
    {
        return $this->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::ROOT.$path, $payload);
    }

    // ── Pedir el enlace ───────────────────────────────────────────────────────────────────────

    public function test_asking_for_the_link_sends_it_to_an_existing_account(): void
    {
        $user = $this->customer();

        $this->send('/auth/password/forgot', ['email' => $user->email])
            ->assertAccepted()
            ->assertValidRequest()
            ->assertValidResponse(202);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /** Y responde EXACTAMENTE lo mismo si la cuenta no existe: no hay nada que enumerar. */
    public function test_asking_for_the_link_looks_identical_for_an_unknown_account(): void
    {
        $known = $this->send('/auth/password/forgot', ['email' => 'cliente@jumpweb.test']);
        RateLimiter::clear('forgot:127.0.0.1');
        $unknown = $this->send('/auth/password/forgot', ['email' => 'no-existe@jumpweb.test']);

        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->getContent(), $unknown->getContent());
        Notification::assertNothingSent();
    }

    /**
     * **Un correo que falta o no lo es se rechaza con 422, no se «acepta» en silencio.**
     *
     * ⚠️ **Este caso nace de la auditoría de A8 y cierra un hueco REAL de la superficie que
     * sobrevive** (`specs/auth-en-cajon.md` §8). Medido por mutación: relajar la regla del
     * controlador a `sometimes` **no tumbaba ni un test de toda la suite** —los 96 del alcance
     * seguían en verde—. La validación equivalente sí estaba probada, pero en
     * `Auth\ForgotPasswordTest`, que conduce el **modal de Livewire** y se retira con él: el subject
     * de aquel caso es la validación del componente, no la del endpoint.
     *
     * ▶ Es exactamente el criterio de `CONVENCIONES §3.quater`: *si un dato viaja al cliente y su
     * único test conduce la superficie vieja, el contrato NO lo está fijando*. Aquí ni siquiera lo
     * conducía — sencillamente no había nadie.
     *
     * ⚠️ Sin la regla, un `email` ausente llegaría a `PasswordRecovery::requestLink()` como cadena
     * vacía y el endpoint respondería **202** — le diría «revisa tu correo» a quien no ha escrito
     * ninguno, que es la peor forma de no fallar.
     */
    public function test_asking_for_the_link_validates_the_email(): void
    {
        $this->send('/auth/password/forgot', [])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['email']]]);

        $this->send('/auth/password/forgot', ['email' => 'no-es-un-correo'])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['email']]]);

        Notification::assertNothingSent();
    }

    public function test_asking_for_the_link_is_rate_limited(): void
    {
        foreach (range(1, PasswordRecovery::MAX_LINK_REQUESTS) as $ignored) {
            $this->send('/auth/password/forgot', ['email' => 'cliente@jumpweb.test'])->assertAccepted();
        }

        $blocked = $this->send('/auth/password/forgot', ['email' => 'cliente@jumpweb.test'])
            ->assertStatus(429)
            ->assertValidResponse(429);

        $this->assertGreaterThan(0, $blocked->json('error.params.retry_after'));
    }

    // ── Fijar la contraseña ───────────────────────────────────────────────────────────────────

    public function test_a_valid_token_changes_the_password(): void
    {
        $user = $this->customer();
        $token = Password::createToken($user);

        $this->send('/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'un-secreto-muy-largo-2026',
            'password_confirmation' => 'un-secreto-muy-largo-2026',
        ])
            ->assertNoContent()
            ->assertValidRequest()
            ->assertValidResponse(204);

        $this->assertTrue(Hash::check('un-secreto-muy-largo-2026', $user->fresh()->password));
    }

    /**
     * El caso de uso central: la víctima que ha perdido el control de su cuenta. Al resetear, TODAS
     * sus credenciales anteriores dejan de valer —incluidos los tokens de API, desde el paso 3a—,
     * porque aquí no hay ninguna que preservar.
     */
    public function test_resetting_revokes_every_previous_credential(): void
    {
        $user = $this->customer();
        $user->createToken('movil');
        $user->createToken('tablet');
        $token = Password::createToken($user);

        $this->send('/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'otro-secreto-muy-largo-2026',
            'password_confirmation' => 'otro-secreto-muy-largo-2026',
        ])->assertNoContent();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    /**
     * Los tres motivos de fallo dan la MISMA respuesta: token inventado, token de otra cuenta y
     * correo inexistente. Si difirieran, el endpoint diría qué correos están registrados.
     */
    public function test_every_failure_looks_the_same(): void
    {
        $this->customer();
        $stranger = User::factory()->create(['email' => 'otro@jumpweb.test']);

        $responses = [];
        foreach ([
            ['token' => 'inventado', 'email' => 'cliente@jumpweb.test'],
            ['token' => Password::createToken($stranger), 'email' => 'cliente@jumpweb.test'],
            ['token' => 'inventado', 'email' => 'no-existe@jumpweb.test'],
        ] as $case) {
            $responses[] = $this->send('/auth/password/reset', $case + [
                'password' => 'un-secreto-muy-largo-2026',
                'password_confirmation' => 'un-secreto-muy-largo-2026',
            ])->assertStatus(422)->assertValidResponse(422)->getContent();
        }

        $this->assertCount(1, array_unique($responses), 'los tres fallos deben ser indistinguibles');
    }

    public function test_the_new_password_must_be_confirmed_and_strong(): void
    {
        $user = $this->customer();

        $this->send('/auth/password/reset', [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'corta',
            'password_confirmation' => 'otra',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['password']]]);
    }

    public function test_resetting_is_rate_limited(): void
    {
        $this->customer();

        foreach (range(1, PasswordRecovery::MAX_RESET_ATTEMPTS) as $ignored) {
            $this->send('/auth/password/reset', [
                'token' => 'inventado',
                'email' => 'cliente@jumpweb.test',
                'password' => 'un-secreto-muy-largo-2026',
                'password_confirmation' => 'un-secreto-muy-largo-2026',
            ])->assertStatus(422);
        }

        $this->send('/auth/password/reset', [
            'token' => 'inventado',
            'email' => 'cliente@jumpweb.test',
            'password' => 'un-secreto-muy-largo-2026',
            'password_confirmation' => 'un-secreto-muy-largo-2026',
        ])->assertStatus(429)->assertValidResponse(429);
    }
}
