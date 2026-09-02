<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Contracts\SocialLoginResult;
use App\Domain\Identity\Contracts\SocialProfile;
use App\Domain\Identity\Exceptions\GoogleAuthException;
use App\Domain\Identity\Services\GoogleAuth;
use App\Domain\Identity\Services\GoogleOAuth;
use App\Domain\Identity\Services\SocialLogin;
use App\Http\Auth\GoogleAuthSession;
use App\Http\Controllers\Controller;
use App\Http\Sidebar\SidebarEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * **Entrar y registrarse con Google**, las dos peticiones de navegador
 * (`docs/specs/auth-con-google.md` §6.4).
 *
 * Van en `web` y no en `/api/v1` a propósito: son redirecciones de navegador con sesión, no una
 * superficie de datos. Meterlas en la API obligaría además a declararlas en el contrato, que exige
 * paridad en las dos direcciones y no tiene nada que decir sobre un `302` hacia Google.
 *
 * ⚠️ **Aquí no vive ninguna regla de identidad.** Este controlador hace lo que le toca a la capa de
 * entrega —el reto, la sesión, el destino de vuelta y el desenlace visible— y le pregunta a
 * {@see SocialLogin} a quién corresponde la afirmación. Es la misma frontera que hay entre
 * `AuthSessionController` y `PasswordLogin`.
 *
 * ⚠️⚠️ **Sin las dos claves configuradas, las dos rutas responden 404** — no un 500 ni una pantalla
 * de error. El hueco falla hacia invisible, como los demás huecos por instalación: una instalación
 * que no use Google no tiene por qué enterarse de que esto existe.
 */
class GoogleAuthController extends Controller
{
    /** La ida: se abre el reto y se manda al visitante a Google. */
    public function redirect(Request $request, GoogleOAuth $oauth): RedirectResponse
    {
        abort_unless(GoogleAuth::enabled(), 404);

        $challenge = GoogleAuthSession::startChallenge($this->safeDestination($request, $request->query('next')));

        return redirect()->away(
            $oauth->authorizationUrl($challenge['state'], $challenge['nonce'], $this->callbackUrl())
        );
    }

    /**
     * **La ida para VINCULAR** a la cuenta en la que ya se está (`#347`, §21.3).
     *
     * ⚠️⚠️ **Es una ruta aparte y no un `?intent=` sobre la de entrar**, y hay dos razones. La primera
     * es que así la protege el middleware `auth`: sin sesión no hay nada que vincular, y con un
     * parámetro habría que comprobarlo a mano dentro. La segunda es que la intención **se anota en el
     * reto del SERVIDOR**, igual que el `nonce`: lo que decide qué se hace al volver no puede ser algo
     * que ponga quien vuelve.
     *
     * ⚠️ Se anota además QUIÉN la pidió. Entre la ida y la vuelta caben un `logout` y un `login` con
     * otra cuenta —en un dispositivo compartido es lo normal—, y sin eso el vínculo aterrizaría en la
     * cuenta equivocada sin que fallara nada.
     */
    public function link(Request $request, GoogleOAuth $oauth): RedirectResponse
    {
        abort_unless(GoogleAuth::enabled(), 404);

        $challenge = GoogleAuthSession::startChallenge(
            $this->safeDestination($request, $request->query('next')),
            GoogleAuthSession::INTENT_LINK,
            (int) Auth::id(),
        );

        return redirect()->away(
            $oauth->authorizationUrl($challenge['state'], $challenge['nonce'], $this->callbackUrl())
        );
    }

    /**
     * La vuelta. El orden de las comprobaciones **es** la defensa:
     *
     *  1. el reto, que se consume siempre (aunque falle);
     *  2. lo que Google dice del intento (cancelado, sin código);
     *  3. el canje servidor-a-servidor, que es lo único que convierte esto en una identidad;
     *  4. y solo entonces, a quién corresponde.
     */
    public function callback(Request $request, GoogleOAuth $oauth, SocialLogin $login): RedirectResponse
    {
        abort_unless(GoogleAuth::enabled(), 404);

        $challenge = GoogleAuthSession::consumeChallenge($request->query('state'));

        // Sin reto válido, esta vuelta no corresponde a ninguna ida nuestra: **no se hace nada**.
        // Es la defensa contra que a alguien le hagan «entrar» con una cuenta que no es la suya
        // mandándole un enlace de retorno fabricado (`P4`).
        if ($challenge === null) {
            Log::warning('auth.google_state_mismatch', ['ip' => $request->ip()]);

            return $this->failure(null);
        }

        $destination = $this->safeDestination($request, $challenge['intended']);

        // Google devuelve `error` cuando la persona cancela o no concede el acceso. No es un fallo
        // nuestro y no se registra como tal: se le dice y se le deja donde estaba.
        if ($request->query('error') !== null) {
            return redirect()->to($destination)->with('status', 'google-cancelled');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->failure($destination);
        }

        try {
            $profile = $oauth->profileFromCode($code, $this->callbackUrl(), $challenge['nonce']);
        } catch (GoogleAuthException $e) {
            // El motivo va al log y NUNCA a la pantalla: a quien llega no le sirve de nada saber que
            // el `aud` no cuadraba, y a quien esté probando por dónde se cuela sí le serviría.
            Log::warning('auth.google_exchange_failed', ['ip' => $request->ip(), 'reason' => $e->reason]);

            return $this->failure($destination);
        }

        // La vuelta de una ida que pedía VINCULAR se resuelve por otra puerta: no autentica, no
        // promueve y no expulsa (`#347`, §21.3).
        if ($challenge['intent'] === GoogleAuthSession::INTENT_LINK) {
            return $this->afterLink($request, $login, $profile, $challenge['holder'], $destination);
        }

        // ⚠️ Quién estaba en esta sesión ANTES de autenticar. Se lee aquí porque dentro de un instante
        // `SocialLogin` lo habrá sustituido; para qué sirve, más abajo.
        $previousId = Auth::id();

        $result = $login->enter($profile, (string) $request->ip());

        if ($result->isSignedIn()) {
            return $this->afterSignIn($request, $result, $previousId, $destination);
        }

        if ($result->isPendingRegistration()) {
            GoogleAuthSession::rememberProfile($profile);

            return $this->pendingRegistration();
        }

        return redirect()->to($destination)->with('status', $this->refusalStatus($result->reason));
    }

    /**
     * Los dos efectos de sesión que el dominio NO hace y que el único login que existía ya hacía
     * (§6.5): regenerar el identificador de sesión y, si entra OTRA persona, no dejarle el desenlace
     * de pago de la anterior.
     */
    private function afterSignIn(Request $request, SocialLoginResult $result, ?int $previousId, string $destination): RedirectResponse
    {
        // Cierra la fijación de sesión. Aquí importa **doble**: el reto de la ida vivía en esta misma
        // sesión, así que quien la hubiera fijado antes tendría además el `state`.
        $request->session()->regenerate();

        // En un dispositivo compartido, Bob no puede encontrarse el cajón abierto con el «pago
        // denegado» de Alice y su código de pedido. Solo si es OTRA persona: a quien vuelve a
        // identificarse en mitad de su propio flujo no se le borra la confirmación de su compra.
        if ($previousId !== null && $result->user !== null && $previousId !== (int) $result->user->getKey()) {
            SidebarEntry::clear();
        }

        $redirect = redirect()->to($destination);

        // Solo se avisa en pantalla cuando el vínculo se acaba de crear: quien ya entraba con Google
        // no necesita que se lo cuenten cada vez. El aviso que sí sale siempre es el correo.
        return $result->linked ? $redirect->with('status', 'google-linked') : $redirect;
    }

    /**
     * **La vuelta de una vinculación pedida desde la cuenta** (`#347`, §21.3).
     *
     * ⚠️⚠️ **La comprobación que de verdad importa es que siga siendo el MISMO titular.** El reto anotó
     * quién la pidió; si ahora hay otra sesión —o ninguna—, no se vincula nada: entre la ida y la
     * vuelta caben un `logout` y un `login` con otra cuenta, y sin esto el vínculo aterrizaría en la
     * cuenta equivocada **sin que fallara nada**. No es un caso raro en un dispositivo compartido.
     *
     * ⚠️ El aviso por correo va aquí y no en el dominio, como en la otra puerta: es un efecto de la
     * petición, y un fallo del SMTP no puede deshacer un vínculo que ya es válido.
     */
    private function afterLink(Request $request, SocialLogin $login, SocialProfile $profile, ?int $holder, string $destination): RedirectResponse
    {
        $user = Auth::user();

        if ($holder === null || $user === null || (int) $user->getKey() !== $holder) {
            Log::warning('auth.google_link_holder_changed', ['ip' => $request->ip()]);

            return redirect()->to($destination)->with('status', 'google-link-session-changed');
        }

        $result = $login->linkToAccount($user, $profile, (string) $request->ip());

        if ($result->wasRefused()) {
            return redirect()->to($destination)->with('status', $this->refusalStatus($result->reason));
        }

        if ($result->linked && $result->user !== null) {
            $login->announceLink($result->user, $profile->provider);
        }

        // Sin `linked` la vinculación ya existía: se dice que está hecha, no que se acaba de hacer.
        return redirect()->to($destination)->with('status', $result->linked ? 'google-linked' : 'google-already-linked');
    }

    /**
     * No hay cuenta todavía: el perfil verificado espera en la sesión y la persona va a la pantalla
     * que completa el alta (§5.3, §7).
     *
     * ⚠️ **La pantalla vive en el CAJÓN** (`[DECIDIDO owner]` Q8), así que el destino es una PUERTA:
     * una ruta web que sirve la home y abre el cajón en su zona, el mismo mecanismo que `/registro` y
     * `/login` (`Http\Sidebar\AccountDoor`). Aquí no se decide nada más.
     */
    private function pendingRegistration(): RedirectResponse
    {
        return redirect()->route('registro.google');
    }

    /** El desenlace visible de un rechazo del dominio. Uno por motivo: cada uno tiene otra salida. */
    private function refusalStatus(?string $reason): string
    {
        return match ($reason) {
            SocialLoginResult::REASON_EMAIL_UNVERIFIED => 'google-email-unverified',
            SocialLoginResult::REASON_ANONYMIZED => 'google-anonymized',
            SocialLoginResult::REASON_PROVIDER_CONFLICT => 'google-provider-conflict',
            SocialLoginResult::REASON_PROVIDER_TAKEN => 'google-provider-taken',
            default => 'google-failed',
        };
    }

    /**
     * Un fallo del mecanismo, dicho de una sola forma. Ver el aviso del `catch`.
     */
    private function failure(?string $destination): RedirectResponse
    {
        return redirect()->to($destination ?? route('login'))->with('status', 'google-failed');
    }

    /**
     * **La URI de redirección**, y tiene que ser LA MISMA en las dos peticiones: Google la compara
     * carácter a carácter en el canje y rechaza el intento si no coinciden.
     *
     * Se genera con `route()` en vez de escribirla: así sale con el host por el que ha entrado el
     * visitante, que es lo que hace que una instalación con `www` y sin `www` funcione con las dos
     * —siempre que las dos estén dadas de alta en la consola de Google—.
     */
    private function callbackUrl(): string
    {
        return route('auth.google.callback');
    }

    /**
     * A dónde vuelve el visitante, validado contra el MISMO host (`SEC-08`).
     *
     * ⚠️ Se valida **al guardar y al usar**. Guardar y confiar sería suficiente hoy, pero la sesión
     * sobrevive a cambios de host y a despliegues, y una redirección abierta cuesta lo mismo de
     * cerrar en los dos sitios.
     *
     * @param  mixed  $candidate
     */
    private function safeDestination(Request $request, $candidate): string
    {
        $fallback = route('account');

        if (! is_string($candidate) || $candidate === '') {
            return $fallback;
        }

        // Ruta relativa del propio sitio. `//otro.host` NO lo es: el navegador la trata como absoluta.
        if (str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//')) {
            return url($candidate);
        }

        $host = parse_url($candidate, PHP_URL_HOST);

        if (is_string($host) && strcasecmp($host, $request->getHost()) === 0) {
            return $candidate;
        }

        return $fallback;
    }
}
