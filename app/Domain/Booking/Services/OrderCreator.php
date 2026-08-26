<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fase 5.3 — Convierte la cesta (sesión) en un PEDIDO real, de forma segura. Todo se
 * re-valida y se calcula en SERVIDOR (regla 12): no se confía en la sesión. Dentro de una
 * transacción con BLOQUEO de filas (`lockForUpdate`) sobre las franjas implicadas, para que
 * dos compras simultáneas de las últimas plazas se serialicen (anti-sobreventa).
 *
 * El pedido nace `pending` con `expires_at = null`: RETIENE la plaza y NO caduca solo
 * (reserva pendiente de pago; el parque la cobra en sitio o, en el futuro, por Redsys).
 * Cuando exista Redsys, basta poner `expires_at` durante la pasarela para reusar la
 * caducidad ya existente (#62). Sin pasarela todavía: no se cobra aquí.
 */
class OrderCreator
{
    /**
     * Cap de líneas distintas por cesta. **Invariante de SERVIDOR** (`INVARIANTES` §1 `PAY-12`):
     * una cesta enorme bloquearía muchas franjas a la vez (contención de BD).
     *
     * Vivía en `Livewire\Tickets\Purchase` —una clase de UI— y `OrderCreator` la importaba, con
     * lo que el dominio dependía de la capa de entrega: el acoplamiento que el spec de módulos
     * señalaba en §1 y que la frontera del paso 6 dejó al descubierto. El valor no cambia (50) ni
     * cambia dónde se aplica; solo vuelve al lado que manda. `Purchase` la referencia desde aquí,
     * así que su constante pública sigue existiendo (y muere con ella en Fase 4).
     */
    public const MAX_LINES_PER_CART = 50;

    /**
     * Minutos que una reserva PROVISIONAL retiene la plaza mientras el cliente verifica su email
     * (matiza #46 para la compra). Placeholder; configurable desde el panel en Fase 7 `[PENDIENTE]`.
     */
    public const VERIFICATION_HOLD_MINUTES = 120;

    public function __construct(
        private RateResolver $rates,
        private SlotAvailability $availability,
        private ProductAvailability $productWindow,
        private PackAvailability $packAvailability,
        private AddonResolver $addons,
        private ZoneDaySlotLock $zoneDayLock,
    ) {}

    /** Momento hasta el que se retiene una reserva provisional pendiente de verificación. */
    public static function verificationHoldUntil(): Carbon
    {
        return now()->addMinutes(self::VERIFICATION_HOLD_MINUTES);
    }

    /**
     * Momento hasta el que un pedido de CHECKOUT retiene su plaza (`AFORO-10`), según el ajuste
     * `sales.hold_minutes` del panel.
     *
     * Vive aquí y no en `CheckoutOrchestrator`, que es su único llamante, por una razón de frontera
     * medida: la ventana sale de `PaymentSettings`, que es de Payments, y el grafo de módulos solo
     * deja a Booking alcanzar `Payments\Contracts`. Esta clase **ya** lee `PaymentSettings` —para
     * `orderPrefix()`, con su costura declarada desde Fase 2—, así que alojar aquí el cálculo evita
     * abrir una flecha nueva hacia la misma configuración. De paso muere la duplicación: el
     * `now()->addMinutes(...)` estaba escrito a mano en las dos superficies que creaban pedidos.
     */
    public static function checkoutHoldUntil(): Carbon
    {
        return now()->addMinutes(PaymentSettings::holdMinutes());
    }

    /**
     * Crea el pedido `pending` a partir de la cesta. Lanza ReservationException si algo no valida.
     * Si $hold es null, la reserva es FIRME (retiene la plaza y no caduca). Si se pasa una fecha,
     * la reserva es PROVISIONAL: retiene la plaza hasta esa hora y, si no se confirma (verificación
     * de email / pago), `orders:expire` la libera. Reutiliza la maquinaria de retención de #62.
     *
     * @param  array<int, array{ticket_type_id:int, date:string, time:string, qty:int}>  $cart
     */
    public function createPendingOrder(User $user, array $cart, ?Carbon $hold = null): Order
    {
        $cart = Cart::sanitize($cart);
        if ($cart === []) {
            throw new ReservationException('tickets.errors.cart_empty');
        }
        // Cap de líneas como INVARIANTE de servidor (auditoría Fase 1, L6, regla 12): el cliente ya
        // lo comprueba al añadir, pero una petición forjada podría llegar con más. Sin esto, una
        // cesta enorme bloquearía muchas franjas a la vez (contención de BD).
        // ⚠️ **Esta es la ÚNICA capa que cuenta, y conviene no volver a escribirlo mal**: el comentario
        // que había aquí citaba un `#[Locked]` sobre la cesta del componente Livewire «como la otra
        // capa», y ese atributo **nunca se aplicó** — se evaluó y se descartó a propósito porque la
        // cesta es client-syncable por diseño (lo dice `PAY-12`, y el propio componente lo dejaba
        // anotado). Hoy la cesta viaja en el payload de la API, así que con más razón: se re-valida
        // entera aquí, y aquí es donde está la defensa.
        if (count($cart) > self::MAX_LINES_PER_CART) {
            throw new ReservationException('tickets.errors.cart_too_large');
        }

        // IDs de todos los productos implicados: líneas (entradas/packs) + sus complementos anidados.
        $ids = [];
        foreach ($cart as $line) {
            $ids[] = $line['ticket_type_id'];
            foreach ($line['addons'] ?? [] as $addon) {
                $ids[] = $addon['ticket_type_id'];
            }
        }
        $ids = array_values(array_unique($ids));

        // Zonas a bloquear, resueltas AQUÍ —FUERA de la transacción— para poder lockear con valores
        // LITERALES (ver `lockSlots`). El `zone_id` de un producto es config estable; resolverlo fuera
        // no introduce carrera y mantiene el `FOR UPDATE` como la PRIMERA sentencia de la transacción,
        // sin una lectura consistente previa que fije el snapshot de REPEATABLE READ (#246).
        $lockZoneIds = TicketType::whereIn('id', $ids)->whereNotNull('zone_id')->distinct()->pluck('zone_id')->all();

        return DB::transaction(function () use ($user, $cart, $hold, $ids, $lockZoneIds) {
            // PRIMERA operación de la transacción = LOCK de franjas (auditoría Fase 1, H2 + #246). La
            // ocupación se cuenta por tramo (#60): bloquear TODAS las franjas de las zonas/fechas
            // implicadas serializa dos reservas concurrentes sobre el mismo día/zona. CRUCIAL: el lock
            // debe ir ANTES de cualquier lectura CONSISTENTE. Bajo REPEATABLE READ (default de MySQL) el
            // snapshot de las lecturas consistentes se fija en la PRIMERA de ellas; si una lectura
            // consistente ocurre antes de tomar el lock —la carga de `TicketType`, o (#246) una
            // SUBCONSULTA dentro del propio `FOR UPDATE`—, el recuento de aforo posterior leería un
            // estado ANTERIOR al lock y dos compras de las últimas plazas SOBREVENDERÍAN (reproducido
            // empíricamente con `purchase:verify-oversell`). Por eso lockeamos por `zone_id` LITERAL
            // (resuelto arriba, fuera de la txn): el `FOR UPDATE` es la primera sentencia y la primera
            // lectura consistente —el recuento de ocupación— ocurre ya con el lock en mano.
            $slots = $this->lockSlots($cart, $lockZoneIds);

            // A partir de aquí, lecturas consistentes: su snapshot se fija AHORA, ya con el lock tomado.
            $types = TicketType::sellable()
                ->inOperationalZone() // autoridad de servidor: no se vende un producto de zona desactivada (#210)
                ->with('addons')
                ->whereIn('id', $ids)
                ->get()->keyBy('id');

            $built = [];     // [['product' => data, 'addons' => [data,...]], ...]
            $subtotal = 0;

            foreach ($cart as $i => $line) {
                $type = $types->get($line['ticket_type_id']);
                $context = [
                    'product' => $type?->tr('name') ?? '—',
                    'when' => $line['date'].' '.substr((string) $line['time'], 0, 5),
                ];

                // Las líneas de la cesta son SIEMPRE productos base (entrada/pack); los complementos van anidados (#87).
                if (! $type || $type->isAddon() || ! $type->zone_id) {
                    throw ReservationException::withContext('tickets.errors.unavailable_line', $context);
                }

                $slot = $slots->get($this->slotKey($type->zone_id, $line['date'], $line['time']));
                if (! $slot || $slot->online_sales_open === false || $slot->status === Slot::STATUS_CLOSED) {
                    throw ReservationException::withContext('tickets.errors.unavailable_line', $context);
                }
                // Fecha pasada: comparada con "hoy" en la zona OPERATIVA del parque (no UTC), para
                // no rechazar/aceptar el día local equivocado cerca de medianoche (auditoría Fase 1).
                if (Carbon::parse($line['date'])->toDateString() < DisplayTime::today()->toDateString()) {
                    throw ReservationException::withContext('tickets.errors.past_date_line', $context);
                }
                // Corte intra-día (auditoría Fase 1, H4): backstop del checkout. La OFERTA ya descarta
                // una franja de HOY cuya hora ya pasó (`SlotOffer`), pero una petición forjada o una
                // cesta OBSOLETA del mismo día podría intentar reservarla; la rechazamos con la MISMA
                // fuente que la oferta (`SlotOffer::passesIntradayFloor`) para que no diverjan.
                if (! SlotOffer::passesIntradayFloor($slot->date->toDateString(), (string) $slot->start_time, DisplayTime::now())) {
                    throw ReservationException::withContext('tickets.errors.too_late_line', $context);
                }
                if (! $this->productWindow->allowsStart($type, $slot->date, $line['time'])) {
                    throw ReservationException::withContext('tickets.errors.outside_window_line', $context);
                }
                // Antelación mínima de reserva del producto (auditoría Fase 1): backstop del checkout
                // (la oferta ya la aplica; esto blinda contra peticiones forjadas/obsoletas).
                if (! $type->meetsMinAdvance($line['date'], $line['time'], DisplayTime::now())) {
                    throw ReservationException::withContext('tickets.errors.too_soon_line', $context);
                }

                $eventData = null; // respuestas de los campos del evento (solo packs, #86)

                if ($type->isPack()) {
                    // Pack (cumpleaños): aforo por CUPO en su propio pool (#82). La cantidad es el
                    // nº de invitados, que debe caer en el rango del pack y dentro del cupo libre
                    // de la franja (contando montaje/limpieza si está activo) y las demás fiestas.
                    $min = $type->min_qty ?? 1;
                    if ($line['qty'] < $min || ($type->max_qty !== null && $line['qty'] > $type->max_qty)) {
                        throw ReservationException::withContext('tickets.errors.pack_guests_range_line', $context + [
                            'min' => $min, 'max' => $type->max_qty ?? '∞',
                        ]);
                    }
                    $available = $this->packAvailability->availableGuestsFor(
                        $slot,
                        $type,
                        $this->otherPackOccupants($cart, $i, $types),
                    );
                    if ($line['qty'] > $available) {
                        throw ReservationException::withContext('tickets.errors.pack_sold_out_line', $context);
                    }

                    // Datos del evento configurables por pack (#86): saneados a las claves del
                    // esquema de la fase de RESERVA; los obligatorios deben venir (regla 12,
                    // re-validado en servidor). Los campos `postform` (#217) se rellenan después.
                    $eventData = $type->sanitizeEventData($line['event_data'] ?? [], TicketType::EVENT_STAGE_BOOKING);
                    if ($type->missingRequiredEventFields($line['event_data'] ?? [], TicketType::EVENT_STAGE_BOOKING) !== []) {
                        throw ReservationException::withContext('tickets.errors.event_required_line', $context);
                    }
                } else {
                    // Entrada: aforo cumulativo por ocupación (#60); debe caber contando las DEMÁS
                    // líneas de entrada de la cesta (mismo pool de plazas por zona/día).
                    $available = $this->availability->availableFor(
                        $slot,
                        $type->duration_min,
                        $this->otherOccupants($cart, $i, $types),
                    );
                    if ($line['qty'] > $available) {
                        throw ReservationException::withContext('tickets.errors.sold_out_line', $context);
                    }
                }

                // Precio del día en SERVIDOR (regla 12). Si no hay precio definido para la tarifa
                // del día (null), el producto no es vendible ese día → no creamos un pedido a 0 €.
                // Un 0 explícito SÍ es válido (producto gratuito intencionado), por eso se distingue
                // null de 0.
                $unit = $this->rates->priceCents($type, Carbon::parse($line['date']));
                if ($unit === null) {
                    throw new ReservationException('tickets.errors.unavailable');
                }
                $unit = (int) $unit;
                $subtotal += $unit * $line['qty'];

                $product = [
                    'ticket_type_id' => $type->id,
                    'slot_id' => $slot->id,
                    'quantity' => $line['qty'],
                    'unit_price' => $unit,
                    'seats' => $line['qty'] * (int) ($type->seats_per_unit ?? 1),
                    'event_data' => $eventData,
                ];

                // Complementos ANIDADOS de esta línea (#87): sin franja/aforo. Resueltos de forma
                // AUTORITATIVA en servidor (regla 12) por `AddonResolver`, que aplica la config del
                // pivote (incluido / obligatorio / por-invitado / grupo excluyente): computa la
                // cantidad efectiva, las unidades GRATIS (incluidas), valida la exclusividad e
                // inyecta los obligatorios y el default de cada grupo aunque el cliente los omita.
                $resolved = $this->addons->resolve($type, (int) $line['qty'], $line['addons'] ?? [], Carbon::today());
                $subtotal += $resolved['subtotal'];

                // Señal/depósito (#225): la parte del valor base de ESTA línea que NO se cobra
                // online queda como ajuste `deposit_remainder` (a cobrar presencialmente en el
                // parque). `depositCents` es data-driven: `none` → resto 0 (las entradas pagan el
                // total online; comportamiento legacy idéntico). El resto se materializa abajo,
                // tras crear el item (necesita su id).
                $lineCharged = $unit * $line['qty'];
                $lineRemainder = max(0, $lineCharged - $type->depositCents($lineCharged));

                $built[] = [
                    'product' => $product,
                    'addons' => $resolved['rows'],
                    'principal_remainder' => $lineRemainder,
                    'has_deposit' => $lineRemainder > 0,
                ];
            }

            $order = $user->orders()->create([
                'code' => $this->uniqueCode(),
                'status' => Order::STATUS_PENDING,
                'subtotal' => $subtotal,
                'tax' => 0,                 // PVP final (IVA incluido); desglose de factura = [PENDIENTE] (#62)
                'total' => $subtotal,
                'currency' => 'EUR',
                'expires_at' => $hold,      // null = firme (no caduca); fecha = provisional (retiene y caduca)
            ]);

            // Crea cada producto y, debajo, sus complementos enlazados (parent_item_id, #87).
            foreach ($built as $entry) {
                $productItem = $order->items()->create($entry['product']);

                // Resto de la señal del principal (#225) → a cobrar en el parque.
                if ($entry['principal_remainder'] > 0) {
                    $this->recordDepositRemainder($order, $productItem, $entry['principal_remainder'], $user);
                }

                if ($entry['addons'] !== []) {
                    $children = $order->items()->createMany(array_map(
                        fn ($addonItem) => $addonItem + ['parent_item_id' => $productItem->id],
                        $entry['addons'],
                    ));

                    // Opción A (#225, decisión clienta): si el principal cobra señal, sus
                    // complementos se cobran ÍNTEGROS en el parque — la señal es lo ÚNICO que
                    // se paga online del cumpleaños. (Un complemento incluido gratis tiene
                    // chargedSubtotal 0 → no genera ajuste.)
                    if ($entry['has_deposit']) {
                        foreach ($children as $child) {
                            $childCharged = $child->chargedSubtotalCents();
                            if ($childCharged > 0) {
                                $this->recordDepositRemainder($order, $child, $childCharged, $user);
                            }
                        }
                    }
                }
            }

            return $order;
        });
    }

    /**
     * Registra el «resto de la señal» (#225) de un item como ajuste `deposit_remainder`
     * (a cobrar presencialmente). Atado al item para que cancelarlo lo anule (mismo criterio
     * que `extra_due`). `applied_by` = el cliente del pedido: en la compra no hay operador y el
     * FK `applied_by` no admite null; registra de forma fidedigna quién originó el cargo.
     */
    private function recordDepositRemainder(Order $order, OrderItem $item, int $cents, User $user): void
    {
        $order->adjustments()->create([
            'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => $cents,
            'currency' => $order->currency ?? 'EUR',
            'reason' => 'deposit_remainder',
            'applied_by' => $user->id,
        ]);
    }

    /**
     * Bloquea (FOR UPDATE) las franjas de las zonas/fechas de la cesta y las devuelve indexadas por
     * "zona|fecha|hora". La receta —literales resueltos fuera de la txn, `orderBy('id')`, primera
     * sentencia— y su porqué viven en `ZoneDaySlotLock`, compartido con las ediciones del panel
     * (extracción 4b, spec §8.9): `$zoneIds` llega ya RESUELTO (arriba, fuera de la transacción),
     * NO como subconsulta (#246). Los complementos (zone_id NULL) no aportan zona.
     *
     * @param  array<int, array{ticket_type_id:int, date:string, time:string, qty:int}>  $cart
     * @param  array<int,int>  $zoneIds
     * @return Collection<string,Slot>
     */
    private function lockSlots(array $cart, array $zoneIds)
    {
        if ($zoneIds === []) {
            return collect();
        }

        $dates = [];
        foreach ($cart as $line) {
            $dates[$line['date']] = $line['date'];
        }

        return $this->zoneDayLock
            ->acquire($zoneIds, array_values($dates))
            ->keyBy(fn (Slot $s) => $this->slotKey($s->zone_id, $s->date->toDateString(), $s->start_time));
    }

    /**
     * Ocupantes provisionales = todas las líneas de la cesta MENOS la actual (para el aforo
     * cumulativo). Se identifica la actual por ÍNDICE (dos líneas pueden ser idénticas).
     *
     * @param  array<int, array{ticket_type_id:int, date:string, time:string, qty:int}>  $cart
     * @param  Collection<int, TicketType>  $types
     * @return array<int, array{entry_start:string, duration_min:int|null, seats:int}>
     */
    private function otherOccupants(array $cart, int $currentIndex, $types): array
    {
        $current = $cart[$currentIndex];
        $type = $types->get($current['ticket_type_id']);
        $occupants = [];
        foreach ($cart as $i => $line) {
            if ($i === $currentIndex) {
                continue;
            }
            $lineType = $types->get($line['ticket_type_id']);
            // Solo cuentan los de la misma zona y día (la ocupación se calcula por zona/día).
            if (! $lineType || (int) $lineType->zone_id !== (int) $type->zone_id || $line['date'] !== $current['date']) {
                continue;
            }
            $occupants[] = [
                'entry_start' => $line['time'],
                'duration_min' => $lineType->duration_min,
                'seats' => (int) $line['qty'] * (int) ($lineType->seats_per_unit ?? 1),
            ];
        }

        return $occupants;
    }

    /**
     * Fiestas provisionales de la cesta para el cupo de packs = todas las líneas de PACK MENOS
     * la actual, de la misma zona de packs y día (pool propio, #82). Se identifica la actual
     * por ÍNDICE (dos líneas pueden ser idénticas).
     *
     * @param  array<int, array{ticket_type_id:int, date:string, time:string, qty:int}>  $cart
     * @param  Collection<int, TicketType>  $types
     * @return array<int, array{start:string, prep_before_min:int, duration_min:int|null, prep_after_min:int, guests:int}>
     */
    private function otherPackOccupants(array $cart, int $currentIndex, $types): array
    {
        $current = $cart[$currentIndex];
        $type = $types->get($current['ticket_type_id']);
        $occupants = [];
        foreach ($cart as $i => $line) {
            if ($i === $currentIndex) {
                continue;
            }
            $lineType = $types->get($line['ticket_type_id']);
            // Solo otras FIESTAS de la misma zona de packs y día (pool independiente del de entradas).
            if (! $lineType || ! $lineType->isPack()
                || (int) $lineType->zone_id !== (int) $type->zone_id
                || $line['date'] !== $current['date']) {
                continue;
            }
            $occupants[] = [
                'start' => $line['time'],
                'prep_before_min' => (int) $lineType->prep_before_min,
                'duration_min' => $lineType->duration_min,
                'prep_after_min' => (int) $lineType->prep_after_min,
                'guests' => (int) $line['qty'] * (int) ($lineType->seats_per_unit ?? 1),
            ];
        }

        return $occupants;
    }

    private function slotKey(int $zoneId, string $date, string $time): string
    {
        return $zoneId.'|'.$date.'|'.$time;
    }

    /**
     * Código de pedido legible y único (`<prefijo>XXXXXX`; prefijo por instalación vía
     * `PaymentSettings::orderPrefix()`, DECISIONES #12.b).
     *
     * El espacio (36^6 ≈ 2 mil millones) hace la colisión extremadamente rara, pero la consulta
     * `exists()` + `INSERT` no es atómica: dos transacciones concurrentes pueden generar el mismo
     * código y la segunda violaría la UNIQUE constraint en `orders.code`. Auditoría 2026-05-26
     * (hallazgo J): hasta MAX_CODE_ATTEMPTS reintentos para absorber esa carrera.
     */
    private function uniqueCode(): string
    {
        $prefix = PaymentSettings::orderPrefix();

        for ($i = 0; $i < 16; $i++) {
            $code = $prefix.Str::upper(Str::random(6));
            if (! Order::where('code', $code)->exists()) {
                return $code;
            }
        }

        // Espacio extremadamente improbable de agotar; si llegamos aquí, hay algo muy raro en BD.
        throw new ReservationException('tickets.errors.unavailable');
    }
}
