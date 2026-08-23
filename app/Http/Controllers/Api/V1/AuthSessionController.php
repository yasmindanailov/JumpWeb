<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Contracts\LoginResult;
use App\Domain\Identity\Services\PasswordLogin;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\Concerns\RequiresStatefulSession;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Sidebar\SidebarEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Fase 3 · paso 3b — abrir y cerrar SESIÓN por API (`docs/specs/api-v1.md` §4.2).
 *
 * Es la superficie que consumirá la SPA del sidebar (Fase 4): **cookie de sesión de Sanctum + CSRF**,
 * nunca un token en `localStorage`. El flujo del cliente es el estándar del modo stateful: pedir
 * `GET /sanctum/csrf-cookie`, y luego `POST auth/login` con el `X-XSRF-TOKEN`. El CSRF no se
 * configura aquí: `EnsureFrontendRequestsAreStateful` ya monta `StartSession` y `ValidateCsrfToken`
 * para las peticiones que vienen de un origen declarado *stateful*.
 *
 * El controlador **no comprueba credenciales ni cuenta intentos**: eso es
 * `Identity\Services\PasswordLogin`, el mismo servicio que usa el modal de la web desde este paso.
 * Si lo hiciera aquí, la API tendría su propia copia de los dos limitadores de `SEC-06` y bastaría
 * con que una de las dos se dejara el de IP-sola para reabrir el credential-stuffing distribuido.
 *
 * ⚠️ **La EMISIÓN de tokens Bearer no vive aquí**: viaja a Fase 6 con la app que los consuma
 * (`DECISIONES #29`). `logout` sí contempla ya esa vía —revoca el token de la petición si lo hay—
 * para que el día que exista el emisor no haya que acordarse de nada.
 */
class AuthSessionController extends Controller
{
    use RequiresStatefulSession;

    /**
     * Abre sesión y devuelve el perfil, la misma forma que `GET me`: quien acaba de identificarse
     * necesita justo eso, y devolverlo evita una segunda petición para pintar la pantalla.
     */
    public function login(Request $request, PasswordLogin $passwordLogin): UserResource|JsonResponse
    {
        // Sin sesión no hay dónde abrirla (ver `RequiresStatefulSession`). Se comprueba ANTES de
        // validar y de tocar el limitador: no tiene sentido gastarle intentos a quien no puede
        // entrar de todos modos. El cliente nativo de Fase 6 no usará esta puerta, sino la emisión
        // de tokens.
        if ($denial = $this->requireSession($request)) {
            return $denial;
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        // ⚠️ Quién estaba en esta sesión ANTES de autenticar. Se lee aquí porque dentro de un
        // instante `Auth::attempt()` lo habrá sustituido; para qué sirve, más abajo.
        $previousId = Auth::id();

        $result = $passwordLogin->attempt(
            $credentials['email'],
            $credentials['password'],
            (bool) ($credentials['remember'] ?? false),
            (string) $request->ip(),
        );

        if ($result->failed()) {
            return $this->denial($result);
        }

        // Regenerar el identificador de sesión tras autenticar cierra la fijación de sesión. Es
        // efecto de la sesión web y por eso lo pone el llamante, no el servicio (spec §4.6.3).
        $request->session()->regenerate();

        // ⚠️⚠️ **Si entra OTRA persona, su desenlace de pago pendiente NO puede ser el de la
        // anterior** (`specs/auth-en-cajon.md` §8·A8). `SidebarEntry` vive en SESIÓN y
        // `regenerate()` **conserva los datos**, así que en un dispositivo compartido Bob se
        // encontraría el cajón abierto con el «pago denegado» de Alice y su código de pedido.
        //
        // ▶ **Estaba solo en `Livewire\Auth\Login`, el modal que se retira**, y el comentario que
        // había aquí lo daba por sabido —«el sidebar Livewire hace lo mismo, más lo suyo con la
        // cesta»— sin que nadie lo leyera como el hueco que era. Este camino es el que usa el cajón
        // **desde 4.4a·2**, así que el hueco lleva abierto desde entonces; lo destapó la auditoría
        // de tests de A8, midiendo qué caza cada regla.
        //
        // ⚠️ El marcador es **quién estaba autenticado**, y no la clave de sesión `purchase.user_id`
        // que usaba el modal: esa clave ya no la lee nadie —su consumidor era el `Purchase.php` que
        // `#112` retiró— y resucitarla sería un segundo estado para decir lo mismo.
        //
        // ⚠️ Solo si es OTRA: a quien vuelve a identificarse en mitad de su propio flujo no se le
        // puede borrar la confirmación de su compra, que es justo lo que `SidebarEntry` conserva.
        if ($previousId !== null && (int) $previousId !== (int) $result->user->getKey()) {
            SidebarEntry::clear();
        }

        return new UserResource($result->user);
    }

    /**
     * Cierra la credencial con la que se llama, y solo esa: la sesión si viene por cookie, el token
     * si viene por Bearer. Para cerrar las DEMÁS está «cerrar otras sesiones», que es otra cosa
     * (`User::revokeOtherAccess()`, paso 3a).
     *
     * Responde 204 sin cuerpo: no hay nada que contar, y un `{"ok":true}` sería un campo que todo
     * cliente tendría que leer para no aprender nada.
     */
    public function logout(Request $request): JsonResponse
    {
        // Vía Bearer: se revoca el token de ESTA petición y nada más. La invalidación de
        // credenciales vive en `User` desde el paso 3a, y `ApiBoundariesTest` lo recordó en cuanto
        // este controlador intentó borrar el token por su cuenta.
        $request->user()?->revokeCurrentAccessToken();

        // Con sesión: invalidar y rotar el token CSRF. `hasSession()` distingue las dos vías — una
        // petición Bearer pura no pasa por `StartSession` y no tiene ninguna que invalidar.
        if ($request->hasSession()) {
            auth('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Los guards ya resueltos siguen cacheando al usuario en memoria: cerrar la sesión limpia
        // `web`, pero el guard que atendió esta petición (`sanctum`) seguiría respondiendo que hay
        // alguien identificado. En producción no se nota —cada petición es un proceso nuevo—, pero
        // sí lo notaría cualquier cosa que corra DESPUÉS dentro de la misma petición (un listener,
        // un middleware terminable) y preguntara por el usuario. Medido: sin esto,
        // `auth('sanctum')->check()` seguía devolviendo `true` justo después del logout.
        Auth::forgetGuards();

        return response()->json(status: 204);
    }

    /**
     * Veredicto denegado → respuesta HTTP. El limitador da **429 con `retry_after`** para que un
     * cliente sensato sepa cuándo volver; las credenciales que no casan dan **401 genérico**, sin
     * distinguir si el correo existe (`SEC-06`).
     */
    private function denial(LoginResult $result): JsonResponse
    {
        if ($result->wasRateLimited()) {
            return ApiErrorResponse::make(
                ApiErrorCode::TooManyRequests,
                429,
                params: ['retry_after' => $result->retryAfter],
                headers: ['Retry-After' => (string) $result->retryAfter],
            );
        }

        return ApiErrorResponse::make(ApiErrorCode::InvalidCredentials, 401);
    }
}
