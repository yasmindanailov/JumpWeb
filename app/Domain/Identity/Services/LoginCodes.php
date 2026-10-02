<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\LoginCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * **El mecanismo del código de un solo uso** (`docs/specs/acceso-con-codigo.md` §4.1, `DECISIONES #848`): emitirlo y
 * gastarlo. Nada más: quién puede pedirlo, cuántas veces y qué abre lo decide quien lo usa —hoy
 * {@see EmailCodeLogin} (entrar); en la A2, las acciones sensibles (`confirm`)—.
 *
 * Las cuatro reglas del código viven AQUÍ y en ningún otro sitio, cada una con su prueba y su mutación
 * (`LoginCodesTest`, `scripts/mutar-acceso-codigo.sh`):
 *  - **6 cifras, 10 minutos**;
 *  - **un solo uso**: gastarlo es una actualización CONDICIONADA a que siga sin usar, así que dos peticiones a la vez
 *    con el mismo código no entran las dos;
 *  - **5 intentos y muere**: cada intento se gasta ANTES de comparar, también de forma condicionada;
 *  - **uno vivo por (correo, propósito)**: pedir otro borra el anterior.
 *
 * ⚠️ Se guarda la HUELLA —HMAC-SHA256 con la clave de la app sobre el propósito, el correo y el código—, nunca el código:
 * quien lea la tabla no puede entrar, y un código de entrar no sirve para confirmar ni para otro correo. Seis cifras
 * son pocas para que un hash sin clave las protegiera (un millón de candidatos); la clave es lo que las protege.
 */
class LoginCodes
{
    /** Cifras del código. */
    public const LENGTH = 6;

    /** Minutos que vale un código. */
    public const TTL_MINUTES = 10;

    /** Intentos que admite un código antes de morir. */
    public const MAX_ATTEMPTS = 5;

    /**
     * **«482-913»: el código tal como se ENSEÑA**, en el asunto y como titular de los tres correos que lo llevan: dos grupos
     * de tres con guion, para leerlo en el aviso del móvil y dictarlo (el diseño, zip (6), correo 8; el owner, `#867`). Las
     * casillas de la isla y del cajón pintan el mismo guion sin que se escriba, y {@see consume()} acepta las dos formas.
     */
    public static function shown(string $code): string
    {
        return substr($code, 0, 3).'-'.substr($code, 3);
    }

    /**
     * Emite un código nuevo para (correo, propósito) y anula los anteriores. Devuelve el código EN CLARO: su único
     * destino es el correo, y quien llama no lo guarda en ninguna parte.
     */
    public function issue(string $email, string $purpose, string $ip): string
    {
        $email = self::normalizeEmail($email);
        $code = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($email, $purpose, $ip, $code): void {
            // Uno vivo por (correo, propósito): el anterior deja de valer aunque no hubiera caducado.
            LoginCode::query()->where('email', $email)->where('purpose', $purpose)->delete();

            LoginCode::query()->create([
                'email' => $email,
                'purpose' => $purpose,
                'code_hash' => self::fingerprint($email, $purpose, $code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
                'ip' => $ip !== '' ? mb_substr($ip, 0, 45) : null,
            ]);
        });

        return $code;
    }

    /**
     * ¿Casa `$code` con el código vivo de (correo, propósito)? Si casa, queda GASTADO. Si no, se ha gastado un intento.
     *
     * Sin código vivo —nunca pedido, caducado, usado o sin intentos— devuelve `false` igual que un código equivocado: a
     * quien prueba no le importa por qué no entra, y distinguirlo no le daría nada al legítimo que no le dé «pide otro».
     */
    public function consume(string $email, string $purpose, string $code): bool
    {
        $email = self::normalizeEmail($email);
        // «482 913» o «482-913» son el mismo código: el correo lo enseña partido para leerlo.
        $code = (string) preg_replace('/[\s\-]+/u', '', $code);

        $live = LoginCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->latest('id')
            ->first();

        if ($live === null) {
            return false;
        }

        // ⚠️⚠️ El intento se GASTA ANTES de comparar, y solo si quedan: sin la condición, N peticiones a la vez leerían
        // «quedan intentos» y compararían todas —el tope de 5 sería de 5 por ronda, no por código—.
        $spent = LoginCode::query()
            ->whereKey($live->getKey())
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->increment('attempts');

        if ($spent !== 1 || ! hash_equals($live->code_hash, self::fingerprint($email, $purpose, $code))) {
            return false;
        }

        // ⚠️⚠️ Y el uso, igual: dos peticiones con el código bueno a la vez pasan la comparación las dos, pero solo UNA
        // lo marca como usado. La otra no entra.
        return LoginCode::query()
            ->whereKey($live->getKey())
            ->whereNull('used_at')
            ->update(['used_at' => now()]) === 1;
    }

    /** La huella: HMAC con la clave de la app sobre propósito, correo y código. */
    private static function fingerprint(string $email, string $purpose, string $code): string
    {
        return hash_hmac('sha256', $purpose.'|'.$email.'|'.$code, (string) config('app.key'));
    }

    /** El mismo correo escrito de dos maneras es el mismo correo (como en el login y el alta). */
    private static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
