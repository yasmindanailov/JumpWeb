<?php

namespace App\Domain\Platform\Contracts;

/**
 * **Lo grueso de una visita de un cliente** (`docs/specs/encuestas.md` §4.7, T5; `DECISIONES #754`): lo que la
 * respuesta ANÓNIMA de una encuesta guarda de él sin guardarle a él —si era su primera vez y qué vino a hacer— y lo
 * que el sello pregunta después: si VOLVIÓ.
 *
 * Es un contrato de Platform por la razón de `ConsentLedger`: quien pregunta (las encuestas) vive en Platform, que no
 * ve a nadie (`ModuleBoundariesTest`); las visitas acreditadas son de Identity (`customer_visits`) y las reservas
 * cobradas de Booking. Lo implementa Identity y la atadura vive en el composition root.
 *
 * «Visita» es la misma en los tres métodos: una **visita acreditada en la puerta** o una **reserva cobrada** (línea
 * principal viva de un pedido pagado, `OrderItem::paidScheduledPrincipal()`) con la fecha de su franja. Fechas `Y-m-d`
 * en días del parque.
 */
interface VisitFacts
{
    /** ¿Es su primera vez? — ninguna visita ANTES de `$day`. */
    public function isFirstVisit(int $userId, string $day): bool;

    /**
     * Qué vino a hacer ese día, por lo que tenía cobrado: `party` (un pack) › `group` (un producto con tramos por
     * volumen) › `entry` › `other` (nada cobrado para ese día: vino con otro, pagó en taquilla…). Los valores son los
     * de `SurveyResponse::KINDS`.
     */
    public function kindOn(int $userId, string $day): string;

    /** El PRIMER día de `($after, $until]` con una visita, o `null` si no volvió en ese tramo. */
    public function firstReturn(int $userId, string $after, string $until): ?string;

    /**
     * El PRIMER día de cada cliente —el menor entre su primera visita acreditada y su primer día cobrado—, por conjuntos
     * (`#819`). Una visita del día `D` es su primera visita ({@see isFirstVisit()}) si y solo si `D` es ese día.
     *
     * @param  list<int>  $userIds
     * @return array<int, string> `[user_id => 'Y-m-d']`; quien no tiene ninguno no sale
     */
    public function firstVisitDays(array $userIds): array;
}
