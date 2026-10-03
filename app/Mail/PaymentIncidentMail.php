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

/**
 * Aviso al OPERADOR de una incidencia crítica de cobro Redsys (recomendación C, 2026-06-15):
 * un cobro capturado en el banco que NO casa con una reserva cumplible (duplicado/huérfano), o
 * un cobro autorizado que llegó tras caducar la reserva.
 *
 * Recibe un array plano SIN PII (códigos/ids, nunca email/nombre del cliente; espejo de la
 * minimización de `audit_logs`). Email interno → en el idioma del PARQUE, sin i18n de cliente.
 * Se envía vía `Mail::to(IncidentSettings::alertEmail())` desde `RedsysReturnHandler`, fuera de
 * la transacción y tolerante a fallos: un fallo de correo nunca debe deshacer un cobro capturado.
 *
 * ▶ **Con la PLANTILLA desde la R1c** (`specs/correos-rediseno.md` §4.1.4), como el de contacto: un `BrandedMailMessage`
 * sin notificación (sin UTM ni marcas). ⚠️ Cambia lo que PINTA, no cómo se envía: `RedsysReturnHandler` (`CRITICAL_RE`) no
 * se toca, y el constructor y el asunto son los de siempre.
 *
 * @phpstan-param array{kind:string, action:string, order_id:int, order_code:string, order_status:string, payment_id:int, gateway_order:?string, source:string} $incident
 */
class PaymentIncidentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Nada del idioma se decide al construirlo (como `ContactMessageMail`): se pinta en la COLA, en el de la instalación.
     *
     * @param  array<string, mixed>  $incident
     */
    public function __construct(public array $incident) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto());
    }

    public function content(): Content
    {
        return new Content(
            view: BrandedMailMessage::VISTAS['html'],
            text: BrandedMailMessage::VISTAS['text'],
            with: $this->withLocale((string) config('app.locale'), fn (): array => $this->mensaje()->data()),
        );
    }

    /** Los dos tipos de `RedsysReturnHandler`: `duplicate`, y el resto es el cobro llegado tras caducar (`overbooked`). */
    private function tipo(): string
    {
        return ($this->incident['kind'] ?? '') === 'duplicate' ? 'duplicate' : 'overbooked';
    }

    /** El asunto, con el idioma del negocio EXPLÍCITO: se lee también fuera del envío. */
    private function asunto(): string
    {
        $locale = (string) config('app.locale');

        return (string) __('emails.payment_incident.subject', [
            'label' => (string) __('emails.payment_incident.labels.'.$this->tipo(), [], $locale),
            'code' => (string) ($this->incident['order_code'] ?? ''),
        ], $locale);
    }

    /** La cabecera con el tipo, el resguardo con lo que identifica el cobro, qué hacer en el aviso y el pie del sistema. */
    private function mensaje(): BrandedMailMessage
    {
        $i = $this->incident;
        $tipo = $this->tipo();
        $valor = static fn (mixed $v): string => $v === null || $v === '' ? '—' : (string) $v;

        return (new BrandedMailMessage)
            ->subject($this->asunto())
            ->hero('emails.payment_incident', 'err', [
                (string) __('emails.payment_incident.order') => $valor($i['order_code'] ?? null),
                (string) __('emails.payment_incident.order_status') => $valor($i['order_status'] ?? null),
                (string) __('emails.payment_incident.gateway_order') => $valor($i['gateway_order'] ?? null),
                (string) __('emails.payment_incident.payment_id') => $valor($i['payment_id'] ?? null),
                (string) __('emails.payment_incident.source') => $valor($i['source'] ?? null),
            ], ['title' => (string) __('emails.payment_incident.titles.'.$tipo)])
            ->notice(
                (string) __('emails.payment_incident.'.$tipo.'.title'),
                (string) __('emails.payment_incident.'.$tipo.'.body'),
                'warn',
            )
            ->outro(__('emails.payment_incident.footer', ['when' => DisplayTime::now()->format('d/m/Y H:i')]));
    }
}
