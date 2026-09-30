<?php

namespace App\Domain\Identity\Contracts;

/**
 * **Cómo acabó completar un cambio de correo** (A2b de `docs/specs/acceso-con-codigo.md` §4.9, `DECISIONES #856`), por el
 * CÓDIGO al buzón nuevo o por el ENLACE firmado de siempre: las dos superficies usan el mismo dominio
 * (`AccountProfile::completeEmailChange()`) y cada una lo traduce a lo suyo —un JSON o una redirección con aviso—.
 * Devuelve un veredicto; no lanza ni pinta.
 */
final readonly class EmailChangeOutcome
{
    /** El correo nuevo es ya el de la cuenta, verificado. */
    public const CONFIRMED = 'confirmed';

    /** La solicitud pasó de su ventana: se ha borrado y hay que pedirla otra vez. */
    public const EXPIRED = 'expired';

    /** Otra cuenta se quedó ese correo entre la solicitud y la confirmación: se ha borrado. */
    public const TAKEN = 'taken';

    /** No hay ningún cambio pendiente. */
    public const NOTHING_PENDING = 'nothing_pending';

    /** El código no casa, caducó o ya se usó. */
    public const WRONG_CODE = 'wrong_code';

    /** Demasiados intentos fallidos. */
    public const RATE_LIMITED = 'rate_limited';

    private function __construct(
        public string $outcome,
        /** Segundos hasta poder reintentar. Solo con `RATE_LIMITED`. */
        public int $retryAfter = 0,
    ) {}

    public static function of(string $outcome): self
    {
        return new self($outcome);
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(self::RATE_LIMITED, max(1, $retryAfter));
    }

    public function confirmed(): bool
    {
        return $this->outcome === self::CONFIRMED;
    }
}
