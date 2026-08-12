<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Desglose financiero de UNA reserva (un producto principal + sus complementos).
 *
 * Fuente ÚNICA del bloque "Totales del producto" para TODAS las superficies (la
 * sub-card del panel, el modal del calendario, la hoja PDF y "Mis pedidos" del
 * cliente) → mismos números en todos sitios por construcción.
 *
 * Dimensiones (reconcilian: `valor = pagadoOnline + aCobrarPuerta + cobradoPuerta`):
 *  - `valor`            → Σ `chargedSubtotalCents` de los items NO cancelados (lo que
 *                          vale el producto hoy; ya descuenta unidades incluidas gratis).
 *  - `pagadoOnline`     → Σ `itemCollectedCents` (cargado − extra_due) de los no cancelados.
 *  - `aCobrarPuerta`    → extra_due NETO PENDIENTE (reserva no finalizada): falta cobrar en puerta.
 *  - `cobradoPuerta`    → extra_due NETO ya resuelto (reserva finalizada): cobrado en puerta.
 *  - `devuelto`         → Σ `payment_refunds.succeeded` de la reserva (principal + complementos).
 *  - `pendienteReembolso` → items CANCELADOS cuyo COBRADO online aún no se devolvió.
 *
 * Los complementos heredan el estado "finalizado" del principal. Mismo criterio de
 * signo/anulación que {@see OrderFinancialSummary} y {@see Order::pendingAtGateLines()}.
 */
final readonly class ReservationFinancials
{
    public function __construct(
        public int $valor,
        public int $pagadoOnline,
        public int $aCobrarPuerta,
        public int $cobradoPuerta,
        public int $devuelto,
        public int $pendienteReembolso,
    ) {}

    public static function make(Order $order, OrderItem $principal): self
    {
        /** @var Collection<int,OrderItem> $items */
        $items = collect([$principal])->merge($principal->children);
        $finished = $principal->isFinishedInPractice();

        $valor = 0;
        $pagadoOnline = 0;
        $gateNeto = 0;
        $devuelto = 0;
        $pendienteReembolso = 0;

        foreach ($items as $item) {
            $devuelto += $order->itemRefundedCents($item);
            // "Pendiente de devolución" por línea (robustez #198): unifica la CANCELACIÓN
            // (todo su online no devuelto) y la REDUCCIÓN de cantidad por debajo de lo
            // pagado online (p. ej. 2 entradas → 1) → así sale también en la card del
            // producto, no solo en el total del pedido.
            $pendienteReembolso += $order->itemPendingRefundCents($item);

            if ($item->isCancelled()) {
                // Un cancelado no es producto actual: fuera del valor/pagado/puerta.
                continue;
            }

            $valor += $item->chargedSubtotalCents();
            $pagadoOnline += $order->itemCollectedCents($item);
            // Bucket de puerta: delta de ediciones + resto de la señal (#225). `pagadoOnline`
            // (itemCollectedCents) ya resta el resto-señal → refleja la señal cobrada.
            $gateNeto += $order->itemExtraDueCents($item) + $order->itemDepositRemainderCents($item);
        }

        $gateNeto = max(0, $gateNeto);

        return new self(
            valor: $valor,
            pagadoOnline: max(0, $pagadoOnline),
            aCobrarPuerta: $finished ? 0 : $gateNeto,
            cobradoPuerta: $finished ? $gateNeto : 0,
            devuelto: $devuelto,
            pendienteReembolso: $pendienteReembolso,
        );
    }

    /** ¿Hay desglose más allá del valor plano (puerta / devolución / pendiente)? */
    public function hasActivity(): bool
    {
        return $this->aCobrarPuerta > 0 || $this->cobradoPuerta > 0
            || $this->devuelto > 0 || $this->pendienteReembolso > 0;
    }
}
