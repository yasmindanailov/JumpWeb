<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Services\PartyInvitations;
use App\Http\Concerns\AuthorizesGuestForm;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvitationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * **Lo que el ANFITRIÓN hace con su invitación** (`specs/celebracion-e-invitacion.md` §4.7 y §4.10,
 * T4·6; `DECISIONES #578`): personalizarla y quitar una respuesta que no quiere apuntar.
 *
 * **Se entra por la MISMA puerta que el formulario de invitados** —el trait de siempre, con su
 * escalada 403 → 410 → 404 y su resolución a mano sin *route model binding*—, y eso no es comodidad:
 * es su reserva, y abrir aquí una vía propia habría multiplicado por dos los sitios donde equivocarse
 * con quién puede tocar los datos de menores de una fiesta.
 *
 * ⚠️⚠️ **Es un endpoint aparte del `PUT` del formulario a propósito.** Personalizar escribe SOLO
 * `party_invitations`, y `order_items.updated_at` es el testigo con el que ese formulario detecta que
 * el parque movió la reserva: meterlo dentro dejaría obsoleta la página que el anfitrión tiene
 * abierta **por cambiar el color de una banda**.
 *
 * ▶ La ADOPCIÓN sí va en el `PUT` del formulario, y por el motivo contrario: adoptar convierte una
 * respuesta en una ficha, así que es exactamente el mismo gesto de guardar.
 */
class InvitationHostController extends Controller
{
    use AuthorizesGuestForm;

    public function __construct(private readonly PartyInvitations $invitations) {}

    /**
     * Personalizar: tema, quién cumple, la línea «Te invita» y si se enseña el teléfono.
     *
     * ⚠️ Un `honoree_name` o un `host_line` con un enlace o un correo **se rechaza sin 422**: ese
     * campo se queda como estaba y el resto se guarda. Lo decide el dominio (`PublicFreeText`), no
     * esta capa. Un 422 convertiría un descuido de redacción en un formulario que no guarda nada, y
     * limpiarlo a medias publicaría un texto que el anfitrión no escribió.
     */
    public function update(Request $request, int $reservation): InvitationResource|JsonResponse
    {
        $item = $this->resolveGuestFormReservation($reservation);

        $this->authorizeGuestFormAccess($request, $item);

        // Tras la escalada, `$item` no puede ser `null`: el peldaño 3 ya abortó con 404.
        /** @var OrderItem $item */
        $invitation = $this->invitations->forReservation($item);

        abort_if($invitation === null, 404);

        $data = $request->validate([
            'theme' => ['sometimes', 'string', 'max:16'],
            'honoree_name' => ['sometimes', 'string', 'max:'.PartyInvitation::HONOREE_NAME_MAX],
            'honoree_age' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'host_line' => ['sometimes', 'string', 'max:'.PartyInvitation::HOST_LINE_MAX],
            'show_host_phone' => ['sometimes', 'boolean'],
        ]);

        return new InvitationResource($this->invitations->personalize($invitation, $data), $item);
    }

    /**
     * «No lo apuntes»: el anfitrión retira una respuesta de su lista.
     *
     * ❗❗ Sin esto se queda ATRAPADO. Desde `#576` un «sí» pendiente es una plaza con dueño y sube el
     * suelo por debajo del cual no puede bajar el número de invitados: una respuesta que no quiere le
     * bloquearía esa bajada sin darle forma de retirarla.
     *
     * ⚠️ **204 también cuando ya estaba retirada**, y no es dejadez: el gesto es idempotente y dos
     * pestañas del mismo anfitrión no tienen por qué pelearse por quién llegó antes. Un 404 aquí
     * además distinguiría «no existe» de «ya estaba», que es información sobre su propia lista que no
     * hace falta dar para completar el gesto.
     */
    public function destroyReply(Request $request, int $reservation, int $reply): Response
    {
        $item = $this->resolveGuestFormReservation($reservation);

        $this->authorizeGuestFormAccess($request, $item);

        // El `where` de la reserva vive dentro de `dismiss()`: una respuesta de OTRA fiesta no se
        // toca aunque el id venga en esta URL.
        /** @var OrderItem $item */
        $this->invitations->dismiss($item, $reply);

        return response()->noContent();
    }
}
