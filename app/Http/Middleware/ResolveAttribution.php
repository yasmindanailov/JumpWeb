<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Services\Analytics\AttributionContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **Resuelve el contexto de atribución ANTES de entrar en el dominio** (`docs/specs/analitica.md` §4.1).
 *
 * Va solo en la ruta que crea pedidos: paga las dos consultas del sello (la sesión abierta y el primer
 * toque) fuera de la transacción de `OrderCreator`, para que el `creating` de `Order` copie el contexto sin
 * consultar nada bajo el lock de aforo. El controlador de pedidos es `CRITICAL_RE` y no se toca; la ruta sí.
 */
final class ResolveAttribution
{
    public function __construct(private readonly AttributionContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->resolve();

        return $next($request);
    }
}
