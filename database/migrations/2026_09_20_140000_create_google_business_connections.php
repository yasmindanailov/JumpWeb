<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LA CONEXIÓN CON LA FICHA DE GOOGLE** (T1·1,
 * `docs/specs/google-business-profile.md` §4.2; `DECISIONES #524`, `#719`).
 *
 * El parque autoriza por OAuth al proyecto central de JumpSystem y lo que queda de ese gesto es **un
 * token de refresco**: la llave con la que la sincronización diaria pide, sin nadie delante, una llave
 * temporal para leer sus reseñas.
 *
 * ⚠️⚠️ **Tabla propia, y NO `settings`** (§4.2·5). No es una preferencia de orden: `Setting::value()`
 * lee la tabla ENTERA y la memoriza por proceso, así que guardar aquí el token lo pasearía por la
 * memoria de todas las peticiones que consultan cualquier ajuste —la CSP, el color de marca, los flags
 * de mantenimiento—, y lo dejaría además en un `pluck` que cualquier volcado imprime. Vive aparte y
 * **cifrado** (cast `encrypted`, el precedente es `CustomerCard::$token`).
 *
 * ⚠️ **Fila única, y como invariante de BD, no como costumbre**: una instalación es un parque y un
 * parque es una ficha (§2·10). La columna `singleton` con índice único hace que una segunda conexión
 * sea imposible en vez de improbable — sin ella, dos filas serían un estado que nadie sabría leer y
 * que aparecería el día que dos pestañas del panel conectaran a la vez.
 *
 * ⚠️⚠️ **`token_fingerprint` es la mitad de comparar-y-escribir** (§4.2·7). Cuando una pasada se topa
 * con `invalid_grant`, marcar «caducada» a pelo pisaría una reconexión que el admin acabe de hacer
 * desde el panel: el worker llevaba el token viejo y no lo sabe. La huella del token QUE USÓ se compara
 * con la guardada, y solo si coinciden se marca. Es un `sha256`, no el token.
 *
 * ⚠️ **`connected_by_user_id` es una FK del esquema, SIN relación de Eloquent** (§4.0). Platform no
 * puede depender de ningún módulo (`ModuleBoundariesTest::ALLOWED` dice `'Platform' => []`, y ni
 * siquiera el kernel compartido lo exime: `AuditLog` necesita su entrada explícita en `SEAM` para
 * mirar a `User`). Quién conectó lo resuelve la capa de ENTREGA, que sí puede usar la superficie
 * pública de cualquier módulo. Así la razón por la que la conexión vive en Platform —«sin
 * dependencias»— sigue siendo cierta en vez de convertirse en una excepción más.
 *
 * **RGPD**: aquí no hay dato de cliente. El único dato personal es de un EMPLEADO (quién conectó), y
 * es la misma naturaleza que `audit_logs.user_id`: rastro de quién hizo una acción de administración.
 * Las reseñas —que sí traen datos de terceros— son de la T2 y viven en sus propias tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_business_connections', function (Blueprint $table) {
            $table->id();

            // El candado de «una sola conexión». Siempre `true`; el único índice que importa es el
            // UNIQUE. Se escribe como columna y no como `id = 1` porque una convención no impide un
            // segundo INSERT y esto sí.
            $table->boolean('singleton')->default(true);
            $table->unique('singleton');

            // El estado de §4.2·7 que SE GUARDA. «Sin configurar» y «lista para conectar» no están
            // aquí a propósito: se derivan de si hay credenciales y de si hay token, y guardarlas
            // crearía un segundo dueño de una verdad que ya se puede calcular.
            $table->string('status', 32)->default('ready_to_connect');
            $table->timestamp('status_changed_at')->nullable();

            // El token de refresco, CIFRADO por el cast del modelo. `text` porque el ciframiento de
            // Laravel infla el valor bastante por encima de lo que ocupa el token de Google.
            $table->text('refresh_token')->nullable();
            // `sha256` del token guardado. No es un secreto —no se puede volver atrás— y es lo que
            // permite que un worker con el token viejo no pise una reconexión recién hecha.
            $table->char('token_fingerprint', 64)->nullable();

            // La ficha elegida (§4.2·4). `location_name` es el nombre de RECURSO de Google
            // (`accounts/…/locations/…`), que es por donde se la pide; el resto viene de su
            // `metadata` y lo usa la portada para enlazar.
            $table->string('location_name')->nullable();
            $table->string('location_title')->nullable();
            $table->string('place_id')->nullable();
            $table->string('maps_uri')->nullable();
            $table->string('new_review_uri')->nullable();

            // Quién conectó y cuándo (§4.2·9). FK del esquema; la relación NO existe en el modelo.
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_business_connections');
    }
};
