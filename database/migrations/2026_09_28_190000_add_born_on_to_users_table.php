<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **La fecha de nacimiento del titular, entera y opcional** (`DECISIONES #792`, `specs/analitica-para-decidir.md` §4.14, TP·1).
 *
 * Para conocer al público (la TP·2 la cuenta por tramos, sin persona). Solo fecha, sin hora ni zona, como
 * `dependents.born_on`: la edad se deriva el día del parque y nunca se guarda. Anulable porque es opcional en las cuatro
 * puertas y las cuentas de antes no la tienen: no se inventa. `RGPD-01`: `anonymize()` la borra (el censo la declara).
 *
 * Aditiva y anulable: sin datos que mover.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('born_on')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('born_on');
        });
    }
};
