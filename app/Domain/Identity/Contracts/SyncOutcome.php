<?php

namespace App\Domain\Identity\Contracts;

/**
 * Fase 6 · menores a cargo, tanda 5 (el PANEL) — lo que pasó al FIJAR el conjunto de menores de una
 * línea desde el mostrador (`docs/specs/menores-a-cargo.md` §9.10.2 D14·3).
 *
 * Es un resultado y no una excepción, como {@see AssignmentOutcome}: el operador tiene al cliente
 * delante y necesita saber QUÉ se rechazó y en qué posición, no una traza. Y es **fail-closed**: con
 * cualquier rechazo no se escribió NADA — `added`, `removed` y `kept` son cero.
 */
final readonly class SyncOutcome
{
    public function __construct(
        /** Filas escritas (menores que no estaban y ahora están). */
        public int $added,
        /** Filas borradas (menores que estaban y el operador desmarcó). */
        public int $removed,
        /** Menores que ya estaban y siguen: no se re-validan ni se auditan. */
        public int $kept,
        /**
         * Posición en la lista pedida (`"0"`, `"1"`…) → motivo (`DependentAssigner::REASON_*`);
         * la clave `''` es un rechazo de la LÍNEA entera (demasiados, o no es una entrada).
         *
         * @var array<string, string>
         */
        public array $rejections = [],
        /** `null` si se procesó; si no, por qué no se pudo ni mirar (`no_line`, `failed`). */
        public ?string $abortedBecause = null,
    ) {}

    /**
     * @param  array<string, string>  $rejections
     */
    public static function rejected(array $rejections): self
    {
        return new self(0, 0, 0, $rejections);
    }

    public static function aborted(string $reason): self
    {
        return new self(0, 0, 0, [], $reason);
    }

    public function ok(): bool
    {
        return $this->rejections === [] && $this->abortedBecause === null;
    }

    public function changed(): bool
    {
        return $this->added + $this->removed > 0;
    }
}
