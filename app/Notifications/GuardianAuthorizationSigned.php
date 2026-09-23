<?php

namespace App\Notifications;

use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\WaiverProof;
use App\Notifications\Support\BrandedMailMessage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

/**
 * La COPIA de lo que acaba de firmar un adulto sin cuenta
 * (`docs/specs/waiver-por-reserva.md` §4.15, `[DECIDIDO owner]` §7·7).
 *
 * ❗ **No verifica nada y no gatea nada.** El justificante ya está registrado cuando esto sale: es su
 * copia, que es lo que un papel firmado da por descontado. Que el correo no llegue —o que no lo haya
 * dejado— **no invalida la firma**.
 *
 * ⚠️⚠️ **El PDF va ADJUNTO, no enlazado, y es deliberado.** Un enlace exigiría una ruta pública que
 * sirviera el registro probatorio de un tercero, o sea **una superficie más por la que se puede pedir
 * la prueba de otra persona**; adjuntarlo lo entrega exactamente a quien lo firmó y a nadie más. Es
 * también por lo que el RESPONSABLE de la reserva no puede descargarlo (§4.5): la prueba lleva el
 * nombre, el correo y el teléfono de un adulto que no es él.
 *
 * ⚠️ Se envía **al correo que la persona declaró**, que nadie ha verificado. Si se equivocó al
 * teclearlo, el mensaje va a un desconocido — y por eso el PDF adjunto es el documento probatorio y
 * el cuerpo del correo no repite ningún dato del menor.
 *
 * ⚠️ El idioma es el de la VERSIÓN FIRMADA, no el del servidor: lo que se envía es la copia de un
 * texto concreto, y ese texto tiene idioma.
 */
class GuardianAuthorizationSigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WaiverSignature $signature) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $proof = WaiverProof::make($this->signature);
        $previous = App::getLocale();
        App::setLocale($proof->locale());

        try {
            $minor = $proof->subjectName() ?? '—';
            $code = $proof->orderCode();

            $mail = (new BrandedMailMessage($this))
                // ⚠️⚠️ **El asunto NO nombra al menor** (`#406`): `guardian_email` lo teclea un adulto
                // sin cuenta y nadie comprueba que ese buzón sea suyo, así que una errata manda esto a
                // un desconocido. El asunto se replica donde el adjunto no llega —previsualización de
                // la bandeja, pantalla de bloqueo, logs del servidor de correo y los REBOTES, que citan
                // asunto y cabeceras—, así que ahí no va un nombre de menor. En el CUERPO sí, que es
                // donde el destinatario legítimo necesita saber por quién firmó.
                ->subject(__('emails.guardian_authorization.subject'))
                ->hero('emails.guardian_authorization', 'ok')
                ->line(__('emails.guardian_authorization.intro', ['name' => $minor]));

            if ($code !== null) {
                $mail->line(__('emails.guardian_authorization.booking', ['code' => $code]));
            }

            $mail
                ->line(__('emails.guardian_authorization.attached'))
                // ⚠️ El aviso de que NADA está verificado viaja también aquí, no solo dentro del PDF:
                // quien lee el correo puede no abrir el adjunto.
                ->line(__('emails.guardian_authorization.not_verified'))
                ->salutation(__('emails.guardian_authorization.salutation'))
                ->attachData(
                    Pdf::loadView('pdf.waiver-proof', ['proof' => $proof])->setPaper('a4')->output(),
                    'justificante-'.$this->signature->getKey().'.pdf',
                    ['mime' => 'application/pdf'],
                );

            return $mail;
        } finally {
            App::setLocale($previous);
        }
    }
}
