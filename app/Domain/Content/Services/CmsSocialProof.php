<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\ReviewSelection;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Content\Models\Testimonial;
use Illuminate\Support\Collection;

/**
 * **Las opiniones PROPIAS del parque, las que escribe el panel** (`DECISIONES #490`,
 * `specs/google-reviews.md` §4.4.bis).
 *
 * ❗❗❗ **No es el plan B.** Por §3.3 esto es lo que ve **todo visitante que no acepta cookies de
 * terceros, cada día**: sin consentimiento no se puede servir ni una reseña de Google —su foto de
 * autor es obligatoria (R3) y vive en el servidor de Google (`RGPD-05`)—. Y hoy, además, es lo
 * único que la sección puede enseñar: el parque tiene **una** reseña en Google, por debajo del
 * umbral que la hace publicable.
 */
class CmsSocialProof implements SocialProof
{
    /**
     * Siempre `null`, y **no es una implementación a medias**.
     *
     * ❗❗ La media agregada **solo existe si viene de Google**. Calcularla con estas filas daría un
     * número real —la media de lo que el parque ha escrito de sí mismo— y publicarlo junto a las
     * cinco estrellas del sistema lo haría pasar por la nota de Google, que es lo que
     * `doc/reglas.md` prohíbe: *«cuando el dato lo posee un tercero, no se le atribuye lo que no ha
     * dado»*. Una nota que se pone uno mismo no es prueba social.
     */
    public function rating(): ?Rating
    {
        return null;
    }

    /** Siempre `false`: una opinión propia no pide permiso a nadie. */
    public function reviewsAwaitConsent(): bool
    {
        return false;
    }

    /** Siempre `false` (`#732`): estas opiniones las escribe el panel y las sirve este servidor. */
    public function reviewsNeedConsent(): bool
    {
        return false;
    }

    /**
     * Siempre `null` (`#732`): **las propias no se filtran**, se publican las que el panel activa.
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
            ->where('is_active', true)
            // Solo las ESCRITAS en el panel: las copiadas de la ficha (`#771`) salen por su hecho, con su marca y sus
            // páginas, y aquí se pintarían como propias. Esta cascada queda como estaba.
            ->where('origin', Testimonial::ORIGIN_OWN)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (Testimonial $t): TestimonialData => new TestimonialData(
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
     * ⚠️ Cómo se cuenta —y que una fecha FUTURA se calla— vive en {@see RelativeAge}, que se comparte
     * con las reseñas de Google desde `#591`: dos copias acabarían contando distinto en la misma
     * sección.
     */
    private function cuando(Testimonial $t): ?string
    {
        return RelativeAge::of($t->published_at);
    }
}
