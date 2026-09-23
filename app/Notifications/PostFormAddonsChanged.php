<?php

namespace App\Notifications;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailBookBlock;
use App\Domain\Booking\Services\EmailProductCard;
use App\Domain\Booking\Services\EmailSlip;
use App\Domain\Booking\Services\PostFormAddonChanges;
use App\Domain\Platform\Services\Money;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Los EXTRAS de una reserva han cambiado desde el post-form
 * (`specs/complementos-post-reserva.md` §4.7·6, D8).
 *
 * ⚠️⚠️ **Agrupado por VENTANA, no por gesto.** Un guardado que sube el cubo de refrescos, retira las
 * tapas y añade la tarta manda **un** correo con las tres líneas. Uno por gesto convertiría una
 * tarde de indecisión en una bandeja de entrada llena — y, peor, ahogaría la señal que este correo
 * existe para dar: **es la única forma que tiene el titular de enterarse de que alguien con su
 * enlace le ha encargado algo** (D12: no hay anti-bot, y este aviso es media defensa).
 *
 * ⚠️ Voz PROPIA y no la del suplemento mixto: aquél anuncia un importe que el producto calcula solo;
 * éste confirma **lo que la persona acaba de pedir**. El bloque del libro va igual, porque la
 * pregunta que sigue a «he pedido dos cubos» es siempre «¿y cuánto pago ahora?».
 *
 * `ShouldQueue` por `PAY-14`: ningún email bloquea al cliente.
 */
class PostFormAddonsChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrderItem $item,
        public PostFormAddonChanges $changes,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = $this->item->order?->code ?? '—';
        $currency = $this->item->order?->currency ?? 'EUR';

        $message = (new BrandedMailMessage($this))
            ->subject(__('emails.postform_addons.subject', ['code' => $code]))
            ->hero('emails.postform_addons', 'ok', EmailSlip::forItem($this->item))
            ->line(__('emails.postform_addons.intro', ['code' => $code]))
            ->line(EmailProductCard::forItem($this->item));

        // Una línea por movimiento, con su verbo: añadido · retirado · cambiado. Un «tus extras son
        // ahora X, Y» dejaría la RETIRADA sin contar, y retirar es justo lo que hay que confirmar
        // cuando quien lo hizo no fue el titular.
        foreach ($this->changes->moves as $move) {
            $message->line(match (true) {
                $move['from'] === 0 => __('emails.postform_addons.added', [
                    'name' => $move['name'], 'qty' => $move['to'],
                ]),
                $move['to'] === 0 => __('emails.postform_addons.removed', ['name' => $move['name']]),
                default => __('emails.postform_addons.updated', [
                    'name' => $move['name'], 'old' => $move['from'], 'new' => $move['to'],
                ]),
            });
        }

        // El neto de ESTA pasada, con signo y rotulado. Sin rótulo, un «12,00 €» no dice si es lo
        // que se añade o lo que se quita, y las dos direcciones caben en el mismo correo.
        if ($this->changes->deltaCents !== 0) {
            $message->line(__($this->changes->deltaCents > 0
                ? 'emails.postform_addons.delta_up'
                : 'emails.postform_addons.delta_down',
                ['amount' => Money::format(abs($this->changes->deltaCents), $currency)]));
        }

        $message->line(__('emails.postform_addons.where_to_pay'));

        // EL LIBRO del pedido a día de hoy: los extras son líneas más, y el saldo dice lo que se
        // paga —o se devuelve— en el parque.
        $message->line(EmailBookBlock::forOrder($this->item->order));

        return $message->line(__('emails.postform_addons.editable'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'order_item_id' => $this->item->id,
            'delta_cents' => $this->changes->deltaCents,
            'moves' => count($this->changes->moves),
        ];
    }
}
