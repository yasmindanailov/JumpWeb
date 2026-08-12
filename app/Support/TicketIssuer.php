<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use Illuminate\Support\Str;

/**
 * Emisión de tickets de un pedido pagado. Extraído de `RedsysReturnHandler` (7.3) para que
 * la confirmación Redsys y el pedido manual de back-office (efectivo/datáfono) compartan la
 * MISMA lógica — un Ticket por plaza para los items con franja; los complementos sin franja
 * (calcetines, taquillas) no emiten ticket. `qr_token` aleatorio e impredecible (#62).
 *
 * Servicio sin estado: el llamador garantiza que se invoca una sola vez por Order (rama
 * "pago autorizado") dentro de su propia transacción.
 */
class TicketIssuer
{
    public function issue(Order $order): void
    {
        // Idempotencia (auditoría Fase 1, cinturón del fix C1): si la Order ya tiene tickets,
        // NO re-emitimos. El contrato es "una sola vez por Order", pero una 2.ª notificación
        // Redsys autorizada (reintento + autorización tardía) podría llamar aquí de nuevo; sin
        // esta guarda se duplicarían los QR. La guarda principal vive en `RedsysReturnHandler`
        // (`$canFulfil`); esta es defensa en profundidad en el propio emisor.
        if ($order->tickets()->exists()) {
            return;
        }

        $order->items()->whereNotNull('slot_id')->each(function (OrderItem $item) use ($order): void {
            $units = max(1, (int) $item->seats);
            for ($i = 0; $i < $units; $i++) {
                Ticket::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'slot_id' => $item->slot_id,
                    'qr_token' => $this->uniqueQrToken(),
                    'status' => Ticket::STATUS_PURCHASED,
                ]);
            }
        });
    }

    /** Token aleatorio único (32 chars). Reintentamos ante colisión (raro pero posible). */
    private function uniqueQrToken(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = Str::random(32);
            if (! Ticket::where('qr_token', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not generate a unique QR token after 5 attempts.');
    }
}
