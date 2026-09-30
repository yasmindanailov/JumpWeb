<?php

namespace App\Notifications\Support;

/**
 * **Un correo de la cuenta que NO va al correo de la cuenta** (A2b de `docs/specs/acceso-con-codigo.md`, `DECISIONES #856`):
 * el código del correo NUEVO va al buzón nuevo (`pending_email`), y el aviso de que el cambio se hizo va al VIEJO.
 *
 * ⚠️⚠️ **Medido el 30-09: estos dos correos salían al buzón equivocado desde siempre.** Declaraban su destinatario con un
 * `routeNotificationForMail()` en la NOTIFICACIÓN, y Laravel solo lee el del MODELO (`RoutesNotifications::
 * routeNotificationFor()`): el enlace «confirma tu nuevo email» llegaba al buzón VIEJO —el cambio se confirmaba sin probar
 * el nuevo— y el aviso anti-robo al NUEVO. Las pruebas no lo veían: con `Notification::fake()` no hay dirección, y la que
 * lo miraba llamaba a ese método a mano. `User::routeNotificationForMail()` pregunta ahora por esta interfaz.
 */
interface ChoosesRecipient
{
    /** La dirección a la que va ESTE correo, en vez de la de la cuenta. */
    public function recipientFor(object $notifiable): string;
}
