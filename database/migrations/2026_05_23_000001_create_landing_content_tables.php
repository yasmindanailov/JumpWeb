<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de contenido de la landing (data-driven / white-label).
 * Los campos de texto multiidioma se guardan como JSON {es,en,fr}.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Configuración del negocio (clave-valor). White-label.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // Zonas (Jump / Kids).
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('subtitle')->nullable();
            $table->json('description')->nullable();
            $table->json('age_label')->nullable();
            $table->json('age_range')->nullable();
            $table->unsignedInteger('area_sqm')->nullable();
            $table->unsignedInteger('rides_count')->nullable();
            $table->string('accent')->default('jump'); // jump | kids
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Atracciones (pertenecen a una zona).
        Schema::create('attractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('age')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tipos de entrada / tarifas (sección de precios).
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('period_label')->nullable();   // "por persona", "5 × 60 min"...
            $table->unsignedInteger('price_cents')->default(0);
            $table->json('items')->nullable();           // lista de ventajas por idioma
            $table->json('badge')->nullable();           // "Top"
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Packs de eventos / cumpleaños.
        Schema::create('event_packages', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('features')->nullable();        // lista {t,s} por idioma
            $table->unsignedInteger('price_cents')->default(0);
            $table->json('price_unit')->nullable();      // "/ niño"
            $table->unsignedInteger('min_guests')->nullable();
            $table->unsignedInteger('max_guests')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Preguntas frecuentes.
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->json('question');
            $table->json('answer');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Normas del parque.
        Schema::create('park_rules', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('park_rules');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('event_packages');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('attractions');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('settings');
    }
};
