<?php

namespace App\Domain\Booking\Contracts;

/**
 * **Los días que un cliente tiene COBRADOS** (`docs/specs/encuestas.md` §4.7, T5): la mitad de Booking de «¿vino ese
 * día, y a qué?». La otra mitad —las visitas acreditadas en la puerta— es de Identity, que las junta en
 * `Platform\Contracts\VisitFacts`.
 *
 * Un día cobrado es una línea PRINCIPAL viva de un pedido PAGADO con la fecha de su franja: la regla de
 * `OrderItem::paidScheduledPrincipal()`, la misma del calendario y de la puerta. Fechas `Y-m-d`, hora de pared.
 */
interface PaidVisits
{
    public const KIND_PARTY = 'party';

    public const KIND_GROUP = 'group';

    public const KIND_ENTRY = 'entry';

    /**
     * Qué tiene cobrado ese día: `party` si alguna línea es un PACK, `group` si alguna tiene tramos de precio por
     * volumen (las excursiones y los grupos, `precio-por-tramo.md`), `entry` si no; `null` sin nada cobrado.
     */
    public function kindOn(int $userId, string $day): ?string;

    /** ¿Tiene algún día cobrado ANTES de `$day`? */
    public function anyBefore(int $userId, string $day): bool;

    /** El primer día cobrado de `($after, $until]`, o `null`. */
    public function firstBetween(int $userId, string $after, string $until): ?string;

    /**
     * El PRIMER día cobrado de cada cliente, por conjuntos (`#819`: la tasa de una encuesta «solo primera visita» sin una
     * consulta por visita). Quien no tiene ninguno no sale.
     *
     * @param  list<int>  $userIds
     * @return array<int, string> `[user_id => 'Y-m-d']`
     */
    public function firstPaidDays(array $userIds): array;
}
