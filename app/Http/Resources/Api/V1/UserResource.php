<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 0 — el usuario autenticado tal y como lo ve `/api/v1` (spec §4.2 y §4.4).
 *
 * **Allowlist, no denylist.** `SEC-10` y `SEC-07` empujan en la misma dirección: lo que sale se
 * enumera, no se filtra. `$user->toArray()` habría funcionado hoy —`#[Hidden]` tapa `password` y
 * `remember_token`— y habría empezado a filtrar el día que alguien añadiera una columna al modelo.
 * Aquí una columna nueva no sale hasta que alguien la escriba, que es la dirección segura del
 * error.
 *
 * **`$wrap = null`** por el contrato de §4.3: «recurso en la raíz; listas bajo `data` + `meta`».
 * Un recurso individual envuelto en `data` obligaría a cada cliente a desenvolver dos formas
 * distintas. Las colecciones llegan en el paso 1 y declaran su envoltorio explícitamente.
 *
 * **Fechas en ISO-8601** (`toIso8601String`), como ya hace el export RGPD de «Mi cuenta»: una sola
 * forma de fecha en todo el sistema.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /** El recurso va en la raíz de la respuesta, sin envoltorio. */
    public static $wrap = null;

    /**
     * `email_verified_at` viaja aunque sea `null`, y a propósito: es como el cliente sabe que hay
     * un registro «pay-first» sin verificar y debe ofrecer el reenvío. `pending_email` es el
     * cambio de correo en curso (doble opt-in, §4.2), que la pantalla de perfil necesita para
     * poder cancelarlo o reenviarlo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'marketing_opt_in' => (bool) $this->marketing_opt_in,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'pending_email' => $this->pending_email,
            'pending_email_sent_at' => $this->pending_email_sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
