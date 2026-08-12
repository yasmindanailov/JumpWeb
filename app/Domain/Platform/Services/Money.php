<?php

namespace App\Domain\Platform\Services;

/**
 * Formateo ÚNICO de importes en céntimos al estilo ES ("1.234,56 €").
 *
 * Antes este mismo `number_format($cents / 100, 2, ',', '.')` estaba reescrito como closure
 * local (`$fmt`/`$eur`/`$euros`/`$money`) en ~10 vistas y en {@see ReservationSlip::money()}
 * (auditoría de organización: 25 ocurrencias del patrón). Tenerlo en un solo sitio significa
 * que el separador, el redondeo y el símbolo de moneda viven en UN lugar — clave para el
 * principio white-label / multi-moneda (que hoy ya asoma en order-totals/payments-list).
 *
 * NO cambia ningún importe: replica EXACTAMENTE el formato previo (pinned por MoneyTest).
 */
class Money
{
    /**
     * Importe en céntimos → "1.234,56 €" (o "… USD" si la moneda no es EUR).
     * EUR usa el símbolo €; cualquier otra moneda muestra su código ISO tras el número
     * (mismo criterio que la card de totales y el timeline de pagos del panel).
     */
    public static function format(int $cents, string $currency = 'EUR'): string
    {
        return self::amount($cents).' '.self::symbol($currency);
    }

    /**
     * Importe en céntimos → "1.234,56" SIN sufijo de moneda (para las vistas que colocan
     * el "€" aparte en el markup, p. ej. los carritos del wizard y del alta manual).
     */
    public static function amount(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    /** Símbolo a mostrar para una moneda: € para EUR, el propio código en otro caso. */
    public static function symbol(string $currency): string
    {
        return $currency === 'EUR' ? '€' : $currency;
    }
}
