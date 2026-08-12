<?php

use App\Support\SpecialRateLabelBackfill;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill del label de la tarifa especial (`rate_types.key='special'`) en instalaciones YA
 * SEMBRADAS (producción): el deploy corre `migrate --force` pero NO resiembra, así que el label
 * quedó con el default viejo «Festivo / finde / víspera». La landing lo muestra ahora en el chip de
 * suplemento («+X€ {label}»); queremos el copy nuevo «Findes y festivos».
 *
 * La lógica vive en `App\Support\SpecialRateLabelBackfill` (testeable, idempotente, QUIRÚRGICA: solo
 * la tarifa `special` y SOLO si su label sigue siendo el default viejo → no pisa ediciones del panel).
 * NO-OP en instalaciones nuevas/dev (`rate_types` aún vacía al migrar; el seeder pone el label nuevo).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new SpecialRateLabelBackfill)->apply();
    }

    public function down(): void
    {
        (new SpecialRateLabelBackfill)->revert();
    }
};
