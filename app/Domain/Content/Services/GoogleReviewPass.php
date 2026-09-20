<?php

namespace App\Domain\Content\Services;

/**
 * **El resultado de recorrer la ficha una vez** (T2·2,
 * `docs/specs/google-business-profile.md` §4.3·1 → §4.3·3; `DECISIONES #524`, `#728`).
 *
 * Lleva las candidatas **y lo que hace falta para decidir si la pasada se puede creer**. La decisión
 * en sí es de la T2·3; lo que se recoge para tomarla es de aquí, porque es lo que se sabe mientras se
 * pagina y se pierde en cuanto se termina.
 *
 * ❗❗❗ **Por qué la media y el total se guardan DOS veces, de la primera página y de la última.**
 * §4.3·3 solo permite borrar con una pasada **coherente**, y una pasada larga contra una ficha viva
 * puede empezar con 320 reseñas y acabar con 180 porque a mitad de recorrido Google esté moviendo
 * algo. Con una sola lectura del total, eso es indistinguible de «el parque perdió 140 reseñas», y
 * la diferencia entre las dos es **si se borra la tabla o no**. {@see coherent()}.
 */
final readonly class GoogleReviewPass
{
    public function __construct(
        /**
         * Las candidatas que sobrevivieron al filtro, **ya recortadas** y de la más reciente a la más
         * antigua (§4.3·10).
         *
         * @var list<IncomingGoogleReview>
         */
        public array $candidates,
        public ?float $firstAverage,
        public int $firstTotal,
        public ?float $lastAverage,
        public int $lastTotal,
        /** Cuántas filas devolvió Google en total, candidatas o no. Es el testigo de que se recorrió. */
        public int $seen,
        /** Cuántas candidatas traían un texto que no se pudo separar (§4.3·8). */
        public int $ambiguous,
        /**
         * **¿Se recorrió la ficha ENTERA?**
         *
         * `false` cuando el recorrido se paró por el tope de páginas ({@see GoogleReviewReader::MAX_PAGES}).
         * ⚠️ Una pasada a medias **no puede borrar**: le faltan reseñas por ver, y lo que no ha visto
         * se parecería a lo que ya no existe.
         */
        public bool $complete,
    ) {}

    /**
     * **¿Se puede creer esta pasada lo bastante como para BORRAR con ella?** (§4.3·3).
     *
     * Tres preguntas, y las tres tienen que decir que sí:
     *
     *  1. **¿Se terminó?** Una pasada cortada por el tope de páginas no ha visto la ficha entera.
     *  2. **¿La ficha se estuvo quieta?** El total y la media de la primera página tienen que seguir
     *     siendo los de la última. Si Google cambió de opinión a mitad de recorrido, lo recogido es
     *     una mezcla de dos momentos.
     *  3. **¿Cuadra lo que se vio con lo que Google dice que hay?** Con `totalReviewCount` mayor que
     *     cero y **ni una fila devuelta**, lo que ha pasado es un fallo, no que el parque se haya
     *     quedado sin reseñas. Es el caso concreto que §4.3·3 nombra: *«una lista vacía con total
     *     mayor que cero no borra»*.
     */
    public function coherent(): bool
    {
        if (! $this->complete) {
            return false;
        }

        if ($this->firstTotal !== $this->lastTotal || $this->firstAverage !== $this->lastAverage) {
            return false;
        }

        return ! ($this->lastTotal > 0 && $this->seen === 0);
    }
}
