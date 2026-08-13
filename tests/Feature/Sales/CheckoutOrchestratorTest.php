<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\AdmissionDecision;
use App\Domain\Booking\Contracts\PaymentInitiation;
use App\Domain\Booking\Contracts\ReservationCheckout;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ReservationAdmissionPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Contracts\PaymentInitiationException;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cierre de Fase 3 — la SECUENCIA de la compra (`docs/specs/checkout-orquestado.md`).
 *
 * `CheckoutOrchestrator` no contiene ninguna regla nueva: la admisión, la creación y la ida del pago
 * ya existían y ya tienen sus propios tests. Lo que esta clase aporta —y lo único que estos tests
 * vigilan— es el **ORDEN**, que es justo lo que ninguna guarda de arquitectura sabe ver: un
 * orquestador que llama a los tres servicios correctos en el orden equivocado pasa
 * `ModuleBoundariesTest` y `ApiBoundariesTest` con nota.
 *
 * Hay un caso por punto del orden, y se ha comprobado POR MUTACIÓN que cada uno muerde (el detalle
 * de qué cae con cada mutación está en `DECISIONES` y en el §6 del spec). Antes de esta clase, la
 * secuencia estaba escrita a mano en cinco puntos de cuatro clases de entrega, y estos tests solo
 * existían —cuando existían— a nivel de endpoint HTTP.
 */
class CheckoutOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TicketType $entry;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        Slot::create([
            'zone_id' => $zone->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 990]);
    }

    private function checkout(): ReservationCheckout
    {
        return app(ReservationCheckout::class);
    }

    /** @return array<int, array{ticket_type_id:int, date:string, time:string, qty:int}> */
    private function cart(int $qty = 2): array
    {
        return [['ticket_type_id' => (int) $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => $qty]];
    }

    /** Sustituye la pasarela por una que NUNCA abre el cobro. */
    private function breakTheGateway(): void
    {
        $this->app->bind(PaymentInitiation::class, fn () => new class implements PaymentInitiation
        {
            public function open($order, ?string $preferredLocale, string $source): never
            {
                throw new PaymentInitiationException('la pasarela no responde');
            }

            public function reopen($order, ?string $preferredLocale, string $source): never
            {
                throw new PaymentInitiationException('la pasarela no responde');
            }
        });
    }

    // ── (1) La admisión va ANTES y CONSUME ────────────────────────────────────────────────────

    /**
     * Un veredicto denegado corta antes de tocar nada: ni pedido ni cobro.
     */
    public function test_a_denied_admission_creates_nothing(): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1']);
        Setting::flushMemo();

        $outcome = $this->checkout()->start($this->user, $this->cart(), ReservationCheckout::SOURCE_CHECKOUT);

        $this->assertTrue($outcome->denied());
        $this->assertSame(AdmissionDecision::RESERVATIONS_PAUSED, $outcome->denial?->reason);
        $this->assertSame(0, Order::count(), 'la pausa tiene que cortar ANTES de crear el pedido');
        $this->assertSame(0, Payment::count());
    }

    /**
     * **La mitad del trabajo de la admisión es CONSUMIR.** Si el orquestador usara la variante que
     * solo consulta (`mayReserve()`), este test seguiría creando pedidos más allá del límite: un
     * chequeo que no cuenta no limita nada, y es el hallazgo E del origen (agotar el aforo del día
     * sin pagar nada).
     */
    public function test_creating_reservations_consumes_the_rate_limiter(): void
    {
        for ($i = 0; $i < ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE; $i++) {
            $this->assertFalse(
                $this->checkout()->start($this->user, $this->cart(1), ReservationCheckout::SOURCE_CHECKOUT)->denied()
            );
        }

        $outcome = $this->checkout()->start($this->user, $this->cart(1), ReservationCheckout::SOURCE_CHECKOUT);

        $this->assertTrue($outcome->denied(), 'la admisión no está consumiendo ficha');
        $this->assertSame(AdmissionDecision::RATE_LIMITED, $outcome->denial?->reason);
    }

    // ── (2) El pedido nace con su ventana de retención (`AFORO-10`) ───────────────────────────

    /**
     * `AFORO-10` — el pedido pendiente nace AUTO-LIBERABLE, y **la ventana la posee el orquestador**:
     * no se recibe por parámetro, justo para que ninguna superficie futura pueda alargarla. El
     * default de `createPendingOrder()` es un pedido FIRME que no caduca, así que omitir el hold
     * retendría aforo para siempre en cuanto un cliente abandonase el pago.
     */
    public function test_the_created_order_always_carries_its_hold(): void
    {
        $outcome = $this->checkout()->start($this->user, $this->cart(), ReservationCheckout::SOURCE_CHECKOUT);

        $order = $outcome->order;

        $this->assertNotNull($order);
        $this->assertNotNull($order->expires_at, 'un pedido sin `expires_at` retiene aforo para siempre');
        $this->assertTrue($order->expires_at->isFuture());
        $this->assertEqualsWithDelta(
            PaymentSettings::holdMinutes(),
            now()->diffInMinutes($order->expires_at),
            1.0,
            'la ventana de retención no es la configurada en `sales.hold_minutes`'
        );
    }

    // ── (3) El cobro se abre SOBRE el pedido ya persistido ────────────────────────────────────

    /**
     * El `Payment` ata `gateway_order ↔ Order` en base de datos, y ese vínculo es la única forma
     * robusta de reconocer el pedido cuando la pasarela responda: su vuelta llega sin sesión válida
     * (POST cross-site con `SameSite=Lax`).
     */
    public function test_the_charge_opens_on_the_already_persisted_order(): void
    {
        $outcome = $this->checkout()->start($this->user, $this->cart(), ReservationCheckout::SOURCE_CHECKOUT);

        $order = $outcome->order;
        $payment = Payment::firstOrFail();

        $this->assertNotNull($order);
        $this->assertNotNull($outcome->ticket);
        $this->assertSame($order->getMorphClass(), $payment->payable_type);
        $this->assertSame($order->id, (int) $payment->payable_id);
        $this->assertSame($order->onlineDueCents(), (int) $payment->amount);
        $this->assertSame($payment->id, $outcome->ticket->payment->id);
    }

    // ── (4) La compensación es ASIMÉTRICA ─────────────────────────────────────────────────────

    /**
     * Primer cobro que no abre → **el pedido se suelta en el acto**: retendría una plaza que nadie
     * va a pagar, y esperar a `orders:expire` la mantendría bloqueada toda la ventana.
     */
    public function test_a_gateway_that_does_not_open_releases_the_order(): void
    {
        $this->breakTheGateway();

        try {
            $this->checkout()->start($this->user, $this->cart(), ReservationCheckout::SOURCE_CHECKOUT);
            $this->fail('se esperaba PaymentInitiationException');
        } catch (PaymentInitiationException) {
            // La disculpa que ve el cliente es de quien captura; aquí solo importa el pedido.
        }

        $order = Order::firstOrFail();

        $this->assertSame(Order::STATUS_EXPIRED, $order->status, 'el pedido tiene que soltarse');
        $this->assertTrue($order->expires_at->isPast(), 'su retención tiene que quedar vencida');
    }

    /**
     * Reintento que no abre → **el pedido NO se toca**. Es la asimetría deliberada con `start()`: la
     * reserva sigue viva con su hold recién extendido, así que el cliente puede volver a intentarlo.
     * Soltarla aquí le quitaría la plaza por un fallo que no es suyo.
     */
    public function test_a_gateway_failure_on_retry_leaves_the_order_alive(): void
    {
        $order = $this->checkout()
            ->start($this->user, $this->cart(), ReservationCheckout::SOURCE_CHECKOUT)
            ->order;

        $this->assertNotNull($order);
        $this->breakTheGateway();

        try {
            $this->checkout()->retry($this->user, $order->code, ReservationCheckout::SOURCE_RETRY_ACCOUNT);
            $this->fail('se esperaba PaymentInitiationException');
        } catch (PaymentInitiationException) {
            // idem
        }

        $order->refresh();

        $this->assertSame(Order::STATUS_PENDING, $order->status, 'el reintento NO puede soltar el pedido');
        $this->assertTrue($order->expires_at->isFuture(), 'su retención sigue viva');
    }

    // ── Lo que NO absorbe el orquestador ──────────────────────────────────────────────────────

    /**
     * `ReservationException` sale entera: lleva los doce códigos de negocio del contrato público y
     * cada superficie los traduce a lo suyo. Y no deja estado a medias — la creación es una
     * transacción entera—, pero **la ficha del limitador ya se consumió**, igual que antes de esta
     * clase. Se asevera para que nadie lo «arregle» moviendo la admisión detrás de la creación:
     * eso reabriría el agujero de agotar el aforo iterando cestas inválidas.
     */
    public function test_an_unsellable_cart_propagates_and_leaves_no_order(): void
    {
        try {
            $this->checkout()->start($this->user, [], ReservationCheckout::SOURCE_CHECKOUT);
            $this->fail('se esperaba ReservationException');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.cart_empty', $e->getMessage());
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Payment::count());

        // La ficha se gastó: quedan RESERVATIONS_PER_MINUTE - 1 intentos válidos.
        for ($i = 0; $i < ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE - 1; $i++) {
            $this->assertFalse(
                $this->checkout()->start($this->user, $this->cart(1), ReservationCheckout::SOURCE_CHECKOUT)->denied()
            );
        }

        $this->assertTrue(
            $this->checkout()->start($this->user, $this->cart(1), ReservationCheckout::SOURCE_CHECKOUT)->denied(),
            'la cesta inválida tenía que haber consumido su ficha'
        );
    }

    /**
     * **`PAY-05` — la incidencia SOBREVIVE al fallo**, y esto es lo que prueba que el orquestador no
     * envuelve la secuencia en una transacción.
     *
     * El rastro `orders.payment_init_failed` lo escribe el initiator antes de lanzar, y es lo que ve
     * la operadora en el historial del pedido para explicar por qué se caducó «sin motivo». Con una
     * transacción abarcadora, la compensación haría rollback del audit junto con todo lo demás y la
     * incidencia desaparecería sin que nada avisara. (El otro motivo para no envolverlo es
     * `AFORO-01`: el `lockForUpdate` de las franjas quedaría sostenido durante la firma del payload.)
     */
    public function test_a_failed_start_keeps_its_incident_trace(): void
    {
        $this->app->bind(PaymentInitiation::class, fn () => new class implements PaymentInitiation
        {
            public function open($order, ?string $preferredLocale, string $source): never
            {
                // Lo que hace el initiator real antes de lanzar (`PAY-05`).
                AuditLogger::log('orders.payment_init_failed', $order, [
                    'order_code' => $order->code, 'source' => $source,
                ]);

                throw new PaymentInitiationException('la pasarela no responde');
            }

            public function reopen($order, ?string $preferredLocale, string $source): never
            {
                throw new PaymentInitiationException('la pasarela no responde');
            }
        });

        try {
            $this->checkout()->start($this->user, $this->cart(), ReservationCheckout::SOURCE_CHECKOUT);
        } catch (PaymentInitiationException) {
            // esperado
        }

        $this->assertSame(
            1,
            AuditLog::where('action', 'orders.payment_init_failed')->count(),
            'la incidencia ha desaparecido: ¿hay una transacción envolviendo la secuencia?'
        );
        $this->assertSame(Order::STATUS_EXPIRED, Order::firstOrFail()->status);
    }
}
