<?php

namespace App\Domain\Booking\Services;

use App\Domain\Platform\Models\Setting;

/**
 * Ajustes de cómo se PRESENTA la disponibilidad de una franja (`DECISIONES #239`).
 *
 * Helper DEFENSIVO, mismo patrón que `CatalogSettings`/`PaymentSettings`/`PuertaSettings`: lee el
 * setting con tipo + rango + fallback no destructivo. Si la fila falta o el valor está corrupto,
 * devuelve el default sin lanzar.
 *
 * ⚠️ **Esto no decide NADA de aforo** (`AFORO-02`). Cuántas plazas quedan lo dice `SlotOffer` y
 * viaja en `available`; aquí solo vive el número a partir del cual una hora **se anuncia** como casi
 * llena. Es un ajuste de escaparate, no una regla de venta: subirlo a 100 no vende una plaza de más
 * ni de menos, solo cambia cuántas horas llevan el aviso.
 */
class AvailabilitySettings
{
    /** Clave del umbral (plazas libres) por debajo del cual una hora se anuncia «casi llena». */
    public const LOW_MAX_KEY = 'booking.low_availability_max';

    /**
     * Default: 8 plazas.
     *
     * Sale de los aforos reales de la instalación de referencia —40 (Jump), 25 (Kids), 60 en la zona
     * de packs—: 8 es el 20 % del aforo mayor y el 32 % del menor, o sea «se está acabando» en las
     * dos sin que el aviso salga el día entero.
     */
    public const LOW_MAX_DEFAULT = 8;

    /**
     * `0` es una respuesta, no una falta: **desactiva el aviso**.
     *
     * ⚠️ Y por eso la comparación de {@see isLow()} exige `> 0` antes de comparar, en vez de dejar
     * que `available <= 0` lo resuelva solo: una franja ofrecida con cero plazas libres no debería
     * existir, pero si existiera, el operador que escribió «0» pidió silencio, no un aviso.
     */
    public const LOW_MAX_MIN = 0;

    /** Tope sano: por encima del aforo mayor de una zona el aviso saldría siempre y dejaría de avisar. */
    public const LOW_MAX_MAX = 100;

    /**
     * Umbral del aviso «casi llena»: una hora lo lleva cuando sus plazas libres son **menores o
     * iguales** que este número. Clamp a [MIN, MAX]; fallback al default si falta o está corrupto.
     */
    public static function lowMax(): int
    {
        $raw = Setting::value(self::LOW_MAX_KEY);

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return self::LOW_MAX_DEFAULT;
        }

        return max(self::LOW_MAX_MIN, min(self::LOW_MAX_MAX, (int) $raw));
    }

    /**
     * ¿Esta franja se anuncia como casi llena?
     *
     * ⚠️ **Vive aquí y no solo en el cliente aunque hoy quien lo pinta sea el cajón.** El operador
     * verá el mismo aviso en el panel el día que «Crear pedido» ofrezca horas, y dos redacciones de
     * la misma comparación es exactamente como divergen: el contrato de `/config` publica el umbral
     * **con su operador escrito** para que el cajón compare igual, y esta función es la referencia.
     */
    public static function isLow(int $available, ?int $lowMax = null): bool
    {
        $threshold = $lowMax ?? self::lowMax();

        return $threshold > 0 && $available <= $threshold;
    }
}
