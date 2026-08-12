<?php

namespace Tests\Feature\Sales;

use App\Http\Controllers\Payments\RedsysReturnController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RateType;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\OrderConfirmation;
use App\Notifications\OrderPaymentDeclined;
use App\Notifications\OrderProcessedAfterExpiration;
use App\Support\Redsys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase 5.5d — Endpoint `POST /pago/redsys/notificacion` (notificación on-line server-to-server).
 *
 * Esta es la **fuente de verdad del cobro en producción** (manual §2.2/§7, decisión #62):
 * los servidores de Redsys golpean directamente este endpoint tras autorizar el pago, sin
 * pasar por el navegador del cliente. Es el único canal fiable cuando el cliente cierra la
 * pestaña, pierde Internet o se queda sin batería tras pulsar "Pagar".
 *
 * Contratos críticos blindados por estos tests:
 *
 *   - **HTTP 200 SIEMPRE** (incluso ante firma inválida, malformación, pago desconocido).
 *     Un 4xx/5xx dispara reintentos exponenciales de Redsys → ruido + race conditions con
 *     la vuelta del navegador. Preferimos absorber el error y dejarlo en logs.
 *   - **WARNING en logs** ante cualquier rechazo (firma inválida, malformed, unknown, amount
 *     mismatch) con `source=notification` para que un operador discrimine en producción
 *     entre vector hacia notif (atacante) vs vector hacia browser_return (probable bug propio).
 *   - **Idempotencia estricta**: doble notificación NO emite doble ticket ni doble email.
 *     Garantizada por `lockForUpdate` + chequeo de `STATUS_PAID` previo en el handler.
 *   - **Race condition** (notificación llega ANTES que la vuelta del navegador): la
 *     notificación marca `paid`; cuando llega la vuelta data-less del navegador, el
 *     controller detecta `paid` y emite token `IdempotentPaid` → cliente ve éxito sin que
 *     marquemos `paid` por segunda vez ni emitamos tickets/email duplicados.
 *   - **POST estricto**: GET → 405. La notificación de Redsys es siempre POST.
 *   - **Defensa en profundidad**: amount mismatch con firma válida → NO se procesa (canario
 *     ante desviaciones de protocolo).
 *
 * Estos tests no validan la cripto (cubierta en `RedsysSignatureTest`) ni los caminos
 * internos del handler (cubiertos en `RedsysReturnHandlerTest`); validan el contrato
 * HTTP del back-channel server-to-server tal y como lo verá Redsys en producción.
 */
class RedsysNotificationEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08 10:00:00');

        Setting::create(['key' => 'redsys_environment', 'value' => 'test', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_merchant_code', 'value' => '999008881', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_terminal', 'value' => '001', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_secret_key', 'value' => self::SANDBOX_KEY, 'group' => 'payment']);
        Setting::create(['key' => 'redsys_currency', 'value' => '978', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_merchant_name', 'value' => 'SaltoPark', 'group' => 'payment']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: Payment, 1: User, 2: Order} */
    private function setupPaidableOrder(): array
    {
        $user = User::factory()->create();
        RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'JUMP'], 'position' => 1]);
        $slot = Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => '2026-06-08', 'start_time' => '10:00:00'],
            ['end_time' => '11:00:00', 'capacity' => 10, 'online_capacity' => 5]
        );
        $type = TicketType::firstOrCreate(
            ['zone_id' => $zone->id, 'duration_min' => 60],
            ['name' => ['es' => 'Jump · 1 hora'], 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]
        );
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-NT'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PENDING, 'subtotal' => 1000, 'total' => 1000,
            'currency' => 'EUR', 'expires_at' => now()->addMinutes(15),
        ]);
        OrderItem::create(['order_id' => $order->id, 'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 1000]);
        $payment = Payment::create([
            'payable_type' => Order::class, 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1000, 'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING, 'gateway_order' => '0000'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
        ]);

        return [$payment, $user, $order];
    }

    /**
     * Construye un payload firmado válido para una notificación de Redsys con el
     * `Ds_Response` dado. Permite overrides puntuales (p. ej. tampering del Ds_Amount).
     *
     * @param  array<string,string>  $overrides
     * @return array{Ds_SignatureVersion: string, Ds_MerchantParameters: string, Ds_Signature: string}
     */
    private function signedPayload(Payment $payment, string $dsResponse, array $overrides = []): array
    {
        $redsys = new Redsys;
        $data = array_merge([
            'Ds_Order' => $payment->gateway_order,
            'Ds_Response' => $dsResponse,
            'Ds_Amount' => (string) $payment->amount,
            'Ds_Currency' => '978',
            'Ds_AuthorisationCode' => '372663',
            'Ds_MerchantData' => $payment->payable->code,
        ], $overrides);
        $params = $redsys->createMerchantParameters($data);
        $signature = $redsys->createMerchantSignature(self::SANDBOX_KEY, $params, $payment->gateway_order);

        return [
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
        ];
    }

    public function test_notification_with_valid_signature_marks_payment_paid_and_returns_200(): void
    {
        Notification::fake();
        [$payment, $user, $order] = $this->setupPaidableOrder();

        $response = $this->postJson(route('payments.redsys.notification'), $this->signedPayload($payment, '0000'));

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('372663', $payment->auth_code);

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNull($order->expires_at, 'Order paid → expires_at NULL (firme definitivo, #105)');

        // Ticket emitido (la entrada tiene slot; un Ticket por plaza).
        $this->assertSame(1, Ticket::where('order_id', $order->id)->count());

        // Email de confirmación enviado (vía notificación; #98).
        Notification::assertSentTo($user, OrderConfirmation::class);
    }

    public function test_notification_is_idempotent_does_not_duplicate_tickets_or_email(): void
    {
        Notification::fake();
        [$payment, $user] = $this->setupPaidableOrder();
        $payload = $this->signedPayload($payment, '0000');

        // Primera notificación: marca paid, emite ticket, envía email.
        $this->postJson(route('payments.redsys.notification'), $payload)->assertStatus(200);
        // Segunda notificación (replay): debe ser no-op desde el punto de vista de efectos.
        $this->postJson(route('payments.redsys.notification'), $payload)->assertStatus(200);
        // Tercera (paranoia): tampoco debe duplicar.
        $this->postJson(route('payments.redsys.notification'), $payload)->assertStatus(200);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(1, Ticket::where('order_id', $payment->payable_id)->count(), 'No tickets duplicados');
        Notification::assertSentToTimes($user, OrderConfirmation::class, 1);
    }

    public function test_notification_with_invalid_signature_returns_200_and_logs_warning_with_notification_source(): void
    {
        [$payment] = $this->setupPaidableOrder();

        // Spy del log: la firma inválida DEBE producir `invalid_signature` con source=notification.
        // Esto es lo que distingue auditoría del canal back-channel del navegador del cliente.
        Log::spy();

        $payload = $this->signedPayload($payment, '0000');
        $payload['Ds_Signature'] = strrev($payload['Ds_Signature']); // tampering robusto

        $response = $this->postJson(route('payments.redsys.notification'), $payload);

        $response->assertStatus(200); // ⚠️ 200 (NO 4xx) — clave para no provocar reintentos infinitos
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status, 'Firma inválida NO debe marcar paid');
        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []): bool {
                return $message === 'redsys.return.invalid_signature'
                    && ($context['source'] ?? null) === 'notification';
            });
    }

    public function test_notification_with_malformed_payload_returns_200_and_logs_warning(): void
    {
        Log::spy();

        // Body vacío: ni siquiera tiene los 3 campos canónicos.
        $response = $this->postJson(route('payments.redsys.notification'), []);

        $response->assertStatus(200);
        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []): bool {
                return $message === 'redsys.return.malformed'
                    && ($context['source'] ?? null) === 'notification';
            });
    }

    public function test_notification_with_currency_mismatch_logs_error_and_does_not_change_state(): void
    {
        // Audit hardening #113 (A3): defense in depth simétrica al amount mismatch. Si la
        // firma valida pero `Ds_Currency` no coincide con el ISO-4217 numérico esperado para
        // `Payment.currency`, rechazamos. No debería ocurrir si la firma vale, pero canario
        // contra desviaciones de protocolo/configuración (e.g., terminal Redsys en otra divisa).
        Log::spy();
        [$payment] = $this->setupPaidableOrder();

        // Firmamos con Ds_Currency='840' (USD); Payment.currency es 'EUR' → debería ser '978'.
        $payload = $this->signedPayload($payment, '0000', ['Ds_Currency' => '840']);

        $response = $this->postJson(route('payments.redsys.notification'), $payload);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status, 'Currency mismatch NO debe marcar paid');

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context = []): bool {
                return $message === 'redsys.return.currency_mismatch'
                    && ($context['source'] ?? null) === 'notification'
                    && ($context['expected'] ?? null) === '978'
                    && ($context['received'] ?? null) === '840';
            });
    }

    public function test_notification_with_amount_mismatch_logs_error_and_does_not_change_state(): void
    {
        Log::spy();
        [$payment] = $this->setupPaidableOrder();

        // Firma válida pero `Ds_Amount` distinto. La firma valida porque firmamos el
        // payload manipulado, pero el handler debe detectar la divergencia con el Payment
        // en BD y rechazar — defensa en profundidad contra desviaciones de protocolo.
        $payload = $this->signedPayload($payment, '0000', ['Ds_Amount' => '99999']);

        $response = $this->postJson(route('payments.redsys.notification'), $payload);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status, 'Amount mismatch NO debe marcar paid');

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context = []): bool {
                return $message === 'redsys.return.amount_mismatch'
                    && ($context['source'] ?? null) === 'notification'
                    && ($context['received'] ?? null) === '99999';
            });
    }

    public function test_notification_with_denied_response_marks_payment_failed_and_sends_decline_email(): void
    {
        // Audit #114 G1: en denegación se manda email `OrderPaymentDeclined` al cliente
        // (NO `OrderConfirmation` — eso solo en éxito). El cliente sabe qué pasó incluso
        // si cerró la pestaña antes de ver el paso 10.
        Notification::fake();
        [$payment, $user, $order] = $this->setupPaidableOrder();

        // CVV 999 en sandbox → Ds_Response 0101 (denegada). Caso típico de KO.
        $response = $this->postJson(route('payments.redsys.notification'), $this->signedPayload($payment, '0101'));

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);

        $order->refresh();
        $this->assertSame(Order::STATUS_PENDING, $order->status, 'KO no toca el Order: caduca por orders:expire (#105)');
        $this->assertNotNull($order->expires_at, 'expires_at intacto → orders:expire liberará aforo cuando cruce');

        // No emite tickets en denegación.
        $this->assertSame(0, Ticket::where('order_id', $order->id)->count());
        // Email de éxito NO se envía (era el comportamiento previo).
        Notification::assertNotSentTo($user, OrderConfirmation::class);
        // Email de denegación SÍ se envía (G1, audit #114).
        Notification::assertSentTo($user, OrderPaymentDeclined::class);
    }

    public function test_double_denied_notification_does_not_send_duplicate_decline_email(): void
    {
        // Idempotencia simétrica para STATUS_FAILED (#114 G1): si llegan DOS notif Denied
        // (caso real: Redsys reintenta tras un blip de red), el cliente NO debe recibir
        // dos emails idénticos. Mismo patrón que la idempotencia para STATUS_PAID.
        Notification::fake();
        [$payment, $user] = $this->setupPaidableOrder();
        $payload = $this->signedPayload($payment, '0101');

        $this->postJson(route('payments.redsys.notification'), $payload)->assertStatus(200);
        $this->postJson(route('payments.redsys.notification'), $payload)->assertStatus(200);
        $this->postJson(route('payments.redsys.notification'), $payload)->assertStatus(200);

        Notification::assertSentToTimes($user, OrderPaymentDeclined::class, 1);
    }

    public function test_notification_with_unknown_gateway_order_returns_200_without_creating_anything(): void
    {
        Log::spy();
        // Firmamos un payload válido con gateway_order que no existe en BD.
        $redsys = new Redsys;
        $data = ['Ds_Order' => '0000999999', 'Ds_Response' => '0000', 'Ds_Amount' => '1000'];
        $params = $redsys->createMerchantParameters($data);
        $signature = $redsys->createMerchantSignature(self::SANDBOX_KEY, $params, '0000999999');

        $response = $this->postJson(route('payments.redsys.notification'), [
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, Order::count());

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []): bool {
                return $message === 'redsys.return.unknown_order'
                    && ($context['source'] ?? null) === 'notification'
                    && ($context['gateway_order'] ?? null) === '0000999999';
            });
    }

    public function test_race_condition_notification_arrives_before_browser_return_browser_falls_back_to_idempotent_paid(): void
    {
        // Escenario "el cliente paga pero su navegador es lento". Redsys notifica primero;
        // el cliente vuelve después. La vuelta debe ver `paid` y NO debe re-procesar.
        Notification::fake();
        [$payment, $user, $order] = $this->setupPaidableOrder();

        // 1. Notificación llega primero (back-channel).
        $this->postJson(route('payments.redsys.notification'), $this->signedPayload($payment, '0000'))
            ->assertStatus(200);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);

        // 2. Cliente vuelve después al navegador. Terminal sandbox SIN datos inline
        //    (caso data-less, #106) — Redsys solo redirige al UrlOK sin Ds_*.
        $response = $this->actingAs($user)->get(route('payments.redsys.return.ok'));

        $response->assertStatus(303);
        $redirect = (string) $response->headers->get('Location');
        $this->assertStringContainsString('redsys=', $redirect);

        // 3. El token de la vuelta debe ser de tipo idempotent_paid (no authorized de nuevo).
        parse_str(parse_url($redirect, PHP_URL_QUERY) ?? '', $query);
        $token = $query['redsys'] ?? null;
        $cached = Cache::get(RedsysReturnController::cacheKey($token));
        $this->assertSame('idempotent_paid', $cached['outcome']);

        // 4. NO se emite segundo ticket ni segundo email.
        $this->assertSame(1, Ticket::where('order_id', $order->id)->count());
        Notification::assertSentToTimes($user, OrderConfirmation::class, 1);
    }

    public function test_raw_response_only_persists_allowlisted_fields(): void
    {
        // Audit hardening #113 (M1): RGPD principio de minimización. Solo persistimos
        // los campos de la allowlist (Ds_Response, Ds_AuthorisationCode, etc.); cualquier
        // campo sensible o añadido por Redsys "sin previo aviso" (manual §4.5) queda fuera.
        Notification::fake();
        [$payment] = $this->setupPaidableOrder();

        // Construimos un payload firmado que incluye un campo sensible típico (Ds_Card_Number,
        // PAN truncado) y un campo basura (Ds_Future_Telemetry) para verificar que NO se guardan.
        $payload = $this->signedPayload($payment, '0000', [
            'Ds_Card_Number' => '454881******0003',
            'Ds_Future_Telemetry' => 'something-we-do-not-want',
        ]);

        $this->postJson(route('payments.redsys.notification'), $payload)->assertStatus(200);

        $payment->refresh();
        $raw = $payment->raw_response;
        $this->assertIsArray($raw);

        // Campos esperados (allowlist): sí presentes.
        $this->assertArrayHasKey('Ds_Response', $raw);
        $this->assertArrayHasKey('Ds_AuthorisationCode', $raw);
        $this->assertArrayHasKey('Ds_Amount', $raw);

        // Campos fuera de la allowlist: NO persistidos (minimización RGPD + defensa contra
        // futuros campos sensibles añadidos por Redsys sin nuestro conocimiento).
        $this->assertArrayNotHasKey('Ds_Card_Number', $raw);
        $this->assertArrayNotHasKey('Ds_Future_Telemetry', $raw);
    }

    public function test_notification_arriving_after_order_expired_marks_paid_but_emits_incident_email_not_tickets(): void
    {
        // Audit hardening #113 (C1) — Race condition crítica. La notificación autorizada
        // llega DESPUÉS de que orders:expire haya caducado la Order. El cobro YA está
        // capturado en banco. Comportamiento esperado:
        //  - Payment → paid (refleja la realidad del banco).
        //  - Order → paid + paid_at (no la dejamos como expired, sería incoherente).
        //  - NO emite tickets (operativa decide reagendar manualmente).
        //  - Email DISTINTO: OrderProcessedAfterExpiration (no OrderConfirmation).
        //  - Log ERROR redsys.return.overbooked_alert (visible para el operador).
        Notification::fake();
        Log::spy();
        [$payment, $user, $order] = $this->setupPaidableOrder();

        // Simulamos que `orders:expire` ya caducó la Order (escenario real: notif tarda).
        $order->forceFill(['status' => Order::STATUS_EXPIRED, 'expires_at' => now()->subMinutes(5)])->save();

        $response = $this->postJson(route('payments.redsys.notification'), $this->signedPayload($payment, '0000'));

        $response->assertStatus(200);

        // Payment y Order pasan a paid (refleja el cobro real del banco).
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNull($order->expires_at, 'Order paid → sin expires_at');

        // NO se emiten tickets (operativa los emitirá si decide reagendar).
        $this->assertSame(0, Ticket::where('order_id', $order->id)->count(),
            'Cuando la Order estaba expired, NO emitimos tickets automáticos');

        // Email DISTINTO: incidencia, no confirmación.
        Notification::assertSentTo($user, OrderProcessedAfterExpiration::class);
        Notification::assertNotSentTo($user, OrderConfirmation::class);

        // Log ALERT visible para el operador (sin secret_key ni firma).
        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context = []): bool {
                return $message === 'redsys.return.overbooked_alert'
                    && ($context['source'] ?? null) === 'notification'
                    && isset($context['order_code'], $context['gateway_order'], $context['message']);
            });
    }

    public function test_notification_endpoint_rejects_get_method_with_405(): void
    {
        // La notificación de Redsys es siempre POST. Cualquier GET = mal uso (o sondeo).
        // 405 deja claro al cliente HTTP qué métodos aceptamos.
        $response = $this->get(route('payments.redsys.notification'));
        $response->assertStatus(405);
    }

    public function test_notification_does_not_require_session_cookie(): void
    {
        // El POST de Redsys es server-to-server: NO trae cookie de sesión del navegador
        // del cliente. El endpoint debe procesar la notificación sin depender de auth(),
        // session() ni nada que requiera contexto del usuario. La identidad viene de la
        // firma + gateway_order (UNIQUE en BD).
        Notification::fake();
        [$payment, $user] = $this->setupPaidableOrder();

        // Sin actingAs(). Solo el POST firmado.
        $response = $this->postJson(route('payments.redsys.notification'), $this->signedPayload($payment, '0000'));

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        // El email sigue llegando al user dueño del Order, identificado por la relación
        // payable → user; NO por la sesión HTTP que aquí no existe.
        Notification::assertSentTo($user, OrderConfirmation::class);
    }
}
