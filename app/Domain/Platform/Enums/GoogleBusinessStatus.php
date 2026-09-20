<?php

namespace App\Domain\Platform\Enums;

use App\Domain\Platform\Services\GoogleBusinessConnectionState;

/**
 * **La máquina de estados de la conexión con la ficha de Google**
 * (`docs/specs/google-business-profile.md` §4.2·7).
 *
 * Existe porque **un permiso de Google se rompe de varias maneras distintas y cada una se arregla de
 * una forma**: una caducada se arregla reconectando, una «sin permiso» la arregla el parque en su
 * ficha, y una «sin acceso a la API» no la arregla nadie del parque —es una solicitud de JumpSystem
 * que Google aún no ha aprobado—. Un booleano «conectada sí/no» mandaría al admin a reconectar una y
 * otra vez contra un problema que no está en su lado.
 *
 * ⚠️⚠️ **Y todas menos una significan «NO llames a Google»** (la columna «La pasada» del §4.2·7). Ésa
 * es la razón de peso de este enum: un permiso roto que se reintenta cada día es una cuenta pidiendo
 * que la bloqueen. {@see self::syncs()} es esa regla, escrita una sola vez.
 */
enum GoogleBusinessStatus: string
{
    /** Faltan las credenciales del cliente central. **Derivado**, no se guarda. */
    case Unconfigured = 'unconfigured';

    /** Hay credenciales y no hay token: falta el gesto del admin. **Derivado**, no se guarda. */
    case ReadyToConnect = 'ready_to_connect';

    /** Vuelta OAuth válida. **El único estado que llama a Google.** */
    case Connected = 'connected';

    /** `invalid_grant`, `invalid_client` o un token que ya no se puede descifrar. */
    case Expired = 'expired';

    /** 403 `PERMISSION_DENIED`: la cuenta perdió el rol sobre la ficha. */
    case Forbidden = 'forbidden';

    /** 404 de la ficha: la borraron, la fusionaron o cambió de cuenta. */
    case LocationLost = 'location_lost';

    /** Cuota 0 — la API sigue sin aprobarse para el proyecto (§7·A·2). */
    case NoApiAccess = 'no_api_access';

    /**
     * ¿La pasada diaria llama a Google?
     *
     * ⚠️ **Se escribe como lista blanca de UN caso y no como «no está en la lista de rotos»**: lo
     * segundo haría que un estado nuevo —el día que Google invente otro modo de fallar— entrara
     * llamando por omisión, que es justo el error que este enum existe para evitar.
     */
    public function syncs(): bool
    {
        return $this === self::Connected;
    }

    /**
     * Los dos que **no se guardan**: se derivan de si hay credenciales y de si hay token
     * ({@see GoogleBusinessConnectionState}). Tenerlos en el enum es lo
     * que permite que la pantalla del panel pinte los siete con el mismo código.
     */
    public function isDerived(): bool
    {
        return $this === self::Unconfigured || $this === self::ReadyToConnect;
    }

    /**
     * ¿Se llegó aquí porque algo se ROMPIÓ? Es lo que hay que avisar por correo al entrar
     * (§4.2·7): «lista para conectar» no es una avería, es una instalación a medio montar.
     */
    public function isFailure(): bool
    {
        return match ($this) {
            self::Expired, self::Forbidden, self::LocationLost, self::NoApiAccess => true,
            self::Unconfigured, self::ReadyToConnect, self::Connected => false,
        };
    }
}
