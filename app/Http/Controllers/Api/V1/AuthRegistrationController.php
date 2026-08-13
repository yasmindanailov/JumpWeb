<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Contracts\SignupResult;
use App\Domain\Identity\Services\SelfSignup;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\Concerns\RequiresStatefulSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Fase 3 · paso 3c — alta pública y reenvío de verificación por API (`docs/specs/api-v1.md` §4.4).
 *
 * Las cuatro capas de defensa del alta —honeypot, límite por IP, límite por correo y Turnstile— y
 * la creación atómica de cuenta+rol+consents viven en `Identity\Services\SelfSignup`, el mismo
 * servicio que usa el modal de la web desde este paso. Aquí solo se traduce el veredicto a HTTP.
 *
 * **Dos contextos de alta, y los decide el servidor** (`DECISIONES #31`). La web tiene dos: el
 * modal suelto (sin sesión, con verificación por correo) y el registro dentro de la compra
 * («pay-first»: se inicia sesión y no se manda verificación, porque el pago la sustituye y un bot
 * no paga). La SPA de Fase 4 necesita los dos, así que el cliente declara desde DÓNDE se registra
 * y el servidor aplica la política. No es un interruptor de seguridad: quedar identificado sin
 * verificar es exactamente lo que el pay-first ya permite en la web, y sin verificar no se accede
 * a «mi cuenta».
 *
 * **Sobre decir que un correo ya existe**: sí, se dice, igual que en la web. Es una decisión de
 * producto de la clienta —conversión sobre ocultación— y replicarla es lo que evita que las dos
 * puertas cuenten cosas distintas. Lo que acota la enumeración masiva es el límite por correo
 * (3/hora), que se hereda del servicio.
 */
class AuthRegistrationController extends Controller
{
    use RequiresStatefulSession;

    /** Alta suelta: sin sesión, con verificación por correo. */
    private const CONTEXT_STANDALONE = 'standalone';

    /** Alta dentro de la compra: pay-first (sesión iniciada, sin verificación). */
    private const CONTEXT_PURCHASE = 'purchase';

    public function register(Request $request, SelfSignup $signup): Response|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Sin `unique` a propósito: la existencia la resuelve el dominio, que además decide
            // qué se le cuenta al usuario y a quién se avisa por correo.
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', Password::min(8)->uncompromised()],
            'accept_privacy' => ['accepted'],
            'accept_terms' => ['accepted'],
            'marketing' => ['sometimes', 'boolean'],
            'context' => ['sometimes', 'string', 'in:'.self::CONTEXT_STANDALONE.','.self::CONTEXT_PURCHASE],
            // El campo señuelo viaja igual que en la web: un cliente legítimo lo deja vacío.
            'website' => ['sometimes', 'string'],
            'turnstile_token' => ['sometimes', 'string'],
        ]);

        $inPurchase = ($data['context'] ?? self::CONTEXT_STANDALONE) === self::CONTEXT_PURCHASE;

        // El alta en la compra deja la sesión abierta, así que necesita una (ver
        // `RequiresStatefulSession`). Se comprueba ANTES de crear nada: sin esta guarda la cuenta
        // se creaba y el 500 llegaba después, dejando al cliente sin saber si tiene cuenta o no.
        // El alta suelta no la necesita y funciona sin origen *stateful*.
        if ($inPurchase && $denial = $this->requireSession($request)) {
            return $denial;
        }

        $result = $signup->register(
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'marketing' => (bool) ($data['marketing'] ?? false),
            ],
            (string) $request->ip(),
            notifyByEmail: ! $inPurchase,
            honeypot: (string) ($data['website'] ?? ''),
            turnstileToken: (string) ($data['turnstile_token'] ?? ''),
        );

        if (! $result->looksSuccessful()) {
            return $this->denial($result);
        }

        // Pay-first: la cuenta recién creada entra directamente, para poder seguir al pago sin
        // pedir las credenciales otra vez. Iniciar sesión es efecto del llamante, no del servicio
        // (spec §4.6.3), y solo se hace si hubo cuenta de verdad — un honeypot no identifica a nadie.
        if ($inPurchase && $result->user !== null) {
            Auth::login($result->user);
            $request->session()->regenerate();
        }

        // **201 sin cuerpo, siempre.** El primer borrador devolvía el perfil cuando había cuenta y
        // nada cuando se fingía: un bot distinguía las dos respuestas de un vistazo y el señuelo
        // dejaba de servir para lo único que sirve. Lo destapó el test de contrato, no una
        // revisión. Quien se registra dentro de la compra ya queda identificado y puede pedir su
        // perfil a `GET /me`; quien se registra suelto no lo necesita, porque aún tiene que
        // verificar el correo.
        return response()->noContent(201);
    }

    /**
     * Reenvía la verificación. **Público a propósito**: quien acaba de darse de alta suelta todavía
     * no tiene sesión, y es justo cuando más falta le hace. Los dos cooldowns del servicio —por IP
     * y por correo destinatario— son los que impiden que esto sea un cañón de correos hacia un
     * buzón ajeno.
     *
     * Responde **202** siempre, haya enviado o no: si el cooldown o la inexistencia de la cuenta se
     * notaran en la respuesta, este endpoint sería un oráculo de correos registrados.
     */
    public function resendVerification(Request $request, SelfSignup $signup): Response
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $signup->resendVerification($data['email'], (string) $request->ip());

        return response()->noContent(202);
    }

    private function denial(SignupResult $result): JsonResponse
    {
        if ($result->outcome === SignupResult::RATE_LIMITED) {
            return ApiErrorResponse::make(
                ApiErrorCode::TooManyRequests,
                429,
                params: ['retry_after' => $result->retryAfter],
                headers: ['Retry-After' => (string) $result->retryAfter],
            );
        }

        // El resto son cosas que el cliente puede corregir y que se cuentan POR CAMPO, igual que la
        // web las pinta bajo el input del correo: la cuenta ya existe (verificada o no) o el
        // anti-bot no pasó. Reutilizar el 422 de validación evita inventar códigos que un cliente
        // tendría que aprender para hacer exactamente lo mismo: enseñar el mensaje junto al campo.
        return ApiErrorResponse::make(
            ApiErrorCode::ValidationFailed,
            422,
            fields: ['email' => [$this->messageFor($result)]],
        );
    }

    private function messageFor(SignupResult $result): string
    {
        return match ($result->outcome) {
            SignupResult::ALREADY_REGISTERED => __('account.register.already_exists'),
            SignupResult::PENDING_VERIFICATION => __('account.register.exists_unverified'),
            default => __('account.register.bot_check_failed'),
        };
    }
}
