<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Services\Analytics\AttributionContext;
use App\Domain\Platform\Services\Analytics\Visitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **Deja la petición en el contexto de atribución y, en la web, acuña la cookie del visitante**
 * (`docs/specs/analitica.md` §4.1).
 *
 * ⚠️ No consulta nada: `AttributionContext::resolveFrom()` solo lee la cookie y guarda la petición; la sesión
 * se resuelve cuando alguien la pide (el sello de un pedido). Va en el grupo `api` sin que `ApiOverheadTest`
 * se entere.
 *
 * ⚠️ **La cookie solo se acuña con `mint`** (grupo `web`): una respuesta pública y cacheable de la API —el
 * arranque del cajón— no puede llevar un `Set-Cookie` por visitante (spec §7.1, producto-5). La API la acuña
 * únicamente en el `202` de `POST /events`, que es `no-store`. Y no se acuña en el panel: el equipo no es
 * audiencia.
 */
final class ResolveVisitor
{
    public const MINT = 'mint';

    public function __construct(private readonly AttributionContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'read'): Response
    {
        $this->context->resolveFrom($request);

        $response = $next($request);

        if ($mode === self::MINT && Visitor::fromRequest($request) === null && ! $request->is('admin', 'admin/*')) {
            $response->headers->setCookie(Visitor::cookie(Visitor::mint(), $request));
        }

        return $response;
    }
}
