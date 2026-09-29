<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Las aperturas de cada correo** (`specs/correos-salientes.md` §4.12, `#797`, la C3).
 *
 * - `email_sends.tracks_opens`: si el correo salió CON el píxel. Sin él, sus aperturas «no se miden».
 * - `email_opens`: una fila por cada vez que se pidió el píxel, con CUÁNDO, su ORIGEN (`apple`, `gmail`, `direct`), la clase
 *   del aparato si se sabe y el veredicto (`null` cuenta; `apple`, `early`, `repeat` no). Sin IP ni agente de usuario. Se va
 *   con su envío (cascada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_sends', function (Blueprint $table) {
            $table->boolean('tracks_opens')->default(false)->after('tracks_clicks');
        });

        Schema::create('email_opens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_send_id')->constrained('email_sends')->cascadeOnDelete();
            $table->string('source', 8);
            $table->string('device', 8)->nullable();
            $table->string('verdict', 16)->nullable();
            $table->timestamp('opened_at');

            $table->index(['email_send_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_opens');

        Schema::table('email_sends', function (Blueprint $table) {
            $table->dropColumn('tracks_opens');
        });
    }
};
