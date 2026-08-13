<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Contracts\LoginResult;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PasswordLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Fase 3 · paso 3b — el inicio de sesión por contraseña, probado por sí mismo.
 *
 * Los tests de las dos superficies (modal Livewire y `POST auth/login`) comprueban que cada una
 * traduce bien el veredicto. Aquí se ataca la REGLA, y sobre todo los matices que por HTTP se
 * ejercitan mal o no se ejercitan: que la clave por IP **no** se limpie al acertar, que las dos
 * claves sean independientes, y que el veredicto no distinga nunca qué credencial falló.
 */
class PasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    private const IP = '203.0.113.7';

    private PasswordLogin $login;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login = app(PasswordLogin::class);
    }

    private function customer(string $email = 'cliente@jumpweb.test'): User
    {
        return User::factory()->create(['email' => $email]);
    }

    private function attempt(string $email, string $password = 'password', string $ip = self::IP): LoginResult
    {
        return $this->login->attempt($email, $password, false, $ip);
    }

    public function test_valid_credentials_return_the_user(): void
    {
        $user = $this->customer();

        $result = $this->attempt($user->email);

        $this->assertTrue($result->succeeded);
        $this->assertSame($user->id, $result->user->id);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    /** Nunca se distingue qué falló: es la mitad de `SEC-06` (anti-enumeración). */
    public function test_the_verdict_never_says_which_credential_failed(): void
    {
        $this->customer();

        $wrongPassword = $this->attempt('cliente@jumpweb.test', 'no-es-esta');
        $unknownEmail = $this->attempt('nadie@jumpweb.test', 'no-es-esta');

        foreach ([$wrongPassword, $unknownEmail] as $result) {
            $this->assertTrue($result->failed());
            $this->assertSame(LoginResult::INVALID_CREDENTIALS, $result->reason);
            $this->assertNull($result->user);
        }
    }

    public function test_five_failures_on_one_account_lock_it_temporarily(): void
    {
        $this->customer();

        foreach (range(1, PasswordLogin::MAX_ATTEMPTS) as $ignored) {
            $this->assertSame(LoginResult::INVALID_CREDENTIALS, $this->attempt('cliente@jumpweb.test', 'mal')->reason);
        }

        $blocked = $this->attempt('cliente@jumpweb.test');

        $this->assertSame(LoginResult::RATE_LIMITED, $blocked->reason);
        $this->assertGreaterThan(0, $blocked->retryAfter, 'un bloqueo sin `retry_after` no le dice al cliente cuándo volver');
    }

    /**
     * El bloqueo es por (correo, IP): otro titular desde la MISMA IP sigue pudiendo entrar. Sin
     * esto, cinco intentos fallidos contra una cuenta dejarían fuera a todos los que comparten un
     * NAT — que es exactamente por lo que este limitador no puede ser el único.
     */
    public function test_locking_one_account_does_not_lock_the_others(): void
    {
        $this->customer('victima@jumpweb.test');
        $other = $this->customer('otra@jumpweb.test');

        foreach (range(1, PasswordLogin::MAX_ATTEMPTS) as $ignored) {
            $this->attempt('victima@jumpweb.test', 'mal');
        }

        $this->assertSame(LoginResult::RATE_LIMITED, $this->attempt('victima@jumpweb.test')->reason);
        $this->assertTrue($this->attempt($other->email)->succeeded);
    }

    /**
     * Segundo limitador (auditoría Fase 1, A5): el barrido de muchas cuentas desde una IP. La clave
     * compuesta no lo ve —un intento por cuenta nunca acumula cinco en ninguna clave—, así que sin
     * este el password-spraying pasaría entero.
     */
    public function test_spraying_many_accounts_from_one_ip_is_stopped(): void
    {
        foreach (range(1, PasswordLogin::MAX_ATTEMPTS_PER_IP) as $i) {
            $this->assertSame(LoginResult::INVALID_CREDENTIALS, $this->attempt("victima{$i}@jumpweb.test", 'probando')->reason);
        }

        $this->assertSame(LoginResult::RATE_LIMITED, $this->attempt('otra-mas@jumpweb.test', 'probando')->reason);
    }

    /**
     * **La clave por IP NO se limpia al acertar**, y esa asimetría es deliberada: la clave es de la
     * IP, no del titular. Si se limpiara, a un atacante le bastaría intercalar un login válido
     * —con una cuenta propia— para reiniciar su contador y seguir barriendo.
     *
     * Es el matiz que peor se ejercita por HTTP y por eso tiene test aquí.
     */
    public function test_a_successful_login_clears_only_its_own_key(): void
    {
        $user = $this->customer();

        // Cuatro fallos contra esta cuenta (sin llegar al bloqueo) y unos cuantos contra otras.
        foreach (range(1, PasswordLogin::MAX_ATTEMPTS - 1) as $ignored) {
            $this->attempt($user->email, 'mal');
        }
        foreach (range(1, 10) as $i) {
            $this->attempt("otra{$i}@jumpweb.test", 'mal');
        }

        $this->assertTrue($this->attempt($user->email)->succeeded);

        // La suya se limpió: vuelve a tener sus cinco intentos.
        $this->assertSame(0, RateLimiter::attempts('cliente@jumpweb.test|'.self::IP));
        // La de la IP sigue contando los 14 fallos, incluido el barrido.
        $this->assertSame(14, RateLimiter::attempts('login-ip|'.self::IP));
    }

    /** Las claves llevan la IP: el mismo correo desde otro origen no arrastra el bloqueo ajeno. */
    public function test_the_lock_does_not_follow_the_account_to_another_ip(): void
    {
        $user = $this->customer();

        foreach (range(1, PasswordLogin::MAX_ATTEMPTS) as $ignored) {
            $this->attempt($user->email, 'mal');
        }

        $this->assertSame(LoginResult::RATE_LIMITED, $this->attempt($user->email)->reason);
        $this->assertTrue($this->attempt($user->email, 'password', '198.51.100.4')->succeeded);
    }
}
