<?php

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Models\User;

/**
 * Veredicto de entrar con una identidad externa (`docs/specs/auth-con-google.md` §5).
 *
 * Hermano de {@see LoginResult}, y por el mismo motivo: **devuelve un veredicto; no lanza ni
 * decide a dónde va nadie**. La diferencia con la contraseña es que aquí hay TRES desenlaces y no
 * dos — se entra, hay que completar un alta, o se rechaza —, y el segundo es el caso del 90 %.
 *
 * ⚠️ **`refused` no es «credenciales inválidas» y aquí SÍ se dice el motivo.** No hay oráculo de
 * enumeración que proteger: a este punto solo llega quien acaba de demostrarle a Google que ese
 * buzón es suyo (`SEC-06` sigue intacta — ver §5.2). Callarle el motivo a esa persona solo consigue
 * que no sepa qué hacer.
 */
final readonly class SocialLoginResult
{
    /** La identidad resuelve a una cuenta y la sesión queda abierta. */
    public const SIGNED_IN = 'signed_in';

    /** No hay cuenta para esa identidad: hay que completar el alta (§5.3). */
    public const NEEDS_REGISTRATION = 'needs_registration';

    /** No se entra, y el motivo se le puede decir. */
    public const REFUSED = 'refused';

    /** El proveedor dice que ese correo NO está verificado en su lado: no se vincula ni se crea nada. */
    public const REASON_EMAIL_UNVERIFIED = 'email_unverified';

    /** La cuenta destino ejerció el art. 17: no se resucita por esta puerta (`P3`). */
    public const REASON_ANONYMIZED = 'anonymized';

    /**
     * Esa cuenta ya está vinculada a OTRA cuenta del mismo proveedor.
     *
     * No es un fallo de seguridad —quien llega aquí ha demostrado el buzón—: es que dos llaves sobre
     * la misma cuenta dejarían «desvincular» sin significado. Se dice y se ofrece la salida.
     */
    public const REASON_PROVIDER_CONFLICT = 'provider_conflict';

    private function __construct(
        public string $status,
        public ?User $user = null,
        public ?SocialProfile $profile = null,
        public ?string $reason = null,
        /** El vínculo se acaba de crear en ESTA entrada (§5.2): lo usa el aviso por correo. */
        public bool $linked = false,
        /** La cuenta destino estaba SIN verificar y se promovió, expulsando a quien la tuviera (P12). */
        public bool $promoted = false,
    ) {}

    public static function signedIn(User $user, bool $linked = false, bool $promoted = false): self
    {
        return new self(self::SIGNED_IN, user: $user, linked: $linked, promoted: $promoted);
    }

    public static function needsRegistration(SocialProfile $profile): self
    {
        return new self(self::NEEDS_REGISTRATION, profile: $profile);
    }

    public static function refused(string $reason): self
    {
        return new self(self::REFUSED, reason: $reason);
    }

    public function isSignedIn(): bool
    {
        return $this->status === self::SIGNED_IN;
    }

    /**
     * ⚠️ Se llama `isPendingRegistration` y no `needsRegistration` porque PHP no admite un método
     * estático y otro de instancia con el mismo nombre: el constructor nombrado ya ocupa aquél.
     */
    public function isPendingRegistration(): bool
    {
        return $this->status === self::NEEDS_REGISTRATION;
    }

    public function wasRefused(): bool
    {
        return $this->status === self::REFUSED;
    }
}
