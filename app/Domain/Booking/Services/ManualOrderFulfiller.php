<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\AuditLogger;
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
     *
     * @throws ReservationException si la cesta no valida (aforo, fechas, precios…)
     * @throws InvalidArgumentException si el método no es efectivo/datáfono
     */
    public function fulfill(User $customer, array $cart, string $method): Order
    {
        if (! in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException("Unsupported manual payment method: {$method}");
        }

        $now = now();

        $order = DB::transaction(function () use ($customer, $cart, $method, $now): Order {
            // Re-valida y bloquea aforo. Lanza ReservationException si algo no cuadra → rollback.
            $order = $this->orderCreator->createPendingOrder($customer, $cart, null);

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

        // Auditoría: quién (Auth::id() del operador), método, importe y cliente.
        AuditLogger::log('orders.created_manual', $order, [
            'order_code' => $order->code,
            'method' => $method,
            'total' => $order->total,
            'customer_id' => $customer->id,
        ]);

        return $order;
    }
}
