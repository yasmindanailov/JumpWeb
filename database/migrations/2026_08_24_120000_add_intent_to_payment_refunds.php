<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Por qué se devolvió el dinero** (`DECISIONES #127(c)`).
 *
 * Reembolsar y cancelar son INDEPENDIENTES a propósito —el operador necesita esa flexibilidad para
 * entenderse con el cliente en las instalaciones—, y esa misma flexibilidad crea dos estados que el
 * desglose no puede narrar sin adivinar:
 *
 *  - **reembolsar SIN cancelar** cubre dos situaciones opuestas: una *compensación* (el cliente no
 *    debe nada) y el *canje en persona* (el cliente pagará en taquilla, así que SIGUE debiendo);
 *  - **cancelar SIN reembolsar** deja «Pendiente de devolverte X €», que **promete una devolución**
 *    si la política del parque es no devolver al cancelar.
 *
 * Sin este dato la frase del cliente solo puede ser vaga, y «que lo entienda claramente» era el
 * encargo. `payment_refunds` no tenía dónde guardarlo: su `failure_reason` es del gateway, no del
 * negocio.
 *
 * Aditiva y NULLABLE: las filas existentes quedan sin intención declarada, que es la verdad —nadie
 * se la preguntó al operador— y las superficies lo tratan como «no consta».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->string('intent', 32)->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('payment_refunds', function (Blueprint $table): void {
            $table->dropColumn('intent');
        });
    }
};
