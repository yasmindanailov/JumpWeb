<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 · waiver (`DECISIONES #181`, revisión de la tanda 4, S-1): la firma que nace de la aceptación
 * PENDIENTE del alta tiene que llevar la IP y el navegador del momento en que la persona MARCÓ la
 * casilla — no los de la petición que verifica el correo, que en pay-first puede ser la notificación
 * S2S de Redsys (`RedsysReturnHandler::autoVerifyBuyer()` también emite `Verified`). PII del alta:
 * `anonymize()` las nulifica y el censo las declara.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('waiver_pending_ip', 45)->nullable()->after('waiver_pending_channel');
            $table->string('waiver_pending_user_agent', 512)->nullable()->after('waiver_pending_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['waiver_pending_ip', 'waiver_pending_user_agent']);
        });
    }
};
