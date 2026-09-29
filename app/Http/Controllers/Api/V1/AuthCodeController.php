<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Services\EmailCodeLogin;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * **LA PUERTA del acceso con código** (A1 de `docs/specs/acceso-con-codigo.md` §4.4, `DECISIONES #849`): el cliente
 * escribe su correo y la respuesta dice por dónde sigue —`code` (tiene cuenta: su código va de camino) o `register` (es
 * nuevo: el alta, sin código)—. El código se escribe después en `POST /auth/login` (la sesión) o en `POST /auth/tokens`
 * (la app).
 *
 * Como sus hermanos, **no decide nada**: los límites, la existencia de la cuenta y el envío son de
 * `Identity\Services\EmailCodeLogin`. Aquí se valida la forma y se traduce el veredicto.
 *
 * ⚠️ Sin sesión a propósito: la usan también la app nativa, que no la tiene, y la pantalla que aún no ha entrado.
 */
class AuthCodeController extends Controller
{
    public function request(Request $request, EmailCodeLogin $login): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        $result = $login->request($data['email'], (string) $request->ip());

        if ($result->limited) {
            // `next` solo cuando el tope fue el del correo: esa cuenta tiene un código recién enviado y la pantalla puede
            // seguir a escribirlo en vez de quedarse parada en la puerta.
            return ApiErrorResponse::make(
                ApiErrorCode::TooManyRequests,
                429,
                params: array_filter(['retry_after' => $result->retryAfter, 'next' => $result->next]),
                headers: ['Retry-After' => (string) $result->retryAfter],
            );
        }

        return response()->json(['next' => $result->next]);
    }
}
