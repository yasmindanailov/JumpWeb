<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **El icono de un producto, que hasta ahora decidía un BOOLEANO** (`DECISIONES #140`).

 * El marcador que acompaña al nombre de un producto —en la cesta, en el resumen y en «Mis pedidos»—
 * salía de `if ($isPack) tarta; else entrada;`. Con eso, **un catálogo entero se reparte en dos
 * dibujos**: la tirolina, la tarta y los calcetines son «no-pack», así que los tres son un ticket.
 *
 * ⚠️ **Guarda la CLAVE de un icono del sistema de diseño, no un fichero subido**
 * (decisión del owner, `#136` §4.6): un SVG subido es código ejecutable que habría que sanear, los
 * trazos y tamaños vendrían dispares y **la paridad cajón↔landing dejaría de poder comprobar el
 * dibujo** — hoy `SidebarIconParityTest` exige que cada geometría del cajón sea, byte a byte, la de
 * un componente del set.
 *
 * Aditiva y NULLABLE: `null` significa «el que le toque por su tipo», que es exactamente lo que
 * hacen hoy los productos existentes. Ninguna fila cambia de aspecto al migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->string('icon', 32)->nullable()->after('badge');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn('icon');
        });
    }
};
