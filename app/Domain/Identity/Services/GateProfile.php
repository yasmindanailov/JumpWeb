<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\GateReservation;
use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Identity\Contracts\GateProfileData;
use App\Domain\Identity\Models\CustomerCard;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\PersonNameKey;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Fase 6 · subsistema A — COMPONE la ficha de puerta en UNA lectura con presupuesto
 * (`docs/specs/identidad-qr-puerta.md` §4.6, §4.11, §9.2 A·3).
 *
 * Es la forma que este repo ya resolvió una vez (`CustomerAccountContext`): un escaneo pide siete u
 * ocho cosas y en hora punta se escanean decenas; si la ficha la ensamblara el componente, la app
 * nativa de mañana tendría que reescribirla. Aquí se compone una vez, el componente pinta y el
 * endpoint la envuelve.
 *
 * Fuentes: las reservas y su dinero por `Booking\Contracts\GateReservations` (Identity no ve a
 * Booking); el waiver por `WaiverStatus`; los menores por `DependentAssigner::forOrderItems()` y
 * `WaiverStatus::forDependents()` —**nombre de pila, edad y estado de la exención; los apellidos
 * NO** (A·7, revisado en `#236`)—; el carné por `CustomerCards`; la visita por `GateVisits`.
 */
final class GateProfile
{
    public function __construct(
        private readonly GateReservations $reservations,
        private readonly DependentAssigner $assigner,
        private readonly CustomerCards $cards,
        private readonly GateVisits $visits,
        // Los niños que han dicho que vienen (T6·4). ⚠️ Por CONTRATO: las respuestas son de Booking,
        // que Identity no puede mirar (`ModuleBoundariesTest`).
        private readonly PartyGuests $guests,
    ) {}

    public function for(User $holder, CarbonInterface $today, int $windowDays): GateProfileData
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $today->toDateString(), 'UTC');
        $from = $day->subDays(max(0, $windowDays))->toDateString();
        $to = $day->addDays(max(0, $windowDays))->toDateString();

        $reservations = $this->reservations->forHolder((int) $holder->getKey(), $from, $to);
        $minorsByItem = $this->minorsByItem($reservations);

        $rows = array_map(
            fn (GateReservation $r): array => $this->row($r, $minorsByItem[$r->orderItemId] ?? []),
            $reservations,
        );
        $todayRows = array_values(array_filter($rows, fn (array $row): bool => $row['date'] === $day->toDateString()));
        $windowRows = array_values(array_filter($rows, fn (array $row): bool => $row['date'] !== $day->toDateString()));

        // Los menores INVITADOS (`specs/waiver-por-reserva.md` §4.11) de las reservas de HOY.
        //
        // ⚠️⚠️ Van al NIVEL de la ficha y no dentro de cada fila, y no es estética: la autorización
        // cuelga del PEDIDO, así que un pedido con tres líneas hoy repetiría la misma lista tres
        // veces. Se agrupa aquí, en la COMPOSICIÓN — decidirlo en el Blade es como nacen los N+1.
        $guestMinors = $this->guestMinors($reservations, $day);

        $waiver = WaiverStatus::for($holder);

        return new GateProfileData(
            userId: (int) $holder->getKey(),
            holderName: (string) $holder->name,
            today: $day->toDateString(),
            waiver: [
                'enabled' => $waiver->isEnabled(),
                'signed' => $waiver->signed,
                'accepted_on' => $waiver->acceptedAt?->toDateString(),
                'outdated' => $waiver->isOutdated(),
                // `#336` — ¿hay una ACEPTACIÓN RETENIDA que el operador pueda dar por firmada con la
                // persona delante? Es lo único que separa «no ha firmado» de «leyó el texto, lo
                // aceptó y solo le falta verificar el correo», y de esa distinción depende que la
                // puerta ofrezca un gesto o pase la tablet.
                'pending_acceptance' => $holder->waiver_pending_document_id !== null,
            ],
            card: $this->cardState($holder),
            today_reservations: $todayRows,
            window: $windowRows,
            windowDays: max(0, $windowDays),
            dependents: $this->dependents($holder, $day),
            guestMinors: $guestMinors['rows'],
            // «8 de 12 con justificante» (T6·4, §4.8): `null` cuando hoy no hay ninguna fiesta con
            // invitación — una cuenta de «0 de 0» sería ruido en la pantalla que más se mira.
            guestMinorsCount: $guestMinors['expected'] > 0
                ? ['signed' => $guestMinors['signed'], 'expected' => $guestMinors['expected']]
                : null,
            visitRegisteredToday: $this->visits->registeredOn($holder, $day),
        );
    }

    /**
     * @param  list<array{name: string, age: int, waiver: ?string}>  $minors
     * @return array<string, mixed>
     */
    private function row(GateReservation $r, array $minors): array
    {
        return [
            'order_code' => $r->orderCode,
            'order_item_id' => $r->orderItemId,
            'date' => $r->date,
            'time_window' => $r->timeWindow,
            'product' => $r->productName,
            'is_entry' => $r->isEntry,
            'quantity' => $r->quantity,
            'addons' => $r->addons,
            // El libro de la reserva (T3·2): lo pagado y el SALDO con su clase y su signo.
            'paid_cents' => $r->paidCents,
            'balance_kind' => $r->balanceKind,
            'balance_cents' => $r->balanceCents,
            'charge_method' => $r->chargeMethod,
            'paid_at' => $r->paidAt,
            'created_at' => $r->createdAt,
            'minors' => $minors,
            // T3 · E (`specs/cumple-mixto.md` §23.2): lo ESCRITO del suplemento de fiesta mixta,
            // tal cual viaja en el contrato — la tarjeta lo pinta bajo el producto para que el
            // empleado no haga la cuenta de memoria con el cliente delante. Desde la T4 el total
            // es el NETO (cargo − descuento) y viajan además el descuento con su frase y el
            // (T3·3 del libro: el «a tu favor» murió con el tope — el saldo lo dice `balance_*`).
            'mixed_party_lines' => $r->mixedPartyLines,
            'mixed_party_surcharge_cents' => $r->mixedPartySurchargeCents,
            'mixed_party_credit' => $r->mixedPartyCredit,
        ];
    }

    /**
     * Los menores asignados a cada línea, como `{name, age, waiver}` — la edad en la FECHA DE LA
     * VISITA de esa línea (D13 de menores) y el estado de su exención por lotes. El nombre de pila
     * entra en `#236`; los apellidos siguen sin tener campo.
     *
     * @param  list<GateReservation>  $reservations
     * @return array<int, list<array{name: string, age: int, waiver: ?string}>>
     */
    private function minorsByItem(array $reservations): array
    {
        $entries = array_values(array_filter($reservations, fn (GateReservation $r): bool => $r->isEntry));
        if ($entries === []) {
            return [];
        }

        $byItem = $this->assigner->forOrderItems(
            array_map(fn (GateReservation $r): int => $r->orderItemId, $entries),
            array_combine(
                array_map(fn (GateReservation $r): int => $r->orderItemId, $entries),
                array_map(fn (GateReservation $r): int => $r->quantity, $entries),
            ),
        );
        if ($byItem === []) {
            return [];
        }

        $all = collect($byItem)->flatten(1)->unique(fn (Dependent $d): int => (int) $d->getKey());
        $statuses = WaiverSettings::isInternal() ? WaiverStatus::forDependents($all) : [];
        $dateByItem = [];
        foreach ($entries as $r) {
            $dateByItem[$r->orderItemId] = $r->date;
        }

        $out = [];
        foreach ($byItem as $itemId => $dependents) {
            $visit = CarbonImmutable::createFromFormat('!Y-m-d', $dateByItem[$itemId], 'UTC');
            foreach ($dependents as $dependent) {
                $out[(int) $itemId][] = self::minor($dependent, $visit, $statuses[(int) $dependent->getKey()] ?? null);
            }
        }

        return $out;
    }

    /**
     * Los menores INVITADOS autorizados en las RESERVAS de HOY, con su edad EN LA
     * FECHA DE LA VISITA (la misma regla D13 que los menores a cargo) y el estado de su
     * justificante.
     *
     * ⚠️ **Presupuesto**: TRES consultas como mucho —las autorizaciones de las reservas del día, sus
     * firmas por lotes y, **solo si hoy hay una fiesta con invitación**, las respuestas de todas ellas
     * en un viaje—, sean uno o veinte niños. `GateProfileTest` fija el techo en 28 y ya cazó un N+1 en
     * `#294`; si esto creciera por fila, lo cazaría otra vez.
     *
     * ⚠️ Se acota a HOY a propósito: la ventana de ±N días es contexto, y los justificantes son para
     * dejar entrar a alguien que está delante.
     *
     * @param  list<GateReservation>  $reservations
     * @return array{rows: list<array{order_code: string, name: string, age: int|null, waiver: ?string, entry: ?string}>, signed: int, expected: int}
     */
    private function guestMinors(array $reservations, CarbonImmutable $day): array
    {
        $today = array_values(array_filter($reservations, fn (GateReservation $r): bool => $r->date === $day->toDateString()));

        return $today === []
            ? ['rows' => [], 'signed' => 0, 'expected' => 0]
            : $this->composeGuestMinors($today, $day);
    }

    /**
     * **Los niños de las fiestas de hoy, con su estado de puerta** (T6·4, §4.8 y §4.5·10).
     *
     * Hasta la T6·4 esto listaba **solo justificantes firmados**, y por eso un niño que había dicho
     * que viene y aún no tenía firma **no existía para la puerta**: el mostrador se enteraba de que
     * faltaba media fiesta cuando la tenía delante. Ahora la lista son *las fichas con nombre del
     * anfitrión* **+** *los «sí» que él todavía no ha apuntado*, y de cada niño se dice en cuál de los
     * tres estados llega.
     *
     * ⚠️⚠️ **Los tres estados, y ninguno en rojo** (§4.5·10): `signed` hay justificante —atado a su
     * respuesta o emparejado por nombre—, `with_adult` el padre dijo que se queda, `unresolved` todo
     * lo demás, que **no es un error**: es trabajo que se hará en el mostrador si nadie lo adelanta.
     * `null` cuando el producto no pide justificante o el waiver no es interno — un punto que no
     * puede cambiar nada sería ruido.
     *
     * ⚠️ **Una firma que no empareja con ninguna ficha SIGUE saliendo**: un padre puede firmar por un
     * niño que el anfitrión nunca apuntó, y las reservas sin invitación son todas así. Esa es la lista
     * de siempre (`specs/waiver-por-reserva.md` §4.11), que aquí no se pierde.
     *
     * ⚠️ El NOMBRE de un niño invitado es **un solo campo de texto libre** —lo escribe el anfitrión o
     * su padre—, así que puede traer apellidos. No es la excepción de `#236` sino su motivo del revés:
     * ahí los apellidos viven en su columna y se retienen; aquí distinguir a dos «Martina» de una
     * clase es justo para lo que se piden (§4.5·5).
     *
     * @param  list<GateReservation>  $today
     * @return array{rows: list<array{order_code: string, name: string, age: int|null, waiver: ?string, entry: ?string}>, signed: int, expected: int}
     */
    private function composeGuestMinors(array $today, CarbonImmutable $day): array
    {
        // ⚠️ **Por LÍNEA desde `#401`, no por pedido.** El justificante cuelga de la visita, así que
        // un pedido con una excursión hoy y una entrada el jueves ya no arrastra a la puerta los
        // menores de la otra fecha. La clave sigue siendo el CÓDIGO del pedido porque es lo que el
        // operador reconoce en pantalla, pero el filtro es la reserva.
        $codeByItem = [];
        foreach ($today as $r) {
            $codeByItem[$r->orderItemId] = $r->orderCode;
        }

        $authorizations = GuardianAuthorization::query()
            ->whereIn('order_item_id', array_keys($codeByItem))
            ->orderBy('id')
            ->get();

        // Las fiestas de hoy con la invitación encendida. ⚠️ **Sin ninguna no se pregunta nada**: la
        // puerta tiene un presupuesto medido (§7.2·R16) y un escaneo normal no puede pagar una
        // consulta por una feature que ese producto no ofrece.
        $parties = array_values(array_filter($today, static fn (GateReservation $r): bool => $r->invitationOffered));
        $repliesByItem = $parties === []
            ? []
            : $this->guests->partyGuestsIn(array_map(static fn (GateReservation $r): int => $r->orderItemId, $parties));

        // ⚠️ Sin salida temprana: aunque no haya ni una firma ni una respuesta, una fiesta de hoy
        // tiene que **contar** para «8 de 12» —el 12 es lo contratado— y sus fichas escritas siguen
        // siendo la lista de quién viene. Lo único que se ahorra es la consulta de las firmas.
        $statuses = $authorizations->isEmpty() ? [] : WaiverStatus::forGuestMinors($authorizations);
        $internal = WaiverSettings::isInternal();
        $rows = [];
        $matched = [];

        $signed = 0;
        $expected = 0;

        foreach ($parties as $r) {
            // La cuenta «8 de 12 con justificante» se hace sobre los invitados CONTRATADOS, no sobre
            // los que el anfitrión ha apuntado: al operador le importa cuántos niños se esperan y
            // cuántos llegan resueltos, y una lista a medias no puede esconder a los que faltan.
            $expected += max(0, $r->quantity);

            foreach ($this->partyChildren($r, $repliesByItem[$r->orderItemId] ?? []) as $child) {
                $auth = $this->authorizationFor($authorizations, $r->orderItemId, $child);
                if ($auth !== null) {
                    $matched[] = (int) $auth->getKey();
                    $signed++;
                }

                $rows[] = [
                    'order_code' => $r->orderCode,
                    // Con firma manda el nombre del JUSTIFICANTE, que es el documento; sin ella, el
                    // que escribieron en la lista.
                    'name' => $auth !== null ? (string) $auth->minor_name : $child['name'],
                    'age' => $auth?->minor_born_on === null ? null : Dependent::ageBetween($auth->minor_born_on->toDateString(), $day),
                    // ⚠️ `?? null` y no un `?->` a secas: `forGuestMinors()` puede no traer estado de
                    // una firma, y afirmar que siempre lo trae es lo que la línea base perdonaba.
                    'waiver' => $auth === null ? null : (($statuses[(int) $auth->getKey()] ?? null)?->minorState()),
                    'entry' => ! $internal || ! $r->waiverOffered
                        ? null
                        : ($auth !== null ? 'signed' : ($child['companion'] === 'with_adult' ? 'with_adult' : 'unresolved')),
                ];
            }
        }

        // Las firmas que NO emparejan con ninguna ficha ni respuesta: la lista de siempre.
        foreach ($authorizations as $a) {
            if (in_array((int) $a->getKey(), $matched, true)) {
                continue;
            }

            $rows[] = [
                'order_code' => $codeByItem[(int) $a->order_item_id] ?? '',
                // NOMBRE de pila y edad, como los menores a cargo. ⚠️ Los APELLIDOS de un
                // justificante no llegan aquí y es estructural (`#236`): distinguir a un niño de
                // otro en un mostrador no los necesita, y esta plantilla no puede ser la puerta por
                // la que entren.
                'name' => (string) $a->minor_name,
                // ⚠️ La edad la calcula `Dependent::ageBetween()`, que es el ÚNICO sitio con esa
                // regla: `diffInYears()` devuelve un FLOAT —el DTO declara `int`— y además no trata
                // el caso de una fecha posterior al día. Un menor invitado no es un `Dependent`,
                // pero la aritmética de la edad sí es la misma.
                'age' => Dependent::ageBetween($a->minor_born_on->toDateString(), $day),
                'waiver' => ($statuses[(int) $a->getKey()] ?? null)?->minorState(),
                'entry' => $internal ? 'signed' : null,
            ];
        }

        return ['rows' => $rows, 'signed' => $signed, 'expected' => $expected];
    }

    /**
     * **Los niños de UNA fiesta**: las fichas con nombre del anfitrión más los «sí» que todavía no ha
     * apuntado, una vez cada uno.
     *
     * ⚠️ Una respuesta ya ADOPTADA no añade un niño: **es** esa ficha. Lo que sí aporta es su
     * `companion` —si el padre dijo que se queda—, que la ficha no sabe.
     *
     * @param  list<array{reply_id: int, name: string, key: string, companion: string|null, pending: bool}>  $replies
     * @return list<array{name: string, key: string, reply_id: int|null, companion: string|null}>
     */
    private function partyChildren(GateReservation $reservation, array $replies): array
    {
        $children = [];

        foreach ($reservation->partyGuests as $card) {
            $children[$card['key']] ??= ['name' => $card['name'], 'key' => $card['key'], 'reply_id' => null, 'companion' => null];
        }

        foreach ($replies as $reply) {
            // ⚠️⚠️ **El emparejado NO es la igualdad de claves**, y el caso lo cazó: el anfitrión pega
            // la lista de la clase con nombres de pila («Mateo») y al padre se le piden nombre y
            // apellidos («Mateo Ruiz»). Con `===` el mismo niño salía DOS VECES en la puerta. Es la
            // misma regla con la que Booking propone una respuesta sobre una ficha.
            $card = null;
            foreach ($children as $key => $child) {
                if ($child['reply_id'] === null && PersonNameKey::cardMatches($key, $reply['key'])) {
                    $card = $key;
                    break;
                }
            }

            if ($card !== null) {
                $children[$card]['reply_id'] = $reply['reply_id'];
                $children[$card]['companion'] = $reply['companion'];

                continue;
            }

            // Un «sí» que el anfitrión aún no ha repasado: viene igual, y la puerta tiene que
            // saberlo — es justo el niño que hoy se quedaba fuera del papel.
            $children[$reply['key']] = [
                'name' => $reply['name'],
                'key' => $reply['key'],
                'reply_id' => $reply['reply_id'],
                'companion' => $reply['companion'],
            ];
        }

        return array_values($children);
    }

    /**
     * El justificante de ese niño: **atado a su respuesta o emparejado por nombre** (§4.5·10).
     *
     * ⚠️ El emparejado por nombre usa `PersonNameKey::cardMatches()`, la MISMA regla con la que
     * Booking propone una respuesta sobre una ficha: la ficha puede llevar solo el nombre de pila
     * («Mateo») y el justificante nombre y apellidos («Mateo Ruiz Pla»).
     *
     * @param  Collection<int, GuardianAuthorization>  $authorizations
     * @param  array{name: string, key: string, reply_id: int|null, companion: string|null}  $child
     */
    private function authorizationFor($authorizations, int $reservationId, array $child): ?GuardianAuthorization
    {
        foreach ($authorizations as $a) {
            if ((int) $a->order_item_id !== $reservationId) {
                continue;
            }

            if ($child['reply_id'] !== null && (int) $a->invitation_reply_id === $child['reply_id']) {
                return $a;
            }

            if (PersonNameKey::cardMatches($child['key'], (string) $a->minor_key)) {
                return $a;
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, age: int, waiver: ?string}>
     */
    private function dependents(User $holder, CarbonImmutable $today): array
    {
        $active = Dependent::query()->where('user_id', $holder->getKey())->active()->orderBy('id')->get();
        if ($active->isEmpty()) {
            return [];
        }
        $statuses = WaiverSettings::isInternal() ? WaiverStatus::forDependents($active) : [];

        return $active
            ->map(fn (Dependent $d): array => self::minor($d, $today, $statuses[(int) $d->getKey()] ?? null))
            ->values()
            ->all();
    }

    /**
     * ⚠️⚠️ **`name` entra aquí en `#236` y REVIERTE una decisión anterior**, así que conviene saber
     * por qué las dos veces.
     *
     * Nació sin nombre por minimización (`#142`, A·7: «edad y estado de la exención, JAMÁS el
     * nombre»). El owner lo cambió por un motivo operativo que la versión anterior no resolvía:
     * cuando un adulto llega con tres niños y a uno le falta la firma, **«7 años ✗» no dice a
     * cuál**, y el empleado no puede hacer su trabajo sin preguntar.
     *
     * ▶ Lo que NO cambia, y por eso esto no es «abrir la mano»: **los apellidos siguen fuera**
     * (`[DECIDIDO owner]`). Distinguir a un niño de otro en un mostrador no los necesita, y lo que
     * no hace falta no se enseña. El recorte es estructural: este array no tiene campo de
     * apellidos, igual que antes no tenía el de nombre.
     *
     * @return array{name: string, age: int, waiver: ?string}
     */
    private static function minor(Dependent $dependent, CarbonImmutable $on, ?WaiverStatus $status): array
    {
        return [
            'name' => (string) $dependent->name,
            'age' => $dependent->ageOn($on),
            'waiver' => $status?->minorState(),
        ];
    }

    private function cardState(User $holder): string
    {
        if ($this->cards->activeFor($holder) !== null) {
            return GateProfileData::CARD_ACTIVE;
        }

        return CustomerCard::query()->where('user_id', $holder->getKey())->exists()
            ? GateProfileData::CARD_REVOKED
            : GateProfileData::CARD_NONE;
    }
}
