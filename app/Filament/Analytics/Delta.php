<?php

namespace App\Filament\Analytics;

use Filament\Support\Icons\Heroicon;

/**
 * **«Frente al periodo anterior»** (`docs/specs/analitica.md` §4.5): cada cifra del cuadro lleva su variación
 * contra el periodo anterior de la misma longitud, como texto, icono y color de la tarjeta.
 *
 * ⚠️ Sin dato anterior no hay porcentaje: dividir por cero se enseña como «sin datos», no como «+∞». Y el
 * color dice si el movimiento es BUENO, no si sube: una devolución que sube es rojo.
 */
final class Delta
{
    /** @return array{description: string, icon: Heroicon, color: string} */
    public static function describe(int $current, int $previous, bool $upIsGood = true): array
    {
        if ($previous === 0) {
            return [
                'description' => __('admin.analytics.delta.no_previous'),
                'icon' => Heroicon::OutlinedMinus,
                'color' => 'gray',
            ];
        }

        $percent = (int) round(($current - $previous) / abs($previous) * 100);

        if ($percent === 0) {
            return [
                'description' => __('admin.analytics.delta.same'),
                'icon' => Heroicon::OutlinedMinus,
                'color' => 'gray',
            ];
        }

        $up = $percent > 0;

        return [
            'description' => __('admin.analytics.delta.vs_previous', [
                'delta' => ($up ? '+' : '−').abs($percent)."\u{00A0}%",
            ]),
            'icon' => $up ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown,
            'color' => $up === $upIsGood ? 'success' : 'danger',
        ];
    }
}
