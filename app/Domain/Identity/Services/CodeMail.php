<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * **Cómo sale un correo con un código** (`docs/specs/acceso-con-codigo.md` §4.3): el de entrar (`EmailCodeLogin`) y el de
 * confirmar (`AccountCredentials`, A2a). Un solo sitio para las dos, extraído en la segunda copia (`#120(r)`).
 *
 * ⚠️⚠️ **TRAS la respuesta y NO por la cola**: la cola la vacía el cron cada minuto en producción, y un código que tarda
 * 60 s en la puerta del parque no llega. `defer()` lo manda en la misma petición cuando el cliente ya tiene su respuesta
 * —el SMTP no le hace esperar, que es lo que pide `PAY-14`—, y `notifyNow()` salta la cola aunque la notificación sea
 * `ShouldQueue` (lo es: quien la notificara por el camino normal la encolaría, que es lo seguro).
 */
final class CodeMail
{
    public static function sendAfterResponse(User $user, Notification $mail): void
    {
        defer(static function () use ($user, $mail): void {
            // Un fallo del transporte no puede subir a ninguna parte (la respuesta ya salió): se registra sin la dirección
            // ni el código, y el cliente puede pedir otro.
            try {
                $user->notifyNow($mail);
            } catch (Throwable $e) {
                Log::warning('auth.code_mail_failed', ['user_id' => $user->id, 'mail' => class_basename($mail), 'error' => $e::class]);
            }
        });
    }
}
