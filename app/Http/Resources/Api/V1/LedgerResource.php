<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Services\Movement;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Booking\Services\Settlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **EL LIBRO, publicado** (`DECISIONES #305`, `openapi/v1.yaml` → `Ledger`; T3·1 de
 * `specs/desglose-libro.md` §6.3).
 *
 * Este serializador **no compone nada**: recibe un {@see OrderBook} ya resuelto y lo transcribe.
 * Toda la regla —qué líneas hay, con qué etiqueta, qué clase de saldo es, si el libro cierra— vive
 * en el dominio, que es lo que hace que las nueve superficies enseñen lo mismo (D1). Recomponer
 * aquí una sola de esas decisiones sería la décima.
 *
 * ⚠️ Los movimientos y las liquidaciones se publican también cuando el libro NO cierra: son
 * HECHOS. Decidir no pintarlos (la asimetría de `#132`: el cliente ve el Total, los cobros y la
 * frase de revisión) es de quien pinta, y `is_consistent` es la señal.
 *
 * @property-read OrderBook $resource
 */
class LedgerResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $book = $this->resource;

        return [
            'total_cents' => $book->totalCents,
            'paid_cents' => $book->paidCents,
            'balance' => [
                'kind' => $book->balance->kind,
                'cents' => $book->balance->cents,
                'rest_at_park_cents' => $book->balance->restAtParkCents,
            ],
            'movements' => array_map(static fn (Movement $m): array => [
                'kind' => $m->kind,
                'label' => $m->label,
                'amount_cents' => $m->amountCents,
                'occurred_at' => $m->occurredAt,
                'occurred_label' => $m->occurredLabel,
                'reservation_id' => $m->reservationId,
            ], $book->movements),
            'settlements' => array_map(static fn (Settlement $s): array => [
                'kind' => $s->kind,
                'label' => $s->label,
                'amount_cents' => $s->amountCents,
                'occurred_at' => $s->occurredAt,
                'occurred_label' => $s->occurredLabel,
                'status' => $s->status,
                'method' => $s->method,
            ], $book->settlements),
            'has_deposit' => $book->hasDeposit,
            'is_consistent' => $book->isConsistent,
            'note' => $book->note,
        ];
    }
}
