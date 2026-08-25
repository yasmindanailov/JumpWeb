<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;

/**
 * Fase 6 · waiver — lo que la capa de entrega sabe de una aceptación y el dominio no puede adivinar:
 * canal, ip, user-agent, sujeto (titular o menor a cargo) y, en el alta presencial, el operador que
 * la DECLARA (`specs/waiver-probatorio.md` §8.4).
 */
final class WaiverSignatureRequest
{
    public function __construct(
        public readonly string $channel,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly string $subjectType = WaiverSignature::SUBJECT_HOLDER,
        public readonly ?int $subjectId = null,
        public readonly ?User $declaredBy = null,
    ) {}

    public static function web(?string $ip, ?string $userAgent): self
    {
        return new self(WaiverSignature::CHANNEL_WEB, $ip, $userAgent);
    }

    public static function api(?string $ip, ?string $userAgent): self
    {
        return new self(WaiverSignature::CHANNEL_API, $ip, $userAgent);
    }

    /**
     * Alta presencial (`CustomerRegistrar`): no hay navegador del cliente, así que la firma la
     * declara el operador y el registro lo dice — el PDF tiene que decirlo con todas las letras.
     */
    public static function declaredAtCounter(User $operator, ?string $ip, ?string $userAgent = null): self
    {
        return new self(WaiverSignature::CHANNEL_PANEL, $ip, $userAgent, declaredBy: $operator);
    }
}
