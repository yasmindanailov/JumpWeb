<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerCards;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CustomerCardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `/api/v1/me/card` — **mi carné QR** (`specs/identidad-qr-puerta.md` §4.1, §4.5, §9.2 A·8).
 *
 *  · `GET`          — el carné activo; si el titular aún no tiene, se emite en ese momento (nace cuando
 *    hace falta, como al componer el correo de confirmación).
 *  · `POST rotate`  — rota: el viejo muere EN EL ACTO (`[DECIDIDO owner]` §4.5: sin ventana de gracia) y
 *    responde `201` con el nuevo. Un correo antiguo o una impresión dejan de valer al instante.
 *
 * Todo lo que decide vive en `Identity\Services\CustomerCards`; aquí se traduce a HTTP. Toda respuesta
 * autenticada de `/api/v1` sale con `no-store` (`RGPD-04`): el token es una credencial de puerta.
 */
class MeCardController extends Controller
{
    public function show(Request $request, CustomerCards $cards): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // ⚠️ `JsonResource` responde 201 cuando el modelo `wasRecentlyCreated`, y la primera lectura EMITE
        // el carné: un GET tiene que ser 200 siempre (el contrato lo fija), nazca o no el carné.
        return (new CustomerCardResource($cards->ensureFor($user)))
            ->response($request)
            ->setStatusCode(200);
    }

    public function rotate(Request $request, CustomerCards $cards): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new CustomerCardResource($cards->rotate($user)))
            ->response($request)
            ->setStatusCode(201);
    }
}
