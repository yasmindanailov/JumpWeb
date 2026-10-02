<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **El GRUPO DE OPCIONES como entidad del producto** (`[DECIDIDO owner]` `DECISIONES #914`, `fiesta-sistema-nuevo.md` §4.21).
 *
 * Hasta aquí un grupo excluyente era solo una clave de texto repetida en cada enganche (`product_addons.choice_group`): sin
 * título ni ajustes propios. Esta tabla le da UNA fila por producto y clave, con lo que es del GRUPO y no de cada opción: su
 * título traducible («¿Qué merienda?»), si hay que elegir (`is_required`) y su orden. Las opciones siguen enganchadas por la
 * misma clave, así que nada de lo vendido ni de lo configurado cambia.
 *
 * `created_at` no es decoración: una fiesta vendida ANTES de que el grupo existiera no lo debe (`#914`, «No se les pide»).
 *
 * Aditiva y sin datos: los grupos de hoy (los de la reserva) siguen funcionando sin fila hasta que el panel les ponga una.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('addon_choice_groups')) {
            return;
        }

        Schema::create('addon_choice_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('ticket_types')->cascadeOnDelete();
            // La MISMA clave que sus opciones llevan en `product_addons.choice_group` (máx. 50, como allí).
            $table->string('key', 50);
            $table->json('title')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_choice_groups');
    }
};
