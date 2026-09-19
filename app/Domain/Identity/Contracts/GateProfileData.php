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
     * @param  array{enabled: bool, signed: bool, accepted_on: ?string, outdated: bool, pending_acceptance: bool}  $waiver
     * @param  list<array{order_code: string, order_item_id: int, date: string, time_window: ?string, product: string, is_entry: bool, quantity: int, addons: list<string>, paid_cents: int, balance_kind: string, balance_cents: int, charge_method: ?string, paid_at: ?string, created_at: string, minors: list<array{name: string, age: int, waiver: ?string}>}>  $today
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
        /**
         * Los menores INVITADOS autorizados en los pedidos de las reservas de HOY
         * (`specs/waiver-por-reserva.md` §4.11): nombre de pila, edad en la fecha de la visita y
         * estado del justificante. ⚠️ Llevan `order_code` porque cuelgan del PEDIDO, no de la línea
         * —de ahí que vivan aquí y no dentro de cada reserva—; y **tampoco tienen apellidos**, por
         * la misma regla estructural que los menores a cargo.
         *
         * ▶ **Desde la T6·4 no son solo los FIRMADOS** (`specs/celebracion-e-invitacion.md` §4.8): la
         * lista son las fichas con nombre de la fiesta más los «sí» que el anfitrión todavía no ha
         * apuntado, y cada uno trae su **estado de entrada** (`entry`): `signed` · `with_adult` ·
         * `unresolved`, **ninguno en rojo** (§4.5·10). `null` cuando el producto no pide justificante
         * o el waiver no es interno. Sin firma no hay fecha de nacimiento, así que `age` puede ser
         * `null`; y el nombre de un niño invitado es **un solo campo libre**, así que puede traer
         * apellidos —es justo para lo que se piden (§4.5·5)—.
         *
         * @var list<array{order_code: string, name: string, age: int|null, waiver: ?string, entry: ?string}>
         */
        public array $guestMinors,
        public bool $visitRegisteredToday,
        /**
         * «8 de 12 con justificante» (T6·4): cuántos de los invitados CONTRATADOS de las fiestas de
         * hoy llegan con firma. `null` si hoy no hay ninguna fiesta con invitación.
         *
         * @var array{signed: int, expected: int}|null
         */
        public ?array $guestMinorsCount = null,
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
            'guest_minors' => $this->guestMinors,
            'guest_minors_count' => $this->guestMinorsCount,
            'visit_registered_today' => $this->visitRegisteredToday,
        ];
    }
}
