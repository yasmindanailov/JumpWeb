<?php

namespace App\Domain\Identity\Contracts;

/**
 * Resultado de actualizar el perfil del titular (`specs/area-cliente.md` §9).
 *
 * **Devuelve un veredicto; no lanza ni pinta**, como {@see LoginResult} y
 * {@see CredentialChangeResult}.
 *
 * ⚠️ **Distingue «guardado» de «guardado, y además se ha pedido un cambio de correo»**, y no es un
 * matiz cosmético: el segundo caso deja el email ANTERIOR intacto y manda dos correos, así que la
 * pantalla tiene que decir algo distinto —«revisa tu buzón nuevo»— y no «listo». Fundirlos haría que
 * el cliente creyera que su correo ya cambió cuando todavía no.
 */
final readonly class ProfileUpdateResult
{
    /** La contraseña de reconfirmación no es la que se ha escrito. */
    public const WRONG_PASSWORD = 'wrong_password';

    /** Demasiados intentos fallidos de reconfirmación. */
    public const RATE_LIMITED = 'rate_limited';

    /**
     * Otra cuenta reclamó ese correo **entre la validación y el guardado**.
     *
     * ⚠️ Existe porque la carrera es real: dos titulares pueden pedir el mismo correo a la vez y
     * `Rule::unique` mira un instante anterior al `INSERT`. Quien la pierde recibe esto, no un 500.
     */
    public const EMAIL_TAKEN = 'email_taken';

    private function __construct(
        public bool $succeeded,
        /** Se ha solicitado un cambio de correo: el anterior sigue vigente hasta que se confirme. */
        public bool $emailChangeRequested = false,
        public ?string $reason = null,
        public int $retryAfter = 0,
    ) {}

    public static function saved(bool $emailChangeRequested = false): self
    {
        return new self(true, $emailChangeRequested);
    }

    public static function wrongPassword(): self
    {
        return new self(false, reason: self::WRONG_PASSWORD);
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(false, reason: self::RATE_LIMITED, retryAfter: $retryAfter);
    }

    public static function emailTaken(): self
    {
        return new self(false, reason: self::EMAIL_TAKEN);
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
