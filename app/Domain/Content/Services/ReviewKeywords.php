<?php

namespace App\Domain\Content\Services;

use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Str;

/**
 * **Las palabras de la reseña del día de la Puerta, y si un texto dice alguna** (`docs/specs/puerta-nueva.md` §4.4, la
 * P3: D17 y D18; el owner, `#817`: «profesional y robusto»).
 *
 * El producto no sabe de qué habla una reseña: el parque escribe en su panel las palabras que la delatan («monitor»,
 * «equipo», el nombre de pila de quien atiende), una por línea y en cualquier idioma, y aquí se decide si un texto dice
 * alguna. Es un valor PURO —sin base ni fachadas fuera de {@see fromSettings()}—: su prueba vive en `tests/Unit`.
 *
 * ▶ **Las dos partes se pliegan IGUAL** ({@see fold()}): minúsculas y cada carácter por `Str::ascii` —la convención de la
 * casa, `PersonNameKey`—, que quita tildes y diéresis, la ñ, la ß, la tilde DESCOMPUESTA y translitera el cirílico.
 * ⚠️⚠️ **Y lo que `Str::ascii` vacía se queda TAL CUAL** (medido el 02-10: el chino, el tailandés, el hebreo y los emoji
 * salen vacíos): plegar el texto entero de una vez borraría una palabra china de los dos lados y nada casaría con ella. Sin
 * quitarle marcas: quitárselas le quitaba las vocales al tailandés. Y sin `intl`, que `composer.json` no exige.
 *
 * ▶ **Por PRINCIPIO de palabra** (D18): sin una letra ni un número delante, y la última palabra puede seguir —«monitor»
 * casa «monitora» y «monitores», y no «desmonitor»—. Una entrada de varias palabras casa si van seguidas, con cualquier
 * espacio entre ellas. ⚠️ En una escritura SIN espacios entre palabras (chino, japonés, tailandés…) no hay principio de
 * palabra que buscar: allí casa dentro del texto.
 */
final class ReviewKeywords
{
    /** El ajuste del panel («Ajustes → Avanzado → Puerta»): una palabra o frase por línea. */
    public const KEY = 'puerta.review_keywords';

    /** Cuántas entradas, como mucho (D17): las que lee la Puerta y las que deja guardar el panel. */
    public const MAX_ENTRIES = 40;

    /** Cuántos caracteres por entrada, como mucho (D17): lo vigila el formulario del panel. */
    public const MAX_LENGTH = 60;

    /** Las escrituras que no separan las palabras con espacios: en ellas, dentro del texto (D18). */
    private const SIN_ESPACIOS = '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}\p{Lao}\p{Khmer}\p{Myanmar}]/u';

    /** @param  list<string>  $patrones  uno por entrada, sobre el texto PLEGADO */
    private function __construct(private readonly array $patrones) {}

    /** Las palabras tal como las dejó el panel. */
    public static function fromSettings(): self
    {
        return self::from((string) Setting::value(self::KEY, ''));
    }

    /** Las palabras de un texto con una por línea. */
    public static function from(string $raw): self
    {
        $patrones = [];
        foreach (self::entries($raw) as $entrada) {
            $plegada = self::fold($entrada);
            $palabras = preg_split('/\s+/u', $plegada, -1, PREG_SPLIT_NO_EMPTY);
            if (! is_array($palabras) || $palabras === []) {
                continue;
            }

            $frase = implode('\s+', array_map(static fn (string $p): string => preg_quote($p, '/'), $palabras));
            $patrones[] = preg_match(self::SIN_ESPACIOS, $plegada) === 1
                ? '/'.$frase.'/u'
                : '/(?<![\p{L}\p{N}])'.$frase.'/u';
        }

        return new self($patrones);
    }

    /**
     * **Las entradas, limpias** (D17): una por línea, sin espacios de sobra, sin líneas vacías y sin repetidas por su forma
     * PLEGADA («Monitor» y «monitor » son la misma); se queda la primera tal como se escribió, y como mucho
     * {@see MAX_ENTRIES}.
     *
     * @return list<string>
     */
    public static function entries(string $raw): array
    {
        $lineas = preg_split('/\R/u', $raw);
        $vistas = [];
        $entradas = [];
        foreach (is_array($lineas) ? $lineas : [] as $linea) {
            $entrada = trim((string) preg_replace('/\s+/u', ' ', $linea));
            $clave = self::fold($entrada);
            if ($entrada === '' || $clave === '' || isset($vistas[$clave])) {
                continue;
            }

            $vistas[$clave] = true;
            $entradas[] = $entrada;
        }

        return array_slice($entradas, 0, self::MAX_ENTRIES);
    }

    /** El ajuste, limpio para guardarlo: sus entradas, una por línea. */
    public static function clean(string $raw): string
    {
        return implode("\n", self::entries($raw));
    }

    /**
     * **¿Pasa del tope de entradas?** Lo pregunta el formulario ANTES de guardar: recortar en silencio dejaría fuera las
     * últimas sin decirlo, y el parque no sabría por qué una palabra no hace nada. Cuenta las líneas con algo escrito.
     */
    public static function tooMany(string $raw): bool
    {
        $lineas = preg_split('/\R/u', $raw);

        return count(array_filter(is_array($lineas) ? $lineas : [], static fn (string $l): bool => trim($l) !== '')) > self::MAX_ENTRIES;
    }

    /** La primera entrada que pasa de {@see MAX_LENGTH} caracteres, o `null`. Lo pregunta el formulario, como el tope. */
    public static function tooLong(string $raw): ?string
    {
        $lineas = preg_split('/\R/u', $raw);
        foreach (is_array($lineas) ? $lineas : [] as $linea) {
            $entrada = trim((string) preg_replace('/\s+/u', ' ', $linea));
            if (mb_strlen($entrada) > self::MAX_LENGTH) {
                return $entrada;
            }
        }

        return null;
    }

    /** ¿No hay ninguna palabra? Sin palabras, la Puerta no enseña ninguna reseña (Q4 descartó «cualquiera reciente»). */
    public function isEmpty(): bool
    {
        return $this->patrones === [];
    }

    /** ¿El texto dice alguna de las palabras? */
    public function matches(string $text): bool
    {
        if ($this->patrones === []) {
            return false;
        }

        $plegado = self::fold($text);
        foreach ($this->patrones as $patron) {
            if (preg_match($patron, $plegado) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * **El texto plegado**: en minúsculas y cada carácter (cada grafema: la tilde descompuesta va con su letra) por
     * `Str::ascii`, o tal cual si lo vacía. Un byte roto no tumba nada: `mb_strtolower` lo cambia por «?» y el resto se lee
     * (medido el 02-10).
     */
    public static function fold(string $text): string
    {
        $text = mb_strtolower($text);
        if (preg_match('/^[\x00-\x7F]*$/', $text) === 1) {
            return $text;
        }

        return (string) preg_replace_callback('/\X/u', static function (array $m): string {
            $grafema = $m[0];
            if (strlen($grafema) === 1) {
                return $grafema;
            }

            $ascii = Str::ascii($grafema);

            return $ascii !== '' ? strtolower($ascii) : $grafema;
        }, $text);
    }
}
