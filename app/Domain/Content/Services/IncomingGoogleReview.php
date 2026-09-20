<?php

namespace App\Domain\Content\Services;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * **Una reseña recién traída de Google, ya entendida y ya saneada** (T2·2,
 * `docs/specs/google-business-profile.md` §4.3·2, §4.3·5 y §4.3·8; `DECISIONES #524`, `#728`).
 *
 * Es la frontera entre «lo que manda Google» y «lo que el producto sabe manejar». Todo lo que entra
 * por {@see fromApi()} sale de aquí con la forma de la casa: las estrellas en número, el texto ya
 * separado de su traducción, el anónimo **sin nombre** y la foto **saneada o `null`**.
 *
 * ❗❗ **Aquí todavía NO hay fila en la base**, y por eso puede llevar {@see $authorPhotoSourceUrl},
 * que es una URL de Google: existe en memoria el rato que dura una pasada, para que la T2·4 sepa qué
 * descargar. **En el modelo esa URL no cabe** —la guarda de `GoogleBusinessReview` lanza— y ése es el
 * reparto: lo de fuera se conoce aquí y no llega a la base.
 *
 * ⚠️ `fromApi()` devuelve `null` cuando la fila **no sirve**, que no es lo mismo que «no es
 * candidata». Sin nombre de recurso no se puede deduplicar (§4.3·3), sin estrellas no se puede
 * filtrar (§4.3·2) y sin fecha no se puede ordenar (§4.3·10): son reseñas que no se pueden tratar.
 * Que una tenga dos estrellas, en cambio, es una decisión del FILTRO y se toma más arriba.
 */
final readonly class IncomingGoogleReview
{
    /**
     * Cómo nombra Google cada nota. Viene como TEXTO (`FIVE`), no como número.
     *
     * ⚠️ `STAR_RATING_UNSPECIFIED` **no se traduce a cero**: una reseña sin nota no es una reseña de
     * cero estrellas, y colarla como 0 la dejaría siempre por debajo del mínimo por el motivo
     * equivocado. No está en la tabla a propósito, y sin nota la fila no sirve.
     *
     * @var array<string,int>
     */
    private const STARS = [
        'ONE' => 1,
        'TWO' => 2,
        'THREE' => 3,
        'FOUR' => 4,
        'FIVE' => 5,
    ];

    /**
     * Hosts desde los que Google sirve las fotos (§4.3·6). **Coincidencia EXACTA**, nunca por sufijo:
     * `googleusercontent.com.loquesea.net` termina en algo que un `str_ends_with` mal escrito daría
     * por bueno.
     *
     * @var list<string>
     */
    private const PHOTO_HOSTS = [
        'lh3.googleusercontent.com',
        'lh4.googleusercontent.com',
        'lh5.googleusercontent.com',
        'lh6.googleusercontent.com',
    ];

    private function __construct(
        /** El nombre de recurso (`accounts/…/locations/…/reviews/…`): la identidad con la que se deduplica. */
        public string $name,
        /** Quién firma, o `null` si es anónima. */
        public ?string $authorName,
        public bool $anonymous,
        /**
         * La foto del autor **en el servidor de Google**, saneada, o `null`.
         *
         * ⚠️⚠️ **Esto NO se guarda**: lo descarga la T2·4 y en la base va la ruta del fichero nuestro.
         * Vive aquí porque es donde nace el dato, que es donde `SEC-07` manda sanearlo.
         */
        public ?string $authorPhotoSourceUrl,
        /** 1–5, ya traducidas de `FIVE`/`FOUR`/… */
        public int $stars,
        /** El texto del AUTOR, ya sin la traducción de Google ({@see GoogleReviewText}). */
        public string $comment,
        /** El analizador no pudo separar con certeza y esto es el texto crudo (§4.3·8). */
        public bool $textAmbiguous,
        /** La respuesta del parque, si la hay. No es dato de tercero: la escribe el propio parque. */
        public ?string $replyComment,
        public ?CarbonImmutable $replyAt,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}

    /**
     * @param  array<string,mixed>  $row  una fila de `reviews.list`, cruda
     */
    public static function fromApi(array $row): ?self
    {
        $name = is_string($row['name'] ?? null) ? trim($row['name']) : '';
        $stars = self::STARS[is_string($row['starRating'] ?? null) ? $row['starRating'] : ''] ?? null;
        $createdAt = self::instant($row['createTime'] ?? null);

        if ($name === '' || $stars === null || $createdAt === null) {
            return null;
        }

        $texto = GoogleReviewText::from(is_string($row['comment'] ?? null) ? $row['comment'] : '');

        if ($texto->text === '') {
            // Sin texto no es candidata (§4.3·2): una nota suelta sin palabras no se puede enseñar
            // como opinión. Sigue contando para la media y el total, que los da Google.
            return null;
        }

        $reviewer = is_array($row['reviewer'] ?? null) ? $row['reviewer'] : [];
        $anonymous = ($reviewer['isAnonymous'] ?? false) === true;
        $reply = is_array($row['reviewReply'] ?? null) ? $row['reviewReply'] : [];
        $replyComment = is_string($reply['comment'] ?? null) ? trim($reply['comment']) : '';

        return new self(
            name: $name,
            // ⚠️⚠️ **El `null` del anónimo entra AQUÍ, no al pintar** (§4.3·5): así no hay ningún
            // camino —ni una consulta, ni un volcado, ni un export— por el que su nombre exista en
            // nuestra base. Se tira aunque Google mande algo en `displayName`.
            authorName: $anonymous ? null : self::name($reviewer['displayName'] ?? null),
            anonymous: $anonymous,
            authorPhotoSourceUrl: $anonymous ? null : self::photoUrl($reviewer['profilePhotoUrl'] ?? null),
            stars: $stars,
            comment: $texto->text,
            textAmbiguous: $texto->ambiguous,
            replyComment: $replyComment === '' ? null : $replyComment,
            replyAt: self::instant($reply['updateTime'] ?? null),
            createdAt: $createdAt,
            updatedAt: self::instant($row['updateTime'] ?? null),
        );
    }

    private static function name(mixed $value): ?string
    {
        $nombre = is_string($value) ? trim($value) : '';

        return $nombre === '' ? null : $nombre;
    }

    /**
     * `https` **y** host en la lista, o `null`. Misma regla dura que las URLs de la ficha en la T1·3b:
     * sin excepciones y sin normalizar «casi».
     */
    private static function photoUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $url = trim($value);
        $parts = parse_url($url);

        if (! is_array($parts) || mb_strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }

        return in_array(mb_strtolower((string) ($parts['host'] ?? '')), self::PHOTO_HOSTS, true) ? $url : null;
    }

    /**
     * Una marca de tiempo de Google (RFC 3339, en UTC), o `null`.
     *
     * ⚠️ `parse()` **lanza** con una cadena que no entiende, y lo que llega aquí viene de fuera: una
     * pasada entera no se puede caer porque una reseña traiga una fecha rara.
     */
    private static function instant(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
