<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\Setting;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Formatea timestamps en la **zona horaria de presentación** del parque.
 *
 * Arquitectura (decisión #111, 2026-05-28):
 *  - La BD almacena en **UTC** (Laravel estándar; `config('app.timezone') = 'UTC'`).
 *    Es lo correcto para white-label: otro parque en otro país no requiere migrar datos.
 *  - La **presentación** al cliente se hace en la zona horaria del parque, configurada
 *    en `settings.display_timezone` (default `Europe/Madrid`, editable en panel Fase 7).
 *  - Si el setting trae un valor inválido o vacío, hacemos **fallback no destructivo** al
 *    default — nunca lanzamos excepción al renderizar una vista.
 *
 * Uso desde Blade:
 *   {{ \App\Domain\Platform\Services\DisplayTime::format($order->created_at) }}
 *   {{ \App\Domain\Platform\Services\DisplayTime::format($consent->accepted_at, 'd/m/Y') }}
 *
 * Uso desde notificaciones / lógica de servicio:
 *   ->line(__('foo.bar', ['when' => DisplayTime::format($order->paid_at)]))
 */
class DisplayTime
{
    /** Zona horaria por defecto si el setting está vacío o es inválido. */
    public const DEFAULT_TIMEZONE = 'Europe/Madrid';

    /** Formato por defecto (DD/MM/AAAA HH:MM). */
    public const DEFAULT_FORMAT = 'd/m/Y H:i';

    /**
     * Convierte un timestamp al timezone de presentación y lo formatea.
     *
     * Acepta `DateTimeInterface`, string parseable por Carbon, o null.
     * `null`/inválido → string vacío (la vista decide qué mostrar en su lugar).
     */
    public static function format(DateTimeInterface|string|null $value, string $format = self::DEFAULT_FORMAT): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $carbon = $value instanceof DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse($value);
        } catch (\Throwable) {
            return '';
        }

        return $carbon->setTimezone(self::timezone())->format($format);
    }

    /**
     * "Ahora" en la zona horaria operativa del parque (no en UTC).
     *
     * La BD guarda en UTC (#111), pero el calendario OPERATIVO (qué día/hora es "hoy" para
     * ofrecer franjas, podar, validar fecha pasada) debe razonar en la zona del parque
     * (`Europe/Madrid` por defecto). Si no, cerca de medianoche el "hoy" en UTC va 1-2 h por
     * detrás del día local y se ofrece/poda un día que en el parque ya terminó (auditoría Fase 1).
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /** "Hoy" (medianoche) en la zona horaria operativa del parque. Respeta `Carbon::setTestNow`. */
    public static function today(): Carbon
    {
        return Carbon::today(self::timezone());
    }

    /**
     * Zona horaria de presentación configurada (con fallback no destructivo).
     *
     * Validamos contra `DateTimeZone` para no aceptar valores que romperían
     * `Carbon::setTimezone()` al renderizar una vista. Si el setting está
     * corrupto, log de WARNING en `Setting::value` no aplica (Setting es un
     * simple lookup) — el helper lo absorbe en silencio devolviendo el default.
     */
    public static function timezone(): string
    {
        $tz = trim((string) Setting::value('display_timezone'));
        if ($tz === '') {
            return self::DEFAULT_TIMEZONE;
        }

        if (! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            return self::DEFAULT_TIMEZONE;
        }

        return $tz;
    }
}
