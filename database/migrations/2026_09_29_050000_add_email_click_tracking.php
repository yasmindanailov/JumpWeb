<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Los clics de cada correo** (`specs/correos-salientes.md` §4.8, la C2).
 *
 * - `email_sends.tracks_clicks`: si el correo salió CON la marca del envío (`jw_e`) en sus enlaces. Sin ella sus clics no se
 *   pueden contar, y el panel dice «no se mide» en vez de un cero que mentiría.
 * - `email_clicks`: una fila por visita que llega con esa marca, con QUÉ enlace (la ruta normalizada, sin query ni tokens) y
 *   CUÁNDO. `verdict` nulo = un clic que cuenta; `repeat`, `early` o `sweep` = no cuenta (el mismo enlace otra vez, antes de
 *   que nadie pudiera leerlo, o la ráfaga de un escáner). Se guardan todas: la ráfaga se reconoce por la hora de cada visita.
 *   Sin IP ni agente de usuario. Se va CON SU ENVÍO (cascada): la poda de `email_sends` es un borrado en bloque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_sends', function (Blueprint $table) {
            $table->boolean('tracks_clicks')->default(false)->after('failed_at');
        });

        Schema::create('email_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_send_id')->constrained('email_sends')->cascadeOnDelete();
            $table->string('route', 255);
            $table->string('verdict', 16)->nullable();
            $table->timestamp('clicked_at');

            $table->index(['email_send_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_clicks');

        Schema::table('email_sends', function (Blueprint $table) {
            $table->dropColumn('tracks_clicks');
        });
    }
};
