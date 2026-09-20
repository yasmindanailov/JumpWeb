<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Platform\Exceptions\GoogleBusinessException;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\GoogleBusinessConnector;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use App\Http\Auth\GoogleBusinessOAuthSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * **Conectar la ficha de Google**, las dos peticiones de navegador
 * (`docs/specs/google-business-profile.md` §4.2·2).
 *
 * ⚠️⚠️ **El permiso se comprueba DOS veces, y la segunda es la que cuenta.** La ruta va con `auth` y
 * `panel_role`, pero `panel_role` deja pasar a `staff` (§4.2·2), y entre la ida y la vuelta caben un
 * cambio de rol y un cambio de sesión. Quien vuelve tiene que ser **el mismo usuario** que fue y
 * **seguir teniendo `settings.manage`**: sin las dos, el permiso sobre la ficha del parque lo
 * entregaría quien no debe, y el rastro de «quién conectó» diría otra cosa.
 *
 * ⚠️ **La URI de redirección es la RUTA COMPLETA** y es la que hay que dar de alta en el cliente OAuth
 * central de JumpSystem, por instalación (§7·A·5). Con el origen pelado, el primer intento devuelve
 * `Error 400: redirect_uri_mismatch` y no hay nada que depurar en nuestro lado — el login con Google
 * ya lo pagó una vez (`specs/auth-con-google.md` §10.1).
 */
class GoogleBusinessConnectController extends Controller
{
    /** La ida: se abre el reto con PKCE y se manda al admin a Google. */
    public function connect(Request $request, GoogleBusinessOAuth $oauth): RedirectResponse
    {
        $this->authorizeSettings($request);

        if (! GoogleBusinessCredentials::fresh()->configured()) {
            // ⚠️ Aquí NO se falla hacia invisible como en el login: al otro lado hay un admin que
            // tiene que poder arreglarlo, y «sin configurar» es un estado que la pantalla explica.
            return $this->back('google-business-not-configured');
        }

        $challenge = GoogleBusinessOAuthSession::start((int) Auth::id());

        return redirect()->away(
            $oauth->authorizationUrl($challenge['state'], $challenge['verifier'], $this->callbackUrl())
        );
    }

    /**
     * La vuelta. El orden de las comprobaciones **es** la defensa:
     *
     *  1. el reto, que se consume siempre (aunque falle);
     *  2. que vuelva **el mismo usuario** y que **siga** pudiendo;
     *  3. lo que Google dice del intento (cancelado, sin código);
     *  4. el canje servidor-a-servidor con el `code_verifier`;
     *  5. y solo entonces se guarda, y se revoca el permiso anterior.
     */
    public function callback(Request $request, GoogleBusinessOAuth $oauth, GoogleBusinessConnector $connector): RedirectResponse
    {
        $this->authorizeSettings($request);

        $challenge = GoogleBusinessOAuthSession::consume($request->query('state'));

        // Sin reto válido, esta vuelta no corresponde a ninguna ida nuestra: **no se hace nada**.
        if ($challenge === null) {
            Log::warning('google_business.state_mismatch', ['ip' => $request->ip()]);

            return $this->back('google-business-failed');
        }

        // El reto anota QUIÉN lo pidió. Entre la ida y la vuelta caben un `logout` y un `login` con
        // otra cuenta —en un ordenador de oficina compartido es lo normal—, y sin esto el token de la
        // ficha quedaría anotado a nombre de quien volvió.
        if ($challenge['holder'] !== (int) Auth::id()) {
            Log::warning('google_business.holder_mismatch', ['ip' => $request->ip()]);

            return $this->back('google-business-failed');
        }

        // Google devuelve `error` cuando el admin cancela o no concede. No es un fallo nuestro.
        if ($request->query('error') !== null) {
            return $this->back('google-business-cancelled');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->back('google-business-failed');
        }

        try {
            $refreshToken = $oauth->refreshTokenFromCode($code, $challenge['verifier'], $this->callbackUrl());
        } catch (GoogleBusinessException $e) {
            // El MOTIVO sí se le dice al admin: es quien puede arreglarlo, y cada uno se arregla de
            // una forma distinta. El material no sale de aquí.
            Log::warning('google_business.connect_failed', ['reason' => $e->reason]);

            return $this->back('google-business-'.str_replace('_', '-', $e->reason));
        }

        $previous = $connector->connect($refreshToken, (int) Auth::id());

        AuditLogger::log('google_business.connected', null, ['reconnected' => $previous !== null]);

        // Fuera de la transacción a propósito: es una llamada HTTP a Google y no tiene nada que hacer
        // dentro de un candado de fila. Si falla, lo único que queda es un permiso de más.
        if ($previous !== null) {
            $connector->revoke($previous);
        }

        return $this->back('google-business-connected');
    }

    /**
     * ⚠️ **`settings.manage`, no `panel_role`.** El middleware de la ruta solo garantiza que quien
     * entra pertenece al panel, y a un `staff` de puerta no se le entrega el permiso sobre la ficha.
     */
    private function authorizeSettings(Request $request): void
    {
        abort_unless(Auth::user()?->hasPermission('settings.manage') === true, 403);
    }

    /**
     * Se genera con `route()` en vez de escribirla, como en el login con Google: así sale con el host
     * por el que ha entrado la petición y no con uno escrito a mano que se desincroniza.
     */
    private function callbackUrl(): string
    {
        return route('admin.google_business.callback');
    }

    /**
     * De vuelta a la pantalla «Ficha de Google», que es quien sabe pintar el desenlace. Es una página
     * de Filament, y su nombre de ruta lo compone el panel a partir del `slug`.
     */
    private function back(string $status): RedirectResponse
    {
        return redirect()->route('filament.admin.pages.ficha-google')->with('status', $status);
    }
}
