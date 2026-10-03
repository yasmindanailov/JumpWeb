<?php

namespace App\Mail;

use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * Email del formulario de contacto al administrador.
 *
 * Fase 7.5 (decisión #180): recibe un array plano (no el modelo `ContactMessage`,
 * retirado) — el formulario ya no persiste en BD, solo envía este correo.
 *
 * ▶ **Con la PLANTILLA desde la R1c** (`specs/correos-rediseno.md` §4.1.4): compone un `BrandedMailMessage` SIN
 * notificación —sin UTM, sin marca de envío ni píxel: el equipo no es audiencia— y pinta sus vistas, con su oscuro y su
 * versión de texto. Se envía igual que antes (`ContactController`), con el `replyTo` de quien escribe.
 *
 * @phpstan-param array{name:string, email:string, phone:?string, topic:?string, message:string, locale:string} $contact
 */
class ContactMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * ⚠️⚠️ **En el idioma del PARQUE, no en el de quien escribe** (lo lee el operador: traducirlo al francés porque el visitante
     * navegaba en francés le dejaría la bandeja en tres idiomas), y por eso AQUÍ no se decide nada del idioma: se construye
     * en la petición, donde `SetLocale` ha pisado `config('app.locale')` con el del visitante (medido al construir la R1c: el
     * correo salía en francés). Se pinta en la COLA, donde es el de la instalación (`ContactTopics`).
     *
     * @param  array<string, mixed>  $contact
     */
    public function __construct(public array $contact) {}

    /**
     * ⚠️ **El TEMA va en el ASUNTO** (`DECISIONES #535`), que es lo que se lee en la bandeja antes
     * de abrir nada —la lección del carril de correos (`#506`)—. Clasifica el mensaje sin tener que
     * entrar en él. Sin tema elegido, el asunto de siempre: una ausencia no inventa una categoría.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asunto(),
            replyTo: [$this->contact['email']],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: BrandedMailMessage::VISTAS['html'],
            text: BrandedMailMessage::VISTAS['text'],
            with: $this->withLocale((string) config('app.locale'), fn (): array => $this->mensaje()->data()),
        );
    }

    /** El asunto, con el idioma del negocio EXPLÍCITO: se lee también fuera del envío (las pruebas, el registro). */
    private function asunto(): string
    {
        $locale = (string) config('app.locale');
        $topic = $this->contact['topic'] ?? null;
        $etiqueta = $topic
            ? (string) __('site.contact_topics.'.$topic, [], $locale)
            : (string) __('emails.contact_message.subject_no_topic', [], $locale);

        return $etiqueta.' — '.($this->contact['name'] ?? '');
    }

    /**
     * El correo: la cabecera con quién escribe, el resguardo con sus datos (los que dejó), el mensaje —un párrafo por cada
     * línea que escribió, que el HTML de antes juntaba— y, al pie, el idioma en que navegaba y cuándo, en la hora del parque.
     */
    private function mensaje(): BrandedMailMessage
    {
        $c = $this->contact;
        $topic = $c['topic'] ?? null;
        $filas = array_filter([
            (string) __('emails.contact_message.name') => (string) ($c['name'] ?? ''),
            (string) __('emails.contact_message.email') => (string) ($c['email'] ?? ''),
            (string) __('emails.contact_message.phone') => (string) ($c['phone'] ?? ''),
            (string) __('emails.contact_message.topic') => $topic ? (string) __('site.contact_topics.'.$topic) : '',
        ], static fn (string $valor): bool => trim($valor) !== '');

        $correo = (new BrandedMailMessage)
            ->subject($this->asunto())
            ->hero('emails.contact_message', 'info', $filas, ['name' => (string) ($c['name'] ?? '')]);
        foreach (preg_split('/\R+/u', trim((string) ($c['message'] ?? ''))) ?: [] as $parrafo) {
            $correo->line($parrafo);
        }

        $idioma = (string) ($c['locale'] ?? '');

        return $correo->outro(__('emails.contact_message.sent_from', [
            'language' => Lang::has('emails.contact_message.languages.'.$idioma)
                ? (string) __('emails.contact_message.languages.'.$idioma)
                : ($idioma !== '' ? $idioma : '—'),
            'when' => DisplayTime::now()->format('d/m/Y H:i'),
        ]));
    }
}
