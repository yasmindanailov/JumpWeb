<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Entrar y registrarse con Google** — la identidad EXTERNA de una cuenta
 * (`docs/specs/auth-con-google.md` §6.2, tanda T1).
 *
 * Una fila = «esta cuenta de JumpWeb es la misma persona que este identificador de este proveedor».
 * No es una credencial: no se puede iniciar sesión con lo que hay aquí guardado. Es el **vínculo**
 * que el retorno de Google resuelve, y el rastro de cuándo y por dónde se creó.
 *
 * ▶ **Por qué una tabla y no dos columnas en `users`** (`[DECIDIDO owner]`: «como lo valores más
 * profesional y robusto»): guarda el RASTRO —con qué correo se vinculó y por qué puerta— que hace
 * falta el día que alguien reclame, y deja la puerta abierta a un segundo proveedor (Apple) sin
 * migrar `users` otra vez. Dos columnas darían la mitad del dato y ninguna de las dos cosas.
 *
 * Las claves, cada una con lo que impide:
 *  - **`UNIQUE(provider, provider_id)`**: un `sub` de Google apunta como mucho a UNA cuenta. Es lo
 *    que hace que el vínculo sea una identidad y no una etiqueta.
 *  - **`UNIQUE(user_id, provider)`**: una cuenta tiene como mucho UNA identidad por proveedor. No es
 *    seguridad —para vincular hay que demostrar el buzón, así que quien lo hace ya es el titular—:
 *    es que dos llaves de Google sobre la misma cuenta dejan «desvincular» (T3) sin significado y el
 *    rastro sin sujeto. El segundo intento se rechaza con su motivo, nunca en silencio.
 *
 * ⚠️⚠️ **`cascadeOnDelete` NO se dispara nunca en la supresión RGPD** (§11): `User::anonymize()`
 * **no borra la fila de `users`** —la FK de los pedidos es RESTRICT y la factura tiene que seguir
 * vinculada—, así que la purga del vínculo va escrita a mano allí. Y va por BORRADO, no por
 * redacción: una fila redactada dejaría el `sub` ocupado en el `UNIQUE` de arriba y **esa persona no
 * podría volver a registrarse con su Google nunca más**. El cascade sí sirve para el borrado de
 * verdad, que es el de `PurgeCustomerData` al preparar una instalación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // El proveedor y su identificador estable. `provider_id` es el `sub` de OpenID Connect:
            // NUNCA el correo (§6.1) — el correo de una cuenta de Google puede cambiar y el `sub` no.
            // 191 caracteres es el largo clásico seguro para un índice utf8mb4; el `sub` de Google es
            // un entero de 21 dígitos en decimal, así que sobra sitio de aquí a Apple.
            $table->string('provider', 32);
            $table->string('provider_id', 191);

            // El correo CON EL QUE SE VINCULÓ, como copia probatoria: si mañana la persona cambia su
            // correo en Google o aquí, esta columna sigue diciendo con cuál se estableció el vínculo.
            // Nadie lo usa para buscar: buscar es cosa del `sub`.
            $table->string('email_at_link', 255)->nullable();

            // Por qué puerta entró el vínculo: `signup` (el alta que nace de Google), `login` (la
            // vinculación automática de §5.2) o `account` (el titular lo pide desde su cuenta, T3).
            $table->string('linked_via', 16);

            // Sin `updated_at` a propósito: la fila no se edita. Desvincular es borrarla y volver a
            // vincular es crearla — y entonces `linked_at` dice la verdad otra vez.
            $table->timestamp('linked_at');

            $table->unique(['provider', 'provider_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identities');
    }
};
