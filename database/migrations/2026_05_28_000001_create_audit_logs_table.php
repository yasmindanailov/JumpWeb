<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7.0 — Tabla de auditoría inmutable de acciones sensibles del panel.
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §1.4 y `docs/DECISIONES.md` #118.
 *
 * Cada acción importante del panel (validar registro, marcar preparada/canjeada,
 * cancelar/reembolsar pedido, anonimizar usuario, cambiar settings, asignar roles…)
 * deja una fila aquí. No tiene `updated_at` ni `deleted_at`: las filas son inmutables.
 *
 * Privacidad: `payload` (JSON) se llena SOLO para acciones sin dato personal
 * (ej. cambios de estado de ticket). Para acciones con dato personal (validar
 * registro por email/teléfono) el `payload` queda null y se conserva solo
 * `payload_hash` (sha256) — permite probar correlación sin filtrar el dato en logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Quién (nullable para acciones del sistema: scheduler, jobs).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Qué (verbo + dominio: `orders.cancelled`, `tickets.redeemed`,
            // `registrations.validated`, `settings.updated`, etc.).
            $table->string('action')->index();

            // Sobre qué (polimórfico, nullable para acciones sin target como login).
            $table->nullableMorphs('target');

            // Cambios — solo si no hay dato personal en juego.
            $table->json('payload')->nullable();

            // Hash sha256 del dato original (siempre presente). En acciones SIN
            // dato personal coincide con sha256(json_encode(payload)); en acciones
            // con dato personal (ej. email buscado en puerta) es la única huella.
            $table->string('payload_hash', 64);

            // Contexto técnico.
            $table->string('ip', 45)->nullable();      // 45 chars cubre IPv6
            $table->string('user_agent', 255)->nullable();

            // Inmutable: solo created_at. Sin updated_at, sin soft delete.
            $table->timestamp('created_at')->useCurrent()->index();

            // Filtro común en informes: "todas las cancelaciones del último mes".
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
