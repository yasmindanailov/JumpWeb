<?php

namespace App\Domain\Booking\Contracts;

/**
 * Veredicto de la política de admisión de reservas (Fase 3 · paso 2).
 *
 * **Devuelve un resultado; no lanza ni pinta.** Es el mismo criterio que el spec §4.6.3 fija para
 * las extracciones de auth: el dominio decide, y los EFECTOS —un `addError` en el sidebar, un flash
 * y un redirect en «Mis pedidos», un 422 con su código en la API— son de quien atiende la petición.
 * Si la política lanzara excepciones, cada superficie tendría que capturarlas para traducirlas
 * igual, y la primera que se olvidara devolvería un 500 donde debía haber un aviso.
 *
 * `reason` es una CLAVE ESTABLE, no una clave de traducción ni un texto: la API la expondrá como
 * código público y los ficheros de `lang/` pueden reorganizarse sin romper a nadie (misma lección
 * que `ApiErrorCode`).
 */
final readonly class AdmissionDecision
{
    /** El panel pausó las reservas online (#218). No es un error del cliente. */
    public const RESERVATIONS_PAUSED = 'reservations_paused';

    /** El titular ya acumula el máximo de pedidos pendientes vivos (tope de aforo retenido). */
    public const TOO_MANY_PENDING = 'too_many_pending';

    /** Demasiadas reservas en el último minuto (frecuencia). */
    public const RATE_LIMITED = 'rate_limited';

    /**
     * @param  array<string, mixed>  $context  datos para componer el aviso (p. ej. `max`), nunca el texto
     */
    private function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public array $context = [],
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    /**
     * @param  self::*  $reason
     * @param  array<string, mixed>  $context
     */
    public static function deny(string $reason, array $context = []): self
    {
        return new self(false, $reason, $context);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }
}
