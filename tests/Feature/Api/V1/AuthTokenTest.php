<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\ApiTokenIssuer;
use App\Domain\Identity\Services\PasswordLogin;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\Api\ApiTestCase;

/**
 * F4 del programa — `POST /api/v1/auth/tokens` y `auth/tokens/rotate` (`docs/specs/token-bearer.md`,
 * `DECISIONES #630`).
 *
 * Lo que se comprueba aquí es lo que un token AÑADE al sistema, no lo que ya estaba: la revocación
 * por las cinco vías es de `ApiTokenRevocationTest` y los limitadores en sí de `PasswordLoginTest`.
 * Aquí: que la puerta nueva **comparte cubos** con el login (no es una segunda oportunidad para un
 * atacante), que un token nace acotado (una ability, caducidad propia, tope por cuenta), que no
 * abre lo que no debe (el panel) y que rota sin dejar vivo al anterior.
 *
 * ⚠️ **Los tokens de estos tests son REALES y viajan en la cabecera**, no `Sanctum::actingAs()`:
 * ese helper monta un token de mentira y no ejercita ni el hash, ni la caducidad, ni la revocación.
 * Por eso {@see asBearer()} olvida los guards antes de cada petición: dentro de un mismo test la
 * aplicación es la misma y el guard CACHEA al usuario, así que sin eso un token ya revocado
 * seguiría «entrando» y el test mediría la caché, no la credencial.
 */
class AuthTokenTest extends ApiTestCase
{
    private function customer(string $email = 'cliente@jumpweb.test'): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    }

    /** @param array<string, mixed> $overrides */
    private function issue(User $user, array $overrides = []): TestResponse
    {
        Auth::forgetGuards();

        return $this->postJson(self::ROOT.'/auth/tokens', $overrides + [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'iPhone de prueba',
        ]);
    }

    private function asBearer(string $token): static
    {
        Auth::forgetGuards();

        return $this->withToken($token);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('login-ip|127.0.0.1');
        parent::tearDown();
    }

    public function test_valid_credentials_issue_a_bounded_token_without_opening_a_session(): void
    {
        $user = $this->customer();

        $response = $this->issue($user)
            ->assertCreated()
            ->assertValidRequest()
            ->assertValidResponse(201)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', $user->email);

        // Transporta una credencial: nada la guarda por el camino (`RGPD-04`).
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // Ninguna sesión: `verify()` no es `attempt()`.
        $this->assertGuest('web');

        $token = PersonalAccessToken::query()->sole();
        $this->assertSame([ApiTokenIssuer::ABILITY], $token->abilities, 'Un token de cliente nace con UNA ability, nunca con el comodín.');
        $this->assertSame('iPhone de prueba', $token->name);
        $this->assertNotNull($token->expires_at, 'Un token SIEMPRE caduca.');
        $this->assertEqualsWithDelta(now()->addMinutes((int) config('sanctum.expiration'))->timestamp, $token->expires_at->timestamp, 5);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->asBearer($response->json('token'))
            ->getJson(self::ROOT.'/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_a_token_still_expires_if_the_configured_lifetime_is_emptied(): void
    {
        config(['sanctum.expiration' => null]);

        $this->issue($this->customer())->assertCreated();

        $expiresAt = PersonalAccessToken::query()->sole()->expires_at;

        $this->assertNotNull($expiresAt);
        // Y caduca en el FUTURO: con la configuración vacía, `addMinutes(0)` daría un token que nace muerto.
        $this->assertTrue($expiresAt->isAfter(now()->addDay()), 'Sin configuración, la caducidad de reserva son 30 días, no cero.');
    }

    public function test_an_unknown_email_and_a_wrong_password_are_indistinguishable(): void
    {
        $user = $this->customer();

        $wrongPassword = $this->issue($user, ['password' => 'no-es-esta'])->assertUnauthorized()->assertValidResponse(401);
        $unknownEmail = $this->issue($user, ['email' => 'nadie@jumpweb.test'])->assertUnauthorized();

        $this->assertSame($wrongPassword->getContent(), $unknownEmail->getContent(), '`SEC-06`: la respuesta no puede decir si el correo existe.');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    /**
     * ⚠️⚠️ **El caso por el que existe `PasswordLogin::verify()`**: si la emisión de tokens llevara
     * sus propios cubos, un atacante tendría el DOBLE de intentos —cinco por el login y cinco por
     * aquí— contra la misma cuenta.
     */
    public function test_failures_at_the_login_door_lock_the_token_door(): void
    {
        $user = $this->customer();

        for ($i = 0; $i < PasswordLogin::MAX_ATTEMPTS; $i++) {
            $this->withHeader('Origin', (string) config('app.url'))
                ->postJson(self::ROOT.'/auth/login', ['email' => $user->email, 'password' => 'mala'])
                ->assertUnauthorized();
        }

        $this->issue($user)
            ->assertStatus(429)
            ->assertValidResponse(429)
            ->assertHeader('Retry-After');

        $this->assertSame(0, PersonalAccessToken::query()->count(), 'Bloqueada, la puerta no emite ni con la contraseña buena.');
    }

    public function test_failures_at_the_token_door_lock_the_login_door(): void
    {
        $user = $this->customer();

        for ($i = 0; $i < PasswordLogin::MAX_ATTEMPTS; $i++) {
            $this->issue($user, ['password' => 'mala'])->assertUnauthorized();
        }

        $this->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::ROOT.'/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429);
    }

    /** El segundo limitador de `SEC-06`: muchas cuentas desde una IP. Sin él, el barrido pasa entero. */
    public function test_the_ip_only_limiter_also_guards_the_token_door(): void
    {
        $user = $this->customer();

        for ($i = 0; $i < PasswordLogin::MAX_ATTEMPTS_PER_IP; $i++) {
            $this->issue($user, ['email' => "barrido{$i}@jumpweb.test"])->assertUnauthorized();
        }

        $this->issue($user)->assertStatus(429);
    }

    public function test_the_device_name_is_required_and_bounded(): void
    {
        $user = $this->customer();

        $this->issue($user, ['device_name' => ''])->assertStatus(422)->assertValidResponse(422);
        $this->issue($user, ['device_name' => str_repeat('x', 61)])->assertStatus(422);
        $this->issue($user, ['device_name' => str_repeat('x', 60)])->assertCreated();
    }

    public function test_the_eleventh_token_retires_the_most_forgotten_one(): void
    {
        $user = $this->customer();

        foreach (range(1, ApiTokenIssuer::MAX_TOKENS) as $i) {
            $token = $user->createToken("dispositivo-{$i}", [ApiTokenIssuer::ABILITY])->accessToken;
            // El 3 es el OLVIDADO (nadie lo usa desde hace un año), aunque no sea el más antiguo.
            $token->forceFill(['last_used_at' => $i === 3 ? now()->subYear() : now()->subMinutes($i)])->save();
        }

        $this->issue($user, ['device_name' => 'el-nuevo'])->assertCreated();

        $names = $user->tokens()->pluck('name');
        $this->assertCount(ApiTokenIssuer::MAX_TOKENS, $names);
        $this->assertNotContains('dispositivo-3', $names, 'Se retira el más olvidado.');
        $this->assertContains('dispositivo-1', $names);
        $this->assertContains('el-nuevo', $names, 'El recién emitido sobrevive siempre.');
    }

    public function test_an_expired_token_no_longer_authenticates(): void
    {
        $token = $this->issue($this->customer())->json('token');

        $this->asBearer($token)->getJson(self::ROOT.'/me')->assertOk();

        $this->travel((int) config('sanctum.expiration') + 1)->minutes();

        $this->asBearer($token)->getJson(self::ROOT.'/me')->assertUnauthorized();
    }

    public function test_rotating_issues_a_new_token_and_kills_the_one_that_asked(): void
    {
        $user = $this->customer();
        $old = $this->issue($user)->json('token');

        $new = $this->asBearer($old)
            ->postJson(self::ROOT.'/auth/tokens/rotate')
            ->assertCreated()
            ->assertValidResponse(201)
            ->assertJsonPath('user.id', $user->id)
            ->json('token');

        $this->assertNotSame($old, $new);
        $this->assertSame(['iPhone de prueba'], $user->tokens()->pluck('name')->all(), 'Rota: no acumula, y conserva el nombre del dispositivo.');
        $this->assertSame([ApiTokenIssuer::ABILITY], $user->tokens()->sole()->abilities);

        $this->asBearer($old)->getJson(self::ROOT.'/me')->assertUnauthorized();
        $this->asBearer($new)->getJson(self::ROOT.'/me')->assertOk();
    }

    public function test_a_cookie_session_has_no_token_to_rotate(): void
    {
        $user = $this->customer();

        $this->actingAs($user)
            ->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::ROOT.'/auth/tokens/rotate')
            ->assertStatus(400)
            ->assertValidResponse(400);

        $this->assertSame(0, $user->tokens()->count());
    }

    /**
     * La ability es una guarda de verdad solo si la superficie la EXIGE: un token emitido para otra
     * cosa no abre la cuenta de nadie, y la cookie de la SPA —que no lleva abilities— sigue entrando.
     */
    public function test_the_authenticated_surface_demands_the_client_ability(): void
    {
        $user = $this->customer();

        $foreign = $user->createToken('un-kiosko', ['puerta'])->plainTextToken;
        $this->asBearer($foreign)->getJson(self::ROOT.'/me')->assertForbidden();

        $client = $user->createToken('la-app', [ApiTokenIssuer::ABILITY])->plainTextToken;
        $this->asBearer($client)->getJson(self::ROOT.'/me')->assertOk();

        // ⚠️ `actingAs($user, 'web')` y no a secas: las peticiones Bearer de arriba dejaron `sanctum` como
        // guard por defecto de ESTA aplicación de test, y sin nombrarlo el usuario se plantaría en ese
        // guard sin token alguno — un 401 del test, no del producto. La SPA entra por `web`.
        Auth::forgetGuards();
        $this->flushHeaders()->actingAs($user, 'web')->getJson(self::ROOT.'/me')->assertOk();
    }

    /**
     * La red de `Tests\TestCase::be()`: tras una petición a la API, `sanctum` queda como guard por
     * defecto y un `actingAs()` SIN guard planta ahí al titular. Sin el `TransientToken` que el guard
     * real adjunta, la ability lo convertía en un 401 que solo existía en los tests (24, al activarla).
     */
    public function test_a_plain_acting_as_after_an_api_request_is_still_a_session_holder(): void
    {
        $ada = $this->customer('ada@jumpweb.test');
        $grace = $this->customer('grace@jumpweb.test');

        $this->actingAs($ada)->getJson(self::ROOT.'/me')->assertOk()->assertJsonPath('id', $ada->id);
        $this->actingAs($grace)->getJson(self::ROOT.'/me')->assertOk()->assertJsonPath('id', $grace->id);
        $this->actingAs($ada, 'sanctum')->getJson(self::ROOT.'/me')->assertOk()->assertJsonPath('id', $ada->id);
    }

    /** El único guard del producto es `web`: Filament no mira `sanctum`, y un Bearer no es una llave del panel. */
    public function test_a_bearer_token_never_opens_the_admin_panel(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = $this->customer('admin@jumpweb.test');
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        // Control: con SESIÓN ese mismo usuario sí entra — si no, la redirección de abajo no probaría nada.
        $this->actingAs($admin, 'web')->get('/admin')->assertOk();
        Auth::forgetGuards();
        $this->app['auth']->shouldUse('web');

        $token = $this->issue($admin)->assertCreated()->json('token');

        $this->asBearer($token)->get('/admin')->assertRedirect('/admin/login');
    }

    /** Los tokens emitidos por la puerta REAL mueren por la palanca única de `RGPD-06`, no solo los de fábrica. */
    public function test_tokens_issued_through_the_real_door_die_with_the_single_point_of_invalidation(): void
    {
        $user = $this->customer();
        $phone = $this->issue($user, ['device_name' => 'movil'])->json('token');
        $tablet = $this->issue($user, ['device_name' => 'tablet'])->json('token');

        // «Cerrar las demás» desde el móvil: él sigue, la tablet cae.
        $this->asBearer($phone)->postJson(self::ROOT.'/me/sessions/revoke-others', ['current_password' => 'password'])->assertSuccessful();
        $this->asBearer($tablet)->getJson(self::ROOT.'/me')->assertUnauthorized();
        $this->asBearer($phone)->getJson(self::ROOT.'/me')->assertOk();

        // La palanca de «me han entrado» (reset de contraseña, supresión): cae también el que pedía.
        $user->revokeAllAccess();
        $this->asBearer($phone)->getJson(self::ROOT.'/me')->assertUnauthorized();
    }

    public function test_the_trail_carries_no_personal_data(): void
    {
        Log::spy();
        $user = $this->customer();

        $this->issue($user)->assertCreated();

        Log::shouldHaveReceived('info')->withArgs(function (string $event, array $context = []) use ($user): bool {
            if ($event !== 'auth.token_issued') {
                return false;
            }

            $flat = json_encode($context);

            return ! str_contains((string) $flat, $user->email) && ! str_contains((string) $flat, 'iPhone de prueba');
        })->once();
    }
}
