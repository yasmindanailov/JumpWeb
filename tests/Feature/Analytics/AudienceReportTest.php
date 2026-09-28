<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\AudienceReport;
use App\Filament\Widgets\Analytics\AudienceWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **QUIÉN VIENE** (TP·2 de `specs/analitica-para-decidir.md` §4.14, `#792`): la población (una persona una vez, con la regla de
 * `paidScheduledPrincipal`), la edad del PRIMER día de visita, los hijos MENORES ese día, con quién viene, las fiestas, y que
 * ninguna celda de 1 a 4 salga nunca (`RGPD-07`).
 */
class AudienceReportTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Europe/Madrid';

    private Zone $zone;

    private TicketType $entry;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => self::TZ, 'group' => 'general']);
        Setting::flushMemo();
        $this->travelTo(Carbon::parse('2026-06-30 12:00:00', self::TZ));
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'is_active' => true]);
        $this->entry = $this->product('Entrada', TicketType::TYPE_ENTRY);
    }

    // ─── Las celdas pequeñas ─────────────────────────────────────────────────────────────────────

    public function test_a_small_ordered_cell_joins_its_neighbour_and_a_small_tail_folds_back_through_zeros(): void
    {
        $fold = static fn (array $counts): array => array_map(
            static fn (array $r): string => $r['from'].'-'.($r['to'] ?? '∞').':'.$r['count'],
            AudienceReport::foldRanges(array_map(static fn (int $i, int $n): array => ['from' => $i, 'to' => $i, 'count' => $n], array_keys($counts), $counts)),
        );

        $this->assertSame(['0-1:6', '2-2:0', '3-3:7'], $fold([1, 5, 0, 7]));
        $this->assertSame(['0-2:13'], $fold([10, 0, 3]), 'el resto pequeño se funde HACIA ATRÁS, también a través de un cero');
        $this->assertSame(['0-0:0', '1-1:5'], $fold([0, 5]), 'un cero no delata a nadie y se queda');
    }

    public function test_no_ordered_or_categorized_cell_is_ever_between_one_and_four(): void
    {
        $casos = [[1, 1, 1, 1, 1], [4, 0, 0, 1], [2, 9, 3, 0, 1, 6], [5, 4, 5, 4], [0, 0, 7, 1], [3, 3]];
        foreach ($casos as $counts) {
            $rows = AudienceReport::foldRanges(array_map(static fn (int $i, int $n): array => ['from' => $i, 'to' => $i, 'count' => $n], array_keys($counts), $counts));
            foreach ($rows as $r) {
                $this->assertFalse($r['count'] > 0 && $r['count'] < AudienceReport::MIN_CELL, 'celda pequeña en '.json_encode($counts));
            }
            $this->assertSame(array_sum($counts), array_sum(array_column($rows, 'count')), 'fundir no pierde a nadie');
        }

        $orden = ['father', 'mother', 'other', 'mixed'];
        $cat = AudienceReport::categorized(20, ['mother' => 12, 'father' => 3, 'other' => 1, 'mixed' => 4], $orden);
        $this->assertSame([['key' => 'mother', 'count' => 12], ['key' => 'other', 'count' => 8]], $cat['rows'], 'las pequeñas y el parentesco «otro» van a UNA fila «Otros»');

        // Una pequeña SOLA va a «Otros», y como «Otros» se queda en 3, se le suma la más pequeña de las demás.
        $sola = AudienceReport::categorized(22, ['mother' => 12, 'father' => 7, 'mixed' => 3], $orden);
        $this->assertSame([['key' => 'mother', 'count' => 12], ['key' => 'other', 'count' => 10]], $sola['rows']);

        // «Otro» de cinco o más Y dos pequeñas que ya suman cinco: sigue siendo UNA fila, no «Otros» dos veces.
        $dos = AudienceReport::categorized(25, ['mother' => 12, 'other' => 6, 'father' => 3, 'mixed' => 4], $orden);
        $this->assertSame([['key' => 'mother', 'count' => 12], ['key' => 'other', 'count' => 13]], $dos['rows']);

        foreach ([[1, 1, 1, 1], [9, 4, 0, 1], [2, 2, 2, 5], [5, 5, 4, 3], [0, 0, 6, 1]] as [$f, $m, $o, $x]) {
            $rows = AudienceReport::categorized(99, ['father' => $f, 'mother' => $m, 'other' => $o, 'mixed' => $x], $orden)['rows'];
            foreach ($rows as $r) {
                $this->assertFalse($r['count'] > 0 && $r['count'] < AudienceReport::MIN_CELL, "celda pequeña en [{$f}, {$m}, {$o}, {$x}]");
            }
            $this->assertSame(array_column($rows, 'key'), array_values(array_unique(array_column($rows, 'key'))), 'cada fila, una vez');
        }

        $pocas = AudienceReport::categorized(9, ['mother' => 3, 'father' => 1], ['father', 'mother']);
        $this->assertSame([], $pocas['rows'], 'con menos de cinco con dato no se reparte');
        $this->assertSame(4, $pocas['with_data']);
    }

    // ─── Quién viene ─────────────────────────────────────────────────────────────────────────────

    /**
     * Cinco personas nacidas el 15-06-2001 vienen el 10 y el 20 de junio: el 10 tienen 24 y el 20, 25. Cuentan UNA vez y
     * con la edad de su PRIMERA visita (18–24). Una sin fecha, una cancelada, una sin pagar y una de julio, fuera o sin dato.
     */
    public function test_each_person_counts_once_with_the_age_of_their_first_visit_of_the_period(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $u = $this->holder("veinte{$i}", '2001-06-15');
            $this->visit($u, '2026-06-10');
            $this->visit($u, '2026-06-20');
        }
        $this->visit($this->holder('sinfecha', null), '2026-06-12');
        $this->visit($this->holder('cancelada', '1990-01-01'), '2026-06-12', cancelled: true);
        $this->visit($this->holder('pendiente', '1990-01-01'), '2026-06-12', status: Order::STATUS_PENDING);
        $this->visit($this->holder('julio', '1990-01-01'), '2026-07-02');

        $r = (new AudienceReport)->compute($this->june());

        $this->assertSame(6, $r['holders']['of']);
        $this->assertSame(5, $r['holders']['with_data']);
        $this->assertSame([['from' => 18, 'to' => 24, 'count' => 5], ['from' => 25, 'to' => 34, 'count' => 0], ['from' => 35, 'to' => 44, 'count' => 0], ['from' => 45, 'to' => 54, 'count' => 0], ['from' => 55, 'to' => null, 'count' => 0]], $r['holders']['rows']);

        // La paridad con el alcance de Eloquent (`paidScheduledPrincipal` + `slotDateBetween`): las mismas personas.
        $scope = OrderItem::query()->paidScheduledPrincipal()->slotDateBetween('2026-06-01', '2026-06-30')->with('order')->get()->pluck('order.user_id')->unique()->count();
        $this->assertSame($scope, $r['holders']['of']);
    }

    /**
     * Sus hijos MENORES el día de la primera visita: uno que ya cumplió 18, uno retirado y uno que aún no había nacido, fuera.
     * Cinco madres y uno sin parentesco.
     */
    public function test_the_kids_are_the_minors_of_that_day_and_who_declares_them_is_per_person(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $u = $this->holder("madre{$i}", '1990-01-01');
            $this->visit($u, '2026-06-10');
            $this->kid($u, '2018-06-15', 'mother');                     // 7 años el 10-06
        }
        $sinParentesco = $this->holder('sinparentesco', '1985-01-01');
        $this->visit($sinParentesco, '2026-06-10');
        $this->kid($sinParentesco, '2010-01-01', null);                   // 16 años
        $this->kid($sinParentesco, '2000-01-01', 'mother');               // 26: ya no es un niño que viene
        $this->kid($sinParentesco, '2012-01-01', 'mother', removed: true); // retirado
        $this->kid($sinParentesco, '2026-06-20', 'mother');               // aún no había nacido el 10-06

        $r = (new AudienceReport)->compute($this->june());

        $this->assertSame(['of' => 6, 'with_data' => 6], array_intersect_key($r['kids_count'], ['of' => 0, 'with_data' => 0]));
        $this->assertSame([['from' => 1, 'to' => 1, 'count' => 6], ['from' => 2, 'to' => 2, 'count' => 0], ['from' => 3, 'to' => null, 'count' => 0]], $r['kids_count']['rows']);
        // 5 de 6–8 y 1 de 15–17: el resto de uno se funde HACIA ATRÁS a través de los ceros.
        $this->assertSame([['from' => 0, 'to' => 2, 'count' => 0], ['from' => 3, 'to' => 5, 'count' => 0], ['from' => 6, 'to' => 17, 'count' => 6]], $r['kids_ages']['rows']);
        $this->assertSame([['key' => 'mother', 'count' => 5]], $r['declared_by']['rows']);
        $this->assertSame(1, $r['declared_by']['unknown'], 'el menor sin parentesco no se adivina');
    }

    public function test_with_minors_is_an_assigned_minor_or_a_product_only_for_minors_and_otherwise_unknown(): void
    {
        $kids = $this->product('Kids', TicketType::TYPE_ENTRY, ageMax: 7);
        $adultos = $this->product('Desde 8', TicketType::TYPE_ENTRY, ageMin: 8);
        $u = $this->holder('familia', '1990-01-01');
        $asignada = $this->visit($u, '2026-06-10');
        DB::table('dependent_assignments')->insert(['dependent_id' => $this->kid($u, '2018-01-01', 'mother'), 'order_item_id' => $asignada->id, 'created_at' => now()]);
        foreach (range(1, 4) as $d) {
            $this->visit($u, '2026-06-1'.$d, product: $kids);
        }
        $this->visit($u, '2026-06-20', product: $adultos);
        $this->visit($u, '2026-06-21');

        $r = (new AudienceReport)->compute($this->june());

        $this->assertSame(['of' => 7, 'with_data' => 5], $r['company'], 'la asignada y las cuatro del producto de menores; «desde 8» no se adivina');
    }

    /** Las fiestas: la edad de quien cumple (la de su invitación, o la declarada al reservar) y la de los invitados, sin su fila. */
    public function test_the_parties_give_the_honoree_age_and_the_guest_ages_without_the_honoree_row(): void
    {
        $pack = $this->product('Cumple', TicketType::TYPE_PACK, guestFields: [
            ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => false, 'label' => ['es' => 'Edad']],
        ], eventFields: [
            ['key' => 'edad_cumple', 'type' => TicketType::FIELD_TYPE_CELEBRANT_AGE, 'required' => false, 'stage' => TicketType::EVENT_STAGE_BOOKING, 'label' => ['es' => 'Edad']],
        ]);

        foreach ([6, 7, 7, 8, 9] as $n => $edad) {
            $fiesta = $this->visit($this->holder("anfitrion{$n}", null), '2026-06-1'.$n, product: $pack, quantity: 2, guests: [['name' => 'A', 'edad' => 7], ['name' => 'B', 'edad' => 8]]);
            DB::table('party_invitations')->insert(['order_item_id' => $fiesta->id, 'token' => Str::random(12), 'theme' => 'jump', 'honoree_name' => 'X', 'honoree_age' => $edad, 'host_line' => 'Te invita', 'created_at' => now(), 'updated_at' => now()]);
        }
        // Sin invitación: la edad declarada al reservar. Y quien cumple es la PRIMERA fila: no es un invitado.
        $sello = $this->visit($this->holder('sello', null), '2026-06-20', product: $pack, quantity: 2, guests: [['name' => 'Cumple', 'edad' => 10], ['name' => 'C', 'edad' => 9]], eventData: ['edad_cumple' => 10]);
        $sello->forceFill(['honoree_row' => true])->saveQuietly();

        $r = (new AudienceReport)->compute($this->june());

        $this->assertSame(['of' => 6, 'with_data' => 6], array_intersect_key($r['honorees'], ['of' => 0, 'with_data' => 0]));
        $this->assertSame(['of' => 11, 'with_data' => 11], array_intersect_key($r['guests'], ['of' => 0, 'with_data' => 0]), '5×2 y 1, sin la de quien cumple');
        $this->assertSame([['from' => 7, 'to' => 7, 'count' => 5], ['from' => 8, 'to' => 9, 'count' => 6]], $r['guests']['rows']);
    }

    /** El presupuesto del cuadro (§6: ≤ 20 consultas por informe), con todas las ramas llenas: no crece con las personas. */
    public function test_the_report_stays_within_its_query_budget_whatever_the_number_of_people(): void
    {
        $pack = $this->product('Cumple', TicketType::TYPE_PACK, guestFields: [
            ['key' => 'edad', 'type' => TicketType::FIELD_TYPE_AGE, 'required' => false, 'label' => ['es' => 'Edad']],
        ]);
        foreach (range(1, 12) as $n) {
            $u = $this->holder("persona{$n}", '1990-01-0'.($n % 9 + 1));
            $this->kid($u, '2018-01-01', 'mother');
            $this->visit($u, '2026-06-1'.($n % 9));
            $this->visit($u, '2026-06-2'.($n % 9), product: $pack, quantity: 1, guests: [['edad' => 7]]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        (new AudienceReport)->compute($this->june());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $queries, "«Quién viene» hace {$queries} consultas: tiene que ser un número fijo, no una por persona");
    }

    // ─── La pantalla ─────────────────────────────────────────────────────────────────────────────

    public function test_the_widget_says_how_many_have_data_and_masks_a_small_count(): void
    {
        $kids = $this->product('Kids', TicketType::TYPE_ENTRY, ageMax: 7);
        $u = $this->holder('solo', '1990-01-01');
        $this->visit($u, '2026-06-10');
        foreach (range(1, 3) as $d) {
            $this->visit($u, '2026-06-2'.$d, product: $kids);
        }
        foreach (range(1, 5) as $d) {
            $this->visit($this->holder("otro{$d}", null), '2026-06-1'.$d);
        }

        $tables = (new AudienceWidget)->tablesFor($this->june());
        $titulares = $tables[0];
        $con = $tables[4];

        $this->assertSame('La edad de quien reserva', $titulares['heading'], 'el título es FIJO: el censo lo busca en el CSV');
        $this->assertSame([['Con dato', 'menos de 5 de 6', ''], ['Menos de 5 con dato: no se reparte', '—', '—']], $titulares['rows']);
        $this->assertSame('Con quién viene', $con['heading']);
        $this->assertSame([['Con menores', 'menos de 5', '—'], ['Sin dato (no se adivina)', '6', '67 %']], $con['rows'], 'tres reservas con menores se dicen «menos de 5»');
    }

    // ─── Andamios ────────────────────────────────────────────────────────────────────────────────

    private function june(): Window
    {
        return ReportPeriod::Custom->window('2026-06-01', '2026-06-30');
    }

    /**
     * @param  list<array<string, mixed>>|null  $guestFields
     * @param  list<array<string, mixed>>  $eventFields
     */
    private function product(string $name, string $type, ?int $ageMax = null, ?int $ageMin = null, ?array $guestFields = null, array $eventFields = []): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'zone_id' => $this->zone->id, 'type' => $type, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1, 'min_qty' => 1, 'max_qty' => 30,
            'guest_age_max' => $ageMax, 'guest_age_min' => $ageMin,
            'guest_fields' => $guestFields, 'event_fields' => $eventFields,
        ]);
    }

    private function holder(string $name, ?string $bornOn): User
    {
        return User::factory()->create(['email' => "{$name}@audiencia.test", 'born_on' => $bornOn]);
    }

    private function kid(User $holder, string $bornOn, ?string $relationship, bool $removed = false): int
    {
        return (int) DB::table('dependents')->insertGetId([
            'user_id' => $holder->id, 'name' => 'Niño', 'relationship' => $relationship, 'born_on' => $bornOn,
            'removed_at' => $removed ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>|null  $guests
     * @param  array<string, mixed>  $eventData
     */
    private function visit(User $holder, string $date, ?TicketType $product = null, bool $cancelled = false, string $status = Order::STATUS_PAID, int $quantity = 1, ?array $guests = null, array $eventData = []): OrderItem
    {
        $slot = Slot::query()->firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => $date, 'start_time' => '17:00:00'],
            ['end_time' => '18:00:00', 'capacity' => 100, 'online_capacity' => 100],
        );
        $order = Order::create([
            'user_id' => $holder->id, 'code' => 'A-'.Str::upper(Str::random(8)), 'status' => $status,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);

        /** @var OrderItem $item */
        $item = $order->items()->create([
            'ticket_type_id' => ($product ?? $this->entry)->id, 'slot_id' => $slot->id,
            'quantity' => $quantity, 'unit_price' => 1000, 'seats' => $quantity,
            'guest_data' => $guests, 'event_data' => $eventData === [] ? null : $eventData,
        ]);
        if ($cancelled) {
            $item->forceFill(['cancelled_at' => now()])->saveQuietly();
        }

        return $item;
    }
}
