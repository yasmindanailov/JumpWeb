<?php

namespace App\Domain\Booking\Contracts;

/**
 * Ventana horaria EFECTIVA de un día, ya resuelta por la prioridad del dominio
 * (fecha especial > temporada > horario semanal).
 *
 * `opensAt`/`closesAt` en `H:i:s`, o `null` = sin restricción de ventana (el fallback no
 * destructivo de `OperatingSchedule`: si no hay nada configurado, el recinto se considera
 * abierto sin horario).
 */
final readonly class OperatingWindow
{
    public function __construct(
        public bool $isOpen,
        public ?string $opensAt,
        public ?string $closesAt,
    ) {}

    /** ¿Tiene ventana completa (apertura Y cierre) que se pueda pintar como «10:00 – 20:00»? */
    public function hasHours(): bool
    {
        return $this->opensAt !== null && $this->closesAt !== null;
    }
}
