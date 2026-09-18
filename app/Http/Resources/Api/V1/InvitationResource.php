<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Services\PartyInvitations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **La invitación digital vista por su ANFITRIÓN** (`specs/celebracion-e-invitacion.md` §4.10, T4·6;
 * `DECISIONES #578`). Sirve el esquema `Invitation` de `openapi/v1.yaml`.
 *
 * ⚠️⚠️ **No confundir con {@see InvitationCardResource}, y no son dos vistas del mismo objeto.** Éste
 * lleva el token, los contadores y las respuestas; aquél es lo que ve un desconocido y **no lleva ni
 * una**. Están separados a propósito: el día que alguien añada un campo aquí no puede filtrarse allí
 * por descuido, que es justo lo que pasaría si uno fuera un subconjunto del otro calculado con
 * banderas.
 *
 * @property-read PartyInvitation $resource
 */
class InvitationResource extends JsonResource
{
    /** El recurso va en la raíz (spec §4.3). */
    public static $wrap = null;

    public function __construct(PartyInvitation $invitation, private readonly OrderItem $reservation)
    {
        parent::__construct($invitation);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $invitations = app(PartyInvitations::class);
        $summary = $invitations->summaryFor($this->reservation);

        return [
            // `null` hasta que exista la página pública (T5), y se rellena solo — ver `shareUrlFor()`.
            'url' => $invitations->shareUrlFor($this->resource),
            'token' => (string) $this->resource->token,
            // Por el lector y no por la columna: un tema retirado de la lista cerrada cae al de por
            // defecto en vez de pedirle al cliente que sepa pintar algo que ya no existe.
            'theme' => $this->resource->safeTheme(),
            'honoree_name' => (string) $this->resource->honoree_name,
            'honoree_age' => $this->resource->honoree_age === null ? null : (int) $this->resource->honoree_age,
            'host_line' => (string) $this->resource->host_line,
            'show_host_phone' => (bool) $this->resource->show_host_phone,
            'shareable' => $invitations->isShareable($this->reservation, $this->resource),
            'replies_yes' => $summary['yes'],
            'replies_no' => $summary['no'],
            'replies_pending' => $summary['pending'],
            'pending_replies' => array_map(self::serializeProposal(...), $invitations->proposalsFor($this->reservation)),
        ];
    }

    /**
     * ⚠️ **Un array PHP vacío se serializa como `[]`, no como `{}`.** El contrato declara `guest_data`
     * como un objeto, así que una respuesta sin datos —el caso NORMAL: el padre solo dice que viene—
     * rompía la validación con «The data (array) must match the type: object». El dominio devuelve un
     * mapa; convertirlo es trabajo de esta capa, no del servicio.
     *
     * @param  array<string, mixed>  $proposal
     * @return array<string, mixed>
     */
    private static function serializeProposal(array $proposal): array
    {
        $proposal['guest_data'] = (object) $proposal['guest_data'];

        return $proposal;
    }
}
