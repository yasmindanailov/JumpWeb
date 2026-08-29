<?php

namespace App\Notifications;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailProductCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El suplemento de una fiesta MIXTA ha cambiado (`docs/specs/cumple-mixto.md` §12).
 *
 * `[DECIDIDO owner, 2026-08-29]`: «al igual que cuando nosotros gestionamos la reserva y le llega
 * correo al cliente, con este caso también, donde él gestiona esa parte que es su trabajo».
 *
 * ⚠️ **Se envía cuando cambia el IMPORTE, no en cada guardado del post-form.** Ese formulario está
 * hecho para editarse durante días; sin esa condición, un cliente que ajusta nombres tres tardes
 * seguidas recibiría tres correos idénticos. La condición vive en `MixedPartySurcharge::reconcile`,
 * que es quien sabe si algo se movió.
 *
 * ⚠️ **Cubre las dos direcciones.** Si el cliente corrige la edad hacia abajo, el suplemento baja o
 * desaparece — y eso también hay que contarlo: un cliente que recibió «se añaden 7,00 €» y luego
 * corrige tiene que saber que ya no los debe. La lección es la misma que `#155` dejó escrita para
 * `OrderItemModified`: **la bajada también es dinero**.
 *
 * `ShouldQueue` por `PAY-14`: ningún email bloquea al cliente.
 */
class MixedPartySurchargeChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $byCustomer  ¿lo movió el propio cliente al guardar sus edades, o el parque?
     *
     * ⚠️ La frase de entrada decía «Has actualizado las edades de los invitados» SIEMPRE, también
     * cuando la reconciliación la disparaba el panel al cambiar la cantidad o la fecha. Al cliente
     * se le atribuía algo que no había hecho, en un correo que le anuncia un cargo — que es
     * exactamente donde peor sienta.
     */
    public function __construct(
        public OrderItem $item,
        public int $oldCents,
        public int $newCents,
        public bool $byCustomer = true,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = $this->item->order?->code ?? '—';
        $product = $this->item->ticketType?->tr('name') ?? '—';
        $amount = fn (int $cents): string => number_format($cents / 100, 2, ',', '.');

        $message = (new MailMessage)
            ->subject(__('emails.mixed_party_surcharge.subject', ['code' => $code]))
            ->greeting(__('emails.mixed_party_surcharge.greeting'))
            ->line(__($this->byCustomer
                ? 'emails.mixed_party_surcharge.intro'
                : 'emails.mixed_party_surcharge.intro_by_park', ['code' => $code, 'product' => $product]));

        $message->line(EmailProductCard::forItem($this->item));

        // Tres desenlaces y tres frases: nace, cambia de importe o desaparece. Fundirlos en «tu
        // suplemento es ahora X» dejaría el caso de la retirada diciendo «ahora es 0,00 €», que es
        // exactamente el tipo de línea que un cliente no sabe leer.
        if ($this->newCents === 0) {
            $message->line(__('emails.mixed_party_surcharge.removed'));
        } elseif ($this->oldCents === 0) {
            $message->line(__('emails.mixed_party_surcharge.added', ['amount' => $amount($this->newCents)]));
        } else {
            $message->line(__('emails.mixed_party_surcharge.updated', [
                'old' => $amount($this->oldCents),
                'new' => $amount($this->newCents),
            ]));
        }

        if ($this->newCents > 0) {
            $message->line(__('emails.mixed_party_surcharge.where_to_pay'));
        }

        return $message->line(__('emails.mixed_party_surcharge.editable'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'order_item_id' => $this->item->id,
            'old_cents' => $this->oldCents,
            'new_cents' => $this->newCents,
        ];
    }
}
