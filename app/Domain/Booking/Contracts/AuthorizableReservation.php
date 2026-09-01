<?php

namespace App\Domain\Booking\Contracts;

use Carbon\CarbonImmutable;

/**
 * Una RESERVA vista por el subsistema del JUSTIFICANTE de un menor invitado
 * ({@see AuthorizableReservations}; `docs/specs/waiver-por-reserva.md` §13).
 *
 * ❗❗ **Sustituye a `AuthorizableOrder`, y el cambio es de SUJETO, no de forma.** Aquélla describía un
 * PEDIDO, y un pedido puede tener dos visitas en dos días distintos: la hoja que firmaba el padre
 * decía *«Días de la visita: 03/09/2026 · 07/09/2026»* y no decía a qué iba su hijo. *Un padre no
 * autoriza un pedido: autoriza que su hijo entre a una visita concreta.*
 *
 * ⚠️ **Cada campo de aquí acaba delante de un DESCONOCIDO**, así que solo entra lo que hace falta para
 * que sepa QUÉ está autorizando y para que el dominio pueda decidir si cabe. Ni importes, ni nombres
 * de nadie, ni los datos de las otras reservas del pedido.
 */
final readonly class AuthorizableReservation
{
    /**
     * @param  int  $reservationId  la línea (`order_items.id`) — el sujeto de la autorización
     * @param  string  $orderCode  la referencia que el cliente ya conoce («R-AB12CD»): sin ella el
     *                             justificante no dice a qué compra pertenece
     * @param  string  $productName  a qué va el menor («Excursión 2 h», «Jump · 1 hora»). ⚠️ Es lo
     *                               que faltaba: hasta `#343` la hoja no decía el tipo de reserva
     * @param  ?string  $date  el día (`Y-m-d`), o `null` si la línea todavía no tiene franja
     * @param  ?string  $startTime  hora de inicio (`H:i:s`) y {@see $endTime} de fin: un padre quiere
     *                              saber a qué hora deja y recoge a su hijo
     * @param  int  $quantity  unidades de ESTA línea. Es el techo bruto de autorizaciones; lo que
     *                         queda libre lo decide Identity restando los menores a cargo ya
     *                         asignados, que ella sí conoce
     * @param  bool  $isPaid  ⚠️ un pedido con SEÑAL **también** es `paid` —medido: 30,00 € cobrados de
     *                        88,00 €—, así que exigirlo NO deja fuera a las excursiones
     * @param  bool  $visitFinished  esta visita ya terminó. `false` cuando la línea NO tiene franja:
     *                               no ha pasado nada que cerrar
     * @param  CarbonImmutable  $linkExpiresAt  la última fecha del PEDIDO + 14 días, la MISMA fuente
     *                                          que el enlace del post-form (`RGPD-03`). ⚠️ Sigue
     *                                          siendo del pedido a propósito: dos enlaces de la misma
     *                                          compra con caducidades distintas serían dos reglas
     */
    public function __construct(
        public int $reservationId,
        public int $orderId,
        public string $orderCode,
        public string $productName,
        public ?string $date,
        public ?string $startTime,
        public ?string $endTime,
        public int $quantity,
        public bool $isPaid,
        public bool $visitFinished,
        public CarbonImmutable $linkExpiresAt,
    ) {}
}
