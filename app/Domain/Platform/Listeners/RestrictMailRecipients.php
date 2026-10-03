<?php

namespace App\Domain\Platform\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * **El BUZÓN TRAMPA** (`DECISIONES #883`): con `mail.only_to` lleno, el correo solo sale hacia esas direcciones.
 *
 * Es lo que deja a una instalación de PRUEBAS enviar correo de verdad —staging, por el mismo `sendmail` que producción,
 * para medir cuánto tarda en llegar un código— sin escribir a nadie más: sus cuentas de prueba, la dirección del parque
 * que vino con los ajustes de producción o cualquiera que pida un código en la web pública. La guarda 3 de
 * `scripts/deploy.sh` solo deja desplegar staging con un correo real si la lista está puesta, y la de producción exige
 * lo contrario.
 *
 * Va en `MessageSending` porque por ahí pasa TODO correo, notificación o no, también desde la cola: los destinatarios
 * de fuera se QUITAN (`To`, `Cc` y `Bcc`) y, si no queda ninguno, el envío se CANCELA (`false`: el framework escucha con
 * `until()`). Un envío cancelado no queda como enviado en el registro de correos (`RecordEmailSend` solo apunta lo que
 * devolvió el transporte).
 *
 * ⚠️ Sin direcciones en claro en el registro (`RGPD-02`): solo cuántas se quitaron y cuántas quedaron.
 */
class RestrictMailRecipients
{
    public function handle(MessageSending $event): ?bool
    {
        $permitidas = array_map('mb_strtolower', (array) config('mail.only_to', []));

        if ($permitidas === []) {
            return null;
        }

        $mensaje = $event->message;
        $antes = [$mensaje->getTo(), $mensaje->getCc(), $mensaje->getBcc()];
        [$to, $cc, $bcc] = array_map(
            static fn (array $lista): array => array_values(array_filter(
                $lista,
                static fn (Address $a): bool => in_array(mb_strtolower($a->getAddress()), $permitidas, true),
            )),
            $antes,
        );
        $quedan = count($to) + count($cc) + count($bcc);
        $fuera = count($antes[0]) + count($antes[1]) + count($antes[2]) - $quedan;

        if ($fuera === 0) {
            return null;
        }

        Log::info('mail.recipients_outside_only_to', ['removed' => $fuera, 'kept' => $quedan]);

        if ($quedan === 0) {
            return false;
        }

        // Cada lista, con lo que queda; la que se queda vacía, fuera (un `To:` vacío no es una cabecera válida).
        foreach (['To' => $to, 'Cc' => $cc, 'Bcc' => $bcc] as $cabecera => $lista) {
            $mensaje->getHeaders()->remove($cabecera);
            if ($lista !== []) {
                $mensaje->getHeaders()->addMailboxListHeader($cabecera, $lista);
            }
        }

        return null;
    }
}
