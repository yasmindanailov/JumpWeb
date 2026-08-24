<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\ReservationScope;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **El REPARTO de `CustomerReservations::pageFor()`, que es lo que sostiene las dos pantallas**
 * (`docs/specs/mis-reservas-por-reserva.md` §3.4 y §6.1).
 *
 * ⚠️⚠️ **Por qué este fichero existe y por qué su primer caso es el importante.** «Mis reservas» y
 * «Historial» son dos pantallas alimentadas por dos ámbitos. Con una lista mezclada, un predicado mal
 * calculado daba un **mal orden** — se ve. Con dos listas, da una reserva que **no sale en ninguna de
 * las dos**, y eso no lo nota nadie: una lista a la que le falta una fila se lee perfectamente. Por
 * eso el ámbito es un enum sobre UN predicado y no dos métodos, y por eso aquí se asevera la
 * propiedad —la unión es el total y no hay solapamiento— y no una lista de casos.
 *
 * ⚠️ **El reloj va CONGELADO** (`docs/TESTING.md`, `DECISIONES #64`): el reparto depende de `now()` y
 * uno de los casos frontera es «hoy, ya terminada». Sin congelar, ese caso sería imposible de escribir
 * cerca de medianoche y el fichero entero amanecería rojo sin que nadie lo hubiera tocado. Ya mordió
 * dos veces en este repo.
 */
class CustomerReservationsPageTest extends TestCase
{
    use RefreshDatabase;

    /** Mediodía de un lunes cualquiera: deja sitio a un «hoy ya terminado» y a un «hoy aún por venir». */
    private const NOW = '2026-06-15 12:00:00';

    private Zone $zone;

    private User $titular;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);

        $this->titular = User::factory()->create(['email_verified_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── LA propiedad ──────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **El caso más importante de esta spec.** No comprueba que cada lista traiga lo suyo:
     * comprueba que **entre las dos no se pierde ni se duplica nada**, que es el fallo que partir la
     * pantalla en dos hace posible y silencioso.
     *
     * ▶ Verificado por mutación: al cambiar el predicado de un lado sin tocar el otro, este caso se
     * pone rojo aunque los dos sigan devolviendo listas de aspecto razonable.
     */
    public function test_the_two_scopes_are_a_partition_of_every_reservation(): void
    {
        $this->seedEveryEdgeCase();

        $upcoming = $this->page(ReservationScope::UPCOMING, 100);
        $past = $this->page(ReservationScope::PAST, 100);

        $total = OrderItem::query()
            ->whereNull('parent_item_id')
            ->whereHas('order', fn ($q) => $q->where('user_id', $this->titular->id))
            ->count();

        $this->assertSame(
            $total,
            $upcoming->total() + $past->total(),
            "Hay {$total} reservas y los dos ámbitos suman ".($upcoming->total() + $past->total()).".\n".
            "⚠️ Una reserva que no cae en ninguno de los dos DESAPARECE de la aplicación entera, y no\n".
            "falla nada: la pantalla se pinta perfecta sin ella. La causa típica es una rama del\n".
            'predicado que compara una columna NULLable sin su `whereNotNull` (ver §3.4 de la spec).'
        );

        $solapadas = array_intersect($this->idsOf($upcoming), $this->idsOf($past));

        $this->assertSame(
            [], array_values($solapadas),
            'Hay reservas en los DOS ámbitos: el predicado ha dejado de ser una partición.'
        );
    }

    /**
     * ⚠️ **La guarda de la guarda.** Si el sembrado dejara de crear casos, la aserción de arriba
     * pasaría sola y para siempre — es lo que le pasó al contador de `PurchaseRetirementTest`
     * (`DECISIONES #63`). Se exige que haya reservas **en los dos lados**.
     */
    public function test_the_fixture_actually_covers_both_sides(): void
    {
        $this->seedEveryEdgeCase();

        $this->assertGreaterThan(0, $this->page(ReservationScope::UPCOMING, 100)->total(), 'el fixture no siembra ninguna viva');
        $this->assertGreaterThan(0, $this->page(ReservationScope::PAST, 100)->total(), 'el fixture no siembra ninguna terminada');
    }

    // ── Qué cae en cada lado ──────────────────────────────────────────────────────────────────

    public function test_upcoming_holds_what_the_client_still_has_ahead(): void
    {
        $proxima = $this->reservation('La próxima', now()->addDays(3)->toDateString());
        $lejana = $this->reservation('La lejana', now()->addDays(30)->toDateString());
        $hoyPorVenir = $this->reservation('Hoy por la tarde', now()->toDateString(), '18:00:00', '20:00:00');

        // ⚠️ La de HOY por la tarde es la más próxima de las tres: la lista se ordena por cuándo
        // ocurre la reserva, no por cuándo se compró.
        $this->assertSame(
            [$hoyPorVenir->id, $proxima->id, $lejana->id],
            $this->idsOf($this->page(ReservationScope::UPCOMING, 100)),
            'las vivas no salen de la más próxima a la más lejana'
        );
    }

    /**
     * ⚠️ **Sin franja va ARRIBA DEL TODO** (decisión del owner, §3.5): no tiene fecha por la que
     * ordenar, no está terminada —`isFinishedInPractice()` da `false` sin franja— y normalmente
     * espera algo del cliente.
     */
    public function test_a_reservation_with_no_slot_leads_the_upcoming_list(): void
    {
        $this->reservation('Con fecha', now()->addDays(2)->toDateString());
        $sinFranja = $this->reservation('Sin fecha asignada', null);

        $this->assertSame(
            $sinFranja->id,
            $this->idsOf($this->page(ReservationScope::UPCOMING, 100))[0],
            'la reserva sin franja no encabeza la lista'
        );
    }

    /**
     * ⚠️⚠️ **El camino del DINERO que no se puede perder.** `openapi/v1.yaml` dice que `/me/orders`
     * incluye los `pending` a propósito: es la única forma de que alguien recupere un pedido a medio
     * pagar mientras su retención de aforo sigue viva. Si un `pending` cayera en el historial, ese
     * camino desaparecería de la pantalla principal y el cliente perdería la plaza sin saberlo.
     */
    public function test_a_live_pending_order_stays_in_upcoming_so_its_payment_can_be_retried(): void
    {
        $item = $this->reservation('A medio pagar', now()->addDays(5)->toDateString(), status: Order::STATUS_PENDING);
        $item->order->update(['expires_at' => now()->addMinutes(20)]);

        $this->assertContains($item->id, $this->idsOf($this->page(ReservationScope::UPCOMING, 100)));
        $this->assertNotContains($item->id, $this->idsOf($this->page(ReservationScope::PAST, 100)));
    }

    /**
     * ⚠️ **Caducado DE HECHO, no de columna.** `orders:expire` corre por cron y puede ir por detrás
     * —en staging llevaba 24 h muerto (`DECISIONES #115`)—, así que un pedido cuyo `expires_at` ya
     * pasó es historial aunque su `status` siga diciendo `pending`.
     */
    public function test_an_order_whose_hold_already_lapsed_is_past_even_if_the_cron_has_not_run(): void
    {
        $item = $this->reservation('Se le pasó el arroz', now()->addDays(5)->toDateString(), status: Order::STATUS_PENDING);
        $item->order->update(['expires_at' => now()->subMinute()]);

        $this->assertContains($item->id, $this->idsOf($this->page(ReservationScope::PAST, 100)));
    }

    public function test_past_holds_cancelled_enjoyed_and_dead_orders_newest_first(): void
    {
        $anteayer = $this->reservation('Anteayer', now()->subDays(2)->toDateString());
        $ayer = $this->reservation('Ayer', now()->subDay()->toDateString());
        $cancelada = $this->reservation('Cancelada', now()->addDays(4)->toDateString());
        $cancelada->update(['cancelled_at' => now()]);

        $ids = $this->idsOf($this->page(ReservationScope::PAST, 100));

        $this->assertSame([$cancelada->id, $ayer->id, $anteayer->id], $ids, 'el historial no sale de lo más reciente a lo más antiguo');
    }

    /**
     * ⚠️⚠️ **El corte de «disfrutada» es EXACTO, no por día**, y espeja
     * `OrderItem::isFinishedInPractice()`. Si fuera por día, esta partición y `upcomingFor()`
     * —que alimenta el bloque de cuenta— **discreparían**, y el panel diría «1 reserva próxima»
     * de algo que la pantalla enseña en el historial.
     */
    public function test_todays_reservation_moves_to_past_the_moment_it_ends_not_at_midnight(): void
    {
        $terminada = $this->reservation('Esta mañana', now()->toDateString(), '09:00:00', '11:00:00');
        $enCurso = $this->reservation('Ahora mismo', now()->toDateString(), '11:30:00', '13:00:00');

        $this->assertContains($terminada->id, $this->idsOf($this->page(ReservationScope::PAST, 100)), 'una reserva de hoy que ya acabó sigue en «Mis reservas»');
        $this->assertContains($enCurso->id, $this->idsOf($this->page(ReservationScope::UPCOMING, 100)), 'una reserva en curso se ha ido al historial');
    }

    /**
     * ⚠️⚠️ **Una reserva «sin límite» SÍ termina, y conviene tenerlo escrito.** El cliente lee
     * «10:00 – sin límite» —lo compone `OrderItem::displayTimeWindow()` cuando el producto no declara
     * `duration_min`— y eso invita a pensar que nunca caduca. Pero **`slots.end_time` es NOT NULL en
     * el esquema** (`create_slots_table`): la franja siempre tiene fin, y `isFinishedInPractice()` lo
     * usa. Así que termina a la hora de cierre de su franja, igual que cualquier otra.
     *
     * ▶ Es también la razón de que la rama `end_time === null` de `isFinishedInPractice()` sea
     * defensiva y hoy **inalcanzable**. El `whereNotNull('slots.end_time')` del predicado se conserva
     * por paridad exacta con ella: si la columna se hiciera NULLable, las dos seguirían diciendo lo
     * mismo — y sin él, `whereNot()` perdería esas filas de los dos ámbitos (§3.4).
     */
    public function test_an_unlimited_window_still_finishes_at_its_slots_closing_time(): void
    {
        $sinLimite = $this->product('Pase sin límite');
        $sinLimite->update(['duration_min' => null]);

        $terminada = $this->reservationOn($sinLimite, now()->toDateString(), '08:00:00', '10:00:00');
        $enCurso = $this->reservationOn($sinLimite, now()->toDateString(), '11:00:00', '14:00:00');

        $this->assertSame('08:00 – '.__('tickets.time_no_limit'), $terminada->fresh()->displayTimeWindow());
        $this->assertContains($terminada->id, $this->idsOf($this->page(ReservationScope::PAST, 100)), 'un pase «sin límite» de esta mañana sigue contando como próximo');
        $this->assertContains($enCurso->id, $this->idsOf($this->page(ReservationScope::UPCOMING, 100)));
    }

    // ── Alcance ───────────────────────────────────────────────────────────────────────────────

    /** Los complementos cuelgan de su línea: no son reservas y no llevan tarjeta propia. */
    public function test_addons_are_not_reservations(): void
    {
        $principal = $this->reservation('Entrada', now()->addDays(3)->toDateString());
        $addon = $principal->order->items()->create([
            'ticket_type_id' => $this->product('Calcetines')->id,
            'parent_item_id' => $principal->id,
            'quantity' => 1, 'unit_price' => 300, 'seats' => 0,
        ]);

        $ids = array_merge($this->idsOf($this->page(ReservationScope::UPCOMING, 100)), $this->idsOf($this->page(ReservationScope::PAST, 100)));

        $this->assertNotContains($addon->id, $ids);
    }

    /** El titular sale del guard, nunca de la petición: aquí se fija que la consulta lo respeta. */
    public function test_it_never_returns_someone_elses_reservations(): void
    {
        $otro = User::factory()->create(['email_verified_at' => now()]);
        $suya = $this->reservation('De otro', now()->addDays(3)->toDateString(), user: $otro);

        $ids = array_merge($this->idsOf($this->page(ReservationScope::UPCOMING, 100)), $this->idsOf($this->page(ReservationScope::PAST, 100)));

        $this->assertNotContains($suya->id, $ids);
    }

    // ── Paginación ────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **El orden tiene que ser TOTAL o paginar pierde filas.** Con dos reservas del mismo día y
     * la misma hora y sin desempate, la BD puede devolverlas en distinto orden en dos peticiones: una
     * saldría dos veces y otra ninguna. Por eso el orden lleva `order_items.id` al final.
     */
    public function test_pagination_never_repeats_nor_drops_a_row(): void
    {
        for ($i = 0; $i < 7; $i++) {
            // MISMA fecha y MISMA hora a propósito: es el caso en que el desempate decide.
            $this->reservation("Empate {$i}", now()->addDays(3)->toDateString());
        }

        $primera = $this->idsOf($this->page(ReservationScope::UPCOMING, 5, 1));
        $segunda = $this->idsOf($this->page(ReservationScope::UPCOMING, 5, 2));

        $this->assertCount(5, $primera);
        $this->assertCount(2, $segunda);
        $this->assertSame([], array_values(array_intersect($primera, $segunda)), 'una reserva sale en las dos páginas');
        $this->assertCount(7, array_unique(array_merge($primera, $segunda)), 'paginar ha perdido una reserva');
    }

    /**
     * ⚠️⚠️ **Y el desempate se comprueba sobre el SQL, no sobre el resultado, porque el resultado NO
     * lo distingue.** Medido: al quitar el `orderBy('order_items.id')` el caso de arriba **sigue en
     * verde**, porque SQLite devuelve las filas empatadas en orden de `rowid` por su cuenta y da la
     * casualidad de que coincide. Un test que no puede fallar es peor que no tenerlo (`#113`), así que
     * lo que se asevera es que la cláusula **existe**: la garantía no puede depender de a qué motor le
     * apetezca ser estable, y menos cuando producción es MySQL y la suite SQLite.
     */
    public function test_the_ordering_is_total_so_pagination_cannot_depend_on_the_engine(): void
    {
        $this->reservation('Cualquiera', now()->addDays(3)->toDateString());

        DB::enableQueryLog();
        $this->page(ReservationScope::UPCOMING, 5);
        $consultas = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $select = $consultas->first(fn (string $q): bool => str_contains($q, 'order by'));

        $this->assertNotNull($select, 'la consulta paginada ya no ordena por nada');
        $this->assertStringContainsString(
            'order_items"."id',
            (string) $select,
            "El orden ha dejado de llevar desempate por `id`.\n".
            "⚠️ Sin un orden TOTAL, dos reservas del mismo día y hora pueden volver en distinto orden\n".
            "en dos peticiones: una saldría dos veces y otra ninguna al pasar de página. No lo ve el\n".
            'caso de arriba porque SQLite es estable por casualidad; MySQL no lo promete.'
        );
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /**
     * @return LengthAwarePaginator<int, OrderItem>
     */
    private function page(ReservationScope $scope, int $perPage, int $page = 1)
    {
        return app(CustomerReservations::class)->pageFor($this->titular->id, $scope, $perPage, $page);
    }

    /**
     * @param  LengthAwarePaginator<int, OrderItem>  $page
     * @return list<int>
     */
    private function idsOf($page): array
    {
        return collect($page->items())->map(fn (OrderItem $i): int => (int) $i->id)->values()->all();
    }

    /** Los siete casos frontera de la spec, sembrados de una vez. */
    private function seedEveryEdgeCase(): void
    {
        $this->reservation('Viva próxima', now()->addDays(3)->toDateString());
        $this->reservation('Viva lejana', now()->addDays(30)->toDateString());
        $this->reservation('Hoy, ya terminada', now()->toDateString(), '09:00:00', '11:00:00');
        $this->reservation('Sin franja', null);
        $this->reservation('Disfrutada', now()->subDays(9)->toDateString());

        $cancelada = $this->reservation('Cancelada', now()->addDays(6)->toDateString());
        $cancelada->update(['cancelled_at' => now()]);

        $caducado = $this->reservation('De pedido caducado', now()->addDays(8)->toDateString(), status: Order::STATUS_PENDING);
        $caducado->order->update(['expires_at' => now()->subHour()]);

        $vivoPendiente = $this->reservation('A medio pagar', now()->addDays(9)->toDateString(), status: Order::STATUS_PENDING);
        $vivoPendiente->order->update(['expires_at' => now()->addMinutes(20)]);

        // ⚠️ Y una SIN franja de un pedido cancelado: la ausencia de fecha no la salva del historial.
        $sinFranjaMuerta = $this->reservation('Sin franja y cancelada', null, status: Order::STATUS_CANCELLED);
        $this->assertNotNull($sinFranjaMuerta->id);
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

    private function reservation(
        string $productName,
        ?string $date,
        string $start = '10:00:00',
        string $end = '11:00:00',
        string $status = Order::STATUS_PAID,
        ?User $user = null,
    ): OrderItem {
        return $this->reservationOn($this->product($productName), $date, $start, $end, $status, $user);
    }

    /** La misma reserva, sobre un producto ya creado: hace falta para el pase «sin límite». */
    private function reservationOn(
        TicketType $product,
        ?string $date,
        string $start = '10:00:00',
        string $end = '11:00:00',
        string $status = Order::STATUS_PAID,
        ?User $user = null,
    ): OrderItem {
        $order = Order::create([
            'user_id' => ($user ?? $this->titular)->id,
            'code' => 'R-'.Str::upper(Str::random(8)),
            'status' => $status, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000,
            'currency' => 'EUR', 'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);

        // ⚠️ `firstOrCreate` y no `create`: las franjas son únicas por (zona, día, hora de inicio), y
        // el caso del desempate siembra SIETE reservas en la misma. Que compartan franja además es lo
        // realista — es lo que pasa cuando una familia reserva a la misma hora.
        $slot = $date === null ? null : Slot::firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => $date, 'start_time' => $start],
            ['end_time' => $end, 'capacity' => 50, 'online_capacity' => 50],
        );

        return $order->items()->create([
            'ticket_type_id' => $product->id,
            'slot_id' => $slot?->id,
            'quantity' => 1, 'unit_price' => 1000, 'seats' => 1,
        ]);
    }
}
