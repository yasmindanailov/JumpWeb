<?php

namespace App\Domain\Platform\Services;

/**
 * **Cómo se escribe un número en el idioma de quien lo lee** (F5 · T2b, `DECISIONES #651`).
 *
 * `Money::showcase()` ya llevaba esta regla y su docblock la resume mejor que nadie: *«el sitio donde
 * se decide cómo se escribe un importe es UNO»*. Pero era UNO **para los importes**: la nota de las
 * reseñas de la portada escribía sus números con los separadores del español **fijos**, en las tres
 * versiones del sitio.
 *
 * ❗❗ **Medido en el navegador el 19-09, con la landing en inglés**: la nota salía `4,8` —que en
 * inglés no es cuatro coma ocho, es otra cosa— y el recuento `1.234 reviews`, que se lee *uno coma
 * doscientos treinta y cuatro*. No fallaba nada: un número mal escrito se pinta igual de bien.
 *
 * ⚠️⚠️ **Y por eso esto es T2b y no una erratita**: la regla vivía DENTRO de `home.blade.php`, que es
 * una vista que se va a la instancia. El día de la mudanza se iba con ella, y cada landing la
 * re-derivaría — la mayoría, mal, porque en español y francés la coma es correcta y el defecto solo
 * se ve en inglés.
 */
final class LocalNumber
{
    /**
     * El separador DECIMAL del idioma activo.
     *
     * ⚠️ Inglés aparte, las lenguas del sitio (español y francés) usan la coma, así que la pregunta
     * es por el inglés y no por una lista de idiomas que habría que mantener.
     */
    public static function decimalSeparator(): string
    {
        return app()->getLocale() === 'en' ? '.' : ',';
    }

    /** El separador de MILLARES del idioma activo: el espejo del anterior. */
    public static function thousandsSeparator(): string
    {
        return app()->getLocale() === 'en' ? ',' : '.';
    }

    /** Un número con decimales, escrito en el idioma activo (una nota: `4,8` / `4.8`). */
    public static function decimal(float $valor, int $decimales = 1): string
    {
        return number_format($valor, $decimales, self::decimalSeparator(), self::thousandsSeparator());
    }

    /** Un recuento entero, escrito en el idioma activo (`1.234` / `1,234`). */
    public static function count(int $valor): string
    {
        return number_format($valor, 0, self::decimalSeparator(), self::thousandsSeparator());
    }
}
