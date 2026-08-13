<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 1 — `GET /api/v1/me/reservations`.
 *
 * El endpoint no filtra nada por su cuenta: delega en `Booking\Contracts\CustomerReservations`, el
 * mismo contrato que alimenta el sidebar de la web desde Fase 2. Por eso estos tests comprueban que
 * la API **hereda** las reglas del dominio —solo pagados, no cancelados, con franja, aún no
 * terminados— en vez de reimplementarlas: si alguna divergiera, tendríamos dos definiciones de
 * «mi próxima reserva» en el mismo producto.
 */
class MeReservationsTest extends ApiTestCase
{
    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function product(string $name): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);
    }

    private function slotOn(string $date, string $start = '10:00:00'): Slot
    {
        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => $start, 'end_time' => '23:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);
    }

    /** Reserva de libro: pedido pagado, línea principal con franja futura. */
    private function reservationFor(User $user, string $productName, string $date, string $status = Order::STATUS_PAID): void
    {
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)),
            'status' => $status, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000,
            'currency' => 'EUR', 'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);

        $order->items()->create([
            'ticket_type_id' => $this->product($productName)->id,
            'slot_id' => $this->slotOn($date)->id,
            'quantity' => 1, 'unit_price' => 1000, 'seats' => 1,
        ]);
    }

    public function test_it_lists_the_upcoming_reservations_of_the_authenticated_user(): void
    {
        $user = $this->verifiedUser();
        $this->reservationFor($user, 'Salto 1 hora', now()->addDays(3)->toDateString());

        $this->actingAs($user)->getJson(self::ROOT.'/me/reservations')
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.product_name', 'Salto 1 hora')
            ->assertJsonPath('data.0.date', now()->addDays(3)->toDateString())
            ->assertJsonPath('data.0.time_window', '10:00–11:00');
    }

    /** De la más próxima a la más lejana: el orden lo fija el dominio y la API lo respeta. */
    public function test_it_lists_them_nearest_first(): void
    {
        $user = $this->verifiedUser();
        $this->reservationFor($user, 'La lejana', now()->addDays(10)->toDateString());
        $this->reservationFor($user, 'La próxima', now()->addDays(2)->toDateString());

        $this->actingAs($user)->getJson(self::ROOT.'/me/reservations')
            ->assertOk()
            ->assertJsonPath('data.0.product_name', 'La próxima')
            ->assertJsonPath('data.1.product_name', 'La lejana');
    }

    /**
     * Regla heredada del dominio: una reserva sin pagar NO es una reserva próxima. La API no la
     * reimplementa — este test existe para que se note si el contrato deja de aplicarla.
     */
    public function test_an_unpaid_order_is_not_an_upcoming_reservation(): void
    {
        $user = $this->verifiedUser();
        $this->reservationFor($user, 'Sin pagar', now()->addDays(3)->toDateString(), Order::STATUS_PENDING);

        $this->actingAs($user)->getJson(self::ROOT.'/me/reservations')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    /** Lo pasado no es «próximo». */
    public function test_a_past_reservation_is_not_listed(): void
    {
        $user = $this->verifiedUser();
        $this->reservationFor($user, 'La de ayer', now()->subDay()->toDateString());

        $this->actingAs($user)->getJson(self::ROOT.'/me/reservations')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    /** Titularidad: el `userId` sale del guard, nunca de la petición. */
    public function test_it_never_shows_reservations_of_another_user(): void
    {
        $ada = $this->verifiedUser();
        $grace = $this->verifiedUser();
        $this->reservationFor($grace, 'La de Grace', now()->addDays(3)->toDateString());

        $response = $this->actingAs($ada)->getJson(self::ROOT.'/me/reservations');

        $response->assertOk()->assertJsonPath('meta.total', 0);
        $this->assertStringNotContainsString('La de Grace', (string) $response->getContent());
    }

    /** Lista vacía sigue siendo una lista: `data` array, `meta.total` a cero. */
    public function test_an_empty_list_keeps_the_shape(): void
    {
        $this->actingAs($this->verifiedUser())->getJson(self::ROOT.'/me/reservations')
            ->assertOk()
            ->assertValidResponse(200)
            ->assertExactJson(['data' => [], 'meta' => ['total' => 0]]);
    }

    public function test_it_rejects_an_anonymous_request(): void
    {
        $this->getJson(self::ROOT.'/me/reservations')
            ->assertUnauthorized()
            ->assertValidResponse(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    /** `RGPD-04`: lleva a qué hora estará una persona concreta en el recinto. */
    public function test_the_response_is_not_stored(): void
    {
        $response = $this->actingAs($this->verifiedUser())->getJson(self::ROOT.'/me/reservations');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
