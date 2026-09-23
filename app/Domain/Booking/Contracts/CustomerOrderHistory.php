<?php

namespace App\Domain\Booking\Contracts;

/**
 * **Lo que BOOKING guarda de un cliente, en forma portable** (RGPD art. 20), para quien vive fuera
 * de Booking — hoy, el export de «Mi cuenta» y `GET /api/v1/me/export`.
 *
 * Existe por la misma regla que {@see CustomerReservations}, que es la que ordena este módulo desde
 * Fase 2: *recibir* una entidad de otro módulo es costura de BD, pero **consultar sus datos exige
 * contrato**. El export vivía entero en `Http\Controllers\Account\AccountController`, que es capa de
 * ENTREGA y por eso quedaba exenta del grafo; al bajar la composición a `Identity\Services\
 * AccountPrivacy` esa exención desaparece, y sin este contrato Identity tendría que recorrer
 * `Order`, `OrderItem`, `Slot` y `TicketType` a mano.
 *
 * Recibe `int $userId` y no el modelo `User`, igual que `CustomerReservations`: la consulta siempre
 * fue por `user_id` y así Booking no importa un modelo de Identity — una flecha menos en el grafo,
 * gratis (`ModuleBoundariesTest`).
 *
 * ⚠️ **Devuelve el documento, no DTOs, y es deliberado.** Un export de portabilidad **es** un
 * documento: su forma la publica `openapi/v1.yaml` (`PersonalDataExport.orders`) y la valida
 * Spectator contra la respuesta REAL en `MePrivacyTest`. Envolverlo en DTOs para aplanarlos otra vez
 * al serializar pondría esa forma en dos sitios —el DTO y el esquema— y dos formas del mismo
 * documento divergen igual que divergieron las cuatro copias del rótulo de día (`DECISIONES
 * #120(j)`). Quien cambie esta forma cambia el contrato, y el contrato es quien manda (`#21`).
 *
 * Implementación actual: `App\Domain\Booking\Services\CustomerOrderHistoryReader` (bind en
 * `BookingServiceProvider`). Se nombra en prosa y no con `{@see}` —igual que `CustomerReservations`—
 * para que un contrato no acabe importando su implementación.
 */
interface CustomerOrderHistory
{
    /**
     * Los pedidos del cliente con el detalle que el art. 20 obliga a entregar: código, estado,
     * importes en céntimos, fechas, líneas con su franja y sus datos de evento, complementos
     * anidados, entradas emitidas y —desde `#678` (T1e)— por dónde llegó el pedido (`attribution`:
     * canal, fuente, medio y campaña; `null` si es anterior a la medición).
     *
     * ⚠️ **Incluye `event_data`** —nombre y ALERGIAS del homenajeado, art. 9— y por eso el consumidor
     * está obligado a servirlo con `no-store` (`RGPD-04`). Que `GET /me/orders` lo excluya a
     * propósito no es contradictorio: allí es una LISTA que se pinta sola en cada página; aquí es el
     * titular pidiendo expresamente **su** copia.
     *
     * @return list<array<string, mixed>>
     */
    public function exportFor(int $userId): array;
}
