<?php

namespace App\Domain\Content\Contracts;

/**
 * Una opinión, venga de donde venga (`DECISIONES #490`).
 *
 * ❗❗❗ **`source` NO es metadato ocioso: la atribución que R3 exige depende de él**
 * (`specs/google-reviews.md` §4.1). Una opinión de Google tiene que salir con su autor y su enlace
 * al perfil; una propia **no puede** salir con la vestimenta de Google. Que el dato lo DIGA evita
 * que la vista lo deduzca — y deducirlo es exactamente el defecto que un decorador mal escrito
 * produce: hereda la vista de la otra fuente, y la suite en verde no lo ve.
 */
final readonly class Testimonial
{
    public const SOURCE_CMS = 'cms';

    public const SOURCE_GOOGLE = 'google';

    public function __construct(
        /** El texto, en el idioma activo. ⚠️ Nunca se recorta en servidor: R4 prohíbe alterarlo. */
        public string $text,
        /** Quién lo firma. */
        public string $author,
        /** 1–5, o `null`: una opinión sin nota sigue siendo una opinión. */
        public ?int $rating,
        /** «hace 2 meses». Ya resuelto y traducido; `null` si no hay fecha que contar. */
        public ?string $when,
        /** Adónde lleva «ver la entera». `null` en las propias: no hay nada más que leer. */
        public ?string $url,
        /** `cms` | `google`. */
        public string $source,
        /** La foto del autor. Solo la de un tercero, y solo con consentimiento. */
        public ?string $avatarUrl = null,
        /**
         * **La ficha pública de quien la firma** (`#494`).
         *
         * ❗❗ **Es la tercera pata de la atribución que R3 exige y faltaba**: *«Each photo and review
         * includes an author attribution (author's avatar image, name, **and profile link**)»* —
         * *«Attribute the author using all available resources (avatar, name, and profile link) when
         * space allows»*. Se tenían las dos primeras.
         * ⚠️ **No cuesta ni una llamada más**: `authorAttribution.uri` ya viaja en la misma respuesta
         * de Places que el nombre y la foto (verificado con HTTP 200 el 2026-09-10). Lo único que
         * faltaba era leerlo.
         * ⚠️ Es URL de un tercero: nace saneada (`SEC-07` se aplica DONDE NACE EL DATO). `null` en
         * las propias, que no tienen perfil en ninguna parte.
         */
        public ?string $authorUrl = null,
        /**
         * **Lo que el autor escribió**, cuando lo que se enseña está traducido (`#494`).
         *
         * ⚠️ **Su ausencia es la señal de que NO hay traducción**, y por eso no existe ningún
         * `bool $translated` que pudiera contradecirla. {@see isTranslated()}.
         */
        public ?OriginalText $originalText = null,
        /**
         * **El idioma en que está escrito `text` cuando NO es el de la página** (`#591`), o `null`.
         *
         * ⚠️ Desde `#591` las reseñas de Google se piden en UN idioma y las otras versiones del sitio
         * las enseñan tal cual: sin esto la tarjeta no puede poner `lang`, y un lector de pantalla lee
         * español con la voz de la página. `null` es lo normal: toda opinión propia y toda reseña en
         * el idioma de la página.
         */
        public ?string $language = null,
        /**
         * **La respuesta del parque a esta reseña** (T2·6, §4.3·10), o `null`.
         *
         * ⚠️ No es dato de un tercero: lo escribió el propio parque en su ficha. Va aquí y no en un
         * segundo testimonio porque **cuelga de la reseña**: se pinta debajo, y separarlas dejaría
         * al lector sin saber a qué contesta.
         */
        public ?string $reply = null,
        /**
         * Las fotos que el autor adjuntó, **como rutas nuestras ya servibles**.
         *
         * ❗❗ **Nunca una URL de un tercero** (§4.3·9, invariante con guarda). Toda la T2 existe para
         * que la portada deje de pedirle nada a Google; una URL de `lh3.googleusercontent.com` aquí
         * lo deshace **sin romper nada visible**, porque la foto se vería igual de bien.
         *
         * @var list<string>
         */
        public array $photos = [],
        /**
         * **La firmó alguien que eligió no dar su nombre** (§4.3·5).
         *
         * ⚠️ Se DECLARA en vez de deducirse de que `author` esté vacío, porque son dos cosas
         * distintas: anónima de origen, y sin nombre porque el plazo corto de §4.3·4 lo retiró. La
         * vista las pinta igual, pero quien lea este objeto no tiene por qué adivinar cuál es.
         */
        public bool $anonymous = false,
    ) {}

    /**
     * ¿Lo que se está enseñando es una traducción?
     *
     * ⚠️ **La política obliga a decirlo** y a dar acceso al original, así que esto no es un adorno:
     * es lo que decide si la tarjeta pinta el aviso. Se DERIVA de tener el original —una sola
     * fuente— en vez de viajar como un segundo campo que pudiera decir lo contrario que el texto.
     */
    public function isTranslated(): bool
    {
        return $this->originalText !== null;
    }

    /**
     * La inicial que va en el círculo cuando no hay foto.
     *
     * ⚠️ **Es `mb_substr` y no `substr`**: con un nombre que empiece por acento, cortar por BYTES
     * parte el carácter y pinta un rombo — el mismo defecto que `#295` pagó con `trim($s, ' ·')`.
     */
    public function initial(): string
    {
        $limpio = trim($this->author);

        return $limpio === '' ? '·' : mb_strtoupper(mb_substr($limpio, 0, 1));
    }
}
