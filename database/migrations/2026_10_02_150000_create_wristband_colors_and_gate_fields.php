<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LAS PULSERAS Y LO QUE SE ENTREGA EN LA PUERTA** (`docs/specs/puerta-nueva.md` §4.4, la P2; §4.3·1–3).
 *
 * - `wristband_colors`: los colores de pulsera del PARQUE, con la frase que lee el empleado en singular y en plural
 *   («pulsera lila» / «pulseras lilas»: la palabra y su concordancia son del parque, no del producto) y su hex; ordenados, y
 *   los que van «en la rueda» se reparten las horas (D10, D11).
 * - `ticket_types.wristband_color_id`: el color FIJO de un producto, que gana a la rueda (D12: la ilimitada, un
 *   cumpleaños). ⚠️ La columna vieja `wristband_color` (texto libre que el `ProductionSeeder` rellena POR ZONA, «Naranja»
 *   Jump y «Verde» Kids) NO se reaprovecha: es el modelo viejo, y leerla pondría en producción un color fijo a todo Jump.
 * - `ticket_types.gate_zone_id`: dónde SALTAN los invitados de un pack (D13); vacío, la zona del pack.
 * - `ticket_types.handed_at_gate` y su rótulo en singular y en plural: el complemento que se entrega en la puerta, como
 *   los calcetines (D14); sin rótulo, su nombre.
 *
 * **RGPD**: nada personal — es configuración del catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wristband_colors', function (Blueprint $table) {
            $table->id();
            $table->string('name_one', 60);
            $table->string('name_other', 60);
            $table->string('hex', 7);
            $table->boolean('in_wheel')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->foreignId('wristband_color_id')->nullable()->after('wristband_color')->constrained('wristband_colors')->nullOnDelete();
            $table->foreignId('gate_zone_id')->nullable()->after('wristband_color_id')->constrained('zones')->nullOnDelete();
            $table->boolean('handed_at_gate')->default(false)->after('gate_zone_id');
            $table->string('gate_label_one', 60)->nullable()->after('handed_at_gate');
            $table->string('gate_label_other', 60)->nullable()->after('gate_label_one');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wristband_color_id');
            $table->dropConstrainedForeignId('gate_zone_id');
            $table->dropColumn(['handed_at_gate', 'gate_label_one', 'gate_label_other']);
        });

        Schema::dropIfExists('wristband_colors');
    }
};
