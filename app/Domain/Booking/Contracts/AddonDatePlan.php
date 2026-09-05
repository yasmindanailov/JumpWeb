<?php

namespace App\Domain\Booking\Contracts;

/**
 * **Qué le pasa a cada línea hija cuando su reserva cambia de día** (`specs/hora-extra.md` §9,
 * `DECISIONES #417`).
 *
 * Es la MISMA derivación que ve el operador antes de confirmar y la que se aplica después: el
 * `preview` del modal y la mutación bajo el lock salen de aquí, porque si fueran dos cálculos el
 * operador podría confirmar una cosa y aplicarse otra — el mismo motivo por el que la oferta y el
 * cobro comparten `AddonResolver` (`AFORO-02`).
 *
 * ⚠️ **`survivingChildIds` no es un dato de presentación: gobierna qué se valida.** La revisión
 * adversarial (§9.8·H1) midió que validar el aterrizaje de una hija que iba a retirarse **bloquea el
 * movimiento entero** por un aforo que nadie va a consumir.
 */
final readonly class AddonDatePlan
{
    /**
     * @param  list<AddonDateChange>  $changes  una entrada por línea hija GOBERNADA (las excluidas no salen)
     * @param  list<int>  $survivingChildIds  las que siguen vivas tras el movimiento: las únicas cuyo
     *                                        aterrizaje hay que validar y mover
     */
    public function __construct(
        public array $changes,
        public array $survivingChildIds,
    ) {}

    /** ¿Hay algo que decirle al operador? Un plan sin cambios no se enseña. */
    public function isEmpty(): bool
    {
        return $this->withdrawals() === [] && $this->repricings() === [];
    }

    /** @return list<AddonDateChange> */
    public function withdrawals(): array
    {
        return array_values(array_filter($this->changes, fn (AddonDateChange $c): bool => $c->isWithdrawal()));
    }

    /** @return list<AddonDateChange> */
    public function repricings(): array
    {
        return array_values(array_filter($this->changes, fn (AddonDateChange $c): bool => $c->isRepricing()));
    }

    /**
     * Lo que el movimiento cambia en el TOTAL del pedido, en céntimos con signo.
     *
     * ⚠️ Se compone de lo COBRADO por cada línea, no de su `unit_price`: un complemento incluido
     * lleva su precio en la fila y `free_quantity` lo neutraliza, así que sumar unitarios diría que
     * cambia dinero donde no cambia ninguno.
     */
    public function netDeltaCents(): int
    {
        return array_sum(array_map(fn (AddonDateChange $c): int => $c->chargedDeltaCents, $this->changes));
    }
}
