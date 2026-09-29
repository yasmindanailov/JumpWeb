<?php

namespace App\Domain\Platform\Listeners;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\EmailSend;
use App\Domain\Platform\Services\Analytics\EmailClickMarks;
use App\Domain\Platform\Services\Analytics\EmailOpenMarks;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Throwable;

/**
 * **Apunta cada correo AL CLIENTE que sale, o que falla al salir** (`specs/correos-salientes.md` §4.6, `DECISIONES #794`,
 * la C1). Escucha los dos eventos del framework en vez de tocar los veinticinco `toMail()`: `NotificationSent` trae el
 * mensaje que salió (asunto, destinatario, HTML y adjuntos) y `NotificationFailed` dice que no salió (la cola reintentará).
 *
 * ⚠️⚠️ **Apuntar NUNCA puede romper el envío** (`#794`): los dos eventos se disparan DENTRO del envío, así que una excepción
 * aquí subiría al trabajo de la cola, que se reintentaría y mandaría el correo DOS veces. Todo va en un `try/catch` que
 * solo deja una línea en el registro, sin la dirección ni el contenido; y tras el commit de la transacción en curso, fuera
 * de su lock (ver {@see safely()}).
 *
 * ⚠️ Una fila por envío: la clave es el id de la notificación (uno por destinatario, el mismo en cada reintento), así que un
 * envío que falla y luego sale bien completa la MISMA fila. Solo los correos al cliente (`EmailUtm::isCustomerKey()`): los
 * avisos al negocio no son de nadie a quien enseñárselos.
 */
final class RecordEmailSend
{
    public function sent(NotificationSent $event): void
    {
        $this->safely(function () use ($event): void {
            $key = $this->customerKey($event->channel, $event->notification);
            $email = $event->response instanceof SentMessage ? $event->response->getOriginalMessage() : null;

            if ($key === null || ! $email instanceof Email) {
                return;
            }

            $send = $email->getHeaders()->get(EmailSend::HEADER)?->getBodyAsString() ?? $this->idOf($event->notification);
            if ($send === null) {
                return;
            }

            $now = now();
            DB::table('email_sends')->upsert([[
                'send_key' => $send,
                'user_id' => $this->userOf($event->notifiable),
                'recipient' => $this->firstAddress($email),
                'mail_key' => $key,
                'subject' => mb_substr((string) $email->getSubject(), 0, 255),
                'html' => $this->htmlOf($email),
                'attachments' => json_encode(array_values(array_map(static fn (DataPart $p): string => (string) $p->getFilename(), $email->getAttachments()))),
                // Si salió con la marca del envío en sus enlaces (la C2, §4.8): sin ella, sus clics «no se miden».
                'tracks_clicks' => EmailClickMarks::for($event->notification) !== null,
                // Y si salió con el píxel de apertura (la C3, §4.12): sin él, sus aperturas «no se miden».
                'tracks_opens' => EmailOpenMarks::for($event->notification) !== null,
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['send_key'], ['user_id', 'recipient', 'mail_key', 'subject', 'html', 'attachments', 'tracks_clicks', 'tracks_opens', 'sent_at', 'updated_at']);
        });
    }

    public function failed(NotificationFailed $event): void
    {
        $this->safely(function () use ($event): void {
            $key = $this->customerKey($event->channel, $event->notification);
            $send = $this->idOf($event->notification);

            if ($key === null || $send === null) {
                return;
            }

            $now = now();
            $done = DB::table('email_sends')->where('send_key', $send)
                ->update(['failures' => DB::raw('failures + 1'), 'failed_at' => $now, 'updated_at' => $now]);

            if ($done === 0) {
                DB::table('email_sends')->insert([
                    'send_key' => $send,
                    'user_id' => $this->userOf($event->notifiable),
                    'recipient' => $this->addressOf($event->notifiable, $event->notification),
                    'mail_key' => $key,
                    'failures' => 1,
                    'failed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /** La clave del correo si va AL CLIENTE por correo electrónico; `null` si no se apunta. */
    private function customerKey(string $channel, object $notification): ?string
    {
        if ($channel !== 'mail' || ! $notification instanceof Notification) {
            return null;
        }

        $key = EmailUtm::keyOf($notification);

        return EmailUtm::isCustomerKey($key) ? $key : null;
    }

    private function idOf(object $notification): ?string
    {
        $id = $notification->id ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function userOf(mixed $notifiable): ?int
    {
        return $notifiable instanceof User ? (int) $notifiable->getAuthIdentifier() : null;
    }

    /** La dirección de quien no tiene cuenta (o de la cuenta), para un envío que no llegó a salir. */
    private function addressOf(mixed $notifiable, object $notification): ?string
    {
        if (! is_object($notifiable) || ! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor('mail', $notification);
        $first = is_array($route) ? (array_key_first($route) !== null && is_string(array_key_first($route)) ? array_key_first($route) : reset($route)) : $route;

        return is_string($first) && $first !== '' ? mb_substr($first, 0, 255) : null;
    }

    private function firstAddress(Email $email): ?string
    {
        $to = $email->getTo()[0] ?? null;

        return $to === null ? null : mb_substr($to->getAddress(), 0, 255);
    }

    private function htmlOf(Email $email): ?string
    {
        $html = $email->getHtmlBody();

        if (is_resource($html)) {
            $html = stream_get_contents($html);
        }

        return is_string($html) && $html !== '' ? $html : null;
    }

    /**
     * El blindaje, como `Recorder`: se apunta TRAS el commit de la transacción en curso y nada de apuntar sube al envío.
     * ⚠️⚠️ Con la cola `sync` el correo sale dentro de la transacción de quien lo dispara (un pedido, un pago): un deadlock
     * aquí desharía en InnoDB la transacción ENTERA y, tragado el error, el código de fuera seguiría sin saberlo. Sin
     * transacción abierta (el worker de la cola), `afterCommit` corre en el acto. En el registro, solo el tipo de error.
     */
    private function safely(callable $record): void
    {
        DB::afterCommit(static function () use ($record): void {
            try {
                $record();
            } catch (Throwable $e) {
                Log::warning('email_sends.record_failed', ['error' => $e::class]);
            }
        });
    }
}
