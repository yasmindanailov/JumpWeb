<?php

namespace App\Notifications;

use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

/**
 * EL ENLACE PARA RESTABLECER LA CONTRASEÑA, con el molde del producto (`DECISIONES #508`).
 *
 * ❗❗❗ **Este correo NO ESTABA EN EL INVENTARIO de 23, y lo lee un cliente.** El artboard
 * `Correos PJP` contó `app/Notifications/` + `app/Mail/` —que es donde miró también la T1— y **los
 * del framework no viven en ninguna carpeta**: salían de `Illuminate\Auth\Notifications`. Medido
 * antes de esta tanda: vestido sí (pasa por el mismo layout, con su modo oscuro), pero **sin
 * cabecera y sin línea de adelanto**, así que en la bandeja se anunciaba con «SaltoPark ¡Hola!».
 * Y es de los más frecuentes que manda el producto: lo dispara la recuperación del cliente **y** el
 * botón «Enviar enlace de contraseña» del panel.
 *
 * ⚠️⚠️ **SE EXTIENDE, NO SE REEMPLAZA, Y ESA ES LA DECISIÓN.** Lo único que cambia es
 * `buildMailMessage($url)` — que recibe **la URL ya construida por el framework**—, así que el
 * TOKEN, la FIRMA, la CADUCIDAD y la ruta siguen siendo suyos. La alternativa que se descartó era
 * `ResetPassword::toMailUsing()`, y tiene dos defectos: su callback recibe **el token, no la URL**
 * —obligaría a copiar aquí la línea `url(route('password.reset', …))` del framework, que es una
 * regla duplicada que envejece sin fallar— y una notificación registrada en un provider **no vive
 * en `app/Notifications/`**, así que las guardas del molde, que censan esa carpeta, no la verían.
 *
 * ⚠️ **El TEXTO del cuerpo no cambia ni una palabra**: se reutilizan las mismas cadenas que ya
 * estaban traducidas (`lang/es.json`, `lang/fr.json`). Lo que entra es el molde.
 */
class PasswordReset extends ResetPassword implements ShouldQueue
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
        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new BrandedMailMessage)
            ->subject(__('emails.password_reset.subject'))
            // Sin RESGUARDO: aquí no hay reserva de la que hablar, como en el resto de los de cuenta.
            ->hero('emails.password_reset', 'warn')
            ->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))
            ->action(Lang::get('Reset Password'), $url)
            // ⚠️ Después del botón, así que caen en el CIERRE — que es donde el framework ya las
            // ponía. La caducidad la sigue diciendo `config`, no un número escrito aquí.
            ->line(Lang::get('This password reset link will expire in :count minutes.', ['count' => $minutos]))
            ->line(Lang::get('If you did not request a password reset, no further action is required.'));
    }
}
