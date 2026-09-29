<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOS CÓDIGOS DE UN SOLO USO del acceso con código (A1 de `docs/specs/acceso-con-codigo.md` §4.1, `DECISIONES #848`):
 * el cliente entra con seis cifras enviadas a su correo. Se guarda su HUELLA (HMAC con la clave de la app y el correo),
 * nunca el código; vive 10 minutos y 5 intentos, y uno solo vivo por (correo, propósito).
 *
 * ⚠️ Va por CORREO y no por `user_id`: el cambio de correo (A2) verifica el correo NUEVO con un código a ESE correo, que
 * todavía no es de nadie. La supresión (`RGPD-01`) los borra por el correo del titular, como el token de reset.
 * Sin `updated_at`: los intentos y el uso se escriben con consultas condicionadas, no guardando el modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('purpose', 16);
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'purpose']);
            // La poda diaria (`model:prune`) borra por edad.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_codes');
    }
};
