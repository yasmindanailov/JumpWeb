<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Platform\Services\QrCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CustomerCardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    /**
     * El carné como IMAGEN (§9.6 B·1): **los MISMOS bytes que el adjunto del correo** (`QrCode::png()`).
     * El cajón lo pinta en un `<img>` del mismo origen —la cookie viaja, y Sanctum trata la petición
     * como *stateful* por el `Referer`— y lo ofrece como descarga. Se eligió frente a un codificador de
     * QR en el navegador por tres razones medidas: el chunk del cajón está a 0,36 KiB de su techo, un
     * solo dibujo para correo/web/app, y la CSP del sitio ya admite `img-src 'self'`.
     *
     * Con la clave de cifrado rotada (§8.1) no hay nada que dibujar: **404**. El JSON ya dice
     * `token: null` y ninguna pantalla pide la imagen en ese estado; el titular rota el carné y listo.
     * `no-store` lo pone el grupo (`RGPD-04`): es una credencial de puerta. No se audita: es la misma
     * re-lectura que `GET /me/card`, y el token nunca se escribe (`RGPD-02`).
     */
    public function png(Request $request, CustomerCards $cards): Response
    {
        /** @var User $user */
        $user = $request->user();

        $token = $cards->ensureFor($user)->plainToken();

        abort_if($token === null, 404);

        return response(QrCode::png($token), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="carne-qr.png"',
        ]);
    }
}
