<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Services\WaiverStatus;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 6 · menores a cargo — una persona a cargo, vista por su titular
 * (`specs/menores-a-cargo.md` §4.1, §4.2).
 *
 * La edad y `is_minor` se DERIVAN aquí de `born_on` con el «hoy» del parque: no hay columna que
 * pueda quedarse vieja. `adult_from` es el día en que deja de estar cubierto por el waiver del
 * adulto (§4.1): la lista lo marca, nadie lo borra.
 *
 * `waiver` es SU estado (§4.3: su propia cadena de firmas, nunca el sello del titular): `signed`,
 * `outdated` cuando el texto cambió, y la firma que responde con su PDF. ⚠️ No lleva el
 * `current_document_id`: es el mismo texto vigente que publican `GET /legal/waiver` y
 * `GET /me/waiver` — dos caminos, un dato.
 *
 * ⚠️ Es la vista del TITULAR. La pantalla de puerta (subsistema A) no reutiliza este recurso: allí
 * el nombre no viaja nunca (§2, «jamás el nombre»).
 *
 * @property-read Dependent $resource
 */
class DependentResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $dependent = $this->resource;
        $today = DisplayTime::today();
        $waiver = WaiverStatus::forDependent($dependent);

        return [
            'id' => (int) $dependent->getKey(),
            'name' => (string) $dependent->name,
            'born_on' => $dependent->born_on->toDateString(),
            'age' => $dependent->ageOn($today),
            'is_minor' => $dependent->isMinorOn($today),
            'adult_from' => $dependent->adultFrom()->toDateString(),
            'waiver' => [
                'mode' => $waiver->mode,
                'signed' => $waiver->signed,
                'outdated' => $waiver->isOutdated(),
                'accepted_at' => $waiver->acceptedAt?->toIso8601String(),
                'accepted_label' => $waiver->acceptedAt !== null ? DisplayTime::format($waiver->acceptedAt, 'd/m/Y') : null,
                'version' => $waiver->version,
                'signature_id' => $waiver->signatureId,
                'pdf_url' => $waiver->signatureId !== null
                    ? route('api.v1.me.waiver.pdf', ['signature' => $waiver->signatureId])
                    : null,
            ],
        ];
    }
}
