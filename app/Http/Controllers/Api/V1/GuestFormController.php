<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Concerns\AuthorizesGuestForm;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GuestFormResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 5 — el POST-FORM de invitados por API (`docs/specs/api-v1.md` §4.4 y §4.6.5).
 *
 * Es el **segundo consumidor** de la API, y se eligió precisamente porque obliga a resolver algo que
 * ningún otro endpoint plantea: **cómo autentica la API a un portador de firma**. El resto de la
 * superficie es pública o va con sesión; esto es un enlace que viaja por correo semanas antes del
 * evento, sin cuenta detrás.
 *
 * **El canje** (lo que §4.6.5 dejó pendiente). La firma de Laravel cubre la URL EXACTA, así que la
 * del correo —que apunta a una ruta web— no autoriza un `PUT /api/v1/...`: reenviar su `signature`
 * a otro path no valida. La salida no es un almacén de credenciales nuevo, sino firmar también la
 * URL de la API con la MISMA caducidad y entregarla a quien ya demostró acceso
 * (`OrderItem::guestFormApiUrls()`): la página que abre el enlace del correo la recibe, y el propio
 * `GET` de aquí devuelve la de guardar. Mismo alcance —una reserva—, misma vida y misma prueba
 * (HMAC del servidor) que el modelo que la web ya usaba: no se relaja nada.
 *
 * **La reserva se resuelve A MANO, sin *route model binding*.** Con binding implícito, un
 * identificador inexistente daría 404 ANTES de comprobar la autorización, y ese 404 —frente al 403
 * de uno existente— le contaría a un desconocido qué reservas hay. La escalada 403 → 410 → 404 es
 * deliberada (spec §6.6) y solo se sostiene si nada responde antes que ella.
 *
 * **`no-store` va en la ruta, no heredado.** Este endpoint es accesible SIN sesión, así que el
 * `no-store` por defecto de la superficie autenticada no lo cubriría — y lo que devuelve son nombres
 * y alergias de menores (`RGPD-04`, dato de salud del art. 9).
 */
class GuestFormController extends Controller
{
    use AuthorizesGuestForm;

    /** El formulario: su esquema, lo ya rellenado, el progreso y a dónde guardar. */
    public function show(Request $request, int $reservation): GuestFormResource
    {
        $item = $this->resolve($reservation);

        $this->authorizeGuestFormAccess($request, $item);

        return new GuestFormResource($item);
    }

    /**
     * Guarda el formulario. **`PUT` y no `PATCH`**: el envío sustituye la lista de invitados entera
     * —el cliente manda las N fichas que hay ahora—, no la parchea ficha a ficha.
     *
     * Lo que se persiste lo decide el dominio (`OrderItem::submitGuestForm()`), que es el mismo que
     * usa la web: saneado contra el esquema y contra la cantidad ACTUAL de invitados, mezcla que
     * preserva los datos de la fase de reserva, sello de completado y rastro sin PII.
     */
    public function update(Request $request, int $reservation): GuestFormResource|JsonResponse
    {
        $item = $this->resolve($reservation);

        $this->authorizeGuestFormAccess($request, $item);

        // El post-form sirve para PREPARAR la fiesta: pasada, se consulta pero no se edita. Un 409 y
        // no un 403 — el permiso no ha cambiado, ha cambiado el momento—, y se comprueba en servidor
        // aunque la interfaz ya no ofrezca editar: una pestaña vieja o un cliente propio no lo saben.
        if ($item->isFinishedInPractice()) {
            return ApiErrorResponse::make(ApiErrorCode::GuestFormClosed, 409);
        }

        $validated = $request->validate([
            // Las CLAVES de cada ficha son data-driven (`guest_fields` del pack), así que aquí solo
            // se valida la FORMA; qué campos existen y cuáles son obligatorios lo sabe el esquema, y
            // el saneado del dominio descarta lo que no reconozca.
            'guests' => ['sometimes', 'array', 'max:'.self::MAX_GUESTS],
            'guests.*' => ['array'],
            'general' => ['sometimes', 'array'],
        ]);

        // ⚠️⚠️ La ausencia de una clave significa «no la toques», NUNCA «vacíala». El `?? []` que
        // había aquí hacía que un `PUT` con solo `general` **borrara las fichas de los menores**
        // (medido: 8 filas vaciadas, respuesta 200) — y el propio contrato lo documentaba como una
        // virtud, «se guarda a trozos». Guardar a trozos borraba el otro trozo.
        $item->submitGuestForm(
            $this->submittedGuestFormArray($request, 'guests', $validated),
            $this->submittedGuestFormArray($request, 'general', $validated),
            $this->guestFormVia($request),
        );

        return new GuestFormResource($item->fresh(['ticketType', 'order', 'slot']));
    }

    /**
     * Tope de fichas del cuerpo. El dominio recorta a la cantidad real de invitados de la reserva,
     * así que esto no es la regla: es lo que impide que un cuerpo desmedido llegue a recorrerse.
     */
    private const MAX_GUESTS = 200;

    /** Reserva por id, o `null`. Sin `firstOrFail`: el «no existe» lo decide la escalada, no esto. */
    private function resolve(int $reservation): ?OrderItem
    {
        return OrderItem::query()
            ->with(['ticketType', 'order.user', 'slot'])
            ->whereKey($reservation)
            ->first();
    }
}
