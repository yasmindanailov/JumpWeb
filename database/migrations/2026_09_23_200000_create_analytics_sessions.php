<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LAS SESIONES DEL LIBRO DE EVENTOS** (`docs/specs/analitica.md` §4.1; `DECISIONES #678`).
 *
 * Una sesión es un visitante (`visitor_id`, la cookie propia de 13 meses) con menos de 30 minutos entre dos
 * eventos. Aquí vive lo que se captura UNA vez por visita y el embudo agrupa después: por dónde entró, de
 * dónde venía, la campaña, el dispositivo y el idioma.
 *
 * ⚠️⚠️ **`user_id` es nullable y SIN clave foránea, a propósito.** Es el régimen IDENTIFICADO de la spec:
 * solo se rellena con la categoría `analytics` consentida, y lo vacía `User::anonymize()` por tabla —una
 * `nullOnDelete` no se dispararía nunca, porque la supresión del art. 17 sobrescribe la fila de `users` y no
 * la borra (`RGPD-01`; la revisión adversarial lo midió: spec §7.1, rgpd-2). Sin `user_id`, la sesión es
 * estadística anónima del editor: la exención de la guía AEPD 2024.
 *
 * ⚠️ **Sin IP ni user agent.** El UA se lee al ingerir para `device` e `is_bot` y se descarta: guardarlo
 * convertiría cada fila en un dato personal que la exención no cubre.
 *
 * ⚠️ Los índices son los que la ingesta y la poda USAN (spec §7.1, rendimiento-7): resolver la sesión
 * abierta de un visitante es `(visitor_id, last_seen_at)` y podar por antigüedad es `last_seen_at`. Sin
 * `timestamps()`: `started_at`/`last_seen_at` son el tiempo de la sesión, y `MassPrunable` poda por el
 * segundo a los 25 meses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 36);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            // `web` o `app`: la superficie que abrió la sesión.
            $table->string('surface', 8)->default('web');
            // La ruta de entrada YA NORMALIZADA (patrón de ruta, sin query ni tokens).
            $table->string('entry_route', 255)->nullable();
            $table->string('referrer_host', 255)->nullable();
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 160)->nullable();
            $table->string('utm_content', 160)->nullable();
            $table->string('utm_term', 160)->nullable();
            $table->string('ref', 60)->nullable();
            // `gclid`, `fbclid`, `ttclid`: identificadores de clic de las plataformas. Solo con la
            // categoría `marketing` consentida; sin ella no se guardan.
            $table->json('click_ids')->nullable();
            $table->string('device', 8)->nullable();
            $table->string('locale', 8)->nullable();
            // Foto de las categorías consentidas en el momento de abrir la sesión.
            $table->json('consent')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_internal')->default(false);

            $table->index(['visitor_id', 'last_seen_at']);
            $table->index('last_seen_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_sessions');
    }
};
