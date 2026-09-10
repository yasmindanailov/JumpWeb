<?php

namespace App\Domain\Content\Services;

/**
 * **Qué pasó al refrescar las reseñas** (`DECISIONES #491`).
 *
 * ❗❗ **Existe porque las cinco salidas NO son la misma cosa, y el operador que lo dispara a mano
 * necesita saber cuál fue.** La primera versión devolvía `?int` y el comando decía «no ha devuelto
 * nada publicable (sin cobertura del umbral, cuota, caída o clave)» — cuatro causas en una frase,
 * así que **para distinguirlas había que mirar el log y deducirlo de una ausencia**. Eso funciona
 * una vez, cuando lo hace quien acaba de escribirlo.
 *
 * ⚠️ Ninguna de las cuatro salidas vacías es un fallo del producto: la sección enseña las opiniones
 * propias, que es su conducta declarada. Lo que cambia es **qué hay que ir a mirar**: `NOT_CONFIGURED`
 * pide un ajuste, `BELOW_THRESHOLD` pide tiempo, `REJECTED` pide la consola de Google y
 * `UNREACHABLE` pide la red.
 */
final readonly class SocialProofRefresh
{
    /** Falta el `place_id` o la clave de API: no hay nada que traer. */
    public const NOT_CONFIGURED = 'not_configured';

    /** No se pudo hablar con Google (timeout, DNS, red). */
    public const UNREACHABLE = 'unreachable';

    /** Google respondió que no: 403 (clave o API), 429 (cuota), 5xx. */
    public const REJECTED = 'rejected';

    /** Respondió bien, pero no llega al umbral de reseñas que hace publicable una media. */
    public const BELOW_THRESHOLD = 'below_threshold';

    /** Respondió bien y está en la caché corta. */
    public const CACHED = 'cached';

    public function __construct(
        public string $outcome,
        /** Cuántas reseñas quedaron servibles. Solo significa algo con `CACHED`. */
        public int $reviews = 0,
        /** El código HTTP, solo con `REJECTED`. Es lo que dice a qué pestaña ir en la consola. */
        public ?int $status = null,
    ) {}

    public function ok(): bool
    {
        return $this->outcome === self::CACHED;
    }
}
