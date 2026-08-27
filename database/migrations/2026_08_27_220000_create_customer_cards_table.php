<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 · subsistema A — el CARNÉ QR del cliente (`docs/specs/identidad-qr-puerta.md` §4.1, §4.4,
 * §4.5, §9.2 A·1; `DECISIONES #208`).
 *
 * `customer_cards`: un identificador ESTABLE, OPACO y ROTABLE por titular, que viaja por correo y se
 * escanea en la puerta. Escanearlo NO autentica (§4.2): es una búsqueda, y la autoridad la pone la
 * sesión del empleado. Por eso puede ser estable e imprimirse.
 *
 *  - `token_hash` (sha256, ÚNICO): para BUSCAR por él. Sobrevive a una rotación de `APP_KEY`.
 *  - `token` (cast `encrypted`): para VOLVER A PINTARLO (cada correo de confirmación). Con la clave
 *    rotada no se puede leer y `CustomerCard::plainToken()` devuelve `null` en vez de lanzar (§8.1).
 *  - `revoked_at` + `revoked_reason` (`rotated` · `revoked` · `anonymized`): tabla propia con HISTORIAL,
 *    no una columna en `users`, porque una auditoría de «se escaneó el carné X» tiene que resolverse
 *    aunque X se haya rotado — y porque el carné es la siguiente credencial que `RGPD-06` existe para
 *    no olvidar: entra en `User::revokeAllAccess()` desde este commit. Uno ACTIVO por titular, lo
 *    garantiza `CustomerCards` bajo el lock de la fila del titular (un índice parcial no es portable).
 *  - `user_id` CASCADE: sin titular no hay carné que buscar ni repintar; así la limpieza de go-live
 *    no tiene que conocer la tabla (a diferencia de `personal_access_tokens`, que no tiene FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 20)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_cards');
    }
};
