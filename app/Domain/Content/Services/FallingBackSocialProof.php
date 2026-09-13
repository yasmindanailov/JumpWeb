<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use Closure;
use Illuminate\Support\Collection;

/**
 * **La cascada de §4.0, en UN solo sitio** (`DECISIONES #491`, `specs/google-reviews.md` §4.1).
 *
 * ❗❗❗ **Es un DECORADOR y no un `if` repartido por la vista, y la spec dice por qué**: un respaldo
 * escrito como condicional en la plantilla acaba con una rama sin cubrir — *«y la rama sin cubrir de
 * un respaldo es, por definición, la que solo se ejecuta cuando algo va mal»*.
 *
 *     ¿hay place_id y clave?            ─no→  CMS
 *     ¿la caché tiene datos frescos?    ─no→  CMS        (evicción de `allkeys-lru`, o aún sin refresco)
 *     ¿llega al umbral de reseñas?      ─no→  CMS        (el filtro vive en `GoogleSocialProof`)
 *     ¿el visitante aceptó terceros?    ─no→  CMS **solo para las OPINIONES**
 *               │sí
 *               └─→ Google
 *
 * ⚠️⚠️ **Ninguna de esas salidas es un error y ninguna se registra como tal.** Un log por visitante
 * sin consentimiento llenaría el log de ruido y escondería los fallos de verdad.
 *
 * ❗❗❗ **LA CIFRA Y LAS OPINIONES NO SE GOBIERNAN IGUAL, y esto resuelve una ambigüedad que la spec
 * tenía escrita sin argumentar.** §4.4.bis afirmaba «sin consentimiento la cabecera no se pinta»,
 * pero su razón escrita era *no inventar la cifra*, que es otra cosa. Mirado de cerca:
 *
 *  · **La cifra agregada NO necesita consentimiento.** La trae **nuestro servidor** con el comando
 *    programado, así que el visitante **no hace ninguna petición a Google**; no tiene autor ni foto,
 *    de modo que la atribución con foto de R3 no aplica; y una media de un negocio no es dato
 *    personal. Lo que sí exige es acreditar la fuente, y eso es TEXTO («en Google»).
 *  · **Las opiniones SÍ.** R3 obliga a mostrar la foto del autor, esa foto vive en
 *    `lh3.googleusercontent.com` y cargarla **es una petición del visitante a Google** — exactamente
 *    lo que `RGPD-05` gestiona. Y servirlas sin foto incumple R3, así que no hay término medio: o
 *    con consentimiento, o las propias.
 *
 * ▶ De ahí sale lo que el owner pedía —que la chapa se vea siempre que se pueda— **sin romper nada**.
 */
class FallingBackSocialProof implements SocialProof
{
    /**
     * ⚠️⚠️ **El consentimiento entra como CIERRE, no como servicio, y es una consecuencia de la
     * frontera, no una preferencia**: `CookieConsent` vive en Identity y **Content no puede mirar a
     * Identity** (`ModuleBoundariesTest`). Lo resuelve el composition root, que sí ve a los dos — la
     * misma salida que `ReservationPlacesTaken` en `#444`.
     * ⚠️ Y es un CIERRE y no un `bool` a propósito: así se lee **cuando hace falta** y no cuando el
     * contenedor construye el objeto, que en una petición cualquiera puede ser antes.
     *
     * @param  Closure(): bool  $terceroPermitido
     */
    public function __construct(
        private readonly GoogleSocialProof $google,
        private readonly CmsSocialProof $cms,
        private readonly Closure $terceroPermitido,
    ) {}

    /**
     * La cifra de Google si la hay, y **nunca una compuesta con opiniones propias**: `CmsSocialProof`
     * devuelve `null` a propósito, así que aquí no hace falta ningún `if` que lo impida.
     */
    public function rating(): ?Rating
    {
        return $this->google->rating() ?? $this->cms->rating();
    }

    /** @return Collection<int, TestimonialData> */
    public function testimonials(): Collection
    {
        if (($this->terceroPermitido)()) {
            $deGoogle = $this->google->testimonials();

            if ($deGoogle->isNotEmpty()) {
                return $deGoogle;
            }
        }

        return $this->cms->testimonials();
    }

    /**
     * **Las reseñas de Google existen y solo falta el permiso** (`#592`).
     *
     * ⚠️ **No mira las opiniones propias, a propósito**: con ellas la sección enseña esas (el cruce de
     * `#491`), y decidir qué va en el hueco de las tarjetas es de la vista, que ya tiene la colección.
     * Mirarlas aquí costaría una segunda consulta por visita para una respuesta que ya está servida.
     * ⚠️ El permiso se pregunta PRIMERO: con él dado no hace falta ni leer la caché.
     */
    public function reviewsAwaitConsent(): bool
    {
        return ! ($this->terceroPermitido)() && $this->google->testimonials()->isNotEmpty();
    }
}
