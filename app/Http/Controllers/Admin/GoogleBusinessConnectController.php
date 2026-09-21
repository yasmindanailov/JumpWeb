<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Enums\GoogleReviewSuppressionReason;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSummary;
use App\Domain\Content\Services\GoogleReviewSuppressions;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Exceptions\GoogleBusinessApiException;
use App\Domain\Platform\Exceptions\GoogleBusinessException;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\GoogleBusinessChoice;
use App\Domain\Platform\Services\GoogleBusinessConnector;
use App\Domain\Platform\Services\GoogleBusinessCredentials;
use App\Domain\Platform\Services\GoogleBusinessOAuth;
use App\Http\Auth\GoogleBusinessOAuthSession;
use App\Http\Controllers\Controller;
use App\Notifications\GoogleBusinessLocationChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

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
     * **Elegir la ficha del parque** (§4.2·4).
     *
     * ⚠️ El identificador llega del navegador **a propósito** —es lo que el admin eligió en la
     * lista—, y por eso el servicio lo revalida contra el listado de Google antes de guardarlo. Aquí
     * no se comprueba nada de eso: la capa de entrega recoge el gesto, el dominio decide si vale.
     */
    public function chooseLocation(Request $request, GoogleBusinessConnector $connector): RedirectResponse
    {
        $this->authorizeSettings($request);

        $conexion = GoogleBusinessConnection::current();
        $token = $conexion?->readToken();

        if ($token === null) {
            return $this->back('google-business-failed');
        }

        $name = (string) $request->input('location', '');

        if ($name === '') {
            return $this->back('google-business-failed');
        }

        try {
            $eleccion = $connector->chooseLocation($token, $name, (int) Auth::id(), $request->boolean('confirmed'));
        } catch (GoogleBusinessException $e) {
            return $this->back('google-business-'.str_replace('_', '-', $e->reason));
        } catch (GoogleBusinessApiException $e) {
            Log::warning('google_business.choose_failed', ['http' => $e->httpStatus, 'reason' => $e->reason]);

            return $this->back('google-business-api-failed');
        }

        if ($eleccion->changed) {
            $this->warnAdmins($eleccion);
        }

        return $this->back('google-business-location-chosen');
    }

    /**
     * **OCULTAR una reseña** (T2·5, §4.3·7, `#731`). Lo exigió la revisión de privacidad.
     *
     * ⚠️⚠️ **El rastro lleva el hash y el motivo, y NADA más**: ni el texto, ni el nombre del autor,
     * ni su foto (`RGPD-02`). Y no es una cautela de más — `audit_logs` **sobrevive a la reseña que
     * lo causó**, así que lo que se escriba aquí dura mucho más que el dato que lo originó.
     */
    public function hideReview(Request $request, GoogleReviewSuppressions $suppressions): RedirectResponse
    {
        $this->authorizeSettings($request);

        $datos = $request->validate([
            'review' => ['required', 'integer'],
            // ⚠️ El motivo se valida contra el ENUM: un valor de fuera no entra en una tabla que no
            // caduca (§4.3·7).
            'reason' => ['required', Rule::enum(GoogleReviewSuppressionReason::class)],
        ]);

        $resena = GoogleBusinessReview::query()->find($datos['review']);

        if ($resena === null) {
            // La pasada pudo haberla retirado entre que se pintó la pantalla y se pulsó el botón.
            return $this->back('google-business-review-missing');
        }

        $motivo = GoogleReviewSuppressionReason::from($datos['reason']);
        $hash = $suppressions->hide($resena, $motivo);

        AuditLogger::log(GoogleReviewSuppressions::ACTION_HIDDEN, payload: ['hash' => $hash, 'reason' => $motivo->value]);

        return $this->back('google-business-review-hidden');
    }

    /**
     * **Dejar de ocultar** (T2·5, `#731`).
     *
     * ▶ **No estaba en la spec y se añade a sabiendas**: sin esto un clic equivocado es irreversible
     * para siempre, porque la lista de supresión no caduca. Aquí no se restaura nada —el texto y las
     * imágenes se borraron al ocultar— sino que se deja de tapar: la reseña vuelve a la portada
     * **solo si sigue publicada en Google**, y la trae la pasada como cualquier otra.
     */
    public function unhideReview(Request $request, GoogleReviewSuppressions $suppressions): RedirectResponse
    {
        $this->authorizeSettings($request);

        $datos = $request->validate(['hash' => ['required', 'string', 'size:64']]);

        if (! $suppressions->unhide($datos['hash'])) {
            return $this->back('google-business-review-missing');
        }

        AuditLogger::log(GoogleReviewSuppressions::ACTION_UNHIDDEN, payload: ['hash' => $datos['hash']]);

        return $this->back('google-business-review-unhidden');
    }

    /**
     * **Desconectar** (§4.2·8). **Solo POST**, y con CSRF: retirar el permiso de la ficha del parque
     * no puede depender de que alguien abra un enlace.
     */
    public function disconnect(Request $request, GoogleBusinessConnector $connector): RedirectResponse
    {
        $this->authorizeSettings($request);

        $token = $connector->disconnect((int) Auth::id());

        if ($token === null) {
            return $this->back('google-business-not-connected');
        }

        // ❗❗ **Y con la conexión se van las reseñas y sus ficheros** (T2·4, §4.3·6, `#730`).
        // Sin conexión no queda base para seguir publicando el nombre y la cara de terceros que
        // nunca han tratado con el parque: lo que justificaba tenerlos era el interés legítimo de
        // enseñar la ficha, y la ficha ya no está. Dejarlos hasta que los alcance el plazo serían
        // 29 días más de datos de otros sin nada detrás.
        // ⚠️ **Va aquí y no en el servicio**: `disconnect()` vive en Platform, que no puede mirar a
        // Content (`ModuleBoundariesTest`: `'Platform' => []`). La capa de entrega es el composition
        // root y sí ve a los dos — la misma frontera que el aviso a los admins.
        // ⚠️ **Fila a fila y por el MODELO**: es su evento `deleting` el que borra el fichero.
        foreach (GoogleBusinessReview::query()->get() as $resena) {
            $resena->delete();
        }

        GoogleBusinessReviewSummary::query()->delete();

        // ⚠️ Fuera de la transacción y sin bloquear el desenlace: lo de casa ya está borrado, y si
        // Google no atiende la revocación **se le dice al admin cómo retirarla a mano** (§4.2·8), que
        // es lo único accionable que queda.
        return $this->back($connector->revoke($token)
            ? 'google-business-disconnected'
            : 'google-business-disconnected-not-revoked');
    }

    /**
     * **El aviso del §4.2·4**, a todo el que pueda tocar esto.
     *
     * ⚠️ **Va aquí y no en el servicio**: `User` vive en Identity y Platform no puede mirar a ningún
     * módulo (`ModuleBoundariesTest`). La capa de entrega es el composition root y sí ve a los dos —
     * la misma frontera que hizo que «quién conectó» sea una FK sin relación (`#720`).
     *
     * ⚠️ **Avisar no puede tumbar el gesto**: el cambio ya está guardado y auditado. Si el correo
     * falla, se registra y se sigue; lo contrario sería perder una elección legítima por un SMTP.
     */
    private function warnAdmins(GoogleBusinessChoice $eleccion): void
    {
        try {
            $destinatarios = User::query()
                ->whereHas('roles', fn (Builder $roles) => $roles
                    ->where('name', 'admin')
                    ->orWhereHas('permissions', fn (Builder $permisos) => $permisos->where('name', 'settings.manage')))
                // Una cuenta anonimizada tiene un correo sintético que rebota (`RGPD-01`).
                ->where('email', 'not like', '%@'.User::ANONYMIZED_EMAIL_DOMAIN)
                ->get();

            Notification::send($destinatarios, new GoogleBusinessLocationChanged(
                $eleccion->location->title,
                $eleccion->previousTitle,
                // Sin `?->`: llegar aquí exige haber pasado `auth` y `settings.manage`, así que el
                // usuario existe — y Larastan lo ve.
                (string) Auth::user()->name,
            ));
        } catch (\Throwable $e) {
            Log::warning('google_business.location_warning_failed', ['error' => $e::class]);
        }
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
