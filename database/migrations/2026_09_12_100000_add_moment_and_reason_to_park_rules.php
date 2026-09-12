<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EL MOMENTO y EL PORQUÉ de una norma (`docs/specs/rediseno-desde-canvas.md` §5.5 · `DECISIONES
 * #533`). Los dos huecos que §4.2 midió al inventariar y que `/normas` no podía llenar: la tabla
 * tenía cuatro columnas (`name`, `description`, `position`, `is_active`) y el diseño pide dos cosas
 * más.
 *
 * ❗❗ **`moment` es lo que ORDENA la página, y es el único orden que el visitante puede usar.** El
 * artboard agrupa por *antes de venir · en la puerta · dentro*: las primeras deciden **si entras**
 * —y son las que la portada adelanta—, y las de dentro no cambian ninguna decisión. Ordenar por
 * tema, o alfabéticamente como estaba, mezcla las dos cosas.
 *
 * ⚠️⚠️ **NULLABLE, y `null` significa «sin agrupar», no un momento por defecto.** Es deliberado y
 * tiene consecuencia medible: **una norma sin momento se sigue publicando**. Si el grupo fuera
 * obligatorio, una norma creada desde el panel sin elegirlo desaparecería de la página sin fallar
 * y sin avisar — y hay una guarda (`CmsLandingFlowTest`) que crea exactamente esa norma. Un
 * `default('inside')` sería peor: afirmaría un momento que nadie ha decidido.
 *
 * ⚠️ **Es una cadena de una lista cerrada** (`VenueRule::MOMENTS`), no un `enum` de base de datos:
 * añadir un momento en un `enum` de MySQL es una migración con `ALTER`, y esta lista la gobierna el
 * producto —el panel ofrece exactamente esos tres— no el esquema.
 *
 * ❗ **`reason` es JSON traducible, igual que `name` y `description`**, porque es texto que lee el
 * cliente en su idioma, y **nullable porque no toda norma tiene motivo que contar**: el artboard lo
 * dice y su propio contenido lo cumple —la de la zona Kids no lleva—. La página lo pinta *solo si
 * está*, y esa ausencia es una respuesta, no una falta.
 *
 * ▶ El motivo de que esto sea DOMINIO y no una cadena más en la vista: *«una norma con motivo se
 * cumple y una norma sola se discute en la puerta»*. Si el porqué viviera en la plantilla, el parque
 * no podría cambiarlo desde el panel y ninguna otra instalación tendría los suyos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('park_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('park_rules', 'moment')) {
                $table->string('moment', 20)->nullable()->after('description');
            }
            if (! Schema::hasColumn('park_rules', 'reason')) {
                $table->json('reason')->nullable()->after('moment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('park_rules', function (Blueprint $table): void {
            foreach (['moment', 'reason'] as $columna) {
                if (Schema::hasColumn('park_rules', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
