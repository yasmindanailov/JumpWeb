<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3.4.1 — Cimientos de las páginas esenciales (data-driven).
 * - pages: textos libres/legales, multiidioma como JSON {es,en,fr}.
 * - contact_messages: bandeja del formulario de contacto/grupos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Páginas de texto libre (legales, etc.). Editable desde el panel (Fase 7).
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('title');
            $table->json('body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Mensajes del formulario de contacto / grupos (se guardan + se envía email).
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message');
            $table->string('type')->default('contact'); // contact | group
            $table->string('locale', 5)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('read_at')->nullable(); // null = sin leer
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('pages');
    }
};
