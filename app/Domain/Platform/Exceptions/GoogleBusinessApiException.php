<?php

namespace App\Domain\Platform\Exceptions;

use App\Domain\Platform\Enums\GoogleBusinessStatus;
use RuntimeException;

/**
 * **Google ha dicho que no**, y lo que importa es *qué clase* de no
 * (`docs/specs/google-business-profile.md` §4.2·7).
 *
 * ⚠️⚠️ **La distinción no es cosmética: decide si se vuelve a llamar.** Un permiso retirado, una
 * cuenta que perdió el rol y una API sin aprobar se parecen mucho desde el código —todas son un 4xx—
 * y **ninguna se arregla reintentando**. Reintentar un `invalid_grant` cada día es una cuenta pidiendo
 * que la bloqueen. En cambio un 429 o un 503 **sí** son temporales, y marcarlos como avería apagaría
 * la conexión de un parque por un mal minuto de Google.
 *
 * Por eso esta excepción lleva **un estado o ninguno**:
 *  - `$status` con valor → la conexión pasa a ese estado y **deja de llamar** hasta que alguien actúe;
 *  - `$status` a `null` → **fallo pasajero**: no se toca el estado guardado y se reintentará.
 *
 * ⚠️ **Nunca lleva material dentro**: ni token, ni cuerpo de la respuesta, ni cabeceras. Solo el
 * código HTTP y la razón corta que publica Google (§4.2·6), que es lo único accionable.
 */
final class GoogleBusinessApiException extends RuntimeException
{
    private function __construct(
        /** El estado al que pasa la conexión, o `null` si el fallo es pasajero. */
        public readonly ?GoogleBusinessStatus $status,
        public readonly int $httpStatus,
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Traduce la respuesta de Google.
     *
     * ⚠️⚠️ **El 403 es DOS cosas distintas y hay que separarlas por la razón**, no por el código: si
     * la cuenta perdió el rol sobre la ficha, lo arregla el parque en su perfil de empresa; si la API
     * no está habilitada o la cuota es 0, no lo arregla nadie del parque —es la solicitud de
     * JumpSystem que Google aún no ha aprobado (§7·A·2)—. Mandar al admin a pelear con lo segundo es
     * hacerle perder el día.
     *
     * @param  string  $reason  el campo `error.status` o `error` de Google; cadena vacía si no vino.
     */
    public static function from(int $httpStatus, string $reason): self
    {
        $status = match (true) {
            // El permiso ya no vale: solo se arregla reconectando.
            $httpStatus === 401,
            in_array($reason, ['invalid_grant', 'invalid_client', 'UNAUTHENTICATED'], true) => GoogleBusinessStatus::Expired,

            // La API no está disponible PARA NOSOTROS: aprobación o cuota, nunca del parque.
            in_array($reason, ['SERVICE_DISABLED', 'accessNotConfigured', 'PERMISSION_DENIED_API'], true) => GoogleBusinessStatus::NoApiAccess,

            $httpStatus === 403 => GoogleBusinessStatus::Forbidden,
            $httpStatus === 404 => GoogleBusinessStatus::LocationLost,

            // ⚠️ 429 y 5xx caen aquí a propósito: son de Google, no del parque, y apagar la conexión
            // por ellos sería confundir un mal minuto con una avería.
            default => null,
        };

        return new self(
            $status,
            $httpStatus,
            $reason,
            trim("Google respondió HTTP {$httpStatus} · {$reason}"),
        );
    }

    /** ¿Hay que dejar de llamar hasta que alguien haga algo? */
    public function isPermanent(): bool
    {
        return $this->status !== null;
    }
}
