<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Contracts\LoginResult;
use App\Domain\Identity\Services\PasswordLogin;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\Concerns\RequiresStatefulSession;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
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
        // efecto de la sesión web y por eso lo pone el llamante, no el servicio (spec §4.6.3); el
        // sidebar Livewire hace lo mismo, más lo suyo con la cesta.
        $request->session()->regenerate();

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
