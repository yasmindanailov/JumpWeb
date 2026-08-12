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
 * @phpstan-param array{name:string, email:string, phone:?string, message:string, locale:string} $contact
 */
class ContactMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $contact
     */
    public function __construct(public array $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuevo mensaje de contacto — '.($this->contact['name'] ?? ''),
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
