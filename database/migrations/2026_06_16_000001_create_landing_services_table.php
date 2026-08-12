<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entidad CMS «LandingService» (#256, `docs/PLAN-SERVICIOS-DATA-DRIVEN.md`, modelo A).
 *
 * EDITORIAL de la página /servicios + del selector «Servicios» del nav. Es la ÚNICA fuente de
 * clasificación de superficie de packs: un pack con un LandingService que lo referencie se anuncia
 * en /servicios (y SALE de la sección Cumpleaños — `TicketType::whereDoesntHave('landingService')`).
 *
 * Lo COMERCIAL (precio, complementos, mín/máx, aforo) vive en el `ticket_type_id` vinculado + su
 * `Zone` (fuente única, sin drift). `ticket_type_id` NULL = sección de solo-contacto («Pedir
 * información»: colegios, eventos a medida). Los campos de texto multiidioma se guardan como JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_services', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();               // = anchor de /servicios#{slug} (lo usa el nav)
            $table->json('accent_word')->nullable();        // palabra grande de acento (i18n)
            $table->json('title')->nullable();              // título de la sección (i18n)
            $table->json('body')->nullable();               // descripción (i18n)
            $table->json('zone_label')->nullable();         // badge «Zona …» (i18n)
            $table->json('specs')->nullable();              // lista [{label,value}] por idioma (i18n)
            $table->json('nav_subtitle')->nullable();       // subtítulo del item del nav (i18n)
            $table->string('image')->nullable();            // ruta de la imagen (public/…)
            // Pack COMPRABLE de esta sección. NULL = solo-contacto. `unique` = relación 1:1 (HasOne):
            // un pack no puede estar en dos secciones (se duplicaría su card). NULL admite repetición
            // (MySQL no compara NULLs), así que varias secciones de solo-contacto conviven. nullOnDelete:
            // si se borra el pack, la sección degrada a contacto (no rompe ni deja FK colgando).
            $table->foreignId('ticket_type_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);    // aparece en /servicios
            $table->boolean('show_in_nav')->default(true);  // aparece en el selector «Servicios» del nav
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_services');
    }
};
