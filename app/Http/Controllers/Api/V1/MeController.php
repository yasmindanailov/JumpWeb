<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 0 — «quién soy». El primer endpoint de la API, y a propósito el más aburrido:
 * su trabajo es demostrar que los cimientos funcionan (enrutado, grupo de middleware, Sanctum,
 * sobre de error, contrato OpenAPI) sin meter una sola regla de negocio por delante.
 *
 * **No exige `verified`**, a diferencia de `/mi-cuenta` en web. Es deliberado: el registro
 * «pay-first» crea cuentas sin verificar, y un cliente que no puede leer su propio estado tampoco
 * puede pintar el aviso de «verifica tu correo» ni ofrecer el reenvío. El resto de `me/*`
 * (escritura de perfil, contraseña, borrado) sí lo exigirá cuando llegue, junto con la
 * reconfirmación de contraseña de `SEGURIDAD §3`.
 *
 * El scoping es implícito y por eso no hay riesgo de IDOR: la única fuente del usuario es el guard,
 * nunca un identificador de la petición.
 */
class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user);
    }
}
