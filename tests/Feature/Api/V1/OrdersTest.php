<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ReservationAdmissionPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Exceptions\PaymentInitiationException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentInitiator;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 4c — `POST /api/v1/orders`, `GET orders/{code}` y `POST orders/{code}/payment`.
 *
 * Aquí no hay contrato de dominio nuevo que probar: la admisión, la creación y la ida del pago ya
 * tienen los suyos. Lo que estos tests vigilan es la **SECUENCIA**, que es donde está el riesgo del
 * paso y lo único que ninguna guarda de arquitectura puede ver — un controlador que llama a los
 * servicios correctos en el orden equivocado pasa `ApiBoundariesTest` igual (spec §10, punto 6).
 *
 * Hay un test por cada punto del orden:
 *  1. la admisión se consulta ANTES de crear, y un veredicto denegado no deja pedido;
 *  2. el pedido nace con su ventana de retención SIEMPRE (`AFORO-10`);
 *  3. el cobro se abre sobre el pedido ya persistido;
 *  4. si el cobro no se abre, el primer intento SUELTA el pedido y el reintento NO lo toca.
 */
class OrdersTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/orders';

    private User $user;

    private Zone $zone;

    private TicketType $entry;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 10, 'online_capacity' => 10,
            ]);
        }

        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 990]);
    }

    /** Pausa de reservas del panel (#218). La lee `MaintenanceSettings::reservationsPaused()`. */
    private function pauseReservations(): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1']);
        Setting::flushMemo();
    }

    /** @return array<string, mixed> */
    private function cart(int $quantity = 2, string $time = '10:00:00'): array
    {
        return ['items' => [[
            'product_id' => $this->entry->id, 'date' => $this->date, 'time' => $time, 'quantity' => $quantity,
        ]]];
    }

    // ── Crear la reserva ──────────────────────────────────────────────────────────────────────

    public function test_it_creates_the_order_and_returns_the_signed_gateway_form(): void
    {
        $response = $this->actingAs($this->user)->postJson(self::PATH, $this->cart());

        $response->assertCreated()->assertValidRequest()->assertValidResponse(201);

        $order = Order::firstOrFail();
        $response->assertJsonPath('order.code', $order->code)
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.total_cents', 1980)
            ->assertJsonPath('order.online_amount_cents', 1980)
            ->assertJsonPath('payment.provider', 'redsys')
            ->assertJsonPath('payment.method', 'POST');

        // El formulario va firmado y el cliente lo reenvía tal cual.
        $this->assertStringStartsWith('https://', (string) $response->json('payment.url'));
        $this->assertArrayHasKey('Ds_MerchantParameters', $response->json('payment.fields'));
        $this->assertArrayHasKey('Ds_Signature', $response->json('payment.fields'));
        $this->assertNotSame('', $response->json('payment.fields.Ds_Signature'));

        // El cobro se abrió SOBRE el pedido ya persistido: el `Payment` lo ata por su morph.
        $payment = Payment::firstOrFail();
        $this->assertSame($order->getMorphClass(), $payment->payable_type);
        $this->assertSame($order->id, (int) $payment->payable_id);
        $this->assertSame($order->onlineDueCents(), (int) $payment->amount);
    }

    /**
     * `AFORO-10` — el pedido nace AUTO-LIBERABLE. El tercer parámetro de `createPendingOrder()` no
     * es opcional en la práctica: su default es un pedido FIRME que no caduca, así que omitirlo
     * dejaría aforo retenido para siempre en cuanto un cliente abandonase el pago.
     */
    public function test_the_created_order_always_carries_its_hold(): void
    {
        $this->actingAs($this->user)->postJson(self::PATH, $this->cart())->assertCreated();

        $order = Order::firstOrFail();

        $this->assertNotNull($order->expires_at, 'un pedido sin `expires_at` retiene aforo para siempre');
        $this->assertTrue($order->expires_at->isFuture());
        $this->assertEqualsWithDelta(
            PaymentSettings::holdMinutes(),
            now()->diffInMinutes($order->expires_at),
            1.0,
            'la ventana de retención no es la configurada'
        );
    }

    // ── (1) La admisión va antes, y denegada no crea nada ─────────────────────────────────────

    public function test_a_paused_installation_refuses_to_create_and_leaves_no_order(): void
    {
        $this->pauseReservations();

        $response = $this->actingAs($this->user)->postJson(self::PATH, $this->cart());

        $response->assertStatus(409)->assertValidResponse(409)
            ->assertJsonPath('error.code', 'reservations_paused');

        $this->assertSame(0, Order::count(), 'la pausa tiene que cortar ANTES de crear el pedido');
        $this->assertSame(0, Payment::count());
    }

    public function test_too_many_live_pending_orders_refuse_the_creation(): void
    {
        for ($i = 0; $i < ReservationAdmissionPolicy::MAX_PENDING_PER_USER; $i++) {
            Order::create([
                'user_id' => $this->user->id, 'code' => 'PEND-'.$i, 'status' => Order::STATUS_PENDING,
                'subtotal' => 100, 'tax' => 0, 'total' => 100, 'currency' => 'EUR',
                'expires_at' => now()->addHour(),
            ]);
        }

        $response = $this->actingAs($this->user)->postJson(self::PATH, $this->cart());

        $response->assertStatus(409)->assertValidResponse(409)
            ->assertJsonPath('error.code', 'too_many_pending_orders')
            ->assertJsonPath('error.params.max', ReservationAdmissionPolicy::MAX_PENDING_PER_USER);

        $this->assertSame(ReservationAdmissionPolicy::MAX_PENDING_PER_USER, Order::count());
    }

    /**
     * El limitador de FRECUENCIA responde 429 y no 409: es lo único de los tres que se arregla
     * esperando, y 429 es exactamente lo que un cliente sensato sabe reintentar.
     */
    public function test_creating_reservations_too_fast_is_rate_limited(): void
    {
        for ($i = 0; $i < ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE; $i++) {
            RateLimiter::hit('reservation-confirm:'.$this->user->id, 60);
        }

        $this->actingAs($this->user)->postJson(self::PATH, $this->cart())
            ->assertStatus(429)
            ->assertValidResponse(429)
            ->assertJsonPath('error.code', 'too_many_requests');

        $this->assertSame(0, Order::count());
    }

    /** Y la admisión CONSUME: crear una reserva gasta una ficha del limitador. */
    public function test_creating_a_reservation_consumes_one_attempt(): void
    {
        $this->actingAs($this->user)->postJson(self::PATH, $this->cart())->assertCreated();

        $this->assertSame(1, RateLimiter::attempts('reservation-confirm:'.$this->user->id));
    }

    // ── Rechazos del dominio, con su código de negocio ────────────────────────────────────────

    /**
     * Un rechazo del dominio sale con **su** código y con los datos de la línea culpable: sin
     * `params`, el cliente pintaría «El producto — no está disponible» (spec §4.3).
     */
    public function test_a_sold_out_slot_answers_with_its_own_code_and_the_guilty_line(): void
    {
        $response = $this->actingAs($this->user)->postJson(self::PATH, $this->cart(quantity: 11));

        $response->assertStatus(422)->assertValidResponse(422)
            ->assertJsonPath('error.code', 'line_sold_out')
            ->assertJsonPath('error.params.product', 'Jump · 1 hora');

        $this->assertStringContainsString($this->date, (string) $response->json('error.params.when'));
        $this->assertSame(0, Order::count());
    }

    public function test_a_product_that_is_not_on_sale_answers_line_unavailable(): void
    {
        $this->entry->update(['is_sellable' => false]);

        $this->actingAs($this->user)->postJson(self::PATH, $this->cart())
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.code', 'line_unavailable');
    }

    // ── (4) El cobro no se abre ───────────────────────────────────────────────────────────────

    /**
     * Si la pasarela no abre, el pedido **se suelta en el acto**: retendría una plaza que nadie va a
     * pagar. Es decisión del llamante y no del initiator porque en un reintento la respuesta
     * correcta es la contraria (ver más abajo).
     */
    public function test_a_gateway_that_does_not_open_releases_the_order(): void
    {
        $this->app->bind(PaymentInitiator::class, fn () => new class extends PaymentInitiator
        {
            public function __construct() {}

            public function open($order, ?string $preferredLocale = null, string $source = self::SOURCE_CHECKOUT): never
            {
                throw new PaymentInitiationException('la pasarela no responde');
            }
        });

        $response = $this->actingAs($this->user)->postJson(self::PATH, $this->cart());

        $response->assertStatus(502)->assertValidResponse(502)
            ->assertJsonPath('error.code', 'payment_unavailable');

        $order = Order::firstOrFail();
        $this->assertSame(Order::STATUS_EXPIRED, $order->status, 'el pedido tiene que soltarse');
        $this->assertTrue($order->expires_at->isPast(), 'su retención tiene que quedar vencida');
    }

    // ── Titularidad ───────────────────────────────────────────────────────────────────────────

    public function test_creating_an_order_requires_a_session(): void
    {
        $this->postJson(self::PATH, $this->cart())
            ->assertUnauthorized()
            ->assertValidResponse(401);

        $this->assertSame(0, Order::count());
    }

    public function test_the_owner_can_fetch_the_order_and_a_stranger_cannot(): void
    {
        $this->actingAs($this->user)->postJson(self::PATH, $this->cart())->assertCreated();
        $code = Order::firstOrFail()->code;

        $this->actingAs($this->user)->getJson(self::PATH.'/'.$code)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('code', $code);

        // Un código ajeno da 404, no 403: decir «existe pero no es tuyo» sería un oráculo.
        $this->actingAs(User::factory()->create())->getJson(self::PATH.'/'.$code)
            ->assertNotFound()
            ->assertValidResponse(404);
    }

    // ── Reintento del cobro ───────────────────────────────────────────────────────────────────

    public function test_the_retry_opens_a_new_payment_and_extends_the_hold(): void
    {
        $this->actingAs($this->user)->postJson(self::PATH, $this->cart())->assertCreated();
        $order = Order::firstOrFail();
        $firstPayment = Payment::firstOrFail();

        // El hold se acerca a su fin: la extensión tiene que notarse.
        $order->forceFill(['expires_at' => now()->addMinute()])->save();

        $response = $this->actingAs($this->user)->postJson(self::PATH.'/'.$order->code.'/payment');

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonPath('order.code', $order->code);

        $order->refresh();
        $this->assertTrue(
            $order->expires_at->gt(now()->addMinutes(2)),
            'el reintento tiene que EXTENDER la retención de aforo (PAY-04)'
        );

        // Un `Payment` NUEVO (la pasarela exige `gateway_order` único de por vida) y el anterior
        // marcado `SUPERSEDED`, que es lo que evita tickets duplicados si se autorizase tarde.
        $this->assertSame(2, Payment::count());
        $this->assertSame(Payment::STATUS_SUPERSEDED, $firstPayment->refresh()->status);
    }

    /**
     * El pedido de otro titular responde `order_not_retryable`, igual que un código inventado: los
     * dos casos son indistinguibles a propósito. Y sobre todo, **no se le toca el hold**.
     */
    public function test_a_stranger_cannot_retry_someone_elses_order(): void
    {
        $this->actingAs($this->user)->postJson(self::PATH, $this->cart())->assertCreated();
        $order = Order::firstOrFail();
        $originalHold = $order->expires_at;

        $this->actingAs(User::factory()->create())
            ->postJson(self::PATH.'/'.$order->code.'/payment')
            ->assertStatus(409)
            ->assertValidResponse(409)
            ->assertJsonPath('error.code', 'order_not_retryable');

        $this->assertTrue($order->refresh()->expires_at->eq($originalHold), 'no se toca el pedido ajeno');
        $this->assertSame(1, Payment::count());

        $this->actingAs($this->user)->postJson(self::PATH.'/INVENTADO/payment')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'order_not_retryable');
    }

    public function test_a_paused_installation_refuses_the_retry_without_touching_the_order(): void
    {
        $this->actingAs($this->user)->postJson(self::PATH, $this->cart())->assertCreated();
        $order = Order::firstOrFail();
        $originalHold = $order->expires_at;

        $this->pauseReservations();

        $this->actingAs($this->user)->postJson(self::PATH.'/'.$order->code.'/payment')
            ->assertStatus(409)
            ->assertValidResponse(409)
            ->assertJsonPath('error.code', 'reservations_paused');

        $this->assertTrue($order->refresh()->expires_at->eq($originalHold));
    }

    /**
     * La diferencia deliberada con la creación: si el cobro no se reabre, **el pedido NO se toca**.
     * Sigue vivo con su hold recién extendido y se puede volver a intentar; soltarlo aquí le
     * quitaría la plaza a quien solo tuvo mala suerte con la pasarela.
     */
    public function test_a_gateway_failure_on_retry_leaves_the_order_alive(): void
    {
        $this->actingAs($this->user)->postJson(self::PATH, $this->cart())->assertCreated();
        $order = Order::firstOrFail();

        $this->app->bind(PaymentInitiator::class, fn () => new class extends PaymentInitiator
        {
            public function __construct() {}

            public function reopen($order, ?string $preferredLocale = null, string $source = self::SOURCE_RETRY_SIDEBAR): never
            {
                throw new PaymentInitiationException('la pasarela no responde');
            }
        });

        $this->actingAs($this->user)->postJson(self::PATH.'/'.$order->code.'/payment')
            ->assertStatus(502)
            ->assertValidResponse(502)
            ->assertJsonPath('error.code', 'payment_unavailable');

        $order->refresh();
        $this->assertSame(Order::STATUS_PENDING, $order->status, 'el pedido sigue vivo tras un reintento fallido');
        $this->assertTrue($order->expires_at->isFuture());
    }

    public function test_the_retry_requires_a_session(): void
    {
        $this->actingAs($this->user)->postJson(self::PATH, $this->cart())->assertCreated();
        $code = Order::firstOrFail()->code;

        $this->app['auth']->forgetGuards();

        $this->postJson(self::PATH.'/'.$code.'/payment')
            ->assertUnauthorized()
            ->assertValidResponse(401);
    }
}
