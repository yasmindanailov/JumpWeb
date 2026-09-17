<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LA INVITACIÓN DIGITAL de una reserva de cumpleaños** — el esqueleto
 * (`docs/specs/celebracion-e-invitacion.md` §4.4, T4·1; `DECISIONES #573`).
 *
 * El anfitrión comparte un enlace, cada padre contesta «sí» o «no» con el nombre de su hijo, y lo
 * que llega **se le PROPONE** al anfitrión en sus fichas.
 *
 * ⚠️⚠️ **Lo que contesta un padre NO escribe `guest_data`, y ésa es la decisión que ordena la feature
 * entera** (§3.1c). Si escribiera ahí: `submitGuestForm()` sustituye la lista ENTERA de fichas, así
 * que el anfitrión con la página abierta **borraría** lo que un padre acabara de escribir; y toda
 * escritura en `order_items` deja obsoleto su testigo (`updated_at`), que es lo que gobierna los
 * extras y el número de invitados. Además una edad escrita por un padre dispararía el suplemento de
 * fiesta mixta: **un tercero movería dinero**. Por eso las respuestas viven en su propia tabla y
 * pasan a `guest_data` por la puerta de siempre, cuando el anfitrión guarda.
 *
 * ⚠️ **Los dos interruptores nacen en `false`, así que esta migración no cambia la conducta de
 * ninguna instalación**: sin tocar el panel, ningún producto ofrece invitación y ningún complemento
 * se enseña en ella. Lo único que cambia hoy es que existe el sitio donde decirlo.
 *
 * Las políticas de borrado, que son el diseño y no un detalle (§7.2·R6 las encontró AUSENTES):
 *  - **`party_invitations.order_item_id` → CASCADE**: `PurgeCustomerData` borra los pedidos POR
 *    TABLA, así que sin cascada la purga de go-live se estrellaría contra estas filas.
 *  - **`invitation_replies` → CASCADE** por sus dos lados, por lo mismo.
 *  - **`guardian_authorizations.invitation_reply_id` → SET NULL**: las respuestas se podan a los 14
 *    días de la visita y **el justificante se conserva años**. Con RESTRICT la poda fallaría; con
 *    CASCADE la poda de una respuesta se llevaría una PRUEBA LEGAL por delante.
 *    ⚠️⚠️ Y por eso mismo **esa columna no puede entrar en el hash de la firma**: este repo ya lo
 *    pagó una vez —`waiver-por-reserva.md` §10, un `SET NULL` dentro del hash ponía
 *    `verifyHash()` en `false` sin que nadie tocara la fila—. Lo vigila la T4·4, que la escribe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_invitations', function (Blueprint $table) {
            $table->id();

            // UNA invitación por reserva. El único la impone, y por eso la regla de «nace sola»
            // puede ser un `firstOrCreate`: dos peticiones simultáneas chocan aquí y la segunda
            // relee en vez de duplicar.
            $table->unsignedBigInteger('order_item_id')->unique();
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();

            // El enlace. **Token opaco de 12 base62 (~71 bits)**, no una firma temporal de Laravel
            // (200 caracteres, imposible de teclear) ni `/i/lucia-8` (adivinable, y publica el
            // nombre y la edad de un menor en la URL). Se puede ROTAR, que es la palanca del
            // operador para anular un enlace ya repartido.
            $table->char('token', 12)->unique();

            $table->string('theme', 16);

            // El texto libre del anfitrión, que se publica bajo el dominio del parque: el saneo que
            // rechaza URLs y correos vive en el escritor (§4.5·12, `SEC-07`), no aquí.
            $table->string('honoree_name', 60);
            $table->unsignedTinyInteger('honoree_age')->nullable();
            $table->string('host_line', 80);
            // El teléfono NO se guarda: sale del de la cuenta, y esto solo dice si se enseña.
            $table->boolean('show_host_phone')->default(false);

            // El aviso de la víspera (T7) es idempotente por reserva y se marca aquí.
            $table->timestamp('reminded_at')->nullable();
            $table->unsignedSmallInteger('reminded_count')->default(0);

            $table->timestamps();
        });

        Schema::create('invitation_replies', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('party_invitation_id');
            $table->foreign('party_invitation_id')->references('id')->on('party_invitations')->cascadeOnDelete();

            // DENORMALIZADO a propósito: la puerta y la hoja de sala leen por LOTES de reservas del
            // día, y la supresión RGPD borra por los ids de las reservas del titular. Sin esta
            // columna, las dos tendrían que pasar por `party_invitations` para nada.
            $table->unsignedBigInteger('order_item_id');
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();

            $table->boolean('attending');

            // **Un solo campo de nombre** (D10), y se pide con apellidos para distinguir a dos niños
            // que se llamen igual. `child_key` es su forma normalizada (`PersonNameKey`).
            $table->string('child_name', 120);
            // 255 = `Platform\Services\PersonNameKey::MAX`, escrito a mano como en
            // `guardian_authorizations.minor_key`: una migración es infraestructura y no se ata a una
            // clase de dominio que mañana puede renombrarse. La paridad la vigila su test.
            $table->string('child_key', 255);

            // Las columnas del pack que el padre quiera dejar, saneadas con `sanitizeGuestData`.
            $table->json('data')->nullable();

            // «¿Vas tú con él?» — `with_adult` · `alone` · `unknown`.
            $table->string('companion', 12)->nullable();

            // Lo que el ANFITRIÓN ha hecho con la respuesta: la adoptó (y sobre qué ficha) o la
            // descartó. Mientras las dos estén vacías, la respuesta está «por repasar».
            $table->timestamp('adopted_at')->nullable();
            $table->string('adopted_name_key', 255)->nullable();
            $table->timestamp('dismissed_at')->nullable();

            $table->timestamps();

            // ⚠️⚠️ **NO es único, y es una decisión de PRIVACIDAD** (V6, §7.2·R1): rechazar un nombre
            // repetido con «ya nos habéis contestado por Hugo» le confirmaría a cualquiera con el
            // enlace que Hugo va a esa fiesta —bastaba con probar nombres—. Un repetido se acepta en
            // silencio, no ocupa plaza nueva y lo resuelve el anfitrión.
            $table->index(['order_item_id', 'child_key']);
        });

        Schema::table('guardian_authorizations', function (Blueprint $table) {
            // El vínculo firma ↔ respuesta, en el lado de Identity, que es quien lo escribe. Sirve
            // para que el firmador NO descuente plaza por una firma atada a un «sí»: esa plaza ya
            // tiene dueño, y sin la excepción el padre que dijo «sí» con la lista completa no podría
            // firmar (§4.5·7).
            $table->unsignedBigInteger('invitation_reply_id')->nullable()->after('order_item_id');
            $table->foreign('invitation_reply_id')->references('id')->on('invitation_replies')->nullOnDelete();
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            // D15. Junto a `guardian_authorization`, su hermano: los dos contestan a «¿qué papeles
            // pide este producto?». Incompatible con `required` —si el justificante hace falta
            // siempre, no hay nada que preguntar en la invitación—, con guarda en el formulario del
            // catálogo (T4·3).
            $table->boolean('guest_invitation')->default(false)->after('guardian_authorization');
        });

        Schema::table('product_addons', function (Blueprint $table) {
            // D12: **qué complemento es «el menú»** que la invitación enseña. Es una casilla del
            // ENGANCHE y no una deducción del grupo excluyente: deducirlo sería la trampa de los
            // calcetines (`#485`), presentación usada como identidad.
            // ⚠️⚠️ Una columna del pivote que no entre en las TRES listas blancas se cae **sin
            // avisar** al enganchar (`#413` §4.7·ter). Las tres se cierran en la T4·3.
            $table->boolean('show_in_invitation')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('product_addons', function (Blueprint $table) {
            $table->dropColumn('show_in_invitation');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('guest_invitation');
        });

        Schema::table('guardian_authorizations', function (Blueprint $table) {
            $table->dropForeign(['invitation_reply_id']);
            $table->dropColumn('invitation_reply_id');
        });

        Schema::dropIfExists('invitation_replies');
        Schema::dropIfExists('party_invitations');
    }
};
