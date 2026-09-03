<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La VERSIÓN del enlace del post-form (`specs/complementos-post-reserva.md` §4.6.bis, `#413` D14).
 *
 * **El problema que cierra**: el enlace del post-form es una URL firmada (HMAC sobre `APP_KEY`) que
 * viaja por correo, abre sin sesión y vive hasta la fecha del evento + 14 días. `RGPD-06` afirma que
 * invalidar el acceso de un titular tiene **un solo sitio** (`User::revokeAllAccess()`) y alcanza a
 * tres credenciales — y ésta **no está, ni puede estar**: no hay fila que borrar. Con los
 * complementos de venta posterior ese enlace pasa además a **escribir dinero de importe elegido**,
 * así que el parque necesita poder cortarlo. Las únicas palancas de hoy son cancelar la reserva o
 * anonimizar al titular, y `RGPD-01` bloquea lo segundo mientras haya una reserva por celebrar: o
 * sea, justo mientras el enlace importa.
 *
 * **Cómo funciona**: la versión viaja como parámetro `v` DENTRO de la URL firmada, y la autorización
 * la compara con la de la fila. Subirla invalida en el acto todos los enlaces emitidos antes, sin
 * tocar `APP_KEY` ni la caducidad.
 *
 * ⚠️ **Default 0 a propósito, y con ello NINGÚN enlace ya emitido se rompe al desplegar**: los que
 * viajan sin `v` se leen como versión 0 y siguen coincidiendo. La versión solo empieza a separar
 * cuando alguien rota.
 *
 * ⚠️ La versión es del ÍTEM y no del pedido: desde `#217` hay **un post-form por reserva**, y rotar
 * el enlace de un cumpleaños no puede tumbar el del otro cumpleaños del mismo pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order_items', 'guest_form_link_version')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('guest_form_link_version')
                ->default(0)
                ->after('guest_form_completed_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_items', 'guest_form_link_version')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('guest_form_link_version');
        });
    }
};
