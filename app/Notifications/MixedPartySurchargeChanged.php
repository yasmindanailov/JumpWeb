<?php

namespace App\Notifications;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailBookBlock;
use App\Domain\Booking\Services\EmailSlip;
use App\Notifications\Support\BrandedMailMessage;
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

        // ❗❗ EL TONO LO PONE EL SIGNO DEL NETO, y sale de UNA derivación para la chapa y para el
        // aviso: con dos, un descuento podía llegar con la chapa en ámbar y su importe en cian.
        // ▶ `warn` cuando hay que pagar algo («falta algo», que es lo que ese tono significa aquí);
        // `info` cuando el neto es un descuento o desaparece — nadie ha roto nada y no es un éxito.
        // ⚠️ Es la misma regla dura que creó el quinto tono en `#503`: *una devolución no es un
        // color, es un signo y una fecha*. Teñir de verde una rebaja la vendería como una
        // celebración, y esto es un dato del dinero.
        $tono = $this->newCents > 0 ? 'warn' : 'info';

        $message = (new BrandedMailMessage($this))
            ->subject(__('emails.mixed_party_surcharge.subject', ['code' => $code]))
            ->hero('emails.mixed_party_surcharge', $tono, EmailSlip::forItem($this->item))
            ->line(__($this->byCustomer
                ? 'emails.mixed_party_surcharge.intro'
                : 'emails.mixed_party_surcharge.intro_by_park'));

        // ⚠️ SIN TARJETA DE PRODUCTO, y no es un olvido (`#507`): el RESGUARDO de la cabecera ya
        // dice QUÉ y CUÁNDO, así que la tarjeta repetía el producto, el día y la hora treinta
        // líneas más abajo. Es el mismo recorte que `#503` hizo en la confirmación, por el mismo
        // motivo — y aquí pesaba más, porque este correo tenía los TRES datos del resguardo
        // repetidos: el código y el producto en la entradilla, y producto, día y hora en la tarjeta.

        // ❗❗❗ LA CIFRA VA EN EL AVISO, NO EN TEXTO CORRIDO (`#507`). Éste es el único correo del
        // producto cuyo trabajo entero es decir UN NÚMERO, y hasta hoy ese número salía como un
        // párrafo más entre otros trece —mismo cuerpo, mismo color, misma sangría—. El molde tiene
        // la caja de tinte justo para esto, y ya la usaban cuatro correos para cosas menos
        // importantes que un cambio de dinero.
        //
        // ⚠️⚠️ LAS SIETE FRASES DEL DESENLACE NO SE TOCAN, y eso es deliberado: nace, cambia o
        // desaparece —en la voz del suplemento o en la del descuento— más el cruce de signo.
        // Fundirlas en «tu importe es ahora X» dejaría la retirada diciendo «ahora es 0,00 €», que
        // es exactamente el tipo de línea que un cliente no sabe leer; y `cumple-mixto.md` §24.5
        // las cita como el patrón de referencia para separar la línea del IMPORTE de la del CANAL.
        // ▶ Lo que cambia es DÓNDE se pintan, no lo que dicen.
        //
        // El cruce de signo (un cargo que pasa a descuento en una familia de tres tramos) lleva
        // cada importe rotulado: un «pasa de 5,00 € a 7,00 €» sin rótulos mentiría sobre la dirección.
        $signed = fn (int $cents): string => $cents < 0
            ? __('emails.mixed_party_surcharge.amount_discount', ['amount' => $amount(-$cents)])
            : __('emails.mixed_party_surcharge.amount_surcharge', ['amount' => $amount($cents)]);

        if ($this->newCents === 0) {
            $cifra = __($this->oldCents > 0
                ? 'emails.mixed_party_surcharge.removed'
                : 'emails.mixed_party_surcharge.credit_removed');
        } elseif ($this->newCents > 0 && $this->oldCents === 0) {
            $cifra = __('emails.mixed_party_surcharge.added', ['amount' => $amount($this->newCents)]);
        } elseif ($this->newCents < 0 && $this->oldCents === 0) {
            $cifra = __('emails.mixed_party_surcharge.credit_added', ['amount' => $amount(-$this->newCents)]);
        } elseif ($this->newCents > 0 && $this->oldCents > 0) {
            $cifra = __('emails.mixed_party_surcharge.updated', [
                'old' => $amount($this->oldCents),
                'new' => $amount($this->newCents),
            ]);
        } elseif ($this->newCents < 0 && $this->oldCents < 0) {
            $cifra = __('emails.mixed_party_surcharge.credit_updated', [
                'old' => $amount(-$this->oldCents),
                'new' => $amount(-$this->newCents),
            ]);
        } else {
            $cifra = __('emails.mixed_party_surcharge.changed_direction', [
                'old' => $signed($this->oldCents),
                'new' => $signed($this->newCents),
            ]);
        }

        // ⚠️ El CANAL viaja DENTRO del aviso, pegado a su importe. Iban como dos párrafos seguidos
        // —«se abona 4,00 €» y, aparte, «se abona en el parque»—, y en un correo que ya tenía trece
        // frases eso obligaba a leer dos para saber una cosa. Sigue siendo la separación que
        // `cumple-mixto.md` §24.5 defiende: son dos oraciones, no una.
        $canal = match (true) {
            $this->newCents > 0 => (string) __('emails.mixed_party_surcharge.where_to_pay'),
            $this->newCents < 0 => (string) __('emails.mixed_party_surcharge.where_discounted'),
            default => '',
        };

        $message->notice(
            (string) __('emails.mixed_party_surcharge.notice_title'),
            trim($cifra.' '.$canal),
            $tono,
        );

        // EL LIBRO del pedido a día de hoy (T3·3 del libro, D-T3·22): el neto del suplemento es una
        // línea más, y el saldo dice lo que se paga o se devuelve en el parque. ⚠️ Se queda ENTERO
        // (`[DECIDIDO owner]`, `#503`): resumirlo perdería el historial, que es lo que hace que el
        // cliente pueda comprobar la cifra en vez de creérsela.
        $message->outro(EmailBookBlock::forOrder($this->item->order));

        return $message->outro(__('emails.mixed_party_surcharge.editable'));
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
