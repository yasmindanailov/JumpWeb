<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El MOTIVO de un reembolso (T4 del libro, `specs/desglose-libro.md` §6.4, `DECISIONES #316`
 * `[DECIDIDO owner]`: «el motivo manda»).
 *
 * `payment_refunds.intent` (`#127(c)`) dice la CLASE del reembolso —`value_returned` ·
 * `compensation` · `paid_in_person`—; esta columna guarda el TEXTO que el operador escribe: con
 * `compensation` es obligatorio (una cortesía es una decisión y se firma con su porqué) y con el
 * resto, opcional. El dominio lo copia al `context.note` de la fila `courtesy` que la compensación
 * escribe, y el panel lo pinta bajo la línea «Descuento por cortesía». Es INTERNO (D-T4·1): no
 * viaja por la API ni llega al cajón ni a los correos.
 *
 * ⚠️ La spec §6.4 daba esta columna por EXISTENTE («desde la migración fundacional, motivo libre del
 * operador»): no existía — lo que hay es `failure_reason`, que es del GATEWAY. Medido al ejecutar la
 * tanda (la suite entera cayó con «no column named reason»); la corrección va delante del texto.
 *
 * Nullable: las filas anteriores no tienen motivo y no se inventa (200 caracteres, el tope del modal
 * y de `Order::REFUND_NOTE_MAX_LENGTH`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->string('reason', 200)->nullable()->after('intent');
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropColumn('reason');
        });
    }
};
