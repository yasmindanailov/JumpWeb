<?php

namespace App\Notifications;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailSlip;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **El enlace del JUSTIFICANTE de un menor invitado, al que RESERVÓ**
 * (`docs/specs/waiver-por-reserva.md` §12.3, T7).
 *
 * Va al responsable —el que compra— para que lo reparta a los padres de los menores que no son suyos.
 * Es «el papelito de la excursión»: **uno por RESERVA marcada**, que él pasa por WhatsApp, correo o
 * impreso.
 *
 * ⚠️⚠️ **Era uno por PEDIDO hasta `#401` y lo cazó el owner**: compró una excursión y una entrada, las
 * dos con menores invitados, y le llegó **un** correo con **un** enlace que decía dos fechas. Ahora es
 * uno por visita, exactamente como su hermano `GuestFormRequest`.
 *
 * ❗ **Esta clase es la PUERTA que faltaba.** Hasta la T7 el subsistema entero existía —dominio,
 * pantalla pública, seis superficies— y el enlace tenía **tres consumidores en todo el repo**, ninguno
 * de los cuales lo OFRECÍA: había que abrir «Mis pedidos», desplegar el pedido y encontrarlo. El
 * owner lo dijo con sus palabras probándolo: *«no me sale nada del enlace»* (§12.1).
 *
 * ⚠️ **Se manda al quedar PAGADO**, en los dos despachadores que ya reparten el post-form
 * (`RedsysReturnHandler` para la compra online y `ManualOrderFulfiller` para el mostrador), y solo si
 * el pedido nació con la marca — o sea, si el cliente marcó la casilla o el producto la exige. Un
 * pedido normal no recibe nada.
 *
 * ⚠️⚠️ **El enlace es una credencial PORTADORA y va en un correo a propósito**, que es exactamente lo
 * que hace su gemelo `GuestFormRequest`: quien lo abre no tiene cuenta, así que la firma HMAC de la
 * URL es lo único que puede autorizarle. Lo que NO puede es viajar en el contexto de cuenta, que se
 * siembra en el HTML de cada página con sesión (la prohibición de `AccountContextResource`).
 *
 * ⚠️ Caduca con `Order::guestFormLinkExpiresAt()` —la última franja + 14 días, la misma fuente que
 * `RGPD-03` fija para el post-form—: no se inventa un plazo nuevo.
 */
class GuardianAuthorizationRequest extends Notification implements ShouldQueue
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
        $code = (string) ($this->reservation->order?->code ?? '');
        $product = $this->reservation->displayProductName();

        $message = (new BrandedMailMessage($this))
            ->subject(__('emails.guardian_request.subject', ['product' => $product, 'code' => $code]))
            ->line(__('emails.guardian_request.intro', ['product' => $product, 'code' => $code]))
            ->line(__('emails.guardian_request.body'));

        // LA CABECERA EN TINTA (`#503`). Su RESGUARDO hace el trabajo que hacía la tarjeta de
        // producto —decir a qué visita es el enlace—: en un pedido con dos reservas marcadas llegan
        // DOS correos y sin eso el cliente no podría distinguirlos. Ahora lo dice arriba del todo.
        $message->hero('emails.guardian_request', 'warn', EmailSlip::forItem($this->reservation));

        // ❗❗ LA FRASE DE QUE SE PUEDE REPARTIR SUBE DEL FINAL DEL CUERPO A UN AVISO PROPIO
        // (`#503`), y no es un cambio de forma: éste es **el único correo del producto escrito para
        // ser REENVIADO**. Sin esa frase donde se lee, un responsable prudente no lo manda —parece
        // su enlace privado— y la feature se queda parada en su bandeja.
        $message->notice(__('emails.guardian_request.notice_title'), __('emails.guardian_request.share'));

        return $message
            ->action(
                __('emails.guardian_request.action'),
                // Fuente ÚNICA del enlace, compartida con el modal del panel y con la cuenta del
                // cliente: `OrderItem::guardianAuthorizationSignedUrl()`. Dos sitios que acuñaran la
                // URL por su cuenta acabarían con dos caducidades distintas de la misma reserva.
                $this->reservation->guardianAuthorizationSignedUrl(),
            )
            ->line(__('emails.guardian_request.outro'));
    }
}
