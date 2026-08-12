<?php

namespace App\Http\Controllers;

use App\Models\CookieConsentLog;
use App\Support\CookieConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registra la decisión de consentimiento de cookies del visitante (#219, `docs/PLAN-COOKIES.md`).
 *
 * Controlador PLANO (no Livewire) → funciona para visitantes ANÓNIMOS sin fricción. Lo llama el
 * banner por `fetch` (CSRF por cabecera `X-CSRF-TOKEN` desde el `<meta>`). Hace dos cosas:
 *   1) Escribe la cookie canónica `jj_cookie_consent` (sin cifrar; la lee el servidor y Alpine).
 *   2) Deja una fila de PRUEBA en `cookie_consent_logs` (acreditación, RGPD art. 5.2/7.1).
 */
class CookieConsentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'maps' => ['required', 'boolean'],
            'social' => ['required', 'boolean'],
        ]);

        $cats = [
            'maps' => (bool) $validated['maps'],
            'social' => (bool) $validated['social'],
        ];

        CookieConsentLog::create([
            'user_id' => $request->user()?->id,
            'categories' => $cats,
            'version' => CookieConsent::POLICY_VERSION,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'accepted_at' => now(),
        ]);

        // Mismos atributos de ámbito/seguridad que la cookie de sesión, pero httpOnly=false (Alpine
        // la lee) y SameSite=Lax. La vida = 24 meses (máximo de la Guía AEPD).
        $cookie = cookie(
            CookieConsent::COOKIE_NAME,
            CookieConsent::encode($cats),
            CookieConsent::LIFETIME_MINUTES,
            config('session.path') ?? '/',
            config('session.domain'),
            (bool) config('session.secure'),
            false,   // httpOnly
            false,   // raw
            'lax',
        );

        return response()->json(['ok' => true])->withCookie($cookie);
    }
}
