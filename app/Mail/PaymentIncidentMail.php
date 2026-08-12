<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al OPERADOR de una incidencia crítica de cobro Redsys (recomendación C, 2026-06-15):
 * un cobro capturado en el banco que NO casa con una reserva cumplible (duplicado/huérfano), o
 * un cobro autorizado que llegó tras caducar la reserva.
 *
 * Recibe un array plano SIN PII (códigos/ids, nunca email/nombre del cliente; espejo de la
 * minimización de `audit_logs`). Email interno → redactado en español, sin i18n de cliente.
 * Se envía vía `Mail::to(IncidentSettings::alertEmail())` desde `RedsysReturnHandler`, fuera de
 * la transacción y tolerante a fallos: un fallo de correo nunca debe deshacer un cobro capturado.
 *
 * @phpstan-param array{kind:string, action:string, order_id:int, order_code:string, order_status:string, payment_id:int, gateway_order:?string, source:string} $incident
 */
class PaymentIncidentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $incident
     */
    public function __construct(public array $incident) {}

    public function envelope(): Envelope
    {
        $label = $this->incident['kind'] === 'duplicate'
            ? 'cobro duplicado/huérfano'
            : 'cobro tras caducar';

        return new Envelope(
            subject: '⚠️ Incidencia de cobro ('.$label.') — pedido '.($this->incident['order_code'] ?? ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-incident',
            with: ['incident' => $this->incident],
        );
    }
}
