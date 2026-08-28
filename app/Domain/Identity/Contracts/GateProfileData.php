<?php

namespace App\Domain\Identity\Contracts;

/**
 * Fase 6 · subsistema A — LA FICHA de puerta de un cliente (`docs/specs/identidad-qr-puerta.md` §4.6,
 * §9.2 A·3/A·7), compuesta UNA vez por `Identity\Services\GateProfile`.
 *
 * ⚠️⚠️ **CORRECCIÓN, y va delante del texto que corregía** (`#236`, `[DECIDIDO owner]`). Este objeto
 * nació SIN el nombre de un menor por minimización, y desde `#236` lo lleva: cuando un adulto llega
 * con tres niños y a uno le falta la firma, «7 años ✗» **no dice a cuál** y el empleado no puede
 * hacer su trabajo. Los menores viajan ahora como `{name, age, waiver}`.
 *
 * ▶ **Lo que sigue siendo estructural es lo que NO lleva**: **los apellidos no tienen campo aquí**,
 * a propósito — distinguir a un niño de otro en un mostrador no los necesita—. Ni tampoco (§4.6)
 * email o teléfono completos, dirección, historial de importes ni alergias. La regla no era «nada
 * de menores»: era **solo lo que hace falta para dejar pasar**, y el nombre de pila hace falta.
 *
 * Es lo que la pantalla guarda en su estado (Livewire lo serializa al navegador) y lo que un endpoint
 * de API envolverá el día que exista una app de escaneo (§4.11).
 */
final readonly class GateProfileData
{
    public const CARD_NONE = 'none';

    public const CARD_ACTIVE = 'active';

    public const CARD_REVOKED = 'revoked';

    /**
     * @param  array{enabled: bool, signed: bool, accepted_on: ?string, outdated: bool}  $waiver
     * @param  list<array{order_code: string, order_item_id: int, date: string, time_window: ?string, product: string, is_entry: bool, quantity: int, addons: list<string>, paid_online_cents: int, pending_gate_cents: int, charge_method: ?string, paid_at: ?string, created_at: string, minors: list<array{name: string, age: int, waiver: ?string}>}>  $today
     * @param  list<array<string, mixed>>  $window  mismo esquema que `$today`, sin el día de hoy
     * @param  list<array{name: string, age: int, waiver: ?string}>  $dependents  los ACTIVOS a cargo: NOMBRE de pila (nunca apellidos), edad HOY y estado de su exención (`current` · `outdated` · `missing` · `null` fuera de interno)
     */
    public function __construct(
        public int $userId,
        public string $holderName,
        /** `Y-m-d` del «hoy» del parque con el que se compuso. */
        public string $today,
        public array $waiver,
        /** `none` · `active` · `revoked` (tuvo carné y ya no tiene ninguno activo). */
        public string $card,
        public array $today_reservations,
        public array $window,
        public int $windowDays,
        public array $dependents,
        public bool $visitRegisteredToday,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'holder_name' => $this->holderName,
            'today' => $this->today,
            'waiver' => $this->waiver,
            'card' => $this->card,
            'today_reservations' => $this->today_reservations,
            'window' => $this->window,
            'window_days' => $this->windowDays,
            'dependents' => $this->dependents,
            'visit_registered_today' => $this->visitRegisteredToday,
        ];
    }
}
