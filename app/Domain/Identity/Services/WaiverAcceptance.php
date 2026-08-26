<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Exceptions\WaiverNotInternalException;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;

/**
 * Fase 6 · waiver — la ACEPTACIÓN por el titular (`specs/waiver-probatorio.md` §4.4): la única regla
 * que las dos puertas —el alta y `POST /me/waiver`— tienen que compartir y no copiar.
 *
 *  · Solo en modo `interno` (§4.1): fuera de él no hay texto que firmar aquí.
 *  · Solo con el identificador de la versión VIGENTE. «La versión que el servidor sirvió» es, por
 *    definición, la vigente en el momento de servirla; si el texto se publicó de nuevo entre servirlo
 *    y aceptarlo, la aceptación **no vale para el texto nuevo** y se rechaza para que el cliente
 *    vuelva a leerlo. Aceptar un texto ya superado a sabiendas no tendría sentido probatorio.
 *
 * Firmar lo hace `WaiverSigner`; aquí solo se decide si se puede.
 */
final class WaiverAcceptance
{
    public function __construct(private readonly WaiverSigner $signer) {}

    /**
     * La versión que el cliente dice haber visto, SI es la vigente del waiver; `null` si no existe,
     * no es del waiver o el texto cambió desde entonces.
     */
    public static function currentDocument(int $documentId): ?LegalDocumentVersion
    {
        $document = LegalDocumentVersion::find($documentId);
        if ($document === null || $document->slug !== WaiverSettings::SLUG) {
            return null;
        }

        return (int) $document->version === LegalDocuments::latestVersionNumber(WaiverSettings::SLUG)
            ? $document
            : null;
    }

    /**
     * @throws WaiverNotInternalException si la instalación no gestiona el waiver aquí
     * @throws WaiverDocumentStaleException si el identificador no es el de la versión vigente
     */
    public function accept(User $holder, int $documentId, WaiverSignatureRequest $request): WaiverSignature
    {
        if (! WaiverSettings::isInternal()) {
            throw new WaiverNotInternalException;
        }

        $document = self::currentDocument($documentId) ?? throw new WaiverDocumentStaleException;

        return $this->signer->sign($holder, $document, $request);
    }
}
