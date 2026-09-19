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

    /**
     * **Importe de ESCAPARATE**: sin decimales cuando son cero y con el separador del IDIOMA.
     *
     * ⚠️⚠️ **No compite con `amount()`: es el otro registro del mismo idioma.** Aquélla escribe
     * importes de TRANSACCIÓN —carrito, pedido, libro, factura— y por eso lleva siempre dos
     * decimales y separadores fijos: en una liquidación «10 €» sería una cifra a medio escribir.
     * Ésta escribe los del CATÁLOGO, que se leen de un vistazo y donde dos ceros a la derecha solo
     * añaden ruido. *El mismo número no se escribe igual en un precio anunciado que en uno cobrado.*
     *
     * ⚠️ El separador decimal es del idioma —«14,95» en español y francés, «14.95» en inglés—, no
     * del dato. Nació en `#478` dentro de `ZoneCards` y sube aquí en `#479` al ganar el segundo y el
     * tercer consumidor: **el sitio donde se decide cómo se escribe un importe es UNO**.
     */
    public static function showcase(int $cents): string
    {
        // ⚠️ El separador decimal lo decide `LocalNumber` desde `#651`: la regla era la misma aquí y
        // en la nota de las reseñas, y allí estaba escrita a mano con el español fijo.
        //
        // ⚠️⚠️ **El de MILLARES sigue siendo `'.'` a mano, y NO es un descuido**: cambiarlo movería
        // importes escritos en la web inglesa (`1.234,00 €` pasaría a `1,234.00 €`), y eso es dinero
        // a la vista de un cliente. Queda anotado como pendiente del owner; hasta entonces, esta
        // llamada imprime EXACTAMENTE lo que imprimía antes.
        return number_format(
            $cents / 100,
            $cents % 100 === 0 ? 0 : 2,
            LocalNumber::decimalSeparator(),
            '.',
        );
    }

    /** Símbolo a mostrar para una moneda: € para EUR, el propio código en otro caso. */
    public static function symbol(string $currency): string
    {
        return $currency === 'EUR' ? '€' : $currency;
    }
}
