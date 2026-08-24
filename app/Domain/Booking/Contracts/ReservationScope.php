<?php

namespace App\Domain\Booking\Contracts;

/**
 * **Los dos lados de UN MISMO predicado**: qué mitad del historial de reservas se pide
 * (`docs/specs/mis-reservas-por-reserva.md` §3.4).
 *
 * ⚠️⚠️ **Que sea un ámbito y no dos métodos es lo que impide perder una reserva.** «Mis reservas» y
 * «Historial» son dos pantallas, y la tentación es darle a cada una su consulta. Con dos consultas
 * independientes, un predicado que no sea exactamente el complemento del otro produce una reserva que
 * **no sale en ninguna de las dos** — y eso no lo nota nadie, porque una lista a la que le falta una
 * fila se lee perfectamente. Con un solo predicado y `where`/`whereNot`, la partición es exhaustiva y
 * disjunta **por construcción**, no por coincidencia.
 *
 * ▶ Lo vigila `MeReservationScopeTest` con la aserción que de verdad importa:
 * `upcoming.total + past.total === total de ítems principales del titular`, y ninguna id en los dos.
 *
 * ⚠️ **Y cada rama tiene que ser NULL-SAFE.** `whereNot()` sobre un predicado que compara una columna
 * NULLable devuelve *unknown*, no *true*: una reserva sin franja se caería de los dos lados. Por eso
 * toda comparación contra `slots.*` va guardada con su `whereNotNull` en
 * `CustomerReservationsReader::terminated()`.
 */
enum ReservationScope: string
{
    /**
     * Las que aún no han terminado: lo que el cliente todavía tiene por delante.
     *
     * Incluye las que **no tienen franja asignada** —no están terminadas y normalmente esperan algo
     * del cliente— y las de pedidos `pending` que siguen vivos, que es el camino por el que alguien
     * recupera un pedido a medio pagar con su retención de aforo intacta.
     */
    case UPCOMING = 'upcoming';

    /** Canceladas, disfrutadas, o de un pedido cancelado, reembolsado o caducado. */
    case PAST = 'past';
}
