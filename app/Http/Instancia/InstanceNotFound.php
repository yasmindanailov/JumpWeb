<?php

namespace App\Http\Instancia;

use App\Http\Controllers\InstancePageController;
use App\Http\PanelPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

/**
 * **LA 404 DE LA INSTANCIA** (la L4 de `#876`, `isla-y-landing-nueva.md` §4.28): si el paquete declara una página que OCUPA
 * la 404 (`'ocupa' => '404'`), un «no encontrado» de la web se pinta con ella —su cabecera, su isla y su pie, como el resto de
 * la web nueva— con estado 404 y `noindex`; si no, la 404 de siempre del producto (`errors/404`).
 *
 * Una sola mano para los dos 404 de la web: el de una dirección que no es ninguna ruta —que llega por el COMODÍN del grupo
 * `web` (`routes/web.php`), para que la página tenga sesión, idioma y cabeceras como cualquier otra— y el de dentro de una
 * ruta (`abort(404)`, un modelo que no existe).
 *
 * ⚠️ Fuera: el panel (su 404 sobria), lo que no es GET ni pide HTML y lo que lleva EXTENSIÓN —un `.png` o un `wp-login.php`
 * que no existen no merecen una página entera con sus hechos—. La API ni llega: su sobre de error (`ApiExceptionRenderer`,
 * registrado antes en `bootstrap/app.php`) la responde siempre. Y si pintarla falla, la de siempre: un error en la 404 no
 * puede volverse un 500.
 */
final class InstanceNotFound
{
    public function __construct(private InstancePages $paginas, private PageFacts $hechos) {}

    /** La 404 de la instancia, o `null` para que Laravel pinte la de siempre. */
    public function render(Request $request): ?Response
    {
        if (! self::esDeLaWeb($request) || ($pagina = $this->paginas->queOcupa('404')) === null) {
            return null;
        }

        try {
            $html = InstancePageController::pintar($pagina, $this->hechos, $request->url(), noindex: true)->render();
        } catch (Throwable $e) {
            Log::warning('instancia: la 404 del paquete no se pudo pintar y sale la del producto', ['error' => $e->getMessage()]);

            return null;
        }

        return response($html, 404);
    }

    /**
     * **El COMODÍN de la web** (`Route::fallback`, en `routes/web.php`): lo que no es ninguna ruta llega aquí ya DENTRO del
     * grupo `web` y sale con su 404, que pinta {@see render()}. ⚠️ Salvo si la dirección EXISTE con otro método —un GET a lo
     * que solo es POST: la notificación del banco, «desconectar» la ficha de Google—: eso es un 405 con su `Allow`, lo que
     * Laravel deja de decir en cuanto hay un comodín (medido: lo cazaron `RedsysNotificationEndpointTest` y
     * `GoogleBusinessDisconnectTest`).
     */
    public function comodin(Request $request): never
    {
        $otros = array_values(array_filter(['POST', 'PUT', 'PATCH', 'DELETE'], function (string $metodo) use ($request): bool {
            try {
                app('router')->getRoutes()->match(Request::create($request->getPathInfo(), $metodo));

                return true;
            } catch (Throwable) {
                return false;
            }
        }));

        if ($otros !== []) {
            throw new MethodNotAllowedHttpException($otros);
        }

        abort(404);
    }

    /** ¿Es una página de la web la que no se encontró? */
    public static function esDeLaWeb(Request $request): bool
    {
        return in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && ! PanelPath::matches($request)
            && ! $request->expectsJson()
            && pathinfo($request->path(), PATHINFO_EXTENSION) === '';
    }
}
