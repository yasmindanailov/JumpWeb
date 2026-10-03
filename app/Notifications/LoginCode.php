<?php

namespace App\Notifications;

use App\Domain\Identity\Services\LoginCodes;
use App\Domain\Platform\Contracts\HidesSecretsInCopy;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * **EL CÓDIGO PARA ENTRAR** (A1 de `docs/specs/acceso-con-codigo.md` §4.3, `DECISIONES #848`): seis cifras, grandes, para
 * cuánto valen y «si no lo has pedido tú, ignóralo». **Sin enlace** (`#848`·4): un enlace abriría la sesión en el
 * navegador del correo y no en el de la compra, y los filtros que visitan enlaces lo gastarían.
 *
 * ⚠️⚠️ **Sale TRAS LA RESPUESTA y no por la cola** (§4.3): la cola la vacía el cron cada minuto en producción, y quien
 * espera un código en la puerta del parque no puede esperar 60 s. `EmailCodeLogin` lo manda con `sendNow()` dentro de
 * `defer()`. Es `ShouldQueue` igualmente (`PAY-14`): quien lo notificara por el camino normal lo encolaría, que es lo
 * seguro; el envío inmediato es una decisión del flujo que lo pide, escrita allí.
 *
 * ⚠️ El código viaja en el asunto (se lee en el aviso del móvil sin abrir el correo: la cola de la puerta) y en su BLOQUE
 * (la R1c, el 8 del zip (6): la cabecera dice «Tu código para entrar» y el código va grande y en mono, debajo), PARTIDO en
 * dos grupos de tres con guion, «482-913», para dictarlo y copiarlo (`LoginCodes::shown()`, la forma de los tres correos
 * con código); el servidor acepta las dos (`LoginCodes::consume()`). En la copia del registro de correos salientes va
 * tapado ({@see HidesSecretsInCopy}).
 */
class LoginCode extends Notification implements HidesSecretsInCopy, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = LoginCodes::shown($this->code);

        return (new BrandedMailMessage($this))
            ->subject(__('emails.login_code.subject', ['code' => $code]))
            // Sin RESGUARDO, como el resto de los de cuenta: el hecho es el código, en su bloque.
            ->hero('emails.login_code', 'info')
            ->code(
                __('emails.login_code.code_label', ['digits' => LoginCodes::LENGTH]),
                $code,
                __('emails.login_code.validity', ['minutes' => LoginCodes::TTL_MINUTES]),
            )
            ->outro(__('emails.login_code.ignore'));
    }

    /**
     * Solo la forma que ENSEÑA el correo: las seis cifras seguidas no aparecen en él, y taparlas por si acaso podría
     * tocar otra cifra de la copia (la versión del logotipo lleva diez).
     *
     * @return list<string>
     */
    public function secretsInCopy(): array
    {
        return [LoginCodes::shown($this->code)];
    }
}
