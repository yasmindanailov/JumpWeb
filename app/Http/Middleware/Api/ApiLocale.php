<?php

namespace App\Http\Middleware\Api;

use App\Http\Middleware\SetLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fase 3 · paso 0 — resolución de idioma para `/api/v1`, **sin escribir en sesión** (spec §4.7).
 *
 * `SetLocale` (web) no era reutilizable tal cual porque PERSISTE la elección en la sesión: en una
 * API eso es estado de servidor que un cliente Bearer no tiene y no debería crear. Aquí solo se
 * LEE. La lista de idiomas soportados sigue siendo una, la de `SetLocale::SUPPORTED`: dos listas
 * que puedan divergir es exactamente el bug que la web y la API no pueden permitirse.
 *
 * Prioridad, y el porqué de cada escalón:
 *  1. **Sesión (solo lectura)** — la SPA de Fase 4 vive en el mismo dominio y comparte sesión con
 *     la web (Sanctum stateful). Sin este escalón, un visitante que elige «FR» en el pie vería la
 *     landing en francés y los datos del sidebar en otro idioma: la misma pantalla, dos idiomas.
 *     Es el escalón que da coherencia SSR ↔ API, y es la traducción a stateless de la regla «la
 *     elección manual del visitante es sagrada» de `SetLocale`.
 *  2. **`Accept-Language`** — el mecanismo estándar de negociación, y el único que tiene un
 *     cliente sin sesión (la app móvil de Fase 6). Se resuelve con el match nativo de Symfony,
 *     igual que en web.
 *  3. **`users.locale`** — preferencia persistida del perfil, para un cliente que no dice nada.
 *     Solo alcanzable con sesión (el guard de ruta aún no ha corrido aquí): un cliente Bearer que
 *     quiera un idioma concreto lo pide por `Accept-Language`, que es para lo que existe.
 *  4. `config('app.locale')`.
 *
 * ⚠️ Debe ir DESPUÉS del middleware stateful de Sanctum en el grupo `api`, o el escalón 1 nunca
 * vería la sesión. El orden está fijado —y comentado— en `bootstrap/app.php`.
 */
class ApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        // 1) Elección del visitante, si esta petición trae sesión (SPA en el mismo dominio).
        if ($request->hasSession()) {
            $sessionLocale = $request->session()->get('locale');
            if (is_string($sessionLocale) && $this->isSupported($sessionLocale)) {
                return $sessionLocale;
            }
        }

        // 2) Negociación de contenido HTTP. `getPreferredLanguage` devuelve el primero de la lista
        //    cuando no hay cabecera, así que solo se consulta si el cliente la ha enviado de verdad.
        if ($request->header('Accept-Language')) {
            $detected = $request->getPreferredLanguage(SetLocale::SUPPORTED);
            if (is_string($detected) && $this->isSupported($detected)) {
                return $detected;
            }
        }

        // 3) Preferencia del perfil, para el cliente que no pide nada.
        $user = $request->user();
        if ($user !== null && $this->isSupported((string) $user->locale)) {
            return (string) $user->locale;
        }

        return (string) config('app.locale');
    }

    private function isSupported(string $locale): bool
    {
        return in_array($locale, SetLocale::SUPPORTED, true);
    }
}
