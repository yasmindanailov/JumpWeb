<?php

namespace App\Domain\Identity\Contracts;

/**
 * Resultado de reenviar la confirmación de un cambio de correo pendiente.
 *
 * ⚠️ **«No había nada pendiente» NO es un fallo**, y por eso tiene su propio estado: quien pulsa dos
 * veces, o vuelve atrás y reintenta, no ha hecho nada malo. Tratarlo como error haría que la pantalla
 * enseñara una alarma por una situación normal.
 */
final readonly class ResendResult
{
    /** No hay ningún cambio de correo pendiente que reenviar. */
    public const NOTHING_PENDING = 'nothing_pending';

    /** Ya se reenvió hace muy poco: hay que esperar. */
    public const THROTTLED = 'throttled';

    private function __construct(
        public bool $sent,
        public ?string $reason = null,
        public int $retryAfter = 0,
    ) {}

    public static function sent(): self
    {
        return new self(true);
    }

    public static function nothingPending(): self
    {
        return new self(false, self::NOTHING_PENDING);
    }

    public static function throttled(int $retryAfter): self
    {
        return new self(false, self::THROTTLED, $retryAfter);
    }
}
