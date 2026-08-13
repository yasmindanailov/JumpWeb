<?php

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Models\User;

/**
 * Resultado de un intento de inicio de sesión (Fase 3 · paso 3b).
 *
 * **Devuelve un veredicto; no lanza ni pinta**, igual que `Booking\Contracts\AdmissionDecision`
 * (spec §4.6.3). El mismo intento fallido es una `ValidationException` bajo el input del modal en
 * la web y un `401` con su código en la API; si el servicio eligiera por ellos, una de las dos
 * superficies acabaría traduciendo una excepción a otra cosa.
 *
 * **El motivo NUNCA distingue «esa cuenta no existe» de «esa contraseña no es»**: las dos son
 * `INVALID_CREDENTIALS`, porque separarlas convierte el login en un oráculo de qué correos están
 * registrados (`SEC-06`, anti-enumeración). Lo que sí se distingue es el limitador, que no revela
 * nada sobre la cuenta y sí le dice al cliente legítimo cuánto tiene que esperar.
 */
final readonly class LoginResult
{
    /** Credenciales que no casan. **No** dice cuál de las dos falló. */
    public const INVALID_CREDENTIALS = 'invalid_credentials';

    /** Demasiados intentos: por (email, IP) o por IP sola. */
    public const RATE_LIMITED = 'rate_limited';

    private function __construct(
        public bool $succeeded,
        public ?User $user = null,
        public ?string $reason = null,
        /** Segundos hasta poder reintentar. Solo tiene sentido con `RATE_LIMITED`. */
        public int $retryAfter = 0,
    ) {}

    public static function success(User $user): self
    {
        return new self(true, $user);
    }

    public static function invalidCredentials(): self
    {
        return new self(false, reason: self::INVALID_CREDENTIALS);
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(false, reason: self::RATE_LIMITED, retryAfter: $retryAfter);
    }

    public function failed(): bool
    {
        return ! $this->succeeded;
    }

    public function wasRateLimited(): bool
    {
        return $this->reason === self::RATE_LIMITED;
    }
}
