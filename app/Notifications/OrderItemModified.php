<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailBookBlock;
use App\Domain\Booking\Services\EmailProductCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sub-fase 7.2e cimientos — Email tras modificación de un OrderItem que NO
 * implica reembolso parcial completo (los reembolsos parciales por sí solos
 * van con `OrderItemRefunded`).
 *
 * Polivalente: cubre cambios de fecha/hora, cantidad, producto, addons, datos
 * del evento. El caller (orquestador `executeItemEdit` de 7.2e.3+) pasa un
 * array `$changes` con keys descriptivas y, opcionalmente, importes:
 *
 *  - `$changes['slot_change']`         → ['old' => '12/06 16:00', 'new' => '15/06 18:00']
 *  - `$changes['quantity_change']`     → ['old' => 3, 'new' => 5]
 *  - `$changes['product_change']`      → ['old' => 'Cumple Tortugas 1h', 'new' => 'Cumple Castores 2h']
 *  - `$changes['event_data_change']`   → true (booleano simple; el detalle no se cita)
 *  - `$changes['addon_change']`        → ['added' => [...], 'removed' => [...], 'updated' => [...]]
 *
 * ▶ Desde la T3·3 del LIBRO (`specs/desglose-libro.md` §6.3.4, D-T3·21) este correo NO lleva ningún
 * importe suelto: el bloque del libro (`EmailBookBlock`) dice cada línea con su signo, el Total, lo
 * pagado y el saldo con su clase, compuesto AL ENVIAR. Los tres céntimos que le pasaba el editor
 * eran una segunda composición del mismo dinero —calculada con la cascada de créditos— y murieron
 * con ella. (Antes, en la T5 de `cumple-mixto.md` §25.5, se había retirado ya el importe del
 * reembolso: un reembolso real manda su propio correo, `OrderItemRefunded`.)
 *
 * Defensivo: si `$changes` está vacío y no hay importes, no debería enviarse
 * (el orquestador comprueba antes de notify). Para defensa en profundidad, el
 * email igual se renderiza con un mensaje genérico de actualización.
 *
 * NOTA: este Notification SOLO existe como esqueleto en 7.2e.0 — su uso real
 * llega en 7.2e.2+ cuando las acciones del modal Gestionar se implementan. Los
 * textos definitivos se afinan empíricamente con la clienta entonces. Por ahora
 * cubre el contrato + se valida con tests unitarios del envío.
 */
class OrderItemModified extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string,mixed>  $changes
     */
    public function __construct(
        public Order $order,
        public OrderItem $item,
        public array $changes = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $productName = $this->item->ticketType?->tr('name')
            ?? __('emails.order_item_modified.product_fallback', ['id' => $this->item->id]);

        $message = (new MailMessage)
            ->subject(__('emails.order_item_modified.subject', ['code' => $this->order->code, 'product' => $productName]))
            ->greeting(__('emails.order_item_modified.greeting'))
            ->line(__('emails.order_item_modified.intro', [
                'code' => $this->order->code,
                'product' => $productName,
            ]));

        // Subcard de la reserva afectada (#251): refleja el estado ACTUAL del item (tras el cambio).
        $message->line(EmailProductCard::forItem($this->item));

        // Cambios descriptivos. Cada tipo tiene su línea con datos.
        if (isset($this->changes['slot_change'])) {
            $message->line(__('emails.order_item_modified.slot_change', [
                'old' => $this->changes['slot_change']['old'] ?? '—',
                'new' => $this->changes['slot_change']['new'] ?? '—',
            ]));
        }
        if (isset($this->changes['quantity_change'])) {
            $message->line(__('emails.order_item_modified.quantity_change', [
                'old' => $this->changes['quantity_change']['old'] ?? '—',
                'new' => $this->changes['quantity_change']['new'] ?? '—',
            ]));
        }
        if (isset($this->changes['product_change'])) {
            $message->line(__('emails.order_item_modified.product_change', [
                'old' => $this->changes['product_change']['old'] ?? '—',
                'new' => $this->changes['product_change']['new'] ?? '—',
            ]));
        }
        if (! empty($this->changes['event_data_change'])) {
            $message->line(__('emails.order_item_modified.event_data_change'));
        }
        if (! empty($this->changes['addon_change'])) {
            $message->line(__('emails.order_item_modified.addon_change'));
        }

        // EL LIBRO del pedido a día de hoy (D-T3·5): la subida como línea «+», la bajada como línea
        // «−» —la BAJADA también es dinero (`#155`), y aquí la cuenta el mismo bloque que todo lo
        // demás— y el saldo con su clase: «a pagar en el parque» o «a devolver en el parque» (D2).
        $message->line(EmailBookBlock::forOrder($this->order));

        return $message
            ->action(__('emails.order_item_modified.action'), route('account.orders'))
            ->line(__('emails.order_item_modified.contact'));
    }
}
