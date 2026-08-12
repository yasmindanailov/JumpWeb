<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atracciones de pago + resalte «Destacada» (#228).
 *
 * Dos campos NUEVOS e INDEPENDIENTES sobre `attractions`:
 *
 * - `is_special` (bool, default false): resalte VISUAL de la atracción («más
 *   llamativa que el resto»). No implica pago. Editable en el panel.
 *
 * - `ticket_type_id` (FK nullable → ticket_types): vincula la atracción a un
 *   COMPLEMENTO (`TYPE_ADDON`) vendible. Si está presente y el complemento es
 *   comprable en la zona de la atracción, la landing muestra precio + CTA «Comprar»
 *   (que abre la cesta en Entradas, posicionada en esa zona). `nullOnDelete`: si se
 *   borra el complemento, la atracción vuelve a ser solo informativa (no se rompe).
 *
 * Mínima superficie: NO se crea ningún tipo de producto nuevo (los tipos son
 * constantes de código, #210); se reutiliza la maquinaria de complementos (#190/#191).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            $table->boolean('is_special')->default(false)->after('is_active');
            $table->foreignId('ticket_type_id')->nullable()->after('is_special')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_type_id');
            $table->dropColumn('is_special');
        });
    }
};
