<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El **contexto de cuenta** del cliente autenticado: cómo saludarle, qué tiene por delante y qué le
 * falta por rellenar (`docs/specs/account-context-vue.md` §4.4).
 *
 * Envuelve la salida de `Identity\Services\CustomerAccountContext::for()` — el mismo servicio que
 * lleva alimentando el nav y el bloque de cuenta desde `#221`, y que a su vez pide las reservas al
 * contrato `Booking\Contracts\CustomerReservations`. **Aquí no se decide ninguna regla**: qué cuenta
 * como próxima reserva y qué formulario está pendiente lo decide Booking, y este Resource solo
 * renombra a la convención de la API.
 *
 * ⚠️⚠️ **ES LA FORMA ÚNICA, y por eso lo usan DOS caminos**: este endpoint y la semilla que el
 * servidor inyecta en el montaje del cajón (`layout.blade.php` → `data-boot`). Componer la misma
 * respuesta en dos sitios es exactamente como dos formas del mismo dato acaban divergiendo, y la
 * poda de la semilla —que lleva menos de lo que publica el contrato— se hace **al sembrar**, nunca
 * cambiando esto: manda `openapi/v1.yaml`.
 *
 * ⚠️ **`next_reservation` DELEGA en {@see UpcomingReservationResource}** en vez de recomponer sus
 * cuatro campos. No es estilo: `GET /me/reservations` ya publica esa forma, y escribirla por segunda
 * vez pondría dos definiciones de «reserva próxima» en la MISMA API — incluida la etiqueta de día,
 * cuya fuente única (`DisplayTime::dayLabel`) vigila `DayLabelSingleSourceTest`. Se llama a
 * `resolve()` para que lo que salga de aquí sea un array plano en los dos caminos: el de la API lo
 * serializaría igual, pero el de la semilla necesita array para poder podarlo y medirlo.
 *
 * ⚠️⚠️ **`pending_forms[].url` es la ruta WEB y va SIN FIRMAR.** La compone
 * `route('reservation.guests', …)` en Identity, y quien autoriza es `Http\Concerns\AuthorizesGuestForm`,
 * que acepta **firma O titularidad de sesión** con su escalada 403 → 410 → 404. Es el mismo campo que
 * `OrderItemResource` publica como `guest_form_url`.
 * ▶ **PROHIBIDO publicar aquí la URL firmada** (`OrderItem::guestFormSignedUrl()` o
 * `guestFormApiUrls()`): sería una credencial portadora, sin sesión y válida durante días, que abre
 * **y reescribe** nombres y alergias de menores (art. 9) — y esta misma respuesta se siembra en el
 * HTML de cada página con sesión. Lo fija `MeAccountContextTest`.
 *
 * ⚠️ **Un cliente por Bearer no puede seguir esa URL** (recibiría 403: la autoriza la sesión web).
 * Cuando Fase 6 necesite abrir el post-form desde una app nativa, será un campo APARTE firmado con
 * `guestFormApiUrls()`, no este.
 */
class AccountContextResource extends JsonResource
{
    /** El recurso va en la raíz: lo devuelve el propio controlador (spec de la API §4.3). */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $context */
        $context = $this->resource;

        return [
            'first_name' => (string) $context['firstName'],
            // `#331`: desde que el alta suelta abre sesión, el área de cuenta es donde se le pide al
            // cliente que verifique su correo — y para pedírselo hay que saber si le falta.
            'email_verified' => (bool) ($context['emailVerified'] ?? true),
            'upcoming_count' => (int) $context['upcomingCount'],
            'next_reservation' => $context['nextReservation'] === null
                ? null
                : UpcomingReservationResource::make($context['nextReservation'])->resolve($request),
            'pending_forms' => array_map(
                /** @param array{productName: string, url: string} $form */
                static fn (array $form): array => [
                    'product_name' => $form['productName'],
                    'url' => $form['url'],
                ],
                $context['pendingForms'],
            ),
            'pending_forms_count' => (int) $context['pendingFormsCount'],
            // Fase 6 · waiver (§4.8): si hay que firmar y qué texto. `document_id` es el mismo id que
            // publica `GET /legal/waiver` en este idioma — dos caminos, un dato.
            'waiver' => [
                'mode' => (string) $context['waiver']['mode'],
                'required' => (bool) $context['waiver']['required'],
                'pending' => (bool) ($context['waiver']['pending'] ?? false),
                'outdated' => (bool) $context['waiver']['outdated'],
                'document_id' => $context['waiver']['documentId'] === null ? null : (int) $context['waiver']['documentId'],
                // `#441` · si alguno de sus MENORES sigue sin firma vigente. Va DENTRO de `waiver`
                // porque es del mismo hecho —qué le falta a esta cuenta de la exención— y porque así
                // el cliente lo lee del mismo sitio que el resto; el aviso del índice lo compone
                // `account/waiver.js`.
                'dependents_pending' => (bool) ($context['waiver']['dependentsPending'] ?? false),
            ],
            // `#349`: si le faltan las CONDICIONES antes de poder contratar. Es el mismo tipo de hecho
            // que `waiver.pending` y viaja por el mismo sitio.
            // ⚠️ Es una PISTA para saber qué pintar, no la autoridad: quien decide es el servidor al
            // crear el pedido, y el cliente sabe reaccionar a su 422 aunque esta pista se equivoque.
            'terms_pending' => (bool) ($context['termsPending'] ?? false),
            // ⚠️ **`pending` decide si se PIDE; `updated` decide qué se DICE**, y son dos hechos. Sin
            // el segundo no se puede cumplir lo que el owner pidió —«se pide de nuevo diciendo que las
            // condiciones se han actualizado»— sin mentirle a quien nunca las aceptó.
            'terms_updated' => (bool) ($context['termsUpdated'] ?? false),
            // ⚠️ «missing», no «pending»: el teléfono no espera una decisión del titular, sencillamente
            // no está. Y por eso la pantalla pinta un CAMPO y no una casilla.
            'phone_missing' => (bool) ($context['phoneMissing'] ?? false),
            // D15 · **la INVITACIÓN a añadir extras**, que no es la deuda de arriba: `pending_forms`
            // dice «te faltan datos» y esto dice «todavía puedes añadir algo». Van separadas porque
            // meterlas en la misma lista haría que «tienes 2 formularios pendientes» contase como
            // pendiente uno que está completo.
            //
            // ⚠️ Una sola reserva y no una lista: el aviso NOMBRA un producto, y la lista es la única
            // parte del contexto sin cota —la razón por la que `pending_forms` se poda en la semilla—.
            // Va a la COLA: `ApiContractTest` compara `required` con las propiedades EN ORDEN.
            'extras_invite' => ($context['extrasInvite'] ?? null) === null ? null : [
                'product_name' => (string) $context['extrasInvite']['productName'],
                'url' => (string) $context['extrasInvite']['url'],
            ],
            // T3a·4 de la analítica: el aviso de que la navegación puede vincularse a la cuenta, CON su texto en
            // el idioma de la petición, solo mientras está pendiente (`DELETE /me/analytics-notice` lo despide).
            // `null` el resto del tiempo, y la clave viaja siempre: el cliente no distingue «no está» de «vacío».
            // A la COLA, como `extras_invite`: `ApiContractTest` compara `required` con las propiedades EN ORDEN.
            'analytics_notice' => ($context['analyticsNotice'] ?? null) === null ? null : [
                'text' => (string) $context['analyticsNotice']['text'],
                'dismiss' => (string) $context['analyticsNotice']['dismiss'],
            ],
        ];
    }
}
