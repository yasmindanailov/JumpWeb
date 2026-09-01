<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de las zonas privadas de `/admin/*`: solo el EQUIPO continúa, y quién es equipo lo dice
 * {@see User::PANEL_ROLES}.
 *
 * Defense in depth junto a `User::canAccessPanel()` (gate canónico de Filament).
 * Ver `docs/PLAN-FASE-7-PANEL.md` §1.3 y `docs/DECISIONES.md` #118.
 *
 * - Sin sesión → 302 al login del panel (lo gestiona el middleware `auth` que
 *   acompaña a este).
 * - Sesión sin rol de equipo → 403 (sin redirección a otro panel; no hay
 *   "panel de cliente": el customer usa la web pública).
 *
 * ⚠️ **Se llamaba `RequiresStaffOrAdmin` y el alias era `staff_or_admin`** (`#320`). El nombre
 * enumeraba los dos roles que había, así que al nacer el tercero —`puerta`— pasaba a MENTIR sobre su
 * propia cobertura, y en código de autorización eso se paga: quien leyera `staff_or_admin` en la ruta
 * de la hoja de sala concluiría que el rol de puerta no llega, cuando quien lo frena ahí es su falta
 * de permiso, no este middleware. Lo que cubre es «tener rol de panel», y así se llama.
 */
class RequiresPanelRole
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

        // `#320`: la lista es `User::PANEL_ROLES`, no dos nombres escritos aquí. Era una SEGUNDA copia
        // —el gate canónico `canAccessPanel()` ya recorría esa constante— y al nacer el rol `puerta`
        // habría rechazado en su propia pantalla a quien sí puede autenticarse. Un middleware y un
        // gate que responden distinto a la misma pregunta es el fallo que esto cierra.
        //
        // Que este rol NO navegue el panel no lo decide este middleware sino `RestrictsPuertaRole`, y
        // lo que puede HACER en cada superficie lo siguen decidiendo sus permisos: la hoja de sala
        // exige `orders.view` y el resumen del día `calendar.view`, que el rol `puerta` no trae.
        foreach (User::PANEL_ROLES as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
