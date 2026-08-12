<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Backfill de las TABLAS DE TARIFAS (`landing_services.price_table`) de los servicios de /servicios,
 * para instalaciones YA SEMBRADAS (producción) donde el deploy añade la columna pero NO resiembra
 * (`deploy-prod.sh` = «migrate --force», sin seeders). Patrón `legacy-data-repair` del proyecto:
 * servicio TESTEABLE invocado por una migración (idempotente, conservador, no-op en BD limpia).
 *
 * QUIRÚRGICO — solo toca lo imprescindible para que aparezcan las tablas, NADA más:
 *  - Actúa SOLO sobre los 2 servicios listados, POR SLUG.
 *  - SOLO rellena `price_table` cuando está NULL → idempotente (2.ª pasada = 0 filas), NO pisa un valor
 *    ya presente, y es NO-OP en instalaciones nuevas/dev (donde el seeder crea los servicios DESPUÉS de
 *    migrar: en el momento de la migración aún no existen → 0 filas afectadas; el seeder ya pone el dato).
 *  - NO toca el editorial (body/specs/nav_subtitle) ni ningún otro contenido/servicio: el resto está
 *    editado en vivo desde el panel y debe permanecer intacto.
 *
 * Los datos son un SNAPSHOT CONGELADO (céntimos), idéntico a `LandingContentSeeder` a esta fecha
 * (verificado por `ServicePriceTableBackfillTest::test_backfill_data_matches_the_seeder`). Como
 * `price_table` no tiene editor de panel, un futuro cambio de precio va por OTRA migración de datos.
 */
class ServicePriceTableBackfill
{
    /**
     * Rellena `price_table` donde falte (NULL), por slug. Idempotente. Usa el query builder (sin
     * eventos de modelo): cero efectos colaterales.
     *
     * @return int filas afectadas
     */
    public function apply(): int
    {
        $affected = 0;

        foreach (self::tables() as $slug => $priceTable) {
            $affected += DB::table('landing_services')
                ->where('slug', $slug)
                ->whereNull('price_table')
                ->update(['price_table' => json_encode($priceTable, JSON_UNESCAPED_UNICODE)]);
        }

        return $affected;
    }

    /**
     * Reverso del backfill: vacía `price_table` SOLO en los 2 slugs gestionados (no toca el resto).
     * Como `price_table` no tiene otro origen (sin editor de panel), limpiarlo es seguro.
     *
     * @return int filas afectadas
     */
    public function revert(): int
    {
        return DB::table('landing_services')
            ->whereIn('slug', array_keys(self::tables()))
            ->whereNotNull('price_table')
            ->update(['price_table' => null]);
    }

    /**
     * Snapshot congelado de las tablas de tarifas por servicio (precio por unidad, en CÉNTIMOS):
     * zonas → duraciones (minutos) → tramos {size, weekday=L–V, weekend=finde/festivo}. `unit` = clave
     * i18n de la fila/nota (`kids` → «X niños»; `people` → «X personas»). MISMOS datos que el seeder.
     *
     * @return array<string, array<string, mixed>> slug => price_table
     */
    public static function tables(): array
    {
        // Tarifas de la zona Jump (idénticas en colegio y empresas): 2 h y 3 h, L–V / finde.
        $jump = [
            'label' => 'Jump',
            'accent' => 'jump',
            'durations' => [
                ['minutes' => 120, 'tiers' => [
                    ['size' => 30, 'weekday' => 1500, 'weekend' => 1700],
                    ['size' => 75, 'weekday' => 1400, 'weekend' => 1600],
                    ['size' => 100, 'weekday' => 1200, 'weekend' => 1400],
                ]],
                ['minutes' => 180, 'tiers' => [
                    ['size' => 30, 'weekday' => 1800, 'weekend' => 2000],
                    ['size' => 75, 'weekday' => 1600, 'weekend' => 1800],
                    ['size' => 100, 'weekday' => 1500, 'weekend' => 1700],
                ]],
            ],
        ];

        return [
            // Excursiones de colegio: Kids + Jump, unidad «niños».
            'excursionescolegio' => [
                'unit' => 'kids',
                'zones' => [
                    [
                        'label' => 'Kids',
                        'accent' => 'kids',
                        'durations' => [
                            ['minutes' => 120, 'tiers' => [
                                ['size' => 30, 'weekday' => 1200, 'weekend' => 1400],
                                ['size' => 75, 'weekday' => 1100, 'weekend' => 1300],
                                ['size' => 100, 'weekday' => 1000, 'weekend' => 1200],
                            ]],
                            ['minutes' => 180, 'tiers' => [
                                ['size' => 30, 'weekday' => 1500, 'weekend' => 1700],
                                ['size' => 75, 'weekday' => 1400, 'weekend' => 1600],
                                ['size' => 100, 'weekday' => 1200, 'weekend' => 1400],
                            ]],
                        ],
                    ],
                    $jump,
                ],
            ],
            // Empresas (team building): solo Jump, unidad «personas».
            'teambuilding' => [
                'unit' => 'people',
                'zones' => [$jump],
            ],
        ];
    }
}
