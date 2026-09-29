<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Services\RememberedDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **«Recordado 90 días SIN USO»** (`DECISIONES #848`·3): cada página de la web que llega con la cookie de recuerdo del
 * titular dentro la devuelve con 90 días más ({@see RememberedDevice::refresh()}). Sin esto la cookie caducaría a los 90
 * días de ENTRAR, aunque se usara a diario.
 *
 * ⚠️ DESPUÉS de atender la petición: si esta misma petición entró o salió, la cookie ya está decidida y manda eso. Solo
 * en el grupo `web`; el panel tiene su propio guard y su propio recuerdo (`SEC-14`).
 */
class RefreshRememberedDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        RememberedDevice::refresh($request);

        return $response;
    }
}
