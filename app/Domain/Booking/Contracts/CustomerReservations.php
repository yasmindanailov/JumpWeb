<?php

namespace App\Domain\Booking\Contracts;

/**
 * Las reservas de un cliente, para quien vive FUERA de Booking (Fase 2, paso 1:
 * `docs/specs/modulos-dominio.md` §5.1 — «reservas del cliente para Identity»).
 *
 * Extraído de las dos consultas que `App\Domain\Identity\Services\CustomerAccountContext` (Identity)
 * hacía a mano sobre `Order`/`OrderItem`/`TicketType`: mismo filtrado, mismo orden,
 * mismos datos. Sin superficie nueva.
 *
 * Recibe `int $userId` y no el modelo `User`: la consulta siempre fue por `user_id`, y
 * así Booking no importa un modelo de Identity — una flecha menos en el grafo, gratis
 * (`ModuleBoundariesTest`). Las relaciones Eloquent cruzadas (`Order::belongsTo(User)`)
 * siguen exentas como costura de BD documentada (§4 del spec).
 *
 * Implementación actual: `App\Support\CustomerReservationsReader` (bind en
 * `BookingServiceProvider`; viaja a `App\Domain\Booking` en el paso 6).
 */
interface CustomerReservations
{
    /**
     * Reservas FUTURAS del cliente, de la más próxima a la más lejana.
     *
     * Ítems principales (no complementos) de pedidos PAGADOS, no cancelados, con franja
     * y aún no finalizados en la práctica.
     *
     * @return list<UpcomingReservation>
     */
    public function upcomingFor(int $userId): array;

    /**
     * Post-forms de invitados (#217) pendientes: uno por cada pack pagado cuya franja aún
     * no ha finalizado. Un pack sin franja sigue pendiente.
     *
     * @return list<PendingGuestForm>
     */
    public function pendingGuestFormsFor(int $userId): array;
}
