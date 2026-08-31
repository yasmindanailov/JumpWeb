<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cumpleaños MIXTO · el SELLO de condiciones de una reserva (`docs/specs/cumple-mixto.md` §21,
 * `DECISIONES #284` D1/D2 y `#288`).
 *
 * `[DECIDIDO owner, 2026-08-31]` «el cliente compra con unas condiciones y las mantenemos; ya las
 * siguientes reservas empiezan con las nuevas». Hasta hoy el veredicto de fiesta mixta —a qué pack
 * corresponde cada invitado por su edad y cuánto cuesta la diferencia— se derivaba del CATÁLOGO
 * VIVO en cada lectura, así que un cambio de tramos o de precios movía dinero de fiestas ya vendidas
 * en las dos direcciones (medido: bajar el corte de Kids creaba 32,00 € de la nada; ensancharlo
 * destruía 40,00 € comunicados; y el PRIMER cargo se calculaba con el catálogo del día en que el
 * cliente rellenaba el formulario, no con el del día en que compró — 10,00 € donde se pactaron 5,00).
 *
 * ▶ **El sello vive en la RESERVA, no en el producto.** Cada línea principal de un pack guarda al
 * nacer una copia de las condiciones de su familia por edad: qué packs la forman, qué tramo cubre
 * cada uno y cuánto costaba cada uno el día de la fiesta. El producto sigue teniendo un solo precio,
 * el de hoy; **el precio viejo solo existe dentro de las reservas que lo llevan**, y por eso no hace
 * falta —ni se construye— un histórico de precios.
 *
 * ⚠️ **Por qué una columna en `order_items` y no otra cosa** (§21.2, medido):
 *  - no `event_data`: es lo que contestó el CLIENTE y `RGPD-01` lo vacía al anonimizar; un sello que
 *    muriese con el olvido borraría las condiciones del PARQUE, no los datos del cliente;
 *  - no el `context` del ajuste del suplemento: ese ajuste solo existe cuando ya se ha escrito
 *    dinero, y el caso espejo es justo una reserva SIN línea a la que un cambio de tramos le crea un
 *    cargo — ahí no hay `context` donde haber sellado nada;
 *  - no una tabla 1:1: nada la consulta por sus columnas y añadiría un `JOIN` a las cinco superficies
 *    que ya cargan `ticketType`+`slot` por fila.
 *
 * ⚠️ **Sin PII**: solo ids, nombres de producto, tramos y precios de catálogo. `User::anonymize()` NO
 * la toca, y hay guarda de que la conserva.
 *
 * ⚠️ `nullable` y sin relleno (`DECISIONES #284` D3: **0 LIVE · 0 PRODUCCIÓN**, no hay nada que
 * rellenar al desplegar). Una línea sin sello no es «sin condiciones»: es SILENCIO —el veredicto no
 * aplica, lo escrito no se mueve— y la ficha del pedido lo dice. Las entradas y los complementos
 * tampoco lo llevan: el veredicto solo existe en un pack.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->json('age_family_seal')->nullable()->after('guest_form_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('age_family_seal');
        });
    }
};
