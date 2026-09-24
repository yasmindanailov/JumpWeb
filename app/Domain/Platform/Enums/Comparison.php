<?php

namespace App\Domain\Platform\Enums;

use App\Domain\Platform\Services\Analytics\Reports\Window;

/**
 * **Contra qué se compara cada cifra del cuadro** (`docs/specs/analitica.md` §4.5): el periodo anterior de la
 * misma longitud (junio contra mayo) o el mismo periodo del año pasado (junio contra el junio anterior), que
 * es la comparación que pide un negocio de temporada. Lo eligió el owner el 24-09 al ver el cuadro.
 */
enum Comparison: string
{
    case Previous = 'previous';
    case YearAgo = 'year_ago';

    public const DEFAULT = self::Previous;

    public static function fromValue(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::DEFAULT) : self::DEFAULT;
    }

    /** La ventana con la que se compara la dada. */
    public function baseline(Window $window): Window
    {
        return match ($this) {
            self::Previous => $window->previous(),
            self::YearAgo => $window->yearAgo(),
        };
    }

    public function label(): string
    {
        return __('admin.analytics.compare.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
