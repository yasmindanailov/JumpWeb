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
    ) {}

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
