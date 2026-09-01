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
            // `#330`: desde que el alta suelta abre sesión, el área de cuenta es donde se le pide al
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
            ],
        ];
    }
}
