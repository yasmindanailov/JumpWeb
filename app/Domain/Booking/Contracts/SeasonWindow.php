<?php

namespace App\Domain\Booking\Contracts;

/**
 * Temporada activa: un rango de fechas con su propia ventana horaria, que sustituye al
 * horario semanal mientras dura.
 *
 * `isCurrent` lo decide BOOKING con la misma regla que aplican las reservas (si varias
 * solapan gana la de inicio más temprano) — antes Content la reimplementaba «para no
 * divergir», que es justo la divergencia esperando a ocurrir.
 */
final readonly class SeasonWindow
{
    public function __construct(
        public int $id,
        public string $name,
        /** `Y-m-d` */
        public string $startsOn,
        /** `Y-m-d` */
        public string $endsOn,
        public ?string $opensAt,
        public ?string $closesAt,
        public bool $isCurrent,
    ) {}
}
