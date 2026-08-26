<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverSettings;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WaiverDocumentResource;

/**
 * `GET /api/v1/legal/waiver` — **el texto firmable vigente** del waiver, en el idioma negociado
 * (`specs/waiver-probatorio.md` §4.2, §4.4).
 *
 * Público a propósito: quien se está dando de alta todavía no tiene sesión y es justo cuando lo
 * necesita. Publica el SNAPSHOT (`legal_document_versions`), nunca la página del CMS: lo que se
 * enseña es exactamente lo que se firma, con su identificador. Sin versión publicada —o fuera del
 * modo `interno`— `document` es `null`, y eso no es un error (§8.1).
 *
 * El idioma lo resuelve `ApiLocale`; el respaldo entre idiomas es el de `LegalDocuments::current()`,
 * el mismo que aplica la web al pintar el texto.
 */
class LegalWaiverController extends Controller
{
    public function show(): WaiverDocumentResource
    {
        return new WaiverDocumentResource([
            'mode' => WaiverSettings::mode(),
            'document' => WaiverSettings::isInternal()
                ? LegalDocuments::current(WaiverSettings::SLUG, app()->getLocale())
                : null,
        ]);
    }
}
