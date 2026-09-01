<?php

use Illuminate\Database\Migrations\Migration;

/**
 * NEUTRALIZADA en la T1 del libro (`specs/desglose-libro.md` §4.7, `DECISIONES #305`).
 *
 * Reparaba datos del repo ORIGEN (#193 de allí): re-atribuía al complemento los ajustes
 * `extra_due` que el código previo ataba al principal. `LegacyAddonAdjustmentRepair` era su único
 * cuerpo y **no tenía ningún otro llamador**; JumpWeb solo instala limpio (`DECISIONES #127`) y
 * no hay pedidos en producción, así que sobre toda base que este repo haya creado era un no-op.
 * Con los tipos de ajuste nuevos (`deposit_split` · `edit` · `mixed` · `courtesy`) el servicio
 * dejó de tener sentido y se retiró; esta migración se queda como fila del historial de
 * `migrations` (borrarla haría fallar `migrate` en toda base que ya la corrió).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sin efecto: ver docblock.
    }

    public function down(): void
    {
        // Sin efecto.
    }
};
