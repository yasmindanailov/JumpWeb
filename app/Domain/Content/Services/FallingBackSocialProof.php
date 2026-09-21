<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\ReviewSelection;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use Closure;
use Illuminate\Support\Collection;

/**
 * **La cascada, en UN solo sitio** (`DECISIONES #491`; reescrita en `#732`, T2·6 de
 * `specs/google-business-profile.md` §4.3·9).
 *
 * ❗❗❗ **Es un DECORADOR y no un `if` repartido por la vista**: un respaldo escrito como condicional
 * en la plantilla acaba con una rama sin cubrir — *«y la rama sin cubrir de un respaldo es, por
 * definición, la que solo se ejecuta cuando algo va mal»*.
 *
 *     ficha de Google (Business Profile)  ─vacío→  Places  ─vacío o sin permiso→  opiniones propias
 *
 * ❗❗ **Lo que cambió en `#732`: la cascada ya no sabe quién necesita permiso.** Antes tenía escrito
 * «Google necesita consentimiento» como una verdad del sistema, y dejó de serlo: las reseñas de la
 * ficha se sirven **enteras desde nuestro servidor** —imagen incluida— así que el navegador no le
 * pide nada a Google y no hay nada que consentir; las de Places sí, porque su foto de autor la carga
 * el visitante. Ahora **cada fuente lo declara** ({@see SocialProof::reviewsNeedConsent()}) y esto
 * solo recorre la lista. Añadir una cuarta fuente es declarar una propiedad suya, no tocar un `if`.
 *
 * ⚠️⚠️ **El ORDEN de las fuentes es la política y vive en el composition root**, no aquí. Aquí solo
 * se recorre: la primera que tenga opiniones que se puedan enseñar, gana.
 *
 * ❗❗❗ **LA CIFRA Y LAS OPINIONES NO SE GOBIERNAN IGUAL** (`#491`, y sigue vigente): la cifra la trae
 * nuestro servidor, no tiene autor ni foto y una media de un negocio no es dato personal, así que
 * **no necesita consentimiento**; las opiniones de una fuente que exige cargar la foto del autor
 * desde un tercero, sí. Por eso {@see self::rating()} recorre las fuentes **sin preguntar por el permiso**
 * y {@see testimonials()} lo pregunta.
 *
 * ⚠️ Ninguna salida de esta cascada es un error y ninguna se registra como tal. Un log por visitante
 * sin consentimiento llenaría el log de ruido y escondería los fallos de verdad.
 */
class FallingBackSocialProof implements SocialProof
{
    /**
     * @param  list<SocialProof>  $fuentes  en orden de preferencia; la última es el respaldo.
     * @param  Closure(): bool  $terceroPermitido  ⚠️⚠️ **Entra como CIERRE, no como servicio, y es
     *                                             consecuencia de la frontera**: `CookieConsent` vive
     *                                             en Identity y **Content no puede mirar a Identity**
     *                                             (`ModuleBoundariesTest`). Lo resuelve el composition
     *                                             root, que sí ve a los dos. Y es un cierre y no un
     *                                             `bool` para que se lea **cuando hace falta** y no
     *                                             cuando el contenedor construye el objeto.
     */
    public function __construct(
        private readonly array $fuentes,
        private readonly Closure $terceroPermitido,
    ) {}

    /**
     * La primera cifra que alguna fuente pueda sostener.
     *
     * ⚠️ **Sin preguntar por el permiso**, a propósito (ver la cabecera). Y nunca una compuesta con
     * opiniones propias: `CmsSocialProof` devuelve `null`, así que aquí no hace falta ningún `if`.
     */
    public function rating(): ?Rating
    {
        foreach ($this->fuentes as $fuente) {
            $cifra = $fuente->rating();

            if ($cifra !== null) {
                return $cifra;
            }
        }

        return null;
    }

    /** @return Collection<int, TestimonialData> */
    public function testimonials(): Collection
    {
        return $this->elegida()?->testimonials() ?? collect();
    }

    /**
     * **La selección de la fuente que está respondiendo**, para que la landing pueda declararla.
     *
     * ⚠️ Sale de la MISMA fuente que las opiniones, y no de la primera que tenga una: si la ficha se
     * queda sin tarjetas y responden las propias, la línea del filtro **no se pinta** — avisaría de
     * un filtro que no se está aplicando a lo que se ve.
     */
    public function selection(): ?ReviewSelection
    {
        return $this->elegida()?->selection();
    }

    /**
     * **Hay opiniones y solo falta el permiso del visitante** (`#592`).
     *
     * ⚠️ Solo lo puede decir la cascada: una fuente sola no sabe si el visitante consintió. Se
     * recorre buscando una que **necesite permiso**, no la tenga, y aun así tenga algo que enseñar.
     * Si otra que no lo necesita ya está respondiendo, no hay nada que esperar.
     */
    public function reviewsAwaitConsent(): bool
    {
        if (($this->terceroPermitido)()) {
            return false;
        }

        foreach ($this->fuentes as $fuente) {
            if (! $fuente->reviewsNeedConsent()) {
                // Ésta responde sin permiso. Si tiene algo, no se está esperando nada.
                if ($fuente->testimonials()->isNotEmpty()) {
                    return false;
                }

                continue;
            }

            if ($fuente->testimonials()->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    /** La cascada, como propiedad de sí misma: necesita permiso si lo necesita quien responde. */
    public function reviewsNeedConsent(): bool
    {
        return $this->elegida()?->reviewsNeedConsent() ?? false;
    }

    /**
     * La primera fuente que puede enseñar algo **ahora mismo**, o `null`.
     *
     * ⚠️ Se memoriza por instancia: la portada pregunta por las opiniones, por la selección y por el
     * permiso, y sin el memo eso serían tres recorridos de la cascada —con sus consultas— para
     * pintar una sección (`PERF-02`). El binding es `scoped`, así que el memo dura la petición.
     */
    private function elegida(): ?SocialProof
    {
        if ($this->resuelta !== false) {
            return $this->resuelta;
        }

        $permitido = ($this->terceroPermitido)();

        foreach ($this->fuentes as $fuente) {
            if ($fuente->reviewsNeedConsent() && ! $permitido) {
                continue;
            }

            if ($fuente->testimonials()->isNotEmpty()) {
                return $this->resuelta = $fuente;
            }
        }

        return $this->resuelta = null;
    }

    /**
     * `false` = todavía no se ha mirado. Se usa `false` y no `null` porque **`null` es una respuesta
     * legítima** —ninguna fuente tiene nada— y con `null` como centinela se recorrería la cascada
     * entera en cada pregunta justo en el caso en que no hay nada que encontrar.
     */
    private SocialProof|null|false $resuelta = false;
}
