<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Ticket;
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

        // Un ticket es una ADMISIÓN, y las admisiones son líneas de PRIMER NIVEL (`specs/hora-extra.md`
        // §4.11, `#410`): una HIJA que ocupa (la hora extra) estrena franja y plazas, pero es la
        // MISMA persona quedándose — no una entrada nueva. Sin este filtro emitiría `seats` tickets
        // propios en silencio (era no-op mientras ninguna hija tenía franja; con la hora extra deja
        // de serlo, y por eso se decide aquí y se fija con caso, no se deja emergente).
        $order->items()->whereNull('parent_item_id')->whereNotNull('slot_id')->each(function (OrderItem $item) use ($order): void {
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
