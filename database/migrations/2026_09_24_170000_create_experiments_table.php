<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Los experimentos** (`docs/specs/analitica.md` §4.4, T5a): qué se está probando, con qué variantes y pesos,
 * y si está vivo. La ASIGNACIÓN no se guarda: la calcula el servidor en cada petición con
 * `hash(clave | sujeto)` (`Platform\Services\Analytics\Experiments`), así que la misma persona cae siempre en
 * la misma variante sin una fila por visitante, y `experiment_exposed` (el libro) es lo que dice a quién se le
 * enseñó de verdad.
 *
 *  · `key`: la clave que viaja al cliente y que lleva el hecho (`[a-z][a-z0-9_-]{0,47}`).
 *  · `variants`: lista ordenada de `{key, weight}`; el orden importa (es el reparto del hash) y los pesos son
 *    enteros positivos, no porcentajes: `50/50`, `1/1` o `90/10` dicen lo mismo con otra escala.
 *  · `active` + `started_at`/`ended_at`: vivo = activo y dentro de la ventana (`isRunning()`); apagarlo deja de
 *    asignar en ≤ 60 s (caché) y el histórico del libro se queda.
 *
 * Sin claves foráneas ni PII: es configuración del producto, como `settings`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiments', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name', 120);
            $table->json('variants');
            $table->boolean('active')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiments');
    }
};
