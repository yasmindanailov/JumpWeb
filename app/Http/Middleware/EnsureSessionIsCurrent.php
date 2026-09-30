<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Services\SessionBinding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **Una sesión que su cuenta ya no reconoce, fuera** (`SessionBinding`, A2a de `specs/acceso-con-codigo.md` §4.9,
 * `DECISIONES #855`, `RGPD-06`): «cerrar las demás sesiones» y la palanca de «me han entrado» rotan o vacían el token de
 * la cuenta, y la sesión de otro dispositivo, atada al viejo, se cierra aquí en su próxima petición —con cualquier
 * driver de sesión, también el `redis` de producción—.
 *
 * ANTES de atender la petición: una ruta con `auth:sanctum` ya no encuentra a nadie (401) y una página de la web se pinta
 * como de un visitante. En la web y en la API con sesión; una petición por Bearer no tiene sesión que mirar.
 */
class EnsureSessionIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            SessionBinding::enforce($request);
        }

        return $next($request);
    }
}
