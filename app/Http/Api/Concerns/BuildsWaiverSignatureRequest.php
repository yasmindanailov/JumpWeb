<?php

namespace App\Http\Api\Concerns;

use App\Domain\Identity\Services\WaiverSignatureRequest;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Fase 6 · waiver — lo que la capa de entrega sabe de una aceptación y el dominio no puede adivinar
 * (`specs/waiver-probatorio.md` §4.3): canal, ip y user-agent. Lo comparten las dos puertas que
 * firman —`POST /me/waiver` y `POST /me/dependents/{id}/waiver`— para que el canal se decida UNA vez.
 */
trait BuildsWaiverSignatureRequest
{
    protected function signatureRequest(Request $request): WaiverSignatureRequest
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
