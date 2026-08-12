<?php

namespace App\Support;

use App\Models\RateType;
use Illuminate\Support\Facades\DB;

/**
 * Backfill del LABEL de la tarifa especial (`rate_types.key='special'`) para instalaciones YA
 * SEMBRADAS (producción), donde el deploy corre `migrate --force` pero NO resiembra: el label quedó
 * con el default viejo «Festivo / finde / víspera» y la card de la landing lo muestra en el chip de
 * suplemento («+X€ {label}»). Patrón `legacy-data-repair` del proyecto: servicio TESTEABLE invocado
 * por una migración (idempotente, conservador, no-op en BD limpia/dev).
 *
 * QUIRÚRGICO — solo renombra si NO se ha personalizado:
 *  - Actúa SOLO sobre la tarifa `special`, y SOLO si su `label->es` sigue siendo el default viejo →
 *    idempotente (2.ª pasada = 0 filas), NO pisa una edición de la clienta (el label es editable en
 *    /admin/rate-types) y es NO-OP en instalaciones nuevas/dev (al migrar, `rate_types` aún está
 *    vacía; el seeder pone el label nuevo DESPUÉS → 0 filas afectadas).
 *  - NO toca ninguna otra columna ni tarifa.
 *
 * El label nuevo es un SNAPSHOT idéntico a `LandingContentSeeder::seedRateTypes()` a esta fecha
 * (guarda anti-drift: SpecialRateLabelBackfillTest::test_new_label_matches_the_seeder).
 */
class SpecialRateLabelBackfill
{
    /** Default SEMBRADO viejo (por locale) que identifica una tarifa `special` sin personalizar. */
    private const OLD_LABEL = ['es' => 'Festivo / finde / víspera', 'en' => 'Holiday / weekend / eve', 'fr' => 'Férié / week-end / veille'];

    /** Label NUEVO (debe coincidir con LandingContentSeeder::seedRateTypes). */
    private const NEW_LABEL = ['es' => 'Findes y festivos', 'en' => 'Weekends & holidays', 'fr' => 'Week-ends et fériés'];

    /** @return array<string,string> */
    public static function newLabel(): array
    {
        return self::NEW_LABEL;
    }

    /** @return array<string,string> */
    public static function oldLabel(): array
    {
        return self::OLD_LABEL;
    }

    /**
     * Renombra el label de la tarifa `special` al nuevo SOLO si sigue siendo el default viejo
     * (match por `label->es`, que json_extract normaliza sea cual sea el escape unicode almacenado).
     * Idempotente. Query builder (sin eventos de modelo): cero efectos colaterales.
     *
     * @return int filas afectadas
     */
    public function apply(): int
    {
        return DB::table('rate_types')
            ->where('key', RateType::KEY_SPECIAL)
            ->where('label->es', self::OLD_LABEL['es'])
            ->update(['label' => json_encode(self::NEW_LABEL, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Reverso: vuelve al default viejo SOLO si el label es exactamente el nuevo (no pisa ediciones).
     *
     * @return int filas afectadas
     */
    public function revert(): int
    {
        return DB::table('rate_types')
            ->where('key', RateType::KEY_SPECIAL)
            ->where('label->es', self::NEW_LABEL['es'])
            ->update(['label' => json_encode(self::OLD_LABEL, JSON_UNESCAPED_UNICODE)]);
    }
}
