<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\GateReservation;
use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Identity\Contracts\GateProfileData;
use App\Domain\Identity\Models\CustomerCard;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

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
            ],
            card: $this->cardState($holder),
            today_reservations: $todayRows,
            window: $windowRows,
            windowDays: max(0, $windowDays),
            dependents: $this->dependents($holder, $day),
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
            'paid_online_cents' => $r->paidOnlineCents,
            'pending_gate_cents' => $r->pendingGateCents,
            'charge_method' => $r->chargeMethod,
            'paid_at' => $r->paidAt,
            'created_at' => $r->createdAt,
            'minors' => $minors,
            // T3 · E (`specs/cumple-mixto.md` §23.2): lo ESCRITO del suplemento de fiesta mixta,
            // tal cual viaja en el contrato — la tarjeta lo pinta bajo el producto para que el
            // empleado no haga la cuenta de memoria con el cliente delante. Desde la T4 el total
            // es el NETO (cargo − descuento) y viajan además el descuento con su frase y el
            // «a tu favor», que el operador liquida en mano (§20.5).
            'mixed_party_lines' => $r->mixedPartyLines,
            'mixed_party_surcharge_cents' => $r->mixedPartySurchargeCents,
            'mixed_party_credit' => $r->mixedPartyCredit,
            'mixed_party_in_favour_cents' => $r->mixedPartyInFavourCents,
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
            'waiver' => $status === null ? null : (! $status->signed ? 'missing' : ($status->isOutdated() ? 'outdated' : 'current')),
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
