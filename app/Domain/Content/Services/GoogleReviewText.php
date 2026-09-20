<?php

namespace App\Domain\Content\Services;

/**
 * **El texto de una reseña, separado de la traducción que Google le mete dentro** (T2·2,
 * `docs/specs/google-business-profile.md` §4.3·8; `DECISIONES #524`, `#728`).
 *
 * Cuando el autor escribió en otro idioma, `reviews.list` devuelve **las dos versiones en el mismo
 * campo** y marcadas con unos paréntesis que **no están documentados** en ninguna parte de la API:
 *
 *     (Translated by Google) Great park for kids (Original) Gran parque para niños
 *
 * Se publica **el original**, que es lo que el autor escribió y lo único sobre lo que se puede decir
 * «esto lo dijo esta persona». La traducción es de Google y se descarta.
 *
 * ❗❗❗ **FALLA CERRADO, y eso es lo que hace de esto una guarda y no un adorno.** El formato no está
 * documentado —§4.3·8 dice literalmente que el orden varía—, así que cualquier cosa que no se pueda
 * separar **con certeza** se guarda CRUDA y se marca. Lo caro sería lo contrario: partir por una
 * heurística optimista y publicar como palabras del autor **un texto que escribió una máquina**, con
 * su nombre y su cara al lado. Un texto crudo marcado se ve raro y alguien lo mira; una traducción
 * publicada como original no se ve nunca.
 *
 * ⚠️⚠️ **La medición contra la ficha REAL está pendiente** (§6·T2①) y no puede hacerse hasta que haya
 * conexión —finales de octubre, `#719`—. Por eso existe {@see SUSPICION}: si el texto trae un
 * paréntesis que nombra a Google y **no** se ha podido separar, se marca igual. Así una variante que
 * todavía no conocemos —otro idioma del marcador, un formato nuevo— cae del lado seguro en vez de
 * colarse como texto del autor.
 *
 * ⚠️ Todo se mide en BYTES (`strpos`/`substr`/`strlen`) y no en caracteres: los marcadores son ASCII,
 * así que los cortes caen siempre en un límite seguro. **Mezclar `strpos` con `mb_substr` sí partiría
 * un carácter**, porque uno da bytes y el otro cuenta caracteres.
 */
final readonly class GoogleReviewText
{
    /** El marcador de la versión que ha traducido Google. */
    public const TRANSLATED = '(Translated by Google)';

    /** El marcador de lo que escribió el autor. */
    public const ORIGINAL = '(Original)';

    /**
     * Un paréntesis que nombra a Google y que no hemos sabido separar. Es la red por debajo de los
     * dos marcadores de arriba, para las variantes que aún no se han medido.
     *
     * ⚠️ Marca de más a propósito: una reseña que diga «lo encontré por (Google Maps)» se guardará
     * cruda y marcada. **No pasa nada** —se publica exactamente el mismo texto— y a cambio ninguna
     * traducción se cuela por un marcador que no conocíamos.
     */
    private const SUSPICION = '#\([^)]*\bGoogle\b[^)]*\)#iu';

    private function __construct(
        /** Lo que se publica: el original del autor, o el texto crudo si no se pudo separar. */
        public string $text,
        /**
         * **¿Hubo que renunciar a separar?** Va a `google_business_reviews.text_ambiguous` y §4.3·8
         * pide contarlo en la verificación: es la señal de que un formato nuevo necesita una mirada.
         */
        public bool $ambiguous,
    ) {}

    public static function from(string $raw): self
    {
        $texto = trim($raw);

        if ($texto === '') {
            return new self('', false);
        }

        $traducciones = substr_count($texto, self::TRANSLATED);
        $originales = substr_count($texto, self::ORIGINAL);

        // Sin ningún marcador, el texto es el del autor y no hay nada que separar — salvo que huela a
        // una variante que todavía no conocemos.
        if ($traducciones === 0 && $originales === 0) {
            return new self($texto, self::suspicious($texto));
        }

        // ⚠️ **Media pareja, o pareja repetida, es exactamente el caso ambiguo.** Un autor puede
        // escribir «(Original)» dentro de su reseña, y entonces cortar por ahí se llevaría por
        // delante parte de lo que dijo. Aquí no se adivina.
        if ($traducciones !== 1 || $originales !== 1) {
            return new self($texto, true);
        }

        $inicioTraducido = strpos($texto, self::TRANSLATED);
        $inicioOriginal = strpos($texto, self::ORIGINAL);

        // ⚠️ El orden VARÍA (§4.3·8), así que se contemplan los dos: el original es lo que va desde su
        // marcador hasta el otro marcador, o hasta el final si su marcador es el segundo.
        $original = $inicioOriginal > $inicioTraducido
            ? substr($texto, $inicioOriginal + strlen(self::ORIGINAL))
            : substr($texto, $inicioOriginal + strlen(self::ORIGINAL), $inicioTraducido - $inicioOriginal - strlen(self::ORIGINAL));

        $original = trim($original);

        // Marcadores bien puestos pero sin original detrás: se reconoce la forma y aun así no hay
        // texto del autor que publicar. Crudo y marcado, que es lo que falla cerrado significa.
        return $original === ''
            ? new self($texto, true)
            : new self($original, false);
    }

    private static function suspicious(string $texto): bool
    {
        return preg_match(self::SUSPICION, $texto) === 1;
    }
}
