<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Contracts\LoginResult;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\EmailCodeLogin;
use App\Domain\Identity\Services\LoginGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\IssuesCodes;
use Tests\TestCase;

/**
 * **Los dos cubos de `SEC-06`, probados por sí mismos** (`LoginGate`, por la puerta del código).
 *
 * Los tests de las superficies (`POST auth/login`, `POST auth/tokens`) comprueban que cada una traduce bien el
 * veredicto. Aquí se ataca la REGLA, y sobre todo los matices que por HTTP se ejercitan mal: que la clave por IP **no**
 * se limpie al acertar, que las dos claves sean independientes, y que el veredicto no distinga nunca si el correo existe.
 *
 * ▶ Era `PasswordLoginTest` y entraba con la contraseña. Desde la A5 (`specs/acceso-con-codigo.md` §4.12, `#869`) la
 * contraseña del cliente ya no existe, pero su núcleo sí: `LoginGate` salió de `PasswordLogin` TAL CUAL en la A1
 * (`#853`) y hoy lo usa el código. El sujeto sobrevive y la prueba se re-apunta (`CONVENCIONES` §3.quater).
 */
class LoginGateTest extends TestCase
{
    use IssuesCodes;
    use RefreshDatabase;

    private const IP = '203.0.113.7';

    private EmailCodeLogin $login;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login = app(EmailCodeLogin::class);
    }

    private function customer(string $email = 'cliente@jumpweb.test'): User
    {
        return User::factory()->create(['email' => $email, 'password' => null]);
    }

    private function attempt(string $email, string $code, string $ip = self::IP): LoginResult
    {
        return $this->login->verify($email, $code, $ip);
    }

    public function test_a_valid_code_returns_the_user_and_stamps_the_login(): void
    {
        $user = $this->customer();

        $result = $this->attempt($user->email, $this->loginCodeFor($user));

        $this->assertTrue($result->succeeded);
        $this->assertSame($user->id, $result->user->id);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    /** Nunca se distingue qué falló: es la mitad de `SEC-06` (anti-enumeración). */
    public function test_the_verdict_never_says_whether_the_email_exists(): void
    {
        $user = $this->customer();

        $wrongCode = $this->attempt($user->email, $this->wrongCode($this->loginCodeFor($user)));
        $unknownEmail = $this->attempt('nadie@jumpweb.test', '123456');

        foreach ([$wrongCode, $unknownEmail] as $result) {
            $this->assertTrue($result->failed());
            $this->assertSame(LoginResult::INVALID_CREDENTIALS, $result->reason);
            $this->assertNull($result->user);
        }
    }

    public function test_five_failures_on_one_account_lock_it_temporarily(): void
    {
        $user = $this->customer();

        foreach (range(1, LoginGate::MAX_ATTEMPTS) as $ignored) {
            $this->assertSame(LoginResult::INVALID_CREDENTIALS, $this->attempt($user->email, '000000')->reason);
        }

        // Con un código BUENO recién emitido: el cubo manda antes que el código.
        $blocked = $this->attempt($user->email, $this->loginCodeFor($user));

        $this->assertSame(LoginResult::RATE_LIMITED, $blocked->reason);
        $this->assertGreaterThan(0, $blocked->retryAfter, 'un bloqueo sin `retry_after` no le dice al cliente cuándo volver');
    }

    /**
     * El bloqueo es por (correo, IP): otro titular desde la MISMA IP sigue pudiendo entrar. Sin esto, cinco intentos
     * fallidos contra una cuenta dejarían fuera a todos los que comparten un NAT — que es exactamente por lo que este
     * limitador no puede ser el único.
     */
    public function test_locking_one_account_does_not_lock_the_others(): void
    {
        $victim = $this->customer('victima@jumpweb.test');
        $other = $this->customer('otra@jumpweb.test');

        foreach (range(1, LoginGate::MAX_ATTEMPTS) as $ignored) {
            $this->attempt($victim->email, '000000');
        }

        $this->assertSame(LoginResult::RATE_LIMITED, $this->attempt($victim->email, $this->loginCodeFor($victim))->reason);
        $this->assertTrue($this->attempt($other->email, $this->loginCodeFor($other))->succeeded);
    }

    /**
     * Segundo limitador (auditoría Fase 1, A5): el barrido de muchas cuentas desde una IP. La clave compuesta no lo ve
     * —un intento por cuenta nunca acumula cinco en ninguna clave—, así que sin éste el barrido pasaría entero.
     */
    public function test_spraying_many_accounts_from_one_ip_is_stopped(): void
    {
        foreach (range(1, LoginGate::MAX_ATTEMPTS_PER_IP) as $i) {
            $this->assertSame(LoginResult::INVALID_CREDENTIALS, $this->attempt("victima{$i}@jumpweb.test", '000000')->reason);
        }

        $this->assertSame(LoginResult::RATE_LIMITED, $this->attempt('otra-mas@jumpweb.test', '000000')->reason);
    }

    /**
     * **La clave por IP NO se limpia al acertar**, y esa asimetría es deliberada: la clave es de la IP, no del titular. Si
     * se limpiara, a un atacante le bastaría intercalar una entrada válida —con una cuenta propia— para reiniciar su
     * contador y seguir barriendo. Es el matiz que peor se ejercita por HTTP y por eso tiene test aquí.
     */
    public function test_a_successful_login_clears_only_its_own_key(): void
    {
        $user = $this->customer();

        // Cuatro fallos contra esta cuenta (sin llegar al bloqueo) y unos cuantos contra otras.
        foreach (range(1, LoginGate::MAX_ATTEMPTS - 1) as $ignored) {
            $this->attempt($user->email, '000000');
        }
        foreach (range(1, 10) as $i) {
            $this->attempt("otra{$i}@jumpweb.test", '000000');
        }

        $this->assertTrue($this->attempt($user->email, $this->loginCodeFor($user))->succeeded);

        // La suya se limpió: vuelve a tener sus cinco intentos.
        $this->assertSame(0, RateLimiter::attempts('cliente@jumpweb.test|'.self::IP));
        // La de la IP sigue contando los 14 fallos, incluido el barrido.
        $this->assertSame(14, RateLimiter::attempts('login-ip|'.self::IP));
    }

    /** Las claves llevan la IP: el mismo correo desde otro origen no arrastra el bloqueo ajeno. */
    public function test_the_lock_does_not_follow_the_account_to_another_ip(): void
    {
        $user = $this->customer();

        foreach (range(1, LoginGate::MAX_ATTEMPTS) as $ignored) {
            $this->attempt($user->email, '000000');
        }

        $this->assertSame(LoginResult::RATE_LIMITED, $this->attempt($user->email, $this->loginCodeFor($user))->reason);
        $this->assertTrue($this->attempt($user->email, $this->loginCodeFor($user), '198.51.100.4')->succeeded);
    }
}
