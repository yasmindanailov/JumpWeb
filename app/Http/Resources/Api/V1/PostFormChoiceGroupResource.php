<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\PostFormChoiceGroupView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un GRUPO DE OPCIONES de venta posterior de una reserva (`PostFormChoiceGroup`, 1.62.0, `DECISIONES #914`): «¿Qué
 * merienda?», cuál está elegida y si falta elegir. Lo que la lista de invitados pinta como una pregunta de una respuesta; las
 * reglas («elige una», obligatoria) las aplica el servidor al guardar.
 *
 * @property-read PostFormChoiceGroupView $resource
 */
class PostFormChoiceGroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $group = $this->resource;

        return [
            'key' => $group->key,
            'title' => $group->title,
            'required' => $group->required,
            'chosen_product_id' => $group->chosenAddonId,
            'product_ids' => $group->addonIds,
            'open' => $group->open,
            'pending' => $group->pending(),
        ];
    }
}
