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
 *
 * ⚠️ **Y se acuña ANTES de componer la página, no después** (T5a, spec §4.4). Hasta la T5 el id nuevo solo
 * viajaba en el `Set-Cookie` de la respuesta y la petición se resolvía SIN visitante; la variante de un
 * experimento tiene que estar en la primera vista —si no, todo el mundo estrena la web con la variante de
 * control—, así que el id se acuña primero, se deja en la petición (`Visitor::ATTRIBUTE`) y la cookie de la
 * respuesta lleva ESE mismo id. Sigue sin consultar nada: acuñar es generar un ULID.
 */
final class ResolveVisitor
{
    public const MINT = 'mint';

    public function __construct(private readonly AttributionContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'read'): Response
    {
        $minted = null;

        if ($mode === self::MINT && Visitor::fromRequest($request) === null && ! $request->is('admin', 'admin/*')) {
            $minted = Visitor::mint();
            $request->attributes->set(Visitor::ATTRIBUTE, $minted);
        }

        $this->context->resolveFrom($request);

        $response = $next($request);

        if ($minted !== null) {
            $response->headers->setCookie(Visitor::cookie($minted, $request));
        }

        return $response;
    }
}
