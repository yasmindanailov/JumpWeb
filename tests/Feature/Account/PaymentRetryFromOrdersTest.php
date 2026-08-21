<?php

namespace Tests\Feature\Account;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ReservationAdmissionPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Audit edge cases (2026-05-28) — Reintento de pago desde "Mis pedidos".
 *
 * Cierra el hueco descubierto en validación: el email `OrderPaymentDeclined` enviaba al
 * cliente a `/mi-cuenta/pedidos`, pero la vista no ofrecía botón para reintentar. Estos
 * tests blindan el flujo HTTP completo (controller `RetryPaymentController`) + el
 * comportamiento condicional del botón en la vista + la lógica de `Order::canBeRetried`.
 */
class PaymentRetryFromOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump1h;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => '2026-06-08',
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);
        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->jump1h->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);

        // Settings sandbox para que `Redsys::buildPaymentFormData` funcione.
        Setting::create(['key' => 'redsys_environment', 'value' => 'test', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_merchant_code', 'value' => '999008881', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_terminal', 'value' => '001', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_secret_key', 'value' => 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_currency', 'value' => '978', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_merchant_name', 'value' => 'SaltoPark', 'group' => 'payment']);
        Setting::create(['key' => 'redsys_next_gateway_order', 'value' => '100000', 'group' => 'payment']);
    }

    /**
     * Crea un Order pending con su Payment fallido (= cliente intentó pagar y Redsys denegó).
     * Este es el caso típico donde el botón "Reintentar el pago" debe aparecer.
     */
    private function makeRetryableOrder(User $user, string $paymentStatus = Payment::STATUS_FAILED): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-RT'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'expires_at' => now()->addMinutes(15),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $this->jump1h->id,
            'slot_id' => $this->slot->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
        Payment::create([
            'payable_type' => (new Order)->getMorphClass(), 'payable_id' => $order->id,
            'provider' => 'redsys', 'amount' => 1000, 'currency' => 'EUR',
            'status' => $paymentStatus,
            'gateway_order' => '0000'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
        ]);

        return $order;
    }

    // ── Order::canBeRetried() ────────────────────────────────────────────────────────────

    public function test_can_be_retried_when_pending_not_expired_with_failed_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_FAILED);

        $this->assertTrue($order->load('payments')->canBeRetried());
    }

    public function test_can_be_retried_when_pending_with_pending_payment(): void
    {
        // Caso: cliente entró en paso 9, nunca volvió. Payment sigue pending. Mientras la
        // Order no expire, el cliente puede reintentar (en lugar de quedarse colgado).
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_PENDING);

        $this->assertTrue($order->load('payments')->canBeRetried());
    }

    public function test_cannot_be_retried_when_order_paid(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_PAID);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();

        $this->assertFalse($order->load('payments')->canBeRetried());
    }

    public function test_cannot_be_retried_when_order_expired(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);
        $order->forceFill(['expires_at' => now()->subMinutes(5)])->save();

        $this->assertFalse($order->load('payments')->canBeRetried());
    }

    public function test_cannot_be_retried_when_order_has_no_payment(): void
    {
        // Provisional / abandono limpio: la Order existe pero el cliente nunca llegó a la
        // pasarela → no hay Payment. El flujo normal es `proceed`/`confirmReservation`,
        // no "reintentar".
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-NOPAY',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertFalse($order->load('payments')->canBeRetried());
    }

    // ── RetryPaymentController ────────────────────────────────────────────────────────────

    public function test_retry_endpoint_creates_new_payment_and_renders_redsys_form(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);

        $response = $this->actingAs($user)
            ->post(route('account.orders.retry', ['code' => $order->code]));

        $response->assertOk();
        // La respuesta renderiza la vista intermedia con el auto-POST.
        $response->assertSeeText(__('tickets.pay_redirecting_title'));
        // Form auto-POST con action a la pasarela sandbox.
        $response->assertSee('action="https://sis-t.redsys.es:25443/sis/realizarPago"', false);
        // Los 3 inputs canónicos.
        $response->assertSee('name="Ds_SignatureVersion"', false);
        $response->assertSee('name="Ds_MerchantParameters"', false);
        $response->assertSee('name="Ds_Signature"', false);

        // Se creó un Payment nuevo con nuevo gateway_order (Redsys §5 exige unicidad).
        $payments = Payment::where('payable_id', $order->id)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame(Payment::STATUS_FAILED, $payments[0]->status); // original intacto
        $this->assertSame(Payment::STATUS_PENDING, $payments[1]->status); // nuevo
        $this->assertNotSame($payments[0]->gateway_order, $payments[1]->gateway_order);
    }

    public function test_retry_supersedes_a_prior_pending_payment(): void
    {
        // Auditoría Fase 1 (complemento C1): al reintentar, el intento `pending` previo se marca
        // `superseded` (NO `failed` — eso dispararía la idempotencia de denegación + un email). Así
        // deja de ser el intento activo; si su autorización llega TARDE, el handler la trata como
        // incidencia de cobro (la Order ya estará PAID por el reintento) en vez de duplicar tickets.
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_PENDING);
        $originalPayment = Payment::where('payable_id', $order->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('account.orders.retry', ['code' => $order->code]))
            ->assertOk();

        $originalPayment->refresh();
        $this->assertSame(Payment::STATUS_SUPERSEDED, $originalPayment->status, 'el intento previo se descarta');

        $payments = Payment::where('payable_id', $order->id)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame(Payment::STATUS_SUPERSEDED, $payments[0]->status);
        $this->assertSame(Payment::STATUS_PENDING, $payments[1]->status); // el nuevo intento activo
        // El pedido sigue siendo reintentable (tiene un pending activo).
        $this->assertTrue($order->load('payments')->canBeRetried());
    }

    public function test_retry_endpoint_extends_expires_at(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);
        $originalExpiry = $order->expires_at;

        // Avanzamos el reloj 10 min para que el reintento extienda la ventana.
        Carbon::setTestNow(now()->addMinutes(10));

        try {
            $this->actingAs($user)
                ->post(route('account.orders.retry', ['code' => $order->code]))
                ->assertOk();

            $order->refresh();
            $this->assertTrue($order->expires_at->isAfter($originalExpiry),
                'expires_at debe extenderse en el reintento');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_retry_endpoint_redirects_with_status_when_order_expired(): void
    {
        // Carrera con orders:expire: entre el render de "Mis pedidos" y el POST, la Order
        // pudo haber sido marcada expired. El controller revalida y redirige con mensaje.
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);
        $order->forceFill(['status' => Order::STATUS_EXPIRED, 'expires_at' => now()->subMinutes(5)])->save();

        $response = $this->actingAs($user)
            ->post(route('account.orders.retry', ['code' => $order->code]));

        $response->assertRedirect(route('account.orders'));
        $response->assertSessionHas('status', 'order-retry-unavailable');

        // No se creó Payment adicional.
        $this->assertSame(1, Payment::where('payable_id', $order->id)->count());
    }

    /**
     * Fase 3 · paso 2 — este endpoint pasa ahora por la MISMA política de admisión que el sidebar,
     * así que hereda su límite de frecuencia por titular (antes solo tenía el `throttle:6,1` por
     * IP/sesión de la ruta, y esa asimetría no la había decidido nadie).
     *
     * El aviso es propio: la reserva NO ha caducado —sigue viva y pagable en cuanto pase el
     * minuto—, y reutilizar aquí el mensaje de «ha caducado y la plaza se ha liberado» le habría
     * dicho a quien pulsó dos veces seguidas que había perdido su reserva.
     */
    public function test_retry_endpoint_is_rate_limited_per_user_with_its_own_notice(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);

        for ($i = 0; $i < ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE; $i++) {
            $this->actingAs($user)
                ->post(route('account.orders.retry', ['code' => $order->code]))
                ->assertOk();
        }

        $response = $this->actingAs($user)
            ->post(route('account.orders.retry', ['code' => $order->code]));

        $response->assertRedirect(route('account.orders'));
        $response->assertSessionHas('status', 'order-retry-throttled');

        // El pedido sigue vivo: el rechazo por frecuencia no toca su retención.
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertTrue($order->fresh()->expires_at->isFuture());
    }

    public function test_retry_endpoint_denies_access_to_another_users_order(): void
    {
        // Defensa IDOR: aunque alguien manipule el code en la URL, el controller filtra
        // por user_id. El response es el mismo que si la Order no existiera (404-ish via
        // redirect con status) — no revelamos si esa Order existe para otro usuario.
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $alicesOrder = $this->makeRetryableOrder($alice);

        $response = $this->actingAs($bob)
            ->post(route('account.orders.retry', ['code' => $alicesOrder->code]));

        $response->assertRedirect(route('account.orders'));
        $response->assertSessionHas('status', 'order-retry-unavailable');
        // El Order de Alice sigue intacto (sin Payment nuevo).
        $this->assertSame(1, Payment::where('payable_id', $alicesOrder->id)->count());
    }

    public function test_retry_endpoint_requires_authentication(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);

        // SIN actingAs → middleware auth redirige al login.
        $response = $this->post(route('account.orders.retry', ['code' => $order->code]));

        $response->assertRedirect(route('login'));
        $this->assertSame(1, Payment::where('payable_id', $order->id)->count());
    }

    public function test_retry_endpoint_requires_verified_email(): void
    {
        // El middleware `verified` aplica al group; sin email verificado se redirige a
        // la página de aviso de verificación.
        $user = User::factory()->unverified()->create();
        $order = $this->makeRetryableOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.retry', ['code' => $order->code]))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_retry_endpoint_returns_405_on_get(): void
    {
        // La ruta es POST estricto (CSRF + intent change). GET → 405.
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);

        $this->actingAs($user)
            ->get(route('account.orders.retry', ['code' => $order->code]))
            ->assertStatus(405);
    }

    // ── Vista Mis pedidos (account/orders.blade.php) ──────────────────────────────────────

    public function test_orders_page_shows_retry_button_for_retryable_orders(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSeeText(__('account.orders.retry_payment'))
            ->assertSee(route('account.orders.retry', ['code' => $order->code]));
    }

    public function test_orders_page_does_not_show_retry_button_for_paid_orders(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_PAID);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertDontSeeText(__('account.orders.retry_payment'));
    }

    public function test_orders_page_does_not_show_retry_button_for_expired_orders(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);
        $order->forceFill(['expires_at' => now()->subMinutes(5)])->save();

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertDontSeeText(__('account.orders.retry_payment'));
    }

    // ── displayStatus + expired hint (#116 pulido tras feedback de la clienta) ───────────

    public function test_display_status_returns_expired_when_pending_with_past_expires_at(): void
    {
        // Caso reportado por la clienta: Orders con status='pending' en BD pero `expires_at`
        // ya cruzado. El cron del sistema no corre en local → orders:expire no las actualiza
        // entre ticks. La UI debe mostrar el estado efectivo, no el desfasado.
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);
        $order->forceFill(['expires_at' => now()->subMinutes(10)])->save();

        $this->assertTrue($order->isExpiredInPractice());
        $this->assertSame(Order::STATUS_EXPIRED, $order->displayStatus());
    }

    public function test_display_status_returns_pending_when_not_expired(): void
    {
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);

        $this->assertFalse($order->isExpiredInPractice());
        $this->assertSame(Order::STATUS_PENDING, $order->displayStatus());
    }

    public function test_display_status_returns_paid_unchanged(): void
    {
        // Una Order ya paid no debe ser "re-clasificada" por el accessor — devolvemos
        // el status real (paid) aunque su expires_at original esté en el pasado.
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_PAID);
        $order->forceFill(['status' => Order::STATUS_PAID, 'expires_at' => null, 'paid_at' => now()])->save();

        $this->assertFalse($order->isExpiredInPractice());
        $this->assertSame(Order::STATUS_PAID, $order->displayStatus());
    }

    public function test_orders_page_renders_expired_badge_for_pending_with_past_expires_at(): void
    {
        // El cliente con varios pedidos pending donde solo algunos pueden reintentarse
        // necesita ver claramente CUÁLES son los caducados. El badge usa displayStatus()
        // y muestra "Caducado" en lugar del crudo "Pendiente de pago".
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user);
        $order->forceFill(['expires_at' => now()->subMinutes(10)])->save();

        $response = $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk();

        // Status efectivo "Caducado", no "Pendiente de pago".
        $response->assertSeeText(__('tickets.statuses.expired'));
        $response->assertDontSeeText(__('account.orders.retry_payment'));
    }

    // ── Modal "Gestionar tu reserva" en Orders pagadas (#117) ──────────────────────────

    public function test_orders_page_shows_manage_button_only_for_paid_orders(): void
    {
        // Audit #117: el botón "Gestionar" aparece SOLO para Orders pagadas (las únicas
        // que tienen sentido gestionar: cambio de fecha, cancelación con devolución, etc.).
        // Para pending o expired no aplica.
        $user = User::factory()->create();
        $paidOrder = $this->makeRetryableOrder($user, Payment::STATUS_PAID);
        $paidOrder->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();
        $pendingOrder = $this->makeRetryableOrder($user); // sigue pending

        $response = $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk();

        // El botón "Gestionar" aparece para el Order paid.
        $response->assertSee(__('account.orders.manage'));
        // Aparece UNA SOLA VEZ (no en el pending). Como `manage` es palabra común, contamos
        // el botón por su clase específica.
        $this->assertSame(1, substr_count($response->getContent(), 'orders__manage-btn'));
    }

    public function test_manage_modal_contains_order_code_and_link_to_contact(): void
    {
        // Audit #117: el modal "Gestionar" muestra el código de pedido destacado (para
        // que el cliente lo copie fácilmente) + CTA "Ir a contacto". Sin formularios:
        // las gestiones se valoran caso por caso vía contacto.
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_PAID);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();

        $response = $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk();

        // Título del modal + texto explicativo profesional + nota sobre el código.
        $response->assertSee(__('account.orders.manage_title'));
        $response->assertSee(__('account.orders.manage_intro'));
        $response->assertSee(__('account.orders.manage_note'));

        // Código del pedido aparece DENTRO del modal con clase específica (destacado).
        $this->assertStringContainsString('manage__code-value', $response->getContent());
        $response->assertSee($order->code);

        // CTA "Ir a contacto" → enlace a la página /contacto.
        $response->assertSeeText(__('account.orders.manage_cta'));
        $response->assertSee(route('contacto'));
    }

    public function test_manage_modal_uses_aria_labelledby_for_accessibility(): void
    {
        // El modal debe ser accesible: aria-modal=true + aria-labelledby apuntando al
        // título. Esto permite que screen readers anuncien correctamente el diálogo.
        $user = User::factory()->create();
        $order = $this->makeRetryableOrder($user, Payment::STATUS_PAID);
        $order->forceFill(['status' => Order::STATUS_PAID, 'paid_at' => now()])->save();

        $response = $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk();

        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('aria-labelledby="manage-title-'.$order->id.'"', false);
    }
}
