<?php

namespace App\Domain\Identity\Contracts;

/**
 * El veredicto de LA PUERTA del acceso con código (`docs/specs/acceso-con-codigo.md` §4.4, `DECISIONES #849`): el
 * cliente escribe su correo y el servidor dice por dónde sigue. Devuelve un veredicto, no lanza ni pinta (como
 * {@see LoginResult}): la API lo traduce a HTTP.
 *
 * ⚠️ **Dice si el correo tiene cuenta, a propósito** (`#849`, como el alta de `#31`): con cuenta, el código; nuevo, el
 * registro sin código —en la puerta del parque hay cola y el alta no espera a ningún correo—. Lo acotan los límites de
 * `Identity\Services\EmailCodeLogin`.
 */
final readonly class CodeRequestResult
{
    /** La cuenta existe: su código va de camino. */
    public const CODE = 'code';

    /** Correo nuevo: a la pantalla del alta, sin código. */
    public const REGISTER = 'register';

    private function __construct(
        /** `code` o `register`; con un límite, `code` si el tope fue el del correo (hay un código vivo) o `null`. */
        public ?string $next,
        public bool $limited = false,
        /** Segundos hasta poder pedir otro. Solo con `$limited`. */
        public int $retryAfter = 0,
    ) {}

    public static function code(): self
    {
        return new self(self::CODE);
    }

    public static function register(): self
    {
        return new self(self::REGISTER);
    }

    /**
     * Demasiadas peticiones. `$next = code` cuando el tope fue el del CORREO: esa cuenta existe y tiene un código recién
     * enviado, así que la pantalla puede seguir a escribirlo (no revela nada que la puerta no diga ya).
     */
    public static function rateLimited(int $retryAfter, ?string $next = null): self
    {
        return new self($next, true, max(1, $retryAfter));
    }
}
