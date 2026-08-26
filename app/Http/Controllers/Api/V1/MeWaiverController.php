<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Exceptions\WaiverNotInternalException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverProof;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Services\AuditLogger;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WaiverStatusResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/api/v1/me/waiver` — **mi waiver** (`specs/waiver-probatorio.md` §4.4, §4.5, §4.8).
 *
 *  · `GET`  — el estado según el modo, y mis firmas con su PDF.
 *  · `POST` — ACEPTAR el texto vigente. La petición trae el `document_id` que `GET /legal/waiver`
 *    sirvió; si el texto se publicó de nuevo entre medias, `409 waiver_document_stale` y el cliente
 *    vuelve a leerlo. Fuera del modo interno, `409 waiver_not_internal`. Es la re-firma «en el
 *    siguiente momento natural» del §4.8: el cajón la ofrece al entrar o al comprar, nunca el mostrador.
 *  · `GET {signature}/pdf` — el PDF de una firma PROPIA (§4.5: «la zona de privacidad del cajón,
 *    para el titular»). Scoping por el guard + 404 si no es suya; se audita como cualquier otra
 *    consulta del registro. `no-store` lo pone el grupo (`RGPD-04`).
 *
 * El canal de la firma sale de cómo se autenticó la petición: Bearer → `api` (app nativa); cookie
 * de sesión → `web` (el cajón).
 */
class MeWaiverController extends Controller
{
    public function show(Request $request): WaiverStatusResource
    {
        /** @var User $user */
        $user = $request->user();

        return new WaiverStatusResource($user);
    }

    public function store(Request $request, WaiverAcceptance $acceptance): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'document_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $acceptance->accept($user, (int) $data['document_id'], $this->signatureRequest($request));
        } catch (WaiverNotInternalException) {
            return ApiErrorResponse::make(ApiErrorCode::WaiverNotInternal, 409);
        } catch (WaiverDocumentStaleException) {
            return ApiErrorResponse::make(ApiErrorCode::WaiverDocumentStale, 409);
        }

        return (new WaiverStatusResource($user->fresh()))->response($request)->setStatusCode(201);
    }

    public function pdf(Request $request, WaiverSignature $signature): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Scoping por el guard: una firma ajena no existe para este titular.
        abort_unless((int) $signature->user_id === (int) $user->getKey(), 404);

        $proof = WaiverProof::make($signature);
        App::setLocale($proof->locale());

        AuditLogger::log('waiver.proof_downloaded', $signature, [
            'signature_id' => $signature->getKey(),
            'user_id' => $user->getKey(),
            'version' => $proof->version->version,
            'locale' => $proof->locale(),
            'integrity_ok' => $proof->integrityOk(),
            'by_holder' => true,
        ]);

        $pdf = Pdf::loadView('pdf.waiver-proof', ['proof' => $proof])->setPaper('a4');

        return $pdf->stream("waiver-{$signature->getKey()}.pdf");
    }

    private function signatureRequest(Request $request): WaiverSignatureRequest
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        // El canal sale de CÓMO se autenticó la petición, no de una cabecera que el cliente pueda
        // añadir. Con cookie de sesión, el guard de Sanctum resuelve al usuario SIN mirar el
        // `Authorization` y le deja un `TransientToken`; solo un token personal real —la app nativa—
        // es `PersonalAccessToken`. Revisión `#169` §10.2·1: con `bearerToken() !== null` bastaba
        // `Bearer basura` junto a la cookie para que la firma constara como `api`.
        return $request->user()?->currentAccessToken() instanceof PersonalAccessToken
            ? WaiverSignatureRequest::api($ip, $userAgent)
            : WaiverSignatureRequest::web($ip, $userAgent);
    }
}
