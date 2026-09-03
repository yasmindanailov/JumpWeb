<?php

namespace App\Domain\Booking\Contracts;

use App\Domain\Booking\Models\OrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
 * Implementación actual: `App\Domain\Booking\Services\CustomerReservationsReader` (bind en
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
     * ¿Tiene el cliente alguna reserva POR CELEBRAR? — la puerta de la supresión (T5 · D8,
     * `cumple-mixto.md` §25.4): con una, la cuenta no se puede anonimizar.
     *
     * **El criterio es EXACTAMENTE el de {@see upcomingFor}** (pagada + principal activo + franja
     * cuyo fin no ha pasado, corte fino en PHP): la puerta y la pantalla de «próximas» del titular
     * cuentan la misma historia. NO es el complemento de `terminated()` (el predicado de las dos
     * pantallas del historial), y las tres divergencias son a propósito:
     *  · una línea pagada SIN franja jamás «termina» (`isFinishedInPractice()` es `false` sin
     *    slot) → bloquearía la supresión PARA SIEMPRE, un callejón del art. 17 — y sin franja no
     *    hay fiesta que reconciliar, así que la consecuencia de D8 («ninguna reserva anonimizada
     *    se reconcilia») se sostiene igual;
     *  · una cesta `PENDING` sin expirar no es un compromiso del parque: caduca sola en minutos;
     *  · el corte de fecha fino (colchón de zona horaria + `isFinishedInPractice()`) ya está
     *    hecho ahí.
     */
    public function hasUpcomingFor(int $userId): bool;

    /**
     * Lo que hay que decirle al titular sobre sus post-forms: los PENDIENTES —uno por cada pack
     * pagado cuya franja aún no ha finalizado; un pack sin franja sigue pendiente— y, aparte, la
     * reserva a la que invitarle a añadir EXTRAS (D15 de `specs/complementos-post-reserva.md`).
     *
     * ⚠️ **Los dos en una llamada porque salen de una consulta.** Y separados en el resultado porque
     * son cosas distintas: una deuda y una invitación ({@see GuestFormNotices}).
     */
    public function guestFormNoticesFor(int $userId): GuestFormNotices;

    /**
     * **Una PÁGINA del historial de reservas del cliente**, ya ordenada para presentación
     * (`docs/specs/mis-reservas-por-reserva.md` §4.1).
     *
     * Ítems principales (no complementos) de pedidos del titular, **en cualquier estado**: incluir
     * los `pending` es lo que permite recuperar un pedido a medio pagar con su retención viva, igual
     * que hace `GET /me/orders` por la misma razón escrita en `openapi/v1.yaml`.
     *
     * ⚠️⚠️ **Los dos ámbitos son los dos lados de UN predicado, y su unión es el total.** Ver
     * {@see ReservationScope}: es lo que impide que una reserva se caiga entre las dos pantallas que
     * lo consumen. No lo partas en `upcomingPageFor()` y `pastPageFor()`.
     *
     * **Orden**, y es del dominio porque depende de qué significa «próxima»:
     *  · `UPCOMING` → las que no tienen franja primero, luego por fecha y hora ASCENDENTE;
     *  · `PAST` → por fecha y hora DESCENDENTE (lo más reciente arriba), sin franja al final.
     * Con un desempate por `id` en los dos, sin el cual dos reservas del mismo instante podrían
     * intercambiarse entre páginas y hacer que una desaparezca al paginar.
     *
     * ⚠️ **Devuelve el paginador de Eloquent y no DTOs**, al revés que {@see upcomingFor}. Quien lo
     * consume necesita el ítem entero —`OrderItemResource` ya sabe serializarlo con sus importes, su
     * post-form y sus complementos— y componer un DTO de veinte campos sería copiar ese Resource a
     * mano. `upcomingFor()` publica DTO porque publica cuatro campos.
     *
     * @param  int  $perPage  tamaño de página; quien llama ya lo ha acotado
     * @return LengthAwarePaginator<int, OrderItem>
     */
    public function pageFor(int $userId, ReservationScope $scope, int $perPage, int $page): LengthAwarePaginator;
}
