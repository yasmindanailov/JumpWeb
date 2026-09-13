<?php

namespace App\Domain\Content\Contracts;

use Illuminate\Support\Collection;

/**
 * **La prueba social de la instalación, sin decir de dónde viene**
 * (`DECISIONES #490` · `specs/google-reviews.md` §4.1).
 *
 * ❗❗ **Es lo ÚNICO que la landing conoce.** `#136` fijó la línea del proyecto —la landing consume
 * DATOS, no proveedores—, así que la vista no tiene un `@if` por fuente ni sabe si detrás hay
 * Google, el panel o los dos. Sin este contrato, el respaldo acaba escrito como un condicional
 * repartido por la plantilla, y **la rama sin cubrir de un respaldo es, por definición, la que solo
 * se ejecuta cuando algo va mal**.
 *
 * Implementaciones previstas (§4.1):
 *
 *     CmsSocialProof            ← las opiniones propias, de `testimonials`   (existe)
 *     GoogleSocialProof         ← Places API con caché corta                 (T2i·b)
 *     FallingBackSocialProof    ← el de arriba con el de abajo detrás        (T2i·b)
 *
 * ⚠️ **El respaldo será un DECORADOR, no un `if`**: la cascada de §4.0 vive en UN solo sitio.
 */
interface SocialProof
{
    /**
     * La cifra agregada, o `null` si no hay ninguna que se pueda sostener.
     *
     * ❗❗❗ **`null` es la respuesta NORMAL, no un fallo.** La media («4,8 sobre 5 · 320 opiniones»)
     * **solo existe si viene de Google**: componerla con opiniones propias sería atribuirle a Google
     * un número que Google no ha dado, y es lo que `doc/reglas.md` prohíbe —*«cuando el dato lo
     * posee un tercero, no se le atribuye lo que no ha dado»*—.
     *
     * Devuelve `null` cuando no hay fuente de tercero configurada, cuando su caché está fría,
     * cuando el visitante no ha consentido, o cuando el recuento no llega al umbral que hace
     * defendible publicar una media. Ninguna de esas cuatro se registra como error.
     */
    public function rating(): ?Rating;

    /**
     * Las opiniones que se pueden enseñar, en el orden en que se enseñan.
     *
     * Vacía es una respuesta válida: con ella la sección **no se pinta** —ni rótulo, ni titular, ni
     * caja vacía—, que es la regla dura del sistema para una sección cuyo contenido pone el panel.
     * ⚠️ Salvo una excepción, la de {@see reviewsAwaitConsent()}: hay opiniones, y solo falta el
     * permiso del visitante para enseñarlas.
     *
     * @return Collection<int, Testimonial>
     */
    public function testimonials(): Collection;

    /**
     * **¿Hay opiniones de un tercero que solo esperan el permiso del visitante?**
     * (`[DECIDIDO owner, 2026-09-13]`, `#592`).
     *
     * ❗❗ **Existe para que la sección no desaparezca entera por un permiso.** Sin cookies de terceros
     * las reseñas de Google no se sirven (su foto es una petición del visitante a Google, `RGPD-05`),
     * y sin opiniones propias detrás la sección se iba **con la nota incluida**, aunque la nota no
     * pide permiso. En producción eso era la primera visita de cualquiera.
     * ⚠️ **Solo lo sabe quien conoce el permiso**: una fuente sola (el panel, Google) responde
     * `false`, y la cascada lo resuelve en un único sitio.
     */
    public function reviewsAwaitConsent(): bool;
}
