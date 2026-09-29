<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;
use WeakMap;

/**
 * **QUIÉN LLEVA LA MARCA DEL ENVÍO en sus enlaces** (`specs/correos-salientes.md` §4.8, la C2): `jw_e=<send_key>`, junto a la
 * UTM, para contar los clics de CADA correo. Una sola regla ({@see allows()}), la misma al enviar y al pulsar:
 *
 * - el interruptor {@see self::SETTING} encendido: APAGADO por defecto, porque el producto es de marca blanca y cada instalación lo
 *   enciende cuando su `/privacidad` lo nombra (`[PENDIENTE: asesoría]`, spec §7);
 * - a una CUENTA que no se ha opuesto (`analytics_opt_out`): quien no tiene cuenta no tiene dónde oponerse;
 * - un correo al cliente y nunca uno de {@see NEVER}: ⚠️⚠️ la encuesta es ANÓNIMA (`#754`) y su participación no guarda si
 *   contestó; «abrió la encuesta a las 10:03» junto al día y la franja de la respuesta la destaparía.
 *
 * ⚠️ **Cómo sabe el molde a quién va**: `toMail()` no se lo dice a `BrandedMailMessage` y tocar los 25 `toMail()` es lo que la
 * spec prohíbe (trampa (1) del §0). El framework dispara `NotificationSending` con el destinatario JUSTO ANTES de componer, con
 * el MISMO objeto de notificación que luego recibe `toMail()`: {@see sending()} anota la marca en un `WeakMap` por ese objeto
 * y el molde la lee con {@see for()}. Sin el evento —un `toMail()` a mano, una vista previa— no hay marca. El `WeakMap` suelta
 * la entrada con la notificación: nada crece en un worker que vive horas.
 */
final class EmailClickMarks
{
    /** El interruptor de Ajustes → Analítica. */
    public const SETTING = 'emails.track_clicks';

    /**
     * Correos que NUNCA llevan marca, aunque vayan a una cuenta que no se opuso.
     *
     * @var list<string>
     */
    public const NEVER = ['survey_invitation'];

    /** @var WeakMap<Notification, string>|null */
    private static ?WeakMap $marks = null;

    public static function enabled(): bool
    {
        return (string) Setting::value(self::SETTING, '0') === '1';
    }

    /** ¿Se cuentan los clics de este correo a este destinatario? La regla de arriba; la misma al enviar y al pulsar. */
    public static function allows(mixed $recipient, string $key): bool
    {
        return self::enabled() && self::personAllows($recipient, $key);
    }

    /**
     * La regla de PERSONA, sin el interruptor: una cuenta que no se opuso, un correo al cliente y nunca uno de {@see NEVER}.
     * La comparten los clics y las aperturas (`EmailOpenMarks`, que le suma el consentimiento).
     */
    public static function personAllows(mixed $recipient, string $key): bool
    {
        return $recipient instanceof User
            && ! $recipient->analytics_opt_out
            && EmailUtm::isCustomerKey($key)
            && ! in_array($key, self::NEVER, true);
    }

    /**
     * Oyente de `NotificationSending`. ⚠️ No devuelve nada a propósito: el framework lo escucha con `until()` y un `false`
     * cancelaría el correo. Y nada de aquí puede impedir que salga: sin marca, el correo sale igual.
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
            Log::warning('email_clicks.mark_failed', ['error' => $e::class]);
        }
    }

    /** La marca de este envío, o `null` si no la lleva. La pregunta el molde al componerse y `RecordEmailSend` al apuntarlo. */
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
