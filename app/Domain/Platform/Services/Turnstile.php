<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Anti-bot Cloudflare Turnstile, activado por clave en `settings` (#43).
 * Sin claves configuradas se desactiva solo: el registro funciona igual.
 * Claves: `security.turnstile_site_key` (pública) y `security.turnstile_secret`.
 *
 * Las claves se leen UNA vez por petición (memoización): evita repetir consultas
 * en cada render del modal (que en local hacían lento el ciclo de Livewire).
 */
class Turnstile
{
    /**
     * @var array{site: ?string, secret: ?string}|null
     *
     * ⚠️ Es un memo ESTÁTICO: sobrevive a la petición, al objeto y al test siguiente del mismo
     * proceso. `SUITE-02` lo tiene documentado para `Setting::$memo` («si se añade OTRO memo
     * estático, necesita su flush en `TestCase::setUp()`») y este se añadió sin él — hasta que dos
     * ficheros de test acabaron con la misma copia de un reset por Reflection. Ver {@see flushCache}.
     */
    private static ?array $cache = null;

    /**
     * @return array{site: ?string, secret: ?string}
     */
    private static function keys(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            self::$cache = [
                'site' => Setting::value('security.turnstile_site_key') ?: null,
                'secret' => Setting::value('security.turnstile_secret') ?: null,
            ];
        } catch (Throwable) {
            // La tabla `settings` aún no existe (migraciones/CI): anti-bot desactivado.
            self::$cache = ['site' => null, 'secret' => null];
        }

        return self::$cache;
    }

    /**
     * Olvida las claves memoizadas.
     *
     * Existe por el mismo motivo que `Setting::flushMemo()` (`SUITE-02`): el rollback de
     * `RefreshDatabase` no dispara eventos de modelo, así que sin esto unas claves configuradas por
     * un test contaminan a los siguientes del mismo proceso — y el resultado depende del ORDEN, que
     * es la peor forma de fallo. Lo llama `TestCase::setUp()`.
     *
     * En producción no hace falta llamarlo: cada petición es un proceso nuevo. Si algún día se
     * pudieran editar las claves en caliente dentro de la misma petición, este es el punto por el
     * que se enteraría.
     */
    public static function flushCache(): void
    {
        self::$cache = null;
    }

    public static function siteKey(): ?string
    {
        return self::keys()['site'];
    }

    public static function secret(): ?string
    {
        return self::keys()['secret'];
    }

    /** Solo está activo si hay clave pública y secreta configuradas. */
    public static function enabled(): bool
    {
        $keys = self::keys();

        return $keys['site'] !== null && $keys['secret'] !== null;
    }

    /** Verifica el token del widget contra Cloudflare. */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        if (! self::enabled()) {
            return true;
        }

        if (empty($token)) {
            return false;
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => self::secret(),
            'response' => $token,
            'remoteip' => $ip,
        ]);

        $ok = $response->successful() && ($response->json('success') === true);

        // Diagnóstico: si Cloudflare rechaza, registrar el error-code y el hostname que reporta.
        // Causas típicas: `invalid-input-response` (token de OTRO widget o caducado), hostname no
        // permitido en el widget, `invalid-input-secret`. Ayuda a distinguir config de Cloudflare.
        if (! $ok) {
            Log::warning('turnstile.verify_failed', [
                'http' => $response->status(),
                'errors' => $response->json('error-codes'),
                'hostname' => $response->json('hostname'),
            ]);
        }

        return $ok;
    }
}
