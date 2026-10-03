<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **«Antes de venir», lo de cada producto** (la R2b de `docs/specs/correos-rediseno.md` §4.3): las líneas que el correo de la
 * reserva pone en su lista «Antes de venir» además de las que salen de los datos (quién firma, lo comprado, la hora).
 * Son de CADA parque y de cada producto —«Todos los profesores entran gratis», «Los calcetines van incluidos»—, y el brief las
 * escribía como política de PlayJump: en el producto, un dato de la instalación (`#1`).
 *
 * - `ticket_types.before_visit` (i18n, nulo): una LISTA por idioma, como `features` (una línea por cosa en el panel). Solo en
 *   un producto principal; en un complemento se borra al guardar.
 *
 * Ningún valor se escribe aquí: los pone el panel de cada instalación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_types', 'before_visit')) {
                $table->json('before_visit')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_types', 'before_visit')) {
                $table->dropColumn('before_visit');
            }
        });
    }
};
