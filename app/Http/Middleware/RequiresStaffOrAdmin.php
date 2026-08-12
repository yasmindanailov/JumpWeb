<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware del panel admin: solo `admin` y `staff` pueden continuar.
 *
 * Defense in depth junto a `User::canAccessPanel()` (gate canónico de Filament).
 * Ver `docs/PLAN-FASE-7-PANEL.md` §1.3 y `docs/DECISIONES.md` #118.
 *
 * - Sin sesión → 302 al login del panel (lo gestiona el middleware `auth` que
 *   acompaña a este).
 * - Sesión sin rol admin/staff → 403 (sin redirección a otro panel; no hay
 *   "panel de cliente": el customer usa la web pública).
 */
class RequiresStaffOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        // El middleware `auth` debe encadenarse delante; aquí asumimos sesión activa.
        // Si por error de configuración no fuera así, denegar igualmente.
        if (! $user) {
            abort(401);
        }

        if (! $user->hasRole('admin') && ! $user->hasRole('staff')) {
            abort(403);
        }

        return $next($request);
    }
}
