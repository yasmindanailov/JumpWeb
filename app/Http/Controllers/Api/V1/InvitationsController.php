<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\InvitationReplyOutcome;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Services\PartyInvitations;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvitationCardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * **La invitación digital vista desde fuera** (`specs/celebracion-e-invitacion.md` §4.6 y §4.10, T4·6;
 * `DECISIONES #578`).
 *
 * Es la única superficie de la API cuya credencial es **un token en la URL** —doce caracteres opacos,
 * una fila que el operador puede anular— y no una sesión ni una firma HMAC. La razón es el uso real:
 * el enlace se reparte a un grupo de clase entero por un chat de padres, así que no puede exigir
 * cuenta y tiene que poder cerrarse sin esperar a ninguna caducidad.
 *
 * ## Un solo «no», y siempre el mismo
 *
 * Token inexistente, enlace anulado, pedido cancelado, invitación apagada en el producto o titular
 * anonimizado responden **el mismo 404** (§7.2·R10). Distinguirlos sería un ORÁCULO: un 410 para
 * «cancelada» frente a un 404 para «no existe» le confirmaría a un desconocido que ese token existió.
 * Toda esa decisión vive en `PartyInvitations::resolvePublic()`, en un solo sitio, para que no pueda
 * divergir entre el `GET` y el `POST`.
 *
 * ## `no-store` explícito
 *
 * Se entra **sin sesión**, así que el `no-store` por defecto de la superficie autenticada no cubre
 * estas rutas — y lo que sirven es el nombre y la edad de un menor (`RGPD-04`). Va en la ruta.
 */
class InvitationsController extends Controller
{
    public function __construct(private readonly PartyInvitations $invitations) {}

    /** La tarjeta pública. Una HOJA EN BLANCO: ni una respuesta, ni un contador. */
    public function show(string $token): InvitationCardResource
    {
        $invitation = $this->invitations->resolvePublic($token);

        abort_if($invitation === null, 404);

        return new InvitationCardResource($invitation);
    }

    /**
     * Lo que contesta un padre.
     *
     * ⚠️ **200 también cuando no se acepta.** Que la lista esté completa o que haya pasado el plazo no
     * es un error del padre: un 4xx haría que cualquier cliente lo pintara como «algo ha fallado» en
     * vez de como la respuesta que es. Los 4xx quedan para lo que sí es culpa de quien llama — 422 por
     * la forma del cuerpo, 404 por un token que no sirve.
     *
     * ⚠️⚠️ **La re-comprobación es de `reply()`, no de aquí.** `resolvePublic()` dice que el enlace
     * abre; quién cabe, el plazo y el tope se deciden **bajo el lock de la invitación**, dentro. Entre
     * que el padre abre la página y pulsa pueden pasar la fiesta y los demás «sí».
     */
    public function reply(Request $request, string $token): JsonResponse
    {
        $invitation = $this->invitations->resolvePublic($token);

        abort_if($invitation === null, 404);

        // ⚠️ Se valida **después** de resolver el token, y ese orden es la propiedad: validar antes
        // haría que un cuerpo mal formado diera 422 con un token inventado y 422 con uno real, pero
        // un cuerpo BIEN formado distinguiría los dos casos — y ya habríamos contado que existe.
        $data = $request->validate([
            'child_name' => ['required', 'string', 'min:1', 'max:'.InvitationReply::CHILD_NAME_MAX],
            'attending' => ['required', 'boolean'],
            'companion' => ['sometimes', 'nullable', 'string', Rule::in(InvitationReply::COMPANIONS)],
            // Solo la FORMA: las claves son data-driven (`guest_fields` del pack) y el saneo del
            // dominio descarta lo que el esquema no declare — el mismo trato que el post-form.
            'guest_data' => ['sometimes', 'nullable', 'array'],
        ]);

        $outcome = $this->invitations->reply(
            $invitation,
            (string) $data['child_name'],
            (bool) $data['attending'],
            is_string($data['companion'] ?? null) ? $data['companion'] : null,
            is_array($data['guest_data'] ?? null) ? $data['guest_data'] : [],
        );

        return response()->json($this->result($outcome));
    }

    /**
     * El desenlace, **acotado a propósito** (§7.2·R1).
     *
     * ❗❗ Un nombre repetido sale como `accepted: true` y sin motivo, exactamente igual que la primera
     * vez. **No existe un motivo «repetido»**, y su ausencia es la propiedad: decir «ya nos habéis
     * contestado por Hugo» le confirmaría a cualquiera con el enlace que Hugo va a esa fiesta, y
     * bastaba con ir probando nombres. La hoja es en blanco también en sus errores.
     *
     * @return array{accepted: bool, reason: string|null}
     */
    private function result(InvitationReplyOutcome $outcome): array
    {
        return [
            'accepted' => $outcome->accepted,
            'reason' => $outcome->reason,
        ];
    }
}
