<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ReservationAdmissionPolicy;
use App\Domain\Identity\Models\User;
use App\Livewire\Tickets\Purchase;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Auditoría 2026-05-26 (hallazgo E + L) — topes anti-abuso del flujo de compra:
 *  - cap de líneas por cesta (MAX_LINES_PER_CART)
 *  - cap de pedidos pending vivos por usuario (MAX_PENDING_PER_USER)
 *  - rate limit por usuario en confirmReservation (RESERVATIONS_PER_MINUTE)
 *  - defensa frente a manipulación de `$step` desde el cliente
 */
class PurchaseLimitsTest extends TestCase
{
    use RefreshDatabase;

    private string $today;

    private Zone $zone;

    private TicketType $jump1h;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-08');
        $this->today = Carbon::today()->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->today,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 100, 'online_capacity' => 100,
        ]);

        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->jump1h->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);

        $this->user = User::factory()->create();
        RateLimiter::clear('reservation-confirm:'.$this->user->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pendingFor(User $user, int $i): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-LIM'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PENDING,
            'subtotal' => 100, 'total' => 100,
        ]);

        return $order;
    }

    /** Coloca una cesta válida en sesión sin pasar por el flujo (atajo para tests). */
    private function withCart(int $qty = 1): array
    {
        return [
            ['ticket_type_id' => $this->jump1h->id, 'date' => $this->today, 'time' => '10:00:00', 'qty' => $qty],
        ];
    }

    public function test_cart_lines_are_capped_to_max(): void
    {
        $cart = [];
        for ($i = 0; $i < Purchase::MAX_LINES_PER_CART; $i++) {
            $cart[] = ['ticket_type_id' => $this->jump1h->id, 'date' => $this->today, 'time' => '10:00:00', 'qty' => 1, 'event_data' => [], 'addons' => []];
        }
        session(['purchase.cart' => $cart]);

        // Forzamos addToCart con una selección válida — debe rebotar con cart_too_large.
        Livewire::test(Purchase::class)
            ->call('selectType', $this->jump1h->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->set('qty', 1)
            ->call('addToCart')
            ->assertHasErrors('selection');
    }

    public function test_user_with_max_pending_orders_cannot_confirm_another(): void
    {
        for ($i = 1; $i <= ReservationAdmissionPolicy::MAX_PENDING_PER_USER; $i++) {
            $this->pendingFor($this->user, $i);
        }

        Livewire::actingAs($this->user)
            ->test(Purchase::class)
            ->set('step', 8)
            ->set('cart', $this->withCart(1))
            ->call('confirmReservation')
            ->assertSet('step', 4)
            ->assertHasErrors('cart');

        // No se creó un pedido adicional.
        $this->assertSame(ReservationAdmissionPolicy::MAX_PENDING_PER_USER, $this->user->orders()->count());
    }

    public function test_pending_with_expired_hold_does_not_count_toward_the_cap(): void
    {
        // Los pendientes con `expires_at` ya pasado son "muertos" (orders:expire los limpia).
        // No deben contar contra el cap → el usuario sí puede crear otro.
        Order::create([
            'user_id' => $this->user->id, 'code' => 'JJ-EXPIRED',
            'status' => Order::STATUS_PENDING, 'expires_at' => now()->subHour(),
            'subtotal' => 100, 'total' => 100,
        ]);
        for ($i = 1; $i < ReservationAdmissionPolicy::MAX_PENDING_PER_USER; $i++) {
            $this->pendingFor($this->user, $i);
        }
        // 1 caducado + 4 vivos = 5 totales, pero solo 4 cuentan → cabe uno más.

        Livewire::actingAs($this->user)
            ->test(Purchase::class)
            ->set('step', 8)
            ->set('cart', $this->withCart(1))
            ->call('confirmReservation')
            ->assertHasNoErrors('cart')
            ->assertSet('step', 9);   // 5.5b (#104): redirect a Redsys
    }

    public function test_rate_limit_blocks_after_max_confirmations_in_a_minute(): void
    {
        // Burst de N+1 intentos. El N+1 debe quedar bloqueado por rate limit.
        for ($i = 0; $i < ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE; $i++) {
            Livewire::actingAs($this->user)
                ->test(Purchase::class)
                ->set('step', 8)
                ->set('cart', $this->withCart(1))
                ->call('confirmReservation')
                ->assertHasNoErrors('cart')
                ->assertSet('step', 9);  // 5.5b (#104): cada intento exitoso redirige a Redsys
        }

        Livewire::actingAs($this->user)
            ->test(Purchase::class)
            ->set('step', 8)
            ->set('cart', $this->withCart(1))
            ->call('confirmReservation')
            ->assertSet('step', 4)
            ->assertHasErrors('cart');
    }

    /**
     * El límite cuenta RESERVAS CREADAS, no pantallas visitadas (Fase 3 · paso 2, `DECISIONES #28`).
     *
     * Este test ejercita el flujo COMPLETO —«Continuar» y luego «Confirmar»— dos veces seguidas, que
     * es lo que hace un cliente que compra entradas y a continuación un pack. Antes gastaba dos
     * fichas por compra (una al avanzar al paso de pago, que no crea nada, y otra al confirmar), así
     * que la segunda compra del minuto se bloqueaba aunque el tope sean 3. Ahora la ficha se gasta
     * donde nace el pedido que retiene aforo.
     */
    public function test_two_consecutive_purchases_in_the_same_minute_are_allowed(): void
    {
        foreach ([1, 2] as $ignored) {
            Livewire::actingAs($this->user)
                ->test(Purchase::class)
                ->set('cart', $this->withCart(1))
                ->call('checkout')            // carrito → paso de pago: NO consume ficha
                ->assertSet('step', 8)
                ->call('confirmReservation')  // aquí nace el pedido: SÍ la consume
                ->assertHasNoErrors('cart')
                ->assertSet('step', 9);
        }

        $this->assertSame(2, $this->user->orders()->count());
    }

    /**
     * Y el techo real sigue siendo el mismo: tres reservas por minuto. Con el flujo completo, la
     * cuarta ya no pasa — así que corregir el doble consumo no aflojó la protección, solo dejó de
     * castigar la navegación.
     */
    public function test_the_fourth_full_purchase_in_a_minute_is_still_blocked(): void
    {
        for ($i = 0; $i < ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE; $i++) {
            Livewire::actingAs($this->user)
                ->test(Purchase::class)
                ->set('cart', $this->withCart(1))
                ->call('checkout')
                ->call('confirmReservation')
                ->assertSet('step', 9);
        }

        // El aviso llega ya al pulsar «Continuar», sin llevar al cliente hasta la pantalla de pago
        // para decirle allí que no. Esa consulta temprana no consume ficha: por eso puede avisar
        // tantas veces como haga falta sin empeorar la situación de quien ya está limitado.
        Livewire::actingAs($this->user)
            ->test(Purchase::class)
            ->set('cart', $this->withCart(1))
            ->call('checkout')
            ->assertSet('step', 4)
            ->assertHasErrors('cart');

        $this->assertSame(ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE, $this->user->orders()->count());
    }

    public function test_unauthenticated_user_cannot_confirm_even_by_manipulating_step(): void
    {
        // Defensa frente a manipulación de propiedad pública `$step` desde el cliente: incluso
        // saltando a step=8, sin sesión NO se crea pedido (la guardia interna re-encamina a 5).
        Livewire::test(Purchase::class)
            ->set('step', 8)
            ->set('cart', $this->withCart(1))
            ->call('confirmReservation')
            ->assertSet('step', 5);

        $this->assertSame(0, Order::count());
    }

    public function test_unverified_user_can_confirm_pay_first(): void
    {
        // Pay-first (decisión clienta 2026-06-14): un usuario autenticado SIN verificar SÍ puede
        // COMPLETAR el pago (crea pedido firme + redirect a Redsys, paso 9). Antes el candado
        // `hasVerifiedEmail()` en confirmReservation lo rebotaba al carrito. El pago auto-verifica
        // la cuenta después (RedsysReturnHandler). La sesión sigue siendo obligatoria (ver el test
        // de usuario no autenticado, que SÍ se re-encamina a identificarse).
        $unverified = User::factory()->unverified()->create();

        Livewire::actingAs($unverified)
            ->test(Purchase::class)
            ->set('step', 8)
            ->set('cart', $this->withCart(1))
            ->call('confirmReservation')
            ->assertSet('step', 9);

        $this->assertSame(1, Order::where('user_id', $unverified->id)->count());
    }

    public function test_confirm_does_not_send_email_until_redsys_authorises(): void
    {
        // T3.1 / P-01 (#104, capa 5.5b): el email de confirmación se mueve a 5.5c — solo se
        // envía cuando Redsys autoriza (`Ds_Response` ∈ 0000–0099). En 5.5b solo se redirige
        // a la pasarela; aún no podemos garantizar al cliente que su reserva está "confirmada".
        Notification::fake();

        Livewire::actingAs($this->user)
            ->test(Purchase::class)
            ->set('step', 8)
            ->set('cart', $this->withCart(1))
            ->call('confirmReservation')
            ->assertSet('step', 9);     // redirect a Redsys

        Notification::assertNotSentTo($this->user, OrderConfirmation::class);
    }

    public function test_confirm_requires_step_to_be_eight(): void
    {
        // Si el cliente llama confirmReservation desde un paso que NO sea 8 (p. ej. step=4 carrito),
        // no se hace nada. Es la guardia inicial del método (early return).
        Livewire::actingAs($this->user)
            ->test(Purchase::class)
            ->set('step', 4)
            ->set('cart', $this->withCart(1))
            ->call('confirmReservation')
            ->assertSet('step', 4); // no cambia, no se crea pedido

        $this->assertSame(0, Order::count());
    }
}
