<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale del panel admin, AISLADO del locale de la web pública.
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §1.3 y `docs/DECISIONES.md` #123.
 *
 * La web pública soporta `es/en/fr` (decisión #40) via `SetLocale`; el panel
 * soporta solo `es/zh_CN` (decisión #123). Mantenerlos separados evita
 * interferencia: un staff chino navegando la web pública en francés vuelve
 * al panel y sigue en zh_CN.
 *
 * Estrategia:
 *  - Lee `users.panel_locale` del usuario autenticado (si es válido).
 *  - Si null/inválido → default `es`.
 *  - `Auth::user()` no resuelto aún en este middleware (Filament lo carga
 *    poco después): usamos `$request->user()` que sí lo tiene tras el stack
 *    de auth (`Authenticate` corre antes que este middleware).
 *
 * El cambio efectivo lo hace `App::setLocale()` — válido durante el ciclo
 * de la petición, no toca el config global.
 */
class SetAdminLocale
{
    /** @var array<int,string> Idiomas soportados en el panel. */
    public const SUPPORTED = ['es', 'zh_CN'];

    public const DEFAULT = 'es';

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(self::resolve($request->user()));

        return $next($request);
    }

    /**
     * Locale efectivo para este usuario (o el default si no hay preferencia válida).
     */
    public static function resolve(?User $user): string
    {
        $candidate = $user?->panel_locale;

        if (is_string($candidate) && in_array($candidate, self::SUPPORTED, true)) {
            return $candidate;
        }

        return self::DEFAULT;
    }
}
