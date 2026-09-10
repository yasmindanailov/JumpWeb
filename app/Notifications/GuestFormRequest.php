<?php

namespace App\Notifications;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailSlip;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email que pide al cliente rellenar el POST-FORM de datos por invitado de UNA reserva de cumpleaños
 * (#217, INDIVIDUALIZADO POR RESERVA). Se envía —uno por reserva— cuando un pedido con uno o varios
 * packs queda PAGADO (online o manual): un pedido con dos cumpleaños manda dos emails, cada uno con
 * el enlace a SU post-form.
 *
 * El botón lleva a un enlace FIRMADO que da acceso al formulario SIN iniciar sesión — la firma HMAC
 * prueba la titularidad de ESA reserva y blinda contra IDOR. El enlace CADUCA en la fecha de la franja
 * de la reserva + 14 días (auditoría Fase 1, A7; ver `OrderItem::guestFormLinkExpiresAt`). El cliente
 * también puede rellenarlo desde "Mis pedidos" (autenticado). Llega en el idioma del usuario.
 */
class GuestFormRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OrderItem $reservation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = (string) ($this->reservation->ticketType?->tr('name') ?? '');
        $code = (string) ($this->reservation->order?->code ?? '');

        // ❗❗ EL ASUNTO, CON EL DATO DELANTE (`#506`). Medido antes: «Completa los datos de tu
        // reserva «Cumpleaños Jump» · R-7MN4PK» son 61 caracteres y en el corte de ~35 de un móvil
        // no entraba ni el producto ni el código: se leía sólo la instrucción.
        //
        // ⚠️ Aquí la fecha NO necesita la guarda de `Order::singleVisitDate()` y es por el sujeto:
        // esto es una RESERVA, no un pedido, así que tiene una franja y su día es exacto. La
        // redacción sin fecha existe igual, para la reserva que aún no tiene franja asignada.
        $dia = $this->reservation->slot?->date
            ? DisplayTime::dayLabel($this->reservation->slot->date)
            : null;

        $message = (new BrandedMailMessage)
            ->subject($dia !== null
                ? __('emails.guest_form.subject', ['day' => $dia, 'code' => $code])
                : __('emails.guest_form.subject_no_date', ['code' => $code]))
            ->line(__('emails.guest_form.intro', ['product' => $product, 'code' => $code]))
            ->line(__('emails.guest_form.body'));

        // LA CABECERA EN TINTA (`#503`). ⚠️ SIN «Dónde»: este correo no habla de llegar al parque,
        // habla de rellenar una ficha — la dirección aquí es ruido. Y sin tarjeta de producto: el
        // resguardo ya dice QUÉ y CUÁNDO, y repetirlo era lo que hacía el correo largo.
        $message->hero('emails.guest_form', 'warn', EmailSlip::forItem($this->reservation));

        // D15 · **el único correo que lleva a este formulario tiene que nombrar lo que ahí se puede
        // pedir**, o la feature se construye entera y no se vende un solo cubo de refrescos. No se
        // añade un envío nuevo: se añade una frase al que ya sale.
        //
        // ⚠️ **Condicionada a que ESTA reserva tenga extras abiertos**, nunca al catálogo: una
        // instalación sin enganches `postform` —el caso por defecto, y hoy los 29— prometería algo
        // que no existe, y el cliente buscaría en su formulario una sección que no está.
        // ▶ Y va en su propio AVISO (`#503`), no como una línea más del cuerpo: es DINERO, y dice
        // dónde se paga.
        if (app(PostFormAddons::class)->offerableFor($this->reservation)->isNotEmpty()) {
            $message->notice(__('emails.guest_form.notice_title'), __('emails.guest_form.extras'));
        }

        return $message
            ->action(
                __('emails.guest_form.action'),
                // Fuente ÚNICA del enlace firmado (compartida con el botón «Copiar enlace» del panel).
                $this->reservation->guestFormSignedUrl(),
            )
            ->line(__('emails.guest_form.outro'));
    }
}
