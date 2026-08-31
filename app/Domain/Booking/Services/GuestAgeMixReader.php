<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Carbon;

/**
 * Deriva el veredicto de mezcla de edades de una reserva (`docs/specs/cumple-mixto.md` §9).
 *
 * **El problema que resuelve, dicho por el owner**: «el sistema no tiene conexión entre un cumple
 * KIDS y uno JUMP». Cierto y medido — `ticket_types` no tenía ninguna columna que agrupase
 * productos. `[DECIDIDO owner, 2026-08-29]` la conexión es **familia + tramo de edad**: cada
 * producto declara el tramo que cubre y una familia, y los que comparten familia son alternativos
 * entre sí. Este lector recorre las edades declaradas en el post-form y le busca a cada invitado el
 * producto de la familia que le toca; los que no caen en el reservado son la mezcla.
 *
 * ▶ **Vale para dos regímenes o para cinco**, y no nombra a ningún cliente: la familia es un slug
 * que pone la instalación. Un producto sin familia lo apaga entero.
 *
 * **Solo lectura.** No toma locks, no escribe y no pertenece al `CRITICAL_RE` del `pre-push`: la
 * decisión de convertir esto en dinero es una acción aparte del operador (spec §9·5, `[DECIDIDO
 * owner]`), no una consecuencia de leer.
 *
 * ⚠️ **La aritmética del suplemento sale del CATÁLOGO, nunca de un número escrito aquí** (spec
 * §2·2): es `precio(destino, día) − precio(reservado, día)`, los dos resueltos por `RateResolver`
 * para la fecha de la franja — la misma fuente que usa la compra, así que el suplemento no puede
 * divergir de lo que cuesta el producto ese día.
 *
 * ⚠️ **Con suelo en 0: bajar de régimen no abona nada.** Un invitado que corresponde a un producto
 * MÁS BARATO cuenta para la etiqueta —la fiesta es mixta de verdad— pero su diferencia es 0. El
 * encargo pide cobrar la diferencia al que sube; devolver dinero al que baja sería un movimiento de
 * caja hacia el cliente que nadie ha pedido ni decidido (`INVARIANTES` §1).
 */
class GuestAgeMixReader
{
    /**
     * Productos de una familia, ya ordenados. Memoiza por familia: la ficha de un pedido y las
     * listas del panel piden el veredicto de varias reservas seguidas, y todas comparten catálogo.
     *
     * @var array<string, list<TicketType>>
     */
    private array $families = [];

    /** Precios ya resueltos, por `«{typeId}|{fecha}»`. @var array<string, int|null> */
    private array $prices = [];

    public function __construct(private RateResolver $rates) {}

    public function for(OrderItem $item): GuestAgeMix
    {
        $walk = $this->walk($item);
        if ($walk === null) {
            return GuestAgeMix::notApplicable();
        }

        $withoutAge = 0;
        $outOfRange = 0;
        /** @var array<int, int> $counts */
        $counts = [];

        foreach ($walk['rows'] as $row) {
            if ($row['state'] === self::ROW_NO_AGE) {
                $withoutAge++;

                continue;
            }
            if ($row['state'] === self::ROW_OUT_OF_RANGE) {
                $outOfRange++;

                continue;
            }
            $target = $row['target'];
            if ((int) $target->id === (int) $walk['type']->id) {
                continue; // le toca el producto que ya tiene: nada que hacer.
            }
            $counts[(int) $target->id] = ($counts[(int) $target->id] ?? 0) + 1;
        }

        return $this->verdict(
            $walk['type'], $walk['family'], $counts, $walk['date'],
            count($walk['rows']), $withoutAge, $outOfRange,
        );
    }

    /**
     * El régimen que le toca a CADA invitado, por posición de su ficha — para el post-form, que
     * pinta el rótulo dentro del recuadro del niño (`[owner, 2026-08-29]`, §15).
     *
     * Sale del MISMO recorrido que el veredicto agregado: si esto tuviera su propia copia de la
     * regla, el rótulo de una ficha podría decir «Kids» mientras el total dice otra cosa.
     *
     * @return array<int, array{state:string, name:?string, own:bool}> vacío si el pack no participa
     */
    public function guestRegimes(OrderItem $item): array
    {
        $walk = $this->walk($item);
        if ($walk === null) {
            return [];
        }

        $out = [];
        foreach ($walk['rows'] as $i => $row) {
            $target = $row['target'];
            $out[$i] = [
                'state' => $row['state'],
                'name' => $target?->tr('name'),
                'own' => $target !== null && (int) $target->id === (int) $walk['type']->id,
            ];
        }

        return $out;
    }

    /** Estados de una ficha: su edad cae en un pack, no la ha declarado, o no la cubre ninguno. */
    public const ROW_OK = 'ok';

    public const ROW_NO_AGE = 'no_age';

    public const ROW_OUT_OF_RANGE = 'out_of_range';

    /**
     * El recorrido ÚNICO de las fichas: a qué producto de la familia le toca cada invitado.
     * Devuelve `null` si el pack no participa (sin familia, sin tramo o sin campo de edad).
     *
     * @return array{type:TicketType, family:list<TicketType>, date:?Carbon, rows:array<int, array{state:string, target:?TicketType}>}|null
     */
    private function walk(OrderItem $item): ?array
    {
        $type = $item->ticketType;

        if ($type === null || ! $type->isPack() || ! $type->participatesInAgeFamily()) {
            return null;
        }

        $ageKey = $type->guestAgeFieldKey();
        $quantity = max(0, (int) $item->quantity);

        // Mismo saneo que el post-form al guardar: normaliza a EXACTAMENTE `quantity` fichas y
        // vuelve a acotar cada edad. Leer con la misma función con la que se escribe es lo que
        // impide que la pantalla y la BD discrepen sobre qué edad tiene un niño.
        $clean = $type->sanitizeGuestData($item->guestData(), $quantity);
        $family = $this->family($type);

        $rows = [];
        for ($i = 0; $i < $quantity; $i++) {
            $raw = $clean[$i][$ageKey] ?? null;
            if ($raw === null || $raw === '') {
                $rows[$i] = ['state' => self::ROW_NO_AGE, 'target' => null];

                continue;
            }

            $target = $this->targetFor($family, (int) $raw);
            $rows[$i] = $target === null
                ? ['state' => self::ROW_OUT_OF_RANGE, 'target' => null]
                : ['state' => self::ROW_OK, 'target' => $target];
        }

        return ['type' => $type, 'family' => $family, 'date' => $item->slot?->date, 'rows' => $rows];
    }

    /**
     * Compone el veredicto con la aritmética del suplemento. Se separa del recorrido porque son dos
     * preguntas distintas —a quién le toca qué, y cuánto cuesta— y solo la segunda depende de la
     * fecha y del catálogo de precios.
     *
     * @param  list<TicketType>  $family
     * @param  array<int,int>  $counts
     */
    private function verdict(
        TicketType $type,
        array $family,
        array $counts,
        ?Carbon $date,
        int $quantity,
        int $withoutAge,
        int $outOfRange,
    ): GuestAgeMix {
        $base = $date !== null ? $this->price($type, $date) : null;

        $upgrades = [];
        $total = 0;
        $savings = 0;
        $priceable = $base !== null;

        foreach ($counts as $typeId => $count) {
            $target = $this->fromFamily($family, $typeId);
            $targetPrice = ($target !== null && $date !== null) ? $this->price($target, $date) : null;

            // La diferencia CRUDA, con su signo, y la parte que se COBRA, que nunca es negativa.
            // Son dos preguntas distintas y por eso viajan las dos: el suplemento sale de la
            // segunda; el «esta fiesta sería más barata» que el owner pidió enseñar
            // (`[owner, 2026-08-29]`, §14) sale de la primera, y NO es dinero.
            $diff = ($base !== null && $targetPrice !== null) ? $targetPrice - $base : null;
            $unit = $diff === null ? null : max(0, $diff);

            if ($unit === null) {
                $priceable = false;
            } else {
                $total += $unit * $count;
                $savings += max(0, -$diff) * $count;
            }

            $upgrades[] = [
                'type_id' => (int) $typeId,
                'name' => $target?->tr('name') ?? '—',
                'count' => $count,
                'unit_cents' => $unit,
                // Lo que cuesta el pack que le TOCA, para poder explicar la aritmética al cliente
                // («11,00 € en vez de 15,00 €») en vez de soltarle un importe sin origen.
                'target_price_cents' => $targetPrice,
                'diff_cents' => $diff,
            ];
        }

        return new GuestAgeMix(
            applies: true,
            mixed: $counts !== [],
            guests: $quantity,
            withoutAge: $withoutAge,
            outOfRange: $outOfRange,
            upgrades: $upgrades,
            surchargeCents: $priceable ? $total : null,
            savingsCents: $priceable ? $savings : null,
            basePriceCents: $base,
        );
    }

    /**
     * Pre-siembra la familia de una simulación: deriva **como si** el catálogo dijera esto.
     *
     * ▶ Existe para poder contestar «¿a qué fiestas ya vendidas afectaría este cambio?» ANTES de
     * guardarlo (`MixedPartyBandImpact`). La alternativa —escribir el cambio, medir y revertir—
     * dispararía eventos de modelo y auditoría por un cálculo que solo sirve para enseñar un número.
     *
     * ⚠️ **No abre un camino nuevo de derivación: siembra el memo que ya existía.** El recorrido, la
     * aritmética y el orden de la familia siguen siendo los mismos, que es lo único que garantiza
     * que el número del aviso y el que se escribirá después salgan de la misma regla.
     *
     * ⚠️ Úsalo sobre una instancia PROPIA (`new GuestAgeMixReader(...)`), nunca sobre la del
     * contenedor: es `scoped`, y contaminar su memo dejaría al resto de la petición derivando contra
     * un catálogo que no existe.
     *
     * @param  list<TicketType>  $members  la familia tal y como quedaría, ya ordenada por quien llama
     */
    public function pretendFamilyIs(string $familyKey, array $members): void
    {
        $this->families[$familyKey] = $members;
    }

    /**
     * Los productos de la familia de `$type`, ORDENADOS por el inicio de su tramo (los abiertos por
     * abajo, primero). El orden manda: si dos tramos se solapasen —el panel lo impide al guardar,
     * pero un dato viejo puede— gana el de menor edad, que es determinista y explicable.
     *
     * ⚠️ **NO se filtra por `is_sellable` ni por `is_active`.** La familia es una CLASIFICACIÓN, no
     * una oferta: un producto retirado de la venta sigue describiendo un régimen al que pertenecen
     * reservas vivas. Filtrarlo convertiría a todos sus invitados en «fuera de rango» el día que
     * alguien lo despublicase, que es exactamente cuando peor viene.
     *
     * ⚠️ El orden se hace **en PHP y no en SQL**: `ORDER BY` con nulos no se comporta igual en
     * MySQL que en SQLite —donde corre la suite— y una familia son tres filas, no tres mil
     * (`specs/panel-navegacion.md` §7.3: un test en SQLite no demuestra la conducta en MySQL).
     *
     * ⚠️ La comparación es de IGUALDAD EXACTA, no un `LOWER(TRIM(…))`, por lo mismo: MySQL cotejaría
     * sin distinguir mayúsculas y SQLite sí, así que la conducta dependería del motor. El valor se
     * normaliza al GUARDARLO (`InteractsWithCatalogForm::sanitizeGuestAgeFamily`), que además es lo
     * único que deja usar el índice de la columna.
     *
     * @return list<TicketType>
     */
    private function family(TicketType $type): array
    {
        $key = (string) $type->guestAgeFamily();

        if (! array_key_exists($key, $this->families)) {
            $members = TicketType::query()
                ->where('type', TicketType::TYPE_PACK)
                ->where('guest_age_family', $key)
                ->get()
                ->all();

            usort($members, function (TicketType $a, TicketType $b): int {
                $am = $a->guest_age_min ?? -1;
                $bm = $b->guest_age_min ?? -1;

                return $am <=> $bm ?: ((int) $a->id <=> (int) $b->id);
            });

            $this->families[$key] = $members;
        }

        return $this->families[$key];
    }

    /**
     * El producto de la familia al que corresponde esa edad, o `null` si ninguno la cubre — que no
     * es un fallo del cliente sino un HUECO DE CONFIGURACIÓN (nadie declaró quién atiende a un niño
     * de 14), y por eso se cuenta y se enseña en vez de tragarse.
     *
     * @param  list<TicketType>  $family
     */
    private function targetFor(array $family, int $age): ?TicketType
    {
        foreach ($family as $candidate) {
            if ($candidate->coversGuestAge($age)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param  list<TicketType>  $family */
    private function fromFamily(array $family, int $typeId): ?TicketType
    {
        foreach ($family as $member) {
            if ((int) $member->id === $typeId) {
                return $member;
            }
        }

        return null;
    }

    /**
     * Precio de catálogo de un producto ese día, memoizado. `null` = ese día no tiene tarifa, y es
     * una respuesta legítima que también se memoiza: con `??=` un producto sin precio se habría
     * vuelto a consultar en cada invitado, que es justo el caso en el que más se repite.
     */
    private function price(TicketType $type, Carbon $date): ?int
    {
        $key = $type->id.'|'.$date->toDateString();

        if (! array_key_exists($key, $this->prices)) {
            $this->prices[$key] = $this->rates->priceCents($type, $date);
        }

        return $this->prices[$key];
    }
}
