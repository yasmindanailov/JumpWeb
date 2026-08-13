<?php

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Models\User;

/**
 * Resultado de un intento de alta pública (Fase 3 · paso 3c).
 *
 * Devuelve un veredicto y deja los EFECTOS al llamante, igual que `LoginResult` y
 * `Booking\Contracts\AdmissionDecision`: la web pinta «revisa tu correo» en el modal, la API
 * responde 201 o 422, y ninguno de los dos debería tener que capturar excepciones para hacerlo.
 *
 * **Los cuatro desenlaces que parecen éxito, y por qué.** `CREATED` y `PRETENDED` se cuentan igual
 * al cliente —la misma pantalla, el mismo 201— porque `PRETENDED` es lo que se le responde a un
 * bot que rellenó el honeypot o a quien ya agotó el límite por correo: si la respuesta delatara la
 * diferencia, el señuelo dejaría de servir. Que aquí sí se distingan es para que quien llama sepa
 * si hay usuario con el que continuar (iniciar sesión en la compra) y para que los tests puedan
 * comprobar que NO se creó ninguna cuenta.
 */
final readonly class SignupResult
{
    /** Cuenta creada. `user` viene relleno. */
    public const CREATED = 'created';

    /**
     * No se creó nada, pero se responde como si sí: honeypot relleno, o límite por correo agotado.
     * Nunca hay `user`.
     */
    public const PRETENDED = 'pretended';

    /**
     * Ese correo ya tiene cuenta **verificada**. Se le dice al usuario, y se le avisa por correo al
     * titular real con un enlace de acceso.
     *
     * ⚠️ Es una decisión de PRODUCTO de la clienta, no un descuido: para el parque prima la
     * conversión sobre ocultar qué correos existen. La API la replica a propósito
     * (`DECISIONES #31`) — dos puertas con dos respuestas distintas anularían la decisión por la
     * de atrás. Lo que sí frena la enumeración masiva es el límite por correo (3/hora).
     */
    public const ALREADY_REGISTERED = 'already_registered';

    /** Ese correo tiene cuenta SIN verificar: se le reenvía la verificación para que la complete. */
    public const PENDING_VERIFICATION = 'pending_verification';

    /** Demasiadas altas desde esta IP. */
    public const RATE_LIMITED = 'rate_limited';

    /** El anti-bot (Turnstile) rechazó la petición. */
    public const BOT_CHECK_FAILED = 'bot_check_failed';

    private function __construct(
        public string $outcome,
        public ?User $user = null,
        /** Segundos hasta poder reintentar. Solo con `RATE_LIMITED`. */
        public int $retryAfter = 0,
    ) {}

    public static function created(User $user): self
    {
        return new self(self::CREATED, $user);
    }

    public static function pretended(): self
    {
        return new self(self::PRETENDED);
    }

    public static function alreadyRegistered(): self
    {
        return new self(self::ALREADY_REGISTERED);
    }

    public static function pendingVerification(): self
    {
        return new self(self::PENDING_VERIFICATION);
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(self::RATE_LIMITED, retryAfter: $retryAfter);
    }

    public static function botCheckFailed(): self
    {
        return new self(self::BOT_CHECK_FAILED);
    }

    /** ¿Se le responde al cliente como si el alta hubiera ido bien? */
    public function looksSuccessful(): bool
    {
        return $this->outcome === self::CREATED || $this->outcome === self::PRETENDED;
    }
}
