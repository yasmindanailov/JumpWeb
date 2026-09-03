<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El EJE de FASE de un complemento (`specs/complementos-post-reserva.md` §4.1, `DECISIONES #413`).
 *
 * `stage` dice **cuándo se VENDE** este enganche —`booking` al reservar, `postform` después— y no
 * «dónde lo ve el cliente». La precisión importa: de ella cuelga la propiedad que sostiene toda la
 * feature (§1.3), porque un `postform` **no nace nunca con el pedido** y por tanto su línea vale 0 al
 * nacer, así que quitarla es neutro en dinero.
 *
 * `postform_cutoff_hours` es el plazo de corte **por complemento** (`[DECIDIDO owner]` Q2): «tapas
 * 48 h», «cubo de refrescos 2 h». Es NULABLE en el esquema porque un enganche `booking` no tiene
 * plazo que declarar, y **obligatorio para un `postform`** (D10) — lo impone el guard del pivote, no
 * la columna: la alternativa, `null` = «hereda el cierre del post-form», heredaba el reloj torcido de
 * §4.9 y dejaba quitar un extra hasta 2 h después de la fiesta.
 *
 * ⚠️ **Default `booking` y NO se reescribe ninguna fila**: los 29 enganches reales quedan idénticos
 * por construcción, que es el criterio de éxito 1 de la spec.
 *
 * ⚠️ **`string` y no `enum` de MySQL**: la suite corre en SQLite (`SUITE-04`) y un `enum` obliga a un
 * `ALTER` distinto por motor cada vez que la lista crezca. La lista cerrada la impone el modelo
 * (`ProductAddon::STAGES`), que es donde ya viven las demás reglas de este pivote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_addons', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_addons', 'stage')) {
                $table->string('stage', 20)->default('booking')->after('position');
            }
            if (! Schema::hasColumn('product_addons', 'postform_cutoff_hours')) {
                $table->unsignedInteger('postform_cutoff_hours')->nullable()->after('stage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_addons', function (Blueprint $table): void {
            if (Schema::hasColumn('product_addons', 'postform_cutoff_hours')) {
                $table->dropColumn('postform_cutoff_hours');
            }
            if (Schema::hasColumn('product_addons', 'stage')) {
                $table->dropColumn('stage');
            }
        });
    }
};
