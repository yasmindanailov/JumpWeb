<?php

use Illuminate\Database\Migrations\Migration;

/**
 * NEUTRALIZADA en la T1 del libro (`specs/desglose-libro.md` §4.7, `DECISIONES #305`).
 *
 * Reparaba datos del repo ORIGEN (bug JJ-WIMWJW de allí): neteaba los cargos de puerta
 * `extra_due` fantasma que el código previo dejaba al subir y bajar cantidad.
 * `LegacyGateAdjustmentReconciliation` era su único cuerpo y **no tenía ningún otro llamador**;
 * JumpWeb solo instala limpio (`DECISIONES #127`) y no hay pedidos en producción, así que sobre
 * toda base que este repo haya creado era un no-op. Con la T1 una bajada escribe su delta
 * entero como hecho (`edit`) y no existe la cascada de créditos que este servicio reparaba; se
 * retiró, y esta migración se queda como fila del historial de `migrations`.
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
