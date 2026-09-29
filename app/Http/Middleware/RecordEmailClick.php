<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Services\Analytics\EmailClicks;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Domain\Platform\Services\Analytics\Recorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
 *
 * ▶ **Y el clic de CADA envío** (`specs/correos-salientes.md` §4.8, la C2): si la URL trae la marca del envío
 * (`jw_e`), `EmailClicks` apunta la visita y la respuesta es un **302 a la MISMA URL sin la marca**. Recargar no
 * cuenta otra vez, y la barra de direcciones o un enlace compartido no llevan la marca. El navegador conserva el
 * `#fragmento` y la firma sigue valiendo, porque `jw_e` es de las claves ignoradas.
 * ⚠️⚠️ Apuntar NUNCA impide abrir la página: si falla, solo queda una línea en el registro y la redirección sale igual.
 */
final class RecordEmailClick
{
    private const SESSION_KEY = 'analytics.email_clicked';

    public function __construct(
        private readonly Recorder $recorder,
        private readonly EmailClicks $clicks,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $request->is('admin', 'admin/*')) {
            return $next($request);
        }

        if ($request->query('utm_source') === EmailUtm::SOURCE) {
            $this->record($request);
        }

        if ($request->query->has(EmailUtm::MARK)) {
            $this->recordClick($request);

            $clean = $this->withoutMark($request);

            // Solo si de verdad se quitó algo: una clave que PHP lee como `jw_e` sin escribirse así (`jw.e`) no
            // desaparece de la query cruda, y redirigir a la misma URL sería un bucle.
            if ($clean !== null) {
                return redirect()->to($clean);
            }
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

    private function recordClick(Request $request): void
    {
        $mark = $request->query(EmailUtm::MARK);

        if (! is_string($mark) || ! Str::isUuid($mark)) {
            return;
        }

        try {
            $this->clicks->record($mark, $request->getPathInfo(), $request->userAgent());
        } catch (Throwable $e) {
            Log::warning('email_clicks.record_failed', ['error' => $e::class]);
        }
    }

    /**
     * La misma URL sin la marca, sobre la ruta y la query CRUDAS: ni se reordena ni se recodifica nada, porque la firma de
     * un enlace firmado se calcula sobre ese texto tal cual. `null` si no había nada que quitar.
     */
    private function withoutMark(Request $request): ?string
    {
        $raw = (string) $request->server->get('QUERY_STRING');
        $query = implode('&', array_filter(
            explode('&', $raw),
            static fn (string $part): bool => urldecode(Str::before($part, '=')) !== EmailUtm::MARK,
        ));

        if ($query === $raw) {
            return null;
        }

        return $request->getSchemeAndHttpHost().Str::before($request->getRequestUri(), '?').($query === '' ? '' : '?'.$query);
    }
}
