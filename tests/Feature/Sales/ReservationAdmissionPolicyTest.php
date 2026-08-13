<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\AdmissionDecision;
use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Contracts\RetryAdmission;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Services\ReservationAdmissionPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Fase 3 · paso 2 — la política de admisión de reservas, probada por sí misma.
 *
 * Las reglas vivían dentro de `Livewire\Tickets\Purchase` y solo se podían ejercitar a través del
 * sidebar: cada caso exigía montar un carrito, un catálogo y un usuario, y lo que se probaba era
 * la pantalla, no la regla. Ahora la regla tiene tests propios —directos y baratos— y los del
 * sidebar comprueban lo suyo: que traduce bien el veredicto.
 *
 * Los tres cambios de comportamiento decididos al extraer (`DECISIONES #28`) tienen aquí su
 * testigo: el reintento no cuenta contra el tope de pendientes, sí pasa por el limitador, y
 * consultar no consume ficha.
 */
class ReservationAdmissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ReservationAdmission $policy;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08 10:00:00');
        $this->user = User::factory()->create();
        $this->policy = app(ReservationAdmission::class);
        RateLimiter::clear('reservation-confirm:'.$this->user->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function userId(): int
    {
        return (int) $this->user->id;
    }

    /** Pausa las reservas online (#218). `updateOrCreate` dispara `saved`, que invalida el memo de `PERF-01`. */
    private function pauseReservations(bool $paused = true): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => $paused ? '1' : '0', 'group' => 'maintenance']);
    }

    private function pendingOrder(string $code, ?Carbon $expiresAt, string $status = Order::STATUS_PENDING): Order
    {
        return Order::create([
            'user_id' => $this->user->id,
            'code' => $code,
            'status' => $status,
            'subtotal' => 1000, 'total' => 1000,
            'expires_at' => $expiresAt,
        ]);
    }

    // ── Crear una reserva ─────────────────────────────────────────────────────────────────────

    public function test_a_clean_user_is_admitted(): void
    {
        $this->assertTrue($this->policy->mayReserve($this->userId())->allowed);
        $this->assertTrue($this->policy->admitReservation($this->userId())->allowed);
    }

    /**
     * El cambio de comportamiento decidido en `DECISIONES #28`: CONSULTAR no gasta ficha.
     *
     * Antes, el sidebar consumía una al pasar del carrito al paso de pago y otra al confirmar, así
     * que la SEGUNDA compra del mismo minuto se bloqueaba pese a que el tope son 3 reservas.
     */
    public function test_checking_does_not_consume_an_attempt_but_admitting_does(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->assertTrue($this->policy->mayReserve($this->userId())->allowed);
        }

        // Diez consultas después, siguen disponibles las tres reservas del minuto.
        foreach (range(1, ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE) as $ignored) {
            $this->assertTrue($this->policy->admitReservation($this->userId())->allowed);
        }

        $this->assertSame(
            AdmissionDecision::RATE_LIMITED,
            $this->policy->admitReservation($this->userId())->reason,
        );
    }

    /** Consultar tras agotar el cubo dice la verdad: no admite, y tampoco consume. */
    public function test_checking_reports_the_exhausted_limiter(): void
    {
        foreach (range(1, ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE) as $ignored) {
            $this->policy->admitReservation($this->userId());
        }

        $this->assertSame(AdmissionDecision::RATE_LIMITED, $this->policy->mayReserve($this->userId())->reason);
    }

    public function test_the_pending_cap_denies_with_the_configured_max(): void
    {
        foreach (range(1, ReservationAdmissionPolicy::MAX_PENDING_PER_USER) as $i) {
            $this->pendingOrder('P-'.$i, now()->addHour());
        }

        $decision = $this->policy->admitReservation($this->userId());

        $this->assertTrue($decision->denied());
        $this->assertSame(AdmissionDecision::TOO_MANY_PENDING, $decision->reason);
        $this->assertSame(ReservationAdmissionPolicy::MAX_PENDING_PER_USER, $decision->context['max']);
    }

    /** Un hold vencido ya devolvió su plaza al inventario: no puede seguir ocupando el cupo. */
    public function test_expired_holds_do_not_count_toward_the_pending_cap(): void
    {
        foreach (range(1, ReservationAdmissionPolicy::MAX_PENDING_PER_USER) as $i) {
            $this->pendingOrder('P-'.$i, now()->subMinute());
        }

        $this->assertTrue($this->policy->admitReservation($this->userId())->allowed);
    }

    /** Los pedidos de OTRO titular no gastan el cupo de este. */
    public function test_the_cap_is_per_user(): void
    {
        $other = User::factory()->create();
        foreach (range(1, ReservationAdmissionPolicy::MAX_PENDING_PER_USER) as $i) {
            Order::create([
                'user_id' => $other->id, 'code' => 'O-'.$i, 'status' => Order::STATUS_PENDING,
                'subtotal' => 1000, 'total' => 1000, 'expires_at' => now()->addHour(),
            ]);
        }

        $this->assertTrue($this->policy->admitReservation($this->userId())->allowed);
    }

    /**
     * La pausa se comprueba ANTES que el limitador: con las reservas paradas, el cliente no puede
     * hacer nada, y gastarle fichas por intentarlo le dejaría bloqueado también al reanudarse.
     */
    public function test_the_pause_denies_first_and_costs_no_attempt(): void
    {
        $this->pauseReservations();

        $this->assertSame(
            AdmissionDecision::RESERVATIONS_PAUSED,
            $this->policy->admitReservation($this->userId())->reason,
        );

        $this->pauseReservations(false);

        // Las tres reservas del minuto siguen intactas tras los intentos rechazados por la pausa.
        foreach (range(1, ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE) as $ignored) {
            $this->assertTrue($this->policy->admitReservation($this->userId())->allowed);
        }
    }

    // ── Reintentar un pago ────────────────────────────────────────────────────────────────────

    public function test_a_retry_extends_the_hold_and_returns_the_order(): void
    {
        $order = $this->pendingOrder('R-1', now()->addMinutes(2));

        $verdict = $this->policy->admitPaymentRetry($this->userId(), 'R-1');

        $this->assertTrue($verdict->allowed);
        $this->assertSame($order->id, $verdict->order->id);
        $this->assertSame(
            now()->addMinutes(PaymentSettings::holdMinutes())->toDateTimeString(),
            $order->fresh()->expires_at->toDateTimeString(),
            'el reintento admitido tiene que abrir una ventana de retención COMPLETA'
        );
    }

    /**
     * El cambio de comportamiento decidido en `DECISIONES #28`: el reintento NO cuenta contra el
     * tope de pendientes. Un reintento no crea aforo —reusa la plaza que ese mismo pedido ya
     * retiene y que ya cuenta en el tope—, así que aplicárselo dejaba sin poder pagar justo a quien
     * más pedidos pendientes acumulaba.
     */
    public function test_a_retry_is_not_blocked_by_the_pending_cap(): void
    {
        foreach (range(1, ReservationAdmissionPolicy::MAX_PENDING_PER_USER) as $i) {
            $this->pendingOrder('R-'.$i, now()->addHour());
        }

        // Crear otra reserva sí está topado…
        $this->assertTrue($this->policy->admitReservation($this->userId())->denied());
        // …pero pagar una de las que ya tiene, no.
        $this->assertTrue($this->policy->admitPaymentRetry($this->userId(), 'R-1')->allowed);
    }

    /** El reintento sí pasa por el limitador, en las dos superficies (`DECISIONES #28`). */
    public function test_a_retry_consumes_an_attempt(): void
    {
        $this->pendingOrder('R-1', now()->addHour());

        foreach (range(1, ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE) as $ignored) {
            $this->assertTrue($this->policy->admitPaymentRetry($this->userId(), 'R-1')->allowed);
        }

        $this->assertSame(
            RetryAdmission::RATE_LIMITED,
            $this->policy->admitPaymentRetry($this->userId(), 'R-1')->reason,
        );
    }

    /**
     * Un reintento rechazado por el limitador NO extiende el hold: si extendiera, martillear el
     * botón mantendría la plaza retenida indefinidamente sin pagar, que es justo lo que el tope
     * quiere evitar.
     */
    public function test_a_throttled_retry_does_not_extend_the_hold(): void
    {
        $order = $this->pendingOrder('R-1', now()->addMinutes(2));

        foreach (range(1, ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE) as $ignored) {
            $this->policy->admitPaymentRetry($this->userId(), 'R-1');
        }
        $expiresAfterAllowed = $order->fresh()->expires_at;

        Carbon::setTestNow(now()->addSeconds(5));
        $this->assertTrue($this->policy->admitPaymentRetry($this->userId(), 'R-1')->denied());

        $this->assertSame(
            $expiresAfterAllowed->toDateTimeString(),
            $order->fresh()->expires_at->toDateTimeString(),
        );
    }

    /**
     * Las cuatro formas de «no hay pedido reintentable» dan el MISMO motivo. Con el hold cruzado la
     * plaza pudo cederse a otro cliente, así que reabrir el cobro llevaría a sobreventa; con un
     * pedido ajeno o inexistente, distinguirlos sería un oráculo de códigos de pedido.
     */
    public function test_nothing_retryable_answers_the_same_reason(): void
    {
        $this->pendingOrder('EXPIRED', now()->subMinute());
        $this->pendingOrder('PAID', null, Order::STATUS_PAID);

        $other = User::factory()->create();
        Order::create([
            'user_id' => $other->id, 'code' => 'ALIEN', 'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'expires_at' => now()->addHour(),
        ]);

        foreach (['EXPIRED', 'PAID', 'ALIEN', 'NO-EXISTE'] as $code) {
            $verdict = $this->policy->admitPaymentRetry($this->userId(), $code);

            $this->assertTrue($verdict->denied(), "«{$code}» no debería ser reintentable");
            $this->assertSame(RetryAdmission::NOT_RETRYABLE, $verdict->reason);
            $this->assertNull($verdict->order);
        }
    }

    /** Un pedido ajeno no se toca ni siquiera acertando su código: el `WHERE` lleva el titular. */
    public function test_a_denied_retry_does_not_touch_another_users_order(): void
    {
        $other = User::factory()->create();
        $alien = Order::create([
            'user_id' => $other->id, 'code' => 'ALIEN', 'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'expires_at' => now()->addMinutes(2),
        ]);
        $before = $alien->expires_at->toDateTimeString();

        $this->policy->admitPaymentRetry($this->userId(), 'ALIEN');

        $this->assertSame($before, $alien->fresh()->expires_at->toDateTimeString());
    }

    public function test_the_pause_also_stops_a_retry(): void
    {
        $order = $this->pendingOrder('R-1', now()->addMinutes(2));
        $this->pauseReservations();

        $verdict = $this->policy->admitPaymentRetry($this->userId(), 'R-1');

        $this->assertSame(RetryAdmission::RESERVATIONS_PAUSED, $verdict->reason);
        $this->assertSame(
            now()->addMinutes(2)->toDateTimeString(),
            $order->fresh()->expires_at->toDateTimeString(),
            'una pausa no puede extender la retención de nadie'
        );
    }
}
