<?php

namespace App\Domain\Content\Services;

use App\Domain\Platform\Models\Setting;

/**
 * **Qué reseñas se quedan y cuántas** (T2·2,
 * `docs/specs/google-business-profile.md` §4.3·2 y §4.3·10; `DECISIONES #524`, `#728`).
 *
 * Existe como objeto y no como dos argumentos sueltos porque las dos cifras se tienen que mirar
 * juntas: guardar menos de las que la portada enseña deja huecos en la sección, y ésa es la clase de
 * error que solo se ve en producción y un martes.
 *
 * ⚠️⚠️ **Esto filtra las TARJETAS, nunca la media ni el total** (`[DECIDIDO owner]`, §4.3·10). La
 * cifra viene de Google contada sobre todas las reseñas, incluidas las que este filtro deja fuera.
 * Enseñar «4,9» calculado solo sobre las buenas es el dato engañoso que persigue la Ómnibus
 * 2019/2161 — y además la política de Google prohíbe agregar su contenido.
 */
final readonly class GoogleReviewFilter
{
    /** El ajuste del panel con el mínimo de estrellas. */
    public const MIN_STARS_KEY = 'reviews.min_stars';

    /** Por defecto, cuatro (`[DECIDIDO owner]`, §4.3·10). */
    public const DEFAULT_MIN_STARS = 4;

    /** Cuántas enseña la portada (§4.3·10). */
    public const SHOWN = 6;

    /**
     * Cuántas se guardan: las que se enseñan **más un margen** (§4.3·2).
     *
     * ⚠️ El margen no es por si acaso: entre dos pasadas, «Ocultar» (§4.3·7) puede llevarse alguna y
     * el autor de otra puede borrarla en Google. Sin margen, la sección se quedaría corta hasta la
     * pasada siguiente —un día entero— y la portada enseñaría cuatro tarjetas donde dice seis.
     */
    public const KEEP = 12;

    public function __construct(
        /** El mínimo de estrellas para que una reseña se pueda enseñar. */
        public int $minStars,
        /** Cuántas candidatas se persisten como mucho. */
        public int $keep = self::KEEP,
    ) {}

    /**
     * El filtro tal y como lo ha dejado el panel.
     *
     * ⚠️ Se acota a 1–5 **al leerlo**: el ajuste es texto en `settings` y lo edita una persona. Un 0
     * apagaría el filtro sin decirlo y un 7 vaciaría la sección para siempre; las dos cosas se verían
     * como «las reseñas no salen» y ninguna llevaría a la casilla que las causó.
     */
    public static function fromSettings(): self
    {
        $minimo = (int) Setting::value(self::MIN_STARS_KEY, self::DEFAULT_MIN_STARS);

        return new self(max(1, min(5, $minimo)));
    }

    /** ¿Esta reseña se puede enseñar? */
    public function accepts(IncomingGoogleReview $review): bool
    {
        return $review->stars >= $this->minStars;
    }
}
