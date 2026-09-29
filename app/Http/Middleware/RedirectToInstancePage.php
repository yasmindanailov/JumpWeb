<?php

namespace App\Http\Middleware;

use App\Http\Instancia\InstancePages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **Una ruta vieja del producto que la instancia SUSTITUYE responde 301 a su página** (`#843`, T6f de
 * `docs/specs/isla-y-landing-nueva.md` §4.22).
 *
 * La landing nueva ordena la web por comprador y deja de tener `/precios`, `/atracciones`, `/servicios`, `/bar` y
 * `/contacto`; lo que Google tiene indexado de ellas no se puede perder. La página del paquete que las recoge lo
 * declara (`'sustituye' => ['contacto', 'bar']`) y aquí se responde, ANTES de que el controlador pinte: sin la vista del
 * paquete, `InstanceViews::pick()` caería al anfitrión mínimo y se publicaría una página sin arte.
 *
 * ⚠️ **301, y con su `?query`**: permanente, que es lo que traslada lo indexado a la URL nueva; y la consulta viaja entera,
 * porque las `utm_` de una campaña vieja son la atribución de esa visita.
 * ⚠️ Sin página que la sustituya —o si esa página no llegó a tener ruta— la ruta sigue pintando lo suyo: un paquete sin
 * declaración no cambia nada. La regla es UNA ({@see InstancePages::redireccionDe()}) y la lee también el sitemap.
 */
class RedirectToInstancePage
{
    public function __construct(private readonly InstancePages $paginas) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ruta = $request->route()?->getName();
        $destino = $ruta === null ? null : $this->paginas->redireccionDe($ruta);

        if ($destino === null) {
            return $next($request);
        }

        $consulta = $request->getQueryString();

        return redirect()->to($consulta === null ? $destino : $destino.'?'.$consulta, 301);
    }
}
