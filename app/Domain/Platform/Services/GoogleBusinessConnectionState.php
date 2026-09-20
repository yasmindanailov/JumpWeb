<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Enums\GoogleBusinessStatus;
use App\Domain\Platform\Models\GoogleBusinessConnection;

/**
 * **El estado EFECTIVO de la conexión con la ficha de Google**
 * (`docs/specs/google-business-profile.md` §4.2·7).
 *
 * De los siete estados, **dos no se guardan**: «sin configurar» y «lista para conectar» son lo que
 * dicen las credenciales y la presencia de un token, no una decisión que nadie haya escrito. Tener un
 * único sitio que los combina evita el defecto clásico de estas tablas —dos dueños del mismo dato— en
 * el que una fila dice «conectada» y las credenciales ya no están.
 *
 * ⚠️ **El orden de las preguntas importa y no es arbitrario**: sin credenciales no hay nada que hacer
 * aunque haya token guardado, porque ni siquiera se puede canjear. Primero JumpSystem, después el
 * parque.
 */
final readonly class GoogleBusinessConnectionState
{
    /**
     * @param  GoogleBusinessConnection|null  $row  La fila, si ya se leyó. Se pasa para que la pantalla
     *                                              del panel no tenga que consultarla dos veces.
     */
    public static function of(?GoogleBusinessConnection $row): GoogleBusinessStatus
    {
        if (! GoogleBusinessCredentials::fresh()->configured()) {
            return GoogleBusinessStatus::Unconfigured;
        }

        if ($row === null || ! $row->hasStoredToken()) {
            return GoogleBusinessStatus::ReadyToConnect;
        }

        // Hay token guardado pero no se puede descifrar: la `APP_KEY` rotó sin `APP_PREVIOUS_KEYS`.
        // Para todo lo que viene después es exactamente una conexión caducada —hay que reconectar—,
        // así que se dice así y no con un estado nuevo que nadie sabría atender.
        if ($row->readToken() === null) {
            return GoogleBusinessStatus::Expired;
        }

        return $row->status;
    }

    /** El estado de ahora mismo, leyendo la fila. */
    public static function current(): GoogleBusinessStatus
    {
        return self::of(GoogleBusinessConnection::current());
    }
}
