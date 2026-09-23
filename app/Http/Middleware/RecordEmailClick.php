<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Domain\Platform\Services\Analytics\Recorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * **`email_clicked`: la llegada desde un correo, como hecho del servidor** (`docs/specs/analitica.md` §4.1,
 * `#678`, T1c). Va en el grupo `web` —también en las páginas de `x-focused-layout`, que son rutas web— y mira
 * lo que `EmailUtm` pega a cada enlace: `utm_source=email&utm_medium=<clave>`.
 *
 * ⚠️ **Solo claves de correos que existen** (`EmailUtm::isCustomerKey()`): un `utm_medium` tecleado en una URL
 * no fabrica un clic de un correo que nadie mandó. Y **una vez por sesión y clave**: recargar la página del
 * post-form diez veces es un clic, no diez.
 *
 * ⚠️ Va DESPUÉS de `ResolveVisitor` en el grupo: el `Recorder` ata el hecho a la sesión del visitante solo
 * con la categoría `analytics`, y para eso el contexto tiene que estar resuelto. El panel no cuenta.
 */
final class RecordEmailClick
{
    private const SESSION_KEY = 'analytics.email_clicked';

    public function __construct(private readonly Recorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->query('utm_source') === EmailUtm::SOURCE && ! $request->is('admin', 'admin/*')) {
            $this->record($request);
        }

        return $next($request);
    }

    private function record(Request $request): void
    {
        $key = $request->query('utm_medium');

        if (! is_string($key) || ! EmailUtm::isCustomerKey($key) || ! $request->hasSession()) {
            return;
        }

        $seen = (array) $request->session()->get(self::SESSION_KEY, []);

        if (in_array($key, $seen, true)) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, [...$seen, $key]);

        $userId = $request->user()?->getAuthIdentifier();

        $this->recorder->fact('email_clicked', ['key' => $key], ['user_id' => $userId === null ? null : (int) $userId]);
    }
}
