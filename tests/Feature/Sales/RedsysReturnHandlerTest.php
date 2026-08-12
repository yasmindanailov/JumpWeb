<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\TicketIssuer;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\Redsys;
use App\Domain\Payments\Services\RedsysReturnHandler;
use App\Domain\Payments\Services\RedsysReturnOutcome;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Mail\PaymentIncidentMail;
use App\Notifications\OrderConfirmation;
use App\Notifications\OrderPaymentDeclined;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase 5.5c — `App\Domain\Payments\Services\RedsysReturnHandler` (procesador de la vuelta firmada).
 *
 * El handler es el ÚNICO punto autorizado a transicionar `Payment.status` a `paid` y
 * `Order.status` a `paid`. Es seguridad crítica: si un atacante logra que `process()`
 * apruebe un pago que no ocurrió, comprometemos el negocio entero. Estos tests blindan
 * cada defensa documentada en docs/PLAN-REDSYS.md §6/§8.
 */
class RedsysReturnHandlerTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    private Redsys $redsys;

    private RedsysReturnHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08 10:00:00');

        // Settings sandbox (mismos que el seeder LandingContentSeeder en producción).
        Setting::create(['key' => 'redsys_environment', 'value' => 'test', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_merchant_code', 'value' => '999008881', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_terminal', 'value' => '001', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_secret_key', 'value' => self::SANDBOX_KEY, 'group' => 'payment']);
        Setting::create(['key' => 'redsys_currency', 'value' => '978', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_merchant_name', 'value' => 'SaltoPark', 'group' => 'payment']);

        $this->redsys = new Redsys;
        $this->handler = new RedsysReturnHandler($this->redsys);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crea un pedido + payment pendiente listo para recibir una vuelta de Redsys.
     * Devuelve el Payment y el slot (para verificar tickets emitidos).
     *
     * @return array{0: Payment, 1: User, 2: Slot, 3: TicketType}
     */
    private function setupPaidableOrder(int $amount = 1000, int $seats = 1, ?User $user = null): array
    {
        $user = $user ?? User::factory()->create();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);

        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => '2026-06-08',
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);
        $type = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-TEST'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PENDING,
            'subtotal' => $amount,
            'total' => $amount,
            'currency' => 'EUR',
            'expires_at' => now()->addMinutes(15), // matching #105 semantics
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $type->id,
            'slot_id' => $slot->id,
            'quantity' => $seats,
            'seats' => $seats,
            'unit_price' => intdiv($amount, $seats),
        ]);

        $payment = Payment::create([
            'payable_type' => (new Order)->getMorphClass(),
            'payable_id' => $order->id,
            'provider' => 'redsys',
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'gateway_order' => '0000'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
        ]);

        return [$payment, $user, $slot, $type];
    }

    /** Construye un payload de RESPUESTA Redsys firmado válido (lo que Redsys nos enviaría). */
    private function makeSignedReturnPayload(Payment $payment, string $dsResponse, ?string $authCode = '372663'): array
    {
        $data = [
            'Ds_Date' => '08/06/2026',
            'Ds_Hour' => '10:30',
            'Ds_Amount' => (string) $payment->amount,
            'Ds_Currency' => '978',
            'Ds_Order' => $payment->gateway_order,
            'Ds_MerchantCode' => '999008881',
            'Ds_Terminal' => '001',
            'Ds_Response' => $dsResponse,
            'Ds_TransactionType' => '0',
            'Ds_SecurePayment' => '1',
            'Ds_AuthorisationCode' => $authCode,
            'Ds_MerchantData' => $payment->payable->code,
        ];

        $params = $this->redsys->createMerchantParameters($data);
        $signature = $this->redsys->createMerchantSignature(self::SANDBOX_KEY, $params, $payment->gateway_order);

        return [
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
        ];
    }

    public function test_authorized_response_transitions_payment_and_order_to_paid(): void
    {
        Notification::fake();
        [$payment, $user] = $this->setupPaidableOrder();
        $payload = $this->makeSignedReturnPayload($payment, '0000');

        $result = $this->handler->process($payload);

        $this->assertSame(RedsysReturnOutcome::Authorized, $result['outcome']);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('372663', $payment->auth_code);
        $this->assertSame('372663', $payment->transaction_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->raw_response);
        $this->assertSame('0000', $payment->raw_response['Ds_Response']);

        $order = $payment->payable;
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        // #105: tras paid → expires_at se fija a null (firme definitivo, no caduca).
        $this->assertNull($order->expires_at);

        // Tickets emitidos: uno por seat (seats=1 en este setup).
        $this->assertSame(1, Ticket::where('order_id', $order->id)->count());

        Notification::assertSentTo($user, OrderConfirmation::class);
    }

    public function test_authorized_boundary_response_0099_also_succeeds(): void
    {
        // Manual Redsys §4: cualquier `Ds_Response` ∈ [0000, 0099] está autorizado.
        [$payment] = $this->setupPaidableOrder();
        $payload = $this->makeSignedReturnPayload($payment, '0099');

        $result = $this->handler->process($payload);

        $this->assertSame(RedsysReturnOutcome::Authorized, $result['outcome']);
    }

    public function test_denied_response_marks_payment_failed_and_sends_decline_email(): void
    {
        // Ds_Response 0101 = "tarjeta caducada" (denegado). Order sigue pending y caducará
        // cuando cruce su expires_at → orders:expire libera el aforo (#105).
        // Audit #114 G1: el cliente recibe OrderPaymentDeclined explicando el motivo
        // (#114 RedsysResponseCode mapea 0101 → 'card_expired'). NO se emiten tickets.
        Notification::fake();
        [$payment, $user] = $this->setupPaidableOrder();
        $payload = $this->makeSignedReturnPayload($payment, '0101');

        $result = $this->handler->process($payload);

        $this->assertSame(RedsysReturnOutcome::Denied, $result['outcome']);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertNull($payment->paid_at);

        $order = $payment->payable;
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        // expires_at SIGUE en su valor original (no tocamos: orders:expire la liberará).
        $this->assertNotNull($order->expires_at);
        $this->assertTrue($order->expires_at->isFuture());

        $this->assertSame(0, Ticket::where('order_id', $order->id)->count());

        // Email de denegación enviado al cliente (G1).
        Notification::assertSentTo($user, OrderPaymentDeclined::class);
    }

    public function test_denied_replay_does_not_send_second_decline_email(): void
    {
        // Audit #114 G1: idempotencia simétrica para STATUS_FAILED. Si llega un segundo
        // evento Denied (típicamente: notif on-line llega TRAS la vuelta del navegador,
        // ambas denegadas), NO debemos re-enviar el email — el cliente ya lo recibió.
        Notification::fake();
        [$payment, $user] = $this->setupPaidableOrder();
        $payload = $this->makeSignedReturnPayload($payment, '0101');

        // Primera llamada: marca failed + envía email.
        $this->handler->process($payload);
        // Segunda llamada (replay): mismo outcome, sin segundo email.
        $this->handler->process($payload);

        Notification::assertSentToTimes($user, OrderPaymentDeclined::class, 1);
    }

    public function test_idempotent_replay_on_already_paid_payment_does_not_duplicate_anything(): void
    {
        // Replay attack o legítima doble vía (vuelta + notificación): segunda llamada NO
        // debe emitir tickets duplicados ni enviar email duplicado.
        Notification::fake();
        [$payment] = $this->setupPaidableOrder();
        $payload = $this->makeSignedReturnPayload($payment, '0000');

        $first = $this->handler->process($payload);
        $second = $this->handler->process($payload);

        $this->assertSame(RedsysReturnOutcome::Authorized, $first['outcome']);
        $this->assertSame(RedsysReturnOutcome::IdempotentPaid, $second['outcome']);

        // Tickets y email solo se emitieron en la primera llamada.
        $this->assertSame(1, Ticket::where('order_id', $payment->payable_id)->count());
        Notification::assertSentToTimes($payment->payable->user, OrderConfirmation::class, 1);
    }

    public function test_invalid_signature_rejects_without_touching_payment(): void
    {
        [$payment] = $this->setupPaidableOrder();
        $payload = $this->makeSignedReturnPayload($payment, '0000');
        // Tampering robusto: invertimos la firma — siempre la invalida (a diferencia de un
        // strtr 'A'→'B' que sería no-op si la firma no incluye 'A' por casualidad).
        $payload['Ds_Signature'] = strrev($payload['Ds_Signature']);

        $result = $this->handler->process($payload);

        $this->assertSame(RedsysReturnOutcome::InvalidSignature, $result['outcome']);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertNull($payment->paid_at);
    }

    public function test_tampered_amount_invalidates_signature_and_rejects(): void
    {
        // Aunque un atacante modifique Ds_Amount, la firma deja de validar → InvalidSignature
        // (la primera línea de defensa rechaza antes de llegar al amount_mismatch interno).
        [$payment] = $this->setupPaidableOrder(1000);
        $payload = $this->makeSignedReturnPayload($payment, '0000');

        $data = $this->redsys->decodeMerchantParameters($payload['Ds_MerchantParameters']);
        $data['Ds_Amount'] = '1'; // 1 céntimo en lugar de 1000
        $payload['Ds_MerchantParameters'] = $this->redsys->createMerchantParameters($data);
        // Dejamos la firma original (tras alterar los params, NO recalculamos).

        $result = $this->handler->process($payload);

        $this->assertSame(RedsysReturnOutcome::InvalidSignature, $result['outcome']);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
    }

    public function test_amount_mismatch_defense_catches_signed_payload_with_wrong_amount(): void
    {
        // Escenario raro pero defensa en profundidad: si la firma valida pero el amount NO
        // coincide con `Payment.amount` (puerta de configuración, bug de protocolo…),
        // rechazamos. Re-firmamos un payload con amount alterado para forzar el escenario.
        [$payment] = $this->setupPaidableOrder(1000);

        $data = [
            'Ds_Order' => $payment->gateway_order,
            'Ds_Response' => '0000',
            'Ds_Amount' => '999', // ≠ Payment.amount (1000)
            'Ds_AuthorisationCode' => '372663',
        ];
        $params = $this->redsys->createMerchantParameters($data);
        $signature = $this->redsys->createMerchantSignature(self::SANDBOX_KEY, $params, $payment->gateway_order);

        $result = $this->handler->process([
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
        ]);

        $this->assertSame(RedsysReturnOutcome::AmountMismatch, $result['outcome']);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
    }

    public function test_unknown_gateway_order_rejects(): void
    {
        // Firmamos un payload válido pero con gateway_order que no existe en BD. NO debe
        // crear nada nuevo; solo rechazar.
        $data = [
            'Ds_Order' => '9999999999',
            'Ds_Response' => '0000',
            'Ds_Amount' => '1000',
        ];
        $params = $this->redsys->createMerchantParameters($data);
        $signature = $this->redsys->createMerchantSignature(self::SANDBOX_KEY, $params, '9999999999');

        $result = $this->handler->process([
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
        ]);

        $this->assertSame(RedsysReturnOutcome::UnknownOrder, $result['outcome']);
        $this->assertSame(0, Payment::count());
    }

    public function test_malformed_payload_with_missing_fields_is_rejected(): void
    {
        foreach ([[], ['Ds_SignatureVersion' => 'HMAC_SHA512_V2'], ['Ds_MerchantParameters' => 'foo'], ['Ds_Signature' => 'bar']] as $payload) {
            $result = $this->handler->process($payload);
            $this->assertSame(RedsysReturnOutcome::MalformedPayload, $result['outcome']);
        }
    }

    public function test_undecodable_merchant_parameters_is_rejected(): void
    {
        $result = $this->handler->process([
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => 'not-valid-base64url!!!',
            'Ds_Signature' => 'whatever',
        ]);

        $this->assertSame(RedsysReturnOutcome::MalformedPayload, $result['outcome']);
    }

    public function test_pack_with_multiple_guests_emits_one_ticket_per_seat(): void
    {
        // Un cumpleaños de 10 niños = 10 tickets (uno por admisión, #18).
        Notification::fake();
        [$payment] = $this->setupPaidableOrder(amount: 15000, seats: 10);
        $payload = $this->makeSignedReturnPayload($payment, '0000');

        $this->handler->process($payload);

        $this->assertSame(10, Ticket::where('order_id', $payment->payable_id)->count());
        Ticket::where('order_id', $payment->payable_id)->get()->each(function (Ticket $t): void {
            $this->assertSame(32, strlen($t->qr_token));
            $this->assertSame(Ticket::STATUS_PURCHASED, $t->status);
        });

        // qr_tokens únicos (no colisión).
        $tokens = Ticket::where('order_id', $payment->payable_id)->pluck('qr_token')->toArray();
        $this->assertSame(count($tokens), count(array_unique($tokens)));
    }

    public function test_response_outside_0000_to_0099_with_signature_valid_is_denied(): void
    {
        // 9999 firmado correctamente: el sistema lo trata como denegación (no como ataque),
        // PORQUE la firma de Redsys lo respaldó. El cliente verá pantalla KO.
        [$payment] = $this->setupPaidableOrder();
        $payload = $this->makeSignedReturnPayload($payment, '9999');

        $result = $this->handler->process($payload);

        $this->assertSame(RedsysReturnOutcome::Denied, $result['outcome']);
    }

    public function test_non_numeric_ds_response_is_treated_as_denial(): void
    {
        [$payment] = $this->setupPaidableOrder();
        $payload = $this->makeSignedReturnPayload($payment, 'XXXX'); // no numérico

        $result = $this->handler->process($payload);

        $this->assertSame(RedsysReturnOutcome::Denied, $result['outcome']);
    }

    public function test_authorized_after_hold_expired_in_practice_does_not_issue_tickets(): void
    {
        // Auditoría Fase 1 (sobreventa silenciosa): el pago autorizado llega DESPUÉS de que el hold
        // venció pero ANTES de que `orders:expire` marque EXPIRED (el status sigue PENDING). La plaza
        // pudo cederse lazy a otro cliente vía SlotAvailability → NO emitimos tickets a ciegas;
        // capturamos el cobro y vamos a la rama de incidencia (AuthorizedAfterExpiration), igual que
        // con EXPIRED. Antes del fix este caso caía en la rama normal y emitía tickets sin alerta.
        Notification::fake();
        [$payment, $user] = $this->setupPaidableOrder();
        // El hold ya venció aunque el status siga PENDING (entre ticks del cron de 5 min / sin cron).
        $payment->payable->forceFill(['expires_at' => now()->subMinute()])->save();
        $payload = $this->makeSignedReturnPayload($payment, '0000');

        $result = $this->handler->process($payload);

        $this->assertSame(RedsysReturnOutcome::AuthorizedAfterExpiration, $result['outcome']);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status, 'el cobro SÍ se captura (ya está en el banco)');

        $order = $payment->payable;
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(0, Ticket::where('order_id', $order->id)->count(), 'NO se emiten tickets a ciegas');
        Notification::assertNotSentTo($user, OrderConfirmation::class);
    }

    public function test_second_authorized_payment_on_already_paid_order_does_not_duplicate(): void
    {
        // Auditoría Fase 1 (C1, CRITICAL): un 2.º Payment autorizado sobre una Order YA PAID
        // (típico: reintento + autorización TARDÍA del 1.er intento) NO debe re-emitir tickets ni
        // reenviar confirmación ni re-pisar paid_at. El cobro se captura (es real e irreversible)
        // pero queda como INCIDENCIA de cobro duplicado para que el operador lo devuelva. Antes del
        // fix, la rama normal re-ejecutaba TicketIssuer->issue() (no idempotente) → doble cobro +
        // tickets duplicados sin alerta.
        Notification::fake();
        [$p1, $user] = $this->setupPaidableOrder();
        $order = $p1->payable;

        // 1.er pago autorizado → Order paid, 1 ticket, 1 confirmación (a las 10:00 del test).
        $this->handler->process($this->makeSignedReturnPayload($p1, '0000'));
        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame('10:00', $order->paid_at->format('H:i'));
        $this->assertSame(1, Ticket::where('order_id', $order->id)->count());

        // 2.º Payment (otro gateway_order) sobre la MISMA Order, autorizado 5 min después.
        $p2 = Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'redsys',
            'amount' => $p1->amount, 'currency' => 'EUR', 'status' => Payment::STATUS_PENDING,
            'gateway_order' => '0000'.str_pad((string) ($order->id + 500000), 6, '0', STR_PAD_LEFT),
        ]);
        Carbon::setTestNow('2026-06-08 10:05:00');

        $result = $this->handler->process($this->makeSignedReturnPayload($p2, '0000'));

        // El 2.º cobro se captura pero es INCIDENCIA, no fulfilment.
        $this->assertSame(RedsysReturnOutcome::AuthorizedAfterExpiration, $result['outcome']);
        $p2->refresh();
        $this->assertSame(Payment::STATUS_PAID, $p2->status, 'el 2.º cobro se registra (está en el banco)');
        $this->assertSame('10:05', $p2->paid_at->format('H:i'));

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame('10:00', $order->paid_at->format('H:i'), 'no se re-pisa el paid_at original');
        $this->assertSame(1, Ticket::where('order_id', $order->id)->count(), 'sin tickets duplicados');
        Notification::assertSentToTimes($user, OrderConfirmation::class, 1);
    }

    public function test_authorized_payment_on_cancelled_order_does_not_resurrect_it(): void
    {
        // Auditoría Fase 1 (H1): una notificación autorizada sobre una Order CANCELLED NO debe
        // resucitarla a PAID ni emitir tickets — la plaza ya se liberó al cancelar, emitir tickets
        // sería sobreventa. El cobro se captura como incidencia; la Order sigue cancelada.
        Notification::fake();
        [$payment, $user] = $this->setupPaidableOrder();
        $order = $payment->payable;
        $order->forceFill(['status' => Order::STATUS_CANCELLED])->save();

        $result = $this->handler->process($this->makeSignedReturnPayload($payment, '0000'));

        $this->assertSame(RedsysReturnOutcome::AuthorizedAfterExpiration, $result['outcome']);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status, 'el cobro se captura (está en el banco)');

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status, 'sigue cancelada, NO resucita');
        $this->assertNull($order->paid_at);
        $this->assertSame(0, Ticket::where('order_id', $order->id)->count(), 'sin tickets de una plaza liberada');
        Notification::assertNotSentTo($user, OrderConfirmation::class);
    }

    public function test_ticket_issuer_is_idempotent(): void
    {
        // Auditoría Fase 1 (cinturón C1): una 2.ª llamada a issue() sobre una Order que ya tiene
        // tickets es un no-op (no duplica QR).
        [$payment] = $this->setupPaidableOrder(amount: 3000, seats: 3);
        $order = $payment->payable;

        (new TicketIssuer)->issue($order);
        $this->assertSame(3, Ticket::where('order_id', $order->id)->count());

        (new TicketIssuer)->issue($order); // segunda llamada
        $this->assertSame(3, Ticket::where('order_id', $order->id)->count(), 'idempotente: no re-emite');
    }

    public function test_authorized_payment_auto_verifies_an_unverified_buyer(): void
    {
        // Pay-first (decisión clienta 2026-06-14): completar el pago demuestra que es una persona
        // (un bot no paga), así que el titular que se registró en la compra SIN verificar queda
        // VERIFICADO automáticamente al autorizarse el pago.
        Notification::fake();
        $buyer = User::factory()->unverified()->create();
        $this->assertFalse($buyer->hasVerifiedEmail());

        [$payment] = $this->setupPaidableOrder(user: $buyer);
        $payload = $this->makeSignedReturnPayload($payment, '0000');

        $result = $this->handler->process($payload);

        $this->assertSame(RedsysReturnOutcome::Authorized, $result['outcome']);
        $this->assertTrue($buyer->fresh()->hasVerifiedEmail(), 'el pago auto-verifica al titular');
    }

    public function test_denied_payment_does_not_auto_verify_the_buyer(): void
    {
        // Solo el pago COMPLETADO auto-verifica: una denegación (no hay pago real) NO debe verificar.
        Notification::fake();
        $buyer = User::factory()->unverified()->create();
        [$payment] = $this->setupPaidableOrder(user: $buyer);
        $payload = $this->makeSignedReturnPayload($payment, '0101'); // denegado

        $this->handler->process($payload);

        $this->assertFalse($buyer->fresh()->hasVerifiedEmail(), 'una denegación no verifica');
    }

    // ─── Visibilidad de incidencias de cobro (recomendación C, 2026-06-15) ──────────

    public function test_duplicate_capture_records_audit_incident_and_alerts_operator(): void
    {
        // Un 2.º cobro autorizado sobre una Order YA PAID = cobro duplicado/huérfano. Además del
        // Log::error de siempre, ahora se registra en audit_logs (visible en «Incidencias») y se
        // avisa por email al operador (al contact.email por fallback).
        Notification::fake();
        Mail::fake();
        Setting::create(['key' => 'contact.email', 'value' => 'operador@jumpweb.test', 'group' => 'contact']);

        [$p1, $user] = $this->setupPaidableOrder();
        $order = $p1->payable;
        $this->handler->process($this->makeSignedReturnPayload($p1, '0000')); // 1.er pago OK

        $p2 = Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id, 'provider' => 'redsys',
            'amount' => $p1->amount, 'currency' => 'EUR', 'status' => Payment::STATUS_PENDING,
            'gateway_order' => '0000'.str_pad((string) ($order->id + 500000), 6, '0', STR_PAD_LEFT),
        ]);
        Carbon::setTestNow('2026-06-08 10:05:00');

        // El cliente vuelve por el navegador (autenticado): aun así la incidencia es un evento del
        // SISTEMA y NO debe grabar su user_id/IP (minimización RGPD).
        $this->actingAs($user);
        $this->handler->process($this->makeSignedReturnPayload($p2, '0000'));

        $incident = AuditLog::where('action', AuditLog::ACTION_DUPLICATE_CAPTURE)->first();
        $this->assertNotNull($incident, 'el cobro duplicado se registra en audit_logs');
        $this->assertSame((new Order)->getMorphClass(), $incident->target_type);
        $this->assertSame($order->id, $incident->target_id);
        $this->assertSame($order->code, $incident->payload['order_code']);
        $this->assertSame($p2->gateway_order, $incident->payload['gateway_order']);
        $this->assertSame('duplicate', $incident->payload['kind']);
        $this->assertSame('paid', $incident->payload['order_status'], 'estado real del pedido (ya pagado)');

        // Sin PII: ni en el payload (email/nombre) ni en las columnas de actor de la fila.
        $this->assertStringNotContainsString('@', json_encode($incident->payload, JSON_THROW_ON_ERROR));
        $this->assertNull($incident->user_id, 'evento de sistema: sin actor');
        $this->assertNull($incident->ip, 'evento de sistema: sin IP del cliente');

        // El Mailable es ShouldQueue (D, #243): el aviso se encola, no se manda síncrono dentro
        // de la petición de notificación de Redsys → assertQueued.
        Mail::assertQueued(
            PaymentIncidentMail::class,
            fn (PaymentIncidentMail $mail): bool => $mail->hasTo('operador@jumpweb.test')
        );
    }

    public function test_overbooked_capture_records_audit_incident(): void
    {
        // Cobro autorizado que llega tras caducar el hold (plaza pudo cederse): incidencia
        // «overbooked», registrada en audit_logs.
        Notification::fake();
        Mail::fake();
        [$payment] = $this->setupPaidableOrder();
        $payment->payable->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->handler->process($this->makeSignedReturnPayload($payment, '0000'));

        $incident = AuditLog::where('action', AuditLog::ACTION_OVERBOOKED_CAPTURE)->first();
        $this->assertNotNull($incident, 'el cobro tras caducar se registra en audit_logs');
        $this->assertSame('overbooked', $incident->payload['kind']);
        $this->assertSame($payment->payable->id, $incident->target_id);
        // El estado capturado es el ORIGINAL (pending-caducado), no el 'paid' que el propio
        // forceFill acaba de forzar — es el dato diagnóstico de la incidencia.
        $this->assertSame('pending', $incident->payload['order_status']);
    }

    public function test_clean_authorized_payment_creates_no_incident(): void
    {
        // Camino feliz: un pago autorizado y cumplible NO genera ninguna incidencia.
        Notification::fake();
        Mail::fake();
        [$payment] = $this->setupPaidableOrder();

        $this->handler->process($this->makeSignedReturnPayload($payment, '0000'));

        $this->assertSame(0, AuditLog::whereIn('action', [
            AuditLog::ACTION_DUPLICATE_CAPTURE,
            AuditLog::ACTION_OVERBOOKED_CAPTURE,
        ])->count());
        Mail::assertNothingOutgoing();
    }

    public function test_incident_without_alert_email_still_records_audit_but_sends_no_mail(): void
    {
        // Sin contact.email ni incidents.alert_email configurados: la incidencia SIGUE registrada
        // (rastro duradero), pero no se intenta enviar email (best-effort, no rompe).
        Notification::fake();
        Mail::fake();
        [$payment] = $this->setupPaidableOrder();
        $payment->payable->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->handler->process($this->makeSignedReturnPayload($payment, '0000'));

        $this->assertSame(1, AuditLog::where('action', AuditLog::ACTION_OVERBOOKED_CAPTURE)->count());
        Mail::assertNothingOutgoing();
    }
}
