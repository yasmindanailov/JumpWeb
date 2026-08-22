<?php

namespace App\Domain\Identity\Contracts;

/**
 * Resultado de una gestión de credenciales que exige **reconfirmar la contraseña**
 * (`specs/area-cliente.md` §9.2).
 *
 * **Devuelve un veredicto; no lanza ni pinta**, igual que {@see LoginResult}. La misma contraseña
 * equivocada es una `ValidationException` bajo el input en «Mi cuenta» y un `422` con su código en la
 * API; si el servicio eligiera por sus dos consumidores, uno de ellos acabaría traduciendo una
 * excepción a otra cosa.
 *
 * ⚠️ **Aquí SÍ se dice que la contraseña es la que falla, y no contradice a `SEC-06`.** En el login,
 * distinguir «no existe esa cuenta» de «no es esa contraseña» convertiría la pantalla en un oráculo
 * de qué correos están registrados; aquí **ya sabemos quién es** —hay sesión— y no se revela nada que
 * el titular no sepa. Ocultarlo solo dejaría al dueño legítimo sin saber qué corregir.
 */
final readonly class CredentialChangeResult
{
    /** La contraseña actual no es la que se ha escrito. */
    public const WRONG_PASSWORD = 'wrong_password';

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

    public static function wrongPassword(): self
    {
        return new self(false, self::WRONG_PASSWORD);
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
