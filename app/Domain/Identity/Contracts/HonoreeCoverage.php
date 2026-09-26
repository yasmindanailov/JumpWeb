<?php

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Services\WaiverStatus;

/**
 * **¿Quién cubre a QUIEN CUMPLE?** (`specs/fiesta-sistema-nuevo.md` §4.13, `[DECIDIDO owner]` `#752`) — la ÚNICA respuesta
 * a esa pregunta, la que leen la lista, la puerta, la víspera y la API (`GuardianPlaces::honoreeCoverage()`).
 *
 * Quien cumple queda cubierto por UNA de dos pruebas, atadas por id y NUNCA por su nombre: un menor a cargo del titular
 * asignado a la línea del pack (`dependent`) o un justificante atado a él (`authorization`). `none`: la reserva lo sella y
 * nadie lo cubre todavía.
 *
 * ⚠️ «Cubierto» y «firmado» no son lo mismo: un menor a cargo cuya exención es de un texto ANTERIOR sigue cubriendo su
 * plaza (es suyo) pero la puerta lo ve «anterior», como a cualquier firma vieja. Por eso viajan las dos cosas.
 */
final readonly class HonoreeCoverage
{
    public const NONE = 'none';

    public const DEPENDENT = 'dependent';

    public const AUTHORIZATION = 'authorization';

    public function __construct(
        /** `none` · `dependent` · `authorization`. */
        public string $via,
        /** El nombre de pila de quien lo cubre, como lo dice su prueba; `null` sin cubrir. */
        public ?string $name = null,
        /** El estado de su exención (`WaiverStatus::MINOR_*`), o `null` sin cubrir o fuera del modo interno. */
        public ?string $waiver = null,
        /** El menor a cargo asignado, si lo cubre uno. */
        public ?int $dependentId = null,
        /** El justificante atado, si lo cubre uno. */
        public ?int $authorizationId = null,
    ) {}

    public static function none(): self
    {
        return new self(self::NONE);
    }

    public function covered(): bool
    {
        return $this->via !== self::NONE;
    }

    /** Cubierto y con la exención VIGENTE: lo que la lista pinta «Firmada» y la puerta cuenta como firmado. */
    public function signed(): bool
    {
        return $this->covered() && $this->waiver === WaiverStatus::MINOR_CURRENT;
    }
}
