<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Services\PersonNameKey;

/**
 * **QUÉ FICHA SE PIERDE CUANDO NO CABEN TODAS** — el borde `§7.1·5` de
 * `docs/specs/celebracion-e-invitacion.md`, que la T6 dejó abierto (`DECISIONES #718`).
 *
 * ## El problema
 *
 * Una lista de fichas se recorta **por el FINAL**: `TicketType::sanitizeGuestData()` conserva las
 * primeras `quantity`. Y el suelo que protege a quien ya confirmó cuenta **plazas, no posiciones**:
 * impide bajar por debajo de CUÁNTOS confirmaron, pero no dice CUÁLES. Con los confirmados en las
 * últimas fichas, una bajada perfectamente permitida —por encima del suelo— borraba justo a los
 * niños que el suelo prometía proteger, y dejaba en pie fichas vacías.
 *
 * ❗ Y no se quedaba ahí: `PartyInvitations::reconcileAdopted()` ve que la ficha de una respuesta
 * adoptada ya no existe y **la descarta**. Así que el «sí» dejaba de contar para el suelo y la
 * protección se deshacía sola, en silencio, sin que el anfitrión pidiera nada.
 *
 * ## La regla, en una línea
 *
 * Antes de recortar se **compacta**: primero los confirmados, luego el resto de fichas con datos, y
 * las vacías al final. Lo que se pierde son fichas vacías mientras las haya.
 *
 * ⚠️⚠️ **ESTABLE dentro de cada grupo.** El orden de las fichas lo eligió el anfitrión, y de la
 * posición cuelgan el régimen de cada una y la hoja de sala (`#571`): se compacta, no se baraja.
 *
 * ⚠️⚠️ **Existe como clase propia porque la costura tiene DOS puntas, y hasta que no se anduvo por
 * HTTP parecía tener una.** El post-form ajusta la cantidad **y después** guarda las fichas que
 * mandó el navegador, en el orden viejo (`specs/invitados-en-post-form.md` §4.7·2, y ese orden es
 * una propiedad, no un accidente). Arreglarlo solo en el ajuste dejaba el defecto vivo por el camino
 * real; arreglarlo en los dos sitios por separado habría sido escribir la misma regla dos veces —
 * exactamente lo que produjo los defectos de `§10.4.7·B`.
 *
 * ⚠️ Quién está protegido sale de **`PartyGuests`**, la MISMA fuente que alimenta el suelo
 * (`GuardianPlaces::committedGuests`), y no de un predicado nuevo.
 */
final class GuestCardOrder
{
    private const CONFIRMADA = 0;

    private const ESCRITA = 1;

    private const VACIA = 2;

    public function __construct(private PartyGuests $guests) {}

    /**
     * Las fichas reordenadas para que el recorte se lleve las vacías primero.
     *
     * El suelo garantiza que la cantidad nunca baja de cuántos confirmaron, así que el grupo de los
     * confirmados **siempre cabe entero**: ésa es la promesa que el borde rompía.
     *
     * @param  list<array<string, string>>|array<int, array<string, string>>  $rows
     * @return list<array<string, string>>
     */
    public function confirmedFirst(OrderItem $reservation, array $rows): array
    {
        $rows = array_values($rows);
        if ($rows === []) {
            return [];
        }

        // ❗ **La ficha de quien cumple está CLAVADA** (F3a de `fiesta-sistema-nuevo.md` §4.8, `#747`): es la primera y
        // no entra en el reparto. Si entrara, un invitado confirmado podría adelantarla y el recorte se la llevaría a
        // ella, que es la única plaza que nunca puede faltar. El suelo cuenta su plaza, así que siempre cabe.
        $cabeza = [];
        if ($reservation->hasHonoreeRow()) {
            $cabeza = [array_shift($rows)];
        }

        $nameKey = $reservation->ticketType?->guestNameFieldKey();

        // `key` es `adopted_name_key ?? child_key`: la clave de la FICHA si el anfitrión ya adoptó la
        // respuesta, y la del NIÑO si escribió el nombre él mismo. Las dos emparejan con la fila.
        $confirmed = $nameKey === null ? [] : array_column(
            $this->guests->partyGuestsIn([(int) $reservation->getKey()])[(int) $reservation->getKey()] ?? [],
            'key',
        );

        // ⚠️ Tres cubos y concatenar, en vez de ordenar: conserva el orden por CONSTRUCCIÓN, sin
        // depender de que la ordenación del lenguaje sea estable.
        $cubos = [self::CONFIRMADA => [], self::ESCRITA => [], self::VACIA => []];

        foreach ($rows as $row) {
            $cubos[$this->grupoDe($row, $nameKey, $confirmed)][] = $row;
        }

        return array_merge($cabeza, $cubos[self::CONFIRMADA], $cubos[self::ESCRITA], $cubos[self::VACIA]);
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $confirmed
     */
    private function grupoDe(array $row, ?string $nameKey, array $confirmed): int
    {
        $name = $nameKey === null ? '' : trim((string) ($row[$nameKey] ?? ''));

        if ($name !== '' && $confirmed !== [] && in_array(PersonNameKey::for($name), $confirmed, true)) {
            return self::CONFIRMADA;
        }

        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return self::ESCRITA;
            }
        }

        return self::VACIA;
    }
}
