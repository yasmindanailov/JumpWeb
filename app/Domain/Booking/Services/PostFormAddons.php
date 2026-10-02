<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\PostFormAddonView;
use App\Domain\Booking\Contracts\PostFormChoiceGroupView;
use App\Domain\Booking\Models\AddonChoiceGroup;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Money;
use App\Notifications\PostFormAddonsChanged;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * **Los complementos que el cliente añade DESPUÉS de reservar** (`specs/complementos-post-reserva.md`
 * §4.5, T2 de `DECISIONES #413`).
 *
 * Reconcilia las líneas hijas de venta posterior de una reserva con el estado que el cliente pide en
 * su post-form. Calcado de {@see MixedPartySurcharge}, que resolvió el mismo problema desde el mismo
 * formulario: reconciliar, no acumular.
 *
 * ## La propiedad que lo gobierna todo, y por qué la puerta es un HECHO
 *
 * Una línea creada DESPUÉS del pedido lleva un ajuste `edit` de su importe exacto, así que
 * `LineFacts::birthValue()` vale 0: **no aportó ni un céntimo al cobro online**, y quitarla es
 * neutro en dinero. Por eso **R0 no mira el eje del catálogo, mira la fila**: el eje es
 * configuración MUTABLE —pasar «Tarta» de `booking` a `postform` es lo primero que el parque hará—
 * y con él como puerta el cliente habría podido retirar líneas ya cobradas, **con el libro cerrando
 * en verde**. El hecho no envejece con el catálogo.
 *
 * ## Las TRES escrituras, que NO son simétricas
 *
 * | gesto | qué se escribe | por qué |
 * |---|---|---|
 * | **alta** | línea nueva + `recordEdit(+cobrado)` | nace con `nac = 0` |
 * | **subir / bajar** | `quantity` + `recordEdit(±Δ)` | sin el movimiento, `nac` se desplaza y `I1` falla |
 * | **retirar** | `markCancelled()` y **NINGÚN** `recordEdit` | el libro ya emite su `−fila`; escribirlo restaría DOS veces |
 *
 * ⚠️⚠️ Las dos roturas tienen la misma consecuencia: el pedido entero pasa a «en revisión» y **al
 * cliente se le oculta su desglose** (`#132`) por haber tocado un cubo de refrescos. `chargedSubtotalCents()`
 * **no mira `cancelled_at`**, que es lo que hace que la retirada no necesite hecho propio.
 *
 * ## El orden de LOCKS es una regla del subsistema, no un detalle
 *
 * **`orders` → `order_items` → hijas.** La primera versión de la spec afirmaba que no había inversión
 * posible; la revisión adversarial **reprodujo el interbloqueo** (`SQLSTATE[40001]`) con dos
 * conexiones reales: `OrderItemCanceller::cancel()`, las dos transacciones de
 * `Order::executePartialRefund()` y la acción «Cancelar pedido» toman `orders` ANTES que el ítem.
 * Tomar aquí el pedido como PRIMERA sentencia elimina la inversión — y el `recordEdit` anidado, que
 * es un SAVEPOINT y retiene su lock hasta el commit exterior, ya no adquiere nada nuevo.
 * ⚠️ Coste asumido y dicho: dos reservas del mismo pedido no guardan su post-form a la vez. Es
 * contención, no incorrección.
 *
 * ## Lo que se re-valida BAJO el lock (`SEC-04` aplicado al tiempo)
 *
 * Entre pintar el formulario y guardarlo puede pasar la fiesta, una cancelación o el plazo de un
 * complemento. Todo se re-comprueba dentro: elegibilidad, cancelación, franja terminada y el corte
 * de CADA complemento.
 */
final class PostFormAddons
{
    public function __construct(private RateResolver $rates) {}

    /**
     * Deja las líneas de venta posterior de esta reserva igual a lo que el cliente pide.
     *
     * @param  array<int,int>  $desired  addonId → cantidad deseada (0 = quitarlo)
     * @param  string  $via  por dónde entró quien guarda (`signed_link` | `account` | `panel`)
     * @param  User|null  $by  el OPERADOR cuando guarda el panel; `null` = el propio titular
     * @param  string|null  $expectedVersion  el TOKEN OPTIMISTA que el cliente vio ({@see versionOf});
     *                                        `null` = sin comprobar (el llamante decide si lo exige)
     */
    public function reconcile(OrderItem $principal, array $desired, string $via, ?User $by = null, ?string $expectedVersion = null): PostFormAddonChanges
    {
        $order = $principal->order;
        if ($order === null) {
            return new PostFormAddonChanges;
        }

        $changes = DB::transaction(function () use ($principal, $order, $desired, $by, $expectedVersion): PostFormAddonChanges {
            // D11 · el PEDIDO primero, y el ítem después. Ver el docblock de la clase: es la regla del
            // subsistema, y el interbloqueo que evita está REPRODUCIDO, no supuesto.
            Order::query()->lockForUpdate()->find($order->getKey());

            /** @var OrderItem|null $item */
            $item = OrderItem::query()
                ->with(['ticketType.addons', 'slot', 'order', 'children'])
                ->whereKey($principal->getKey())
                ->lockForUpdate()
                ->first();

            // `SEC-04` aplicado al tiempo: lo que valía al pintar puede no valer al guardar.
            if ($item === null || ! $item->acceptsGuestForm() || $item->isFinishedInPractice()) {
                return new PostFormAddonChanges(blocked: $this->blockAll($desired, 'closed'));
            }

            // El TOKEN OPTIMISTA (borde 5 de §4.8): el lock SERIALIZA las dos escrituras, pero **no
            // decide cuál gana**. Sin esto, el operador sube «Tarta» a 3 por teléfono, el cliente
            // guarda con la pantalla cargada antes y su estado deseado la baja a 1 — borrando el
            // trabajo del operador en silencio y con un movimiento de dinero. Es la única superficie
            // de dinero del producto sin token, y va a mover dinero.
            if ($expectedVersion !== null && $expectedVersion !== self::versionOf($item)) {
                return new PostFormAddonChanges(blocked: $this->blockAll($desired, 'stale'));
            }

            return $this->apply($item, $desired, $by);
        });

        if ($changes->changed()) {
            // `RGPD-02`: ni un nombre ni una alergia — qué reserva, por dónde entró y cuánto se movió.
            AuditLogger::log('orders.postform_addons_changed', $order, [
                'order_code' => $order->code,
                'order_item_id' => $principal->getKey(),
                'via' => $via,
                'delta_cents' => $changes->deltaCents,
                'moves' => array_map(
                    static fn (array $m): array => ['addon_id' => $m['addon_id'], 'from' => $m['from'], 'to' => $m['to']],
                    $changes->moves,
                ),
            ]);

            // D8 · UN correo por ventana, con las tres líneas dentro, y **fuera de la transacción**:
            // por `notifyCustomer` —la puerta que ya comprueba que el titular tiene correo, porque
            // una cuenta anonimizada (`RGPD-01`) no lo tiene— y no por `$user->notify()`.
            //
            // ⚠️ Va bajo `changed()`, igual que el suplemento mixto: este formulario se edita
            // durante días y un guardado que no mueve nada no puede mandar correo.
            $order->notifyCustomer(new PostFormAddonsChanged(
                $principal->fresh(['ticketType', 'slot', 'order']) ?? $principal,
                $changes,
            ));
        }

        return $changes;
    }

    /**
     * El TOKEN OPTIMISTA de una reserva: la misma convención que las CINCO puertas del operador
     * (`OrderItemEditor`, `OrderItemCanceller`, `OrderItemRefunder`, `OrderItemGuestDataWriter`,
     * `OrderItemEventDataWriter`), para que cliente y panel hablen del mismo instante.
     */
    public static function versionOf(OrderItem $item): string
    {
        return (string) ($item->updated_at?->getTimestamp() ?? '');
    }

    /**
     * Los complementos que el cliente PUEDE mover ahora mismo en esta reserva: los de fase
     * `postform` con configuración sana ({@see AddonResolver::forStage}) **y dentro de su plazo**.
     *
     * ⚠️⚠️ **«Fuera de plazo» NO cuenta como ofrecido, y de ahí sale la regla que evita una trampa
     * medida**: los `<input disabled>` **no se envían**, así que con plazos distintos por complemento
     * («tapas 48 h», «cubo 2 h») un guardado normal llegaría sin las tapas — y si el estado deseado
     * gobernase todo lo ofrecido, **se cancelarían solas**, justo lo contrario de D5. Conservar lo
     * que está fuera de plazo es lo que hace que ese guardado sea inofensivo.
     *
     * @return Collection<int, TicketType> por id de complemento
     */
    public function offerableFor(OrderItem $principal): Collection
    {
        $type = $principal->ticketType;
        if ($type === null || ! $principal->acceptsGuestForm() || $principal->isFinishedInPractice()) {
            return collect();
        }

        return $this->forThisSale($principal, AddonResolver::forStage($type->addons, ProductAddon::STAGE_POSTFORM))
            ->filter(function (TicketType $addon) use ($principal): bool {
                $pivot = $addon->addonPivot();

                return $pivot !== null && self::isWithinWindow($principal, $pivot);
            })
            ->keyBy('id');
    }

    /**
     * Los GRUPOS DE OPCIONES de esta reserva (`[DECIDIDO owner]` `#914`): «¿Qué merienda?», con sus opciones, si hay que
     * elegir y cuál está elegida. Lo que leen la lista («Falta elegir…»), el parque («sin elegir») y la API.
     *
     * @param  list<PostFormAddonView>|null  $rows  las de {@see viewFor()} si ya se tienen (la lista las pinta igual)
     * @return list<PostFormChoiceGroupView>
     */
    public function choiceGroupsFor(OrderItem $principal, ?array $rows = null): array
    {
        $rows ??= $this->viewFor($principal);
        $members = [];
        foreach ($rows as $row) {
            if ($row->group !== null) {
                $members[$row->group][] = $row;
            }
        }
        if ($members === []) {
            return [];
        }

        $groups = [];
        foreach ($this->groupsOf($principal) as $key => $group) {
            $options = $members[$key] ?? [];
            if ($options === []) {
                continue;
            }
            $chosen = collect($options)->first(fn (PostFormAddonView $o): bool => $o->quantity > 0);
            $groups[] = new PostFormChoiceGroupView(
                key: $key,
                title: trim((string) ($group->tr('title') ?? '')),
                required: $group->is_required,
                chosenAddonId: $chosen?->productId,
                addonIds: array_map(static fn (PostFormAddonView $o): int => $o->productId, $options),
                open: collect($options)->contains(fn (PostFormAddonView $o): bool => ! $o->closed),
            );
        }

        return $groups;
    }

    /**
     * Fuera las opciones de un grupo que esta reserva NO debe (`[DECIDIDO owner]` `#914`, «No se les pide»): el grupo se
     * creó DESPUÉS de venderla, así que lo vendido se rige por la configuración con la que se vendió —la merienda de antes
     * se eligió con el Menú al reservar— y lo lleva el parque. Sin opciones de grupo, ni una consulta.
     *
     * @param  Collection<int, TicketType>  $addons
     * @return Collection<int, TicketType>
     */
    private function forThisSale(OrderItem $principal, Collection $addons): Collection
    {
        if (! $addons->contains(fn (TicketType $addon): bool => $addon->addonPivot()?->choiceGroup() !== null)) {
            return $addons;
        }

        $groups = $this->groupsOf($principal);
        $soldAt = $principal->order?->created_at;

        return $addons->filter(function (TicketType $addon) use ($groups, $soldAt): bool {
            $key = $addon->addonPivot()?->choiceGroup();

            return $key === null || ($groups->get($key)?->appliesToSaleAt($soldAt) ?? false);
        })->values();
    }

    /** @return Collection<string, AddonChoiceGroup> los grupos del producto de la reserva, por clave (una consulta) */
    private function groupsOf(OrderItem $principal): Collection
    {
        $type = $principal->ticketType;

        return $type === null ? collect() : $type->loadMissing('choiceGroups')->choiceGroups->keyBy('key');
    }

    /**
     * Los extras de venta posterior de esta reserva **tal y como se le enseñan al cliente**: los
     * abiertos con su cantidad, y los CERRADOS con su motivo (§4.7·bis).
     *
     * ⚠️ Enseña también los cerrados a propósito. Ocultarlos sería el modo de fallo de esta feature:
     * quien pidió tapas hace dos semanas tiene que seguir viéndolas —con su «ya no se puede
     * cambiar»— o creería que se han perdido. Y quien compró la tarta al reservar tiene que ver por
     * qué ese botón no está.
     *
     * @return list<PostFormAddonView>
     */
    public function viewFor(OrderItem $principal): array
    {
        $type = $principal->ticketType;
        if ($type === null || ! $principal->acceptsGuestForm()) {
            return [];
        }

        // ⚠️ La fiesta pasada CIERRA las filas, NO las esconde. Es la regla de las tres puertas de
        // §4.6 aplicada a la LECTURA: manda la más estricta, y «la más estricta» es un cierre. Con un
        // `return []` aquí, quien encargó dos cubos de refrescos abría su formulario al día siguiente
        // y **no encontraba ni rastro de ellos** —los mismos extras que se le van a cobrar en el
        // parque—, y la rama `readonly` de la plantilla, escrita justo para eso, era código MUERTO.
        //
        // ⚠️⚠️ **Y el cierre NO necesita término propio para «ya se celebró»**: el plazo se mide
        // contra el INICIO de la franja, así que una fiesta terminada venció su corte por
        // construcción —para cualquier valor, `0` incluido—. Se escribió con un `|| $finished`
        // delante y **ninguna mutación podía distinguirlo**: era una condición que no decide nada.
        // *Un cinturón que ningún caso puede separar de su hebilla no es un cinturón, es ruido.*

        $order = $principal->order;
        $order?->loadMissing('adjustments');
        // Los regalos de cada complemento (`#770`) en una consulta, no una por fila.
        $type->loadMissing('addons.giftPromotions');

        $lines = [];
        foreach ($principal->children as $child) {
            if (! $child->isCancelled()) {
                $lines[(int) $child->ticket_type_id] = $child;
            }
        }

        $offered = $this->forThisSale($principal, AddonResolver::forStage($type->addons, ProductAddon::STAGE_POSTFORM));

        // Un GRUPO con una opción comprada AL RESERVAR se cierra entero (`#914`): esa línea es del parque (R0), y dejar
        // elegir otra opción dejaría dos vivas en un grupo de «elige una».
        $frozenGroups = [];
        foreach ($offered as $addon) {
            $line = $lines[(int) $addon->getKey()] ?? null;
            $key = $addon->addonPivot()?->choiceGroup();
            if ($key !== null && $line !== null && $order !== null && LineFacts::forItem($order, $line)->birthValue() !== 0) {
                $frozenGroups[$key] = true;
            }
        }

        $rows = [];
        foreach ($offered as $addon) {
            $pivot = $addon->addonPivot();
            if ($pivot === null) {
                continue; // inalcanzable: `forStage()` solo deja pasar enganches con su pivote
            }
            $id = (int) $addon->getKey();
            $line = $lines[$id] ?? null;
            $group = $pivot->choiceGroup();
            $bornWithOrder = ($line !== null && $order !== null && LineFacts::forItem($order, $line)->birthValue() !== 0)
                || ($group !== null && isset($frozenGroups[$group]));

            // R2 en la lectura: si ya hay línea manda SU precio, que es el comunicado. Sin línea,
            // el del catálogo **del día de la VISITA** (`#415`) — y si ese día no tiene tarifa, el
            // extra no se puede ofrecer (salvo INCLUIDO, que sin tarifa es gratis: {@see unitFor}).
            $unit = $this->unitFor($principal, $addon, $pivot, $line);
            if ($unit === null) {
                continue;
            }

            $withinWindow = self::isWithinWindow($principal, $pivot);
            $quantity = $line !== null ? (int) $line->quantity : 0;
            $included = (bool) $pivot->is_included;

            $rows[] = new PostFormAddonView(
                productId: $id,
                productName: (string) $addon->tr('name'),
                unitPriceCents: $unit,
                // El importe se formatea con el servicio del dominio y no a mano: el
                // `number_format(...).' €'` quemado del embudo tiene ficha propia en `DEUDA.md`, y
                // una superficie nueva no puede nacer heredándolo. Una INCLUIDA dice «Incluido», no su tarifa (`#914`).
                note: $included ? __('guestform.extras_included') : Money::format($unit, $principal->order?->currency ?? 'EUR'),
                // El «Más info» del catálogo (`#416`), traducido y saneado a lista de textos: es el
                // mismo dato que la landing enseña, y aquí decide una compra.
                features: array_values(array_filter(array_map(
                    static fn ($f): string => trim((string) $f),
                    is_array($addon->tr('features')) ? $addon->tr('features') : [],
                ), static fn (string $f): bool => $f !== '')),
                gifts: $addon->giftLines(),
                quantity: $quantity,
                maxQuantity: (int) ($pivot->max_qty ?? 0),
                // Lo que se COBRA, gratis descontado (`chargedSubtotalCents()`, la misma cuenta que el libro): una incluida
                // por niño son 14 unidades a 0 €, no 14 por su tarifa.
                chargedCents: $line?->chargedSubtotalCents() ?? 0,
                closed: $bornWithOrder || ! $withinWindow,
                closedReason: match (true) {
                    $bornWithOrder => PostFormAddonView::REASON_SOLD_AT_BOOKING,
                    ! $withinWindow => PostFormAddonView::REASON_CUTOFF,
                    default => null,
                },
                closesAt: self::deadlineFor($principal, $pivot)?->toIso8601String(),
                // F5 (`#749`): lo que la lista de invitados necesita para PINTARLO (la tarta, lo de los padres, «Para 6
                // adultos», la foto). Presentación: nada de esto entra en `reconcile()` ni en el precio.
                serves: $addon->peopleServed(),
                family: trim((string) ($addon->tr('family') ?? '')),
                block: $pivot->postformBlock(),
                imageUrl: $addon->imageUrl(),
                included: $included,
                // Una línea ya vendida dice su unidad por su SELLO; una que aún no existe, por el enganche de hoy (`#448`).
                perGuest: ($line !== null ? AddonResolver::soldQuantityUnit($line, $pivot) : $pivot->quantityUnit()) === ProductAddon::MODE_PER_GUEST,
                group: $group,
            );
        }

        return $rows;
    }

    /**
     * El unitario de un extra en esta reserva: el de su LÍNEA si ya la tiene (R2, el comunicado); si no, el del catálogo del
     * día de la VISITA (`#415`). `null` = no se puede tarificar, salvo INCLUIDO: incluido sin tarifa es gratis, como al
     * reservar (`AddonResolver::resolve()`). Punto ÚNICO de la lectura y la escritura: si divergieran, la pantalla
     * enseñaría un precio y se cobraría otro.
     */
    private function unitFor(OrderItem $principal, TicketType $addon, ProductAddon $pivot, ?OrderItem $line): ?int
    {
        if ($line !== null) {
            return (int) $line->unit_price;
        }

        $price = $this->rates->priceCents($addon, self::pricingDate($principal));

        return $price ?? ($pivot->is_included ? 0 : null);
    }

    /**
     * El instante EXACTO en que vence el plazo de este complemento, en la zona del parque: **el de su LISTA de invitados**
     * (`[DECIDIDO owner]` `#912`, `fiesta-sistema-nuevo.md` §4.20: «1 plazo solamente… por lista»), calculado por UNA sola
     * aritmética, la de `GuestCountPolicy::deadlineFor()`, que ya cierra el número, las fichas y las respuestas. `null` si la
     * reserva no tiene franja. El enganche ya no decide; el parámetro se queda por quien lo llama (la isla de plataforma).
     */
    public static function deadlineFor(OrderItem $principal, ProductAddon $pivot): ?Carbon
    {
        return app(GuestCountPolicy::class)->deadlineFor($principal);
    }

    /**
     * El día con el que se tarifica un extra NUEVO de esta reserva: **el de la VISITA** (`#415`).
     *
     * Punto único, y por eso existe: lo llaman la LECTURA ({@see viewFor}) y la ESCRITURA
     * ({@see write}), y si divergieran la pantalla enseñaría un precio y se cobraría otro — la
     * segunda fuente de verdad que este subsistema evita en todo lo demás.
     *
     * ⚠️ Sin franja se cae a hoy: una reserva sin día no tiene día de visita con el que preguntar.
     */
    private static function pricingDate(OrderItem $principal): CarbonInterface
    {
        $date = $principal->slot?->date;

        return $date !== null ? Carbon::parse($date->toDateString()) : Carbon::today();
    }

    /**
     * ¿Estamos DENTRO del plazo de este complemento para esta reserva? (§4.6.)
     *
     * ⚠️⚠️ **Se mide con `DisplayTime::now()` contra la hora de PARED de la franja**, y no con el
     * predicado que cierra el post-form: `OrderItem::isFinishedInPractice()` parsea esa hora como UTC
     * y por eso declara terminada una reserva 1–2 h TARDE (ficha en `DEUDA.md`, §4.9). Heredarlo aquí
     * dejaría quitar un extra ya consumido — que es exactamente lo que el plazo existe para impedir.
     *
     * ⚠️ Desde `#912` el plazo es el de la LISTA ({@see deadlineFor()}): siempre existe con franja y nunca hereda ese reloj
     * torcido, así que ya no hace falta exigir uno por enganche (era la regla D10). Y se compara igual que
     * `GuestCountPolicy::isWithinWindow()` (`lt`): la cabecera y cada complemento cierran en el MISMO instante.
     */
    public static function isWithinWindow(OrderItem $principal, ProductAddon $pivot): bool
    {
        // Un solo sitio calcula el instante del corte ({@see deadlineFor}), y aquí solo se compara:
        // dos aritméticas del mismo plazo acabarían dando respuestas distintas a la pantalla y al
        // guardado, que es exactamente la clase de divergencia que esta feature no puede permitirse.
        $deadline = self::deadlineFor($principal, $pivot);

        // Hora de PARED del parque, que es como se guardan las franjas (lo demuestra que
        // `SlotOffer::passesIntradayFloor` las compare contra `DisplayTime::now()`).
        return $deadline !== null && DisplayTime::now()->lt($deadline);
    }

    /**
     * La reconciliación, ya con el ítem bloqueado y fresco.
     *
     * @param  array<int,int>  $desired
     */
    private function apply(OrderItem $item, array $desired, ?User $by): PostFormAddonChanges
    {
        $order = $item->order;
        $offerable = $this->offerableFor($item);
        $actor = $by ?? $order?->user;

        if ($order === null || $actor === null) {
            return new PostFormAddonChanges(blocked: $this->blockAll($desired, 'closed'));
        }

        // R0 · solo se gobiernan las hijas que NACIERON DESPUÉS. Se mide por el HECHO de la fila
        // (`birthValue() === 0`), nunca por el eje del catálogo: ver el docblock de la clase.
        $order->loadMissing('adjustments');
        $governedLines = [];   // addonId => OrderItem
        $frozenLines = [];     // addonId => OrderItem (nacidas con el pedido: solo lectura para el cliente)
        foreach ($item->children as $child) {
            if ($child->isCancelled()) {
                continue;
            }
            $key = (int) $child->ticket_type_id;
            if (LineFacts::forItem($order, $child)->birthValue() === 0) {
                $governedLines[$key] = $child;
            } else {
                $frozenLines[$key] = $child;
            }
        }

        $moves = [];
        $delta = 0;
        [$desired, $blocked] = $this->settleChoices($item, $offerable, $desired, $governedLines, $frozenLines);

        // Lo que SE QUITA, antes de lo que se pone: cambiar de opción en un grupo es cancelar una y crear otra en este
        // mismo guardado, y así no hay un instante con dos vivas.
        $desired = array_filter($desired, static fn ($qty): bool => (int) $qty <= 0)
            + array_filter($desired, static fn ($qty): bool => (int) $qty > 0);

        foreach ($desired as $addonId => $rawQty) {
            $addonId = (int) $addonId;
            $addon = $offerable->get($addonId);
            $pivot = $addon?->addonPivot();

            if ($addon === null || $pivot === null) {
                // No ofrecido, fuera de plazo o de otra fase: no se toca y se DICE.
                $blocked[] = ['addon_id' => $addonId, 'reason' => 'not_offerable'];

                continue;
            }
            if (isset($frozenLines[$addonId])) {
                // Se compró al reservar: es del parque, no del cliente (R0).
                $blocked[] = ['addon_id' => $addonId, 'reason' => 'sold_at_booking'];

                continue;
            }

            $current = $governedLines[$addonId] ?? null;
            $requested = max(0, (int) $rawQty);
            // La UNIDAD: la del SELLO si la línea ya existe —lo vendido no lo reinterpreta el catálogo de mañana (`#448`)—, la
            // del enganche si nace ahora.
            $quantityUnit = $current !== null ? AddonResolver::soldQuantityUnit($current, $pivot) : $pivot->quantityUnit();
            // La MISMA autoridad de cantidad que la compra: tope del enganche incluido. ⚠️ Salvo «no elegida» de una opción
            // POR NIÑO (`#914`): esa autoridad devuelve los invitados SIEMPRE —al reservar el grupo nunca está vacío—, y aquí
            // 0 es «esta no», que se tiene que poder decir.
            $target = $quantityUnit === ProductAddon::MODE_PER_GUEST && $requested === 0
                ? 0
                : AddonResolver::effectiveQuantityForUnit($pivot, $quantityUnit, $requested, (int) $item->quantity);
            $from = $current !== null ? (int) $current->quantity : 0;

            if ($target === $from) {
                continue; // R4: idempotente.
            }

            $moved = $this->write($item, $order, $addon, $pivot, $quantityUnit, $current, $from, $target, $actor);
            if ($moved === null) {
                $blocked[] = ['addon_id' => $addonId, 'reason' => 'unpriced'];

                continue;
            }

            $delta += $moved;
            $moves[] = ['addon_id' => $addonId, 'name' => (string) $addon->tr('name'), 'from' => $from, 'to' => $target];
        }

        return new PostFormAddonChanges($moves, $blocked, $delta);
    }

    /**
     * «ELIGE UNA», bajo el lock (`[DECIDIDO owner]` `#914`): lo que el cuerpo pide de cada GRUPO DE OPCIONES, llevado a lo
     * que el grupo admite. Esta es la autoridad; la lista solo lo pinta (una pregunta de una respuesta).
     *
     *  · elegir una QUITA las demás, las mande el cuerpo o no: un cliente de la API puede mandar solo la nueva, y dos vivas
     *    serían dos meriendas para la misma fiesta;
     *  · dos elegidas a la vez no mueven nada del grupo, y se DICE (`choice_conflict`);
     *  · un grupo con «hay que elegir» no se vacía: la elegida se cambia, no se quita (`choice_required`);
     *  · con una opción comprada AL RESERVAR, el grupo es del parque (`sold_at_booking`, R0).
     *
     * Un grupo que el cuerpo no menciona no se toca (D5, como cualquier extra omitido).
     *
     * @param  Collection<int, TicketType>  $offerable
     * @param  array<int,int>  $desired
     * @param  array<int, OrderItem>  $governed
     * @param  array<int, OrderItem>  $frozen
     * @return array{0: array<int,int>, 1: list<array{addon_id:int, reason:string}>}
     */
    private function settleChoices(OrderItem $item, Collection $offerable, array $desired, array $governed, array $frozen): array
    {
        $members = [];
        foreach ($offerable as $id => $addon) {
            $key = $addon->addonPivot()?->choiceGroup();
            if ($key !== null) {
                $members[$key][] = (int) $id;
            }
        }
        if ($members === []) {
            return [$desired, []];
        }

        $groups = $this->groupsOf($item);
        $blocked = [];
        foreach ($members as $key => $ids) {
            $sent = array_values(array_filter($ids, static fn (int $id): bool => array_key_exists($id, $desired)));
            if ($sent === []) {
                continue;
            }
            $refuse = static function (string $reason) use (&$desired, &$blocked, $sent): void {
                foreach ($sent as $id) {
                    unset($desired[$id]);
                    $blocked[] = ['addon_id' => $id, 'reason' => $reason];
                }
            };

            if (array_intersect($ids, array_keys($frozen)) !== []) {
                $refuse('sold_at_booking');

                continue;
            }
            $chosen = array_values(array_filter($sent, static fn (int $id): bool => (int) $desired[$id] > 0));
            if (count($chosen) > 1) {
                $refuse('choice_conflict');

                continue;
            }
            if ($chosen === []) {
                if ((bool) $groups->get($key)?->is_required && array_intersect($ids, array_keys($governed)) !== []) {
                    $refuse('choice_required');
                }

                continue;
            }
            foreach ($ids as $id) {
                if ($id !== $chosen[0]) {
                    $desired[$id] = 0;
                }
            }
        }

        return [$desired, $blocked];
    }

    /**
     * Escribe UN gesto y devuelve el delta en céntimos, o `null` si no se pudo tarificar.
     *
     * ⚠️ **La fila y su hecho son una escritura INDIVISIBLE**: separarlos es exactamente el modo de
     * fallo que rompe `I1` —el editor del panel ya lo tuvo—, así que viven en el mismo método y no
     * hay otra puerta.
     *
     * ⚠️ **No se llama a `AddonResolver::resolve()`**, y es deliberado: aquél **LANZA** cuando un
     * complemento ofrecido no tiene precio ese día, y aquí eso tumbaría el guardado entero del
     * post-form —edades y nombres incluidos— por un hueco de tarifas. `MixedPartySurcharge` ya
     * resolvió esto al revés y lo dejó escrito: *no poder tarificar es una AUSENCIA*. Lo que sí se
     * comparte es la autoridad de la cantidad (`effectiveQuantityForUnit`) y la de lo gratis (`freeUnitsForUnit`), las dos con
     * la unidad de la línea: su SELLO si ya existe (`#448`), la del enganche si nace.
     */
    private function write(OrderItem $item, Order $order, TicketType $addon, ProductAddon $pivot, string $quantityUnit, ?OrderItem $current, int $from, int $target, User $actor): ?int
    {
        // R2 · la línea conserva su `unit_price`: subir de 2 a 3 cobra la tercera al precio de la
        // LÍNEA, no al de hoy. Solo una línea NUEVA se tarifica, y con el día de la VISITA (`#415`,
        // que revisa §4.11 de la hora extra: la regla pasó a ser el día de la línea, no el de la
        // compra). Tiene que dar el MISMO número que la lectura de {@see viewFor}, o la pantalla
        // enseñaría un precio y se cobraría otro.
        $unit = $this->unitFor($item, $addon, $pivot, $current);

        if ($unit === null) {
            return null;
        }

        // RETIRAR: `markCancelled()` y NINGÚN hecho. El libro emite su propio movimiento de
        // cancelación desde `chargedSubtotalCents()`, que no mira `cancelled_at`; escribir además un
        // `recordEdit` restaría DOS veces y rompería `I1` e `I3`.
        //
        // ⚠️⚠️ **Pero el delta que se DEVUELVE sí lleva la retirada, y no es lo mismo.** «Qué hecho
        // se escribe» y «cuánto cambia el pedido» son dos preguntas distintas, y devolver 0 aquí
        // confundía la segunda con la primera: el correo del cliente decía **«se suman 27,00 €»**
        // en un guardado que subía el cubo (+12), retiraba las tapas (−8) y añadía la tarta (+15),
        // o sea 19,00 — y el audit registraba el mismo número inflado. Lo cazó la aritmética de un
        // caso, no una revisión.
        if ($target === 0) {
            // Se mide ANTES de cancelar a propósito: hoy `chargedSubtotalCents()` no mira
            // `cancelled_at` —de eso vive la retirada—, pero leerlo después ataría este importe a
            // esa propiedad, y el día que alguien la cambie el delta caería a 0 sin fallar nada.
            $charged = $current?->chargedSubtotalCents() ?? 0;
            $current?->markCancelled($actor);

            return -$charged;
        }

        // Lo gratis, con la unidad de la LÍNEA (el sello): una incluida por niño lo es entera, sea cual sea el número.
        $free = AddonResolver::freeUnitsForUnit($pivot, $quantityUnit, $target);
        $newCharged = max(0, $target - $free) * $unit;

        if ($current === null) {
            $child = $item->children()->create([
                'order_id' => $item->order_id,
                'ticket_type_id' => $addon->getKey(),
                // Neutro al aforo: un complemento de venta posterior no puede OCUPAR (§4.3·5), y esa
                // prohibición es la mitad del coste que esta feature se ahorra.
                'slot_id' => null,
                'quantity' => $target,
                'free_quantity' => $free,
                'unit_price' => $unit,
                'seats' => 0,
                // El SELLO DEL MODO (`specs/hora-extra.md` §12, `#448`). Desde `#914` también `per_guest`: la opción
                // POR NIÑO de un grupo (la merienda). El sello es lo que lee `GuestCountAdjuster` para que la línea siga
                // al número de niños, y lo que impide que el catálogo de mañana la reinterprete.
                'addon_quantity_mode' => $quantityUnit,
                'event_data' => null,
            ]);
            $this->recordMove($order, $child, $newCharged, $addon, 0, $target, $actor);

            return $newCharged;
        }

        $oldCharged = $current->chargedSubtotalCents();
        $current->forceFill(['quantity' => $target, 'free_quantity' => $free])->save();
        // ⚠️ El «desde» se recibe por parámetro y NO de `getOriginal()`: `save()` sincroniza los
        // originales, así que leerlo después devolvería el valor NUEVO y el libro rotularía
        // «Cubo de refrescos: 2 → 2». Lo cazó el caso del rótulo, no una revisión.
        $this->recordMove($order, $current, $newCharged - $oldCharged, $addon, $from, $target, $actor);

        return $newCharged - $oldCharged;
    }

    /**
     * El HECHO de la gestión, atado SIEMPRE a la hija.
     *
     * ⚠️⚠️ **Nunca al principal**, aunque las cuatro identidades del libro cerrarían igual: son
     * SUMAS y por construcción no ven una permuta entre líneas — medido, atarlo al principal hace
     * que el panel ofrezca devolver el importe de un extra que nadie pagó online.
     *
     * ⚠️ El contexto es `changes.quantity_change`, **no `addon_change`**: medido,
     * `MovementLabel::edit()` no lee la clave `removed` ni las bajadas, así que `addon_change` daría
     * «Cambios en Cumpleaños Jump» para un −12,00 €. Con `quantity_change` sobre la hija el libro ya
     * sabe decir «Cubo de refrescos: 3 → 2». (La prohibición de `changes.*` de `cumple-mixto.md` T4
     * es del ajuste del CRÉDITO mixto y por otra razón: allí haría reconstruir un original que no
     * existe. Aquí no hay crédito.)
     */
    private function recordMove(Order $order, OrderItem $child, int $delta, TicketType $addon, int $from, int $to, User $actor): void
    {
        if ($delta === 0) {
            // `Order::recordEdit()` LANZA con delta cero, y un complemento a 0 € es configuración
            // válida: la línea sigue con `nac = 0`, así que no hay hecho que escribir.
            return;
        }

        $order->recordEdit($child, $delta, $actor, 'postform_addon', [
            'changes' => ['quantity_change' => ['old' => $from, 'new' => $to]],
        ]);
    }

    /**
     * @param  array<int,int>  $desired
     * @return list<array{addon_id:int, reason:string}>
     */
    private function blockAll(array $desired, string $reason): array
    {
        return array_values(array_map(
            static fn (int $id): array => ['addon_id' => $id, 'reason' => $reason],
            array_map('intval', array_keys($desired)),
        ));
    }
}
