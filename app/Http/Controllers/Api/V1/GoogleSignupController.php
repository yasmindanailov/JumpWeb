<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Services\GoogleAuth;
use App\Domain\Identity\Services\GoogleSignup;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverAcceptance;
use App\Domain\Identity\Services\WaiverSettings;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\Concerns\RequiresStatefulSession;
use App\Http\Auth\GoogleAuthSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * **La pantalla que completa el alta con Google** (`docs/specs/auth-con-google.md` §7, tanda T2).
 *
 * Dos operaciones y las dos hablan con **la sesión del servidor**, donde el retorno de Google dejó el
 * perfil ya verificado:
 *
 *  · `GET  auth/google/pending`  — qué pintar: el nombre que sugiere Google y el correo, que es fijo.
 *  · `POST auth/google/complete` — el alta, con lo único que Google no da: el nombre corregido y el
 *    descargo. Teléfono y condiciones se piden en el checkout desde la T8·c.
 *
 * ⚠️⚠️ **Ni el `sub` ni el correo viajan en la petición, y ésa es la defensa entera.** Si viajaran,
 * cualquiera crearía una cuenta con la identidad verificada de otro — y a esa cuenta se le firma un
 * descargo probatorio. Es la doctrina que `AuthRegistrationController` ya escribe para el
 * `waiver_document_id`: *el servidor solo emite la aceptación con el identificador que él sirvió*.
 *
 * ⚠️ **Las dos exigen SESIÓN**, y no por costumbre: sin sesión no hay perfil que consumir. Un cliente
 * nativo no puede usar esta puerta — cuando exista tendrá la suya, con PKCE (§13).
 *
 * ⚠️ **Sin las dos claves configuradas responden 404**, igual que las rutas web: una instalación que
 * no use Google no tiene por qué publicar esta superficie.
 */
class GoogleSignupController extends Controller
{
    use RequiresStatefulSession;

    /**
     * Lo que la pantalla necesita para pintarse. **404 cuando no hay nada esperando**: la sesión
     * caducó, ya se usó, o esta persona nunca pasó por Google. La pantalla lo traduce en «vuelve a
     * empezar», que es lo único que puede hacer.
     */
    public function pending(Request $request): JsonResponse
    {
        abort_unless(GoogleAuth::enabled(), 404);

        if ($denial = $this->requireSession($request)) {
            return $denial;
        }

        $profile = GoogleAuthSession::peekProfile();

        if ($profile === null) {
            return ApiErrorResponse::make(ApiErrorCode::NotFound, 404);
        }

        return response()->json([
            'name' => $profile->name,
            'email' => $profile->email,
        ]);
    }

    /**
     * El alta. **Consume el perfil**: un segundo envío —dos pestañas, doble clic— ya no encuentra
     * nada que crear.
     */
    public function complete(Request $request, GoogleSignup $signup): Response|JsonResponse
    {
        abort_unless(GoogleAuth::enabled(), 404);

        if ($denial = $this->requireSession($request)) {
            return $denial;
        }

        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());

        // ⚠️ Se lee el texto ANTES de consumir el perfil: si el descargo se republicó mientras la
        // persona rellenaba, esto responde 409 y **la pantalla se vuelve a pintar con lo tecleado**.
        // Consumir primero dejaría a esa persona sin perfil y sin cuenta, que es el peor desenlace
        // posible de una carrera que no es culpa suya.
        $waiver = null;
        if ($this->waiverRequired()) {
            $waiver = WaiverAcceptance::currentDocument((int) $data['waiver_document_id']);

            if ($waiver === null) {
                return ApiErrorResponse::make(ApiErrorCode::WaiverDocumentStale, 409);
            }
        }

        $profile = GoogleAuthSession::consumeProfile();

        if ($profile === null) {
            return ApiErrorResponse::make(ApiErrorCode::NotFound, 404);
        }

        try {
            $result = $signup->register(
                $profile,
                ['name' => $data['name']],
                (string) $request->ip(),
                $request->userAgent(),
                $waiver,
            );
        } catch (WaiverDocumentStaleException) {
            // La re-comprobación BAJO EL LOCK del firmador (`#181`): el texto se publicó entre la
            // lectura de arriba y la firma. La cuenta no llega a existir —la transacción del alta ya
            // cerró, pero la firma va después y su fallo deja el alta hecha—, así que aquí lo honesto
            // es decir 409 y que la pantalla relea. Ver el aviso de `GoogleSignup`.
            return ApiErrorResponse::make(ApiErrorCode::WaiverDocumentStale, 409);
        }

        if ($result->wasRefused()) {
            // Entre pintar la pantalla y enviarla, la identidad dejó de poder entrar (la cuenta que
            // apareció con ese correo se anonimizó, o ya tenía otra llave de Google).
            return ApiErrorResponse::make(ApiErrorCode::ValidationFailed, 422, fields: [
                'name' => [__('api.google.refused')],
            ]);
        }

        // La sesión ya está abierta (la abre el alta, como el alta suelta desde `#331`). Se regenera
        // el identificador aquí, que es donde vive la sesión web: mismo trato que el login.
        $request->session()->regenerate();

        return response()->noContent(201);
    }

    /**
     * ⚠️ **Ni `email` ni `sub`**: los pone el servidor. Lo único que se acepta del cliente es lo que
     * Google no sabe.
     *
     * ⚠️⚠️ **Y desde la T8·c son DOS cosas, no cuatro** (`[DECIDIDO owner, 2026-09-02]`, spec §21.4.3):
     * el **nombre** —que Google a veces da como «Ana G.» y viaja a la firma del descargo— y la
     * **casilla del descargo**. El teléfono y las condiciones los pide el checkout, que es donde hacen
     * falta y donde la ley sitúa la aceptación.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'accept_waiver' => $this->waiverRequired() ? ['accepted'] : ['sometimes', 'nullable', 'boolean'],
            'waiver_document_id' => $this->waiverRequired()
                ? ['required', 'integer', 'min:1']
                : ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'accept_waiver.accepted' => __('api.register.waiver_required'),
            'waiver_document_id.required' => __('api.register.waiver_document_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'name' => __('account.register.name'),
        ];
    }

    /**
     * ¿Hay un texto que aceptar en esta instalación? **La misma pregunta que el alta con contraseña**,
     * y con la misma respuesta: si `GET /legal/waiver` sirve un documento, aceptarlo es condición.
     *
     * ⚠️ Cuando NO lo hay, `[DECIDIDO owner]` (Q6) dice que **ni siquiera se pinta esta pantalla**: el
     * retorno de Google entra directo. Aquí se contempla igualmente el caso porque el modo puede
     * cambiar entre que se pinta y se envía, y porque un endpoint que asuma una configuración es un
     * endpoint que revienta el día que cambie.
     */
    private function waiverRequired(): bool
    {
        return WaiverSettings::isInternal()
            && LegalDocuments::current(WaiverSettings::SLUG, app()->getLocale()) !== null;
    }
}
