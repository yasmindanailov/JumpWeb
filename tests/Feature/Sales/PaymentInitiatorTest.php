<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Exceptions\PaymentInitiationException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentInitiator;
use App\Domain\Payments\Services\Redsys;
use App\Domain\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 3 · paso 2 — la IDA del pago, probada por sí misma.
 *
 * Vivía por triplicado (checkout del sidebar, reintento del sidebar, reintento de «Mis pedidos») y
 * solo se ejercitaba a través de cada pantalla. Estos tests atacan el servicio directo, con un
 * doble de `Redsys` que no firma nada: lo que se prueba aquí es lo que el initiator DECIDE —qué
 * `Payment` crea, a cuáles sustituye y qué registra al fallar—, no la criptografía de la pasarela,
 * que tiene sus propios tests.
 */
class PaymentInitiatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->order = Order::create([
            'user_id' => $this->user->id,
            'code' => 'JJ-INIT1',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 2500, 'total' => 2500, 'currency' => 'EUR',
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /** Redsys que no toca la red ni firma: devuelve un `gateway_order` incremental y un payload fijo. */
    private function fakeGateway(): Redsys
    {
        return new class extends Redsys
        {
            public int $issued = 0;

            public function nextGatewayOrder(): string
            {
                $this->issued++;

                return str_pad((string) $this->issued, 12, '0', STR_PAD_LEFT);
            }

            public function buildPaymentFormData(Order $order, Payment $payment, ?string $locale = null): array
            {
                return ['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => 'x', 'Ds_Signature' => 'y'];
            }
        };
    }

    private function initiator(?Redsys $gateway = null): PaymentInitiator
    {
        return new PaymentInitiator($gateway ?? $this->fakeGateway());
    }

    private function pendingPaymentOn(Order $order): Payment
    {
        return Payment::create([
            'payable_type' => (new Order)->getMorphClass(),
            'payable_id' => $order->id,
            'provider' => 'redsys',
            'amount' => $order->onlineDueCents(),
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'gateway_order' => '999999999999',
        ]);
    }

    public function test_opening_creates_a_pending_payment_for_the_online_amount(): void
    {
        $ticket = $this->initiator()->open($this->order);

        $this->assertSame(Payment::STATUS_PENDING, $ticket->payment->status);
        $this->assertSame($this->order->onlineDueCents(), (int) $ticket->payment->amount);
        $this->assertSame('EUR', $ticket->payment->currency);
        $this->assertNotSame('', (string) $ticket->payment->gateway_order);
        $this->assertArrayHasKey('Ds_Signature', $ticket->formData);
    }

    /**
     * `PAY-04`: reabrir el cobro descarta los intentos `pending` anteriores como **`superseded`**,
     * no como `failed`. La diferencia importa: si ese intento viejo se autorizase tarde, el handler
     * de vuelta lo trata como cobro real y su guarda de incidencia evita emitir tickets duplicados.
     */
    public function test_reopening_supersedes_the_previous_pending_payment(): void
    {
        $previous = $this->pendingPaymentOn($this->order);

        $ticket = $this->initiator()->reopen($this->order);

        $this->assertSame(Payment::STATUS_SUPERSEDED, $previous->fresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, $ticket->payment->status);
        $this->assertNotSame($previous->gateway_order, $ticket->payment->gateway_order);
    }

    /**
     * Y el PRIMER cobro no supersede nada. Parece obvio y no lo es: las dos operaciones comparten
     * casi todo el código, así que sin este testigo bastaría un parámetro mal puesto para que abrir
     * un pedido matara el intento en curso de otro cobro del mismo pedido.
     */
    public function test_opening_leaves_other_pending_payments_alone(): void
    {
        $previous = $this->pendingPaymentOn($this->order);

        $this->initiator()->open($this->order);

        $this->assertSame(Payment::STATUS_PENDING, $previous->fresh()->status);
    }

    /** Solo se sustituyen los intentos DE ESE pedido. */
    public function test_reopening_does_not_touch_payments_of_another_order(): void
    {
        $otherOrder = Order::create([
            'user_id' => $this->user->id, 'code' => 'JJ-INIT2', 'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR', 'expires_at' => now()->addMinutes(15),
        ]);
        $alien = $this->pendingPaymentOn($otherOrder);

        $this->initiator()->reopen($this->order);

        $this->assertSame(Payment::STATUS_PENDING, $alien->fresh()->status);
    }

    /**
     * Un fallo al abrir el cobro deja rastro en `audit_logs` (#169) ANTES de propagarse: es lo que
     * la operadora ve en el historial del pedido cuando un pedido se caduca «sin explicación».
     * Registrarlo dentro del servicio y no en cada llamante es media razón de que este servicio
     * exista.
     */
    public function test_a_failure_is_audited_and_raised_as_a_typed_exception(): void
    {
        $broken = new class extends Redsys
        {
            public function nextGatewayOrder(): string
            {
                return '000000000001';
            }

            public function buildPaymentFormData(Order $order, Payment $payment, ?string $locale = null): array
            {
                throw new RuntimeException('simulated_failure');
            }
        };

        try {
            $this->initiator($broken)->open($this->order, null, PaymentInitiator::SOURCE_CHECKOUT);
            $this->fail('abrir el cobro debía fallar');
        } catch (PaymentInitiationException $e) {
            $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
        }

        $log = AuditLog::query()->where('action', 'orders.payment_init_failed')->latest('id')->first();

        $this->assertNotNull($log, 'el fallo tiene que quedar en el historial del pedido');
        $this->assertSame('checkout', $log->payload['source']);
        $this->assertSame($this->order->code, $log->payload['order_code']);
    }

    /** El pedido NO se toca al fallar: quién lo suelta (o no) lo decide el llamante. */
    public function test_a_failure_does_not_decide_the_fate_of_the_order(): void
    {
        $broken = new class extends Redsys
        {
            public function nextGatewayOrder(): string
            {
                throw new RuntimeException('gateway_order_unavailable');
            }
        };

        try {
            $this->initiator($broken)->reopen($this->order);
        } catch (PaymentInitiationException) {
            // esperado
        }

        $this->assertSame(Order::STATUS_PENDING, $this->order->fresh()->status);
        $this->assertSame(0, Payment::query()->count(), 'una transacción abortada no deja el Payment a medias');
    }
}
