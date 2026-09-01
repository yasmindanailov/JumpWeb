<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\CounterSale;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\AuditLogger;
use App\Notifications\GuardianAuthorizationRequest;
use App\Notifications\GuestFormRequest;
use App\Notifications\OrderConfirmation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Pedido manual de back-office cobrado EN EL MOMENTO (efectivo o datáfono físico) — Fase 7.3,
 * decisión #120.
 *
 * Reutiliza la maquinaria validada de la compra pública sin duplicarla:
 *  - `OrderCreator::createPendingOrder` re-valida TODO en servidor (aforo con `lockForUpdate`,
 *    ventana de producto, rango de pack, precio del día, complementos compatibles). Si la
 *    franja se llena justo entonces, lanza `ReservationException` y NO se crea nada.
 *  - `TicketIssuer` emite los tickets (misma lógica que la vuelta Redsys).
 *  - `OrderConfirmation` informa al cliente (su correo es su único rastro fuera de "Mis pedidos").
 *
 * El cobro es inmediato y firme (`expires_at = null`): el operador ya tiene el dinero (efectivo)
 * o el datáfono ya autorizó. NO toca Redsys → no hay ventana de fallo de pasarela. El "enlace de
 * pago por email" (Order pending + Redsys) es una iteración posterior (#120, 3er método).
 *
 * Atomicidad: creación + Payment pagado + marca de Order + tickets van en UNA transacción
 * (la transacción interna de `OrderCreator` anida vía savepoint). El email y la auditoría se
 * hacen FUERA: un fallo de SMTP no debe deshacer un cobro ya tomado (mismo patrón que
 * `RedsysReturnHandler`).
 */
class ManualOrderFulfiller
{
    public const METHOD_CASH = 'cash';

    public const METHOD_DATAFONO = 'datafono';

    public const METHODS = [self::METHOD_CASH, self::METHOD_DATAFONO];

    public function __construct(
        private OrderCreator $orderCreator,
        private TicketIssuer $ticketIssuer,
    ) {}

    /**
     * Crea y cobra al instante un pedido para `$customer` con el método indicado.
     *
     * @param  array<int, array{ticket_type_id:int, date:string, time:string, qty:int}>  $cart
     * @param  bool  $allowBelowPackMinimum  `#329` — la excepción del operador para vender un pack
     *                                       por debajo de su mínimo de invitados, YA resuelta contra
     *                                       el permiso por la página que llama (`SEC-04`: aquí no hay
     *                                       actor al que preguntárselo).
     *                                       ▶ Aquí se compone el {@see CounterSale} que baja al
     *                                       dominio: **este servicio ES el mostrador**, así que la
     *                                       antelación mínima no ata (`#330`) sin que nadie tenga que
     *                                       pedirlo — quien llama no puede elegir eso, y es a
     *                                       propósito.
     *
     * @throws ReservationException si la cesta no valida (aforo, fechas, precios…)
     * @throws InvalidArgumentException si el método no es efectivo/datáfono
     */
    public function fulfill(User $customer, array $cart, string $method, bool $allowBelowPackMinimum = false): Order
    {
        if (! in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException("Unsupported manual payment method: {$method}");
        }

        $now = now();

        $order = DB::transaction(function () use ($customer, $cart, $method, $now, $allowBelowPackMinimum): Order {
            // Re-valida y bloquea aforo. Lanza ReservationException si algo no cuadra → rollback.
            $order = $this->orderCreator->createPendingOrder($customer, $cart, null, CounterSale::byOperator($allowBelowPackMinimum));

            Payment::create([
                'payable_type' => (new Order)->getMorphClass(),
                'payable_id' => $order->id,
                'provider' => $method,
                // #225 (D3): el pedido manual cobra solo la SEÑAL si el producto la tiene
                // configurada; el resto queda «a cobrar en el parque». Sin señal → el total.
                'amount' => $order->onlineDueCents(),
                'currency' => $order->currency,
                'status' => Payment::STATUS_PAID,
                'paid_at' => $now,
            ]);

            $order->forceFill([
                'status' => Order::STATUS_PAID,
                'paid_at' => $now,
                'expires_at' => null, // pagada al momento; sin caducidad.
            ])->save();

            $this->ticketIssuer->issue($order);

            return $order;
        });

        // Email FUERA de la transacción: un fallo SMTP no debe deshacer un cobro ya tomado.
        //
        // Cliente de agenda SIN email (`email` null, #263): no hay a dónde enviar. Guardamos con
        // `filled(email)` para NO encolar notificaciones muertas (de lo contrario el job se crearía y
        // `MailChannel` lo descartaría en silencio en el worker). El enlace del post-form de
        // cumpleaños sigue accesible: el operador lo obtiene con el icono «enlace» del producto y lo
        // envía por WhatsApp/SMS (el cliente solo tiene teléfono).
        if ($order->user && filled($order->user->email)) {
            try {
                $order->user->notify(new OrderConfirmation($order));
            } catch (Throwable $e) {
                Log::warning('manual_order.confirmation_mail_failed', [
                    'order_id' => $order->id,
                    'order_code' => $order->code,
                    'error' => $e->getMessage(),
                ]);
            }

            // Post-form de datos por invitado (#217): mismo email con enlace firmado si la reserva
            // (creada en local) incluye un pack que pide esos datos. Aislado en su try/catch.
            try {
                $order->loadMissing('items.ticketType');
                if ($order->needsGuestForm()) {
                    // Individualizado POR RESERVA (#217): un email por cada pack que pide el post-form.
                    foreach ($order->guestFormItems() as $reservation) {
                        if ($reservation->needsGuestForm()) {
                            $order->user->notify(new GuestFormRequest($reservation));
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning('manual_order.guest_form_mail_failed', [
                    'order_id' => $order->id,
                    'order_code' => $order->code,
                    'error' => $e->getMessage(),
                ]);
            }

            // El enlace del JUSTIFICANTE de un menor invitado (`specs/waiver-por-reserva.md` §12.3).
            //
            // ❗ **Esta rama es el «caso 3» del owner entero**: una venta EN PERSONA. Y por eso no
            // hizo falta inventar un justificante sin reserva — `CreateManualOrderPage` exige cliente
            // y crea la cuenta si no existe, así que una venta de mostrador **ya produce un pedido
            // con responsable** y vale el mismo enlace (§12.3).
            try {
                // UNO POR RESERVA marcada (`#343`), como el post-form de aquí arriba: un pedido con
                // dos visitas necesita dos enlaces, y cada padre tiene que saber a cuál va su hijo.
                foreach ($order->guardianReservations() as $reservation) {
                    $order->user->notify(new GuardianAuthorizationRequest($reservation));
                }
            } catch (Throwable $e) {
                Log::warning('manual_order.guardian_mail_failed', [
                    'order_id' => $order->id,
                    'order_code' => $order->code,
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($order->user) {
            // Sin email: dejamos rastro (sin PII) de que las notificaciones del cliente se omiten y, si
            // hay post-form de cumpleaños, de que el enlace debe entregarse a mano (icono del producto).
            $order->loadMissing('items.ticketType');
            Log::info('manual_order.customer_notifications_skipped_no_email', [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'needs_guest_form' => $order->needsGuestForm(),
            ]);
        }

        // `#329` — la excepción del mínimo se registra **solo cuando de verdad se usó** (un `false`
        // en cada pedido normal sería ruido que entierra la señal, D7) y se mide sobre lo ESCRITO
        // —las líneas del pedido ya creado— y no sobre la intención del formulario: *el rastro dice
        // qué se vendió, no qué se pidió*. `pack_min_qty` acompaña para que se sepa de qué mínimo se
        // bajó sin reconstruir el catálogo de entonces.
        //
        // ⚠️ Las dos condiciones hacen falta y ninguna sobra: la bandera evita recorrer las líneas en
        // cada pedido normal (coste), y el `!== []` cubre el caso en que la intención llegó marcada
        // pero la venta acabó siendo legal — pasa si alguien BAJA el mínimo del producto en el panel
        // mientras el operador tiene la línea en el carrito.
        $belowMinimumLines = $allowBelowPackMinimum ? $this->linesBelowPackMinimum($order) : [];

        // Auditoría: quién (Auth::id() del operador), método, importe y cliente.
        AuditLogger::log('orders.created_manual', $order, [
            'order_code' => $order->code,
            'method' => $method,
            'total' => $order->total,
            'customer_id' => $customer->id,
        ] + ($belowMinimumLines !== [] ? ['below_pack_minimum' => $belowMinimumLines] : []));

        return $order;
    }

    /**
     * Las líneas del pedido que quedaron por debajo del mínimo de invitados de su pack (`#329`).
     *
     * ⚠️ Un COMPLEMENTO también es una fila de `order_items`, pero nunca es `pack`, así que el
     * `isPack()` los deja fuera solo — la trampa de `#324`, donde un `instanceof` alcanzaba a los
     * complementos porque comparten clase con los productos principales.
     *
     * @return list<array{order_item_id:int, ticket_type_id:int, quantity:int, pack_min_qty:int}>
     */
    private function linesBelowPackMinimum(Order $order): array
    {
        $order->loadMissing('items.ticketType');

        $lines = [];
        foreach ($order->items as $item) {
            $type = $item->ticketType;
            if ($type === null || ! $type->isPack()) {
                continue;
            }
            $min = $type->contractableMinimum();
            if ((int) $item->quantity < $min) {
                $lines[] = [
                    'order_item_id' => (int) $item->id,
                    'ticket_type_id' => (int) $type->id,
                    'quantity' => (int) $item->quantity,
                    'pack_min_qty' => $min,
                ];
            }
        }

        return $lines;
    }
}
