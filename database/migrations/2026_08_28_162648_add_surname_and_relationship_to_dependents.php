<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apellidos y relación con el titular en una persona a cargo (`#236`, `[DECIDIDO owner]`).
 *
 * Hasta ahora la entidad era «nombre y fecha de nacimiento, nada más» (`DECISIONES #142`). El owner
 * añade dos datos que el mostrador sí necesita: **los apellidos** —para identificar sin ambigüedad
 * a quien no conoce a la familia— y **qué relación** tiene el adulto con el menor (padre, madre,
 * tutor/a legal, abuelo/a, otro), que es lo que sostiene que ese adulto pueda firmar por él.
 *
 * ⚠️ **Las dos columnas nacen NULABLES y no se rellenan a la fuerza.** Los menores ya declarados
 * no tienen apellidos ni relación, y no hay de dónde sacarlos: inventar un valor por defecto sería
 * meter un dato falso en una tabla que alimenta una FIRMA legal. Se exigen en el ALTA nueva (la
 * validación de `POST /me/dependents`), no en el esquema, y quien tenga fichas viejas las verá sin
 * completar hasta que las edite. `MODELO-DATOS.md` lo recoge.
 *
 * ⚠️ **`relationship` es una cadena corta, no un enum de base de datos**: las opciones son una lista
 * fija traducida (`Dependent::RELATIONSHIPS`) y añadir una en el futuro no puede exigir una
 * migración. La validación la cierra en la aplicación, que es donde vive el catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dependents', function (Blueprint $table): void {
            $table->string('surname', 120)->nullable()->after('name');
            $table->string('relationship', 32)->nullable()->after('surname');
        });
    }

    public function down(): void
    {
        Schema::table('dependents', function (Blueprint $table): void {
            $table->dropColumn(['surname', 'relationship']);
        });
    }
};
