<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LOS EVENTOS DEL LIBRO** (`docs/specs/analitica.md` §4.1 y §4.2; `DECISIONES #678`).
 *
 * Una fila por hecho: los que emite el cliente (`page_viewed`, `step_entered`…) y los que escribe el
 * servidor (`order_paid`, `order_declined`…). El nombre es una CLAVE del contrato
 * (`Platform\Services\Analytics\Contract`), nunca texto libre.
 *
 * ⚠️⚠️ **`event_id` lo genera el cliente (un ULID) y es ÚNICO por visitante**: la cola del emisor reintenta
 * ante un 429 o un corte de red, y `sendBeacon` no devuelve respuesta, así que sin idempotencia el mismo
 * lote entraría dos veces (spec §7.1, medicion-7). Se inserta con `insertOrIgnore`.
 *
 * ⚠️ **`received_at` es la verdad temporal**: sesiona y ordena. `occurred_at` viene del reloj del cliente y
 * se acota a `received_at ± 5 min` (faltas-7): un móvil con la hora mal no puede poner eventos en el futuro.
 *
 * ⚠️ `route` guarda el PATRÓN de la ruta con los parámetros enmascarados y la query filtrada por lista
 * blanca, nunca la URL: un token de invitación o la firma de un enlace viajan en la URL y no pueden
 * acabar en una tabla de 25 meses (seguridad-2). `props` va acotado a 2 KB en la ingesta.
 *
 * ⚠️ Sin claves foráneas: la poda borra eventos y después sesiones, y `user_id`/`order_id` son
 * referencias que sobreviven a su origen (un pedido no se borra; un usuario se anonimiza por tabla).
 * Los índices son los del embudo (`name`, `received_at`, `session_id`), de la poda (`received_at`) y de
 * la lectura por sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->char('event_id', 26);
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('visitor_id', 36)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name', 48);
            $table->string('route', 255)->nullable();
            $table->json('props')->nullable();
            $table->timestamp('occurred_at', 3);
            $table->timestamp('received_at', 3);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('refund_id')->nullable();

            $table->unique(['visitor_id', 'event_id']);
            $table->index('received_at');
            $table->index(['name', 'received_at', 'session_id']);
            $table->index('session_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
