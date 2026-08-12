<?php

namespace App\Domain\Booking\Contracts;

/**
 * Excepción de calendario: un día concreto cerrado o con horario propio.
 *
 * La ventana que viaja aquí es la EFECTIVA (la del día especial o, si no la define, la del
 * horario semanal de ese día), resuelta por Booking. Content solo la pinta: antes repetía esa
 * resolución a mano en `specialDetail()`.
 */
final readonly class SpecialDay
{
    public function __construct(
        /** `Y-m-d` */
        public string $date,
        public bool $isClosed,
        /** Nota traducida al idioma activo, o null. */
        public ?string $note,
        public ?string $opensAt,
        public ?string $closesAt,
    ) {}

    public function hasHours(): bool
    {
        return $this->opensAt !== null && $this->closesAt !== null;
    }
}
