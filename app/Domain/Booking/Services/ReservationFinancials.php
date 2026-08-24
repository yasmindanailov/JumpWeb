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
 * ## DOS EJES, y no se mezclan (`DECISIONES #127`)
 *
 * **EJE VALOR** — lo que vale el producto, descompuesto por CANAL. Cierra siempre:
 *
 *     valor = pagadoOnline + pendienteOnline + aCobrarPuerta + cobradoPuerta + compensado
 *
 *  - `valor`            → Σ `chargedSubtotalCents` de los items NO cancelados (lo que vale el
 *                          producto hoy; ya descuenta unidades incluidas gratis).
 *  - `pagadoOnline`     → lo cobrado por WEB que respalda este producto, **NETO de compensación**.
 *                          `0` si el pedido no se ha cobrado todavía.
 *  - `pendienteOnline`  → lo que falta por cobrar POR WEB. `0` en cuanto el pedido se cobra.
 *  - `aCobrarPuerta`    → cargo de puerta NETO pendiente (reserva no finalizada, o pedido sin cobrar).
 *  - `cobradoPuerta`    → cargo de puerta NETO ya resuelto: reserva finalizada **de un pedido
 *                          COBRADO**. Un pedido que nadie pagó no cobró nada en el parque.
 *  - `compensado`       → dinero devuelto SIN quitar producto (la compensación de cortesía, o un
 *                          reembolso sin cancelar). No es un canal de cobro y no baja el valor:
 *                          es su propio término, y por eso la «ley de caja» tenía una excepción
 *                          que en realidad era un término que faltaba.
 *
 * **EJE CAJA** — el dinero que vuelve al cliente. Es OTRO eje: no resta del valor.
 *
 *  - `devuelto`         → Σ `payment_refunds.succeeded` de la reserva (principal + complementos).
 *  - `pendienteReembolso` → items CANCELADOS cuyo COBRADO online aún no se devolvió.
 *
 * ⚠️ **`pagadoOnline` y `pendienteOnline` son excluyentes**: el mismo importe está en uno o en
 * otro según el pedido se haya cobrado o no. Publicarlo sin esa distinción es lo que hacía que un
 * pedido SIN pagar anunciara «Pagado online 11,90 €» (`specs/desglose-dinero-cliente.md` §4.ter.2).
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
        public int $pendienteOnline = 0,
        public int $compensado = 0,
    ) {}

    /**
     * ¿Procede el aviso «señal pagada · resto en el parque» para esta reserva? (#225 F3)
     *
     * Son TRES condiciones y hay que cumplirlas las tres: el pedido está pagado, el producto usa
     * señal, y queda algo por cobrar en puerta. Publicar los números por separado y dejar que cada
     * superficie las compona es como divergen — y ya hay cuatro superficies pintando este bloque.
     *
     * Vive aquí porque esta clase se declara «fuente ÚNICA del bloque de totales del producto para
     * TODAS las superficies»: la composición es parte del bloque, no de quien lo pinta.
     *
     * ⚠️ Mira el estado CRUDO del pedido, no `displayStatus()`. Para un pedido pagado los dos
     * coinciden —`displayStatus()` solo convierte un pendiente vencido en caducado—, y usar el
     * crudo mantiene la conducta byte a byte con la que tenía el sidebar.
     */
    public function showsDepositNote(Order $order, OrderItem $principal): bool
    {
        return $order->status === Order::STATUS_PAID
            && ($principal->ticketType?->hasDeposit() ?? false)
            && $this->aCobrarPuerta > 0;
    }

    public static function make(Order $order, OrderItem $principal): self
    {
        /** @var Collection<int,OrderItem> $items */
        $items = collect([$principal])->merge($principal->children);
        $finished = $principal->isFinishedInPractice();
        // ⚠️ «Sin cobro no hay cobro» (`DECISIONES #127`): que la franja haya pasado NO significa que
        // se cobrara nada en el parque si el pedido nunca llegó a cobrarse. Sin esta condición, un
        // pedido abandonado cuya franja pasa declara dinero cobrado que no existe (medido en staging:
        // «Pagado en el parque 90,00 €» con 0,00 € realmente cobrados).
        // `paid_at` es el predicado correcto y cubre los DOS canales de cobro reales: lo escriben
        // `RedsysReturnHandler` (web) y `ManualOrderFulfiller` (taquilla), y sobrevive a la
        // cancelación y al reembolso.
        $collected = $order->paid_at !== null;

        $valor = 0;
        $online = 0;
        $gateNeto = 0;
        $devuelto = 0;
        $pendienteReembolso = 0;
        $compensado = 0;

        foreach ($items as $item) {
            $refundedOnItem = $order->itemRefundedCents($item);
            $devuelto += $refundedOnItem;
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
            $online += $order->itemCollectedCents($item);
            // Bucket de puerta: delta de ediciones + resto de la señal (#225). `itemCollectedCents`
            // ya resta el resto-señal → refleja la señal cobrada.
            $gateNeto += $order->itemExtraDueCents($item) + $order->itemDepositRemainderCents($item);
        }

        // ⚠️ La compensación se REPARTE desde el pedido, no se redefine aquí: su fórmula está
        // anclada a CAJA porque la versión por-línea sobre-reporta cuando la pérdida de valor no
        // deja huella en el ítem (`#225`, `DECISIONES #127`). Una sola fórmula, un solo número.
        $compensado = $order->reservationCompensatedCents($principal);

        $gateNeto = max(0, $gateNeto);
        $online = max(0, $online);
        $gateResuelto = $finished && $collected;

        return new self(
            valor: $valor,
            pagadoOnline: $collected ? $online - $compensado : 0,
            aCobrarPuerta: $gateResuelto ? 0 : $gateNeto,
            cobradoPuerta: $gateResuelto ? $gateNeto : 0,
            devuelto: $devuelto,
            pendienteReembolso: $pendienteReembolso,
            pendienteOnline: $collected ? 0 : $online,
            compensado: $compensado,
        );
    }

    /** ¿Hay desglose más allá del valor plano (puerta / devolución / pendiente)? */
    public function hasActivity(): bool
    {
        return $this->aCobrarPuerta > 0 || $this->cobradoPuerta > 0
            || $this->devuelto > 0 || $this->pendienteReembolso > 0
            || $this->pendienteOnline > 0 || $this->compensado > 0;
    }
}
