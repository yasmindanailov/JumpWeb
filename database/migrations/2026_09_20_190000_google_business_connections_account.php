<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LA CUENTA DE LA QUE CUELGA LA FICHA** (T1·5,
 * `docs/specs/google-business-profile.md` §4.2·4 y §4.2·10; `DECISIONES #726`).
 *
 * ⚠️⚠️ **Sin esto la T2 no puede traer ni una reseña**, y no se ve leyendo el código: son **dos APIs
 * distintas que nombran la misma ficha de dos formas**, comprobado contra la documentación oficial el
 * 2026-09-20.
 *
 *   · `locations.list` (Business Information v1) devuelve
 *     *«Google identifier for this location in the form: `locations/{locationId}`»* — **sin cuenta**;
 *   · `reviews.list` (v4) exige
 *     `GET https://mybusiness.googleapis.com/v4/{parent=accounts/*``/locations/*}/reviews`.
 *
 * Así que el nombre que se guardó en la T1·3b **no sirve para pedir sus reseñas**. La cuenta se
 * conoce al listar —se recorren una a una— y se tira; aquí se conserva.
 *
 * ⚠️ Nullable y sin respaldo: la única fila que pudiera existir es de una conexión de desarrollo, y
 * se rellena sola al volver a elegir ficha. `null` significa «elegida antes de la T1·5», y el
 * comando de verificación lo dice en vez de fallar.
 *
 * **RGPD**: no es dato personal — es el identificador de una cuenta de empresa en Google.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_business_connections', function (Blueprint $table) {
            $table->string('account_name')->nullable()->after('location_name');
        });
    }

    public function down(): void
    {
        Schema::table('google_business_connections', function (Blueprint $table) {
            $table->dropColumn('account_name');
        });
    }
};
