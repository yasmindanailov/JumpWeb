<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Services\OrderLedger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **El desglose de dinero, publicado en DOS EJES** (`DECISIONES #127`, `openapi/v1.yaml` → `Ledger`).
 *
 * Este serializador **no compone nada**: recibe un {@see OrderLedger} ya resuelto y lo transcribe.
 * Toda la regla —qué canales hay, cómo se reparte la compensación, qué frase explica el estado— vive
 * en el dominio, que es lo que hace que las ocho superficies enseñen lo mismo. Recomponer aquí una
 * sola de esas decisiones sería la novena.
 *
 * @property-read OrderLedger $resource
 */
class LedgerResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $l = $this->resource;

        return [
            // EJE VALOR — los cinco canales cierran `total_cents`, siempre (`PAY-16`).
            'value' => [
                'total_cents' => $l->valor,
                'paid_online_cents' => $l->pagadoOnline,
                'pending_online_cents' => $l->pendienteOnline,
                'paid_at_gate_cents' => $l->pagadoPuerta,
                'pending_at_gate_cents' => $l->pendientePuerta,
                'compensated_cents' => $l->compensado,
            ],
            // EJE CAJA — `held = paid_online + pending_refund` (`PAY-17`). NO resta del valor.
            //
            // ⚠️ `has_cash` viaja publicado y no derivado (`DECISIONES #128`): la condición de
            // enseñar el ancla la decide el dominio. Derivarla en la interfaz es lo que dejó al
            // cliente sin ver, en un pedido normal, cuánto había salido de su banco.
            'cash' => [
                'charged_online_cents' => $l->cobradoOnline,
                'refunded_cents' => $l->devuelto,
                'held_cents' => $l->retenido,
                'pending_refund_cents' => $l->pendienteDevolucion,
                'has_cash' => $l->hasCash(),
                'charged_method' => $l->cobroMetodo,
                'charged_at_label' => $l->cobroFecha,
            ],
            // Trazabilidad: lo facturado al reservar. FUERA de la suma, a propósito.
            'invoiced_cents' => $l->facturado,
            // ⚠️ Y su frase, con DIRECCIÓN E IMPORTE (`L6`, `DECISIONES #133`). `null` cuando no hay
            // diferencia: **es la condición de enseñar la línea**, publicada en vez de dejar que cada
            // superficie re-derive `invoiced_cents !== value.total_cents` — la misma lección de `L1`.
            'invoiced_hint' => $l->facturadoNota,
            'gate_lines' => $l->gateLines,
            'has_deposit' => $l->hasDeposit,
            // ⚠️⚠️ **Si esto es `false`, el desglose por canales NO es cierto** y el cliente no debe
            // pintarlo (`DECISIONES #132`). Se publica en vez de dejar que cada superficie recomponga
            // las identidades: son dos y ya se escribían en un solo sitio.
            'is_consistent' => $l->cuadra,
            'note' => $l->nota,
        ];
    }
}
