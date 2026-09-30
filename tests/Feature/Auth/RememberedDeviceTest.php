<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\RememberedDevice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\ApiTestCase;

/**
 * A1 de `docs/specs/acceso-con-codigo.md` (§4.4, `DECISIONES #848`·3 y `#853`) — **el dispositivo RECORDADO 90 días sin
 * uso o hasta cerrar sesión**, y que siga siendo una credencial que la palanca única alcanza (`RGPD-06`).
 *
 * Cada caso simula un DISPOSITIVO con su cookie de recuerdo y nada más (sin sesión, con los guards olvidados): es lo que
 * trae un navegador que vuelve al día siguiente. Lo que se prueba:
 *  - «sin uso»: la cookie vuelve con 90 días más en cada página, y solo si sigue siendo buena;
 *  - «cerrar las demás sesiones» deja fuera las cookies de los OTROS y conserva la de quien lo pide (medido antes: no
 *    tocaba ninguna, y el otro dispositivo volvía a entrar en cuanto caducaba su sesión);
 *  - la palanca de «me han entrado» (y la supresión) no deja ninguna;
 *  - cerrar sesión cierra ESTE dispositivo, no los demás.
 * Arnés: `scripts/mutar-acceso-codigo.sh`.
 */
class RememberedDeviceTest extends ApiTestCase
{
    private function recallerName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    private function rememberedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'remember_token' => Str::random(60)]);
    }

    /** La cookie de recuerdo tal como la escribe el guard: identificador, token y huella de la contraseña. */
    private function cookieOf(User $user): string
    {
        return $user->getAuthIdentifier().'|'.$user->getRememberToken().'|huella';
    }

    /**
     * Un navegador que vuelve: su cookie de recuerdo y nada más (ni sesión, ni guards con alguien en memoria).
     *
     * ⚠️ `withCredentials()` no es adorno: sin él, `getJson()`/`postJson()` de la suite NO mandan cookies, y el 401 que
     * sale parece «la cookie no vale» cuando la cookie ni viajó (medido al escribir este fichero).
     * ⚠️ Y la sesión se VACÍA: con el driver `array` de la suite el almacén es el mismo objeto en todas las peticiones
     * de una prueba, y una petición sin cookie de sesión HEREDA sus atributos —el inicio de sesión del dispositivo
     * anterior incluido—. Sin esto, «el otro dispositivo sigue fuera» daba 200 por la sesión de otro.
     */
    private function device(string $cookie): static
    {
        Auth::forgetGuards();
        app('session')->driver()->flush();
        $this->defaultCookies = [];

        return $this->withCredentials()
            ->withCookie($this->recallerName(), $cookie)
            ->withHeader('Origin', (string) config('app.url'));
    }

    /** Una página cualquiera de la web (el grupo `web`): cambiar de idioma, que responde sin contenido sembrado. */
    private function webPage(string $cookie): TestResponse
    {
        return $this->device($cookie)->get('/lang/es')->assertRedirect();
    }

    private function me(string $cookie): TestResponse
    {
        return $this->device($cookie)->getJson(self::ROOT.'/me');
    }

    public function test_a_returning_device_gets_its_cookie_back_with_ninety_more_days(): void
    {
        $user = $this->rememberedUser();

        $response = $this->webPage($this->cookieOf($user));

        $cookie = $response->getCookie($this->recallerName(), false);
        $this->assertNotNull($cookie, '«90 días SIN USO»: cada página que la usa la devuelve alargada');
        $this->assertEqualsWithDelta(now()->addMinutes(RememberedDevice::MINUTES)->timestamp, $cookie->getExpiresTime(), 5);
        $this->assertSame($this->cookieOf($user), $response->getCookie($this->recallerName())?->getValue(), 'la misma cookie, no otra');
    }

    public function test_a_stale_cookie_is_not_lengthened(): void
    {
        $user = $this->rememberedUser();
        $stale = $user->getAuthIdentifier().'|'.Str::random(60).'|huella';

        $response = $this->webPage($stale);

        $this->assertNull($response->getCookie($this->recallerName(), false), 'una cookie que ya no abre nada no se alarga');
    }

    public function test_the_remembered_device_gets_in_without_a_session(): void
    {
        $user = $this->rememberedUser();

        $this->me($this->cookieOf($user))->assertOk()->assertJsonPath('id', $user->id);
    }

    /**
     * ⚠️⚠️ `RGPD-06`: «cerrar las demás sesiones» deja fuera la cookie de los OTROS dispositivos y conserva la de quien
     * lo pide, re-emitida con el token nuevo.
     */
    public function test_closing_the_other_sessions_drops_the_other_devices_and_keeps_this_one(): void
    {
        $user = $this->rememberedUser();
        $old = $this->cookieOf($user);

        $response = $this->device($old)
            ->postJson(self::ROOT.'/me/sessions/revoke-others', ['current_password' => 'password'])
            ->assertSuccessful();

        $mine = $response->getCookie($this->recallerName())?->getValue();
        $this->assertIsString($mine, 'el dispositivo que lo pide recibe su cookie con el token nuevo');
        $this->assertNotSame($old, $mine);
        $this->assertSame($user->fresh()->getRememberToken(), explode('|', $mine)[1]);

        $this->me($old)->assertStatus(401);
        $this->me($mine)->assertOk();
    }

    public function test_the_emergency_lever_leaves_no_remembered_device(): void
    {
        $user = $this->rememberedUser();
        $cookie = $this->cookieOf($user);
        $this->me($cookie)->assertOk();

        $user->revokeAllAccess();

        // `getRawOriginal`: `getRememberToken()` devuelve `''` por un `NULL`.
        $this->assertNull($user->fresh()->getRawOriginal('remember_token'));
        $this->me($cookie)->assertStatus(401);
    }

    /** «Hasta cerrar sesión» es de ESTE dispositivo: salir en el portátil no echa al móvil (`#848`·3). */
    public function test_logging_out_closes_this_device_only(): void
    {
        $user = $this->rememberedUser();
        $token = $user->getRememberToken();
        $cookie = $this->cookieOf($user);

        $response = $this->device($cookie)->postJson(self::ROOT.'/auth/logout')->assertNoContent();

        $gone = $response->getCookie($this->recallerName(), false);
        $this->assertNotNull($gone, 'la cookie de recuerdo de este dispositivo se borra');
        $this->assertLessThan(time(), $gone->getExpiresTime());
        $this->assertSame($token, $user->fresh()->getRememberToken(), 'el token no rota: los demás siguen recordados');

        $this->me($cookie)->assertOk();
    }

    /**
     * ⚠️⚠️ Tras salir, NADIE dentro: la petición de salir trae la cookie de recuerdo, y un guard resuelto a la vuelta
     * (`Auth::forgetGuards()` + el `$request->user()` de `NoStoreWhenAuthenticated`) volvía a entrar con ella y dejaba la
     * sesión NUEVA con el titular dentro. Medido en el navegador (A3, `#857`): tras «Cerrar sesión», `/me` daba 200.
     */
    public function test_logging_out_does_not_sign_this_device_back_in(): void
    {
        $user = $this->rememberedUser();

        $this->device($this->cookieOf($user))->postJson(self::ROOT.'/auth/logout')->assertNoContent();

        // La siguiente petición del navegador: con su sesión (la del driver `array`, el mismo almacén) y SIN la cookie de
        // recuerdo, que la respuesta mandó borrar. Sin `device()`: vaciaría la sesión que se quiere mirar.
        Auth::forgetGuards();
        $this->defaultCookies = [];
        $this->withCredentials()->withHeader('Origin', (string) config('app.url'))
            ->getJson(self::ROOT.'/me')
            ->assertStatus(401);
    }

    /**
     * Una cuenta SIN contraseña se recuerda sin avisos: el framework pasa la contraseña por `hash_hmac()`, y un `NULL` ahí
     * es obsoleto en PHP 8.5 (y un error en la siguiente). `User::getAuthPassword()` da `''`.
     */
    public function test_a_passwordless_account_is_remembered_without_deprecations(): void
    {
        $user = User::factory()->create(['password' => null]);
        $this->assertNull($user->getRawOriginal('password'));

        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            Auth::guard('web')->login($user, remember: true);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
        $this->assertSame('', $user->getAuthPassword());
    }
}
