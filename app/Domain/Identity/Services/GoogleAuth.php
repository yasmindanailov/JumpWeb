<?php

namespace App\Domain\Identity\Services;

use App\Domain\Platform\Models\Setting;
use Throwable;

/**
 * Las claves de Google POR INSTALACIÓN (`docs/specs/auth-con-google.md` §10).
 *
 * Hermano exacto del servicio `Turnstile` de Platform —nombrado en prosa: una anotación resoluble
 * acaba siendo un `use` en manos de Pint (`#320`), y aquí no se usa esa clase— y por el mismo motivo:
 * es configuración de un cliente, no del producto. **Una instalación = un proyecto de Google = un ID
 * de cliente**, así que el repo no puede llevar ninguna de las dos.
 *
 * ⚠️ **Exige las DOS claves para darse por activo.** Es la lección de `PublicConfigResource`: con
 * media configuración, el botón aparecería y el canje fallaría en el peor momento posible — después
 * de que la persona haya elegido su cuenta en Google. Sin claves el hueco **falla hacia invisible**,
 * como el logotipo, el icono, el kit y la foto del menú.
 *
 * ⚠️⚠️ **Viven en `settings` y eso tiene un coste ESCRITO** (`[DECIDIDO owner]` Q10): el propio repo
 * dice de la clave de Redsys que ponerla en esa tabla «la deja en una tabla que se vuelca en cada
 * backup». El owner asume ese coste a cambio de aprovisionar sin tocar el `.env` — y por eso las dos
 * claves entran en `SetSetting::PROTECTED_KEYS`, que es como este repo dice «esto cuesta caro tocarlo
 * a ciegas».
 */
final class GoogleAuth
{
    public const CLIENT_ID_KEY = 'auth.google_client_id';

    public const CLIENT_SECRET_KEY = 'auth.google_client_secret';

    /**
     * @var array{id: ?string, secret: ?string}|null
     *
     * ⚠️ Memo ESTÁTICO: sobrevive a la petición y al test siguiente del mismo proceso. `SUITE-02`
     * exige su purga en `TestCase::setUp()` — sin ella, unas claves puestas por un test decidirían
     * el resultado de los siguientes según el ORDEN en que corrieran, que es la peor forma de fallo.
     */
    private static ?array $cache = null;

    /**
     * @return array{id: ?string, secret: ?string}
     */
    private static function keys(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            self::$cache = [
                'id' => Setting::value(self::CLIENT_ID_KEY) ?: null,
                'secret' => Setting::value(self::CLIENT_SECRET_KEY) ?: null,
            ];
        } catch (Throwable) {
            // La tabla `settings` todavía no existe (migraciones/CI): entrar con Google, desactivado.
            self::$cache = ['id' => null, 'secret' => null];
        }

        return self::$cache;
    }

    /** Ver el aviso de {@see self::$cache}. Lo llama `TestCase::setUp()`. */
    public static function flushCache(): void
    {
        self::$cache = null;
    }

    public static function clientId(): ?string
    {
        return self::keys()['id'];
    }

    public static function clientSecret(): ?string
    {
        return self::keys()['secret'];
    }

    /** Las DOS o ninguna. Ver el aviso de la cabecera. */
    public static function enabled(): bool
    {
        $keys = self::keys();

        return $keys['id'] !== null && $keys['secret'] !== null;
    }
}
