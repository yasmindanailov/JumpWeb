<?php

namespace App\Domain\Content\Enums;

/**
 * **Cómo terminó una pasada de sincronización** (T2·3,
 * `docs/specs/google-business-profile.md` §4.3·1 y §4.3·3; `DECISIONES #524`, `#729`).
 *
 * Existe porque §4.2·1 pide que la pantalla del panel enseñe **la última pasada y su resultado**, y
 * «falló» no es una respuesta: el admin tiene que poder distinguir lo que arregla él de lo que no.
 *
 * ⚠️ **Tres de los cuatro NO son errores.** Que no se llamara porque la conexión está caducada, o
 * que ya hubiera otra pasada en curso, son desenlaces normales de un sistema que corre solo todos
 * los días. Tratarlos como fallos llenaría el panel de rojos que no hay que atender.
 */
enum GoogleBusinessSyncOutcome: string
{
    /** Se recorrió la ficha y se guardó lo que había que guardar. */
    case Done = 'done';

    /**
     * **Había otra pasada en curso y ésta no llegó a llamar.**
     *
     * El caso real es el botón del panel pulsado mientras corre la diaria. No es un error: es el
     * candado haciendo su trabajo, y lo que hay que enseñar es «espera, ya se está haciendo».
     */
    case Busy = 'busy';

    /**
     * **El estado de la conexión dice que no se llame** (§4.2·7): sin configurar, sin conectar,
     * caducada, sin permiso, ficha perdida o sin acceso a la API.
     *
     * ⚠️ No se llama **a propósito**. Reintentar contra una conexión caducada gasta cuota compartida
     * entre todos los parques y no arregla nada: lo que falta lo tiene que hacer una persona.
     */
    case Idle = 'idle';

    /** Se llamó y Google dijo que no. Si el «no» es permanente, el estado de la conexión ya lo dice. */
    case Failed = 'failed';
}
