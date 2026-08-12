<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.1 — Ampliar `users` con perfil + consentimientos legales.
 * El teléfono es obligatorio en el registro (validación de formulario, 4.2);
 * en BD es nullable para no romper usuarios creados desde el panel/seed.
 * Ver docs/04-MODELO-DATOS.md (§3) y docs/SEGURIDAD.md (§7, teléfono en claro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('locale', 5)->default('es')->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('locale');
            $table->boolean('marketing_opt_in')->default(false)->after('last_login_at');
            // Sello de aceptación de cada documento legal (la prueba detallada vive en `consents`).
            $table->timestamp('privacy_accepted_at')->nullable()->after('marketing_opt_in');
            $table->timestamp('terms_accepted_at')->nullable()->after('privacy_accepted_at');
            $table->timestamp('waiver_accepted_at')->nullable()->after('terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'locale',
                'last_login_at',
                'marketing_opt_in',
                'privacy_accepted_at',
                'terms_accepted_at',
                'waiver_accepted_at',
            ]);
        });
    }
};
