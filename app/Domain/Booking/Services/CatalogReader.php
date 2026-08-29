<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\CatalogAddon;
use App\Domain\Booking\Contracts\CatalogEventField;
use App\Domain\Booking\Contracts\CatalogProduct;
use App\Domain\Booking\Contracts\CatalogProductDetail;
use App\Domain\Booking\Contracts\CatalogZone;
use App\Domain\Booking\Contracts\ProductCatalog;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-model de BOOKING: el catálogo de venta ({@see ProductCatalog}).
 *
 * Reúne en un solo sitio lo que estaba repartido entre `Livewire\Tickets\Purchase::catalogTypes()`
 * (qué se vende), `catalogSection()` (qué se cuenta de cada producto) y las reglas de oferta de
 * complementos de `AddonResolver::viewModel()` (cuáles llegan a ofrecerse). No inventa reglas: las
 * que ya existían se llaman donde estaban —`depositLabel()`, `priceVaries()`, `eventFields()`,
 * `AddonResolver::defaultSelection()`— y las que estaban escritas a mano en el Livewire se traen
 * aquí para que dejen de tener dos copias.
 *
 * **Solo lectura y sin estado.** No memoiza entre peticiones: quien lo consuma varias veces en la
 * misma petición debe pedirlo al contenedor una vez (la web ya lo hace).
 *
 * ⚠️ **La fecha de las tarifas de complementos es HOY, no la de la reserva.** No es un descuido de
 * este read-model: es lo que hacen el modelo de vista de la compra (`Purchase::addonViewModel()`) y
 * el cobro real (`OrderCreator`, que resuelve los complementos con `Carbon::today()` mientras
 * resuelve la línea principal con la fecha de la franja). Copiarlo aquí mantiene lo que se muestra
 * igual a lo que se cobra; cambiarlo sería tocar precios, y eso exige decisión del owner
 * (`INVARIANTES` §1).
 */
class CatalogReader implements ProductCatalog
{
    public function __construct(private RateResolver $rates, private AddonResolver $addons) {}

    /** @return list<CatalogZone> */
    public function zones(): array
    {
        return Zone::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->map(fn (Zone $zone): CatalogZone => $this->describeZone($zone))
            ->all();
    }

    /**
     * @param  CatalogProduct::TYPE_*|null  $type
     * @return list<CatalogProduct>
     */
    public function products(?string $type = null): array
    {
        $types = $this->sellableQuery()
            ->when($type !== null, fn ($query) => $query->where('type', $this->modelType($type)))
            ->get();

        return $types->map(fn (TicketType $product): CatalogProduct => $this->describe($product))->all();
    }

    public function product(int $id): ?CatalogProductDetail
    {
        $product = $this->sellableQuery()->whereKey($id)->first();

        if (! $product) {
            return null;
        }

        return new CatalogProductDetail(
            product: $this->describe($product),
            // Misma regla que aplica `OrderCreator` al admitir la línea: el mínimo de invitados de
            // un pack, 1 en una entrada. Un `min_qty` a 0 o nulo no puede bajar de 1.
            minQuantity: $product->isPack() ? max(1, (int) ($product->min_qty ?? 1)) : 1,
            maxQuantity: $product->max_qty !== null ? (int) $product->max_qty : null,
            eventFields: $this->eventFields($product),
            addons: $this->addons($product),
        );
    }

    /**
     * La consulta que DEFINE el catálogo, en un solo sitio: en venta online (`is_sellable`), de zona
     * operativa y seleccionable (entrada o pack).
     *
     * `is_active` («visible en la web») queda fuera a propósito: es un eje independiente de la
     * venta (P3), y un producto oculto de la landing se sigue pudiendo comprar desde el flujo.
     *
     * Los eager loads no son optimización opcional: sin `prices.rateType` el precio «desde» y
     * `priceVaries()` provocan una consulta por producto, y sin `zone` otra más.
     */
    /** @return Builder<TicketType> */
    private function sellableQuery(): Builder
    {
        return TicketType::query()
            ->sellable()
            ->inOperationalZone()
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK])
            ->with(['zone', 'prices.rateType'])
            ->orderBy('position');
    }

    private function describe(TicketType $product): CatalogProduct
    {
        return new CatalogProduct(
            id: (int) $product->id,
            type: $this->contractType($product),
            name: (string) $product->tr('name'),
            // `?:` y no `??`: una traducción vacía es «sin distintivo», igual que la ausencia.
            badge: $product->tr('badge') ?: null,
            features: self::features($product),
            fromPriceCents: $this->fromPriceCents($product),
            priceVaries: $product->priceVaries(),
            depositLabel: $product->depositLabel(),
            periodLabel: $product->tr('period_label') ?: null,
            featured: (bool) $product->featured,
            // El marcador, resuelto por el dominio: la clave elegida en el panel o el respaldo de
            // su tipo. Es la MISMA fuente que usan la cesta y el resumen (`ProductIcon`).
            icon: $product->iconKey(),
            zone: $product->zone ? $this->describeZone($product->zone) : null,
        );
    }

    private function describeZone(Zone $zone): CatalogZone
    {
        return new CatalogZone(
            id: (int) $zone->id,
            slug: (string) $zone->slug,
            name: (string) $zone->tr('name'),
        );
    }

    /**
     * Precio «desde»: el MÍNIMO de los precios configurados. Es el que anuncia hoy el catálogo de la
     * compra; se conserva tal cual para que la web y la API no digan cifras distintas.
     *
     * Deliberadamente NO filtra por tarifa activa. Hacerlo cambiaría el importe anunciado en una
     * instalación que tenga precios colgando de una tarifa desactivada, y tocar lo que se le
     * anuncia al cliente es decisión del owner, no de una extracción (ver `DEUDA.md`).
     */
    private function fromPriceCents(TicketType $product): ?int
    {
        $min = $product->prices->min('amount_cents');

        return $min !== null ? (int) $min : null;
    }

    /**
     * Campos del evento que se piden AL RESERVAR. Solo en packs, porque `eventFields()` devuelve el
     * esquema configurado sin mirar el tipo y una entrada con `event_fields` sueltos —posible a
     * mano en BD— no los pide en ningún flujo.
     *
     * @return list<CatalogEventField>
     */
    private function eventFields(TicketType $product): array
    {
        if (! $product->isPack()) {
            return [];
        }

        return array_map(
            fn (array $field): CatalogEventField => new CatalogEventField(
                key: (string) $field['key'],
                label: $product->eventFieldLabel($field),
                type: (string) $field['type'],
                required: (bool) $field['required'],
            ),
            $product->eventFields(TicketType::EVENT_STAGE_BOOKING),
        );
    }

    /**
     * Complementos OFRECIBLES del producto, en el orden del pivote.
     *
     * Dos reglas que vienen del dominio y no se reescriben aquí:
     *  - un complemento de PAGO sin precio para la tarifa no se ofrece (lo mismo que hace
     *    `AddonResolver::viewModel()`; ofrecerlo terminaría en un checkout rechazado);
     *  - la selección de partida la dicta `AddonResolver::defaultSelection()`.
     *
     * ⚠️ La tarifa del día se resuelve UNA vez y el precio se lee de la relación ya cargada
     * (`priceCentsForRate`), en vez de llamar a `RateResolver::priceCents()` por complemento: ese
     * método hace su propia consulta, así que dentro del bucle costaba dos consultas por
     * complemento. Lo destapó `ApiOverheadTest`, midiendo, no leyendo. El resultado es idéntico —la
     * tarifa aplicable es la misma para todos— y el coste deja de depender de cuántos haya.
     *
     * @return list<CatalogAddon>
     */
    private function addons(TicketType $product): array
    {
        /** @var Collection<int, TicketType> $offered */
        $offered = $product->addons()->with('prices.rateType')->get();

        $default = AddonResolver::defaultSelection($offered);
        $selected = array_flip([...array_values($default['groups']), ...array_keys($default['qty'])]);
        $rate = $this->rates->for(Carbon::today());

        $rows = [];
        foreach ($offered as $addon) {
            $pivot = $addon->pivot;
            $price = $addon->priceCentsForRate($rate);

            if ($price === null && ! $pivot->is_included) {
                continue;
            }

            $rows[] = new CatalogAddon(
                id: (int) $addon->id,
                name: (string) $addon->tr('name'),
                features: self::features($addon),
                priceCents: (int) ($price ?? 0),
                included: (bool) $pivot->is_included,
                mandatory: (bool) $pivot->is_mandatory,
                perGuest: $pivot->isPerGuest(),
                allowExtra: (bool) $pivot->allow_extra,
                includedQuantity: (int) $pivot->included_quantity,
                maxQuantity: $pivot->max_qty !== null ? (int) $pivot->max_qty : null,
                choiceGroup: $pivot->choiceGroup(),
                requiresAddonId: $pivot->requiresAddonId(),
                selectedByDefault: isset($selected[(int) $addon->id]),
            );
        }

        return $rows;
    }

    /**
     * Ventajas normalizadas a lista de cadenas no vacías. Un campo traducible puede llegar como
     * lista o como texto suelto según lo haya guardado el panel, y quien las pinta no debería tener
     * que distinguirlo. Es la misma normalización que `AddonResolver::viewModel()` aplica a las
     * ventajas de los complementos, ahora también para los productos.
     *
     * @return list<string>
     */
    private static function features(TicketType $product): array
    {
        $features = $product->tr('features');

        if (! is_array($features)) {
            $single = trim((string) $features);

            return $single === '' ? [] : [$single];
        }

        return array_values(array_filter(
            array_map(fn (mixed $feature): string => trim((string) $feature), $features),
            fn (string $feature): bool => $feature !== '',
        ));
    }

    /**
     * Tipo del CONTRATO a partir del tipo del modelo. El `match` es exhaustivo a propósito: si
     * mañana aparece un tercer tipo seleccionable, esto revienta en vez de colar por el catálogo un
     * valor que el contrato público no declara.
     *
     * @return CatalogProduct::TYPE_*
     */
    private function contractType(TicketType $product): string
    {
        return match ($product->type) {
            TicketType::TYPE_ENTRY => CatalogProduct::TYPE_ENTRY,
            TicketType::TYPE_PACK => CatalogProduct::TYPE_PACK,
        };
    }

    /** Inverso de `contractType()`: el filtro público traducido al valor del modelo. */
    private function modelType(string $contractType): string
    {
        return match ($contractType) {
            CatalogProduct::TYPE_ENTRY => TicketType::TYPE_ENTRY,
            CatalogProduct::TYPE_PACK => TicketType::TYPE_PACK,
        };
    }
}
