<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Los objetivos del mes** (T3c·2 de `docs/specs/analitica-para-decidir.md` §4.13, `DECISIONES #759`): cuánto quiere el
 * parque que valga una cifra del cuadro en un mes. Configuración del producto, como `experiments`: sin datos personales
 * (quien lo puso es un empleado, y la fila lo suelta si su cuenta se borra).
 *
 *  - `metric_key` — la clave de la cifra en el catálogo del cuadro (`money.net`, `traffic.conversion`…).
 *  - `month` — el PRIMER día del mes al que se refiere.
 *  - `target` — en la unidad de la cifra: céntimos, unidades o puntos básicos (10000 = 100 %).
 *  - `set_by` — quién lo puso por última vez.
 *
 * Una por cifra y mes (`UNIQUE`): cambiarlo es sobrescribirla, y el rastro (`analytics.goals_updated`) guarda el antes y
 * el después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_goals', function (Blueprint $table): void {
            $table->id();
            $table->string('metric_key', 64);
            $table->date('month');
            $table->unsignedBigInteger('target');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['metric_key', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_goals');
    }
};
