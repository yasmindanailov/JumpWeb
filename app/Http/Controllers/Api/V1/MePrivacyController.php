<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Exceptions\AccountHasUpcomingReservationsException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountAnalytics;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Http\Api\ApiCollection;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\Concerns\TranslatesCredentialVerdicts;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConsentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * **Los dos derechos RGPD del titular** (`specs/area-cliente.md` §9.3, tanda 2 · paso 8): borrar la
 * cuenta (art. 17) y descargarse sus datos (art. 20).
 *
 * Todo lo que decide vive en `Identity\Services\AccountPrivacy` —la reconfirmación con su limitador,
 * la purga y la composición del documento—; aquí se traduce a HTTP y, en el borrado, se cierra la
 * sesión de ESTA petición, que es lo único que el dominio deja a propósito en manos de la superficie.
 */
class MePrivacyController extends Controller
{
    use TranslatesCredentialVerdicts;

    /**
     * `DELETE /me` — derecho de supresión (art. 17).
     *
     * ⚠️⚠️ **NO borra la fila de `users`: la ANONIMIZA.** La FK `orders.user_id` es `RESTRICT` y las
     * facturas tienen que seguir vinculadas (AEAT, ≥4 años), así que la identidad se sobrescribe con
     * valores neutros y la historia contable se conserva **sin PII**. Para el titular el efecto es el
     * mismo —login imposible, datos personales fuera— y su email queda libre para re-registrarse.
     *
     * ⚠️ **Exige reconfirmar la contraseña**, con el mismo limitador que `PUT me/password`: es la
     * única gestión irreversible de las cinco.
     *
     * ⚠️ **`409` con una reserva por celebrar** (T5 · D8, `cumple-mixto.md` §25.4): la supresión
     * espera a que pase o se cancele, y el mensaje se lo explica — el cajón lo pinta tal cual
     * (`form-outcome.js` enseña el `error.message` de cualquier 4xx como aviso).
     */
    public function destroy(Request $request, AccountPrivacy $privacy): JsonResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string']]);

        /** @var User $user */
        $user = $request->user();

        try {
            $result = $privacy->anonymize($user, $data['current_password'], (string) $request->ip());
        } catch (AccountHasUpcomingReservationsException) {
            return ApiErrorResponse::make(ApiErrorCode::AccountHasUpcomingReservations, 409);
        }

        if ($result->failed()) {
            return $this->credentialDenial($result);
        }

        $this->closeCurrentSession($request);

        return response()->json(status: 204);
    }

    /**
     * `GET /me/consents` — los consentimientos otorgados por el titular.
     *
     * ⚠️ **Existe porque `/mi-cuenta` los enseña y esa página se retira** (tanda 3): sin publicarlos,
     * el borrado le quitaría al cliente la prueba visible del art. 7.1 que hoy tiene. Era la
     * condición 1.bis de la retirada.
     *
     * ⚠️ **Del más reciente al más antiguo, como la página.** El orden es contrato desde que esto es
     * una respuesta de API: dejarlo al motor haría que MySQL y el SQLite de la suite pudieran no
     * coincidir.
     */
    public function consents(Request $request): ApiCollection
    {
        /** @var User $user */
        $user = $request->user();

        return new ApiCollection(
            $user->consents()->orderByDesc('accepted_at')->orderByDesc('id')->get(),
            ConsentResource::class,
        );
    }

    /**
     * `PUT /me/marketing` — dar o **RETIRAR** el consentimiento de comunicaciones comerciales
     * (art. 7.3, `specs/auth-con-google.md` §9).
     *
     * ⚠️⚠️ **Es la pieza que cierra un incumplimiento vivo**, y no de las cuentas de Google: hasta hoy
     * el consentimiento se daba en el alta con un clic y **no había forma de retirarlo** — ninguna
     * ruta actualizaba `marketing_opt_in`—. El art. 7.3 exige que retirarlo sea *tan fácil como
     * darlo*, y por eso esto **no pide contraseña**: es exactamente igual de fácil en los dos
     * sentidos, y ponerle fricción solo a la retirada sería incumplirlo por otra puerta.
     *
     * ⚠️ **204 pase lo que pase**, incluida la llamada que no cambia nada: el titular pide un ESTADO,
     * no una transición, así que dos clics seguidos en «no quiero» dan el mismo desenlace. La
     * idempotencia la garantiza el dominio, que no escribe dos pruebas de lo mismo.
     */
    public function marketing(Request $request, AccountPrivacy $privacy): JsonResponse
    {
        $data = $request->validate(['accepted' => ['required', 'boolean']]);

        /** @var User $user */
        $user = $request->user();

        $privacy->setMarketing($user, (bool) $data['accepted'], (string) $request->ip());

        return response()->json(status: 204);
    }

    /**
     * `PUT /me/analytics` — vincular la navegación a la cuenta, o **OPONERSE** a ello (art. 21 y 7.3,
     * `specs/analitica.md` §4.3, T3a·3).
     *
     * El consentimiento es la categoría `analytics` del banner; esto es la puerta de la CUENTA para
     * retirarlo —desvincula lo registrado, sella la prueba y dispara el olvido en el driver— o para volver
     * a darlo. Sin contraseña y con 204 pase lo que pase, por las mismas dos razones que `marketing()`.
     */
    public function analytics(Request $request, AccountAnalytics $analytics): JsonResponse
    {
        $data = $request->validate(['accepted' => ['required', 'boolean']]);

        /** @var User $user */
        $user = $request->user();

        $analytics->setLinked($user, (bool) $data['accepted'], (string) $request->ip());

        return response()->json(status: 204);
    }

    /**
     * `DELETE /me/analytics-notice` — **despedir el aviso** de que la navegación puede vincularse a la cuenta
     * (T3a·4). El aviso viaja en el contexto de cuenta mientras está pendiente; esto deja la marca de que el
     * titular lo vio. 204 siempre, también cuando ya estaba despedido: el titular pide un ESTADO.
     */
    public function dismissAnalyticsNotice(Request $request, AccountAnalytics $analytics): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $analytics->markNoticeSeen($user);

        return response()->json(status: 204);
    }

    /**
     * `GET /me/export` — derecho de portabilidad (art. 20).
     *
     * ⚠️ **Devuelve el documento, no un fichero adjunto.** El `Content-Disposition` de la página web
     * es una decisión de PRESENTACIÓN de un navegador; un cliente de API —la SPA, el móvil— quiere el
     * cuerpo y decide él si lo guarda, lo enseña o lo manda a otro sitio. Poner la cabecera aquí
     * obligaría a los dos a pelearse con ella.
     *
     * ⚠️ **`no-store` es obligatorio** (`RGPD-04`) y lo estampa `Api\NoStoreWhenAuthenticated` en
     * TODA respuesta autenticada de `/api/v1` desde Fase 3 · paso 0. Es exactamente el caso que
     * justifica que sea por defecto y no ruta a ruta: éste es el cuerpo con más PII del producto
     * —perfil, consentimientos con IP y `event_data` con nombre y ALERGIAS de menores, art. 9— y en
     * una superficie que solo crece, lo que hay que acordarse de poner se acaba olvidando.
     *
     * @return array<string, mixed>
     */
    public function export(Request $request, AccountPrivacy $privacy): array
    {
        /** @var User $user */
        $user = $request->user();

        return $privacy->exportFor($user);
    }

    /**
     * Cierra la credencial con la que llegó ESTA petición.
     *
     * ⚠️ `anonymize()` ya revocó **todas** las credenciales del titular por `revokeAllAccess()`
     * —incluida la de esta petición: en el art. 17 no hay ninguna que conservar—, así que lo que
     * queda aquí es lo que vive en memoria durante la petición en curso. Es la misma receta que
     * `AuthSessionController::logout`, con sus dos matices ya medidos allí: `hasSession()` distingue
     * la vía de cookie de un Bearer puro —que no pasa por `StartSession` y no tiene sesión que
     * invalidar— y `forgetGuards()` hace falta porque los guards ya resueltos siguen cacheando al
     * usuario en memoria.
     *
     * ⚠️⚠️ **Y se deja la DESPEDIDA en la sesión nueva, que es lo que empareja las dos superficies.**
     * La web termina en `redirect('/')->with('status', 'account-deleted')` y el layout pinta ese
     * aviso; el cajón sale a `/` por su cuenta y sin esto llegaría a una home muda, con el cliente
     * sin saber si su cuenta se ha borrado de verdad. Va **después** de `invalidate()` a propósito:
     * aquél vacía la sesión y le da un identificador nuevo, así que un aviso puesto antes se
     * perdería. El mensaje viaja en la sesión ANÓNIMA que se acaba de crear — no queda nada del
     * titular en ella.
     */
    private function closeCurrentSession(Request $request): void
    {
        if ($request->hasSession()) {
            auth('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash('status', AccountPrivacy::FAREWELL_STATUS);
        }

        Auth::forgetGuards();
    }
}
