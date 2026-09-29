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

    /**
     * Qué parte del tiempo de las dos ventanas es la del periodo: 0,5 si duran lo mismo. Un mes de 31 días contra uno de
     * 28 no lo son, y la prueba de un recuento compara RITMOS, no totales (`Metric::reading()`). Aquí y no en cada sitio
     * que juzga un cambio: la tarjeta y el texto para IA (T3d) tienen que juzgar igual.
     */
    public function share(Window $window): float
    {
        $baseline = $this->baseline($window);
        $now = $window->to->getTimestamp() - $window->from->getTimestamp();
        $before = $baseline->to->getTimestamp() - $baseline->from->getTimestamp();

        return $now + $before > 0 ? $now / ($now + $before) : 0.5;
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
