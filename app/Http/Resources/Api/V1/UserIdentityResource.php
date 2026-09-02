<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una identidad externa vinculada, vista por su titular (`specs/auth-con-google.md` §8, tanda T3).
 *
 * ⚠️ **No publica el `sub`**, al revés que el export del art. 20: lo que esta pantalla necesita saber
 * es *con qué cuenta se entra y desde cuándo*, y el identificador del proveedor no le añade nada.
 * Sale en el export porque allí el titular pide **todo lo que hay**, que es otro acto. Misma doctrina
 * que la IP de los consentimientos.
 *
 * @property-read UserIdentity $resource
 */
class UserIdentityResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $identity = $this->resource;

        return [
            'provider' => (string) $identity->provider,
            'email_at_link' => $identity->email_at_link,
            'linked_at' => $identity->linked_at?->toIso8601String(),
            // Compuesta por el SERVIDOR y con la zona horaria de la instalación, como en los
            // consentimientos: si la formateara el cliente, un titular en otro huso vería otra fecha.
            'linked_label' => DisplayTime::format($identity->linked_at, 'd/m/Y'),
        ];
    }
}
