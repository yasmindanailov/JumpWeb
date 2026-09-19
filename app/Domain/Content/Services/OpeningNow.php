<?php

namespace App\Domain\Content\Services;

use Carbon\CarbonInterface;

/**
 * El estado de apertura AHORA MISMO, en hechos: si está abierto, hasta qué hora, y cuándo vuelve a abrir.
 *
 * ⚠️ **`closesAt` solo tiene valor cuando está abierto y `opensAt` solo cuando está cerrado.** No es una
 * casualidad de la implementación: decir «cierra a las 21:30» de un parque cerrado, o «abre a las 16:30» de
 * uno abierto, es la clase de dato que un consumidor pinta sin pensar y que deja un cartel mintiendo.
 *
 * ⚠️ `opensAt` puede ser `null` estando cerrado: es la instalación que todavía no ha configurado horario, y
 * afirmar cualquier otra cosa sería inventar.
 */
final readonly class OpeningNow
{
    public function __construct(
        public bool $openNow,
        public ?string $closesAt,
        public ?CarbonInterface $opensAt,
    ) {}
}
