<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Services\DependentAssigner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 4 · paso 4.0b·4b — las respuestas del evento de un pedido, por reserva.
 *
 * **Deliberadamente sin nada que no sea la PII.** Ni nombre de producto, ni fecha, ni importes: eso
 * ya lo sirve `GET orders/{code}`, y el cliente empareja por `reservation_id`, que es el `id` de la
 * línea que aquel publica. Repetir aquí lo que no es sensible obligaría a mantener dos copias del
 * mismo campo y, sobre todo, invitaría a usar ESTE endpoint —el que devuelve datos de un menor—
 * para pintar el resumen entero.
 *
 * **Las reservas SIN respuestas también salen**, con su lista vacía. Es la diferencia entre «este
 * pack no tenía campos que rellenar» y «no sé nada de esta reserva»: omitirlas dejaría al cliente
 * sin saber si la petición cubrió su línea o si le faltó.
 *
 * La composición —qué respuesta va con qué etiqueta, en qué orden— no está aquí: la pone
 * `OrderItem::eventAnswers()` sobre `TicketType::eventAnswers()`, que es la misma que usa la compra
 * web. Un serializador que compusiera por su cuenta sería la segunda fuente de verdad que este paso
 * existe para no crear.
 *
 * @property-read Order $resource
 */
class OrderEventDataResource extends JsonResource
{
    /** El recurso va en la raíz (spec §4.3). */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $items = $order->items->whereNull('parent_item_id')->values();

        // Fase 6 · menores a cargo, tanda 4 (`specs/menores-a-cargo.md` §9.9.3 D7): los menores para
        // los que es cada entrada salen por AQUÍ y no por `OrderItem`, por la misma razón que las
        // respuestas del pack —nombres de menores en una lista paginada—. Una consulta por pedido, no
        // por línea; y la coherencia con la cantidad actual la deriva el asignador (D4). Es la capa de
        // entrega componiendo Booking e Identity, que es lo único que puede hacerlo.
        $dependents = app(DependentAssigner::class)->forOrderItems(
            $items->map(static fn (OrderItem $item): int => (int) $item->id)->all(),
            $items->mapWithKeys(static fn (OrderItem $item): array => [(int) $item->id => (int) $item->quantity])->all(),
        );

        return [
            'order_code' => (string) $order->code,
            'reservations' => $items
                ->map(static fn (OrderItem $item): array => [
                    'reservation_id' => (int) $item->id,
                    // Solo la fase de RESERVA: lo del post-form vive en su endpoint, que se abre
                    // con firma. El porqué, en el docblock del controlador.
                    'answers' => $item->eventAnswers(TicketType::EVENT_STAGE_BOOKING),
                    // Solo `id` y `name`: la edad y la exención se leen en `GET /me/dependents`.
                    'dependents' => array_map(
                        static fn (Dependent $dependent): array => ['id' => (int) $dependent->getKey(), 'name' => (string) $dependent->name],
                        $dependents[(int) $item->id] ?? [],
                    ),
                ])
                ->values()
                ->all(),
        ];
    }
}
