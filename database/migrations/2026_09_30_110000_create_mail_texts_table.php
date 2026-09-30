<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOS TEXTOS DE LOS CORREOS, EDITADOS DESDE EL PANEL (R1·T de `docs/specs/correos-rediseno.md` §4.2.1, `[DECIDIDO owner]`
 * `#802`): una fila por clave de texto (`emails.order_confirmation.intro`) e idioma, con el texto del PARQUE. Sin fila, el
 * de fábrica del producto (`lang/`): «volver al de fábrica» es borrarla. Las variables se guardan como `{variable}`.
 *
 * ⚠️ No es un dato personal: es el copy del parque. `updated_by` dice quién lo tocó (el rastro entero va a `audit_logs`) y
 * se queda a `null` si esa cuenta se borra: el texto sigue siendo del parque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_texts', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 191);
            $table->string('locale', 8);
            $table->text('text');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_texts');
    }
};
