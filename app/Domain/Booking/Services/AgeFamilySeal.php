<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;

/**
 * El SELLO de condiciones de una reserva (`docs/specs/cumple-mixto.md` §21): la familia por edad con
 * la que se vendió una fiesta, sus tramos y sus precios para el día de la fiesta.
 *
 * `[DECIDIDO owner, 2026-08-31]` («el cliente compra con unas condiciones y las mantenemos»): el
 * veredicto de fiesta MIXTA se deriva de ESTO y no del catálogo vivo, así que ningún cambio de
 * catálogo —precio, tramo, familia, un hermano nuevo— mueve una reserva ya vendida. Solo cambia de
 * condiciones lo que cambia de producto (`AgeFamilySealer`).
 *
 * Es un valor inmutable parseado del JSON de `order_items.age_family_seal`. Tres cosas que conviene
 * saber al leerlo:
 *  - `family === null` es un sello VÁLIDO: «este pack se vendió SIN condiciones por edad». Es una
 *    afirmación, no un silencio, y el reconciliador la trata como tal ({@see participates}).
 *  - `booked_type_id` y `priced_on` son el RECIBO —bajo qué pack y para qué día se calculó todo lo
 *    demás—. Si no coinciden con la fila, el sello está CADUCADO ({@see matches}) y no gobierna
 *    nada: alguien movió la reserva sin re-sellarla, y eso se enseña, no se tapa.
 *  - los miembros van ORDENADOS por el inicio de su tramo (los abiertos por abajo, primero) y por id
 *    de desempate, que es la regla que siempre tuvo el lector: si dos tramos se solapasen, gana el
 *    de menor edad, determinista y explicable.
 */
final class AgeFamilySeal
{
    public const VERSION = 1;

    /**
     * Por qué una edad no tiene producto en estas condiciones (`DECISIONES #284` D6, spec §22.4):
     * por debajo del tramo más bajo de la familia, por encima del más alto, o en un HUECO entre dos
     * tramos. Cada caso tiene su texto, configurable por instalación (`MixedPartySettings`).
     */
    public const NO_PRODUCT_BELOW = 'below';

    public const NO_PRODUCT_ABOVE = 'above';

    public const NO_PRODUCT_GAP = 'gap';

    /** @param  list<SealedRegime>  $members */
    public function __construct(
        public readonly ?string $family,
        public readonly int $bookedTypeId,
        public readonly string $pricedOn,
        public readonly string $sealedAt,
        public readonly array $members,
    ) {}

    /** El documento tal como viene de la columna; `null` si no hay sello o no se puede leer. */
    public static function fromArray(mixed $raw): ?self
    {
        if (! is_array($raw)) {
            return null;
        }
        $booked = $raw['booked_type_id'] ?? null;
        $pricedOn = $raw['priced_on'] ?? null;
        if (! is_numeric($booked) || ! is_string($pricedOn) || $pricedOn === '') {
            return null; // sin los dos hechos del recibo no hay contra qué validarlo: no es un sello.
        }

        $members = [];
        foreach (is_array($raw['members'] ?? null) ? $raw['members'] : [] as $member) {
            $regime = is_array($member) ? SealedRegime::fromArray($member) : null;
            if ($regime !== null) {
                $members[] = $regime;
            }
        }

        $family = $raw['family'] ?? null;

        return new self(
            is_string($family) && $family !== '' ? $family : null,
            (int) $booked,
            $pricedOn,
            (string) ($raw['sealed_at'] ?? ''),
            self::sorted($members),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'v' => self::VERSION,
            'family' => $this->family,
            'booked_type_id' => $this->bookedTypeId,
            'priced_on' => $this->pricedOn,
            'sealed_at' => $this->sealedAt,
            'members' => array_map(static fn (SealedRegime $m): array => $m->toArray(), $this->members),
        ];
    }

    /** ¿Se vendió CON condiciones por edad? Con `false`, el veredicto es «no aplica» y lo AFIRMA. */
    public function participates(): bool
    {
        return $this->family !== null && $this->members !== [];
    }

    /**
     * ¿Este sello describe la fila tal como está hoy? Los dos hechos del recibo —el pack reservado y
     * el día tarificado— tienen que ser los de la fila. Un sello que no casa está CADUCADO: la reserva
     * se movió de pack o de día sin pasar por el sellador (un `UPDATE` a mano, un camino nuevo que
     * nadie enganchó), y derivar de él sería poner precio a condiciones que no son las suyas.
     */
    public function matches(OrderItem $item): bool
    {
        return $this->bookedTypeId === (int) $item->ticket_type_id
            && $this->pricedOn === $item->slot?->date?->toDateString();
    }

    public function member(int $typeId): ?SealedRegime
    {
        foreach ($this->members as $member) {
            if ($member->typeId === $typeId) {
                return $member;
            }
        }

        return null;
    }

    /** El pack reservado, tal como se selló (con su precio del día). */
    public function booked(): ?SealedRegime
    {
        return $this->member($this->bookedTypeId);
    }

    /**
     * El régimen que le toca a esa edad, o `null` si ninguno de los sellados la cubre — que con el
     * sello ya no es un «hueco de configuración» sino «no hay producto para esa edad en las
     * condiciones de esta reserva» (`DECISIONES #284` D6).
     */
    public function regimeFor(int $age): ?SealedRegime
    {
        foreach ($this->members as $member) {
            if ($member->covers($age)) {
                return $member;
            }
        }

        return null;
    }

    /**
     * El rango que cubre la familia sellada, entre todos sus tramos: `[mínimo, máximo]`, con `null`
     * en el lado que algún tramo deja abierto. Lo que hay ENTRE los dos y ningún tramo cubre es un
     * hueco.
     *
     * @return array{0:?int, 1:?int}
     */
    public function coverage(): array
    {
        $min = null;
        $max = null;
        $openBelow = false;
        $openAbove = false;

        foreach ($this->members as $member) {
            if ($member->ageMin === null) {
                $openBelow = true;
            } elseif ($min === null || $member->ageMin < $min) {
                $min = $member->ageMin;
            }
            if ($member->ageMax === null) {
                $openAbove = true;
            } elseif ($max === null || $member->ageMax > $max) {
                $max = $member->ageMax;
            }
        }

        return [$openBelow ? null : $min, $openAbove ? null : $max];
    }

    /**
     * Por qué esa edad NO tiene producto en estas condiciones, o `null` si sí lo tiene
     * (`DECISIONES #284` D6): no es un hueco de configuración, es «no hay producto para esa edad», y
     * se le explica al cliente con un texto distinto por caso.
     */
    public function noProductReason(int $age): ?string
    {
        if ($this->regimeFor($age) !== null) {
            return null;
        }

        [$min, $max] = $this->coverage();

        if ($min !== null && $age < $min) {
            return self::NO_PRODUCT_BELOW;
        }
        if ($max !== null && $age > $max) {
            return self::NO_PRODUCT_ABOVE;
        }

        return self::NO_PRODUCT_GAP;
    }

    /**
     * El mismo sello —misma familia, mismos tramos— con los precios de OTRO día
     * (`[DECIDIDO owner, 2026-08-31]`, §21.8 Q2): mover la fiesta de día conserva las condiciones
     * con las que se compró y solo el precio sigue al día, que es lo que `PAY-18` ya hace con el
     * precio de la propia fiesta.
     *
     * @param  callable(SealedRegime): ?int  $priceFor  el precio de ese régimen para el día nuevo
     */
    public function repricedFor(string $pricedOn, string $sealedAt, callable $priceFor): self
    {
        return new self(
            $this->family,
            $this->bookedTypeId,
            $pricedOn,
            $sealedAt,
            array_map(static fn (SealedRegime $m): SealedRegime => $m->withPrice($priceFor($m)), $this->members),
        );
    }

    /**
     * @param  list<SealedRegime>  $members
     * @return list<SealedRegime>
     */
    public static function sorted(array $members): array
    {
        usort($members, static function (SealedRegime $a, SealedRegime $b): int {
            return (($a->ageMin ?? -1) <=> ($b->ageMin ?? -1)) ?: ($a->typeId <=> $b->typeId);
        });

        return array_values($members);
    }
}
