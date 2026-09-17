<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\InvitationReplyOutcome;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\PersonNameKey;
use App\Domain\Platform\Services\PublicFreeText;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * **La INVITACIÓN DIGITAL de una reserva y lo que contestan los padres**
 * (`docs/specs/celebracion-e-invitacion.md` §4.5, T4·2; `DECISIONES #574`).
 *
 * ❗❗ **Un padre no escribe `order_items`.** Lo que contesta vive en su propia tabla y se le PROPONE
 * al anfitrión, que la adopta al guardar por la puerta de siempre. Por eso este servicio **no toca
 * dinero ni aforo**, no bloquea `slots` y no roza ningún fichero del `CRITICAL_RE`.
 *
 * ## El lock, y por qué es UNA sola fila
 *
 * La exclusión que hace falta es entre **respuestas de la misma invitación** compitiendo por la última
 * plaza, y todas pasan por su fila de `party_invitations`. Bloquear ahí —y **solo ahí**— da esa
 * exclusión sin añadir ninguna arista al grafo de bloqueos del post-form, que ya tiene un ciclo
 * conocido entre `orders` e `items` (ficha en `DEUDA.md`). La reserva se lee fresca dentro de la misma
 * transacción, sin `FOR UPDATE`: no se escribe, y pedirle lock la metería en ese grafo para nada.
 *
 * ⚠️ `SEC-04` aplicado al tiempo: entre que el padre abre la página y contesta puede pasar la fiesta,
 * cancelarse el pedido, vencer el plazo **o llenarse la lista con otros «sí»**. Todo se re-comprueba
 * DENTRO, nunca solo al pintar.
 */
final class PartyInvitations
{
    /**
     * Tope de respuestas por invitación: `3 × invitados` (§4.5·12). Frena el spam de «no» —que no
     * ocupa plaza y por tanto no lo para la regla de lista completa— sin castigar el caso real, donde
     * una familia contesta, se equivoca y vuelve a contestar.
     */
    public const REPLY_CAP_PER_GUEST = 3;

    public function __construct(private GuestCountPolicy $policy) {}

    /**
     * La invitación de esta reserva, creándola si no existe.
     *
     * ⚠️ **Nace en el GET a propósito** (§4.5·1): Web Share necesita el enlace **en el mismo gesto**
     * del usuario, y un `fetch` previo pierde la activación en Safari. Un escáner de correos que abra
     * el enlace solo crea una fila vacía, sin nada que filtrar.
     *
     * ⚠️ El `firstOrCreate` puede chocar contra el único de `order_item_id` si dos peticiones entran a
     * la vez: **se relee, no se revienta**. Es el mismo trato que el resto del proyecto le da a una
     * carrera contra un índice que ya expresa la regla.
     *
     * `null` si el producto no ofrece invitación, o si su esquema por invitado no tiene columna de
     * nombre — sin ella no hay con qué emparejar y el interruptor no debería poder encenderse (T4·3).
     */
    public function forReservation(OrderItem $reservation): ?PartyInvitation
    {
        $type = $reservation->ticketType;

        if ($type === null || ! $type->guest_invitation || $type->guestNameFieldKey() === null) {
            return null;
        }

        $defaults = [
            'token' => PartyInvitation::freshToken(),
            'theme' => PartyInvitation::THEME_DEFAULT,
            'honoree_name' => $this->prefilledHonoreeName($reservation, $type),
            'honoree_age' => $this->prefilledHonoreeAge($reservation, $type),
            'host_line' => $this->prefilledHostLine($reservation),
            'show_host_phone' => false,
        ];

        try {
            return PartyInvitation::query()->firstOrCreate(
                ['order_item_id' => $reservation->getKey()],
                $defaults,
            );
        } catch (QueryException) {
            return PartyInvitation::query()->where('order_item_id', $reservation->getKey())->first();
        }
    }

    /**
     * ¿Se puede COMPARTIR?
     *
     * ⚠️⚠️ **Pasado el plazo se sigue compartiendo** (§7.2·R8), y es lo contrario de lo que la primera
     * versión de la spec decía: la información de la fiesta —dónde, a qué hora, qué menú— hace falta
     * justo **el día de la fiesta**, y cerrar el enlace con el plazo dejaría a los padres sin ella
     * precisamente cuando la necesitan. Lo único que el plazo cierra son las RESPUESTAS.
     */
    public function isShareable(OrderItem $reservation, ?PartyInvitation $invitation): bool
    {
        return $invitation !== null
            && trim((string) $invitation->honoree_name) !== ''
            && $this->policy->isOpenFor($reservation);
    }

    /**
     * Lo que contesta un padre, con la invitación bloqueada.
     *
     * @param  array<string, mixed>  $data  las columnas del pack que quiera dejar (G2), todas opcionales
     */
    public function reply(
        PartyInvitation $invitation,
        string $childName,
        bool $attending,
        ?string $companion = null,
        array $data = [],
    ): InvitationReplyOutcome {
        $childKey = PersonNameKey::for($childName);

        if ($childKey === '') {
            return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_NO_NAME);
        }

        $outcome = DB::transaction(function () use ($invitation, $childName, $childKey, $attending, $companion, $data): InvitationReplyOutcome {
            /** @var PartyInvitation|null $locked */
            $locked = PartyInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_CLOSED);
            }

            /** @var OrderItem|null $reservation */
            $reservation = OrderItem::query()
                ->with(['ticketType', 'slot', 'order'])
                ->whereKey($locked->order_item_id)
                ->first();

            if ($reservation === null || ! $this->policy->isOpenFor($reservation)) {
                return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_CLOSED);
            }
            // El MISMO plazo y la MISMA fuente que el número de invitados (D14): si el anfitrión ya no
            // puede mover su lista, un padre tampoco puede meterle a nadie.
            if (! $this->policy->isWithinWindow($reservation)) {
                return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_CUTOFF);
            }

            $quantity = max(0, (int) $reservation->quantity);
            $existing = $locked->replies()->get();

            if ($existing->count() >= self::REPLY_CAP_PER_GUEST * max(1, $quantity)) {
                return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_TOO_MANY);
            }

            // Un «no» NUNCA ocupa: no hay nada que comprobar contra la lista completa (D3).
            $joined = false;
            if ($attending) {
                $verdict = $this->placeFor($reservation, $existing, $childKey);
                if ($verdict === null) {
                    return InvitationReplyOutcome::refused(InvitationReplyOutcome::REASON_FULL);
                }
                $joined = $verdict;
            }

            $type = $reservation->ticketType;

            $reply = InvitationReply::query()->create([
                'party_invitation_id' => $locked->getKey(),
                'order_item_id' => $reservation->getKey(),
                'attending' => $attending,
                'child_name' => mb_substr(trim($childName), 0, InvitationReply::CHILD_NAME_MAX),
                'child_key' => $childKey,
                // Se sanea con el MISMO saneo del pack, tratando la respuesta como una fila: así una
                // clave inventada no entra y una edad fuera de rango no llega a la aritmética de un
                // suplemento el día que el anfitrión la adopte.
                'data' => $data === [] || $type === null ? null : ($type->sanitizeGuestData([$data], 1)[0] ?: null),
                'companion' => in_array($companion, InvitationReply::COMPANIONS, true) ? $companion : null,
            ]);

            // `RGPD-02`: el rastro NO lleva el nombre del niño ni sus datos. Solo qué reserva, si viene
            // o no, y si ocupó plaza nueva — que es lo que el operador necesita para entender la lista.
            AuditLogger::log('orders.invitation_reply_received', $reservation->order, [
                'order_code' => $reservation->order?->code,
                'order_item_id' => $reservation->getKey(),
                'attending' => $attending,
                'joined_existing_place' => $joined,
            ]);

            return InvitationReplyOutcome::accepted($reply, $joined);
        });

        return $outcome;
    }

    /**
     * ¿Cabe este «sí»? `null` = la lista está completa. `true` = se une a una plaza que ya tenía dueño
     * (no ocupa una nueva); `false` = ocupa una plaza libre.
     *
     * La cuenta de §4.5·3: **fichas con nombre** (acotadas a la cantidad) **+ «sí» pendientes,
     * distintos por `child_key`, que no emparejan con ninguna de esas fichas**.
     *
     * @param  Collection<int, InvitationReply>  $existing
     */
    private function placeFor(OrderItem $reservation, $existing, string $childKey): ?bool
    {
        $namedKeys = $this->namedGuestKeys($reservation);

        // Empareja con una ficha ya escrita (regla 4) → esa plaza ya tiene dueño y es el mismo niño.
        if ($this->matches($namedKeys, $childKey)) {
            return true;
        }

        // Repite un «sí» pendiente (regla 5, V6) → se une a su propuesta y no ocupa plaza nueva.
        $pending = $existing
            ->filter(fn (InvitationReply $r): bool => (bool) $r->attending && $r->isPending())
            ->pluck('child_key')
            ->unique();

        if ($pending->contains($childKey)) {
            return true;
        }

        // Los pendientes que NO emparejan con una ficha escrita son los que ya ocupan plaza propia.
        $pendingOwnPlaces = $pending
            ->reject(fn (string $key): bool => $this->matches($namedKeys, $key))
            ->count();

        $taken = count($namedKeys) + $pendingOwnPlaces;

        return $taken >= max(0, (int) $reservation->quantity) ? null : false;
    }

    /**
     * Las claves normalizadas de las fichas que YA tienen nombre, acotadas a la cantidad de la reserva.
     *
     * ⚠️ La columna de nombre es **la primera `text`** del esquema, no la clave `name`
     * ({@see TicketType::guestNameFieldKey()}): el esquema por invitado no tiene un tipo «nombre» y
     * una instalación puede renombrarla desde su panel.
     *
     * @return list<string>
     */
    private function namedGuestKeys(OrderItem $reservation): array
    {
        $type = $reservation->ticketType;
        $nameKey = $type?->guestNameFieldKey();
        if ($type === null || $nameKey === null) {
            return [];
        }

        $keys = [];
        foreach (array_slice($reservation->guestData(), 0, max(0, (int) $reservation->quantity)) as $row) {
            // Sin `is_array($row)`: `guestData()` declara `array<int, array<string,string>>` y
            // `sanitizeGuestData()` lo impone al escribir. Comprobarlo afirmaba una duda que el
            // contrato ya cierra — y Larastan lo dice.
            $name = trim((string) ($row[$nameKey] ?? ''));
            if ($name !== '') {
                $keys[] = PersonNameKey::for($name);
            }
        }

        return $keys;
    }

    /**
     * ¿La clave de la respuesta es la de alguna ficha, **o la de su primera palabra**?
     *
     * El segundo caso es el real y no un adorno: el anfitrión pega «Mateo» con el pegado de la T2 y el
     * padre escribe «Mateo Ruiz» — con apellidos, porque es lo que se le pide para distinguir a dos
     * niños que se llamen igual.
     *
     * @param  list<string>  $namedKeys
     */
    private function matches(array $namedKeys, string $childKey): bool
    {
        // `explode()` nunca devuelve lista vacía: sin `?? ''`, que afirmaría lo contrario.
        $firstWord = PersonNameKey::for(explode(' ', $childKey)[0]);

        foreach ($namedKeys as $key) {
            if ($key === $childKey || ($key !== '' && $key === $firstWord)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ▶ **El RECIBO de una respuesta (§4.5·6) NO está aquí, y es deliberado.** Es una URL firmada de
     * 2 horas atada a la fila, que abre las dos ofertas —dejar los datos del niño y decir si va un
     * adulto con él—; nombra la ruta de la página pública, **que nace en la T5**. Escribirlo hoy sería
     * un método que lanza en cuanto alguien lo llame y que ninguna prueba puede ejercer: el mismo
     * criterio con el que la T4·1 aplazó el contrato `PartyGuests`.
     * ⚠️ Lo que sí queda decidido y escrito en la spec: **2 horas y no es un enlace de edición** (D9).
     * Un enlace permanente convertiría cada respuesta en una credencial viva sobre los datos de un
     * menor, repartida por un grupo de clase entero.
     */

    /** El nombre del homenajeado que el anfitrión ya escribió al comprar, si se puede publicar. */
    private function prefilledHonoreeName(OrderItem $reservation, TicketType $type): string
    {
        $key = $type->celebrantNameFieldKey();
        $value = $key === null ? null : ($reservation->event_data[$key] ?? null);

        return PublicFreeText::clean(is_scalar($value) ? (string) $value : null, PartyInvitation::HONOREE_NAME_MAX) ?? '';
    }

    /**
     * La edad, leída por TIPO y SANEADA.
     *
     * ⚠️ `celebrantAgeFieldKey()` busca el tipo `celebrant_age`, nunca una clave llamada «age»: es la
     * regla que `#588` fijó, y leerla por nombre se rompería en la primera instalación que la
     * renombrara desde su panel.
     */
    private function prefilledHonoreeAge(OrderItem $reservation, TicketType $type): ?int
    {
        $key = $type->celebrantAgeFieldKey();
        if ($key === null) {
            return null;
        }

        $answers = $reservation->event_data ?? [];
        $value = $type->sanitizeEventData($answers, TicketType::EVENT_STAGE_BOOKING)[$key] ?? null;

        return is_numeric($value) ? max(0, min(255, (int) $value)) : null;
    }

    /** «Te invita …», rellenado con el nombre de la cuenta. El teléfono NO se copia: sale de la cuenta. */
    private function prefilledHostLine(OrderItem $reservation): string
    {
        return PublicFreeText::clean($reservation->order?->user?->name, PartyInvitation::HOST_LINE_MAX) ?? '';
    }
}
