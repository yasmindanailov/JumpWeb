<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Platform\Services\DisplayTime;

/**
 * Fase 6 · el JUSTIFICANTE de un menor invitado — **quién viene autorizado en un pedido**
 * (`docs/specs/waiver-por-reserva.md` §4.10, §4.12), para las superficies que lo enseñan.
 *
 * ❗❗ **Son DOS formas y no una con un filtro, a propósito.** `[DECIDIDO owner]` §7·4: el
 * RESPONSABLE ve *«los nombres de los menores autorizados y quién falta»* y **nunca los datos de
 * contacto de los otros padres**. Si hubiera un solo método que devolviera todo, esa regla
 * dependería de que cada plantilla se acordara de no pintar dos campos — y las plantillas cambian.
 * Con dos formas, **la del responsable no puede filtrar lo que no lleva**.
 *
 * | | La ve | Lleva |
 * |---|---|---|
 * | {@see forOperator()} | panel y hoja de sala | menor, adulto que firmó, su relación, cuándo y el estado |
 * | {@see forResponsible()} | «Mis pedidos» del que reservó | **solo** el menor y el estado |
 *
 * ⚠️ Ninguna de las dos lleva el **correo ni el teléfono** del adulto. Están en la fila y en la
 * prueba, y ahí se quedan: la puerta no los necesita para dejar pasar, y el operador que de verdad
 * los necesite abre el registro probatorio, que tiene permiso propio y consulta auditada.
 *
 * ⚠️ **Vive en Identity y recibe un `int $orderId`**: `ReservationSlip` está en Booking, que **no
 * puede mirar a Identity**, así que la composición no puede vivir allí. La capa de entrega —que ve
 * los dos módulos— es quien las junta.
 */
final class GuardianRoster
{
    /**
     * Lo que ve el OPERADOR (panel y hoja de sala).
     *
     * @return list<array{minor: string, born_on: ?string, guardian: string, relationship: string, signed_on: ?string, waiver: ?string}>
     */
    public function forOperator(int $orderId): array
    {
        return $this->rows($orderId, static fn (GuardianAuthorization $a, ?WaiverStatus $status): array => [
            'minor' => $a->minorFullName(),
            'born_on' => $a->minor_born_on?->toDateString(),
            'guardian' => $a->guardianFullName(),
            'relationship' => (string) $a->guardian_relationship,
            'signed_on' => $status?->acceptedAt !== null ? DisplayTime::format($status->acceptedAt, 'd/m/Y') : null,
            'waiver' => $status?->minorState(),
        ]);
    }

    /**
     * Lo que ve el RESPONSABLE en su cuenta: **el menor y su estado, y nada más**.
     *
     * ⚠️ Es PII de menores de OTRAS familias en la pantalla de un cliente, y se acepta a propósito
     * (§4.10): es el responsable del grupo y sin ello no puede perseguir a quien falta. Lo que no se
     * acepta es que además vea a los adultos, y por eso esa columna no existe en esta forma.
     *
     * @return list<array{minor: string, waiver: ?string}>
     */
    public function forResponsible(int $orderId): array
    {
        return $this->rows($orderId, static fn (GuardianAuthorization $a, ?WaiverStatus $status): array => [
            'minor' => $a->minorFullName(),
            'waiver' => $status?->minorState(),
        ]);
    }

    /** Cuántos justificantes tiene el pedido. Sin denominador: ver {@see forResponsible()} y §4.10. */
    public function countFor(int $orderId): int
    {
        return GuardianAuthorization::query()->where('order_id', $orderId)->count();
    }

    /**
     * ⚠️ **DOS consultas, sean uno o cien justificantes**: las autorizaciones y sus firmas por lotes
     * (`WaiverStatus::forGuestMinors`). El presupuesto de la puerta lo vigila `GateProfileTest`, y la
     * ficha del pedido no puede permitirse una consulta por fila.
     *
     * @param  callable(GuardianAuthorization, ?WaiverStatus): array<string, mixed>  $shape
     * @return list<array<string, mixed>>
     */
    private function rows(int $orderId, callable $shape): array
    {
        $authorizations = GuardianAuthorization::query()
            ->where('order_id', $orderId)
            ->orderBy('id')
            ->get();
        if ($authorizations->isEmpty()) {
            return [];
        }

        $statuses = WaiverStatus::forGuestMinors($authorizations);

        return $authorizations
            ->map(fn (GuardianAuthorization $a): array => $shape($a, $statuses[(int) $a->getKey()] ?? null))
            ->values()
            ->all();
    }
}
