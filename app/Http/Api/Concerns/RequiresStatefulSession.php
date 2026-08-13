<?php

namespace App\Http\Api\Concerns;

use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 3c — la guarda para los endpoints de `/api/v1` que necesitan SESIÓN.
 *
 * `EnsureFrontendRequestsAreStateful` solo monta `StartSession` cuando la petición viene de un
 * origen declarado *stateful* (`Origin`/`Referer` en `config/sanctum.php`). Un navegador los envía
 * siempre; un `curl`, una app mal configurada o una integración a medio hacer, no. En ese caso
 * `$request->session()` lanza «Session store not set on request» y el endpoint responde **500** a
 * un problema de integración perfectamente diagnosticable.
 *
 * Se centraliza porque ya pasó DOS veces: primero en el login del paso 3b y otra vez en el alta
 * dentro de la compra, donde además era peor —la cuenta se creaba y el 500 llegaba después—. Las
 * dos las encontró un `curl` contra el servidor real, no la suite: los tests mandan siempre el
 * encabezado, así que ejercitan la rama buena.
 *
 * **Comprobar ANTES de hacer nada**: la respuesta es la misma se valide o no la petición, y así no
 * se gastan intentos del limitador ni se crean cuentas que luego no se pueden usar.
 */
trait RequiresStatefulSession
{
    /**
     * Devuelve la respuesta de error si la petición no puede abrir sesión, o `null` si sí puede.
     *
     * Es 400 y no 401: no falta identidad, falta que el cliente hable el modo SPA. El mensaje sale
     * del catálogo i18n como todos (spec §4.3).
     */
    protected function requireSession(Request $request): ?JsonResponse
    {
        return $request->hasSession()
            ? null
            : ApiErrorResponse::make(ApiErrorCode::BadRequest, 400);
    }
}
