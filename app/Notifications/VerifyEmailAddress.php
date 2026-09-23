<?php

namespace App\Notifications;

use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

/**
 * EL ENLACE PARA VERIFICAR EL CORREO DE UNA CUENTA NUEVA, con el molde del producto (`#508`).
 *
 * ❗❗❗ **Tampoco estaba en el inventario de 23**, por el mismo motivo que su hermano
 * (`PasswordReset`): el artboard contó carpetas y éste salía de `Illuminate\Auth\Notifications`.
 * Lo recibe **toda cuenta nueva** (`Identity\Services\SelfSignup`) y cada reenvío desde
 * `/email/verificar`, así que es de los que más salen del producto.
 *
 * ⚠️ **Se extiende para cambiar SOLO la composición**: `buildMailMessage($url)` recibe la URL
 * **temporal y firmada** que el framework ya calculó con el hash del correo — replicarla aquí sería
 * copiar una regla de seguridad, que es justo lo que no se toca.
 *
 * ⚠️ Y el TEXTO del cuerpo se queda igual: las mismas cadenas ya traducidas.
 *
 * ❗ **No confundirlo con sus dos vecinos**, que son otra cosa y por eso tienen grupo propio:
 * `verify_purchase` es el correo de quien se da de alta **durante una compra** —lleva el código del
 * pedido y habla de la plaza apartada— y `verify_pending_email` es el de **cambiar** el correo de
 * una cuenta que ya existe. Éste es el del alta suelta.
 */
class VerifyEmailAddress extends VerifyEmail implements ShouldQueue
{
    /**
     * ⚠️⚠️ `ShouldQueue` POR `PAY-14` — «ningún email se manda síncrono», y **esto destapó que los
     * dos del framework llevaban violándolo desde siempre**: `Illuminate\Auth\Notifications` no
     * implementa la interfaz, y la guarda que lo vigila (`QueuedEmailsTest`) **escanea
     * `app/Notifications/`**, donde estos dos no vivían. Al traerlos aquí, la guarda los alcanzó y
     * los puso en rojo el mismo día. *Una guarda que censa una carpeta no vigila lo que está fuera
     * de ella.*
     */
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        return (new BrandedMailMessage($this))
            ->subject(__('emails.verify_email.subject'))
            ->hero('emails.verify_email', 'warn')
            ->line(Lang::get('Please click the button below to verify your email address.'))
            ->action(Lang::get('Verify Email Address'), $url)
            ->line(Lang::get('If you did not create an account, no further action is required.'));
    }
}
