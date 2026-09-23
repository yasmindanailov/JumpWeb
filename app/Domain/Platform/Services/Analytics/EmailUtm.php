<?php

namespace App\Domain\Platform\Services\Analytics;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * **LAS UTM DE LOS CORREOS** (`docs/specs/analitica.md` §4.1 «Los correos», `#678`, T1c).
 *
 * Cada enlace a ESTA casa que viaja en un correo al cliente lleva `utm_source=email&utm_medium=<clave>`, y la
 * clave es la del correo (`order_confirmation`, `guest_form_request`, `password_reset`…), derivada del nombre
 * de su clase: una convención, no un parámetro que se pueda olvidar. Con ella la sesión que abre el clic se
 * atribuye al correo que la trajo, y `email_sent` (`RecordEmailSent`) y `email_clicked` (`RecordEmailClick`)
 * cuentan con la MISMA clave, así que el panel puede decir «de cada cien confirmaciones vuelven doce».
 *
 * ⚠️⚠️ **El UTM se PEGA DESPUÉS de firmar, y la validación lo IGNORA.** La spec decía «antes de firmar, y por
 * defensa `ignoreQuery`», y las dos cosas a la vez no pueden ser: el HMAC de `signedRoute()` cubre la query
 * ENTERA y `hasCorrectSignature()` RETIRA las claves ignoradas antes de recalcularlo (leído en el framework al
 * hacer la T1c). Un enlace firmado con el UTM dentro y validado ignorándolo daría **403 justo al padre que
 * abre el justificante**. Pegarlo después y declararlo ignorado —`validateSignatures(except:)` para el
 * middleware `signed`, `hasValidSignatureWhileIgnoring()` en los accesos por firma— hace válida la URL con o
 * sin él, y también cuando un gestor de correo o un proxy añadan uno suyo de la misma lista. Ninguna de esas
 * claves decide nada: son atribución, y el servidor las acota y las vacía si parecen dato personal.
 *
 * ⚠️ Solo los enlaces a esta casa (`APP_URL`): un enlace externo no lleva nuestro UTM. Y el fragmento
 * (`#gf-invite`) se conserva detrás, que es donde el navegador lo lee.
 * ⚠️ Quedan fuera los avisos al NEGOCIO: los dos `Mailable` con vista propia no pasan por el molde, y
 * `GoogleBusinessLocationChanged` se excluye por clave. El equipo no es audiencia.
 */
final class EmailUtm
{
    public const SOURCE = 'email';

    /**
     * Lo que la validación de una firma IGNORA: la lista blanca de atribución, la misma que la ingesta admite
     * en una ruta (`RouteNormalizer`). Una clave fuera de esta lista pegada a un enlace firmado sigue dando 403.
     *
     * @var list<string>
     */
    public const IGNORED_QUERY = RouteNormalizer::QUERY_ALLOWLIST;

    /**
     * Correos que NO lee un cliente: sin UTM y sin `email_sent`.
     *
     * @var list<string>
     */
    public const NOT_TO_CUSTOMERS = ['google_business_location_changed'];

    /** La clave de un correo: `OrderConfirmation` → `order_confirmation`. */
    public static function keyOf(Notification|string $notification): string
    {
        return Str::snake(class_basename($notification));
    }

    /** ¿Es la clave de un correo que lee un cliente? Solo con ellas se etiqueta, se cuenta y se acepta un clic. */
    public static function isCustomerKey(string $key): bool
    {
        return ! in_array($key, self::NOT_TO_CUSTOMERS, true) && in_array($key, self::keys(), true);
    }

    /**
     * Las claves de TODOS los correos de `app/Notifications`, leídas del disco una vez por proceso: es lo que
     * hace que una clave fabricada en una URL (`utm_medium=lo-que-sea`) no cuente como un correo.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        static $keys = null;

        return $keys ??= array_map(
            static fn (string $file): string => Str::snake(basename($file, '.php')),
            glob(app_path('Notifications/*.php')) ?: [],
        );
    }

    /** @return array{utm_source: string, utm_medium: string} */
    public static function params(string $key): array
    {
        return ['utm_source' => self::SOURCE, 'utm_medium' => $key];
    }

    /**
     * La URL con el UTM pegado al final — si es de esta casa, hay clave y no lo llevaba ya. Una URL FIRMADA
     * sale igual de válida: sus claves van declaradas como ignoradas (ver la cabecera).
     */
    public static function tag(string $url, ?string $key): string
    {
        if ($key === null || $key === '' || ! self::isOurs($url) || str_contains($url, 'utm_source=')) {
            return $url;
        }

        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $base .= (str_contains($base, '?') ? '&' : '?').http_build_query(self::params($key));

        return $fragment === null ? $base : $base.'#'.$fragment;
    }

    private static function isOurs(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return str_starts_with($url, '/');
        }

        return strcasecmp($host, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) === 0;
    }
}
