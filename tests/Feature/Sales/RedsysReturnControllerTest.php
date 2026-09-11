<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\Redsys;
use App\Domain\Payments\Services\RedsysReturnOutcome;
use App\Domain\Platform\Models\Setting;
use App\Http\Controllers\Payments\RedsysReturnController;
use App\Http\Sidebar\SidebarEntry;
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
 *   3. GET `/?redsys={token}` autenticado como el propietario → el desenlace llega al cajón y este
 *      se abre solo en el paso 6 con el código.
 *   4. Defensas: token usado por usuario distinto NO aplica el desenlace; token caducado no aplica;
 *      firma inválida devuelve 400 sin generar token.
 *
 * ⚠️⚠️ **Dónde se comprueba el desenlace, y por qué cambió en 4.7·2b·3.** Estos casos miraban
 * `session('purchase.confirmed_code')` DESPUÉS del GET. Con el motor Livewire eso valía porque el
 * componente era `lazy` y consumía la sesión en una petición POSTERIOR. Retirado el componente, el
 * cajón SPA **vive en el mismo documento** y es `layout.blade.php` quien llama a
 * `SidebarEntry::consume()`: al terminar el GET la sesión está SIEMPRE vacía.
 *
 * Eso no solo puso rojos los tres casos positivos — **dejó INERTES los dos de seguridad**, que
 * aseveraban `assertNull(session(...))` sobre un valor que ya no puede ser otra cosa. Medido por
 * mutación el 2026-08-21: desactivando la comprobación de titularidad de
 * `HomeController::maybeConsumeRedsysReturn()` —es decir, con un tercero aplicándose el desenlace
 * ajeno— `test_home_get_does_not_apply_token_for_a_different_user` y
 * `test_home_get_does_not_apply_token_when_unauthenticated` seguían en VERDE.
 *
 * ▶ Por eso todos leen ahora el payload de montaje del cajón (`outcomeInBoot()`), que es donde el
 * desenlace viaja de verdad. Repetida la misma mutación contra la versión nueva, los dos se ponen
 * rojos.
 */
class RedsysReturnControllerTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    /**
     * El desenlace que el servidor le entrega al cajón en ESTA respuesta.
     *
     * Es el sucesor de mirar `session('purchase.*_code')`: el layout consume la sesión al pintar y
     * deja el resultado en `data-boot` del punto de montaje. Devuelve `[outcome, orderCode]`, los
     * dos `null` cuando no había nada que entregar.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function outcomeInBoot(TestResponse $response): array
    {
        if (preg_match('/id="sidecart-spa" data-boot="([^"]*)"/', $response->getContent(), $m) !== 1) {
            $this->fail('no se ha encontrado el punto de montaje del cajón en la respuesta');
        }

        $boot = json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);

        return [$boot['outcome'] ?? null, $boot['orderCode'] ?? null];
    }

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

        $cached = RedsysReturnController::handoff()->get(RedsysReturnController::cacheKey($token));
        $this->assertIsArray($cached);
        $this->assertSame($user->id, $cached['user_id']);
        $this->assertSame($payment->payable->code, $cached['order_code']);
        $this->assertSame('authorized', $cached['outcome']);
    }

    /**
     * ⚠️⚠️ **EL PASE SOBREVIVE A UN VACIADO DE LA CACHÉ, y desde `#137` eso es lo que hay que fijar.**
     *
     * No es un dato cacheado: es un pase de un solo uso que decide si quien acaba de pagar ve
     * «¡Reserva creada!» o una home vacía. Mientras el store por defecto fue `database` nadie podía
     * desalojarlo y la distinción no se notaba. Con Redis y `allkeys-lru` **sí puede desaparecer**
     * bajo presión de memoria — y precisamente en el pico de reservas, que es cuando más pases hay.
     *
     * ▶ Esta comprobación es la que caza la regresión, porque **el resto de la suite pasaría igual**
     * si el pase volviera al store por defecto: en los tests ese store es `array` y nada lo vacía.
     * Aquí se vacía a propósito.
     */
    public function test_the_handoff_pass_survives_the_cache_being_flushed(): void
    {
        [$payment, $user] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'));

        parse_str(parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY) ?? '', $query);
        $token = $query['redsys'] ?? null;
        $this->assertNotEmpty($token, 'la vuelta no ha emitido pase');

        // Lo que hace una caché al llenarse, o al reiniciarse el contenedor que la aloja.
        Cache::flush();

        $this->assertIsArray(
            RedsysReturnController::handoff()->get(RedsysReturnController::cacheKey($token)),
            'el pase se fue con la caché: quien pagó volvería del banco sin su confirmación',
        );

        // Y sigue sirviendo de verdad: la home lo consume y deja el pedido confirmado.
        $this->actingAs($user)->get(route('home', ['redsys' => $token]))->assertOk();

        $this->assertNull(
            RedsysReturnController::handoff()->get(RedsysReturnController::cacheKey($token)),
            'el pase es de UN solo uso y no se ha consumido',
        );
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
        $owner = $this->actingAs($user)->get('/?redsys='.$token);
        $owner->assertOk()
            // #106 (validación visual 2026-05-26): además de entregar el desenlace, el layout debe
            // abrir el cajón automáticamente (Alpine lee `data-purchase-open`). Sin esto el cliente
            // vuelve de Redsys y NO ve el resultado hasta hacer click.
            ->assertSee('data-purchase-open="1"', false);

        $this->assertSame(
            [SidebarEntry::OUTCOME_CONFIRMED, $payment->payable->code],
            $this->outcomeInBoot($owner)
        );

        // Token consumido (one-shot): la segunda GET ya no entrega nada.
        $second = $this->actingAs($user)->get('/?redsys='.$token);
        $this->assertSame([null, null], $this->outcomeInBoot($second));
    }

    public function test_home_get_does_not_apply_token_for_a_different_user(): void
    {
        // Defensa: aunque un atacante capture el token y se loguee con otra cuenta, no
        // debe aplicar el resultado. El token NO se consume (ver el test de abajo): quien
        // lo tiene que usar es su dueño.
        [$payment] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'));
        $token = $this->tokenFromRedirect($response);

        $bob = User::factory()->create();
        $asBob = $this->actingAs($bob)->get('/?redsys='.$token);
        $asBob->assertOk();

        // ⚠️ Se mira el PAYLOAD, no la sesión: el layout la consume al pintar, así que
        // `assertNull(session(...))` sería cierto pase lo que pase (medido por mutación).
        $this->assertSame([null, null], $this->outcomeInBoot($asBob));
        $asBob->assertDontSee('data-purchase-open="1"', false);
    }

    public function test_home_get_does_not_apply_token_when_unauthenticated(): void
    {
        // Sin autenticación, el token NO se aplica. Defensa contra que un atacante anónimo
        // use un token capturado para llegar al paso 6 (no podría — la sesión que se
        // escribiría no se asociaría a ningún usuario en el sidebar). Tampoco se consume.
        [$payment] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'));
        $token = $this->tokenFromRedirect($response);

        $anonymous = $this->get('/?redsys='.$token);
        $anonymous->assertOk();

        $this->assertSame([null, null], $this->outcomeInBoot($anonymous));
        $anonymous->assertDontSee('data-purchase-open="1"', false);
    }

    /**
     * Fase 3 · paso 4d — **una vuelta ajena no le quema el token a su dueño.**
     *
     * Antes se consumía (`Cache::pull`) en la primera línea y se validaba después, buscando que un
     * token capturado no fuera reutilizable. El efecto real era el contrario: como el token va atado
     * a su `user_id`, un tercero **no podía usarlo pero sí QUEMARLO** — bastaba con abrir la URL de
     * la vuelta sin sesión para que el cliente legítimo perdiera su confirmación y se encontrara un
     * carrito vacío después de haber pagado. Ahora se mira, se valida y solo entonces se consume.
     */
    public function test_a_stranger_cannot_burn_the_token_of_its_owner(): void
    {
        [$payment, $user] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'));
        $token = $this->tokenFromRedirect($response);

        // Alguien sin sesión, y alguien con otra cuenta, pasan por la URL de la vuelta.
        $this->get('/?redsys='.$token)->assertOk();
        $this->actingAs(User::factory()->create())->get('/?redsys='.$token)->assertOk();

        $this->assertNotNull(
            RedsysReturnController::handoff()->get(RedsysReturnController::cacheKey($token)),
            'un tercero no puede consumir el token: el dueño todavía no lo ha usado'
        );

        // Y el dueño lo sigue teniendo entero.
        $owner = $this->actingAs($user)->get('/?redsys='.$token);
        $owner->assertOk();
        $this->assertSame(
            [SidebarEntry::OUTCOME_CONFIRMED, $payment->payable->code],
            $this->outcomeInBoot($owner)
        );
    }

    /** Y una vez aplicado, sigue siendo de un solo uso. */
    public function test_the_token_is_consumed_once_it_has_been_applied(): void
    {
        [$payment, $user] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ok'), $this->makePayload($payment, '0000'));
        $token = $this->tokenFromRedirect($response);

        $this->actingAs($user)->get('/?redsys='.$token)->assertOk();

        $this->assertNull(
            RedsysReturnController::handoff()->get(RedsysReturnController::cacheKey($token)),
            'tras aplicarse, el token tiene que desaparecer de la cache'
        );
    }

    public function test_browser_return_ko_writes_failed_code_to_session_on_follow_up(): void
    {
        [$payment, $user] = $this->setupPaidableOrder();
        $response = $this->post(route('payments.redsys.return.ko'), $this->makePayload($payment, '0101'));
        $response->assertStatus(303);

        $redirect = (string) $response->headers->get('Location');
        parse_str(parse_url($redirect, PHP_URL_QUERY) ?? '', $query);
        $token = $query['redsys'];

        $owner = $this->actingAs($user)->get('/?redsys='.$token);
        $owner->assertOk();
        $this->assertSame(
            [SidebarEntry::OUTCOME_FAILED, $payment->payable->code],
            $this->outcomeInBoot($owner)
        );

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
        $cached = RedsysReturnController::handoff()->get(RedsysReturnController::cacheKey($token));
        $this->assertSame('idempotent_paid', $cached['outcome']);
    }

    public function test_browser_return_get_without_data_with_already_failed_payment_redirects_with_denied_token(): void
    {
        // `DECISIONES #454`: la notificación llegó ANTES que el navegador y el banco dijo que no
        // (denegación, cancelación en la pasarela o una excepción SIS de la que Redsys también
        // notifica). El fallback tiene que dar el MISMO desenlace que la vuelta firmada por UrlKO
        // —token de rechazo, con reintento— y no «verificando tu pago», que afirma lo contrario.
        [$payment, $user] = $this->setupPaidableOrder();
        $payment->forceFill(['status' => Payment::STATUS_FAILED])->save();

        $response = $this->actingAs($user)->get(route('payments.redsys.return.ko'));

        $response->assertStatus(303);
        $token = $this->tokenFromRedirect($response);
        $cached = RedsysReturnController::handoff()->get(RedsysReturnController::cacheKey($token));
        $this->assertSame(RedsysReturnOutcome::Denied->value, $cached['outcome']);
        $this->assertSame($payment->payable->code, $cached['order_code']);
        $this->assertNull(session('purchase.verifying_code'), 'un rechazo ya notificado no es «verificando»');

        // Y la portada lo consume como el rechazo firmado: el cajón abre en el paso de KO.
        $owner = $this->actingAs($user)->get('/?redsys='.$token);
        $owner->assertOk();
        $this->assertSame([SidebarEntry::OUTCOME_FAILED, $payment->payable->code], $this->outcomeInBoot($owner));

        // El pedido no se toca: sigue pendiente y caducará solo (`orders:expire`).
        $this->assertSame(Order::STATUS_PENDING, $payment->payable->fresh()->status);
    }

    public function test_browser_return_get_without_data_resolves_by_the_latest_attempt_not_by_an_older_pending_order(): void
    {
        // Medido el 2026-09-11 con el terminal de pruebas de CaixaBank (`DECISIONES #453`): el
        // cliente dejó un pedido pendiente, empezó otro y lo canceló en la pasarela; la notificación
        // marcó `failed` el segundo antes de que volviera el navegador. Buscando solo `pending|paid`,
        // la búsqueda saltaba el intento recién fallido y caía en el pedido ANTERIOR, al que se le
        // decía «verificando tu pago · tu banco ha procesado el pago». Lo que decide es el ÚLTIMO
        // intento, sea cual sea su desenlace.
        [$older, $user] = $this->setupPaidableOrder();
        $newerOrder = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-RC'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PENDING, 'subtotal' => 1000, 'total' => 1000,
            'currency' => 'EUR', 'expires_at' => now()->addMinutes(15),
        ]);
        $newer = Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $newerOrder->id,
            'provider' => 'redsys', 'amount' => 1000, 'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED, 'gateway_order' => '0000'.str_pad((string) $newerOrder->id, 6, '0', STR_PAD_LEFT),
        ]);
        $this->assertGreaterThan($older->id, $newer->id, 'el intento fallido tiene que ser el más reciente');

        $response = $this->actingAs($user)->get(route('payments.redsys.return.ko'));

        $response->assertStatus(303);
        $cached = RedsysReturnController::handoff()->get(RedsysReturnController::cacheKey($this->tokenFromRedirect($response)));
        $this->assertSame(RedsysReturnOutcome::Denied->value, $cached['outcome']);
        $this->assertSame($newerOrder->code, $cached['order_code'], 'el rechazo es del intento recién fallido, no del pedido anterior');
        $this->assertNull(session('purchase.verifying_code'), 'el pedido pendiente anterior no se cuela como «verificando»');
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
