<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «AVÍSAME DE FECHAS» (`specs/avisame-de-fechas.md` §4.1, `[DECIDIDO owner]` `#750`): el adulto que firmó la autorización
 * de un invitado CON su correo pide, desde el recibo, un correo antes del cumpleaños del niño.
 *
 * ⚠️ La fila NO copia ni un dato del menor ni del adulto: todo sale de la autorización firmada, que es además la PRUEBA
 * del consentimiento (quién, cuándo, con qué texto). Por eso la clave ajena va en CASCADA: suprimir la autorización
 * arrastra la marca, y no queda un correo suelto sin su porqué.
 *  - `revoked_at`: la baja (art. 7.3 y LSSI art. 22.1). La fila se queda: prueba que en su día se pidió.
 *  - `sent_for`: el cumpleaños para el que ya salió el correo (uno por cumpleaños); `sent_at`, cuándo.
 *  - `locale`: el idioma en que se marcó la casilla, que es el del correo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birthday_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guardian_authorization_id')->unique()->constrained('guardian_authorizations')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->timestamp('accepted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->date('sent_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_reminders');
    }
};
