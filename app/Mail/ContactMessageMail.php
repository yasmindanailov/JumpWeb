<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email del formulario de contacto al administrador.
 *
 * Fase 7.5 (decisión #180): recibe un array plano (no el modelo `ContactMessage`,
 * retirado) — el formulario ya no persiste en BD, solo envía este correo.
 *
 * @phpstan-param array{name:string, email:string, phone:?string, topic:?string, message:string, locale:string} $contact
 */
class ContactMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $contact
     */
    public function __construct(public array $contact) {}

    /**
     * ⚠️ **El TEMA va en el ASUNTO** (`DECISIONES #535`), que es lo que se lee en la bandeja antes
     * de abrir nada —la lección del carril de correos (`#506`)—. Clasifica el mensaje sin tener que
     * entrar en él.
     *
     * ⚠️⚠️ **Y el asunto se escribe en el idioma del PARQUE, no en el de quien escribe.** Este correo
     * lo lee el operador: traducirlo al francés porque el visitante navegaba en francés le dejaría la
     * bandeja en tres idiomas. `__()` resolvería con el locale de la petición, así que el tema se
     * traduce con el de `config('app.locale')` explícito.
     * ⚠️ Sin tema elegido el asunto es el de siempre: una ausencia no inventa una categoría.
     */
    public function envelope(): Envelope
    {
        $topic = $this->contact['topic'] ?? null;
        $etiqueta = $topic
            ? (string) __('site.contact_topics.'.$topic, [], (string) config('app.locale'))
            : null;

        return new Envelope(
            subject: ($etiqueta ? $etiqueta.' — ' : 'Nuevo mensaje de contacto — ').($this->contact['name'] ?? ''),
            replyTo: [$this->contact['email']],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact',
            with: ['contact' => $this->contact],
        );
    }
}
