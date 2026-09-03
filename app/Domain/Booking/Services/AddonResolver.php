<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resuelve, de forma AUTORITATIVA en SERVIDOR (regla 12), los complementos de una línea de
 * producto: aplica la config por-enganche del pivote `product_addons` (incluido / obligatorio /
 * por-invitado / grupo excluyente), inyecta lo que el cliente no envió pero el producto exige,
 * valida la exclusividad de los grupos y computa las unidades GRATIS (incluidas) frente a las de
 * pago. Una única fuente de verdad compartida por `OrderCreator` (creación del pedido) y por la
 * previsualización del carrito en la superficie de compra —el componente Livewire hasta 4.7·2b·3,
 * hoy `POST orders/quote` y el cajón SPA— → lo que se MUESTRA coincide con lo que se COBRA, sin que
 * el cliente pueda saltarse un obligatorio ni elegir dos de un grupo.
 *
 * Reglas de precio (céntimos):
 *  - Complemento INCLUIDO: las primeras unidades son gratis (`free_quantity`); si no tiene precio
 *    definido se trata como 0 (es gratis igualmente). Las que excedan lo incluido se cobran a su
 *    precio normal (matriz de tarifas).
 *  - Complemento de PAGO (no incluido): debe tener precio para la tarifa; si no, no es vendible.
 *  - Cobro de la línea = `(quantity − free_quantity) × unit_price` (ver `OrderItem::chargedSubtotalCents`).
 */
class AddonResolver
{
    public function __construct(private RateResolver $rates) {}

    /**
     * @param  TicketType  $product  producto base (con `addons` cargado, incluido el pivote)
     * @param  int  $lineQuantity  cantidad de la línea base (= invitados en un pack; = unidades en una entrada)
     * @param  array<int, array{ticket_type_id:int, qty:int}>  $requested  selección del cliente
     * @return array{rows: array<int, array<string, mixed>>, subtotal: int}
     */
    public function resolve(TicketType $product, int $lineQuantity, array $requested, CarbonInterface $date): array
    {
        /** @var Collection<int, TicketType> $offered Complementos ofrecibles (vendibles+activos) por id, con pivote. */
        $offered = $product->addons->keyBy('id');

        // Cantidad pedida por el cliente, saneada (id => qty>0), SOLO de los ofrecibles (defensa).
        $wanted = [];
        foreach ($requested as $r) {
            $id = (int) ($r['ticket_type_id'] ?? 0);
            $qty = (int) ($r['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            if (! $offered->has($id)) {
                throw new ReservationException('tickets.errors.unavailable');
            }
            $wanted[$id] = ($wanted[$id] ?? 0) + $qty;
        }

        // Mapa grupo → [ids de sus miembros] (en orden de pivote).
        $groups = [];
        foreach ($offered as $id => $addon) {
            $group = $addon->pivot->choiceGroup();
            if ($group !== null) {
                $groups[$group][] = (int) $id;
            }
        }

        // 1) Grupos excluyentes: exactamente UNO por grupo (el pedido, o el default si no se pidió ninguno).
        $selected = [];   // id => qty pedida (la efectiva se recalcula luego)
        $inGroup = [];    // ids ya resueltos por un grupo
        foreach ($groups as $memberIds) {
            $chosen = array_values(array_filter($memberIds, fn ($id) => isset($wanted[$id])));
            if (count($chosen) > 1) {
                // Dos del mismo grupo: la UI lo impide (radio); aquí es defensa server-side.
                throw new ReservationException('tickets.errors.unavailable');
            }
            foreach ($memberIds as $id) {
                $inGroup[$id] = true;
            }
            $pick = $chosen[0] ?? $this->groupDefault($offered, $memberIds);
            if ($pick !== null) {
                $selected[$pick] = $wanted[$pick] ?? 0;
            }
        }

        // 2) Complementos sueltos: los pedidos, y los OBLIGATORIOS aunque no se pidan (se forzarán al mínimo).
        foreach ($offered as $id => $addon) {
            $id = (int) $id;
            if (isset($inGroup[$id])) {
                continue;
            }
            if (isset($wanted[$id])) {
                $selected[$id] = $wanted[$id];
            } elseif ($addon->pivot->is_mandatory) {
                $selected[$id] = 0;
            }
        }

        // 2b) Dependencias «requiere» (data-driven): un complemento solo es vendible si su requisito
        // está también seleccionado. Poda a punto fijo (cadenas A→B→C). AUTORIDAD DE SERVIDOR: ni una
        // cesta forjada puede comprar el dependiente sin su requisito (espeja la oferta de `viewModel`).
        $selected = self::pruneUnmetDependencies($selected, $offered);

        // 3) Filas autoritativas (en orden de pivote, para una visualización estable).
        $rows = [];
        $subtotal = 0;
        $occupyingQuantity = 0; // Σ de entradas que SE QUEDAN (hora extra, `specs/hora-extra.md` §4.4·5)
        foreach ($offered as $id => $addon) {
            $id = (int) $id;
            if (! array_key_exists($id, $selected)) {
                continue;
            }
            $pivot = $addon->pivot;

            $qty = self::effectiveQuantity($pivot, (int) $selected[$id], $lineQuantity);
            if ($qty <= 0) {
                continue;
            }

            // La HORA EXTRA (`specs/hora-extra.md`): un complemento que OCUPA lleva sus plazas en la
            // fila y tres CINTURONES delante — configuraciones que el pivote y el guard del modelo
            // ya impiden, re-validadas aquí porque esta es la autoridad del cobro (regla 12) y una
            // fila torcida por la puerta de atrás no puede ni venderse ni degradar a neutro:
            $occupies = $addon->occupiesAfterParent();
            if ($occupies) {
                // D2 (§7): en un pack «todos se quedan» es que la fiesta DURA MÁS — otro mecanismo.
                // Y un ocupante colgado de un pack sería invisible para `max_guests_per_slot`.
                if ($product->isPack()) {
                    throw new ReservationException('tickets.errors.unavailable');
                }
                // §4.1: «ocupa y no dice cuánto» — para `occupancyMap` sería «hasta el cierre».
                if (! $addon->hasSaneOccupancyConfig()) {
                    throw new ReservationException('tickets.errors.unavailable');
                }
                // §4.4·5: `per_guest` impondría la hora extra a TODO el grupo (lo contrario del
                // encargo) y `is_mandatory` la auto-inyectaría — «no se ofrece» pasaría a ser
                // «no se puede vender el padre a esa hora» (`AFORO-02` por otra puerta).
                if ($pivot->isPerGuest() || $pivot->is_mandatory) {
                    throw new ReservationException('tickets.errors.unavailable');
                }

                $occupyingQuantity += $qty;
            }

            $free = self::freeUnits($pivot, $qty);

            $price = $this->rates->priceCents($addon, $date);
            if ($pivot->is_included) {
                $unit = $price === null ? 0 : (int) $price; // incluido sin precio = gratis
            } else {
                if ($price === null) {
                    throw new ReservationException('tickets.errors.unavailable');
                }
                $unit = (int) $price;
            }

            $rows[] = [
                'ticket_type_id' => $id,
                // La FRANJA de una hija que ocupa la pone `OrderCreator` bajo el lock (la regla del
                // borde 8 vive en `AddonOccupancy`); aquí solo las PLAZAS, que no dependen de ella.
                'slot_id' => null,
                'quantity' => $qty,
                'free_quantity' => $free,
                'unit_price' => $unit,
                'seats' => $occupies ? AddonOccupancy::seats($addon, $qty) : 0,
                'event_data' => null,
            ];
            $subtotal += max(0, $qty - $free) * $unit;
        }

        // §4.4·5, la mitad que `effectiveQuantity()` NO puede imponer (resuelve UN complemento cada
        // vez y no ve a los hermanos): no pueden QUEDARSE más de los que ENTRAN. Con «1 hora extra»
        // ×3 y «2 horas extra» ×3 sobre un padre de 4, cada uno pasa por separado y se quedan 6 de 4
        // — se cobraría un imposible físico y se ocuparían plazas fantasma. La SUMA se comprueba
        // aquí, el único punto que ve todas las filas, compartido por presupuesto y cobro.
        if ($occupyingQuantity > $lineQuantity) {
            throw ReservationException::withContext('tickets.errors.addon_over_line', [
                'staying' => $occupyingQuantity,
                'entering' => $lineQuantity,
            ]);
        }

        return ['rows' => $rows, 'subtotal' => $subtotal];
    }

    /**
     * Default de un grupo excluyente: el complemento INCLUIDO (gratis) o, si ninguno lo es, el
     * primero por posición. Así el grupo nunca queda vacío (la elección es implícitamente obligatoria).
     *
     * @param  Collection<int, TicketType>  $offered
     * @param  array<int, int>  $memberIds
     */
    private function groupDefault(Collection $offered, array $memberIds): ?int
    {
        $first = null;
        foreach ($memberIds as $id) {
            $addon = $offered->get($id);
            if (! $addon) {
                continue;
            }
            if ($first === null) {
                $first = (int) $id;
            }
            if ($addon->pivot->is_included) {
                return (int) $id;
            }
        }

        return $first;
    }

    /**
     * Cantidad EFECTIVA de un complemento:
     *  - `per_guest` → nº de invitados de la línea (no la toca el cliente).
     *  - `fixed`     → la pedida, nunca por debajo del mínimo obligatorio (lo incluido si es
     *                  obligatorio) y, si no admite extras, nunca por encima de lo incluido.
     */
    public static function effectiveQuantity(ProductAddon $pivot, int $requestedQty, int $lineQuantity): int
    {
        if ($pivot->isPerGuest()) {
            return max(0, $lineQuantity);
        }

        $qty = max(0, $requestedQty);
        $min = $pivot->is_mandatory ? max(1, (int) $pivot->included_quantity) : 0;
        if ($qty < $min) {
            $qty = $min;
        }
        if ($pivot->is_included && ! $pivot->allow_extra) {
            $qty = min($qty, max(1, (int) $pivot->included_quantity));
        }
        // P9: tope máximo por complemento (data-driven). Nunca por debajo del mínimo obligatorio.
        if ($pivot->max_qty !== null) {
            $qty = min($qty, max($min, (int) $pivot->max_qty));
        }

        return $qty;
    }

    /** Unidades GRATIS de una cantidad efectiva (las INCLUIDAS): per_guest → todas; fija → lo incluido. */
    public static function freeUnits(ProductAddon $pivot, int $effectiveQty): int
    {
        if (! $pivot->is_included) {
            return 0;
        }
        if ($pivot->isPerGuest()) {
            return max(0, $effectiveQty);
        }

        return min((int) $pivot->included_quantity, max(0, $effectiveQty));
    }

    // ─── API de SELECCIÓN/VISTA compartida por la compra pública y el panel ────────────────────
    // (la compra pública —vía la API—, el alta manual y, donde aplica, el modal "Gestionar"
    //  delegan aquí para que la lógica de incluido/obligatorio/per-invitado/grupo NO derive.)

    /**
     * Selección por defecto de los complementos de un producto al elegirlo: el default de cada
     * grupo excluyente (el incluido, o el primero por orden) y los obligatorios sueltos a su mínimo.
     *
     * @param  Collection<int, TicketType>  $offered  complementos del producto (con pivote cargado)
     * @return array{qty: array<int,int>, groups: array<string,int>}
     */
    public static function defaultSelection(Collection $offered): array
    {
        $qty = [];
        $groups = [];
        foreach ($offered as $addon) {
            $pivot = $addon->pivot;
            $group = $pivot->choiceGroup();
            if ($group !== null) {
                // Default del grupo: el primero por orden, salvo que un miembro sea el incluido.
                if (! isset($groups[$group]) || $pivot->is_included) {
                    $groups[$group] = (int) $addon->id;
                }
            } elseif ($pivot->is_mandatory && ! $pivot->isPerGuest()) {
                $qty[(int) $addon->id] = max(1, (int) $pivot->included_quantity);
            }
        }

        return ['qty' => $qty, 'groups' => $groups];
    }

    /**
     * Estado de selección de un complemento dado el estado de la UI (cantidades + elección de
     * grupo): ¿está activo y con qué cantidad efectiva? Espeja `resolve()` para que lo MOSTRADO
     * coincida con lo que se COBRARÁ.
     *
     * @param  array<int,int>  $qtyMap  addonId → cantidad pedida (complementos de cantidad libre)
     * @param  array<string,int>  $groupChoices  grupo → addonId elegido
     * @return array{0:bool, 1:int} [seleccionado, cantidad efectiva]
     */
    public static function selectionState(ProductAddon $pivot, int $addonId, array $qtyMap, array $groupChoices, int $guests): array
    {
        $group = $pivot->choiceGroup();
        if ($group !== null) {
            if ((int) ($groupChoices[$group] ?? 0) !== $addonId) {
                return [false, 0];
            }
            $qty = $pivot->isPerGuest() ? $guests : max(1, (int) ($qtyMap[$addonId] ?? max(1, (int) $pivot->included_quantity)));

            return [true, $qty];
        }

        if ($pivot->is_mandatory) {
            $qty = $pivot->isPerGuest() ? $guests : max(1, (int) ($qtyMap[$addonId] ?? (int) $pivot->included_quantity));

            return [true, $qty];
        }

        $userQty = (int) ($qtyMap[$addonId] ?? 0);
        if ($pivot->isPerGuest()) {
            return $userQty > 0 ? [true, $guests] : [false, 0];
        }

        return $userQty > 0 ? [true, $userQty] : [false, 0];
    }

    /**
     * Poda a PUNTO FIJO los complementos seleccionados cuyo requisito («requiere») NO está también
     * seleccionado. Cubre cadenas (A→B→C): si C cae, B se queda sin su requisito y también cae, etc.
     * Autoridad compartida por la OFERTA (viewModel/buildSelection/resolveSelectedIds) y el COBRO
     * (resolve): ni una petición forjada puede colar un dependiente sin su requisito.
     *
     * @param  array<int,mixed>  $selected  ids seleccionados como CLAVES (el valor se conserva tal cual)
     * @param  Collection<int, TicketType>  $offered  complementos ofrecibles (con pivote)
     * @return array<int,mixed> el mismo mapa sin los dependientes huérfanos
     */
    private static function pruneUnmetDependencies(array $selected, Collection $offered): array
    {
        $byId = $offered->keyBy('id');
        do {
            $changed = false;
            foreach (array_keys($selected) as $id) {
                $required = $byId->get($id)?->pivot->requiresAddonId();
                if ($required !== null && ! array_key_exists($required, $selected)) {
                    unset($selected[$id]);
                    $changed = true;
                }
            }
        } while ($changed);

        return $selected;
    }

    /**
     * Conjunto de ids de complementos SELECCIONADOS dado el estado de la UI, ya con la poda de
     * dependencias «requiere» aplicada (punto fijo). Fuente única de «qué está activo» para el
     * view-model y para `buildSelection`, de modo que lo MOSTRADO coincide con lo que se anida y cobra.
     *
     * @param  Collection<int, TicketType>  $offered
     * @param  array<int,int>  $qtyMap
     * @param  array<string,int>  $groupChoices
     * @return array<int,int> ids seleccionados (lista)
     */
    public static function resolveSelectedIds(Collection $offered, array $qtyMap, array $groupChoices, int $guests): array
    {
        $selected = [];
        foreach ($offered as $addon) {
            [$sel] = self::selectionState($addon->pivot, (int) $addon->id, $qtyMap, $groupChoices, $guests);
            if ($sel) {
                $selected[(int) $addon->id] = (int) $addon->id;
            }
        }

        return array_values(self::pruneUnmetDependencies($selected, $offered));
    }

    /**
     * Selección de complementos para anidar en la línea (la INTENCIÓN del usuario; `resolve()` la
     * re-valida/completa en servidor). Respeta la dependencia «requiere»: un dependiente cuyo
     * requisito no esté elegido NO se anida (la fuente única `resolveSelectedIds`).
     *
     * @param  Collection<int, TicketType>  $offered
     * @param  array<int,int>  $qtyMap
     * @param  array<string,int>  $groupChoices
     * @return array<int, array{ticket_type_id:int, qty:int}>
     */
    public static function buildSelection(Collection $offered, array $qtyMap, array $groupChoices, int $guests): array
    {
        $selectedIds = array_flip(self::resolveSelectedIds($offered, $qtyMap, $groupChoices, $guests));

        $out = [];
        foreach ($offered as $addon) {
            $id = (int) $addon->id;
            if (! isset($selectedIds[$id])) {
                continue;
            }
            [$selected, $qty] = self::selectionState($addon->pivot, $id, $qtyMap, $groupChoices, $guests);
            if ($selected && $qty > 0) {
                $out[] = ['ticket_type_id' => $id, 'qty' => $qty];
            }
        }

        return $out;
    }

    /**
     * Modelo de vista de los complementos: grupos excluyentes (radio "elige uno") + complementos
     * sueltos, cada uno con su etiqueta (INCLUIDO/GRATIS), cantidad, unidades gratis, importe a
     * cobrar, nota de precio i18n y "ventajas" (features) para el desplegable "Más info". Incluye el
     * total de los seleccionados. Compartido por la compra pública y el panel (crear pedido).
     *
     * @param  Collection<int, TicketType>  $offered
     * @param  array<int,int>  $qtyMap
     * @param  array<string,int>  $groupChoices
     * @return array{groups: array<int, array<string, mixed>>, singles: array<int, array<string, mixed>>, total: int}
     */
    public function viewModel(Collection $offered, array $qtyMap, array $groupChoices, int $guests, bool $isPack, CarbonInterface $date): array
    {
        $groups = [];
        $singles = [];
        $total = 0;

        // Selección AUTORITATIVA tras podar dependencias «requiere» (lo mostrado = lo que se cobra) +
        // tabla de nombres para la nota «Requiere: X» de los complementos dependientes no disponibles.
        $selectedIds = array_flip(self::resolveSelectedIds($offered, $qtyMap, $groupChoices, $guests));
        $nameById = $offered->mapWithKeys(fn (TicketType $a): array => [(int) $a->id => $a->tr('name')])->all();

        foreach ($offered as $addon) {
            $pivot = $addon->pivot;
            $id = (int) $addon->id;
            // La HORA EXTRA (`specs/hora-extra.md` §4.1): un ocupante declarado con configuración
            // rota, o colgado de un pack (§7·D2), NO SE OFRECE — el mismo cinturón que `resolve()`
            // aplica al cobro, aquí fallando hacia invisible (ofrecerlo terminaría en rechazo).
            if ($addon->occupiesAfterParent() && (! $addon->hasSaneOccupancyConfig() || $isPack)) {
                continue;
            }
            // "Sin precio" (null = no vendible esa tarifa) ≠ "0 € explícito" (gratis): un complemento
            // DE PAGO sin precio NO se ofrece (el checkout lo rechazaría igual); un incluido sí es gratis.
            $rawRate = $this->rates->priceCents($addon, $date);
            if ($rawRate === null && ! $pivot->is_included) {
                continue;
            }
            $rate = (int) ($rawRate ?? 0);
            [, $qty] = self::selectionState($pivot, $id, $qtyMap, $groupChoices, $guests);
            // Dependencia «requiere»: disponible solo si su requisito está seleccionado; la selección
            // efectiva la dicta el conjunto ya podado (un dependiente huérfano queda NO seleccionado).
            $requiredId = $pivot->requiresAddonId();
            $available = $requiredId === null || isset($selectedIds[$requiredId]);
            $selected = isset($selectedIds[$id]);
            $free = self::freeUnits($pivot, $qty);
            $charged = max(0, $qty - $free) * $rate;
            if ($selected) {
                $total += $charged;
            }

            $badge = null;
            if ($pivot->is_included) {
                $badge = $isPack ? 'included' : 'free';
            } elseif ($rawRate !== null && $rate === 0) {
                $badge = 'free';
            }

            $features = $addon->tr('features');
            $features = is_array($features)
                ? array_values(array_filter(array_map(fn ($f) => trim((string) $f), $features), fn ($f) => $f !== ''))
                : [];

            $min = ($pivot->is_mandatory && ! $pivot->isPerGuest()) ? max(1, (int) $pivot->included_quantity) : 0;

            $priceStr = number_format($rate / 100, 2, ',', '.').' €';
            if ($pivot->is_included) {
                $note = ($pivot->allow_extra && ! $pivot->isPerGuest() && $rate > 0)
                    ? __('tickets.addon_included_extra', ['price' => $priceStr])
                    : __('tickets.addon_included');
            } elseif ($addon->occupiesAfterParent()) {
                // La HORA EXTRA (ojo del owner, `specs/hora-extra.md` §8.6): la cantidad son
                // ENTRADAS que se quedan, y la nota lo dice SIEMPRE — y con la fila elegida dice
                // para CUÁNTAS («Hora extra · Para 2 entradas que se quedan»), que es la lectura
                // que él pidió. Se compone aquí y no en el cliente: la heredan el cajón, la API y
                // el alta manual del panel sin una línea suya, y se re-computa en cada clic.
                $note = $qty > 0
                    ? trans_choice('tickets.addon_stay_selected', $qty, ['count' => $qty, 'price' => $priceStr])
                    : __('tickets.addon_stay_price', ['price' => $priceStr]);
            } elseif ($pivot->isPerGuest()) {
                $note = __('tickets.addon_per_unit', ['price' => $priceStr]);
            } else {
                $note = $priceStr;
            }

            $row = [
                'id' => $id,
                'name' => $addon->tr('name'),
                'price' => $rate,
                'note' => $note,
                'is_included' => (bool) $pivot->is_included,
                'is_mandatory' => (bool) $pivot->is_mandatory,
                'per_guest' => $pivot->isPerGuest(),
                'allow_extra' => (bool) $pivot->allow_extra,
                // per-invitado opcional (no grupo, no obligatorio, no incluido) → se activa con
                // CHECKBOX (la cantidad la fija el aforo, no el cliente). El resto (libre) usa stepper.
                'can_toggle' => $available && $pivot->isPerGuest() && $pivot->choiceGroup() === null && ! $pivot->is_mandatory && ! $pivot->is_included,
                'badge' => $badge,
                'features' => $features,
                'selected' => $selected,
                // Dependencia «requiere»: si el requisito no está elegido, el complemento se muestra
                // DESHABILITADO con la nota «Requiere: X» (los controles `can_*` quedan en false).
                'available' => $available,
                'requires_name' => $available ? null : ($requiredId !== null ? ($nameById[$requiredId] ?? null) : null),
                'qty' => $qty,
                'free' => $free,
                'charged' => $charged,
                'min' => $min,
                'max' => $pivot->max_qty !== null ? (int) $pivot->max_qty : null,
                'can_dec' => $available && $selected && ! $pivot->isPerGuest() && $pivot->choiceGroup() === null && $qty > $min,
                'can_inc' => $available && ! $pivot->isPerGuest() && $pivot->choiceGroup() === null
                    && ! ($pivot->is_included && ! $pivot->allow_extra) && $qty < 20
                    && ($pivot->max_qty === null || $qty < (int) $pivot->max_qty),
            ];

            $group = $pivot->choiceGroup();
            if ($group !== null) {
                $groups[$group] ??= ['key' => $group, 'label' => __('tickets.addon_choose_one'), 'options' => []];
                $groups[$group]['options'][] = $row;
            } else {
                $singles[] = $row;
            }
        }

        return ['groups' => array_values($groups), 'singles' => $singles, 'total' => $total];
    }
}
