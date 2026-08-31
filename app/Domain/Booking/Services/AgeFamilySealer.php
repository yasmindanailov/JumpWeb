<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use Carbon\CarbonInterface;

/**
 * ESCRIBE el sello de condiciones de una reserva (`docs/specs/cumple-mixto.md` §21.4,
 * `DECISIONES #288`).
 *
 * `[DECIDIDO owner, 2026-08-31]` «el cliente compra con unas condiciones y las mantenemos; solo
 * cambia de condiciones lo que cambia de producto». Eso se traduce en TRES momentos y ninguno más:
 *
 *  - **Nace** (`OrderCreator`, web · API · alta manual): sello NUEVO con la familia del pack, sus
 *    tramos y los precios del día de la franja — resueltos por `RateResolver`, la MISMA fuente con
 *    la que se fija el `unit_price` de la propia reserva.
 *  - **Cambia de PRODUCTO** (`OrderItemEditor::edit`): sello NUEVO con el pack nuevo y el catálogo de
 *    hoy para el día efectivo.
 *  - **Cambia de FECHA** (`OrderItemEditor::changeSlot` y `edit`): el sello se RE-PRECIA — misma
 *    familia y mismos tramos, precios del día destino — porque los tramos son sus condiciones y el
 *    precio del día es lo único que `PAY-18` hace seguir a la fecha ({@see reprice}).
 *
 * Y NUNCA por un cambio del catálogo, ni de la cantidad, ni de las edades, ni de los complementos.
 *
 * ⚠️ **Va DENTRO de la transacción que muta la reserva**, en el mismo `forceFill` que mueve
 * `ticket_type_id` o `slot_id`, no en el post-commit donde vive la reconciliación: si fuera después
 * habría una ventana con la reserva en el día nuevo y el sello del viejo, y el reconciliador —que
 * relee la fila bloqueada— derivaría de un sello caducado. Por eso este fichero está en el
 * `CRITICAL_RE` del `pre-push`: no escribe dinero, escribe **las condiciones que deciden el dinero**,
 * bajo los locks de `OrderCreator` y `OrderItemEditor` — el mismo motivo por el que está
 * `PackAvailability` («el gate vigilaba a quien LLAMA y no a quien CUENTA»).
 *
 * ⚠️ La familia se lee SIN filtrar por `is_sellable`/`is_active`: es una clasificación, no una
 * oferta (el criterio que siempre tuvo el lector). Un pack retirado de la venta sigue describiendo
 * un régimen, y las reservas que se vendan mientras exista lo llevarán sellado.
 */
class AgeFamilySealer
{
    public function __construct(private RateResolver $rates) {}

    /**
     * El sello de un pack para un día, compuesto desde el catálogo de HOY. Puro: no escribe.
     *
     * `null` para lo que no es un pack (entradas y complementos no llevan sello). Un pack que no
     * participa en ninguna familia recibe un sello con `family: null`: **una afirmación** («se
     * vendió sin condiciones por edad»), que es distinta del silencio de una línea sin sello.
     */
    public function build(TicketType $type, CarbonInterface $pricedOn): ?AgeFamilySeal
    {
        if (! $type->isPack()) {
            return null;
        }

        $day = $pricedOn->toDateString();
        $sealedAt = now()->toIso8601String();

        if (! $type->participatesInAgeFamily()) {
            return new AgeFamilySeal(null, (int) $type->id, $day, $sealedAt, []);
        }

        $family = (string) $type->guestAgeFamily();
        $members = TicketType::query()
            ->where('type', TicketType::TYPE_PACK)
            ->where('guest_age_family', $family)
            ->get()
            ->map(fn (TicketType $member): SealedRegime => new SealedRegime(
                (int) $member->id,
                is_array($member->name) ? $member->name : ['es' => (string) $member->name],
                $member->guest_age_min === null ? null : (int) $member->guest_age_min,
                $member->guest_age_max === null ? null : (int) $member->guest_age_max,
                $this->rates->priceCents($member, $pricedOn),
            ))
            ->all();

        return new AgeFamilySeal($family, (int) $type->id, $day, $sealedAt, AgeFamilySeal::sorted($members));
    }

    /**
     * Sella la fila con el catálogo de hoy para ese día (nacimiento y cambio de producto).
     * Escribe con `forceFill` + `save`: es para quien crea la línea FUERA de `OrderCreator` —el
     * verificador de concurrencia, un test— y no puede meter el documento en el `create()`.
     */
    public function seal(OrderItem $item, TicketType $type, CarbonInterface $pricedOn): ?AgeFamilySeal
    {
        $seal = $this->build($type, $pricedOn);
        $item->forceFill(['age_family_seal' => $seal?->toArray()])->save();

        return $seal;
    }

    /**
     * El sello RE-PRECIADO para otro día: conserva familia y tramos, resuelve cada precio para
     * `$pricedOn`. Un miembro cuyo producto ya no exista queda sin precio (`null`), que el veredicto
     * trata como «no se puede tarificar» y el reconciliador como una ausencia: ni retira ni crea.
     */
    public function reprice(AgeFamilySeal $seal, CarbonInterface $pricedOn): AgeFamilySeal
    {
        $ids = array_map(static fn (SealedRegime $m): int => $m->typeId, $seal->members);
        $products = TicketType::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $seal->repricedFor(
            $pricedOn->toDateString(),
            now()->toIso8601String(),
            function (SealedRegime $member) use ($products, $pricedOn): ?int {
                $product = $products->get($member->typeId);

                return $product instanceof TicketType ? $this->rates->priceCents($product, $pricedOn) : null;
            },
        );
    }
}
