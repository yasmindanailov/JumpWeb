<?php

namespace App\Domain\Identity\Contracts;

/**
 * Fase 6 · menores a cargo, tanda 4 — lo que pasó al ASIGNAR tras crear el pedido
 * (`docs/specs/menores-a-cargo.md` §4.10, §9.9.3 D3).
 *
 * Es un resultado y no una excepción a propósito: la asignación corre DESPUÉS de que el pedido exista
 * y retenga aforo, y un fallo aquí no puede deshacer nada de eso — el pedido sigue en pie y la línea se
 * queda sin asignar. Quien llama solo necesita saber cuántas filas se escribieron y cuántas peticiones
 * se descartaron, para el log; el detalle de cada descarte ya está en el log del asignador.
 */
final readonly class AssignmentOutcome
{
    public function __construct(
        /** Filas escritas (las que ya existían no cuentan: la escritura es idempotente). */
        public int $assigned,
        /** Ids pedidos que no se asignaron (ajeno, retirado, adulto ese día, sin firma, línea sin hueco…). */
        public int $skipped,
        /** `null` si se procesó; si no, por qué no se escribió NADA (`line_mismatch`, `failed`). */
        public ?string $abortedBecause = null,
    ) {}

    public static function nothingRequested(): self
    {
        return new self(0, 0);
    }

    public static function aborted(string $reason, int $requested): self
    {
        return new self(0, $requested, $reason);
    }
}
