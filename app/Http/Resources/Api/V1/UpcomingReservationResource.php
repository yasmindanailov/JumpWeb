<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\UpcomingReservation;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 1 — una reserva futura del cliente.
 *
 * Serializa el DTO `Booking\Contracts\UpcomingReservation` **tal cual**, sin recalcular nada: el
 * contrato de Fase 2 ya decidió qué se ve desde fuera de Booking y en qué forma. Su docblock lo
 * dejó escrito con esta fase en mente —«`date` en `Y-m-d` mantiene el DTO serializable tal cual
 * para la API v1 de Fase 3»— y aquí se cumple la promesa: la API no abre una segunda vía de
 * lectura al dominio, usa la que ya existía.
 *
 * `time_window` viene ya compuesta por Booking («10:00–12:00», «10:00 – sin límite») porque componer
 * esa ventana es una regla del dominio —depende de si la franja tiene fin— y no de quien la pinta.
 *
 * @property-read UpcomingReservation $resource
 */
class UpcomingReservationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource->date,
            // La misma etiqueta que el bloque de cuenta lleva pintando desde `#221`, ahora desde su
            // fuente única (`DisplayTime::dayLabel`) en vez de recompuesta en cada superficie.
            'date_label' => DisplayTime::dayLabel($this->resource->date),
            'time_window' => $this->resource->timeWindow,
            'product_name' => $this->resource->productName,
        ];
    }
}
