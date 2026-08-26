<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverStatus;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 6 · waiver — **mi waiver**, visto por su titular (`specs/waiver-probatorio.md` §4.5, §4.8):
 * el estado según el modo de la instalación, y sus propias firmas con el PDF de cada una.
 *
 * ⚠️ `current_document_id` es lo que el cliente devuelve en `POST /me/waiver`: cuando `outdated` es
 * `true`, ése es el texto nuevo que hay que aceptar. Es el mismo id que publica `GET /legal/waiver`
 * en ese idioma — dos caminos, un dato.
 *
 * ⚠️ Lo que NO viaja: ip, user-agent ni hashes de las firmas. Es el mismo criterio que `Consent`
 * (`GET /me/consents`): la prueba completa está en el PDF, que es un acto explícito del titular.
 *
 * @property-read User $resource
 */
class WaiverStatusResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $status = WaiverStatus::for($user);
        $current = WaiverSettings::isInternal()
            ? LegalDocuments::current(WaiverSettings::SLUG, app()->getLocale())
            : null;

        $signatures = $user->waiverSignatures()
            ->with('version')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (WaiverSignature $signature): array => [
                'id' => (int) $signature->getKey(),
                'version_label' => $signature->version?->label() ?? '—',
                'accepted_at' => $signature->accepted_at?->toIso8601String(),
                'accepted_label' => DisplayTime::format($signature->accepted_at, 'd/m/Y'),
                'channel' => (string) $signature->channel,
                'declared' => $signature->isDeclaredByOperator(),
                'subject' => (string) $signature->subject_type,
                'pdf_url' => route('api.v1.me.waiver.pdf', ['signature' => $signature->getKey()]),
            ])
            ->values()
            ->all();

        return [
            'mode' => $status->mode,
            // S-2 (`#181`): la aceptación marcada en el alta que espera al correo verificado.
            'pending' => $user->waiver_pending_document_id !== null,
            'signed' => $status->signed,
            'outdated' => $status->isOutdated(),
            'accepted_at' => $status->acceptedAt?->toIso8601String(),
            'accepted_label' => $status->acceptedAt !== null ? DisplayTime::format($status->acceptedAt, 'd/m/Y') : null,
            'version' => $status->version,
            'current_document_id' => $current?->getKey(),
            'signatures' => $signatures,
        ];
    }
}
