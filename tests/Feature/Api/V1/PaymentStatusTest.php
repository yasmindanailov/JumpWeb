<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 4d — `GET /api/v1/orders/{code}/payment-status`.
 *
 * Lo que estos tests fijan es el hueco que la revisión del spec (§4.5) midió y que ningún endpoint
 * cubría: **el rechazo de tarjeta solo viajaba por la SESIÓN de la web** (`purchase.failed_code`),
 * así que un cliente de API veía «pendiente» durante toda la ventana de retención y después
 * «caducado» — nunca «reintenta», teniendo el reintento disponible desde el paso 4c.
 *
 * De ahí los dos ejes: con `order_status` solo, un pedido rechazado y uno que nadie ha intentado
 * pagar son idénticos.
 */
class PaymentStatusTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function path(string $code): string
    {
        return self::ROOT.'/orders/'.$code.'/payment-status';
    }

    private function order(string $status = Order::STATUS_PENDING, ?Carbon $expiresAt = null): Order
    {
        return Order::create([
            'user_id' => $this->user->id,
            'code' => 'R-'.mb_strtoupper(bin2hex(random_bytes(3))),
            'status' => $status,
            'subtotal' => 1980, 'tax' => 0, 'total' => 1980, 'currency' => 'EUR',
            'expires_at' => $expiresAt,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function payment(Order $order, string $status, array $attributes = []): Payment
    {
        return Payment::create(array_merge([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'provider' => 'redsys',
            'amount' => 1980,
            'currency' => 'EUR',
            'status' => $status,
            'gateway_order' => (string) random_int(100000000, 999999999),
        ], $attributes));
    }

    // ── Los dos ejes ──────────────────────────────────────────────────────────────────────────

    public function test_a_reservation_nobody_tried_to_pay_reports_no_payment(): void
    {
        $order = $this->order(expiresAt: now()->addMinutes(10));

        $response = $this->actingAs($this->user)->getJson($this->path($order->code));

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonPath('order_code', $order->code)
            ->assertJsonPath('order_status', 'pending')
            ->assertJsonPath('payment_status', 'none')
            ->assertJsonPath('can_be_retried', false)
            ->assertJsonPath('declined_reason', null)
            ->assertJsonPath('declined_message', null);
    }

    public function test_an_open_attempt_reports_pending_on_both_axes(): void
    {
        $order = $this->order(expiresAt: now()->addMinutes(10));
        $this->payment($order, Payment::STATUS_PENDING);

        $this->actingAs($this->user)->getJson($this->path($order->code))
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('order_status', 'pending')
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('can_be_retried', true);
    }

    /**
     * **El caso que motiva el endpoint.** Con un solo eje, esto sería indistinguible de la reserva
     * que nadie ha intentado pagar; con los dos, el cliente sabe que puede ofrecer «reintentar» y
     * por qué falló.
     */
    public function test_a_declined_card_is_reported_with_its_reason_and_can_be_retried(): void
    {
        $order = $this->order(expiresAt: now()->addMinutes(10));
        $this->payment($order, Payment::STATUS_FAILED, ['raw_response' => ['Ds_Response' => '0101']]);

        $response = $this->actingAs($this->user)->getJson($this->path($order->code));

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonPath('order_status', 'pending')
            ->assertJsonPath('payment_status', 'failed')
            ->assertJsonPath('can_be_retried', true)
            // El CÓDIGO es lo que el cliente programa; el mensaje, lo que muestra.
            ->assertJsonPath('declined_reason', 'card_expired');

        $this->assertNotSame('', (string) $response->json('declined_message'));
        $this->assertNotSame('card_expired', $response->json('declined_message'));
    }

    public function test_an_unknown_gateway_code_falls_back_to_the_generic_reason(): void
    {
        $order = $this->order(expiresAt: now()->addMinutes(10));
        $this->payment($order, Payment::STATUS_FAILED, ['raw_response' => ['Ds_Response' => '7777']]);

        $this->actingAs($this->user)->getJson($this->path($order->code))
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('declined_reason', 'default');
    }

    /**
     * Tras reintentar, el rechazo anterior deja de anunciarse: enseñarlo mientras hay otro cobro en
     * curso le diría al cliente que su tarjeta ha fallado cuando está esperando respuesta.
     */
    public function test_a_new_attempt_hides_the_reason_of_the_previous_decline(): void
    {
        $order = $this->order(expiresAt: now()->addMinutes(10));
        $this->payment($order, Payment::STATUS_FAILED, ['raw_response' => ['Ds_Response' => '0101']]);
        $this->payment($order, Payment::STATUS_PENDING);

        $this->actingAs($this->user)->getJson($this->path($order->code))
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('declined_reason', null)
            ->assertJsonPath('declined_message', null);
    }

    /** Manda el cobro que triunfó, no el orden de creación (`PAY-01`). */
    public function test_a_paid_order_reports_paid_even_if_another_attempt_was_opened_later(): void
    {
        $order = $this->order(status: Order::STATUS_PAID);
        $this->payment($order, Payment::STATUS_PAID, ['paid_at' => now()]);
        $this->payment($order, Payment::STATUS_PENDING);

        $this->actingAs($this->user)->getJson($this->path($order->code))
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('order_status', 'paid')
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('can_be_retried', false);
    }

    public function test_a_superseded_attempt_is_reported_as_such(): void
    {
        $order = $this->order(expiresAt: now()->addMinutes(10));
        $this->payment($order, Payment::STATUS_SUPERSEDED);

        $this->actingAs($this->user)->getJson($this->path($order->code))
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('payment_status', 'superseded');
    }

    /**
     * Un hold vencido sale `expired` aunque el barrido periódico no haya pasado: quien sondea
     * necesita saber YA que su plaza volvió al inventario, no en el próximo tick del scheduler.
     */
    public function test_an_expired_hold_is_reported_before_the_sweeper_runs(): void
    {
        $order = $this->order(expiresAt: now()->subMinute());
        $this->payment($order, Payment::STATUS_FAILED, ['raw_response' => ['Ds_Response' => '0190']]);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status, 'la columna sigue pendiente');

        $this->actingAs($this->user)->getJson($this->path($order->code))
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('order_status', 'expired')
            ->assertJsonPath('can_be_retried', false);
    }

    // ── Titularidad y sondeo ──────────────────────────────────────────────────────────────────

    public function test_a_stranger_gets_the_same_404_as_an_invented_code(): void
    {
        $order = $this->order(expiresAt: now()->addMinutes(10));

        $this->actingAs(User::factory()->create())->getJson($this->path($order->code))
            ->assertNotFound()
            ->assertValidResponse(404);

        $this->actingAs($this->user)->getJson($this->path('NO-EXISTE'))
            ->assertNotFound()
            ->assertValidResponse(404);
    }

    public function test_polling_requires_a_session(): void
    {
        $order = $this->order(expiresAt: now()->addMinutes(10));

        $this->getJson($this->path($order->code))
            ->assertUnauthorized()
            ->assertValidResponse(401);
    }

    /**
     * `RGPD-04`: toda respuesta autenticada de la API va `no-store`. En un endpoint que se consulta
     * en bucle importa el doble — es el que más veces pasaría por una caché intermedia.
     */
    public function test_the_polling_response_is_not_stored(): void
    {
        $order = $this->order(expiresAt: now()->addMinutes(10));

        $response = $this->actingAs($this->user)->getJson($this->path($order->code))->assertOk();

        // Se comprueba la DIRECTIVA, no la cadena: Symfony normaliza y reordena `Cache-Control`,
        // así que asertar el literal ataría el test a un detalle del framework.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /**
     * ⚠️ `PAY-01`: consultar NO confirma. Si sondear pudiera dar un pedido por pagado, sería una
     * segunda vía a `paid` sin la firma de la pasarela delante.
     */
    public function test_polling_never_transitions_the_order(): void
    {
        $order = $this->order(expiresAt: now()->addMinutes(10));
        $this->payment($order, Payment::STATUS_PENDING);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->user)->getJson($this->path($order->code))->assertOk();
        }

        $order->refresh();
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertSame(1, Payment::count(), 'sondear no puede abrir cobros');
        $this->assertSame(Payment::STATUS_PENDING, Payment::firstOrFail()->status);
    }
}
