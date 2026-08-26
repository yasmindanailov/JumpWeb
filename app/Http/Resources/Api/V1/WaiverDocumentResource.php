<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Models\LegalDocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 6 · waiver — **el texto firmable VIGENTE**, en el idioma negociado, con el identificador que
 * el cliente tiene que devolver al aceptar (`specs/waiver-probatorio.md` §4.4). Es lo que pintan la
 * casilla del alta y la pantalla de firma.
 *
 * `document` es `null` cuando no hay nada que firmar aquí: modo distinto de `interno` o ninguna
 * versión publicada todavía. Un `null` es una respuesta legítima, no un error (§8.1: la maquinaria
 * existe antes que el texto definitivo).
 *
 * @property-read array{mode: string, document: ?LegalDocumentVersion} $resource
 */
class WaiverDocumentResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $document = $this->resource['document'];

        return [
            'mode' => (string) $this->resource['mode'],
            'document' => $document === null ? null : [
                'id' => (int) $document->getKey(),
                'version' => (int) $document->version,
                'locale' => (string) $document->locale,
                'title' => (string) $document->title,
                'sections' => $document->sections(),
                'published_at' => $document->published_at?->toIso8601String(),
            ],
        ];
    }
}
