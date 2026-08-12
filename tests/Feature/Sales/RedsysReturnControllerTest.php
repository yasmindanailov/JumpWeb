<?php

namespace Tests\Feature\Sales;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Http\Controllers\Payments\RedsysReturnController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\Redsys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fase 5.5c — `App\Http\Controllers\Payments\RedsysReturnController` (HTTP edge).
 *
 * Tests E2E del flujo cross-site:
 *   1. POST `/pago/redsys/retorno-ok` con firma válida (SIN cookie de sesión) → 303
 *      a `/?redsys={token}` con token one-shot en cache (5 min TTL).
 *   2. Token contiene `user_id + order_code + outcome` y se consume al primer uso.
 *   3. GET `/?redsys={token}` autenticado como el propietario → la sesión recoge
 *      `purchase.confirmed_code` y el sidebar (Purchase Livewire) abre el paso 6 con
 *      el código.
 *   4. Defensas: token usado por usuario distinto NO escribe sesión; token caducado
 *      no aplica; firma inválida devuelve 400 sin generar token.
 */
class RedsysReturnControllerTest extends TestCase
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

    /** @return array{0: Payment, 1: User} */
    private function setupPaidableOrder(): array
    {
        $user = User::factory()->create();
        // Re-entrante: el segundo `setupPaidableOrder` en el mismo test no debe duplicar
        // los catálogos (RateType.key es UNIQUE; Zone.slug también).
        RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'JUMP'], 'position' => 1]);
        // slots tiene UNIQUE(zone_id, date, start_time): firstOrCreate.
        $slot = Slot::firstOrCreate(
            ['zone_id' => $zone->id, 'date' => '2026-06-08', 'start_time' => '10:00:00'],
            ['end_time' => '11:00:00', 'capacity' => 10, 'online_capacity' => 5]
        );
        $type = TicketType::firstOrCreate(
            ['zone_id' => $zone->id, 'duration_min' => 60],
            ['name' => ['es' => 'Jump · 1 hora'], 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]
        );
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-RC'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PENDING, 'subtotal' => 1000, 'total' => 1000,
            'currency' => 'EUR', 'expires_at' => now()->addMinutes(15),
        ]);
        OrderItem::create(['order_id' => $order->id, 'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 1000]);
        $payment = Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1000, 'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING, 'gateway_order' => '0000'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
        ]);

        return [$payment, $user];
    }

    /** Construye el payload firmado que Redsys postaría a UrlOK / UrlKO. */
    private function makePayload(Payment $payment, string $dsResponse): array
    {
        $redsys = new Redsys;
        $data = [
            'Ds_Order' => $payment->gateway_order,
            'Ds_Response' => $dsResponse,
            'Ds_Amount' => (string) $payment->amount,
            'Ds_Currency' => '978',
            'Ds_AuthorisationCode' => '372663',
            'Ds_MerchantData' => $payment->payable->code,
        ];
        $params = $redsys->createMerchantParameters($data);
        $signature = $redsys->createMerchantSignature(self::SANDBOX_KEY, $params, $payment->gateway_order);

        return [
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
        ];
    }

    public function test_browser_return_ok_redirects_with_one_shot_token_in_query(): void
    {
        [$payment] = $this->setupPaidableOrder();

        // POST cross-site (sin cookies de sesión Laravel). En tests Laravel, la ausencia
        // de sesión es el comportamiento por defecto al hacer post() sin actingAs.
        $response = $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'));

        $response->assertStatus(303);

        $redirect = $response->headers->get('Location');
        $this->assertNotEmpty($redirect);
        // Debe redirigir a la home con el query param `redsys=…`.
        $this->assertStringContainsString('redsys=', (string) $redirect);

        // El Payment ya está paid (el handler corrió dentro del controller).
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
    }

    public function test_token_payload_contains_user_id_order_code_and_outcome(): void
    {
        [$payment, $user] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'));

        $redirect = (string) $response->headers->get('Location');
        parse_str(parse_url($redirect, PHP_URL_QUERY) ?? '', $query);
        $token = $query['redsys'] ?? null;
        $this->assertNotEmpty($token);

        $cached = Cache::get(RedsysReturnController::cacheKey($token));
        $this->assertIsArray($cached);
        $this->assertSame($user->id, $cached['user_id']);
        $this->assertSame($payment->payable->code, $cached['order_code']);
        $this->assertSame('authorized', $cached['outcome']);
    }

    public function test_invalid_signature_returns_400_without_generating_a_token(): void
    {
        [$payment] = $this->setupPaidableOrder();
        $payload = $this->makePayload($payment, '0000');
        // Tampering robusto: invertimos la firma — la cadena resultante NO valida HMAC
        // independientemente de los caracteres que contenga (a diferencia de un strtr 'A'→'B'
        // que sería no-op si la firma no incluye 'A').
        $payload['Ds_Signature'] = strrev($payload['Ds_Signature']);

        $response = $this->post(route('payments.redsys.return.ok'), $payload);

        $response->assertStatus(400);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        // No cache entry created.
        $this->assertSame([], Cache::get('redsys.return:any', []));
    }

    public function test_unknown_order_returns_400_without_touching_state(): void
    {
        // Firmamos un payload válido con gateway_order desconocido — no debe crear nada.
        $redsys = new Redsys;
        $data = ['Ds_Order' => '0000999999', 'Ds_Response' => '0000', 'Ds_Amount' => '1000'];
        $params = $redsys->createMerchantParameters($data);
        $signature = $redsys->createMerchantSignature(self::SANDBOX_KEY, $params, '0000999999');

        $response = $this->post(route('payments.redsys.return.ok'), [
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $signature,
        ]);

        $response->assertStatus(400);
        $this->assertSame(0, Payment::count());
    }

    public function test_home_get_consumes_token_and_writes_session_for_owner(): void
    {
        [$payment, $user] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'));
        $token = $this->tokenFromRedirect($response);

        // GET subsiguiente con cookies del usuario propietario.
        $this->actingAs($user)->get('/?redsys='.$token)
            ->assertOk()
            // #106 (validación visual 2026-05-26): además de escribir la session flag, el
            // layout debe abrir el sidebar automáticamente (Alpine lee `data-purchase-open`).
            // Sin esto el cliente vuelve de Redsys y NO ve el resultado hasta hacer click.
            ->assertSee('data-purchase-open="1"', false);

        $this->assertSame($payment->payable->code, session('purchase.confirmed_code'));
        // Token consumido (one-shot): segunda GET no debería escribir nada.
        session()->forget('purchase.confirmed_code');
        $this->actingAs($user)->get('/?redsys='.$token);
        $this->assertNull(session('purchase.confirmed_code'));
    }

    public function test_home_get_does_not_apply_token_for_a_different_user(): void
    {
        // Defensa: aunque un atacante capture el token y se loguee con otra cuenta, no
        // debe aplicar el resultado. Token consumido pero sesión intacta.
        [$payment] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'));
        $token = $this->tokenFromRedirect($response);

        $bob = User::factory()->create();
        $this->actingAs($bob)->get('/?redsys='.$token)->assertOk();
        $this->assertNull(session('purchase.confirmed_code'));
        $this->assertNull(session('purchase.failed_code'));
    }

    public function test_home_get_does_not_apply_token_when_unauthenticated(): void
    {
        // Sin autenticación, el token NO se aplica. Defensa contra que un atacante anónimo
        // use un token capturado para llegar al paso 6 (no podría — la sesión que se
        // escribiría no se asociaría a ningún usuario en el sidebar).
        [$payment] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'));
        $token = $this->tokenFromRedirect($response);

        $this->get('/?redsys='.$token)->assertOk();
        $this->assertNull(session('purchase.confirmed_code'));
    }

    public function test_browser_return_ko_writes_failed_code_to_session_on_follow_up(): void
    {
        [$payment, $user] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ko'), $this->makePayload($payment, '0101'));
        $response->assertStatus(303);

        $redirect = (string) $response->headers->get('Location');
        parse_str(parse_url($redirect, PHP_URL_QUERY) ?? '', $query);
        $token = $query['redsys'];

        $this->actingAs($user)->get('/?redsys='.$token)->assertOk();
        $this->assertSame($payment->payable->code, session('purchase.failed_code'));
        $this->assertNull(session('purchase.confirmed_code'));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
    }

    public function test_browser_return_accepts_get_with_query_params(): void
    {
        // #106 (verificado empíricamente 2026-05-26 con la clienta): el terminal Redsys
        // sandbox puede redirigir el navegador por GET con los `Ds_*` como query params
        // en lugar de auto-POST. El handler debe procesar ambos (el ejemplo oficial v2.0
        // `ejemploRecepcionaPet.php` hace `array_merge($_GET, $_POST, $jsonBody)`).
        [$payment] = $this->setupPaidableOrder();

        $response = $this->get(route('payments.redsys.return.ok').'?'.http_build_query($this->makePayload($payment, '0000')));

        $response->assertStatus(303);
        $this->assertStringContainsString('redsys=', (string) $response->headers->get('Location'));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
    }

    public function test_browser_return_get_without_signed_data_and_no_recent_payment_redirects_home(): void
    {
        // #106: terminal Redsys que NO incluye datos en la redirección. Sin un Payment
        // reciente del usuario autenticado, redirigimos a home sin escribir flags de sesión
        // (no leakeamos información).
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('payments.redsys.return.ok'));

        $response->assertRedirect(route('home'));
        $this->assertNull(session('purchase.verifying_code'));
        $this->assertNull(session('purchase.confirmed_code'));
    }

    public function test_browser_return_get_without_data_with_pending_payment_sets_verifying_flag(): void
    {
        // #106: el cliente acaba de pagar pero el terminal no incluyó `Ds_*` en la vuelta.
        // El Payment sigue `pending` (esperamos a la notificación on-line). Mostramos al
        // cliente "verificando" con su nº de pedido en el paso 11 — NUNCA marcamos paid
        // sin firma válida (eso sería un vector de fraude).
        [$payment, $user] = $this->setupPaidableOrder();

        $response = $this->actingAs($user)->get(route('payments.redsys.return.ok'));

        $response->assertRedirect(route('home'));
        $this->assertSame($payment->payable->code, session('purchase.verifying_code'));
        // El Payment NO ha sido modificado — sigue pending, esperando notificación.
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
    }

    public function test_browser_return_get_without_data_with_already_paid_payment_redirects_with_token(): void
    {
        // #106: carrera benigna — la notificación on-line (5.5d) llegó ANTES que el
        // navegador del cliente; el Payment ya está paid. El fallback del controller debe
        // detectarlo y redirigir con token de éxito para que el sidebar abra en paso 6.
        [$payment, $user] = $this->setupPaidableOrder();
        $payment->forceFill(['status' => Payment::STATUS_PAID, 'paid_at' => now()])->save();
        $payment->payable->forceFill(['status' => 'paid', 'paid_at' => now(), 'expires_at' => null])->save();

        $response = $this->actingAs($user)->get(route('payments.redsys.return.ok'));

        $response->assertStatus(303);
        $redirect = (string) $response->headers->get('Location');
        $this->assertStringContainsString('redsys=', $redirect);

        $token = $this->tokenFromRedirect($response);
        $cached = Cache::get(RedsysReturnController::cacheKey($token));
        $this->assertSame('idempotent_paid', $cached['outcome']);
    }

    public function test_browser_return_get_without_data_without_auth_redirects_home(): void
    {
        // Sin usuario autenticado, no podemos asociar la vuelta a ningún pedido. Redirige
        // a home sin escribir nada (no leak).
        $response = $this->get(route('payments.redsys.return.ok'));

        $response->assertRedirect(route('home'));
        $this->assertNull(session('purchase.verifying_code'));
    }

    public function test_browser_return_ignores_stale_payments_outside_lookback_window(): void
    {
        // Defensa: un Payment antiguo (>30 min) no se asocia a una vuelta sin datos. Evita
        // que un usuario que vuelve a la URL mucho tiempo después vea "verificando" sobre
        // un pago viejo.
        [$payment, $user] = $this->setupPaidableOrder();
        $payment->forceFill(['created_at' => now()->subHour()])->save();

        $response = $this->actingAs($user)->get(route('payments.redsys.return.ok'));

        $response->assertRedirect(route('home'));
        $this->assertNull(session('purchase.verifying_code'));
    }

    public function test_routes_are_csrf_excluded(): void
    {
        // Las 3 rutas Redsys deben aceptar POST cross-site SIN token CSRF (porque la
        // cookie no viaja en POST cross-site). Si CSRF estuviera activo, devolvería 419.
        [$payment] = $this->setupPaidableOrder();
        $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'))
            ->assertStatus(303);
        // notification ahora responde 200 (en 5.5d se cablea de verdad).
        $payment2 = $this->setupPaidableOrder()[0];
        $this->post(route('payments.redsys.notification'), $this->makePayload($payment2, '0000'))
            ->assertStatus(200);
    }

    /** Extrae el token `?redsys=` del header Location del 303. */
    private function tokenFromRedirect(TestResponse $response): string
    {
        $redirect = (string) $response->headers->get('Location');
        parse_str(parse_url($redirect, PHP_URL_QUERY) ?? '', $query);
        $token = $query['redsys'] ?? null;
        $this->assertNotEmpty($token, 'Expected `redsys` query param in redirect');

        return (string) $token;
    }
}
