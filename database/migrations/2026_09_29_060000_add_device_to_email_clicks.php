<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Desde qué se pulsó** (`specs/correos-salientes.md` §4.10, `#796`, la C2b): `mobile`, `tablet` o `desktop`, sacado del
 * agente de usuario AL PULSAR (`Services\Analytics\Device`) sin guardar el agente. Nulo si no se sabe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_clicks', function (Blueprint $table) {
            $table->string('device', 8)->nullable()->after('route');
        });
    }

    public function down(): void
    {
        Schema::table('email_clicks', function (Blueprint $table) {
            $table->dropColumn('device');
        });
    }
};
