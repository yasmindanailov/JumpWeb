<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Contracts\SignupResult;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\PasswordPolicy;
use App\Domain\Identity\Services\SelfSignup;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverSettings;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\Concerns\RequiresStatefulSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Las reglas del alta. **Las mismas que el componente Livewire**, campo a campo: son las dos
     * puertas de la misma operación, y una regla que difiera es una cuenta que una puerta acepta y la
     * otra no.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Sin `unique` a propósito: la existencia la resuelve el dominio, que además decide
            // qué se le cuenta al usuario y a quién se avisa por correo.
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => PasswordPolicy::rules(),
            'accept_privacy' => ['accepted'],
            'accept_terms' => ['accepted'],
            'marketing' => ['sometimes', 'boolean'],
            'context' => ['sometimes', 'string', 'in:'.self::CONTEXT_STANDALONE.','.self::CONTEXT_PURCHASE],
            // El campo señuelo viaja igual que en la web: un cliente legítimo lo deja vacío.
            //
            // ⚠️ **`nullable` no es adorno: sin él, el caso NORMAL fallaba.** `ConvertEmptyStringsToNull`
            // convierte el `""` que manda un cliente legítimo en `null`, `sometimes` lo ve presente y
            // `string` lo rechaza — así que el alta moría con un **422 sobre un campo que el usuario no
            // ve** y que ni siquiera es suyo. Lo destapó el primer cliente real del endpoint (el cajón
            // SPA); los tests del paso 3c mandaban el señuelo relleno o lo omitían, que son justo los
            // dos casos que esquivan el fallo.
            'website' => ['sometimes', 'nullable', 'string'],
            'turnstile_token' => ['sometimes', 'nullable', 'string'],
            // Fase 6 · waiver (`specs/waiver-probatorio.md` §4.4): casilla SEPARADA y desmarcada por
            // defecto — aceptarla es opcional en el alta, pero aceptar sin decir QUÉ texto se leyó no
            // vale nada: el servidor solo emite la aceptación con el identificador que él sirvió.
            'accept_waiver' => ['sometimes', 'nullable', 'boolean'],
            'waiver_document_id' => ['required_if_accepted:accept_waiver', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Los avisos propios de las casillas legales.
     *
     * ⚠️ **Espejo de `Register::messages()`, y sin ellos las dos puertas divergían**: el componente
     * dice «Debes aceptar esta condición para continuar.» y la API caía al genérico de la regla
     * `accepted`. Un cliente que pinte el mismo formulario enseñaría un texto distinto según por qué
     * puerta entrara.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'accept_privacy.accepted' => __('account.register.must_accept'),
            'accept_terms.accepted' => __('account.register.must_accept'),
            // En `api.register`, no en `account.register`: ese grupo viaja en el montaje de cada
            // página y estos avisos solo los emite el servidor (`SidebarMountTest` mide el peaje).
            'waiver_document_id.required_if_accepted' => __('api.register.waiver_document_required'),
        ];
    }

    /**
     * Cómo se NOMBRAN los campos en los avisos de validación.
     *
     * ⚠️ **Sin esto las dos puertas decían cosas distintas**, y no en el código sino en la pantalla: el
     * componente Livewire declara `validationAttributes()` con los rótulos del formulario —«El campo
     * **Nombre y apellidos** es obligatorio.»— y la API respondía con el nombre técnico —«El campo
     * **name** es obligatorio.»—. Lo mismo, dicho peor, a un cliente que pinta el mismo formulario.
     *
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'name' => __('account.register.name'),
            'email' => __('account.register.email'),
            'phone' => __('account.register.phone'),
            'password' => __('account.register.password'),
        ];
    }

    public function register(Request $request, SelfSignup $signup): Response|JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());

        $inPurchase = ($data['context'] ?? self::CONTEXT_STANDALONE) === self::CONTEXT_PURCHASE;

        // El alta en la compra deja la sesión abierta, así que necesita una (ver
        // `RequiresStatefulSession`). Se comprueba ANTES de crear nada: sin esta guarda la cuenta
        // se creaba y el 500 llegaba después, dejando al cliente sin saber si tiene cuenta o no.
        // El alta suelta no la necesita y funciona sin origen *stateful*.
        if ($inPurchase && $denial = $this->requireSession($request)) {
            return $denial;
        }

        // Fase 6 · waiver: si acepta, el identificador tiene que ser el del texto VIGENTE, y se
        // comprueba ANTES de crear nada — un 422 después de crear la cuenta dejaría al cliente sin
        // saber si la tiene. Fuera del modo interno no hay texto que aceptar aquí.
        $waiver = null;
        if ((bool) ($data['accept_waiver'] ?? false)) {
            if (! WaiverSettings::isInternal()) {
                return ApiErrorResponse::make(ApiErrorCode::ValidationFailed, 422, fields: [
                    'waiver_document_id' => [__('api.register.waiver_not_internal')],
                ]);
            }
            $document = WaiverAcceptance::currentDocument((int) $data['waiver_document_id']);
            if ($document === null) {
                return ApiErrorResponse::make(ApiErrorCode::ValidationFailed, 422, fields: [
                    'waiver_document_id' => [__('api.register.waiver_stale')],
                ]);
            }
            // El canal sale de CÓMO se sirvió la petición, no de una cabecera que el cliente elija:
            // el alta es anónima, así que aquí «web» es «vino por el grupo stateful de Sanctum y tiene
            // sesión» (el cajón, con `Origin`) y «api» es «sin sesión» (la app nativa). Revisión `#169`
            // §10.2·1: `bearerToken() !== null` lo decidía una cabecera que cualquiera puede añadir.
            $waiver = [
                'document' => $document,
                'channel' => $request->hasSession() ? WaiverSignature::CHANNEL_WEB : WaiverSignature::CHANNEL_API,
                'user_agent' => $request->userAgent(),
            ];
        }

        $result = $signup->register(
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'marketing' => (bool) ($data['marketing'] ?? false),
                'waiver' => $waiver,
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
