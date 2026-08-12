<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Helpers defensivos para los settings críticos del módulo de pagos.
 *
 * Audit hardening #113 (2026-05-28, M2): el panel admin (Fase 7) o un acceso directo a la
 * tabla `settings` permite escribir cualquier valor. Si alguien mete `'abc'` en
 * `sales.hold_minutes`, `(int) Setting::value(...)` devolvería `0` → todas las Orders
 * caducarían inmediatamente. Mismo con `redsys_currency='abc'` → Redsys rechazaría con
 * SIS0027 al iniciar el pago.
 *
 * Este helper:
 *  - lee el setting con su default sensato,
 *  - valida tipo/rango,
 *  - en caso de valor inválido, devuelve el DEFAULT (fallback no destructivo: la vista no
 *    debe romper, ni el flujo de pago debe abortar, por un setting corrupto).
 *
 * Mismo patrón que `DisplayTime::timezone()` (#111).
 */
class PaymentSettings
{
    /** Retención de plaza durante el flujo de pago. Default 20 min (#113, buffer C1). */
    public const HOLD_MINUTES_DEFAULT = 20;

    public const HOLD_MINUTES_MIN = 1;

    public const HOLD_MINUTES_MAX = 240;

    /** ISO-4217 numérico de 3 dígitos. Default '978' (EUR). */
    public const CURRENCY_DEFAULT = '978';

    /**
     * Horizonte de compra en meses (sub-fase 7.2e.2bis6, decisión #160).
     * Default 6 meses — restricción operativa del negocio: ninguna entrada o
     * reserva (cliente o panel) puede agendarse más allá de `today + N meses`.
     * Aplicado en `availableDatesForItem` (UI selector fecha) + `validateNewSlot`
     * (defense in depth backend).
     */
    public const PURCHASE_HORIZON_MONTHS_DEFAULT = 6;

    public const PURCHASE_HORIZON_MONTHS_MIN = 1;

    public const PURCHASE_HORIZON_MONTHS_MAX = 24;

    /**
     * Minutos de retención de plaza durante el pago Redsys.
     *
     * Manual Redsys §2.3: el timeout del TPV es habitualmente 15 min en sandbox y puede
     * variar en producción según el banco. La retención debe ser ≥ ese timeout (#62 §14.8)
     * con margen suficiente para tolerar latencias de notificación on-line (#113, C1).
     */
    public static function holdMinutes(): int
    {
        $raw = Setting::value('sales.hold_minutes', (string) self::HOLD_MINUTES_DEFAULT);

        $n = filter_var($raw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::HOLD_MINUTES_MIN, 'max_range' => self::HOLD_MINUTES_MAX],
        ]);

        return $n !== false ? $n : self::HOLD_MINUTES_DEFAULT;
    }

    /**
     * Divisa Redsys en formato ISO-4217 numérico (3 dígitos).
     *
     * Por contrato debe ser '978' (EUR) para España. Si el panel admin escribe algo
     * inválido, fallback a '978' para no romper el flujo de pago.
     */
    public static function redsysCurrency(): string
    {
        $raw = (string) Setting::value('redsys_currency', self::CURRENCY_DEFAULT);

        return (ctype_digit($raw) && strlen($raw) === 3) ? $raw : self::CURRENCY_DEFAULT;
    }

    /**
     * Horizonte máximo de compra/edición en meses (sub-fase 7.2e.2bis6, #160).
     *
     * Ninguna entrada o reserva puede agendarse más allá de `today + N meses`.
     * Restricción operativa del negocio: el parque no acepta reservas a más
     * de 6 meses por defecto (cancelaciones bajas, datos de horario futuros
     * inciertos). Configurable por el panel admin.
     */
    public static function purchaseHorizonMonths(): int
    {
        $raw = Setting::value(
            'sales.purchase_horizon_months',
            (string) self::PURCHASE_HORIZON_MONTHS_DEFAULT,
        );

        $n = filter_var($raw, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => self::PURCHASE_HORIZON_MONTHS_MIN,
                'max_range' => self::PURCHASE_HORIZON_MONTHS_MAX,
            ],
        ]);

        return $n !== false ? $n : self::PURCHASE_HORIZON_MONTHS_DEFAULT;
    }
}
