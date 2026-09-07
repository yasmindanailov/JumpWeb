<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * `#441` — se intentó declarar un menor **sin aceptar su exención** en una instalación que la
 * gestiona dentro (`waiver.mode = interno`) y con una versión publicada
 * (`docs/specs/firma-al-declarar-menor.md` §4.3).
 *
 * ⚠️⚠️ **Es un CINTURÓN, no el mensaje que ve el cliente.** Quien llega por la API recibe un
 * `422 validation_failed` sobre `accept_waiver`, que es lo que le dice qué casilla falta; esto lo
 * lanza el ESCRITOR para que el invariante —*no existe un menor declarado sin aceptación*— no dependa
 * de que cada llamante se acuerde. Un llamante nuevo que se lo salte se estrella aquí en vez de
 * escribir una ficha huérfana.
 *
 * ▶ El precedente es la razón por la que `surname`/`relationship` NO viven aquí
 * (`DependentRegistry`, comentario de §4.2): aquéllos son datos que las fichas viejas no tienen y
 * no se pueden inventar. **Éste es distinto**: no es un dato del menor, es la condición para que la
 * ficha pueda existir, y por eso sí es del escritor.
 */
class DependentWaiverRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Para declarar una persona a cargo hay que aceptar su descargo de responsabilidad.');
    }
}
