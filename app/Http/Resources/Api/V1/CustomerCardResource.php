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
        $token = $this->resource->plainToken();

        return [
            'token' => $token,
            'issued_at' => $this->resource->issued_at->toIso8601String(),
            // §9.6 B·1: la URL de la IMAGEN la compone el SERVIDOR —como `pdf_url` en el waiver—: el cajón
            // no compone rutas de la API a mano. Nula cuando no hay token que dibujar (clave rotada, §8.1).
            'png_url' => $token === null ? null : route('api.v1.me.card.png'),
        ];
    }
}
