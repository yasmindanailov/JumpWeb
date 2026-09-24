<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **La prueba del consentimiento gana el VISITANTE** (`docs/specs/analitica.md` §4.3, T3b·2).
 *
 * Hasta hoy una fila de `cookie_consent_logs` decía quién consintió solo si tenía cuenta (`user_id`); el
 * visitante anónimo era una IP y un user agent. Con `visitor_id` (la cookie del libro de eventos, un ULID
 * opaco) el servidor puede RELEER el consentimiento VIVO de un visitante —su última decisión— antes de
 * comunicar una compra a Meta o TikTok desde la cola: la decisión de esta noche manda sobre la foto que se
 * selló en el pedido esta tarde.
 *
 * Nullable: quien decide sin cookie del visitante (un cliente de API sin cabecera) deja la fila sin él, y
 * entonces no hay conversión de servidor, que es lo correcto. Se poda con la fila (24 meses).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cookie_consent_logs', function (Blueprint $table): void {
            $table->string('visitor_id', 36)->nullable()->after('user_id');
            $table->index(['visitor_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('cookie_consent_logs', function (Blueprint $table): void {
            $table->dropIndex(['visitor_id', 'accepted_at']);
            $table->dropColumn('visitor_id');
        });
    }
};
