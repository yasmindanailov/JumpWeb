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
        /**
         * El menor INVITADO por el que se firma (`specs/waiver-por-reserva.md` §4.1). Va en su propio
         * campo y no en `$subjectId` porque `waiver_signatures.subject_id` tiene **FK dura a
         * `dependents`** —medido: un id ajeno da `1452`— y quitarla regresaría un endurecimiento.
         */
        public readonly ?int $authorizationId = null,
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
    /**
     * La misma petición, pero EN NOMBRE de un menor a cargo (`menores-a-cargo.md` §4.3): el canal, la
     * ip y el user-agent son los del titular que firma; cambia el sujeto.
     */
    public function forDependent(int $dependentId): self
    {
        return new self($this->channel, $this->ip, $this->userAgent, WaiverSignature::SUBJECT_DEPENDENT, $dependentId, $this->declaredBy);
    }

    /**
     * La misma petición, pero por un menor INVITADO a una reserva
     * (`specs/waiver-por-reserva.md` §4.1): el canal, la ip y el user-agent son los del **adulto que
     * firma**, que no tiene cuenta; el titular de la fila sigue siendo el RESPONSABLE de la reserva.
     */
    public function forGuestMinor(int $authorizationId): self
    {
        return new self(
            $this->channel,
            $this->ip,
            $this->userAgent,
            WaiverSignature::SUBJECT_GUEST_MINOR,
            null,
            $this->declaredBy,
            $authorizationId,
        );
    }

    public static function declaredAtCounter(User $operator, ?string $ip, ?string $userAgent = null): self
    {
        return new self(WaiverSignature::CHANNEL_PANEL, $ip, $userAgent, declaredBy: $operator);
    }
}
