<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ofertas promocionales INFORMATIVAS (#270, `docs/PLAN-OFERTAS-WIDGET.md`). Entidad CMS editorial:
 * título (i18n) + imagen. Gobiernan el widget flotante «caja de regalo» de la landing, que SOLO
 * aparece si hay ofertas activas. Sin dinero/carrito. La imagen es una subida real del panel
 * (disco `uploads` → public/uploads/ofertas, sin symlink); aquí se guarda su ruta relativa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();              // título de la oferta (i18n {es,en,fr})
            $table->string('image')->nullable();            // ruta relativa en el disco `uploads` (ofertas/<uuid>.webp)
            $table->unsignedInteger('position')->default(0); // orden del carrusel (reordenable)
            $table->boolean('is_active')->default(true);    // gobierna la visibilidad del widget
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
