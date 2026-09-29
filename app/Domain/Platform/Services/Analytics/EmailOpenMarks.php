<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Contracts\ConsentLedger;
use App\Domain\Platform\Models\Setting;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;
use WeakMap;

/**
 * **QUIÉN LLEVA EL PÍXEL DE APERTURA** (`specs/correos-salientes.md` §4.12, `#797`, la C3): una imagen de 1×1 al final del
 * correo, `GET /e/{send}.gif`, que dice cuándo se abrió. Una sola regla ({@see allows()}), la misma al enviar y al abrir:
 *
 * - su interruptor {@see self::SETTING} encendido: APAGADO de fábrica y SEPARADO del de los clics, porque su base legal es
 *   otra —el píxel pide CONSENTIMIENTO— y `/cookies` tiene que nombrarlo antes (`[PENDIENTE: asesoría]`);
 * - la regla de persona de los clics (`EmailClickMarks::personAllows()`: una cuenta sin oposición, nunca la encuesta);
 * - y la ÚLTIMA decisión de cookies de esa cuenta con «análisis» aceptado (`ConsentLedger::accountConsentedNow()`): sin un
 *   «sí» vivo —nunca decidió, o dijo que no—, el correo sale sin píxel.
 *
 * Cómo sabe el molde a quién va: como `EmailClickMarks`, un oyente de `NotificationSending` lo anota en un `WeakMap` por la
 * notificación y el molde lo lee con {@see for()}; sin el evento (un `toMail()` a mano) no hay píxel.
 */
final class EmailOpenMarks
{
    /** El interruptor de Ajustes → Avanzado → «Correos a los clientes». */
    public const SETTING = 'emails.track_opens';

    /** @var WeakMap<Notification, string>|null */
    private static ?WeakMap $marks = null;

    public static function enabled(): bool
    {
        return (string) Setting::value(self::SETTING, '0') === '1';
    }

    /** ¿Lleva píxel este correo a este destinatario? La misma regla al enviar y al abrir. */
    public static function allows(mixed $recipient, string $key): bool
    {
        return self::enabled()
            && EmailClickMarks::personAllows($recipient, $key)
            && $recipient instanceof User
            && app(ConsentLedger::class)->accountConsentedNow((int) $recipient->getKey(), 'analytics') === true;
    }

    /**
     * Oyente de `NotificationSending`. ⚠️ No devuelve nada: el framework lo escucha con `until()` y un `false` cancelaría el
     * correo. Y nada de aquí puede impedir que salga: sin píxel, el correo sale igual.
     */
    public function sending(NotificationSending $event): void
    {
        try {
            $notification = $event->notification;

            if ($event->channel !== 'mail') {
                return;
            }

            // El framework declara el id como texto, pero es NULO hasta que lo fija el que envía.
            $send = (string) $notification->id;

            if ($send !== '' && self::allows($event->notifiable, EmailUtm::keyOf($notification))) {
                self::map()[$notification] = $send;
            }
        } catch (Throwable $e) {
            Log::warning('email_opens.mark_failed', ['error' => $e::class]);
        }
    }

    /** La clave del envío si lleva píxel, o `null`. La pregunta el molde al componerse y `RecordEmailSend` al apuntarlo. */
    public static function for(Notification $notification): ?string
    {
        return self::map()[$notification] ?? null;
    }

    /** @return WeakMap<Notification, string> */
    private static function map(): WeakMap
    {
        return self::$marks ??= new WeakMap;
    }
}
