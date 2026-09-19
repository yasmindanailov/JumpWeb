<?php

namespace App\Domain\Booking\Contracts;

/**
 * **LO QUE LE QUEDA POR HACER a una reserva antes de la visita**
 * (`specs/celebracion-e-invitacion.md` §4.9, T7·2a; `DECISIONES #714`).
 *
 * ❗❗ **Es un HECHO por cada cosa, no un texto ni una decisión.** Quien lo lee decide si manda un
 * correo, si pinta un aviso o si no hace nada: el mismo dato sirve para el aviso de la víspera, para
 * la ficha del panel y para la app. Si esto devolviera frases, el día que el panel quisiera enseñar
 * lo mismo habría que traducirlas de vuelta a cifras.
 *
 * ⚠️ **Las cuatro cifras se miden por separado y NINGUNA anula a otra**: una reserva puede tener las
 * fichas completas y deber dinero, o estar pagada y sin una sola ficha. Un único «¿falta algo?» no
 * podría decir QUÉ falta, que es lo que hace útil el aviso.
 */
final readonly class PendingWork
{
    public function __construct(
        /** Fichas de invitado ya completas. */
        public int $guestsDone,
        /** Fichas que el producto pide en total. `0` = este producto no pide fichas. */
        public int $guestsTotal,
        /**
         * Respuestas de la invitación **por repasar** (las dos clases: un «no» también hay que verlo).
         * `0` si el producto no ofrece invitación.
         */
        public int $repliesToReview,
        /**
         * Plazas de menor **sin resolver**: ni asignadas a un menor a cargo, ni con justificante
         * firmado, ni con un «sí» de la invitación detrás. `0` si el producto no pide justificante.
         */
        public int $minorsUnresolved,
        /**
         * Lo que queda por pagar **en el parque**, en céntimos y siempre ≥ 0.
         *
         * ⚠️ Solo el saldo `pay_at_park` del libro (`specs/desglose-libro.md`): una devolución
         * pendiente **no es trabajo del cliente** y no tiene sitio en un aviso que le pide cosas.
         */
        public int $balanceAtParkCents,
    ) {}

    /** Fichas que faltan por completar. */
    public function guestsMissing(): int
    {
        return max(0, $this->guestsTotal - $this->guestsDone);
    }

    /**
     * ¿Queda algo por hacer?
     *
     * ⚠️ Es la condición de envío del aviso de la víspera (§4.9): **si no queda nada, no se manda**.
     * Un correo que dice «no tienes que hacer nada» es un correo que enseña a ignorar los correos.
     */
    public function any(): bool
    {
        return $this->guestsMissing() > 0
            || $this->repliesToReview > 0
            || $this->minorsUnresolved > 0
            || $this->balanceAtParkCents > 0;
    }
}
