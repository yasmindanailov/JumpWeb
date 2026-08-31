<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;

/**
 * Deriva el veredicto de mezcla de edades de una reserva (`docs/specs/cumple-mixto.md` §9 y §21).
 *
 * **El problema que resuelve, dicho por el owner**: «el sistema no tiene conexión entre un cumple
 * KIDS y uno JUMP». `[DECIDIDO owner, 2026-08-29]` la conexión es **familia + tramo de edad**: cada
 * producto declara el tramo que cubre y una familia, y los que comparten familia son alternativos
 * entre sí. Este lector recorre las edades declaradas en el post-form y le busca a cada invitado el
 * régimen de la familia que le toca; los que no caen en el reservado son la mezcla.
 *
 * ▶ **Desde el 2026-08-31 deriva del SELLO de la reserva, no del catálogo** (`DECISIONES #284` D1/D2,
 * spec §21.5). La familia, los tramos y los precios del día se copian en
 * `order_items.age_family_seal` cuando la reserva nace y solo se reescriben cuando cambia de
 * producto o de día (`AgeFamilySealer`). Del catálogo vivo se toma únicamente lo que NO es una
 * condición de venta: la clave del campo de edad y el saneo de las fichas, que son esquema.
 * Consecuencia medida: un cambio de tramo o de tarifa ya no mueve una fiesta vendida en ninguna
 * dirección, y el primer cargo sale del catálogo del día de la compra, no del día del formulario.
 *
 * ▶ **Tres formas de «no aplica», y no son intercambiables** ({@see sealOrVerdict}): sin sello
 * (silencio), sello caducado (silencio, y se enseña en rojo) y sello que dice «sin condiciones»
 * (afirmación, y puede retirar un suplemento). El reconciliador las distingue por `sealed`.
 *
 * **Solo lectura y sin estado.** No toma locks, no escribe, no memoiza nada —todo lo que necesita
 * viaja en la fila que ya tiene cargada— y no pertenece al `CRITICAL_RE` del `pre-push`.
 *
 * ⚠️ **La aritmética del suplemento sale de los precios SELLADOS, nunca de un número escrito aquí**:
 * es `precio_sellado(destino) − precio_sellado(reservado)`, los dos resueltos por `RateResolver`
 * para la fecha de la franja en el momento de sellar — la misma fuente que usó la compra, así que
 * el suplemento no puede divergir de lo que costaba el producto ese día cuando se vendió.
 *
 * ⚠️ **Con suelo en 0: bajar de régimen no abona nada.** Un invitado que corresponde a un producto
 * MÁS BARATO cuenta para la etiqueta —la fiesta es mixta de verdad— pero su diferencia es 0. El
 * encargo pide cobrar la diferencia al que sube; el −X € está diseñado (§20) y es la tanda T4.
 */
class GuestAgeMixReader
{
    /** Estados de una ficha: su edad cae en un régimen, no la ha declarado, o no la cubre ninguno. */
    public const ROW_OK = 'ok';

    public const ROW_NO_AGE = 'no_age';

    public const ROW_OUT_OF_RANGE = 'out_of_range';

    public function for(OrderItem $item): GuestAgeMix
    {
        $seal = $this->sealOrVerdict($item);
        if ($seal instanceof GuestAgeMix) {
            return $seal;
        }

        $rows = $this->rows($item, $seal);

        $withoutAge = 0;
        $outOfRange = 0;
        /** @var array<int, int> $counts */
        $counts = [];

        foreach ($rows as $row) {
            if ($row['state'] === self::ROW_NO_AGE) {
                $withoutAge++;

                continue;
            }
            if ($row['state'] === self::ROW_OUT_OF_RANGE) {
                $outOfRange++;

                continue;
            }
            $target = $row['target'];
            if ($target->typeId === $seal->bookedTypeId) {
                continue; // le toca el régimen que ya tiene: nada que hacer.
            }
            $counts[$target->typeId] = ($counts[$target->typeId] ?? 0) + 1;
        }

        return $this->verdict($seal, $counts, count($rows), $withoutAge, $outOfRange);
    }

    /**
     * El régimen que le toca a CADA invitado, por posición de su ficha — para el post-form, que
     * pinta el rótulo dentro del recuadro del niño (`[owner, 2026-08-29]`, §15).
     *
     * Sale del MISMO recorrido que el veredicto agregado: si esto tuviera su propia copia de la
     * regla, el rótulo de una ficha podría decir «Kids» mientras el total dice otra cosa.
     *
     * Una ficha SIN producto (`ROW_OUT_OF_RANGE`) trae además su `reason` —`below` · `above` · `gap`
     * (`AgeFamilySeal::NO_PRODUCT_*`)— para que el post-form le ponga el texto que corresponde
     * (`DECISIONES #284` D6, spec §22.4).
     *
     * @return array<int, array{state:string, name:?string, own:bool, reason:?string}> vacío si la reserva no participa
     */
    public function guestRegimes(OrderItem $item): array
    {
        $seal = $this->sealOrVerdict($item);
        if ($seal instanceof GuestAgeMix) {
            return [];
        }

        $out = [];
        foreach ($this->rows($item, $seal) as $i => $row) {
            $target = $row['target'];
            $out[$i] = [
                'state' => $row['state'],
                'name' => $target?->displayName(),
                'own' => $target !== null && $target->typeId === $seal->bookedTypeId,
                'reason' => $row['reason'],
            ];
        }

        return $out;
    }

    /**
     * El sello que GOBIERNA esta reserva, o el veredicto «no aplica» que corresponde a no tenerlo.
     *
     * Las tres salidas sin sello que gobierne significan cosas distintas, y el reconciliador las
     * separa por `sealed` ({@see MixedPartySurcharge::derivationGoverns}):
     *  - **sin sello** (una entrada, un complemento, o una reserva anterior al sello): SILENCIO —
     *    no se sabe con qué condiciones se vendió, así que no se afirma nada y no se toca nada;
     *  - **sello caducado** (no casa con el pack o la fecha de la fila): SILENCIO, y además se
     *    enseña en rojo — alguien movió la reserva sin re-sellarla;
     *  - **sello sin familia**: AFIRMACIÓN — se vendió sin condiciones por edad. Si un operador
     *    cambió el pack a uno sin familia, esto es lo que deja retirar la línea de suplemento.
     */
    private function sealOrVerdict(OrderItem $item): AgeFamilySeal|GuestAgeMix
    {
        $type = $item->ticketType;
        if ($type === null || ! $type->isPack()) {
            return GuestAgeMix::notApplicable();
        }

        $seal = $item->ageFamilySeal();
        if ($seal === null) {
            return GuestAgeMix::notApplicable();
        }
        if (! $seal->matches($item)) {
            return GuestAgeMix::notApplicable(staleSeal: true);
        }
        if (! $seal->participates()) {
            return GuestAgeMix::notApplicable(sealed: true);
        }

        return $seal;
    }

    /**
     * El recorrido ÚNICO de las fichas: a qué régimen SELLADO le toca cada invitado.
     *
     * ⚠️ La clave del campo de edad y el saneo vienen del ESQUEMA vigente del pack, no del sello: son
     * la forma del formulario, no una condición de venta (§21.3). Si el parque retira el campo de
     * edad, todas las fichas pasan a «sin edad», el veredicto queda incompleto y el importe se
     * congela — que es la conducta correcta para «falta el dato».
     *
     * @return array<int, array{state:string, target:?SealedRegime, reason:?string}>
     */
    private function rows(OrderItem $item, AgeFamilySeal $seal): array
    {
        $type = $item->ticketType;
        $ageKey = $type?->guestAgeFieldKey();
        $quantity = max(0, (int) $item->quantity);

        // Mismo saneo que el post-form al guardar: normaliza a EXACTAMENTE `quantity` fichas y
        // vuelve a acotar cada edad. Leer con la misma función con la que se escribe es lo que
        // impide que la pantalla y la BD discrepen sobre qué edad tiene un niño.
        $clean = $type?->sanitizeGuestData($item->guestData(), $quantity) ?? [];

        $rows = [];
        for ($i = 0; $i < $quantity; $i++) {
            $raw = $ageKey === null ? null : ($clean[$i][$ageKey] ?? null);
            if ($raw === null || $raw === '') {
                $rows[$i] = ['state' => self::ROW_NO_AGE, 'target' => null, 'reason' => null];

                continue;
            }

            $age = (int) $raw;
            $target = $seal->regimeFor($age);
            $rows[$i] = $target === null
                ? ['state' => self::ROW_OUT_OF_RANGE, 'target' => null, 'reason' => $seal->noProductReason($age)]
                : ['state' => self::ROW_OK, 'target' => $target, 'reason' => null];
        }

        return $rows;
    }

    /**
     * Compone el veredicto con la aritmética del suplemento. Se separa del recorrido porque son dos
     * preguntas distintas —a quién le toca qué, y cuánto cuesta— y solo la segunda mira los precios.
     *
     * @param  array<int,int>  $counts
     */
    private function verdict(AgeFamilySeal $seal, array $counts, int $quantity, int $withoutAge, int $outOfRange): GuestAgeMix
    {
        $base = $seal->booked()?->priceCents;

        $upgrades = [];
        $total = 0;
        $savings = 0;
        $priceable = $base !== null;

        foreach ($counts as $typeId => $count) {
            $target = $seal->member($typeId);
            $targetPrice = $target?->priceCents;

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
                'name' => $target?->displayName() ?? '—',
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
            sealed: true,
        );
    }
}
