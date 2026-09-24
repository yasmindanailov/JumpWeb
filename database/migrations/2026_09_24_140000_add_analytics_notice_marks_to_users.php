<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Las dos marcas del AVISO del régimen identificado** (`docs/specs/analitica.md` §4.3, T3a·4).
 *
 * Las cuentas que existían antes de la v3 de la política de cookies se informan ANTES de activar el enlace
 * sesión↔cuenta, por dos canales: el correo (`analytics:notify-accounts`, la noche del despliegue) y el aviso
 * del índice del área de cliente. Cada canal deja su marca:
 *  · `analytics_notified_at` — el correo salió. Es la idempotencia del comando: se marca ANTES de encolar,
 *    así que un fallo del correo deja a la cuenta sin él pero no con seis (el molde de `reservations:eve-notice`).
 *  · `analytics_notice_seen_at` — el titular despidió el aviso del cajón (`DELETE /me/analytics-notice`).
 *
 * El aviso del cajón se pinta cuando la primera está y la segunda no: una cuenta creada DESPUÉS del comando ya
 * se registró bajo la política nueva y no recibe ninguno de los dos. Las dos son marcas, no dato personal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('analytics_notified_at')->nullable()->after('first_attribution');
            $table->timestamp('analytics_notice_seen_at')->nullable()->after('analytics_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['analytics_notified_at', 'analytics_notice_seen_at']);
        });
    }
};
