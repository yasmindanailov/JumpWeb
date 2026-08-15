<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ReservationAdmissionPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\Redsys;
use App\Domain\Payments\Services\Redsys\Vendor\Utils;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Fase 5.5b — IDA al sandbox Redsys (#104).
 *
 * Lo que se compra deja tras de sí:
 *   1. La reserva firme (`Order` pending) con su ventana de retención.
 *   2. Un `gateway_order` ÚNICO (atómico).
 *   3. Un `Payment` `pending` que ata `gateway_order → Order` ANTES de redirigir
 *      (clave para reconciliar la vuelta sin sesión).
 *   4. El payload FIRMADO por `App\Domain\Payments\Services\Redsys` (server-side, regla 12 SEGURIDAD).
 *
 * El email de confirmación de pedido (OrderConfirmation) NO se envía en esta capa: se
 * mueve a 5.5c (sobre la rama Ds_Response ∈ 0000–0099). Hasta entonces, no decimos al
 * cliente que su reserva está "confirmada" — solo está pendiente de pago.
 *
 * ⚠️ **Se conduce por `POST /api/v1/orders`, no por el sidebar Livewire** (Fase 4 · paso 4.7·2b·2,
 * `DECISIONES #65`). El sujeto de este fichero es el PAYLOAD que sale hacia la pasarela —importe,
 * moneda, datos de comercio, URLs, idioma, ASCII y firma—, y eso es servidor puro: no tiene por qué
 * morir con una vista. La superficie que sobrevive a la retirada es la que usa el cajón SPA, y
 * produce **exactamente el mismo payload** porque las dos pasan por el mismo orquestador
 * (`CheckoutOrchestrator` → `PaymentInitiation::open()`, `DECISIONES #37`) con el mismo `source`
 * (`checkout`) y el mismo idioma (`$user->locale`).
 *
 * ⚠️ **Y la traducción de nombres importa**: lo que el componente publicaba en `redsysFormData` es el
 * payload CRUDO del proveedor (`gatewayUrl`/`signatureVersion`/`params`/`signature`); la API publica
 * los mismos datos con los **nombres reales de los `<input>`** (`payment.url` +
 * `payment.fields['Ds_*']`), que es lo que documenta `PaymentTicket::gatewayFields()`. No son dos
 * formatos: es el mismo dato dicho para quien no tiene plantilla donde mirarlo.
 */
class RedsysIdaTest extends TestCase
{
    use RefreshDatabase;

    private string $today;

    private Zone $zone;

    private TicketType $jump1h;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08'); // lunes (tarifa normal)
        $this->today = Carbon::today()->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);

        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->today,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);

        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->jump1h->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'),
            'amount_cents' => 1000,
        ]);

        // Sandbox público de Redsys (#104). Los tests no llaman a la pasarela real (se
        // detienen ANTES del envío); este seed solo asegura que `Setting::value` devuelve
        // lo esperado por el builder. Ver `database/seeders/LandingContentSeeder.php`.
        Setting::create(['key' => 'redsys_environment', 'value' => 'test', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_merchant_code', 'value' => '999008881', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_terminal', 'value' => '001', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_secret_key', 'value' => 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_currency', 'value' => '978', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_merchant_name', 'value' => 'SaltoPark', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_next_gateway_order', 'value' => '100000', 'group' => 'payment']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Compra una entrada por la superficie que SOBREVIVE a la retirada del sidebar Livewire.
     *
     * Devuelve el sobre `payment` tal y como lo publica el contrato —`url` + `fields` con los nombres
     * reales de los `<input>`— porque es lo que de verdad viaja a la pasarela. Los casos de abajo
     * decodifican `Ds_MerchantParameters` y miran dentro: ahí está el sujeto del fichero.
     *
     * @return array{0: array<string, mixed>, 1: User}
     */
    private function buyOneAsVerifiedUser(?User $user = null): array
    {
        $user = $user ?? User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/orders', ['items' => [[
            'product_id' => $this->jump1h->id, 'date' => $this->today, 'time' => '10:00:00', 'quantity' => 1,
        ]]]);

        $response->assertCreated();

        return [(array) $response->json('payment'), $user];
    }

    /** Los tres campos firmados, con los nombres que exige la pasarela. */
    private function fields(array $payment): array
    {
        return (array) $payment['fields'];
    }

    /**
     * El `gateway_order` y el `Payment` que deja la ida, que es lo específico de Redsys.
     *
     * ⚠️ Lo que este caso ya NO comprueba, y dónde vive: que el pedido nazca `pending` con su
     * `expires_at` lo fija `Api\V1\OrdersTest::test_the_created_order_always_carries_its_hold`, y que
     * el sidebar Livewire avance al paso 9 con la cesta vacía lo fija `PurchasePanelTest` (paso 8 →
     * `confirmReservation` → 9, `confirmed` false, `cart` vacía). Aquí queda lo que no cubre nadie
     * más: **la FORMA del `gateway_order`**, que es requisito del manual de Redsys §5.
     */
    public function test_the_ida_leaves_a_pending_payment_with_a_well_formed_gateway_order(): void
    {
        [, $user] = $this->buyOneAsVerifiedUser();

        $order = Order::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertNotNull($order->expires_at);
        $this->assertSame(1000, $order->total);          // céntimos, servidor

        $payment = $order->payments()->firstOrFail();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(1000, $payment->amount);       // importe = total del pedido (no client-trust)
        $this->assertSame('redsys', $payment->provider);
        $this->assertNotNull($payment->gateway_order);
        $this->assertSame(10, strlen($payment->gateway_order));
        $this->assertTrue(ctype_digit(substr($payment->gateway_order, 0, 4)), '4 primeros dígitos numéricos (Redsys §5)');
    }

    public function test_redsys_form_data_carries_signed_payload_and_sandbox_url(): void
    {
        [$payment] = $this->buyOneAsVerifiedUser();

        $fields = $this->fields($payment);

        $this->assertSame(Redsys::URL_TEST, $payment['url']);
        $this->assertSame(Redsys::SIGNATURE_VERSION, $fields['Ds_SignatureVersion']);
        $this->assertNotEmpty($fields['Ds_MerchantParameters']);
        $this->assertNotEmpty($fields['Ds_Signature']);

        // Firma server-side reproducible: re-firmando los mismos params con la clave del
        // sandbox debe coincidir bit a bit (round-trip). Si no, hay manipulación o bug.
        $expected = (new Redsys)->createMerchantSignature(
            'sq7HjrUOBfKmC576ILgskD5srU870gJ7',
            $fields['Ds_MerchantParameters'],
            // El gateway_order va en el propio payload — lo decodificamos para extraerlo.
            (new Redsys)->decodeMerchantParameters($fields['Ds_MerchantParameters'])['DS_MERCHANT_ORDER'],
        );
        $this->assertSame($expected, $fields['Ds_Signature']);
    }

    public function test_payload_amount_currency_and_merchant_data_come_from_the_server(): void
    {
        [$payment, $user] = $this->buyOneAsVerifiedUser();

        $data = (new Redsys)->decodeMerchantParameters($this->fields($payment)['Ds_MerchantParameters']);
        $order = Order::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('1000', $data['DS_MERCHANT_AMOUNT']);    // céntimos, NO 10.00
        $this->assertSame('978', $data['DS_MERCHANT_CURRENCY']);   // EUR ISO-4217
        $this->assertSame('0', $data['DS_MERCHANT_TRANSACTIONTYPE']); // 0 = autorización
        $this->assertSame('999008881', $data['DS_MERCHANT_MERCHANTCODE']);
        $this->assertSame('001', $data['DS_MERCHANT_TERMINAL']);
        $this->assertSame($order->code, $data['DS_MERCHANT_MERCHANTDATA']); // reconciliación cruzada
        $this->assertSame('SaltoPark', $data['DS_MERCHANT_MERCHANTNAME']);
        $this->assertNotEmpty($data['DS_MERCHANT_PRODUCTDESCRIPTION']);
        $this->assertLessThanOrEqual(125, mb_strlen($data['DS_MERCHANT_PRODUCTDESCRIPTION'])); // manual Anexo 1
    }

    public function test_ida_refuses_a_zero_or_negative_amount(): void
    {
        // Auditoría Fase 1 (M1): backstop de importe. Redsys rechaza un cobro de 0 con un SIS error
        // genérico y dejaría el aforo retenido sin pista; preferimos fallar rápido y con mensaje
        // propio (el llamador lo registra como `payment_init_failed`).
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-ZERO',
            'status' => Order::STATUS_PENDING, 'subtotal' => 0, 'total' => 0, 'currency' => 'EUR',
        ]);
        $payment = Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'redsys',
            'amount' => 0, 'currency' => 'EUR', 'status' => Payment::STATUS_PENDING,
            'gateway_order' => '0000100000',
        ]);

        $this->expectException(\RuntimeException::class);
        (new Redsys)->buildPaymentFormData($order, $payment);
    }

    public function test_payload_urls_are_absolute_and_point_to_named_routes(): void
    {
        [$payment] = $this->buyOneAsVerifiedUser();

        $data = (new Redsys)->decodeMerchantParameters($this->fields($payment)['Ds_MerchantParameters']);

        $this->assertSame(route('payments.redsys.return.ok'), $data['DS_MERCHANT_URLOK']);
        $this->assertSame(route('payments.redsys.return.ko'), $data['DS_MERCHANT_URLKO']);
        $this->assertStringStartsWith('http', $data['DS_MERCHANT_URLOK']);  // absoluta
        $this->assertStringStartsWith('http', $data['DS_MERCHANT_URLKO']);
        $this->assertLessThanOrEqual(250, strlen($data['DS_MERCHANT_URLOK']));  // manual Anexo 1
    }

    public function test_merchant_url_is_empty_until_5_5d_configures_it(): void
    {
        // Hasta 5.5d (notificación on-line) no hay URL pública configurable; el campo viaja
        // vacío y Redsys solo responde por la vuelta (UrlOK/UrlKO).
        [$payment] = $this->buyOneAsVerifiedUser();
        $data = (new Redsys)->decodeMerchantParameters($this->fields($payment)['Ds_MerchantParameters']);
        $this->assertSame('', $data['DS_MERCHANT_MERCHANTURL']);
    }

    public function test_merchant_url_is_used_when_configured_in_settings(): void
    {
        Setting::create(['key' => 'redsys_merchant_url', 'value' => 'https://example.com/notif', 'group' => 'payment']);

        [$payment] = $this->buyOneAsVerifiedUser();
        $data = (new Redsys)->decodeMerchantParameters($this->fields($payment)['Ds_MerchantParameters']);
        $this->assertSame('https://example.com/notif', $data['DS_MERCHANT_MERCHANTURL']);
    }

    /**
     * El idioma de la pasarela es el del TITULAR, y gana al de la petición.
     *
     * ⚠️ **La cabecera `Accept-Language: es` no es decorado: sin ella este caso no probaba nada**, y
     * se descubrió por mutación al re-apuntar el fichero en 4.7·2b·2. `ApiLocale` resuelve
     * sesión → `Accept-Language` → `users.locale`, así que una petición MUDA de un usuario francés ya
     * deja el locale de la app en `fr`: apagar el `$user->locale` que pasa `CheckoutOrchestrator`
     * —cuyo fallback es justamente `app()->getLocale()`— **seguía dando 004**. Con la cabecera, las dos
     * fuentes discrepan y el 004 solo puede venir del titular. Verificado: con la mutación puesta, el
     * caso cae.
     *
     * Y lo que fija de paso no lo cubría nadie: quien paga desde un dispositivo negociado en otro
     * idioma —o compartido— ve la pasarela **en el suyo**, que es lo que declara el puerto
     * (`PaymentInitiation`: «idioma del titular; `null` cae al locale de la petición»).
     */
    public function test_consumer_language_follows_the_holder_and_not_the_request(): void
    {
        $userFr = User::factory()->create(['locale' => 'fr']);

        $response = $this->actingAs($userFr)
            ->withHeader('Accept-Language', 'es')
            ->postJson('/api/v1/orders', ['items' => [[
                'product_id' => $this->jump1h->id, 'date' => $this->today, 'time' => '10:00:00', 'quantity' => 1,
            ]]]);

        $response->assertCreated();
        $this->assertSame('es', app()->getLocale(), 'la petición tiene que quedar en OTRO idioma que el titular');

        $data = (new Redsys)->decodeMerchantParameters(
            (string) $response->json('payment.fields.Ds_MerchantParameters')
        );
        $this->assertSame('004', $data['DS_MERCHANT_CONSUMERLANGUAGE']); // 004 = FR (manual Anexo 1)
    }

    public function test_order_confirmation_email_is_no_t_sent_on_redsys_ida(): void
    {
        // 5.5b: la "confirmación" para el cliente solo llega tras autorización del banco.
        // OrderConfirmation se mueve a 5.5c (sobre la rama Ds_Response 0000–0099).
        Notification::fake();

        $this->buyOneAsVerifiedUser();

        Notification::assertNothingSent();
    }

    public function test_gateway_order_is_unique_across_multiple_purchases(): void
    {
        // Dos compras consecutivas no comparten gateway_order (defensa contra Redsys 0913
        // "pedido repetido"). El contador atómico de 5.5a lo garantiza.
        [, $alice] = $this->buyOneAsVerifiedUser();
        $bob = User::factory()->create();
        $this->buyOneAsVerifiedUser($bob);

        $alicePayment = Order::where('user_id', $alice->id)->firstOrFail()->payments()->firstOrFail();
        $bobPayment = Order::where('user_id', $bob->id)->firstOrFail()->payments()->firstOrFail();

        $this->assertNotSame($alicePayment->gateway_order, $bobPayment->gateway_order);
        // Mientras el contador no se reuse, también deben ser monotónicos.
        $this->assertGreaterThan($alicePayment->gateway_order, $bobPayment->gateway_order);
    }

    public function test_order_expires_at_is_set_to_hold_window_when_redirecting(): void
    {
        // #105 (2026-05-26): con Redsys real, "firme" no es para siempre — la Order retiene
        // aforo durante `sales.hold_minutes` (15 min por defecto, ≥ timeout del TPV; #62).
        // Si el cliente no paga en esa ventana, `orders:expire` la libera. Sólo la vuelta
        // OK firmada (5.5c) la fija con `expires_at = null`.
        Setting::create(['key' => 'sales.hold_minutes', 'value' => '15', 'group' => 'payment']);

        [, $user] = $this->buyOneAsVerifiedUser();
        $order = Order::where('user_id', $user->id)->firstOrFail();

        $this->assertNotNull($order->expires_at);
        $this->assertGreaterThan(now()->addMinutes(14), $order->expires_at);
        $this->assertLessThanOrEqual(now()->addMinutes(15), $order->expires_at);
    }

    public function test_abandoned_order_does_not_count_against_pending_cap_after_expiry(): void
    {
        // Escenario validado por la clienta el 2026-05-26: clic en "Confirmar reserva" pero
        // sin completar el pago → Order queda pending con expires_at en el futuro. Tras la
        // ventana, `orders:expire` la marca expired y NO cuenta contra el cap (#92/#105).
        Setting::create(['key' => 'sales.hold_minutes', 'value' => '15', 'group' => 'payment']);

        $user = User::factory()->create();
        for ($i = 0; $i < ReservationAdmissionPolicy::MAX_PENDING_PER_USER; $i++) {
            $this->buyOneAsVerifiedUser($user);
            // Espaciar para no chocar con el rate limit (3/min).
            RateLimiter::clear('reservation-confirm:'.$user->id);
        }
        $this->assertSame(ReservationAdmissionPolicy::MAX_PENDING_PER_USER, $user->orders()->where('status', Order::STATUS_PENDING)->count());

        // Avanzamos el tiempo más allá de la ventana de retención → las órdenes caducan en BD.
        Carbon::setTestNow(now()->addMinutes(20));
        Artisan::call('orders:expire');

        // El cap mira "live pending" (status pending Y expires_at>now). Tras orders:expire,
        // los `pending` con expires_at pasado pasan a status=expired → 0 vivos.
        // El usuario debería poder hacer una nueva reserva.
        $this->buyOneAsVerifiedUser($user);
        $this->assertSame(1, $user->orders()->where('status', Order::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count());
    }

    public function test_payment_preparation_failure_is_logged_with_context(): void
    {
        // Observabilidad (auditoría 2026-05-26): si la preparación del pago falla, la traza
        // tiene que llegar a los logs CON contexto (order_id, user_id, exception class), pero
        // NUNCA con secret_key ni firma. Un `catch (\Throwable)` mudo es deuda inaceptable.
        // El canal es `payments.initiation_failed` desde Fase 3 · paso 2: las tres copias de la ida
        // del pago (checkout y los dos reintentos) loguean por el mismo sitio y se distinguen por
        // `source`, en vez de por tres nombres de evento distintos que había que conocer para
        // buscarlos.
        Log::shouldReceive('error')
            ->once()
            ->with('payments.initiation_failed', \Mockery::on(function (array $ctx): bool {
                return isset($ctx['order_id'], $ctx['order_code'], $ctx['user_id'], $ctx['error'], $ctx['exception'], $ctx['trace'])
                    && $ctx['source'] === 'checkout'
                    && ! isset($ctx['secret_key'], $ctx['signature'], $ctx['params']);
            }));

        // Forzamos un fallo: una clave Redsys vacía hace que `Signature::createMerchantSignature`
        // no produzca una firma utilizable (no throw directamente, así que probamos otra ruta):
        // mejor borrar la migración no se puede aquí; usamos un mock del servicio Redsys.
        $broken = new class extends Redsys
        {
            public function buildPaymentFormData(Order $order, Payment $payment, ?string $locale = null): array
            {
                throw new \RuntimeException('simulated_failure');
            }
        };
        app()->instance(Redsys::class, $broken);

        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/orders', ['items' => [[
            'product_id' => $this->jump1h->id, 'date' => $this->today, 'time' => '10:00:00', 'quantity' => 1,
        ]]])->assertStatus(502)->assertJsonPath('error.code', 'payment_unavailable');

        // El Order que se llegó a crear debe quedar expired (no `pending` huérfano).
        $order = Order::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(Order::STATUS_EXPIRED, $order->status);

        // Feedback ADMIN (#169): además del log de fichero, el fallo deja un
        // rastro estructurado y visible en el panel (historial del pedido).
        $audit = AuditLog::where('action', 'orders.payment_init_failed')->latest()->first();
        $this->assertNotNull($audit, 'El fallo de inicio de pago debe registrarse en audit_logs para la operadora.');
        $this->assertSame($order->id, (int) $audit->target_id);
        $this->assertSame('checkout', $audit->payload['source']);
    }

    public function test_amount_cannot_be_tampered_with_via_signature(): void
    {
        // Una manipulación del importe en el navegador invalidaría la firma server-side.
        // Lo probamos a nivel de servicio: re-firmar los params ALTERADOS con la misma
        // clave y mismo `gateway_order` produce una firma DISTINTA a la enviada
        // originalmente. Redsys rechazaría la operación (SIS0042).
        [$payment] = $this->buyOneAsVerifiedUser();
        $fields = $this->fields($payment);

        $redsys = new Redsys;
        $data = $redsys->decodeMerchantParameters($fields['Ds_MerchantParameters']);
        $originalAmount = $data['DS_MERCHANT_AMOUNT'];
        $gatewayOrder = $data['DS_MERCHANT_ORDER'];

        $data['DS_MERCHANT_AMOUNT'] = '1';     // céntimos: intento de fraude (1 cént)
        $tamperedParams = Utils::base64_url_encode_safe(json_encode($data));

        $tamperedSignature = $redsys->createMerchantSignature(
            'sq7HjrUOBfKmC576ILgskD5srU870gJ7',
            $tamperedParams,
            $gatewayOrder,
        );

        $this->assertNotSame($originalAmount, $data['DS_MERCHANT_AMOUNT']);
        $this->assertNotSame($fields['Ds_MerchantParameters'], $tamperedParams);
        $this->assertNotSame(
            $fields['Ds_Signature'],
            $tamperedSignature,
            'Tampered amount must invalidate the signature (Redsys would reject with SIS0042).',
        );
    }

    public function test_description_and_merchant_name_are_ascii_sanitized(): void
    {
        // Audit hardening #113 (M3+M4): el manual Redsys §3 advierte de evitar caracteres
        // especiales en los campos de texto. `Str::ascii()` translitera (á→a, ñ→n) y descarta
        // el resto, evitando rechazos por SIS si futuras ediciones del template o el nombre
        // del comercio (Setting editado en panel Fase 7) incluyen caracteres no-ASCII.
        Setting::updateOrCreate(['key' => 'redsys_merchant_name'], ['value' => 'Jumpingjümp · Niños', 'group' => 'payment']);

        [$payment] = $this->buyOneAsVerifiedUser();

        $redsys = new Redsys;
        $data = $redsys->decodeMerchantParameters($this->fields($payment)['Ds_MerchantParameters']);

        // Merchant name transliterado a ASCII puro (sin ü ni ñ ni el bullet ·).
        $this->assertMatchesRegularExpression('/^[\x20-\x7e]+$/', $data['DS_MERCHANT_MERCHANTNAME'], 'Merchant name debe ser ASCII puro');
        $this->assertStringContainsString('Jumping', $data['DS_MERCHANT_MERCHANTNAME']);
        $this->assertStringNotContainsString('ü', $data['DS_MERCHANT_MERCHANTNAME']);
        $this->assertStringNotContainsString('·', $data['DS_MERCHANT_MERCHANTNAME']);

        // Description también ASCII puro.
        $this->assertMatchesRegularExpression('/^[\x20-\x7e]+$/', $data['DS_MERCHANT_PRODUCTDESCRIPTION'], 'Description debe ser ASCII puro');
    }
}
