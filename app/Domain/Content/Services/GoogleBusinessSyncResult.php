<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Enums\GoogleBusinessSyncOutcome;
use App\Domain\Platform\Enums\GoogleBusinessStatus;

/**
 * **Lo que dejó una pasada** (T2·3,
 * `docs/specs/google-business-profile.md` §4.2·1, §4.3·1 y §4.3·3; `DECISIONES #524`, `#729`).
 *
 * Es lo que el comando imprime y lo que la pantalla del panel enseñará como «última pasada». Se
 * devuelve en vez de registrarse a secas porque **quien la pidió tiene derecho a saber qué salió**:
 * un admin que pulsa «Sincronizar» y no ve nada no sabe si ha funcionado o si no le han hecho caso.
 *
 * ⚠️ **Aquí no hay ni un dato de nadie**: cuántas, no cuáles. Estos números acaban en un log y en
 * una pantalla, y `RGPD-02` prohíbe que un texto o un nombre de reseña llegue a cualquiera de los dos.
 */
final readonly class GoogleBusinessSyncResult
{
    public function __construct(
        public GoogleBusinessSyncOutcome $outcome,
        /** El estado de la conexión al terminar. */
        public GoogleBusinessStatus $status,
        /** Cuántas candidatas quedaron guardadas. */
        public int $kept = 0,
        /** Cuántas filas se retiraron por no estar ya entre las candidatas. */
        public int $deleted = 0,
        /** Cuántas filas devolvió Google, candidatas o no. */
        public int $seen = 0,
        /**
         * **¿La pasada se pudo creer?** (§4.3·3). `false` no es un fallo: es que esta vez **no se
         * borró nada** y el resumen se quedó como estaba.
         */
        public bool $coherent = false,
        /** Cuántas de las guardadas traían un texto que no se pudo separar (§4.3·8). */
        public int $ambiguous = 0,
    ) {}

    /** Para el log y para la pantalla. Sin un solo dato de tercero. */
    public function toLog(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'estado' => $this->status->value,
            'guardadas' => $this->kept,
            'retiradas' => $this->deleted,
            'vistas' => $this->seen,
            'coherente' => $this->coherent,
            'ambiguas' => $this->ambiguous,
        ];
    }
}
