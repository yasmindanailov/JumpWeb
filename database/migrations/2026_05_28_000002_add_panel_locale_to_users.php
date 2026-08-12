<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7.0 — Idioma del panel admin por usuario, aislado del idioma de la web.
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §1.3 y `docs/DECISIONES.md` #123.
 *
 * La columna `users.locale` (Fase 4.1) es el idioma del CLIENTE en la web pública
 * y en los emails que recibe (ES/EN/FR — decisión #40). El panel admin lo usan
 * empleados con perfil distinto y soporta SOLO `es` y `zh_CN` (#123).
 *
 * Mantener locales separados evita que cambios en uno alteren al otro: un staff
 * chino mantiene el panel en `zh_CN` aunque navegue por la web pública en `fr`,
 * y al revés. Null = usuario que aún no ha elegido → middleware aplica el default
 * del panel (`es`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('panel_locale', 10)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('panel_locale');
        });
    }
};
