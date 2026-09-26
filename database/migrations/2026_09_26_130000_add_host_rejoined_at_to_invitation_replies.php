<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3c de `specs/fiesta-sistema-nuevo.md` (§4.8, `#747`): «AL FINAL VIENE». La familia dijo que no, cambió de opinión y se
 * lo dijo al anfitrión, que la vuelve a contar en su lista (el `volver()` del diseño reescribe la respuesta a «sí»). La
 * respuesta pasa a «sí» y este sello dice QUIÉN la cambió y cuándo: el rastro de que el «no» fue del padre y el «sí», del
 * anfitrión. `null` = la respuesta es la que dio la familia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitation_replies', function (Blueprint $table): void {
            $table->timestamp('host_rejoined_at')->nullable()->after('adopted_at');
        });
    }

    public function down(): void
    {
        Schema::table('invitation_replies', function (Blueprint $table): void {
            $table->dropColumn('host_rejoined_at');
        });
    }
};
