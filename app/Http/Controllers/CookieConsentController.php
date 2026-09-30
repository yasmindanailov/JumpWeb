<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\CookieConsentLog;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Http\Legal\CookieInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registra la decisión de consentimiento de cookies del visitante (#219, `docs/sistemas/COOKIES.md`).
 *
 * Controlador PLANO (no Livewire) → funciona para visitantes ANÓNIMOS sin fricción. Lo llama el
 * banner por `fetch` (CSRF por cabecera `X-CSRF-TOKEN` desde el `<meta>`). Hace dos cosas:
 *   1) Escribe la cookie canónica `cookie_consent` (sin cifrar; la lee el servidor y Alpine).
 *   2) Deja una fila de PRUEBA en `cookie_consent_logs` (acreditación, RGPD art. 5.2/7.1).
 *
 * ⚠️ Valida UNA clave por categoría OFRECIDA ({@see CookieInventory::offered()}, `#860`), todas obligatorias: el
 * aviso manda siempre la decisión completa, y una categoría que falte no puede darse por rechazada en silencio ni
 * por aceptada. Lo que NO se ofreció se guarda en `false` diga lo que diga la petición —nadie aceptó lo que no se le
 * preguntó— y la cookie anota lo preguntado, para volver a preguntar si se enciende algo después.
 */
class CookieConsentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $offered = CookieInventory::offered();
        $validated = $request->validate(array_fill_keys($offered, ['required', 'boolean']));

        $cats = CookieConsent::allSetTo(false);
        foreach ($offered as $category) {
            $cats[$category] = (bool) $validated[$category];
        }

        CookieConsentLog::create([
            'user_id' => $request->user()?->id,
            // T3b·2: el visitante del libro de eventos, para RELEER su consentimiento vivo desde la cola antes de
            // comunicar una compra a un anunciante. Sin cookie válida, `null`: entonces no hay conversión de servidor.
            'visitor_id' => Visitor::fromRequest($request),
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
            CookieConsent::encode($cats, $offered),
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
