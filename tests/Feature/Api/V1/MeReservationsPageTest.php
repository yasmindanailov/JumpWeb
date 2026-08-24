<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Api\ApiTestCase;

/**
 * **`GET /api/v1/me/reservations/{scope}`** — el historial POR RESERVA
 * (`docs/specs/mis-reservas-por-reserva.md`).
 *
 * ⚠️ **Lo que NO se prueba aquí**: el reparto entre los dos ámbitos. Eso es una propiedad del
 * DOMINIO y vive en `Tests\Feature\Sales\CustomerReservationsPageTest`, con su verificación por
 * mutación. Aquí se comprueba la ENTREGA: que el contrato encaja, que el titular es el del guard,
 * que la PII no se cuela y que el camino del dinero llega entero. Mezclar las dos capas dejaría la
 * propiedad importante probada a través de HTTP, que es donde peor se lee por qué falla.
 *
 * ⚠️ **Reloj congelado** (`DECISIONES #64`): las fechas de las reservas deciden en qué ámbito caen.
 */
class MeReservationsPageTest extends ApiTestCase
{
    private const NOW = '2026-06-15 12:00:00';

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── El contrato ───────────────────────────────────────────────────────────────────────────

    public function test_a_card_is_a_reservation_plus_the_minimum_of_its_order(): void
    {
        $user = $this->verifiedUser();
        $item = $this->reservation($user, 'Salto 1 hora', now()->addDays(3)->toDateString());

        $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming')
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('data.0.reservation.id', $item->id)
            ->assertJsonPath('data.0.reservation.product_name', 'Salto 1 hora')
            ->assertJsonPath('data.0.order.code', $item->order->code)
            ->assertJsonPath('data.0.order.status', 'paid')
            ->assertJsonPath('data.0.order.can_be_retried', false);
    }

    /**
     * ⚠️⚠️ **El LEDGER no viaja, y es una decisión, no un olvido** (§4.2 de la spec). Es del PEDIDO:
     * meterlo en la tarjeta lo repetiría tantas veces como reservas tenga —el caso real
     * `DEMO-LEDGER` tiene tres—. Se pide con `GET /orders/{code}` al desplegar «Ver pedido».
     *
     * ▶ Este caso existe para que, si alguien lo añade «para ahorrarse una petición», tenga que
     * borrarlo a conciencia y leer por qué.
     */
    public function test_the_order_ledger_does_not_travel_with_every_card(): void
    {
        $user = $this->verifiedUser();
        $this->reservation($user, 'Salto', now()->addDays(3)->toDateString());

        $card = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming')->json('data.0.order');

        foreach (['ledger', 'refund', 'items'] as $campo) {
            $this->assertArrayNotHasKey($campo, $card, "«{$campo}» se ha colado en la tarjeta: el ledger es del pedido y se pide aparte");
        }
    }

    public function test_an_unknown_scope_is_a_404_before_touching_the_database(): void
    {
        $this->actingAs($this->verifiedUser())
            ->getJson(self::ROOT.'/me/reservations/loquesea')
            ->assertNotFound();
    }

    public function test_per_page_is_capped(): void
    {
        $this->actingAs($this->verifiedUser())
            ->getJson(self::ROOT.'/me/reservations/upcoming?per_page=100000')
            ->assertStatus(422);
    }

    // ── Seguridad y RGPD ──────────────────────────────────────────────────────────────────────

    public function test_it_needs_a_session(): void
    {
        $this->getJson(self::ROOT.'/me/reservations/upcoming')->assertUnauthorized();
        $this->getJson(self::ROOT.'/me/reservations/past')->assertUnauthorized();
    }

    /** El titular sale del guard, nunca de la petición: no hay superficie de IDOR. */
    public function test_it_never_leaks_someone_elses_reservations(): void
    {
        $mio = $this->verifiedUser();
        $ajeno = $this->verifiedUser();
        $this->reservation($mio, 'La mía', now()->addDays(3)->toDateString());
        $this->reservation($ajeno, 'La suya', now()->addDays(4)->toDateString());

        $this->actingAs($mio)->getJson(self::ROOT.'/me/reservations/upcoming')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reservation.product_name', 'La mía');
    }

    /**
     * ⚠️ **`RGPD-04`: `no-store` en toda respuesta autenticada.** Lo pone el middleware de `/api/v1`
     * por defecto, y por eso mismo hay que aseverarlo: «viene por defecto» es justo la clase de
     * afirmación que deja de ser cierta sin que nadie lo note — pasó con el `no-store` de la web,
     * que lo ponía un accidente de Livewire (`DECISIONES #123`).
     */
    public function test_the_response_is_never_cached(): void
    {
        $user = $this->verifiedUser();
        $this->reservation($user, 'Salto', now()->addDays(3)->toDateString());

        $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    /**
     * ⚠️⚠️ **Las respuestas del pack NO viajan**, igual que en `/me/orders`: son datos de un MENOR
     * —nombre, edad y alergias, art. 9— y una lista que se pinta sola en cada visita las arrastraría
     * en cada página. Se piden con `GET /orders/{code}/event-data`, que es un acto explícito.
     */
    public function test_the_event_answers_of_a_pack_never_travel_in_the_list(): void
    {
        $user = $this->verifiedUser();
        $item = $this->reservation($user, 'Cumpleaños', now()->addDays(3)->toDateString());
        $item->update(['event_data' => ['celebrant_name' => 'Lucía Menor', 'allergies' => 'frutos secos']]);

        $body = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming')->getContent();

        $this->assertStringNotContainsString('Lucía Menor', (string) $body);
        $this->assertStringNotContainsString('frutos secos', (string) $body);
        $this->assertStringNotContainsString('event_data', (string) $body);
    }

    // ── El camino del dinero ──────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **Un pedido a medio pagar sigue en «Mis reservas», con su reintento.** `openapi/v1.yaml`
     * dice que `/me/orders` incluye los `pending` a propósito: es la única forma de que alguien
     * recupere un pedido cuya retención de aforo sigue viva. Si esta pantalla lo mandara al
     * historial, el cliente perdería la plaza sin enterarse.
     */
    public function test_a_half_paid_order_keeps_its_retry_within_reach(): void
    {
        $user = $this->verifiedUser();
        $item = $this->reservation($user, 'A medio pagar', now()->addDays(5)->toDateString(), Order::STATUS_PENDING);
        $item->order->update(['expires_at' => now()->addMinutes(20)]);
        $item->order->payments()->create([
            'provider' => 'redsys', 'status' => 'failed', 'amount' => 1000, 'currency' => 'EUR',
        ]);

        $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming')
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order.status', 'pending')
            ->assertJsonPath('data.0.order.can_be_retried', true);
    }

    // ── Orden y paginación ────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **El orden A TRAVÉS DE LAS PÁGINAS es lo único que distingue esta solución de aplanar en
     * el cliente** (§3.1 de la spec). Aplanando la página de `/me/orders` el orden solo sería cierto
     * dentro de una página; aquí tiene que serlo entre ellas.
     */
    public function test_the_order_holds_across_pages(): void
    {
        $user = $this->verifiedUser();

        // Sembradas DESORDENADAS a propósito: si la consulta ordenara por creación, el resultado
        // saldría en este mismo orden y el caso pasaría sin medir nada.
        foreach ([9, 2, 7, 1, 5, 3, 11] as $dias) {
            $this->reservation($user, "En {$dias} días", now()->addDays($dias)->toDateString(), start: sprintf('%02d:00:00', 8 + $dias % 6));
        }

        $primera = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming?per_page=3&page=1');
        $segunda = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming?per_page=3&page=2');
        $tercera = $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming?per_page=3&page=3');

        $fechas = array_merge(
            $primera->json('data.*.reservation.date'),
            $segunda->json('data.*.reservation.date'),
            $tercera->json('data.*.reservation.date'),
        );

        $ordenadas = $fechas;
        sort($ordenadas);

        $this->assertSame($ordenadas, $fechas, 'las reservas no salen en orden a través de las páginas');
        $this->assertCount(7, array_unique($fechas), 'paginar ha perdido o repetido una reserva');
        $this->assertSame(3, $primera->json('meta.last_page'));
    }

    /** Y el historial va al revés: lo último que hiciste, arriba. */
    public function test_the_history_leads_with_the_most_recent(): void
    {
        $user = $this->verifiedUser();
        $this->reservation($user, 'Hace mucho', now()->subDays(40)->toDateString());
        $this->reservation($user, 'Hace poco', now()->subDays(2)->toDateString());

        $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/past')
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.reservation.product_name', 'Hace poco')
            ->assertJsonPath('data.1.reservation.product_name', 'Hace mucho');
    }

    // ── Coste ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **El N+1 no puede entrar en silencio.** Cada tarjeta necesita su producto, su franja, sus
     * complementos y el pedido con sus pagos: sin eager-load son cinco consultas por fila, y con
     * cinco filas eso son veinticinco que nadie ve hasta que la pantalla va lenta en producción.
     * El techo es holgado a propósito —no se persigue un número, se persigue que no CREZCA con el
     * número de reservas—, y por eso el caso mide con 1 y con 5.
     */
    public function test_the_cost_does_not_grow_with_the_number_of_cards(): void
    {
        $user = $this->verifiedUser();
        $this->reservation($user, 'Una', now()->addDays(2)->toDateString());

        // ⚠️ Una petición de calentamiento ANTES de medir: la primera de una sesión trae consultas
        // que no son del endpoint —crear la fila de sesión, resolver ajustes— y sin esto el caso
        // compara dos números que difieren por ruido y no por el eager-load. Medido: la primera
        // petición costaba una consulta MÁS que las siguientes, así que el número «con una tarjeta»
        // salía mayor que el «con cinco» y el caso fallaba al revés de lo que vigila.
        $this->countQueries($user);
        $conUna = $this->countQueries($user);

        for ($i = 0; $i < 4; $i++) {
            $this->reservation($user, "Otra {$i}", now()->addDays(3 + $i)->toDateString());
        }

        $conCinco = $this->countQueries($user);

        $this->assertSame(
            $conUna, $conCinco,
            "Una tarjeta cuesta {$conUna} consultas y cinco cuestan {$conCinco}: el coste CRECE con el ".
            "número de reservas, así que falta un eager-load en `CustomerReservationsReader::pageFor()`.\n".
            'Con cinco pedidos distintos y sin eager-load serían ~5 consultas más, no una.'
        );
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    private function countQueries(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson(self::ROOT.'/me/reservations/upcoming?per_page=50')->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function reservation(
        User $user,
        string $productName,
        string $date,
        string $status = Order::STATUS_PAID,
        string $start = '10:00:00',
    ): OrderItem {
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(8)),
            'status' => $status, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000,
            'currency' => 'EUR', 'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);

        $slot = Slot::firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => $date, 'start_time' => $start],
            ['end_time' => '23:00:00', 'capacity' => 50, 'online_capacity' => 50],
        );

        return $order->items()->create([
            'ticket_type_id' => TicketType::create([
                'name' => ['es' => $productName], 'type' => TicketType::TYPE_ENTRY,
                'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
                'is_sellable' => true, 'is_active' => true,
                'position' => (int) TicketType::max('position') + 1,
            ])->id,
            'slot_id' => $slot->id,
            'quantity' => 1, 'unit_price' => 1000, 'seats' => 1,
        ]);
    }
}
