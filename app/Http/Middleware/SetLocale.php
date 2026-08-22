<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Services\SiteLocales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolución del idioma activo, con auto-detección en primera visita.
 *
 * Prioridad:
 *   1) `session('locale')` — el usuario ya eligió manualmente (o ya se autodetectó antes).
 *      Una vez fijado, NO se vuelve a tocar — la elección manual del visitante es sagrada.
 *   2) `users.locale` — si el visitante está autenticado y su perfil tiene idioma soportado,
 *      lo aplicamos y lo persistimos en sesión.
 *   3) `Accept-Language` del navegador — auto-detección en primera visita (nuevo, 2026-05-26):
 *      usamos el match nativo de Symfony `getPreferredLanguage(SUPPORTED)`. Se persiste en
 *      sesión para no recalcular en cada request.
 *   4) `config('app.locale')` — fallback final.
 *
 * El usuario puede cambiarlo manualmente desde la ruta `lang.switch` (footer/drawer móvil);
 * esa elección guarda en sesión y a partir de entonces gana sobre todo lo demás.
 */
class SetLocale
{
    /**
     * ⚠️ **La lista vive en `Platform\Services\SiteLocales` desde la tanda 2**: qué idiomas habla el
     * sitio es configuración de la instalación, no una regla de HTTP, y la necesita también el
     * dominio —la validación del perfil—. Esta constante se conserva como ALIAS para no romper a
     * quien la nombre, pero su valor sale de allí.
     */
    public const SUPPORTED = SiteLocales::SUPPORTED;

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        // 1) Elección previa (manual o auto-detect anterior). Sagrada.
        if ($sessionLocale = session('locale')) {
            return $sessionLocale;
        }

        // 2) Usuario autenticado con idioma en perfil → ese, y persistimos en sesión.
        $user = $request->user();
        if ($user && in_array($user->locale, self::SUPPORTED, true)) {
            session(['locale' => $user->locale]);

            return $user->locale;
        }

        // 3) Auto-detección por `Accept-Language` (primera visita). Symfony hace el matching
        //    y devuelve el primero soportado por preferencia del navegador. Si NO hay header,
        //    devuelve el primero de SUPPORTED — ese caso queda como fallback implícito.
        if ($request->header('Accept-Language')) {
            $detected = $request->getPreferredLanguage(self::SUPPORTED);
            if (in_array($detected, self::SUPPORTED, true)) {
                session(['locale' => $detected]);

                return $detected;
            }
        }

        // 4) Fallback.
        return config('app.locale');
    }
}
