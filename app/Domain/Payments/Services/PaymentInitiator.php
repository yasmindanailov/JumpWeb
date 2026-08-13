<?php

namespace App\Domain\Payments\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Contracts\PaymentTicket;
use App\Domain\Payments\Exceptions\PaymentInitiationException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * La IDA del pago: abrir un cobro para un pedido y devolver el formulario firmado (Fase 3 · paso 2,
 * `docs/specs/api-v1.md` §4.6.2).
 *
 * Existía por triplicado —`Purchase::confirmReservation()`, `Purchase::retryPayment()` y
 * `RetryPaymentController`—, casi línea a línea, y la API habría sido la cuarta copia. Cada copia
 * era una oportunidad de que una sola de ellas se dejara el `SUPERSEDED` de `PAY-04`, o el rastro
 * en `audit_logs` que el panel necesita para explicar por qué un pedido se caducó «sin motivo».
 *
 * **Qué NO decide**: si el cliente puede pagar. Eso es la política de admisión
 * (`Booking\Contracts\ReservationAdmission`) y va antes. Aquí se asume que ya se dijo que sí.
 *
 * **El `Payment` se crea ANTES de redirigir** y ata `gateway_order ↔ Order` en base de datos: la
 * vuelta de Redsys llega sin sesión válida (POST cross-site, `SameSite=Lax`), así que ese vínculo
 * persistido es la única forma robusta de reconocer el pedido al recibir la respuesta.
 */
class PaymentInitiator
{
    /** Superficie que abre el cobro. Solo viaja al `audit_logs`, para saber por dónde entró. */
    public const SOURCE_CHECKOUT = 'checkout';

    public const SOURCE_RETRY_SIDEBAR = 'retry_sidebar';

    public const SOURCE_RETRY_ACCOUNT = 'retry_account';

    public function __construct(private Redsys $redsys) {}

    /**
     * PRIMER cobro de un pedido recién creado.
     *
     * @param  string  $source  una de las constantes `SOURCE_*`
     *
     * @throws PaymentInitiationException
     */
    public function open(Order $order, ?string $preferredLocale = null, string $source = self::SOURCE_CHECKOUT): PaymentTicket
    {
        return $this->start($order, $preferredLocale, $source, supersedePending: false);
    }

    /**
     * REINTENTO: el cliente vio «pago denegado» (o abandonó) y vuelve a intentarlo sobre el MISMO
     * pedido, que sigue reteniendo su plaza.
     *
     * Se crea un `Payment` nuevo porque Redsys exige `gateway_order` único por comercio+terminal de
     * por vida (manual §5, error 0913): reusar el fallido daría 0913. Los intentos `pending`
     * anteriores pasan a `SUPERSEDED` —estado terminal propio, **no** `failed`— para que, si uno de
     * ellos se autorizase tarde, el handler de vuelta lo trate como cobro real y su guarda de
     * incidencia evite emitir tickets duplicados (`PAY-04`, `PAY-02`).
     *
     * @param  string  $source  una de las constantes `SOURCE_*`
     *
     * @throws PaymentInitiationException
     */
    public function reopen(Order $order, ?string $preferredLocale = null, string $source = self::SOURCE_RETRY_SIDEBAR): PaymentTicket
    {
        return $this->start($order, $preferredLocale, $source, supersedePending: true);
    }

    /**
     * Reserva el `gateway_order`, crea el `Payment` y firma el payload.
     *
     * Todo fallo se registra DOS veces y a propósito: en el log con la traza (diagnóstico técnico) y
     * en `audit_logs` con `orders.payment_init_failed` (#169), que es lo que ve la operadora en el
     * historial del pedido. Antes de esto, un fallo de inicio de pago solo existía en
     * `laravel.log` y el pedido se auto-caducaba sin explicación visible en el panel.
     */
    private function start(Order $order, ?string $preferredLocale, string $source, bool $supersedePending): PaymentTicket
    {
        try {
            $payment = DB::transaction(function () use ($order, $supersedePending): Payment {
                $gatewayOrder = $this->redsys->nextGatewayOrder();

                if ($supersedePending) {
                    Payment::query()
                        ->where('payable_type', (new Order)->getMorphClass())
                        ->where('payable_id', $order->id)
                        ->where('status', Payment::STATUS_PENDING)
                        ->update(['status' => Payment::STATUS_SUPERSEDED]);
                }

                return Payment::create([
                    'payable_type' => (new Order)->getMorphClass(),
                    'payable_id' => $order->id,
                    'provider' => 'redsys',
                    // #225: el importe ONLINE (la señal, si el producto la usa), no el total.
                    'amount' => $order->onlineDueCents(),
                    'currency' => $order->currency,
                    'status' => Payment::STATUS_PENDING,
                    'gateway_order' => $gatewayOrder,
                ]);
            });

            $formData = $this->redsys->buildPaymentFormData($order, $payment, $preferredLocale ?? app()->getLocale());
        } catch (Throwable $e) {
            $this->recordFailure($order, $source, $e);

            throw new PaymentInitiationException(
                "No se pudo abrir el cobro del pedido {$order->code} ({$source}): {$e->getMessage()}",
                previous: $e,
            );
        }

        return new PaymentTicket($payment, $formData);
    }

    private function recordFailure(Order $order, string $source, Throwable $e): void
    {
        Log::error('payments.initiation_failed', [
            'order_id' => $order->id,
            'order_code' => $order->code,
            'user_id' => $order->user_id,
            'source' => $source,
            'error' => $e->getMessage(),
            'exception' => $e::class,
            'trace' => $e->getTraceAsString(),
        ]);

        AuditLogger::log('orders.payment_init_failed', $order, [
            'order_code' => $order->code,
            'source' => $source,
            'exception' => $e::class,
            'error' => $e->getMessage(),
        ]);
    }
}
