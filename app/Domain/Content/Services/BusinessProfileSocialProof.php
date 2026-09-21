<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\ReviewSelection;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSummary;
use Illuminate\Support\Collection;

/**
 * **Las reseñas de la ficha de Google, leídas de NUESTRA base** (T2·6,
 * `docs/specs/google-business-profile.md` §4.3·9 y §4.3·10; `DECISIONES #524`, `#732`).
 *
 * ❗❗❗ **La portada NUNCA habla con Google** (§4.0 y `PERF-02`). Todo lo que esto devuelve lo trajo
 * la pasada diaria y lo guardó en casa: el texto, la media, y **también las imágenes**, que se
 * sirven por una ruta nuestra. Por eso, y **a diferencia de Places**, estas opiniones **no necesitan
 * el consentimiento del visitante** ({@see reviewsNeedConsent()}): no hay ninguna petición a Google
 * que consentir.
 *
 * ⚠️⚠️ **La media y el total NO se filtran, las tarjetas SÍ** (`[DECIDIDO owner]`, §4.3·10). Son dos
 * caminos distintos a propósito: la cifra viene de Google contada sobre **todas** las reseñas, y las
 * tarjetas enseñan las de cuatro estrellas o más. Que la cifra bajara al filtrar sería el parque
 * cambiando la nota de su propia ficha. Lo que la ley exige a cambio es **decir que se filtra**, y de
 * eso se encarga {@see selection()}.
 *
 * ⚠️ **Puede haber cifra sin tarjetas.** Si el filtro no deja ninguna, {@see testimonials()} devuelve
 * vacío —y la cascada pasa a las propias— pero {@see self::rating()} sigue respondiendo: §4.3·10 dice
 * «la media se sigue enseñando». No es una incoherencia; es la regla.
 */
class BusinessProfileSocialProof implements SocialProof
{
    /**
     * Cuántas tarjetas enseña la portada (§4.3·10).
     *
     * ⚠️ Menor que lo que guarda la pasada ({@see GoogleReviewFilter::KEEP}) a propósito: el margen
     * está para que «Ocultar» o una reseña borrada en Google no dejen la sección corta hasta mañana.
     */
    public const SHOWN = GoogleReviewFilter::SHOWN;

    /**
     * **El umbral que hace publicable la CIFRA** (`#494`, `[DECIDIDO owner]`, definitivo).
     *
     * ⚠️ Se muda aquí desde `GoogleSocialProof` con su guarda: es una decisión del owner sobre
     * cuántas reseñas hacen defendible enseñar una media, y no depende de qué API la traiga.
     * ⚠️ **La doc de `google-reviews.md` dice «10» y miente** (medido el 20-09): manda esto.
     */
    public const MIN_REVIEWS = 1;

    /**
     * La media de la ficha, **tal y como la contó Google**, o `null`.
     *
     * ⚠️ `null` es la respuesta normal: sin conexión, sin pasada, con la pasada vieja o por debajo
     * del umbral. Ninguna es un error y ninguna se registra como tal.
     */
    public function rating(): ?Rating
    {
        $resumen = GoogleBusinessReviewSummary::current();

        if ($resumen === null || ! $resumen->publishable() || $resumen->total_review_count < self::MIN_REVIEWS) {
            return null;
        }

        return new Rating(
            value: (float) $resumen->average_rating,
            count: $resumen->total_review_count,
            url: $resumen->maps_uri,
            source: TestimonialData::SOURCE_GOOGLE,
            // §4.3·10: «con *a fecha de …*». La trajo una pasada diaria, no está viva.
            asOf: $resumen->fetched_at,
        );
    }

    /** Siempre `false`: estas opiniones se sirven enteras desde nuestro servidor. */
    public function reviewsNeedConsent(): bool
    {
        return false;
    }

    /**
     * Siempre `false`, y **no es una implementación a medias**: si no necesitan permiso, no pueden
     * estar esperándolo. Lo que la cascada hace con esto está en {@see FallingBackSocialProof}.
     */
    public function reviewsAwaitConsent(): bool
    {
        return false;
    }

    /**
     * **Lo que se está dejando fuera** (§4.3·10), o `null` si no hay nada que enseñar.
     *
     * ⚠️ Se devuelve `null` cuando no hay tarifas que filtrar —sin reseñas publicables no hay
     * filtro que declarar— para que la landing no pinte una advertencia sobre una sección que está
     * enseñando opiniones propias.
     */
    public function selection(): ?ReviewSelection
    {
        if ($this->reviews()->isEmpty()) {
            return null;
        }

        $resumen = GoogleBusinessReviewSummary::current();

        return new ReviewSelection(
            // ⚠️ El filtro se lee aquí y no se inyecta: `GoogleReviewFilter` lleva un `int` en el
            // constructor, así que no es autoresoluble, y pedirlo por el contenedor reventaba la
            // portada entera con un `BindingResolutionException` — medido el 21-09. Y es el número
            // EFECTIVO, ya acotado a 1–5, que es lo que hay que decirle al visitante.
            minStars: GoogleReviewFilter::fromSettings()->minStars,
            allReviewsUrl: $resumen?->maps_uri,
            writeReviewUrl: $resumen?->new_review_uri,
        );
    }

    /** @return Collection<int, TestimonialData> */
    public function testimonials(): Collection
    {
        return $this->reviews()->map(fn (GoogleBusinessReview $r): TestimonialData => new TestimonialData(
            // §4.3·8: es el ORIGINAL del autor, ya separado de la traducción de Google.
            text: $r->comment,
            // ⚠️⚠️ **El nombre pasa por `publishableAuthor()`**, que aplica el plazo corto del
            // §4.3·4: sin una pasada que confirme la reseña, su autor deja de pintarse. Leer la
            // columna a pelo publicaría el nombre de alguien cuya reseña puede llevar días borrada.
            author: $r->publishableAuthor() ?? '',
            rating: $r->star_rating,
            when: RelativeAge::of($r->review_created_at),
            // Una reseña de Business Profile no tiene página propia: «ver todas» va en la selección.
            url: null,
            source: TestimonialData::SOURCE_GOOGLE,
            avatarUrl: self::photoUrl($r->publishablePhotoPath()),
            // La v4 no da el perfil público del autor, a diferencia de Places.
            authorUrl: null,
            reply: $r->reply_comment,
            photos: self::photoUrls($r->photos ?? []),
            // Anónima de origen, o sin nombre porque el plazo corto lo retiró: para la tarjeta es
            // lo mismo, y declararlo evita que la vista lo deduzca de una cadena vacía.
            anonymous: $r->anonymous || $r->publishableAuthor() === null,
        ))->values();
    }

    /**
     * Las candidatas que se pueden enseñar hoy: dentro del plazo, las más recientes, recortadas.
     *
     * @return Collection<int, GoogleBusinessReview>
     */
    private function reviews(): Collection
    {
        return GoogleBusinessReview::query()
            // ⚠️ El plazo se aplica AL LEER (§4.3·4): aquí es donde esa promesa se cumple o no.
            ->withinRetention()
            ->orderByDesc('review_created_at')
            ->limit(self::SHOWN)
            ->get();
    }

    /**
     * La URL pública de una imagen nuestra, o `null`.
     *
     * ❗❗ **Es el único sitio que convierte una ruta en URL**, y por eso es el único sitio donde
     * podría colarse un host de tercero. La ruta que sale de la base ya pasó por la guarda del
     * modelo (§4.3·9); aquí se vuelve a comprobar la FORMA antes de publicarla, porque lo que sale
     * de aquí va directo al `src` de un `<img>` en la portada.
     */
    private static function photoUrl(?string $path): ?string
    {
        if ($path === null || ! GoogleReviewImages::isOwnName($path)) {
            return null;
        }

        return route('resenas.foto', ['fichero' => $path]);
    }

    /**
     * @param  array<int, mixed>  $paths
     * @return list<string>
     */
    private static function photoUrls(array $paths): array
    {
        $urls = [];

        foreach ($paths as $path) {
            $url = is_string($path) ? self::photoUrl($path) : null;

            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}
