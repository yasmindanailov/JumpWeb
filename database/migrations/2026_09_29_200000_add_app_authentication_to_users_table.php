<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EL AUTHENTICATOR DE LOS ADMINISTRADORES (P3 de `docs/specs/panel-a-salvo.md` §4.3, `DECISIONES #847`/`#851`): el
 * secreto de su app de autenticación y sus códigos de recuperación, los dos CIFRADOS por el modelo (`encrypted`,
 * `encrypted:array`) y ocultos. Filament guarda los códigos ya con hash. `TEXT`: el cifrado alarga el valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
