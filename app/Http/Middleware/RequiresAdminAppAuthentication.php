<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **EL AUTHENTICATOR, OBLIGATORIO PARA LOS ADMINISTRADORES** (P3 de `docs/specs/panel-a-salvo.md` §4.3, `DECISIONES #847`:
 * «solo administradores»; `#851`).
 *
 * Filament evalúa su «obligatorio» al REGISTRAR las rutas —sin usuario—, así que vale para todo el panel o para nadie. Se
 * activa para el panel y ESTE middleware ocupa el sitio del suyo (`multiFactorAuthenticationRequiredMiddlewareName`): deja
 * pasar a mostrador y puerta y a un administrador con el authenticator ya configurado; a uno sin él lo manda a la página
 * donde lo configura. Va también en las trece rutas del personal de `routes/web.php` (alias `panel_mfa`): sin él, un
 * administrador sin authenticator abriría un PDF por su dirección.
 *
 * ⚠️ Lee el guard del PANEL (`SEC-14`) y la URL de SU panel por nombre: fuera de Filament no hay «panel actual».
 */
class RequiresAdminAppAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');

        // `panel.admin_mfa` es `true` y no sale del `.env`: solo la suite lo apaga (`config/panel.php`).
        if (! config('panel.admin_mfa', true)
            || ! $user instanceof User || ! $user->hasRole('admin') || filled($user->getAppAuthenticationSecret())) {
            return $next($request);
        }

        return redirect()->guest((string) Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl());
    }
}
