<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T3a·3 de la analítica (`specs/analitica.md` §4.1 y §4.3, `#678`) — el régimen IDENTIFICADO en la cuenta.
 *
 * `analytics_opt_out`: la OPOSICIÓN del titular (art. 21) a que su navegación se vincule a su cuenta, desde
 * «Mi cuenta → Privacidad» o `PUT /me/analytics`. De fábrica `false`: el consentimiento es la categoría
 * `analytics` del banner, y esto es la puerta para retirarlo desde la cuenta —tan fácil como darlo—.
 * `first_attribution`: la primera fuente y campaña con la que esa persona llegó, escrita UNA vez al primer
 * enlace sesión↔cuenta y nunca después (inmutable). Las dos son PII derivada del régimen identificado:
 * `anonymize()` las devuelve a su valor neutro y el censo de `AnonymizeCoversEveryUserColumnTest` las declara.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('analytics_opt_out')->default(false)->after('marketing_opt_in');
            $table->json('first_attribution')->nullable()->after('analytics_opt_out');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['analytics_opt_out', 'first_attribution']);
        });
    }
};
