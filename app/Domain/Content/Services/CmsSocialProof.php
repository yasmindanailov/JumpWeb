<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\ReviewSelection;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Content\Models\Testimonial;
use Illuminate\Support\Collection;

/**
 * **Las opiniones del PANEL** (`DECISIONES #490`, `specs/google-reviews.md` §4.4.bis y §9): las escritas en él y las
 * COPIADAS de la ficha de Google del parque (`#771`), con la nota de la ficha copiada.
 *
 * ▶ Desde `#771` es el RESPALDO de la cascada: Places se retiró, y hasta que Google apruebe el Perfil de Empresa —que
 * va delante— esto es lo que se enseña. Todo se sirve desde este servidor: no pide permiso a nadie.
 * ▶ A la portada de siempre llegan las escritas aquí y, de las copiadas, las que el parque etiquetó «portada» —con su
 * marca de Google y su enlace—. Las páginas nuevas eligen las suyas por `/reviews`.
 */
class CmsSocialProof implements SocialProof
{
    /** La etiqueta con la que una copiada de la ficha sale en la portada de siempre. */
    public const HOME_TAG = 'portada';

    public function __construct(private readonly CopiedRating $nota) {}

    /**
     * **La nota de Google COPIADA de la ficha** (`#771`), o `null`.
     *
     * ❗❗ Sigue sin existir una media compuesta con opiniones propias: sería la media de lo que el parque ha escrito de
     * sí mismo, y publicarla junto a las estrellas la haría pasar por la nota de Google (`doc/reglas.md`: *«cuando el
     * dato lo posee un tercero, no se le atribuye lo que no ha dado»*). Ésta es la de Google, tal cual y con su fecha.
     */
    public function rating(): ?Rating
    {
        return $this->nota->get();
    }

    /** Siempre `false`: una opinión del panel no pide permiso a nadie. */
    public function reviewsAwaitConsent(): bool
    {
        return false;
    }

    /** Siempre `false` (`#732`): estas opiniones —y sus imágenes, descargadas— las sirve este servidor. */
    public function reviewsNeedConsent(): bool
    {
        return false;
    }

    /**
     * Siempre `null` (`#732`): **no se filtran**, se publican las que el panel activa.
     *
     * ⚠️ Pintar la línea del §4.3·10 sobre estas sería avisar de un filtro que no existe — y peor,
     * atribuirle a la sección una advertencia legal que no le corresponde.
     */
    public function selection(): ?ReviewSelection
    {
        return null;
    }

    /** @return Collection<int, TestimonialData> */
    public function testimonials(): Collection
    {
        return Testimonial::query()
            ->published()
            ->get()
            // Las escritas aquí, todas las publicadas; las copiadas, solo las que el parque eligió para la portada.
            ->filter(fn (Testimonial $t): bool => $t->origin !== Testimonial::ORIGIN_GOOGLE || $t->hasTag(self::HOME_TAG))
            ->map(fn (Testimonial $t): TestimonialData => $t->origin === Testimonial::ORIGIN_GOOGLE
                ? new TestimonialData(
                    text: (string) $t->tr('text'),
                    author: (string) $t->author,
                    rating: $t->rating,
                    when: $this->cuando($t),
                    // La copiada SÍ tiene «la entera»: la ficha en Google, adonde lleva su enlace.
                    url: $t->source_url,
                    source: TestimonialData::SOURCE_GOOGLE,
                    avatarUrl: $t->avatarUrl(),
                    reply: $t->reply,
                    photos: $t->photoUrls(),
                    attribution: TestimonialData::ATTRIBUTION_GOOGLE_WORD,
                )
                : new TestimonialData(
                    text: (string) $t->tr('text'),
                    author: (string) $t->author,
                    rating: $t->rating,
                    when: $this->cuando($t),
                    // Una opinión propia **no tiene «la entera» en ningún sitio**: lo que hay es lo que
                    // se lee. Prometer un enlace que no esconde nada es lo que `doc/reglas.md` llama
                    // gastar la confianza que la sección viene a dar.
                    url: null,
                    source: TestimonialData::SOURCE_CMS,
                ))
            ->values();
    }

    /**
     * «hace 2 meses», derivado de la fecha.
     *
     * ⚠️ **Se DERIVA y no se teclea**, que es por lo que la columna es una fecha y no el `meta` de
     * texto libre que proponía la spec: «hace 2 meses» escrito a mano es verdad el día que se
     * escribe y mentira dos meses después.
     *
     * ⚠️ Cómo se cuenta —y que una fecha FUTURA se calla— vive en {@see RelativeAge}.
     */
    private function cuando(Testimonial $t): ?string
    {
        return RelativeAge::of($t->published_at);
    }
}
