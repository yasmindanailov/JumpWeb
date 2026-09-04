<?php

namespace App\Domain\Booking\Contracts;

/**
 * Lo que le pasa a UNA línea hija al mover su reserva de día (`specs/hora-extra.md` §9).
 *
 * Tres desenlaces y solo tres: se queda igual, cambia de precio, o se retira porque ese día ese
 * producto no se vende. La distinción importa porque **cada uno escribe cosas distintas** (§9.6), y
 * son asimétricas:
 *
 * | acción | qué se escribe |
 * |---|---|
 * | `KEEP` | nada |
 * | `REPRICE` | `unit_price` **+ `recordEdit(±Δ)`** — sin el hecho, el libro deja de cerrar |
 * | `WITHDRAW` | `markCancelled()` y **NINGÚN** `recordEdit` — el libro ya emite su `−fila` |
 */
final readonly class AddonDateChange
{
    public const KEEP = 'keep';

    public const REPRICE = 'reprice';

    public const WITHDRAW = 'withdraw';

    public function __construct(
        public int $childId,
        public int $productId,
        public string $productName,
        public string $action,
        public int $quantity,
        /** Unidades gratis de la fila. **No se tocan**: son del pivote, no del día. */
        public int $freeQuantity,
        public int $currentUnitCents,
        /** El unitario del día NUEVO; `null` cuando ese día no se vende (→ `WITHDRAW`). */
        public ?int $newUnitCents,
        /**
         * Lo que este cambio mueve en el total, con signo — **calculado sobre lo COBRADO**
         * (`max(0, quantity − freeQuantity) × unitario`), no sobre el unitario suelto.
         */
        public int $chargedDeltaCents,
    ) {}

    public function isWithdrawal(): bool
    {
        return $this->action === self::WITHDRAW;
    }

    public function isRepricing(): bool
    {
        return $this->action === self::REPRICE;
    }

    /** Las unidades que de verdad se cobran de esta línea. */
    public function chargedUnits(): int
    {
        return max(0, $this->quantity - $this->freeQuantity);
    }
}
