<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Models\CustomerCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 6 · subsistema A — el CARNÉ QR del titular, visto por él (`specs/identidad-qr-puerta.md`
 * §4.1, §4.5, §9.2 A·8).
 *
 * `token` es el dato del QR TAL CUAL —el payload es el token pelado (§3·C), sin URL—: la app o el
 * cajón lo pintan como QR (modo alfanumérico, versión 2 con corrección H). Escanearlo NO autentica
 * (§4.2): identifica al titular en la puerta, y nada más. `token` viene `null` solo si la clave de
 * cifrado rotó (§8.1): el titular rota el carné y listo.
 *
 * @property-read CustomerCard $resource
 */
class CustomerCardResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->plainToken(),
            'issued_at' => $this->resource->issued_at->toIso8601String(),
        ];
    }
}
