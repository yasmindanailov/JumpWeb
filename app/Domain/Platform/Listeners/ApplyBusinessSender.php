<?php

namespace App\Domain\Platform\Listeners;

use App\Domain\Platform\Models\Setting;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * El REMITENTE de todo correo saliente sale del PANEL, no del `.env`.
 *
 * `[DECIDIDO owner, 2026-09-10]` (`DECISIONES #500`, T2 de `specs/correos-desde-canvas.md`).
 * Medido antes de construirlo: `config/mail.php` resuelve `mail.from` con `env(...)` y **nadie lo
 * sobreescribe** en todo el repo, así que en local los 23 correos salían de
 * `JumpWeb <hello@example.com>` — el nombre del PRODUCTO y el placeholder de Laravel. En la bandeja
 * el remitente pesa más que el asunto, y no estaba escrito en ninguna decisión.
 *
 * ## Por qué un LISTENER y no `Mail::alwaysFrom()` en un provider
 *
 * `alwaysFrom()` en `boot()` obliga a consultar `settings` **en cada petición**, incluidas las que
 * no envían ningún correo. `MessageSending` se dispara solo cuando hay un correo de verdad, así que
 * el coste se paga donde se usa. Y funciona igual desde el worker de la cola, que es por donde salen
 * casi todos (`ShouldQueue`, `PAY-14`).
 *
 * ## ❗❗ La regla que lo hace seguro: solo se sustituye el remitente POR DEFECTO
 *
 * Cuando este evento se dispara, Laravel **ya ha puesto** el `From` de `config('mail.from')`. Para
 * no pisar a quien lo haya elegido a propósito, se sustituye únicamente si el que hay es
 * exactamente ése. Hoy **ningún correo define `from` propio** (medido en `app/Mail/` y
 * `app/Notifications/`), así que la comprobación no cambia nada — existe para el día que alguien lo
 * ponga, que es justo cuando dejaría de ser evidente.
 *
 * ⚠️ **`ContactMessageMail` sí define `replyTo`** (el correo de quien escribe). Este listener no
 * toca el `Reply-To` de nadie: cambiar el remitente y quedarse con su respuesta son dos cosas
 * distintas.
 *
 * ## ⚠️ Degradaciones, todas hacia «no tocar»
 *
 * Un ajuste mal puesto **no puede impedir que un correo salga**. Si no hay exactamente un `From`,
 * si el que hay no es el de config, o si no hay ninguna dirección válida con la que sustituirlo,
 * el mensaje se va tal cual. `Setting::mailFromAddress()` ya cae a la de config antes de rendirse.
 *
 * ⚠️ **Lo que este mecanismo NO arregla: la entregabilidad.** Una dirección fuera del dominio que
 * firma con SPF/DKIM acaba en spam, y eso no lo puede saber el código. El aviso vive en el campo
 * del panel.
 *
 * ⚠️ **Y una nota de operación**: `Setting::value()` memoiza la tabla entera por proceso y solo la
 * invalida con los eventos del modelo. Un worker de cola de larga vida **no se entera** de un
 * cambio hecho en el panel hasta que se recicla. No es nuevo —le pasa a todo ajuste— pero aquí se
 * nota más, porque el síntoma es «he cambiado el remitente y sigue saliendo el viejo».
 */
class ApplyBusinessSender
{
    public function handle(MessageSending $event): void
    {
        $from = $event->message->getFrom();

        // Ni cero (no hay qué sustituir) ni varios (alguien está haciendo algo deliberado).
        if (count($from) !== 1) {
            return;
        }

        $porDefecto = trim((string) config('mail.from.address'));
        if ($porDefecto === '' || strcasecmp($from[0]->getAddress(), $porDefecto) !== 0) {
            return;
        }

        $direccion = Setting::mailFromAddress();
        if ($direccion === null) {
            return;
        }

        $event->message->from(new Address($direccion, Setting::businessName()));
    }
}
