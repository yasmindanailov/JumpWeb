<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **De dónde vino cada visita acreditada** (`DECISIONES #756`, `specs/analitica-para-decidir.md` §4.8.bis).
 *
 * Desde `#756` la búsqueda por correo o móvil en la puerta también acredita, como el escaneo del carné (`#741`). Una
 * tecleada puede ser una consulta sin visita (lo dejó escrito `#741`), así que la fila dice su origen: `card` (el
 * escaneo) o `lookup` (la búsqueda tecleada). Así el cuadro puede separarlas y JumpPoints, cuando llegue, decidir cuáles
 * cuentan. Las filas de antes quedan a `null`: se hicieron con el botón que se retiró en `#234`, y no se inventa su
 * origen. No se reconstruyen visitas desde el rastro (`[DECIDIDO owner]` 27-09).
 *
 * Aditiva y anulable: sin datos que mover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_visits', function (Blueprint $table) {
            $table->string('source', 16)->nullable()->after('registered_by');
        });
    }

    public function down(): void
    {
        Schema::table('customer_visits', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
