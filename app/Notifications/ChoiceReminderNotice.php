<?php

namespace App\Notifications;

use App\Domain\Booking\Models\AddonChoiceGroup;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailSlip;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **«FALTA ELEGIR…»** (P4 de `fiesta-sistema-nuevo.md` §4.20; `[DECIDIDO owner]` `#913` —«el día antes del plazo», un
 * correo— y `#914`): al titular, el día antes de que se cierre la lista, si a su fiesta le falta contestar algún GRUPO DE
 * OPCIONES con «hay que elegir» (la merienda). Solo si falta: el comando no lo construye si no.
 *
 * ⚠️ Dice qué pasa si no se elige —lo decide el parque el día de la fiesta— y no como reproche: falta poco y se arregla con un
 * toque, desde el botón, que lleva a la lista.
 *
 * ⚠️ Lleva las CLAVES de los grupos y no sus títulos: el título se lee al pintar, en el idioma de quien lo recibe. Y se
 * vuelve a mirar al ENVIAR ({@see shouldSend()}): si entre la pasada y la cola se eligió, no sale un «falta elegir» falso.
 *
 * `ShouldQueue` por `PAY-14`, como el resto: el envío no bloquea al comando.
 */
class ChoiceReminderNotice extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  list<string>  $groupKeys  las claves de lo que falta (`addon_choice_groups.key`) */
    public function __construct(
        public OrderItem $reservation,
        public array $groupKeys,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** ¿Sigue faltando al enviarlo? La cola puede tardar, y el cliente puede haber elegido mientras. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        $fresh = $this->reservation->fresh(['ticketType.choiceGroups', 'ticketType.configurableAddons', 'children', 'order']);

        return $fresh !== null && PostFormAddons::unansweredRequiredGroups($fresh) !== [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = (string) $this->reservation->order->code;
        $cierra = app(GuestCountPolicy::class)->deadlineFor($this->reservation);

        $message = (new BrandedMailMessage($this))
            // ❗ El dato DELANTE (`#506`): en el corte de una lista de móvil tiene que entrar lo que falta, no el saludo.
            ->subject(__('emails.choice_reminder.subject', ['code' => $code]))
            ->line(__('emails.choice_reminder.intro', [
                'day' => DisplayTime::dayInSentence($cierra),
                'hora' => $cierra?->format('H:i') ?? '',
            ]));

        // Cada grupo que falta, por su título (lo que el parque le puso en el panel), en el idioma de quien lo recibe.
        foreach ($this->titles() as $title) {
            $message->line($title);
        }

        $message->hero('emails.choice_reminder', 'warn', EmailSlip::forItem($this->reservation));

        return $message
            ->line(__('emails.choice_reminder.park_decides'))
            ->action(
                __('emails.choice_reminder.action'),
                // Fuente ÚNICA del enlace firmado, la misma que el correo del post-form y la víspera.
                $this->reservation->guestFormSignedUrl(),
            )
            ->line(__('emails.choice_reminder.outro'));
    }

    /** @return list<string> */
    private function titles(): array
    {
        return AddonChoiceGroup::query()
            ->where('product_id', $this->reservation->ticket_type_id)
            ->whereIn('key', $this->groupKeys)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (AddonChoiceGroup $group): string => $group->displayTitle())
            ->all();
    }
}
