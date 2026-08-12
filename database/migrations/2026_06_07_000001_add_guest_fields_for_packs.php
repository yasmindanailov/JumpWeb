<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Post-formulario de datos por invitado para packs de cumpleaños (#217, iter. 1).
 *
 * Espejo de la migración de `event_fields`/`event_data` (2026_05_25_000002), pero para los
 * datos POR NIÑO de la fiesta (distinto de los datos básicos del evento):
 *  - `ticket_types.guest_fields`: ESQUEMA data-driven de las columnas que se piden por cada
 *    invitado (JSON, lista de `{key, label(traducible), type(text|number|textarea), required}`).
 *    Sembrado con 4 columnas por defecto {nombre, alergia, observaciones, menú especial}; el
 *    admin puede añadir/quitar/renombrar. Null en entradas/complementos.
 *  - `order_items.guest_data`: RESPUESTAS por-niño de esa línea de pack (JSON, lista de N objetos
 *    `[{<key>: valor, …}, …]`; N = `quantity`). El cliente las rellena en el post-form (iter. 2).
 *  - `order_items.guest_form_completed_at`: sello (timestamp) de cuándo el cliente envió el
 *    formulario completo. El estado FORM OK/NO se DERIVA en vivo de `guest_data` contra
 *    `quantity` ({@see OrderItem::guestFormStatus}); este sello es auditoría/visualización.
 *
 * Ver docs/PLAN-POSTFORM-INVITADOS.md y docs/DECISIONES.md (#217).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->json('guest_fields')->nullable()->after('event_fields');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('guest_data')->nullable()->after('event_data');
            $table->timestamp('guest_form_completed_at')->nullable()->after('guest_data');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('guest_fields');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['guest_data', 'guest_form_completed_at']);
        });
    }
};
