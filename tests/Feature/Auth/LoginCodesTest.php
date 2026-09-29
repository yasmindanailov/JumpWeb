<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Services\LoginCodes;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A1 de `docs/specs/acceso-con-codigo.md` (§4.1, §6) — **el código de un solo uso, probado por sí mismo**.
 *
 * Las superficies (`AuthCodeTest`) comprueban que la puerta y el login lo usan bien. Aquí se ataca cada REGLA del
 * código, y cada una con su control —el mismo caso sin la regla entra—, porque una regla que no se ve fallar no está
 * probada: caduca a los 10 minutos, se gasta al usarlo, muere al quinto intento, uno solo vivo por (correo, propósito),
 * y la tabla guarda la huella y nunca el código. Arnés: `scripts/mutar-acceso-codigo.sh`.
 */
class LoginCodesTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'ana.codigo@example.test';

    private const IP = '203.0.113.9';

    private LoginCodes $codes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codes = app(LoginCodes::class);
    }

    private function issue(string $email = self::EMAIL): string
    {
        return $this->codes->issue($email, LoginCode::PURPOSE_LOGIN, self::IP);
    }

    private function consume(string $code, string $email = self::EMAIL): bool
    {
        return $this->codes->consume($email, LoginCode::PURPOSE_LOGIN, $code);
    }

    /** Un código que NO es `$code`, con las mismas seis cifras. */
    private function wrong(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function test_a_code_is_six_digits_and_the_table_keeps_its_fingerprint_not_the_code(): void
    {
        $code = $this->issue();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $row = LoginCode::query()->sole();
        $this->assertSame(self::EMAIL, $row->email);
        // La RECETA de la huella, fijada: HMAC con la clave de la app sobre propósito, correo y código. Sin la clave, un
        // millón de candidatos se recorren en un instante; sin el correo o el propósito, una huella valdría para otro.
        $this->assertSame(hash_hmac('sha256', LoginCode::PURPOSE_LOGIN.'|'.self::EMAIL.'|'.$code, (string) config('app.key')), $row->code_hash);
        $this->assertNotSame($code, $row->code_hash);
        $this->assertStringNotContainsString($code, json_encode($row->getAttributes()), 'el código en claro no puede estar en ninguna columna');
        $this->assertSame(self::IP, $row->ip);
        $this->assertEqualsWithDelta(now()->addMinutes(LoginCodes::TTL_MINUTES)->timestamp, $row->expires_at->timestamp, 2);
    }

    public function test_the_right_code_opens_once_and_only_once(): void
    {
        $code = $this->issue();

        $this->assertTrue($this->consume($code), 'control: el código bueno entra');
        $this->assertFalse($this->consume($code), 'un solo uso: la segunda vez ya no');
        $this->assertNotNull(LoginCode::query()->sole()->used_at);
    }

    /**
     * ⚠️⚠️ **Dos usos A LA VEZ: entra uno.** B gasta el código justo en el hueco entre la LECTURA de A (el código vivo) y
     * su ESCRITURA; A no puede entrar con lo que leyó, porque el intento y el uso están condicionados a que siga sin usar.
     *
     * Determinista a propósito: la carrera real con diez peticiones contra MySQL da un solo 201, pero su CONTROL —el
     * código sin las condiciones— también (medido el 29-09: el hueco es demasiado estrecho para cuatro procesos), así que
     * aquella carrera no discrimina. Esta sí: el hueco se abre a mano con `DB::listen`.
     */
    public function test_two_uses_in_the_gap_between_read_and_write_only_one_gets_in(): void
    {
        $code = $this->issue();
        $inTheGap = null;

        DB::listen(function (QueryExecuted $query) use (&$inTheGap, $code): void {
            if ($inTheGap !== null || ! str_starts_with(strtolower(ltrim($query->sql)), 'select') || ! str_contains($query->sql, 'login_codes')) {
                return;
            }
            $inTheGap = false;
            $inTheGap = $this->consume($code);
        });

        $first = $this->consume($code);

        $this->assertTrue($inTheGap, 'B, que entró en el hueco, gasta el código');
        $this->assertFalse($first, 'A, que ya lo había leído vivo, NO entra');
    }

    public function test_the_code_expires_after_ten_minutes(): void
    {
        $code = $this->issue();

        $this->travel(LoginCodes::TTL_MINUTES)->minutes();
        $this->travel(1)->seconds();

        $this->assertFalse($this->consume($code), 'caducado, aunque sea el bueno');
    }

    public function test_just_before_expiring_it_still_opens(): void
    {
        $code = $this->issue();

        $this->travel(LoginCodes::TTL_MINUTES * 60 - 5)->seconds();

        $this->assertTrue($this->consume($code), 'control del caso de arriba: a 5 s de caducar, entra');
    }

    public function test_the_fifth_wrong_attempt_kills_the_code(): void
    {
        $code = $this->issue();

        foreach (range(1, LoginCodes::MAX_ATTEMPTS) as $ignored) {
            $this->assertFalse($this->consume($this->wrong($code)));
        }

        $this->assertFalse($this->consume($code), 'con los cinco intentos gastados, ni el bueno entra');
        $this->assertSame(LoginCodes::MAX_ATTEMPTS, LoginCode::query()->sole()->attempts);
    }

    public function test_four_wrong_attempts_still_leave_the_right_code_working(): void
    {
        $code = $this->issue();

        foreach (range(1, LoginCodes::MAX_ATTEMPTS - 1) as $ignored) {
            $this->consume($this->wrong($code));
        }

        $this->assertTrue($this->consume($code), 'control del caso de arriba: al cuarto fallo el bueno sigue entrando');
    }

    public function test_asking_for_another_code_kills_the_previous_one(): void
    {
        $first = $this->issue();
        $second = $this->issue();

        if ($first === $second) {
            $this->markTestSkipped('los dos códigos salieron iguales (una vez en un millón)');
        }

        $this->assertFalse($this->consume($first), 'uno vivo por (correo, propósito): el anterior ya no vale');
        $this->assertTrue($this->consume($second), 'y el nuevo sí');
        $this->assertSame(1, LoginCode::query()->count());
    }

    public function test_a_code_only_opens_the_email_it_was_sent_to(): void
    {
        $code = $this->issue();
        $this->codes->issue('otra.persona@example.test', LoginCode::PURPOSE_LOGIN, self::IP);

        $this->assertFalse($this->consume($code, 'otra.persona@example.test'), 'la huella lleva el correo dentro');
        $this->assertTrue($this->consume($code), 'control: en su correo, entra');
    }

    public function test_a_code_only_serves_its_own_purpose(): void
    {
        $code = $this->issue();

        $this->assertFalse($this->codes->consume(self::EMAIL, 'confirm', $code), 'un código de entrar no confirma nada');
        $this->assertTrue($this->consume($code), 'control: para entrar, entra');
    }

    public function test_the_email_is_normalized_and_the_code_can_come_split(): void
    {
        $code = $this->issue('  Ana.Codigo@Example.TEST ');

        $this->assertTrue($this->consume(substr($code, 0, 3).' '.substr($code, 3)), '«482 913» es el mismo código, y el correo el mismo en minúsculas');
    }
}
