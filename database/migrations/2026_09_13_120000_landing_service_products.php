<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **Un servicio vende VARIOS productos** (`DECISIONES #588`, `[DECIDIDO owner]`).
 *
 * Hasta aquí `landing_services.ticket_type_id` era un 1:1: un servicio, un pack. Una excursión de
 * colegio son DOS productos (2 horas y 3 horas), así que el segundo se quedaba sin servicio y **salía
 * anunciado en la página de cumpleaños** —el síntoma que vio el owner—, y la tabla de `/servicios`
 * tenía que teclearse aparte porque el servicio no alcanzaba los dos productos.
 *
 * ▶ La tabla de enlace mantiene las dos reglas del 1:1:
 *  · `ticket_type_id` **UNIQUE** — un producto se anuncia en UN servicio (se duplicaría su tabla).
 *  · su EXISTENCIA saca al producto de Cumpleaños (`TicketType::scopeBirthdaySurfacePacks`).
 * ⚠️ `cascadeOnDelete` en los dos lados: borrar el pack o el servicio borra el enlace, que es lo que
 * hacía el `nullOnDelete` de la columna (el servicio degrada a «Pedir información»).
 * ⚠️ Idempotente: se puede correr sobre una base que ya la tenga.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('landing_service_products')) {
            Schema::create('landing_service_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('landing_service_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ticket_type_id')->unique()->constrained()->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('landing_services', 'ticket_type_id')) {
            return;
        }

        foreach (DB::table('landing_services')->whereNotNull('ticket_type_id')->get(['id', 'ticket_type_id']) as $row) {
            DB::table('landing_service_products')->insertOrIgnore([
                'landing_service_id' => $row->id,
                'ticket_type_id' => $row->ticket_type_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ⚠️ El orden lo impone MySQL: la FK antes que el índice que la sostiene, y la columna al final.
        Schema::table('landing_services', function (Blueprint $table) {
            $table->dropForeign(['ticket_type_id']);
        });
        Schema::table('landing_services', function (Blueprint $table) {
            $table->dropUnique(['ticket_type_id']);
        });
        Schema::table('landing_services', function (Blueprint $table) {
            $table->dropColumn('ticket_type_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('landing_services', 'ticket_type_id')) {
            Schema::table('landing_services', function (Blueprint $table) {
                $table->foreignId('ticket_type_id')->nullable()->unique()->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('landing_service_products')) {
            // Vuelve UN producto por servicio —el primero—: el 1:1 no puede guardar más.
            foreach (DB::table('landing_service_products')->orderBy('id')->get() as $link) {
                DB::table('landing_services')->where('id', $link->landing_service_id)->whereNull('ticket_type_id')
                    ->update(['ticket_type_id' => $link->ticket_type_id]);
            }
            Schema::drop('landing_service_products');
        }
    }
};
