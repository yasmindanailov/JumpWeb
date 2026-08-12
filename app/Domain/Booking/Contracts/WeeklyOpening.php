<?php

namespace App\Domain\Booking\Contracts;

/** Horario configurado para un día de la semana (`weekday` de Carbon: 0=domingo … 6=sábado). */
final readonly class WeeklyOpening
{
    public function __construct(
        public int $weekday,
        public bool $isClosed,
        public ?string $opensAt,
        public ?string $closesAt,
    ) {}
}
