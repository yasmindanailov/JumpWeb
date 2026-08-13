<?php

namespace App\Domain\Identity\Contracts;

/**
 * Resultado de pedir un enlace de recuperación o de fijar la nueva contraseña
 * (Fase 3 · paso 3c).
 *
 * **Aquí la anti-enumeración SÍ es estricta**, al contrario que en el alta: un correo inexistente
 * y un token inválido o caducado dan el MISMO desenlace (`INVALID`), y pedir el enlace responde
 * igual exista o no la cuenta. La diferencia con el registro no es incoherencia: en el alta la
 * clienta decidió avisar («ya tienes cuenta») porque hay una persona intentando comprar y
 * esconderlo le cuesta la venta; aquí no hay nada que ganar diciéndolo y sí un oráculo que regalar.
 */
final readonly class PasswordResetResult
{
    /** Enlace enviado (o no, si esa cuenta no existe: no se distingue) / contraseña cambiada. */
    public const OK = 'ok';

    /** Nuestro limitador por IP. */
    public const RATE_LIMITED = 'rate_limited';

    /** Token inválido o caducado, o cuenta inexistente. **Los tres dicen lo mismo.** */
    public const INVALID = 'invalid';

    /**
     * El limitador del propio broker de Laravel (un enlace por minuto y correo). Se distingue de
     * `RATE_LIMITED` porque su mensaje es el del framework y no revela nada: es un límite de tasa
     * legítimo, no enumeración.
     */
    public const BROKER_THROTTLED = 'broker_throttled';

    private function __construct(
        public string $outcome,
        /** Segundos hasta poder reintentar. Solo con `RATE_LIMITED`. */
        public int $retryAfter = 0,
    ) {}

    public static function ok(): self
    {
        return new self(self::OK);
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(self::RATE_LIMITED, $retryAfter);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID);
    }

    public static function brokerThrottled(): self
    {
        return new self(self::BROKER_THROTTLED);
    }

    public function succeeded(): bool
    {
        return $this->outcome === self::OK;
    }
}
