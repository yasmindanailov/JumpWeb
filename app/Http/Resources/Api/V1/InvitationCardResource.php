<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Services\PartyInvitations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **La invitación vista por un DESCONOCIDO** (`specs/celebracion-e-invitacion.md` §4.6 y §4.10, T4·6;
 * `DECISIONES #578`). Sirve el esquema `InvitationCard`.
 *
 * ❗❗ **Es una HOJA EN BLANCO, y eso es lo único que hay que saber de este fichero.** No lleva ni una
 * respuesta, ni cuántos han contestado, **ni si un nombre concreto ya contestó**. Ése es exactamente
 * el motivo por el que su enlace se puede repartir a un grupo de clase entero por un chat de padres:
 * quien lo tiene ve la fiesta, pero **no puede averiguar quién va**.
 *
 * ⚠️⚠️ **Por eso NO es una vista recortada de {@see InvitationResource} con banderas**, sino un objeto
 * aparte que se construye campo a campo. Si fuera lo mismo filtrado, el día que alguien añadiera un
 * contador al objeto del anfitrión se filtraría aquí **sin que ninguna prueba lo notara** — y lo que
 * se filtra es quién va a la fiesta de un niño.
 *
 * @property-read PartyInvitation $resource
 */
class InvitationCardResource extends JsonResource
{
    /** El recurso va en la raíz (spec §4.3). */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reservation = $this->resource->reservation;

        return [
            'theme' => $this->resource->safeTheme(),
            'honoree_name' => (string) $this->resource->honoree_name,
            'honoree_age' => $this->resource->honoree_age === null ? null : (int) $this->resource->honoree_age,
            'host_line' => (string) $this->resource->host_line,
            'family_words' => (string) $this->resource->family_words,
            'gift_hints' => (string) $this->resource->gift_hints,
            // ⚠️ Sale de la CUENTA y nunca se teclea aquí (§4.5·12): el anfitrión solo decide si se
            // enseña. Un teléfono escrito a mano en una invitación pública sería un campo de texto
            // libre con el que mandar a la gente a llamar a donde quisiera.
            'host_phone' => $this->resource->show_host_phone
                ? ($reservation?->order?->user?->phone ?: null)
                : null,
            'date' => $reservation?->slot?->date?->toDateString(),
            // La ventana sale de la duración EFECTIVA de la reserva (base + hora extra), nunca del
            // fin de la franja: la rejilla es de 60 min y una fiesta de dos horas diría una hora de
            // menos — la trampa de `#426`, ya resuelta dentro de `displayTimeWindow()`.
            'time_window' => $reservation?->displayTimeWindow(),
            'product_name' => $reservation?->displayProductName(),
            'replies_open' => $reservation !== null && app(PartyInvitations::class)->repliesOpenFor($reservation),
        ];
    }
}
