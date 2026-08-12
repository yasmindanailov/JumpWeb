<?php

namespace Tests\Feature\Sales;

use App\Livewire\Tickets\Purchase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RateType;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Audit #114 (2026-05-28) — UX edge cases del paso 10 (KO) y paso 11 (verificando).
 *
 * Casos cubiertos:
 *  - G6 retryPayment: reusa Order pending + nuevo Payment con nuevo gateway_order.
 *  - G6 retryPayment con Order caducada → vuelta al paso 1 + mensaje.
 *  - G6 retryPayment con Order de otro user → defensa IDOR.
 *  - G7 motivo traducido en paso 10 desde Payment.raw_response.
 *  - G10 polling paso 11 → Order paid → step 6.
 *  - G10 polling paso 11 → Order expired → step 1 + mensaje.
 *  - addAnother limpia orderCode/redsysFormData/declinedReasonText.
 */
class PurchaseRetryAndPollingTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump1h;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-08'); // lunes (tarifa normal)
        $this->today = Carbon::today()->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->today,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);
        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->jump1h->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);

        // Settings sandbox (mismo set que RedsysIdaTest).
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

    /** Crea Order pending + Payment failed + lo deja preparado para "vuelta KO". */
    private function setupFailedOrder(User $user, string $dsResponse = '0101'): array
    {
        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-FAIL'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'expires_at' => now()->addMinutes(15),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->jump1h->id,
            'slot_id' => Slot::first()->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
        $payment = Payment::create([
            'payable_type' => Order::class, 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1000, 'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'gateway_order' => '0000000'.str_pad((string) $order->id, 3, '0', STR_PAD_LEFT),
            'raw_response' => ['Ds_Response' => $dsResponse, 'Ds_Order' => '0000000001'],
        ]);

        return [$order, $payment];
    }

    public function test_paso_10_mounts_with_translated_decline_reason(): void
    {
        // Audit #114 G7: la sesión trae `purchase.failed_code` (escrito por el controller
        // al consumir el token de retorno) → Purchase::mount lee el Payment failed más
        // reciente, extrae Ds_Response y lo traduce a texto humano.
        $user = User::factory()->create();
        [$order] = $this->setupFailedOrder($user, '0101'); // 0101 = tarjeta caducada
        session(['purchase.failed_code' => $order->code]);

        $component = Livewire::actingAs($user)->test(Purchase::class);

        $component
            ->assertSet('step', 10)
            ->assertSet('orderCode', $order->code);

        $reason = $component->get('declinedReasonText');
        $this->assertNotEmpty($reason);
        // 0101 → 'card_expired' → la traducción incluye "caducada" en español por defecto.
        $this->assertStringContainsString('caducada', $reason);
    }

    public function test_paso_10_unknown_ds_response_falls_back_to_default_reason(): void
    {
        $user = User::factory()->create();
        [$order] = $this->setupFailedOrder($user, '9999'); // código no listado
        session(['purchase.failed_code' => $order->code]);

        $component = Livewire::actingAs($user)->test(Purchase::class);

        $this->assertSame(
            __('tickets.payment_failed.reasons.default'),
            $component->get('declinedReasonText'),
        );
    }

    public function test_retry_payment_reuses_order_and_creates_new_payment_with_new_gateway_order(): void
    {
        // Audit #114 G6: el cliente pulsa "Reintentar" en paso 10. La Order pending sigue
        // viva, así que reusamos. Nuevo Payment con nuevo gateway_order (Redsys exige
        // unicidad: manual §5).
        $user = User::factory()->create();
        [$order, $failedPayment] = $this->setupFailedOrder($user);
        session(['purchase.failed_code' => $order->code]);

        Livewire::actingAs($user)
            ->test(Purchase::class)
            ->assertSet('step', 10)
            ->call('retryPayment')
            ->assertSet('step', 9)
            ->assertSet('declinedReasonText', null);

        $payments = Payment::where('payable_id', $order->id)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame(Payment::STATUS_FAILED, $payments[0]->status); // anterior intacto
        $this->assertSame(Payment::STATUS_PENDING, $payments[1]->status); // nuevo
        $this->assertNotSame($payments[0]->gateway_order, $payments[1]->gateway_order, 'Nuevo gateway_order, no reuso');
        $this->assertNotNull($payments[1]->gateway_order);
    }

    public function test_retry_payment_extends_expires_at_window(): void
    {
        $user = User::factory()->create();
        [$order] = $this->setupFailedOrder($user);
        $originalExpiry = $order->expires_at;
        session(['purchase.failed_code' => $order->code]);

        // Avanzamos el reloj 10 min para verificar que el reintento extiende la ventana.
        Carbon::setTestNow(now()->addMinutes(10));

        Livewire::actingAs($user)
            ->test(Purchase::class)
            ->call('retryPayment');

        $order->refresh();
        $this->assertTrue($order->expires_at->isAfter($originalExpiry),
            'expires_at debe extenderse en el reintento (nueva ventana, no la residual)');
    }

    public function test_retry_payment_with_expired_order_returns_to_step_1_with_message(): void
    {
        $user = User::factory()->create();
        [$order] = $this->setupFailedOrder($user);
        $order->forceFill(['status' => Order::STATUS_EXPIRED, 'expires_at' => now()->subMinutes(5)])->save();
        session(['purchase.failed_code' => $order->code]);

        Livewire::actingAs($user)
            ->test(Purchase::class)
            ->call('retryPayment')
            ->assertSet('step', 1)
            ->assertSet('orderCode', null)
            ->assertSet('declinedReasonText', null)
            ->assertHasErrors(['cart']);
    }

    public function test_retry_payment_does_not_apply_for_another_user_order(): void
    {
        // Defensa IDOR: aunque alguien manipule el campo, retryPayment filtra por user_id.
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$alicesOrder] = $this->setupFailedOrder($alice);

        // Bob entra con la sesión apuntando al código de Alice.
        session(['purchase.failed_code' => $alicesOrder->code]);

        Livewire::actingAs($bob)
            ->test(Purchase::class)
            ->call('retryPayment')
            ->assertSet('step', 1) // re-encamina al paso 1, NO toca el Order de Alice
            ->assertSet('orderCode', null);

        // El Order de Alice sigue intacto (sin nuevo Payment).
        $this->assertSame(1, Payment::where('payable_id', $alicesOrder->id)->count());
    }

    public function test_check_payment_status_transitions_to_step_6_when_order_paid(): void
    {
        // Audit #114 G10: polling cada 5s en el paso 11. Cuando la notificación llega y
        // marca la Order paid, el siguiente poll detecta el cambio y muestra paso 6.
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-VER01',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'expires_at' => now()->addMinutes(15),
        ]);
        session(['purchase.verifying_code' => $order->code]);

        $component = Livewire::actingAs($user)->test(Purchase::class)
            ->assertSet('step', 11);

        // Simulamos que la notificación llega y marca la Order paid (background).
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now(), 'expires_at' => null])->save();

        $component->call('checkPaymentStatus')
            ->assertSet('step', 6)
            ->assertSet('confirmed', true);
    }

    public function test_check_payment_status_transitions_to_step_1_when_order_expired(): void
    {
        // Caso patológico: la Order entró en step 11 pero la notificación nunca llegó →
        // orders:expire la marca expired → el polling lo detecta y cierra la pantalla 11.
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-VER02',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'expires_at' => now()->addMinutes(15),
        ]);
        session(['purchase.verifying_code' => $order->code]);

        $component = Livewire::actingAs($user)->test(Purchase::class)
            ->assertSet('step', 11);

        $order->forceFill(['status' => Order::STATUS_EXPIRED])->save();

        $component->call('checkPaymentStatus')
            ->assertSet('step', 1)
            ->assertSet('orderCode', null)
            ->assertHasErrors(['cart']);
    }

    public function test_check_payment_status_does_nothing_when_order_still_pending(): void
    {
        // Caso normal: la notificación aún no ha llegado. El polling no hace nada,
        // la pantalla 11 sigue mostrándose.
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-VER03',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'expires_at' => now()->addMinutes(15),
        ]);
        session(['purchase.verifying_code' => $order->code]);

        Livewire::actingAs($user)->test(Purchase::class)
            ->call('checkPaymentStatus')
            ->assertSet('step', 11)
            ->assertSet('orderCode', $order->code);
    }

    public function test_add_another_clears_residual_state_from_previous_order(): void
    {
        // addAnother debe limpiar orderCode + redsysFormData + declinedReasonText.
        $user = User::factory()->create();
        [$order] = $this->setupFailedOrder($user);
        session(['purchase.failed_code' => $order->code]);

        Livewire::actingAs($user)
            ->test(Purchase::class)
            ->assertSet('step', 10)
            ->assertSet('orderCode', $order->code)
            ->call('addAnother')
            ->assertSet('step', 1)
            ->assertSet('orderCode', null)
            ->assertSet('redsysFormData', [])
            ->assertSet('declinedReasonText', null);
    }
}
