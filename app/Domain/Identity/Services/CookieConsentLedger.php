<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\CookieConsentLog;
use App\Domain\Platform\Contracts\ConsentLedger;

/**
 * **La lectura del consentimiento vivo, desde la prueba** (T3b·2): la última fila de `cookie_consent_logs`
 * del visitante manda. Es la implementación de {@see ConsentLedger} y la única puerta por la que Platform
 * pregunta a Identity por un consentimiento de cookies.
 *
 * ⚠️ La última por `accepted_at` y, a igualdad, por `id`: dos decisiones en el mismo segundo (aceptar y
 * retirar seguidos) se ordenan por la que se escribió después.
 */
final class CookieConsentLedger implements ConsentLedger
{
    public function consentedNow(string $visitorId, string $category): ?bool
    {
        $last = CookieConsentLog::query()
            ->where('visitor_id', $visitorId)
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->first();

        if ($last === null) {
            return null;
        }

        return (bool) (($last->categories ?? [])[$category] ?? false);
    }
}
