<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.1 — Roles y permisos (tablas propias, sin paquete externo).
 * Roles iniciales: admin, customer (preparado para staff). El permiso fino se
 * usará desde la Fase 7; aquí dejamos la estructura lista. `admin` = super-admin
 * (puede todo) vía Gate global en AppServiceProvider.
 * Decisión #42 (roles + Gates) actualizada por #44 (se añade `permissions`).
 * Ver docs/04-MODELO-DATOS.md (§3) y docs/DECISIONES.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();   // admin | customer | (staff)
            $table->string('label');            // etiqueta legible para el panel
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();   // p. ej. orders.refund, content.edit
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']); // un usuario no repite rol
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
