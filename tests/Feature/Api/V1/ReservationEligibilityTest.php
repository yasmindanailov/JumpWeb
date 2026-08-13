<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\ReservationAdmissionPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 4 · paso 4.0b — `GET /api/v1/me/reservation-eligibility`.
 *
 * El aviso temprano de «¿puedo reservar?». La web lo hace desde siempre al pasar del carrito a la
 * identificación; la API solo tenía `POST /orders`, que **crea el pedido y retiene aforo**, así que
 * la SPA no podía avisar sin comprometerse a comprar.
 *
 * **El test que justifica el endpoint entero es `test_asking_does_not_consume_an_attempt`.** Si
 * alguien cambia `mayReserve()` por `admitReservation()` —dos palabras— el endpoint pasa a gastar
 * ficha del limitador y la conducta que se rompe es la peor posible: el cliente que MIRA dos veces
 * su carrito se queda sin poder confirmar su segunda compra del minuto. Es el mismo hallazgo que
 * el paso 2 de Fase 3 ya pagó una vez —«el limitador contaba pantallas en vez de reservas»—.
 */
class ReservationEligibilityTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/me/reservation-eligibility';

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

    /** @return array<string, mixed> */
    private function cart(): array
    {
        return ['items' => [[
            'product_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'quantity' => 1,
        ]]];
    }

    private function fillPendingOrders(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Order::create([
                'user_id' => $this->user->id, 'code' => 'PEND-'.$i, 'status' => Order::STATUS_PENDING,
                'subtotal' => 100, 'tax' => 0, 'total' => 100, 'currency' => 'EUR',
                'expires_at' => now()->addHour(),
            ]);
        }
    }

    // ── El camino feliz ───────────────────────────────────────────────────────────────────────

    public function test_a_clean_holder_may_reserve(): void
    {
        $this->actingAs($this->user)->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('reason', null)
            ->assertJsonPath('max_pending_orders', null);
    }

    /**
     * `RGPD-04`: toda respuesta autenticada de la API va `no-store`.
     *
     * Se comprueba la DIRECTIVA y no la cadena, porque Symfony normaliza y reordena `Cache-Control`
     * — mismo criterio que `MeTest` y que el resto de superficies con PII del repo.
     */
    public function test_the_response_is_not_stored(): void
    {
        $header = (string) $this->actingAs($this->user)->getJson(self::PATH)
            ->assertOk()
            ->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $header);
        $this->assertStringContainsString('private', $header);
    }

    // ── Los tres motivos, con el CÓDIGO PÚBLICO y no la constante del dominio ─────────────────

    public function test_a_paused_installation_reports_the_pause(): void
    {
        Setting::updateOrCreate(['key' => 'reservations.paused'], ['value' => '1']);
        Setting::flushMemo();

        $this->actingAs($this->user)->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('reason', 'reservations_paused')
            ->assertJsonPath('max_pending_orders', null);
    }

    /**
     * ⚠️ El código público es `too_many_pending_orders` y la constante del dominio es
     * `too_many_pending`. Los dos nombres existen a propósito, y por eso hay un mapa
     * (`Http\Api\AdmissionCodeMap`) en vez de exponer la constante tal cual.
     */
    public function test_too_many_pending_orders_reports_its_limit(): void
    {
        $this->fillPendingOrders(ReservationAdmissionPolicy::MAX_PENDING_PER_USER);

        $this->actingAs($this->user)->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('reason', 'too_many_pending_orders')
            ->assertJsonPath('max_pending_orders', ReservationAdmissionPolicy::MAX_PENDING_PER_USER);
    }

    public function test_hitting_the_rate_limit_is_reported_without_being_an_error(): void
    {
        for ($i = 0; $i < ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE; $i++) {
            $this->actingAs($this->user)->postJson(self::ROOT.'/orders', $this->cart())->assertCreated();
        }

        // 200, no 429: preguntar salió BIEN. El 429 lo devuelve intentar CREAR.
        $this->actingAs($this->user)->getJson(self::PATH)
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('allowed', false)
            ->assertJsonPath('reason', 'too_many_requests');
    }

    // ── LA razón de ser del endpoint ──────────────────────────────────────────────────────────

    /**
     * **Preguntar no gasta.** Se consulta muchas más veces que el límite por minuto y, después, una
     * compra REAL sigue admitiéndose. Si el controlador usara `admitReservation()` —la variante que
     * consume—, la primera compra tras mirar el carrito cuatro veces sería rechazada con 429.
     */
    public function test_asking_does_not_consume_an_attempt(): void
    {
        $consultas = ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE * 3;

        for ($i = 0; $i < $consultas; $i++) {
            $this->actingAs($this->user)->getJson(self::PATH)
                ->assertOk()
                ->assertJsonPath('allowed', true);
        }

        $this->actingAs($this->user)->postJson(self::ROOT.'/orders', $this->cart())
            ->assertCreated();
    }

    /**
     * Y el espejo del anterior: lo que sí consume sigue consumiendo. Sin este caso, alguien podría
     * «arreglar» el test anterior desactivando el limitador y los dos pasarían.
     */
    public function test_creating_still_consumes_its_attempt(): void
    {
        RateLimiter::clear('reservations:'.$this->user->id);

        for ($i = 0; $i < ReservationAdmissionPolicy::RESERVATIONS_PER_MINUTE; $i++) {
            $this->actingAs($this->user)->postJson(self::ROOT.'/orders', $this->cart())->assertCreated();
        }

        $this->actingAs($this->user)->postJson(self::ROOT.'/orders', $this->cart())
            ->assertStatus(429);
    }

    // ── Titularidad ───────────────────────────────────────────────────────────────────────────

    /** Sin sesión no hay a quién preguntar por: 401, y ni un dato del sistema en la respuesta. */
    public function test_it_requires_a_session(): void
    {
        $this->getJson(self::PATH)
            ->assertUnauthorized()
            ->assertValidResponse(401);
    }

    /**
     * El veredicto es SIEMPRE del titular autenticado: no hay parámetro por el que preguntar por
     * otro. Se comprueba con dos titulares en estados distintos y la misma URL.
     */
    public function test_the_verdict_belongs_to_the_authenticated_holder_only(): void
    {
        $this->fillPendingOrders(ReservationAdmissionPolicy::MAX_PENDING_PER_USER);

        $this->actingAs($this->user)->getJson(self::PATH)
            ->assertOk()
            ->assertJsonPath('allowed', false);

        $this->actingAs(User::factory()->create())->getJson(self::PATH)
            ->assertOk()
            ->assertJsonPath('allowed', true);
    }
}
