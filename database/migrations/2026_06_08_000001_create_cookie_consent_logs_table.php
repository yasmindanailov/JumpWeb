<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #219 — Acreditación del consentimiento de cookies (responsabilidad proactiva, RGPD art. 5.2/7.1;
 * Guía AEPD: debe poder demostrarse quién consintió, cuándo y para qué).
 *
 * Tabla PROPIA (no se reutiliza `consents`): el consentimiento de cookies lo da también el
 * VISITANTE ANÓNIMO (sin cuenta), así que `user_id` es nullable; y se guarda `user_agent`, que
 * `consents` no tiene. Una fila por DECISIÓN explícita (aceptar/rechazar/guardar/cargar), no por
 * visita. Ver `docs/PLAN-COOKIES.md` §5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_consent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('categories');                 // {"maps":bool,"social":bool}
            $table->string('version');                  // CookieConsent::POLICY_VERSION
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            // Índice para la poda eficiente por antigüedad (`CookieConsentLog` es Prunable: se borran
            // las filas > 24 meses, la vida del consentimiento — minimización/limitación RGPD).
            $table->index('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_consent_logs');
    }
};
