<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **A QUIÉN SE LE PREGUNTA, en cada encuesta** (`DECISIONES #819`, `[DECIDIDO owner]` 02-10;
 * `docs/specs/puerta-nueva.md` §4.4, la P1c; `docs/specs/encuestas.md` §4.1).
 *
 * `all` (a todos, lo de siempre) o `first_visit` (solo en su primera visita: ni visita acreditada ni día cobrado antes,
 * la regla de `VisitFacts::isFirstVisit()`). Lo decide el panel porque depende de la pregunta: «¿Cómo nos conociste?» solo
 * tiene sentido la primera vez; «¿Qué tal hoy?», para todos.
 *
 * Las encuestas que ya existen quedan en `all`: es lo que hacían.
 *
 * **RGPD**: no es dato personal — es una opción de la encuesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('audience', 16)->default('all')->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
