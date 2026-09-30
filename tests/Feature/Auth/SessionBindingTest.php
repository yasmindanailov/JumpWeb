<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LoginCodes;
use App\Domain\Identity\Services\SessionBinding;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\ApiTestCase;

/**
 * A2a de `docs/specs/acceso-con-codigo.md` (§4.9, `DECISIONES #855`, `RGPD-06`) — **la revocación que NO depende del driver
 * de sesión**: cada sesión de la web va atada al `remember_token` de su cuenta, y la que ya no casa se cierra.
 *
 * ⚠️⚠️ La suite corre con `SESSION_DRIVER=array`, donde `User::purgeSessions()` no borra nada —como con el `redis` de
 * producción—. Por eso estas pruebas son la medida que vale: si pasan aquí, «cerrar las demás sesiones» cierra la SESIÓN
 * viva del otro dispositivo sin tabla que purgar. Cada «dispositivo» entra con un código y lleva SOLO su cookie de sesión
 * (sin la de recuerdo: lo que se mide es la sesión). Arnés: `scripts/mutar-acceso-codigo.sh`.
 */
class SessionBindingTest extends ApiTestCase
{
    private const IP = '203.0.113.40';

    private function customer(): User
    {
        return User::factory()->create(['email' => 'ana.sesiones@example.test', 'email_verified_at' => now()]);
    }

    private function sessionCookie(): string
    {
        return (string) config('session.cookie');
    }

    /**
     * Un dispositivo: su cookie de sesión y nada más. ⚠️ Con el driver `array` el almacén de sesión es el MISMO objeto en
     * todas las peticiones de una prueba y una petición HEREDA sus atributos: se vacía antes de cada una (`TESTING.md`).
     */
    private function device(?string $session): static
    {
        Auth::forgetGuards();
        app('session')->driver()->flush();
        $this->defaultCookies = [];
        $this->withCredentials()->withHeader('Origin', (string) config('app.url'));

        return $session === null ? $this : $this->withCookie($this->sessionCookie(), $session);
    }

    /** Entra con un código de verdad y devuelve la cookie de sesión de ese dispositivo. */
    private function signIn(User $user): string
    {
        $code = app(LoginCodes::class)->issue((string) $user->email, LoginCode::PURPOSE_LOGIN, self::IP);
        $response = $this->device(null)->postJson(self::ROOT.'/auth/login', ['email' => $user->email, 'code' => $code])->assertOk();

        return (string) $response->getCookie($this->sessionCookie())?->getValue();
    }

    private function me(string $session): TestResponse
    {
        return $this->device($session)->getJson(self::ROOT.'/me');
    }

    private function confirmCode(User $user): string
    {
        return app(LoginCodes::class)->issue((string) $user->email, LoginCode::PURPOSE_CONFIRM, self::IP);
    }

    public function test_a_session_is_bound_when_it_signs_in(): void
    {
        $user = $this->customer();
        $session = $this->signIn($user);

        // Mirado ANTES de cualquier otra petición: la primera la ataría igual (la rama de las sesiones viejas) y
        // escondería que el oyente del `Login` falta.
        $this->assertTrue($this->bindingOf($session), 'la sesión sale del inicio de sesión ya atada');
        $this->me($session)->assertOk()->assertJsonPath('id', $user->id);
        $this->assertNotSame('', (string) $user->fresh()->getRememberToken(), 'la cuenta tiene token: una sesión atada a «nada» no se podría cerrar');
    }

    /**
     * ⚠️⚠️ **Lo que antes no pasaba con `redis`** (§4.4): el móvil cierra las demás sesiones con un código, y la SESIÓN viva
     * del portátil deja de estar dentro en su próxima petición. El móvil sigue dentro.
     */
    public function test_closing_the_other_sessions_closes_their_live_session_with_any_driver(): void
    {
        $this->assertNotSame('database', config('session.driver'), 'la medida vale porque NO hay tabla que purgar');
        $user = $this->customer();
        $laptop = $this->signIn($user);
        $phone = $this->signIn($user);
        $this->me($laptop)->assertOk();

        $this->device($phone)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => $this->confirmCode($user)])
            ->assertNoContent();

        $this->me($laptop)->assertStatus(401);
        $this->me($phone)->assertOk();
    }

    /**
     * Y en la WEB (el grupo `web`, no la API): la página que exige sesión trata al portátil como a un visitante. La
     * descarga de «Mi cuenta» porque no depende de contenido sembrado.
     */
    public function test_a_closed_session_is_a_visitor_on_the_web_too(): void
    {
        $user = $this->customer();
        $laptop = $this->signIn($user);
        $phone = $this->signIn($user);
        $this->device($laptop)->get('/mi-cuenta/exportar')->assertOk();

        $this->device($phone)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => $this->confirmCode($user)])
            ->assertNoContent();

        $this->device($laptop)->get('/mi-cuenta/exportar')->assertRedirect();
        $this->device($phone)->get('/mi-cuenta/exportar')->assertOk();
    }

    /** Y con la contraseña (hasta la A5), igual. */
    public function test_closing_the_other_sessions_with_the_password_closes_them_too(): void
    {
        $user = $this->customer();
        $laptop = $this->signIn($user);
        $phone = $this->signIn($user);

        $this->device($phone)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['current_password' => 'password'])
            ->assertNoContent();

        $this->me($laptop)->assertStatus(401);
        $this->me($phone)->assertOk();
    }

    public function test_the_emergency_lever_closes_every_live_session(): void
    {
        $user = $this->customer();
        $laptop = $this->signIn($user);
        $phone = $this->signIn($user);

        $user->fresh()->revokeAllAccess();

        $this->me($laptop)->assertStatus(401);
        $this->me($phone)->assertStatus(401);
    }

    /** Una sesión de ANTES de este mecanismo (sin huella) se ata en su próxima petición, y desde ahí se puede cerrar. */
    public function test_a_session_from_before_the_binding_is_bound_and_then_closable(): void
    {
        $user = $this->customer();
        $old = $this->signIn($user);
        $this->forgetBinding($old);

        $this->me($old)->assertOk();
        $this->assertTrue($this->bindingOf($old), 'la sesión vieja se ató al pasar');

        $phone = $this->signIn($user);
        $this->device($phone)->postJson(self::ROOT.'/me/sessions/revoke-others', ['code' => $this->confirmCode($user)])->assertNoContent();

        $this->me($old)->assertStatus(401);
    }

    /** Salir en la web (`POST /logout`) cierra ESTE dispositivo: los demás siguen dentro (`#848`·3). */
    public function test_logging_out_of_the_web_closes_this_device_only(): void
    {
        $user = $this->customer();
        $laptop = $this->signIn($user);
        $phone = $this->signIn($user);
        $token = $user->fresh()->getRememberToken();

        $this->device($laptop)->post('/logout')->assertRedirect('/');

        $this->assertSame($token, $user->fresh()->getRememberToken(), 'salir no rota el token de todos');
        $this->me($phone)->assertOk();
        $this->me($laptop)->assertStatus(401);
    }

    /**
     * Borra la huella de una sesión guardada, como la de una sesión nacida antes de este mecanismo. La cookie (ya descifrada)
     * ES el id de la sesión; y la sesión se guarda en JSON (`session.serialization`).
     */
    private function forgetBinding(string $sessionId): void
    {
        $handler = app('session')->driver()->getHandler();
        $data = (array) json_decode((string) $handler->read($sessionId), true);
        unset($data[SessionBinding::KEY]);
        $handler->write($sessionId, (string) json_encode($data));
    }

    private function bindingOf(string $sessionId): bool
    {
        $data = json_decode((string) app('session')->driver()->getHandler()->read($sessionId), true);

        return is_array($data) && is_string($data[SessionBinding::KEY] ?? null);
    }
}
