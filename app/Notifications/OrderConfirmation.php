<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Services\EmailBookBlock;
use App\Domain\Booking\Services\EmailSlip;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\QrCode;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email de confirmación de reserva PAGADA (capa 5.5c, #104).
 *
 * Originalmente (auditoría 2026-05-26 2ª ronda, P-01) se enviaba al crear la reserva firme
 * con texto "pendiente de pago". Con Redsys real, se envía SOLO tras autorización del banco
 * (`RedsysReturnHandler` → `Ds_Response` ∈ 0000–0099): cumpleel principio "no decir al
 * cliente que su reserva está confirmada hasta que el cobro está cerrado". Si el cliente
 * cierra la pestaña, este email es su único rastro fuera de "Mis pedidos".
 *
 * El email llega en el idioma del usuario (User implementa HasLocalePreference).
 */
class OrderConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // ❗❗ EL ASUNTO, CON EL DATO DELANTE (`#506`; artboard `Correos PJP`, y `[DECIDIDO owner]`).
        // Medido antes: «Tu reserva confirmada en SaltoPark (nº R-7MN4PK)» son 48 caracteres y en el
        // corte de ~35 de un móvil se leía «Tu reserva confirmada en SaltoPark» — o sea, el nombre
        // del parque **que el remitente ya dice justo al lado**, y ni el día ni el código.
        //
        // ⚠️⚠️ Y LA FECHA SÓLO SI EL PEDIDO TIENE UNA. `singleVisitDate()` devuelve `null` cuando
        // hay reservas en días distintos, y entonces se usa la redacción sin fecha: en la bandeja no
        // hay cuerpo debajo que matice un día que no es el único. Es la misma pareja de claves que
        // el titular ya tenía (`headline` / `headline_no_date`).
        // ⚠️ DOS FORMATOS DE LA MISMA FECHA, y es deliberado: `dayLabel()` («Sáb. 5 sep.») se lee
        // como un DATO y lleva el mes —en la bandeja, «lunes 17» no dice de qué mes—; `dayInSentence()`
        // («lunes 17») va DENTRO de la oración del titular. Lo dicen sus propios docblocks. La FECHA
        // sale de una sola derivación, así que asunto y titular no se pueden contradecir.
        $fecha = $this->order->singleVisitDate();
        $dia = $this->day();
        $message = (new BrandedMailMessage)
            ->subject($fecha !== null
                ? __('emails.order_confirmation.subject', [
                    'day' => DisplayTime::dayLabel($fecha),
                    'code' => $this->order->code,
                ])
                : __('emails.order_confirmation.subject_no_date', ['code' => $this->order->code]))
            ->line(__('emails.order_confirmation.intro', ['code' => $this->order->code]));

        // LA CABECERA EN TINTA (`#503`; artboard `Correos PJP` 1a): chapa de estado, titular y el
        // RESGUARDO con cuándo · qué · dónde · pedido. Sustituye al saludo —el artboard no tiene
        // «¡Hola!» en ninguno de los cuatro— y sube arriba los cuatro datos que se buscan al abrir,
        // que hasta hoy estaban repartidos entre la tarjeta de producto y una frase del cuerpo.
        // ⚠️ Éste es el ÚNICO de los cuatro dibujados que lleva DÓNDE: es el que habla de la visita.
        // ⚠️ El titular cambia de CLAVE, no de parámetro, cuando no hay día: «Nos vemos el » con el
        // hueco vacío es peor que una frase neutra.
        $message->hero(
            'emails.order_confirmation', 'ok',
            EmailSlip::forOrder($this->order, conLugar: true),
            ['day' => $dia],
        );
        if ($dia === '') {
            $message->viewData['hero']['titulo'] = (string) __('emails.order_confirmation.headline_no_date');
        }

        // ⚠️ SIN TARJETA DE PRODUCTO, y no es un olvido (`#503`): el RESGUARDO de la cabecera ya dice
        // QUÉ y CUÁNDO, así que la tarjeta repetía los dos datos treinta líneas más abajo. El artboard
        // no la dibuja en este correo por eso mismo. Sigue viva en los correos que hablan de UNA
        // reserva concreta sin resguardo (modificada, cancelada, reembolsada).

        // EL LIBRO del pedido (T3·3 de `specs/desglose-libro.md`, D-T3·5): lo que vale, lo cobrado y el
        // saldo con su clase —con señal, «a pagar en el parque» el resto; sin ella, «nada pendiente»—
        // compuesto AL ENVIAR: este correo se REENVÍA desde el panel, y para entonces el pedido puede
        // haber cambiado. Sustituye a las tres líneas de canal («Total pagado» / «Señal pagada online» /
        // «Pendiente de pago en el parque»), que eran una segunda composición del mismo dinero.
        $message->line(EmailBookBlock::forOrder($this->order));

        // Audit #114 G8 — Fecha y hora del cobro (en la TZ de presentación, #111). Solo
        // cuando `paid_at` existe (rama Authorized del handler; en `IdempotentPaid` también
        // se preserva el `paid_at` original).
        if ($this->order->paid_at) {
            $message->line(__('emails.order_confirmation.paid_at', [
                'when' => DisplayTime::format($this->order->paid_at, 'd/m/Y H:i'),
            ]));
        }

        // La promesa de «te pediremos los datos de los invitados» SOLO aplica si el pedido tiene
        // post-form (pack con `guest_fields`); para entradas no existe ese paso (#217).
        $paidConfirmation = $this->order->needsGuestForm()
            ? 'emails.order_confirmation.paid_confirmation_guest_form'
            : 'emails.order_confirmation.paid_confirmation';

        // Fase 6 · subsistema A (`specs/identidad-qr-puerta.md` §4.10, §9.2 A·8): el CARNÉ QR viaja en
        // este correo como PNG ADJUNTO —no como imagen remota (los clientes las bloquean) ni como SVG
        // inline (no lo renderizan)—. El carné nace aquí si el titular aún no tiene. Si la clave de
        // cifrado rotó y no se puede repintar (§8.1), el correo sale sin adjunto en vez de fallar.
        $token = $this->order->user !== null
            ? app(CustomerCards::class)->ensureFor($this->order->user)->plainToken()
            : null;
        if ($token !== null) {
            $message
                ->line(__('emails.order_confirmation.card_attached'))
                ->attachData(QrCode::png($token), 'carne-qr.png', ['mime' => 'image/png']);
        }

        // EL AVISO «ANTES DE VENIR» (`#503`): calcetines, descargo y venir diez minutos antes. Es
        // contenido que la portada ya tiene cerrado en su sección 05, y este correo es el ÚNICO
        // momento en que hace falta — cuando ya has comprado y aún no has venido. No se escribe nada
        // nuevo: se reutiliza lo aprobado.
        $message->notice(__('emails.order_confirmation.notice_title'), __('emails.order_confirmation.notice_body'));

        return $message
            ->action(__('emails.order_confirmation.action'), route('account.orders'))
            ->line(__($paidConfirmation))
            ->line(__('emails.order_confirmation.outro'));
    }

    /**
     * El día de la visita para el titular: «Nos vemos el **sábado 5**».
     *
     * ⚠️ Devuelve cadena vacía cuando la reserva no tiene franja —un pedido de solo complementos, o
     * una línea sin día—, y entonces el titular se queda en «Nos vemos el », que es peor que una
     * frase neutra. Por eso el diccionario tiene `headline_no_date` y quien llama elige.
     */
    private function day(): string
    {
        // ⚠️ Sale de `Order::singleVisitDate()`, que devuelve `null` si el pedido tiene reservas en
        // DÍAS DISTINTOS (`#506`). Antes se cogía la primera y el titular afirmaba «Nos vemos el
        // sábado 4» en un pedido de dos días; hoy cae a `headline_no_date`, que ya existía.
        // ▶ Y es la MISMA derivación que usa el asunto, para que no puedan contradecirse.
        $fecha = $this->order->singleVisitDate();

        return $fecha !== null ? DisplayTime::dayInSentence($fecha) : '';
    }
}
