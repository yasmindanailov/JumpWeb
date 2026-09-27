<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\AuthorizableReservation;
use App\Domain\Booking\Contracts\HonoreeWaivers;
use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Contracts\ReservationPlacesTaken;
use App\Domain\Booking\Contracts\SignedInvitationReplies;
use App\Domain\Identity\Contracts\HonoreeCoverage;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\GuardianAuthorization;
use DateTimeInterface;

/**
 * **Cuántos menores INVITADOS caben todavía en una reserva**
 * (`docs/specs/waiver-por-reserva.md` §13.3).
 *
 * ❗❗ **Lo encontró el owner con un pedido real**: compró UNA entrada, se la asignó a un menor a
 * cargo suyo y además marcó que venía un menor invitado. La pantalla decía *«0 justificantes
 * firmados · 3 plazas»* de una línea cuya única plaza ya tenía dueño.
 *
 * La cuenta es de una línea, y desde `#576` de cuatro sumandos:
 *
 *     libres = cantidad − menores a cargo YA asignados − justificantes YA firmados
 *                       − «sí» de la invitación digital que aún no han firmado
 *
 * ⚠️⚠️ **Vive en Identity y no en el contrato de Booking, y no es una preferencia**: la cantidad la
 * sabe Booking y los menores a cargo los sabe Identity — **Booking no puede mirar a Identity**
 * (`ModuleBoundariesTest`). El único sitio donde las dos cifras coexisten es aquí.
 *
 * ⚠️ **Una plaza de un menor a cargo NO es una plaza que pueda ocupar un invitado**, y eso es un
 * HECHO, no una estimación — a diferencia de «cuántos de los que vienen son menores», que §4.10
 * declara incognoscible y por eso no se inventa ningún denominador. Aquí sí se sabe: esa entrada
 * tiene nombre.
 *
 * ⚠️ Los ADULTOS no se restan: una entrada sin asignar puede ser un adulto o un menor invitado, y
 * suponer lo primero cerraría la puerta a quien tiene derecho a firmar. **La cota es superior a
 * propósito**: el tope existe para que nadie autorice a más gente de la que se ha comprado, no para
 * adivinar la composición del grupo.
 *
 * ▶ **Y desde `#444` es además el implementador de {@see ReservationPlacesTaken}**, el contrato por el
 * que Booking pregunta lo mismo sin poder mirar a Identity: es uno de los dos suelos de una bajada de
 * invitados desde el post-formulario. La frontera no cambia —sigue siendo Identity quien sabe de
 * menores—; lo que se publica es la RESPUESTA, no la consulta.
 *
 * ▶ **Y desde `#704`, también el de {@see SignedInvitationReplies}**, que es la MISMA pregunta que hace
 * {@see committedGuests()} —«¿esta respuesta ya tiene justificante atado?»— vista de una en una. Va
 * aquí y no en una clase nueva justamente por eso: dos dueños del mismo hecho acaban discrepando, y
 * este es el sitio donde las dos mitades ya coexistían.
 */
final class GuardianPlaces implements HonoreeWaivers, ReservationPlacesTaken, SignedInvitationReplies
{
    public function __construct(private readonly PartyGuests $guests) {}

    /** Plazas de la reserva que todavía podrían recibir un menor invitado. Nunca negativo. */
    public function freeIn(AuthorizableReservation $reservation): int
    {
        return max(0, $reservation->quantity - $this->takenIn($reservation->reservationId));
    }

    /**
     * Lo que ya tiene dueño en esa reserva: menores a cargo asignados + justificantes firmados **+ los
     * «sí» de la invitación digital que todavía no tienen justificante** (V4,
     * `specs/celebracion-e-invitacion.md` §4.5·8, `DECISIONES #576`).
     *
     * ⚠️ El tercer sumando entra para que **el suelo de `#444` proteja a un niño que confirmó**: sin él,
     * el anfitrión podría bajar los invitados por debajo de los «sí» que ya tiene y dejar fuera a quien
     * le había dicho que venía, sin que nada avisara.
     */
    public function takenIn(int $reservationId): int
    {
        return $this->assignedDependents($reservationId)
            + $this->authorizations($reservationId)
            + $this->committedGuests($reservationId)
            // ▶ Desde F3a (`#747`): la plaza de QUIEN CUMPLE, si la reserva la sella. Ocupa una plaza con dueño como
            // un «sí», así que el suelo no deja bajar por debajo de él + los confirmados ni se firma por encima.
            // ❗ Y desde F7 (`#752`) UNA sola vez: si ya la cubre su menor a cargo (una asignación) o su justificante, esa
            // prueba ya sumó arriba y aquí no se suma otra. Antes, firmar por él gastaba DOS plazas (medido: 13 → 12).
            + ($this->guests->honoreeSeatsIn($reservationId) > 0 && ! $this->honoreeCovered($reservationId) ? 1 : 0);
    }

    /**
     * **¿Quién cubre a quien cumple?** (`specs/fiesta-sistema-nuevo.md` §4.13, `#752`) — la ÚNICA respuesta, la que leen
     * la lista, la puerta, la víspera y la API. `null` si la reserva no sella a quien cumple.
     *
     * Por la ATADURA y NUNCA por el nombre (la regla de {@see isReplySigned()}): un justificante con `honoree`, o la
     * asignación de un menor a cargo sobre la línea del pack —un pack no admite otras (`DependentAssigner`)—. Emparejar
     * por nombre, lo de F3b, fallaba con «María José» y acertaba con cualquier «Lucía» (medido, §4.8).
     */
    public function honoreeCoverage(int $reservationId): ?HonoreeCoverage
    {
        if ($this->guests->honoreeSeatsIn($reservationId) < 1) {
            return null;
        }

        return $this->honoreeCoveragesOf([$reservationId])[$reservationId];
    }

    /**
     * La cobertura de VARIAS reservas que sellan a quien cumple, **en cuatro consultas como mucho, sean una o veinte**: la
     * de la puerta, que tiene un presupuesto medido (§7.2·R16, `GateProfileTest`) y no admite una lectura por fiesta. La
     * misma regla que {@see HonoreeCoverage()}, que la usa: una sola copia de la pregunta.
     *
     * ⚠️ `$sealed` son reservas que SELLAN a quien cumple, y lo dice quien llama (la puerta lo sabe por su contrato, sin
     * consultar): una línea de ENTRADA con asignaciones no es de quien cumple, y aquí no se vuelve a comprobar.
     *
     * @param  list<int>  $sealed
     * @return array<int, HonoreeCoverage> por id de reserva
     */
    public function honoreeCoveragesOf(array $sealed): array
    {
        $sealed = array_values(array_unique(array_map('intval', $sealed)));
        if ($sealed === []) {
            return [];
        }

        $internal = WaiverSettings::isInternal();
        $out = [];

        $authorizations = GuardianAuthorization::query()->whereIn('order_item_id', $sealed)->where('honoree', true)->get();
        $statuses = $internal && $authorizations->isNotEmpty() ? WaiverStatus::forGuestMinors($authorizations) : [];
        foreach ($authorizations as $a) {
            $out[(int) $a->order_item_id] = new HonoreeCoverage(
                HonoreeCoverage::AUTHORIZATION,
                (string) $a->minor_name,
                ($statuses[(int) $a->getKey()] ?? null)?->minorState(),
                authorizationId: (int) $a->getKey(),
                bornOn: $a->minor_born_on->toDateString(),
            );
        }

        $rest = array_values(array_diff($sealed, array_keys($out)));
        if ($rest !== []) {
            $assignments = DependentAssignment::query()->whereIn('order_item_id', $rest)->with('dependent')->orderBy('id')->get()
                ->filter(fn (DependentAssignment $a): bool => $a->dependent !== null)
                ->unique(fn (DependentAssignment $a): int => (int) $a->order_item_id);
            $dependents = $assignments->map(fn (DependentAssignment $a) => $a->dependent)->values();
            $dependentStatuses = $internal && $dependents->isNotEmpty() ? WaiverStatus::forDependents($dependents) : [];
            foreach ($assignments as $a) {
                $d = $a->dependent;
                $nacido = $d->getAttribute('born_on');
                $out[(int) $a->order_item_id] = new HonoreeCoverage(
                    HonoreeCoverage::DEPENDENT,
                    (string) $d->name,
                    ($dependentStatuses[(int) $d->getKey()] ?? null)?->minorState(),
                    dependentId: (int) $d->getKey(),
                    bornOn: $nacido instanceof DateTimeInterface ? $nacido->format('Y-m-d') : null,
                );
            }
        }

        foreach ($sealed as $id) {
            $out[$id] ??= HonoreeCoverage::none();
        }

        return $out;
    }

    /**
     * {@see HonoreeWaivers}: la pregunta de la víspera, con la misma respuesta que pinta la lista («Falta · Firmar su
     * descargo» = modo interno, la reserva lo sella y no está `signed()`).
     */
    public function honoreeWaiverMissing(int $reservationId): bool
    {
        if (! WaiverSettings::isInternal()) {
            return false;
        }

        $coverage = $this->honoreeCoverage($reservationId);

        return $coverage !== null && ! $coverage->signed();
    }

    /**
     * ¿Lo cubre ya una prueba atada? Sin mirar si su exención está vigente: la plaza es suya igual (una firma que quedó
     * «anterior» no la devuelve al montón). Es la pregunta de las plazas y la de los dos escritores, bajo su lock.
     */
    public function honoreeCovered(int $reservationId): bool
    {
        return GuardianAuthorization::query()->where('order_item_id', $reservationId)->where('honoree', true)->exists()
            || $this->honoreeAssignment($reservationId) !== null;
    }

    /**
     * La asignación de quien cumple: la única que admite una línea de pack (`DependentAssigner::assignHonoree()`).
     * ⚠️ Solo tiene sentido en una reserva que sella a quien cumple; en una entrada, sus asignaciones son otra cosa.
     */
    private function honoreeAssignment(int $reservationId): ?DependentAssignment
    {
        if ($this->guests->honoreeSeatsIn($reservationId) < 1) {
            return null;
        }

        return DependentAssignment::query()->where('order_item_id', $reservationId)->with('dependent')->orderBy('id')->first();
    }

    /**
     * Los «sí» vivos que **todavía no tienen justificante atado**.
     *
     * ⚠️⚠️ **La resta se hace AQUÍ y no en Booking, y es la razón de que el contrato devuelva ids.** Las
     * respuestas las sabe Booking y las firmas las sabe Identity: éste es el único sitio donde las dos
     * mitades coexisten — exactamente el motivo por el que esta clase existe desde `#401`. Si no se
     * restaran, un niño que dijo «sí» **y** firmó ocuparía dos plazas del suelo.
     *
     * ⚠️ **Lo que sí cuenta dos veces, declarado** (§4.5·8): un justificante SUELTO de un niño que
     * además dijo «sí» — porque su firma no viene atada a la respuesta y no hay forma de saber que son
     * el mismo niño sin comparar nombres, que es justo lo que `#328` decidió no hacer. El suelo sale
     * alto, que es el lado seguro: protege de más, nunca de menos.
     */
    public function committedGuests(int $reservationId): int
    {
        $ids = $this->guests->committedReplyIdsIn($reservationId);
        if ($ids === []) {
            return 0;
        }

        $signed = GuardianAuthorization::query()
            ->where('order_item_id', $reservationId)
            ->whereIn('invitation_reply_id', $ids)
            ->pluck('invitation_reply_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return count(array_diff($ids, $signed));
    }

    /**
     * ¿Esa respuesta de la invitación ya tiene justificante atado? (`#704`, §10.6·C)
     *
     * ⚠️ **Por la ATADURA y no por el nombre**: la firma que nace desde el recibo guarda de qué
     * respuesta viene, así que esto es un hecho. Comparar nombres es lo que `#328` descartó.
     *
     * ⚠️ No se acota a una reserva a propósito: `invitation_reply_id` apunta a UNA respuesta, que
     * pertenece a una sola reserva. Pedir además el id de la reserva daría dos fuentes para el mismo
     * vínculo y una de las dos podría mentir.
     */
    public function isReplySigned(int $replyId): bool
    {
        return GuardianAuthorization::query()->where('invitation_reply_id', $replyId)->exists();
    }

    /**
     * Cuántas plazas de esta reserva están asignadas a menores a cargo del titular.
     *
     * ⚠️ Se acota a la CANTIDAD de la línea igual que `DependentAssigner::forOrderItems()`: si la
     * línea bajó de unidades desde el panel, las asignaciones sobrantes ya no ocupan nada. Sin ese
     * tope, bajar una línea podría hacer que las plazas libres salieran negativas y el `max(0, …)`
     * escondería la incoherencia en vez de que la cuenta sea correcta.
     */
    public function assignedDependents(int $reservationId): int
    {
        return DependentAssignment::query()->where('order_item_id', $reservationId)->count();
    }

    /** Justificantes ya escritos para esa reserva. */
    public function authorizations(int $reservationId): int
    {
        return GuardianAuthorization::query()->where('order_item_id', $reservationId)->count();
    }

    /**
     * **Las claves de los menores con justificante en esa reserva** (`specs/fiesta-sistema-nuevo.md` §4.1): lo que
     * la lista de invitados del sistema nuevo necesita para decir «Firmada · Falta» en cada fila, y la respuesta
     * de la invitación a la que está atada cada firma (o `null` si es una firma suelta).
     *
     * ⚠️ Se publica la RESPUESTA y no la consulta, como el resto de esta clase: Booking no mira a Identity, y la
     * página cruza cada ficha con estas claves por `PersonNameKey::cardMatches()`, la misma regla que la puerta
     * (`GateProfile`). Aquí no se compara nada: se dice qué hay.
     *
     * ▶ Desde F7 (`#752`) dice también si esa firma es la de QUIEN CUMPLE: la lista la deja fuera del emparejado por nombre
     * de los invitados —un «Mateo» invitado no puede salir firmado por el justificante del Mateo que cumple—, y quien
     * cumple no se empareja por nombre con nada: lo dice `honoreeCoverage()`.
     *
     * @return list<array{key: string, reply_id: int|null, honoree: bool}>
     */
    public function signedMinorsIn(int $reservationId): array
    {
        return GuardianAuthorization::query()
            ->where('order_item_id', $reservationId)
            ->orderBy('id')
            ->get(['minor_key', 'invitation_reply_id', 'honoree'])
            ->map(static fn (GuardianAuthorization $a): array => [
                'key' => (string) $a->minor_key,
                'reply_id' => $a->invitation_reply_id === null ? null : (int) $a->invitation_reply_id,
                'honoree' => $a->honoree === true,
            ])
            ->all();
    }
}
