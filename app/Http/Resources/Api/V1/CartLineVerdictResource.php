<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\CartLineProblem;
use App\Domain\Booking\Contracts\CartLineVerdict;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 4 · paso 4.0b·6 — el veredicto de una línea candidata.
 *
 * **Los problemas viajan sin contexto, y es una decisión.** El dominio adjunta a cada uno los datos
 * para componer el aviso —el mínimo del pack, el tope de la cesta, la etiqueta del campo—, porque un
 * consumidor cualquiera puede necesitarlos; el sidebar web los usa para nombrar los campos que
 * faltan. Un cliente de esta API **ya los tiene**: `GET catalog/products/{id}` publica
 * `min_quantity` y los `event_fields` con su etiqueta, y `GET config` publica `cart_max_lines`.
 * Reenviarlos aquí sería un segundo sitio del que leer el mismo número, y —al ser un mapa libre—
 * obligaría a relajar `additionalProperties: false` en el contrato justo en el esquema más nuevo.
 *
 * @property-read CartLineVerdict $resource
 */
class CartLineVerdictResource extends JsonResource
{
    /** El recurso va en la raíz (spec §4.3). */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $verdict = $this->resource;

        return [
            'valid' => $verdict->accepted,
            'problems' => array_map(static fn (CartLineProblem $problem): array => [
                'reason' => $problem->reason,
                // La clave del campo del evento que falta, para resaltar ESE input. `null` cuando el
                // problema es de la línea entera.
                'field' => $problem->field,
            ], $verdict->problems),
            // Con qué cantidad entraría de verdad. 0 si no entra: una línea rechazada no tiene
            // cantidad efectiva, y devolver la pedida invitaría a usarla igualmente.
            'quantity' => $verdict->quantity,
            // ¿Se recortó al cupo? El servidor lo hace en silencio desde siempre
            // (anti-manipulación); un cliente que pidió 10 y entra con 6 tiene que poder enseñar 6.
            'quantity_capped' => $verdict->quantityCapped,
            // El techo de esta franja con la cesta ya descontada — el mismo número que acota el
            // selector en `availability/{product}/times`, para no obligar a pedir las dos cosas.
            'max_quantity' => $verdict->maxQuantity,
            // Índice de la línea de la cesta ENVIADA que absorbería a esta, o `null` si entra como
            // nueva. Sin esto, un cliente que compone su cesta en el navegador crearía una línea
            // duplicada donde el servidor habría sumado cantidades.
            'merges_with_index' => $verdict->mergesWithIndex,
        ];
    }
}
