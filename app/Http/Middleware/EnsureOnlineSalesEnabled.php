<?php

namespace App\Http\Middleware;

use App\Domain\Payments\Services\PaymentSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **La compra ONLINE puede estar cerrada, y quien lo dice es el SERVIDOR.**
 *
 * Lanzamiento del 2026-09-01: sin claves de Redsys de producción, el catálogo se enseña pero no se
 * puede comprar. Los CTA del Blade pasan a `tel:` cuando `sales.online_enabled = 0`, pero eso es
 * presentación: la verdad vive aquí, delante de las cuatro rutas que hacen avanzar una compra
 * (presupuesto, validar línea, crear pedido, abrir el pago). Sin este suelo, cualquiera con la SPA
 * cargada seguiría creando pedidos contra una pasarela de pruebas.
 *
 * 503 y no 403: no es un problema de permisos del cliente, es que el servicio no está disponible.
 */
class EnsureOnlineSalesEnabled
{
    public const CODE = 'online_sales_disabled';

    public function handle(Request $request, Closure $next): Response
    {
        if (! PaymentSettings::onlineSalesEnabled()) {
            return response()->json([
                'message' => __('api.errors.online_sales_disabled'),
                'code' => self::CODE,
            ], 503);
        }

        return $next($request);
    }
}
