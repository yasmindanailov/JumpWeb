<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `#320` (`[DECIDIDO owner]`) — **el rol `puerta` no navega el panel: aterriza en su pantalla.**
 *
 * «Crear un rol solo para la puerta, más simple, y que solo entre a la página de la puerta con el
 * mismo login.» Es el puesto de la entrada: valida quién pasa y nada más.
 *
 * ## Por qué un middleware y no `canAccessPanel()`
 *
 * La respuesta obvia sería que `User::canAccessPanel()` dejara fuera a `puerta`. **No se puede**, y es
 * la medición que decidió el diseño: la página de login de Filament comprueba `canAccessPanel()`
 * DENTRO de `authenticate()`, y si es falsa hace `logout()` y falla la validación con «estas
 * credenciales no coinciden». Cerrar el panel por ahí le cierra también el LOGIN, y el encargo dice
 * expresamente «con el mismo login». Así que entra por `/admin/login` como todo el equipo y esto lo
 * devuelve a su sitio en cuanto pisa cualquier ruta del panel.
 *
 * Es una REDIRECCIÓN y no un 403 a propósito: no se le deniega algo que buscaba, es que su sitio es
 * otro. Un 403 en la tablet de la entrada, a mitad de una cola, no le dice nada al operario.
 *
 * ## Esto no es la autorización, y no pretende serlo
 *
 * «Ocultar no es autorizar» (`#223`): lo que este rol puede HACER lo siguen decidiendo sus permisos,
 * y trae solo los dos de su puesto ({@see PermissionSeeder::PUERTA_DEFAULT_PERMISSIONS}).
 * Sin permisos de panel no abriría ninguna pantalla aunque este middleware desapareciera; con él, ni
 * siquiera llega a que se lo pregunten. Son dos capas, y la de abajo es la que manda.
 *
 * ⚠️ **`staff` NO pasa por aquí.** El empleado de mostrador sigue operando el panel con sus trece
 * permisos: este rol no lo sustituye, se le pone al lado. Y `admin` tampoco, evidentemente — si
 * alguien acumulara los dos roles, manda el de más alcance.
 */
class RestrictsPuertaRole
{
    /** El rol que este middleware acota. Su sitio es {@see RoleSeeder}. */
    public const ROLE = 'puerta';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        // Sin sesión no hay rol que mirar: responde el `auth` de Filament (login), no esto.
        if ($user === null || ! $user->hasRole(self::ROLE)) {
            return $next($request);
        }

        // Un rol de MÁS alcance gana: acumular «puerta» no puede degradar a un encargado.
        foreach (['admin', 'staff'] as $wider) {
            if ($user->hasRole($wider)) {
                return $next($request);
            }
        }

        return redirect()->route('admin.puerta.validar');
    }
}
