<?php

namespace App\Domain\Content\Contracts;

/**
 * **Qué se está enseñando y qué se está dejando fuera** (T2·6,
 * `docs/specs/google-business-profile.md` §4.3·9 y §4.3·10; `DECISIONES #524`, `#732`).
 *
 * ❗❗❗ **Existe por una razón LEGAL, no de diseño.** La portada enseña las reseñas de cuatro
 * estrellas o más, y la directiva Ómnibus (2019/2161) considera engañoso enseñar solo las positivas
 * **sin decirlo**. Así que «lo estamos filtrando» deja de ser un detalle interno de la fuente y pasa
 * a ser **un dato del contrato**: la landing no puede pintar la sección sin poder decirlo.
 *
 * ⚠️⚠️ **La frase NO viaja aquí, viajan sus piezas.** El texto es copia y vive en `lang/`, donde lo
 * escribe un traductor; lo que el producto sabe es **el número** y **los dos enlaces**. Mandar la
 * frase compuesta desde el dominio obligaría a traducirla en PHP y dejaría a cada instancia sin
 * poder decirlo con sus palabras.
 *
 * ⚠️ `null` en {@see SocialProof::selection()} significa **«esto no se filtra»**, que es el caso de
 * las opiniones propias y el de Places: sin filtro no hay nada que declarar, y pintar la línea ahí
 * sería avisar de algo que no pasa.
 */
final readonly class ReviewSelection
{
    public function __construct(
        /**
         * El mínimo de estrellas que se está exigiendo.
         *
         * ⚠️ Es el número **efectivo**, no el del ajuste: si alguien pone 7 en el panel, lo que la
         * línea tiene que decir es lo que de verdad se está aplicando.
         */
        public int $minStars,
        /** La ficha en Google, para «ver todas». `null` si no se conoce. */
        public ?string $allReviewsUrl,
        /** «Escribir una reseña». `null` si no se conoce. */
        public ?string $writeReviewUrl,
    ) {}
}
