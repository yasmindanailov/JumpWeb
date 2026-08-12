<?php

use App\Domain\Content\Services\ServicePriceTableBackfill;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill de `landing_services.price_table` para los servicios de /servicios (colegio + empresas) en
 * instalaciones YA SEMBRADAS (producción): el deploy añadió la columna (migración anterior) pero NO
 * resiembra, así que quedó NULL → las tablas de precios no aparecerían.
 *
 * La lógica vive en `App\Domain\Content\Services\ServicePriceTableBackfill` (testeable, idempotente, QUIRÚRGICA: solo
 * `price_table`, solo 2 servicios por slug, solo donde está NULL). NO toca el editorial ni ningún otro
 * contenido/servicio: el resto está editado en vivo y debe permanecer intacto. NO-OP en instalaciones
 * nuevas/dev (los servicios aún no existen al migrar; el seeder ya pone el dato).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ServicePriceTableBackfill)->apply();
    }

    public function down(): void
    {
        (new ServicePriceTableBackfill)->revert();
    }
};
