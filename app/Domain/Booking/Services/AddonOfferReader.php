<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\AddonChoiceGroup;
use App\Domain\Booking\Contracts\AddonOffer;
use App\Domain\Booking\Contracts\ResolvedAddon;
use App\Domain\Booking\Contracts\ResolvedAddons;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-model de COMPLEMENTOS RESUELTOS ({@see AddonOffer}).
 *
 * **No reimplementa nada, y eso es lo que lo hace seguro.** Todo el trabajo lo sigue haciendo
 * `AddonResolver`, que es la autoridad que ya comparten la compra web, el alta manual del panel y
 * `OrderCreator`. Lo que se añade aquí es la forma: convertir el modelo de vista —arrays pensados
 * para un Blade— en DTOs que un contrato público pueda publicar sin atarse a las claves de una
 * plantilla.
 *
 * ⚠️ **Por eso la compra web NO se cambió en este paso.** En la extracción anterior (4.0b·6) la
 * regla vivía dentro del componente Livewire y había que sacarla; aquí ya vivía en el dominio y las
 * dos superficies ya la comparten, así que hacer pasar al sidebar por estos DTOs solo cambiaría el
 * tipo de dato que consume su plantilla —la del paso con más clics del embudo— sin retirar ninguna
 * duplicación. No hay copia que unificar: hay una fuente que publicar.
 *
 * **La fecha del PRECIO es HOY, no la de la línea.** Medido (spec §4.4.0, punto 2): los cuatro
 * llamantes de producción pasan `Carbon::today()`, incluido `CartPricer` con el comentario explícito
 * de que «los complementos no tienen fecha propia». Tarificar aquí al día reservado inventaría una
 * divergencia con lo que el checkout va a cobrar (`specs/hora-extra.md` §4.11, `[DECIDIDO owner]`:
 * el precio de la hora extra no varía por día).
 *
 * ⚠️ Desde la hora extra la fecha/hora de la línea SÍ entran en `resolve()` — para OTRA cosa: la
 * OCUPACIÓN (¿aterriza la hija a esa hora, y con cuántas plazas?). Precio y aterrizaje son dos
 * preguntas con dos fechas, y mezclarlas es como nació el bug que este párrafo evita.
 */
class AddonOfferReader implements AddonOffer
{
    public function __construct(
        private AddonResolver $resolver,
        private SlotAvailability $slotAvailability,
    ) {}

    public function resolve(int $productId, int $quantity, array $quantities = [], array $choices = [], ?string $date = null, ?string $time = null): ?ResolvedAddons
    {
        $product = $this->selectableProduct($productId);

        if ($product === null) {
            return null;
        }

        // El eje de FASE (`specs/complementos-post-reserva.md` §4.4, `#413`): esta es la oferta del
        // EMBUDO, así que solo se venden aquí los complementos de fase `booking`. Un `postform`
        // **no nace nunca con el pedido**, y de eso vive la propiedad de §1.3.
        $offered = AddonResolver::forStage($product->addons, ProductAddon::STAGE_BOOKING);
        $guests = max(0, $quantity);

        // La HORA EXTRA (`specs/hora-extra.md` §4.5): con la hora de la línea delante, un ocupante
        // que NO ATERRIZA —sin «franja siguiente», cerrada o llena— se retira de la oferta ANTES de
        // resolver nada: así el modelo de vista, el total y la `selection` quedan coherentes sin él,
        // igual que con un complemento de pago sin tarifa. Ofrecerlo sería ofrecer lo que el
        // checkout rechaza (el primo de `AFORO-02`, la trampa exacta que motivó §4.5).
        $landing = $this->occupantLanding($product, $offered, $date, $time);
        if ($date !== null && $time !== null) {
            $offered = $offered->filter(
                fn (TicketType $addon): bool => ! $addon->occupiesAfterParent()
                    || array_key_exists((int) $addon->id, $landing)
            )->values();
        }

        // Un grupo sin elegir no es un estado que exista: siempre hay uno activo. Se completan solo
        // los grupos que el cliente no mandó, para no pisar su elección.
        $choices += AddonResolver::defaultSelection($offered)['groups'];

        // ⚠️⚠️ **La OFERTA se tarifica con el día de la VISITA, igual que el cobro** (`#415`). Con
        // `Carbon::today()` aquí y en `OrderCreator` los dos coincidían —no había bug de paridad—,
        // pero los dos respondían a la pregunta equivocada: si un complemento solo tiene precio en
        // días `special` (la hora extra de finde), lo que decidía si aparece era **el día en que se
        // abre la web**, no el de la fiesta. Un martes no se podía añadir a una reserva del sábado, y
        // un sábado sí se añadía a una del martes.
        //
        // ⚠️ Sin fecha elegida todavía se cae a HOY, que es la conducta de siempre: es el estado en
        // que el cliente aún no ha dicho qué día viene, así que no hay día de visita con el que
        // preguntar. En cuanto elige, el precio y la presencia se recalculan con el suyo.
        $view = $this->resolver->viewModel(
            $offered,
            $quantities,
            $choices,
            $guests,
            $product->isPack(),
            $date !== null ? Carbon::parse($date) : Carbon::today(),
        );

        // Y el que SÍ aterriza sale CAPADO por las plazas reales de su franja y por «no se quedan
        // más de los que entran» (§4.4·5) — el mismo tope que `AddonResolver::resolve()` impone al
        // cobrar, dicho aquí en `max`/`can_inc` para que el stepper pare ANTES del rechazo.
        $this->capOccupantRows($view, $landing, $guests);

        return new ResolvedAddons(
            groups: array_map(static fn (array $group): AddonChoiceGroup => new AddonChoiceGroup(
                key: (string) $group['key'],
                label: (string) $group['label'],
                options: array_map(self::toDto(...), $group['options']),
            ), $view['groups']),
            singles: array_map(self::toDto(...), $view['singles']),
            totalCents: (int) $view['total'],
            // La misma autoridad que el modelo de vista (`resolveSelectedIds`), así que lo que se
            // guarda es exactamente lo que se enseña: obligatorios inyectados y dependientes
            // huérfanos fuera.
            selection: array_values(AddonResolver::buildSelection($offered, $quantities, $choices, $guests)),
        );
    }

    /**
     * Plazas con las que ATERRIZA cada complemento ocupante a la hora dada: `addonId → plazas
     * libres de su franja` (solo los que aterrizan con ≥ 1; la franja sale de la regla del borde 8
     * vía `AddonOccupancy`). Vacío sin fecha/hora — sin la hora de la línea no hay franja siguiente
     * que consultar, y la oferta pasa sin decorar.
     *
     * ⚠️ Se calcula SIN los ocupantes provisionales de la cesta (este endpoint no la recibe): una
     * cesta con otra línea sobre la misma franja puede dejar la oferta un punto OPTIMISTA — y ese
     * borde lo cierra el checkout con su mensaje (`addon_occupancy_line`). Es presentación; la
     * autoridad re-comprueba bajo lock (§4.5: «recalcular evita el rechazo, no lo sustituye»).
     *
     * @param  Collection<int, TicketType>  $offered
     * @return array<int, int>
     */
    private function occupantLanding(TicketType $product, $offered, ?string $date, ?string $time): array
    {
        if ($date === null || $time === null || $product->zone_id === null) {
            return [];
        }

        $occupying = $offered->filter(fn (TicketType $addon): bool => $addon->occupiesAfterParent());
        if ($occupying->isEmpty()) {
            return [];
        }

        $time = strlen($time) === 5 ? $time.':00' : $time;
        $childStart = AddonOccupancy::childEntryStart($product, $time);
        if ($childStart === null) {
            return []; // borde 7: un padre sin fin no tiene franja siguiente
        }

        $childSlot = Slot::query()
            ->where('zone_id', $product->zone_id)
            ->where('date', $date)
            ->where('start_time', $childStart)
            ->first();
        if ($childSlot === null) {
            return []; // borde 3/8: sin franja siguiente no hay hora extra a esa hora
        }

        $landing = [];
        foreach ($occupying as $addon) {
            if (! $addon->hasSaneOccupancyConfig()) {
                continue; // el cinturón de §4.1: una config rota ni se ofrece
            }
            $available = $this->slotAvailability->availableFor($childSlot, $addon->duration_min);
            if ($available >= 1) {
                $landing[(int) $addon->id] = $available;
            }
        }

        return $landing;
    }

    /**
     * Capa `max`/`can_inc` de las filas OCUPANTES con las dos cotas reales: las plazas de su
     * franja (`$landing`) y «no se quedan más de los que entran» (§4.4·5, contando a los HERMANOS
     * seleccionados). Los valores que el cobro impondrá, dichos en la oferta para que el stepper
     * pare antes del rechazo. Una fila con la cantidad ya por encima del tope no se muta: su
     * `can_inc` queda en `false` y el checkout dirá el resto.
     *
     * @param  array{singles: array<int, array<string, mixed>>, groups: array<int, array{options: array<int, array<string, mixed>>}>}  $view
     * @param  array<int, int>  $landing
     */
    private function capOccupantRows(array &$view, array $landing, int $lineQuantity): void
    {
        if ($landing === []) {
            return;
        }

        $eachRow = function (callable $fn) use (&$view): void {
            foreach ($view['singles'] as &$row) {
                $fn($row);
            }
            unset($row);
            foreach ($view['groups'] as &$group) {
                foreach ($group['options'] as &$row) {
                    $fn($row);
                }
                unset($row);
            }
            unset($group);
        };

        $staying = 0;
        $eachRow(function (array $row) use (&$staying, $landing): void {
            if (isset($landing[(int) $row['id']]) && ($row['selected'] ?? false)) {
                $staying += (int) $row['qty'];
            }
        });

        $eachRow(function (array &$row) use ($staying, $landing, $lineQuantity): void {
            $id = (int) $row['id'];
            if (! isset($landing[$id])) {
                return; // fila neutra: ni una cota nueva
            }
            $others = $staying - (($row['selected'] ?? false) ? (int) $row['qty'] : 0);
            $cap = max(0, min($landing[$id], $lineQuantity - $others));
            $row['max'] = $row['max'] === null ? $cap : min((int) $row['max'], $cap);
            $row['can_inc'] = (bool) $row['can_inc'] && (int) $row['qty'] < (int) $row['max'];
        });
    }

    /**
     * El producto BASE por id, o `null`.
     *
     * Mismo filtro que el catálogo y la disponibilidad —en venta, zona operativa y seleccionable—,
     * y por el mismo motivo: que no exista, que no se venda o que su zona esté apagada dan la misma
     * respuesta para no convertir el endpoint en un oráculo.
     *
     * `addons.prices.rateType` va eager-loaded porque el modelo de vista recorre cada complemento;
     * el precio del día lo resuelve igualmente `RateResolver` con una consulta por complemento
     * —pendiente conocida y medida en `DEUDA.md`—, y `ApiOverheadTest` impide que empeore.
     */
    private function selectableProduct(int $productId): ?TicketType
    {
        return TicketType::sellable()
            ->inOperationalZone()
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK])
            ->with('addons.prices.rateType')
            ->whereKey($productId)
            ->first();
    }

    /**
     * Fila del modelo de vista → DTO. Traducción pura de claves: si aquí se decidiera algo, sería la
     * segunda fuente de verdad que todo el contrato existe para no crear.
     *
     * @param  array<string, mixed>  $row
     */
    private static function toDto(array $row): ResolvedAddon
    {
        return new ResolvedAddon(
            productId: (int) $row['id'],
            name: (string) $row['name'],
            priceCents: (int) $row['price'],
            note: (string) $row['note'],
            isIncluded: (bool) $row['is_included'],
            isMandatory: (bool) $row['is_mandatory'],
            perGuest: (bool) $row['per_guest'],
            allowExtra: (bool) $row['allow_extra'],
            badge: $row['badge'] !== null ? (string) $row['badge'] : null,
            features: array_values(array_map(strval(...), $row['features'])),
            selected: (bool) $row['selected'],
            available: (bool) $row['available'],
            requiresName: $row['requires_name'] !== null ? (string) $row['requires_name'] : null,
            quantity: (int) $row['qty'],
            freeQuantity: (int) $row['free'],
            chargedCents: (int) $row['charged'],
            minQuantity: (int) $row['min'],
            maxQuantity: $row['max'] !== null ? (int) $row['max'] : null,
            canToggle: (bool) $row['can_toggle'],
            canIncrease: (bool) $row['can_inc'],
            canDecrease: (bool) $row['can_dec'],
        );
    }
}
