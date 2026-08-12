<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría Fase 1 · L2: normaliza `seats_per_unit = 1` en los PACKS existentes.
 *
 * Un pack es «1 niño = 1 plaza»: su cupo lo gobiernan `min_qty`/`max_qty` y los topes por franja
 * (#82/#225). Con `seats_per_unit > 1` el checkout (`OrderCreator`) valida el cupo de niños en
 * UNIDADES pero lo consume en PLAZAS (`qty × seats_per_unit`) → sobreventa del cupo por franja. El
 * formulario del catálogo (oculta el campo en packs) y el guardado (lo fuerza a 1, regla 12) ya lo
 * impiden de cara al futuro; esto repara cualquier fila previa. Idempotente y no-op en una BD
 * coherente (el seed siempre usa `seats_per_unit = 1`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ticket_types')
            ->where('type', 'pack') // TicketType::TYPE_PACK
            ->where('seats_per_unit', '!=', 1)
            ->update(['seats_per_unit' => 1]);
    }

    public function down(): void
    {
        // No-op: `seats_per_unit > 1` en un pack era un dato incoherente; no se reconstruye.
    }
};
