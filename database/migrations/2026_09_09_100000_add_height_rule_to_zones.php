<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La REGLA DE ALTURA de una zona (`docs/specs/rediseno-desde-canvas.md` §5.4 · `DECISIONES #478`).
 *
 * La sección «Para quién» del mockup dice la regla del parque en dos mitades —*manda la edad, y si
 * no cuadra manda la altura*— y dibuja la segunda en una barra con su umbral colocado. Para eso
 * hace falta un **número**, y hoy no lo hay: la altura vive dentro del texto libre de la edad
 * (`zones.age_range`, medido: `"+8 años · 1,30 m+"`).
 *
 * ⚠️⚠️ **Esto es DOMINIO, no diseño, y por eso es una columna y no una cadena más.** Con el número
 * estructurado pasan tres cosas que con el texto libre no pueden pasar: el diagrama se dibuja solo,
 * una instalación **sin restricción de altura simplemente no lo pinta** —que es lo que hace que
 * esto sea producto y no la web de este parque— y el dato se puede validar. Con texto libre, cada
 * instalación lo redacta a su manera y nadie puede comprobar nada.
 *
 * ⚠️ **NULLABLE y sin default**, y `null` significa **«esta zona no tiene regla de altura»**, no
 * «cero». Es el caso NORMAL, no el raro: un parque de otro sector puede no restringir por altura, y
 * un `default(0)` afirmaría un umbral que nadie ha medido y pintaría una barra con el corte abajo
 * del todo. La sección pregunta por el dato antes de dibujar nada.
 *
 * ▶ **Dos columnas y no una.** `height_min_cm` es «hay que medir AL MENOS esto» (la zona grande) y
 * `height_max_cm` es «hasta esto» (la zona pequeña). Con una sola habría que deducir el sentido de
 * qué zona es, y eso es exactamente el tipo de regla implícita que este repo paga caro: la misma
 * cifra —130— significa lo contrario en Kids que en Jump.
 *
 * ⚠️ **En CENTÍMETROS enteros y no en metros decimales**: la presentación decide si escribe «1,30 m»
 * o «130 cm», y un entero no arrastra los errores de coma flotante de un `decimal` que solo existe
 * para dividirse por 100 al pintarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            if (! Schema::hasColumn('zones', 'height_min_cm')) {
                $table->unsignedSmallInteger('height_min_cm')->nullable()->after('age_range');
            }
            if (! Schema::hasColumn('zones', 'height_max_cm')) {
                $table->unsignedSmallInteger('height_max_cm')->nullable()->after('height_min_cm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            foreach (['height_min_cm', 'height_max_cm'] as $columna) {
                if (Schema::hasColumn('zones', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
