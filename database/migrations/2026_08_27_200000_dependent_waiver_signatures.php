<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 · menores a cargo, tanda 2 — la firma del MENOR (`docs/specs/menores-a-cargo.md` §4.3, §9;
 * `DECISIONES #197`).
 *
 * 1. **`waiver_signatures.subject_id` → `dependents.id`, RESTRICT.** «No se puede borrar la fila si
 *    detrás hay un waiver firmado» (§4.4) deja de ser solo la guarda de `Dependent::deleting` y pasa a
 *    ser la base de datos. `NULL` en las firmas del titular. ▶ Medido: la gramática SQLite de Laravel
 *    13 recrea la tabla para añadir la FK, así que la suite la ejerce igual que MySQL.
 * 2. **La identidad del menor viaja EN la firma** (`subject_name`, `subject_born_on`), como la del
 *    titular desde `#161`: el registro tiene que seguir diciendo DE QUIÉN se aceptó aunque la fila de
 *    `dependents` se desvincule o la cuenta se suprima. Entran en el hash: esquema canónico **v3**
 *    (`WaiverSignature::HASHED_FIELDS_BY_VERSION`); lo firmado con v1/v2 sigue verificando con el suyo.
 *
 * Precondición: no existe todavía ninguna firma de menor (hasta esta tanda `dependent` era código sin
 * escritor). Si una instalación tuviera alguna, la FK fallaría contra ids sin fila: se aborta con un
 * mensaje claro en vez de dejar la migración a medias.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphans = DB::table('waiver_signatures')->where('subject_type', 'dependent')->count();
        if ($orphans > 0) {
            throw new RuntimeException("Hay {$orphans} firmas de menor anteriores a la tabla `dependents`: resuélvelas a mano antes de migrar.");
        }

        Schema::table('waiver_signatures', function (Blueprint $table) {
            $table->string('subject_name', 120)->nullable()->after('holder_email');
            $table->date('subject_born_on')->nullable()->after('subject_name');
            $table->foreign('subject_id')->references('id')->on('dependents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('waiver_signatures', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropColumn(['subject_name', 'subject_born_on']);
        });
    }
};
