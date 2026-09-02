<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Un consentimiento se puede RETIRAR, y eso tiene que dejar constancia** (art. 7.3 del RGPD;
 * `docs/specs/auth-con-google.md` §9, tanda T3).
 *
 * ⚠️⚠️ **Cierra un incumplimiento VIVO, y no es de las cuentas de Google**: hasta hoy el
 * consentimiento de marketing se daba con un clic en el alta y **no se podía retirar por ninguna
 * superficie** —ninguna ruta actualizaba `marketing_opt_in`— y, aunque se hubiera podido, `consents`
 * no tenía dónde anotarlo. El art. 7.3 exige que retirarlo sea **tan fácil como darlo**, y el art.
 * 5.2 que quede prueba de las dos cosas.
 *
 * ▶ **Por qué una columna y no borrar la fila**: la fila ES la prueba de que en su día se aceptó, y
 * el art. 7.1 obliga a poder demostrarlo. Borrarla dejaría al parque sin poder justificar los envíos
 * que hizo mientras el consentimiento estaba vivo. Lo que la retirada añade es **cuándo dejó de
 * valer**, no un borrado.
 *
 * ⚠️ Y **la IP de la retirada va aparte de la del alta**: son dos actos distintos, en dos momentos y
 * puede que desde dos sitios. Reutilizar `ip` habría pisado la prueba de la aceptación con la de su
 * cancelación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('accepted_at');
            $table->string('revoked_ip', 45)->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->dropColumn(['revoked_at', 'revoked_ip']);
        });
    }
};
