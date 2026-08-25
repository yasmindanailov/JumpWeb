<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use Carbon\CarbonInterface;

/**
 * Fase 6 · waiver — «¿este titular tiene waiver, y de qué versión?», respondido según el MODO de la
 * instalación (`specs/waiver-probatorio.md` §4.1, §4.8). Es lo que consultan la puerta y, en las
 * tandas siguientes, la ficha del panel y la API.
 *
 *  - `desactivado`: no hay pregunta (`isEnabled()` false).
 *  - `externo`: manda el sello `users.waiver_accepted_at`, como siempre; no hay versiones.
 *  - `interno`: manda el REGISTRO firmado. Un sello sin registro (datos de un sistema externo
 *    anterior) NO cuenta: al cambiar a interno, quien no firmó aquí tiene que firmar.
 *    `isCurrent` dice si la firma es de la versión vigente; si no, está `outdated` — se SEÑALA y se
 *    deja pasar (§4.8): la re-firma se pide en la siguiente compra o inicio de sesión.
 */
final class WaiverStatus
{
    public function __construct(
        public readonly string $mode,
        public readonly bool $signed,
        public readonly ?CarbonInterface $acceptedAt,
        public readonly ?int $version,
        public readonly ?string $locale,
        public readonly bool $isCurrent,
    ) {}

    public static function for(User $user): self
    {
        $mode = WaiverSettings::mode();

        if ($mode === WaiverSettings::MODE_OFF) {
            return new self($mode, false, null, null, null, false);
        }

        if ($mode === WaiverSettings::MODE_EXTERNAL) {
            $acceptedAt = $user->waiver_accepted_at;

            return new self($mode, $acceptedAt !== null, $acceptedAt, null, null, $acceptedAt !== null);
        }

        $last = WaiverSignature::query()
            ->where('user_id', $user->getKey())
            ->where('subject_type', WaiverSignature::SUBJECT_HOLDER)
            ->with('version')
            ->orderByDesc('id')
            ->first();

        if ($last === null) {
            return new self($mode, false, null, null, null, false);
        }

        $latest = LegalDocuments::latestVersionNumber(WaiverSettings::SLUG);

        return new self(
            $mode,
            true,
            $last->accepted_at,
            (int) $last->version->version,
            (string) $last->version->locale,
            $latest !== null && (int) $last->version->version === $latest,
        );
    }

    public function isEnabled(): bool
    {
        return $this->mode !== WaiverSettings::MODE_OFF;
    }

    /** Firmado, pero de una versión anterior a la vigente: señalar y dejar pasar (§4.8). */
    public function isOutdated(): bool
    {
        return $this->signed && ! $this->isCurrent;
    }
}
