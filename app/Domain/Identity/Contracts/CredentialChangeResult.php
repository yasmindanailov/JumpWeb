<?php

namespace App\Domain\Identity\Contracts;

/**
 * Resultado de una gestión de credenciales que exige **reconfirmar** con un código `confirm` al correo de la cuenta
 * (`specs/area-cliente.md` §9.2; desde la A5 de `specs/acceso-con-codigo.md`, `#869`, solo con el código: la contraseña
 * del cliente se retiró).
 *
 * **Devuelve un veredicto; no lanza ni pinta**, igual que {@see LoginResult}: la pantalla y la API traducen el mismo
 * «no» cada una a lo suyo.
 *
 * ⚠️ **Aquí SÍ se dice que el código es el que falla, y no contradice a `SEC-06`.** En la puerta, distinguir «no existe
 * esa cuenta» de «no es ese código» la convertiría en un oráculo de qué correos están registrados; aquí **ya sabemos quién
 * es** —hay sesión— y no se revela nada que el titular no sepa. Ocultarlo solo le dejaría sin saber qué corregir.
 */
final readonly class CredentialChangeResult
{
    /** El CÓDIGO de confirmar no casa, caducó o ya se usó (A2a, `#855`): el 422 va sobre `code`. */
    public const WRONG_CODE = 'wrong_code';

    /** Demasiados intentos fallidos seguidos desde esta IP para esta cuenta. */
    public const RATE_LIMITED = 'rate_limited';

    private function __construct(
        public bool $succeeded,
        public ?string $reason = null,
        /** Segundos hasta poder reintentar. Solo tiene sentido con `RATE_LIMITED`. */
        public int $retryAfter = 0,
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function wrongCode(): self
    {
        return new self(false, self::WRONG_CODE);
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(false, self::RATE_LIMITED, $retryAfter);
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
