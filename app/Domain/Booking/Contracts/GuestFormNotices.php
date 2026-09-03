<?php

namespace App\Domain\Booking\Contracts;

/**
 * Lo que el bloque de cuenta tiene que decir sobre los post-forms de este cliente
 * (`specs/complementos-post-reserva.md` §4.7·quinquies, D15).
 *
 * Son **dos hechos y no uno**, y mezclarlos costaría una mentira: `pending` es una DEUDA —«te faltan
 * los datos de los invitados»— y `extras` es una INVITACIÓN —«todavía puedes añadir algo»—. Con las
 * dos en la misma lista, el aviso de «tienes 2 formularios pendientes» contaría como pendiente uno
 * que está completo.
 *
 * ⚠️ Viajan juntas porque salen de **la misma consulta**: separarlas en dos métodos costaría un
 * segundo barrido de los pedidos del titular en **cada página con sesión**.
 */
final readonly class GuestFormNotices
{
    public function __construct(
        /** @var list<PendingGuestForm> los post-forms que todavía piden datos */
        public array $pending = [],
        /**
         * La reserva a la que invitar a añadir EXTRAS, o `null`. Una sola: el aviso nombra un
         * producto, y con varias la más cercana es la que antes cierra su plazo.
         */
        public ?PendingGuestForm $extras = null,
    ) {}
}
