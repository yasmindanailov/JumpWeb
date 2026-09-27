<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\CustomerVisit;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Analytics\CustomersReport;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **Registros y puerta, contra hechos sembrados** (`specs/analitica.md` §4.5 y §6, T2b; `#735`).
 *
 * El parque en `Europe/Madrid`, el reloj en el miércoles 2026-06-10 a las 09:00 UTC. Junio lleva cuatro cuentas
 * de cliente (una verificada y compradora, una a las 22:30 UTC del 9 que es el 10 en el parque), una del
 * EQUIPO que no cuenta, una de mayo (el periodo anterior); dos altas con contraseña y una con Google en el libro;
 * y en la puerta: tres tecleos (dos encontrados, uno no), un escaneo encontrado —el mismo cliente dos veces el
 * mismo día, que cuenta una—, uno a las 22:30 UTC del 9 (hora 0 del 10 en el parque), una ficha abierta y tres
 * visitas acreditadas de dos clientes.
 */
class CustomersReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    }

    // ─── El fixture ─────────────────────────────────────────────────────────────────────────────

    /** @return array<string, User> */
    private function seedJune(): array
    {
        $ana = $this->customer('2026-06-03 10:00:00', verified: true);
        $bea = $this->customer('2026-06-05 18:00:00', verified: false);
        $carl = $this->customer('2026-06-09 22:30:00', verified: true);   // el 10 en el parque
        $dora = $this->customer('2026-06-07 12:00:00', verified: true);
        $mayo = $this->customer('2026-05-05 10:00:00', verified: true);     // el periodo anterior: el MISMO tramo, 1–10 de mayo (T0, #755)
        $staff = $this->customer('2026-06-04 10:00:00', verified: true, role: 'staff');   // no es cliente

        Order::create(['user_id' => $ana->id, 'code' => 'JW-CR001', 'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => '2026-06-03 11:00:00']);

        $this->registered($ana, 'password', '2026-06-03 10:00:00');
        $this->registered($bea, 'password', '2026-06-05 18:00:00');
        $this->registered($carl, 'google', '2026-06-09 22:30:00');
        // Dora se dio de alta desde el panel: sin hecho en el libro → «sin dato».

        // La puerta.
        $this->lookup(CustomersReport::ACTION_LOOKUP_TYPED, $ana, '2026-06-05 09:15:00');
        $this->lookup(CustomersReport::ACTION_LOOKUP_TYPED, null, '2026-06-05 09:20:00');       // no encontrada
        $this->lookup(CustomersReport::ACTION_LOOKUP_SCANNED, $ana, '2026-06-05 15:40:00');     // la misma Ana, el mismo día
        $this->lookup(CustomersReport::ACTION_LOOKUP_TYPED, $bea, '2026-06-09 22:30:00');       // hora 0 del 10 en el parque
        $this->lookup(CustomersReport::ACTION_PROFILE_VIEWED, $ana, '2026-06-05 09:16:00');
        $this->lookup(CustomersReport::ACTION_LOOKUP_TYPED, $mayo, '2026-05-06 10:00:00');      // el periodo anterior

        CustomerVisit::create(['user_id' => $ana->id, 'visited_on' => '2026-06-05', 'registered_by' => $staff->id]);
        CustomerVisit::create(['user_id' => $ana->id, 'visited_on' => '2026-06-08', 'registered_by' => $staff->id]);
        CustomerVisit::create(['user_id' => $bea->id, 'visited_on' => '2026-06-08', 'registered_by' => $staff->id]);
        CustomerVisit::create(['user_id' => $mayo->id, 'visited_on' => '2026-05-06', 'registered_by' => $staff->id]);

        return compact('ana', 'bea', 'carl', 'dora', 'mayo', 'staff');
    }

    // ─── Los registros ──────────────────────────────────────────────────────────────────────────

    public function test_registrations_count_customers_only_in_the_park_day(): void
    {
        $this->seedJune();

        $r = (new CustomersReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame([
            'total' => 4,        // Ana, Bea, Carl y Dora; ni el empleado ni la de mayo
            'verified' => 3,
            'buyers' => 1,
            'by_method' => ['password' => 2, 'google' => 1, 'unknown' => 1],
        ], $r['registrations']);
        $this->assertSame(1, $r['previous']['registrations']);

        $series = collect($r['series'])->keyBy('key');
        $this->assertSame(1, $series['2026-06-10']['registrations'], 'las 22:30 UTC del 9 son el 10 en el parque');
        $this->assertSame(0, $series['2026-06-09']['registrations']);
        $this->assertSame(1, $series['2026-06-03']['verified']);
    }

    // ─── La puerta ──────────────────────────────────────────────────────────────────────────────

    public function test_the_gate_is_measured_from_its_audit_trail_and_its_visits(): void
    {
        $this->seedJune();

        $r = (new CustomersReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame([
            'lookups' => 4,
            'typed' => 3,
            'scanned' => 1,
            'found' => 3,
            'not_found' => 1,
            'customers' => 2,        // Ana (dos veces) y Bea
            'profile_views' => 1,
            'visits' => 3,
            'visitors' => 2,
        ], $r['gate']);
        // T0c (#756): en mayo, una sola visita y sin ninguna antes → una «primera vez».
        $this->assertSame(['registrations' => 1, 'lookups' => 1, 'found' => 1, 'visits' => 1, 'returning' => 0, 'first_time' => 1, 'repeat' => 0], $r['previous']);

        $series = collect($r['series'])->keyBy('key');
        $this->assertSame(['key' => '2026-06-05', 'registrations' => 1, 'verified' => 0, 'lookups' => 3, 'found' => 2, 'customers' => 1, 'visits' => 1], $series['2026-06-05']);
        $this->assertSame(2, $series['2026-06-08']['visits']);
        $this->assertSame(1, $series['2026-06-10']['lookups'], 'la búsqueda de las 22:30 UTC del 9 es del 10 en el parque');
        $this->assertSame(0, $series['2026-06-09']['lookups']);
    }

    /**
     * **Los que VUELVEN** (T0c, `#756`; el owner, 27-09: «cuántas veces vuelven cada X tiempo»). Con el reloj en el 10 de
     * junio a las 11:00 de Madrid, «Este mes» es del 1 al 10:
     *  - A: 1-abr (sin origen, el botón de antes), 3-jun (carné), 8-jun (búsqueda) → vuelve y viene dos veces; 63 y 5 días.
     *  - B: 5-jun (carné) → primera vez.
     *  - C: 20-may (carné), 9-jun (búsqueda) → vuelve; 20 días.
     *  - D: 1-dic-2025, 10-jun (carné) → vuelve; 191 días.
     *  - F: 2-may → no es de junio (y en mayo cuenta en el tramo comparado).
     * Huecos [5, 20, 63, 191]: mediana 20 (la de abajo de las dos centrales), uno en cada tramo.
     */
    public function test_the_ones_who_come_back_how_often_and_by_which_door(): void
    {
        $visit = fn (User $u, string $day, ?string $source) => app(GateVisits::class)->register($u, null, Carbon::parse($day), $source);
        [$a, $b, $c, $d, $f] = [User::factory()->create(), User::factory()->create(), User::factory()->create(), User::factory()->create(), User::factory()->create()];

        $visit($a, '2026-04-01', null);
        $visit($a, '2026-06-03', CustomerVisit::SOURCE_CARD);
        $visit($a, '2026-06-08', CustomerVisit::SOURCE_LOOKUP);
        $visit($b, '2026-06-05', CustomerVisit::SOURCE_CARD);
        $visit($c, '2026-05-20', CustomerVisit::SOURCE_CARD);
        $visit($c, '2026-06-09', CustomerVisit::SOURCE_LOOKUP);
        $visit($d, '2025-12-01', CustomerVisit::SOURCE_CARD);
        $visit($d, '2026-06-10', CustomerVisit::SOURCE_CARD);
        $visit($f, '2026-05-02', CustomerVisit::SOURCE_CARD);

        $r = (new CustomersReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame([
            'visitors' => 4,
            'returning' => 3,
            'first_time' => 1,
            'repeat' => 1,
            'gap_median_days' => 20,
            'gap_buckets' => ['week' => 1, 'month' => 1, 'quarter' => 1, 'longer' => 1],
            'by_source' => ['card' => 3, 'lookup' => 2],
        ], $r['returns']);
        $this->assertSame(1, $r['previous']['first_time'], 'F en el 1–10 de mayo');
        $this->assertSame(0, $r['previous']['returning']);
    }

    /** Los bordes de «cada cuánto vuelven»: 7 días es «en una semana», 30 «en un mes», 90 «en tres meses»; uno más, el siguiente. */
    public function test_the_return_gaps_fall_in_their_brackets_at_the_edges(): void
    {
        $visit = fn (User $u, string $day) => app(GateVisits::class)->register($u, null, Carbon::parse($day), CustomerVisit::SOURCE_CARD);

        foreach ([7, 8, 30, 31, 90, 91] as $gap) {
            $u = User::factory()->create();
            $visit($u, Carbon::parse('2026-06-05')->subDays($gap)->toDateString());
            $visit($u, '2026-06-05');
        }

        $r = (new CustomersReport)->compute(ReportPeriod::ThisMonth->window())['returns'];

        $this->assertSame(['week' => 1, 'month' => 2, 'quarter' => 2, 'longer' => 1], $r['gap_buckets']);
        $this->assertSame(30, $r['gap_median_days'], 'huecos [7, 8, 30, 31, 90, 91]: la de abajo de las dos centrales');
    }

    public function test_the_hours_are_the_park_hours(): void
    {
        $this->seedJune();

        $hours = (new CustomersReport)->compute(ReportPeriod::ThisMonth->window())['hours'];

        $this->assertCount(24, $hours);
        $this->assertSame(2, $hours[11], 'las 09:15 y 09:20 UTC son las 11 en Madrid');
        $this->assertSame(1, $hours[17]);
        $this->assertSame(1, $hours[0], 'las 22:30 UTC son las 0 del día siguiente en Madrid');
        $this->assertSame(0, $hours[22]);
        $this->assertSame(4, array_sum($hours));
    }

    public function test_an_empty_period_is_all_zeros(): void
    {
        $r = (new CustomersReport)->compute(ReportPeriod::Yesterday->window());

        $this->assertSame(['total' => 0, 'verified' => 0, 'buyers' => 0, 'by_method' => ['password' => 0, 'google' => 0, 'unknown' => 0]], $r['registrations']);
        $this->assertSame(0, $r['gate']['lookups']);
        $this->assertSame(0, $r['gate']['visitors']);
        $this->assertSame(array_fill(0, 24, 0), $r['hours']);
        $this->assertCount(1, $r['series']);
    }

    // ─── El presupuesto y la caché ──────────────────────────────────────────────────────────────

    public function test_the_query_budget_does_not_grow_with_the_rows(): void
    {
        $this->seedJune();
        ReportPeriod::ThisMonth->window();   // el memo de la zona, caliente

        DB::flushQueryLog();
        DB::enableQueryLog();
        (new CustomersReport)->compute(ReportPeriod::ThisMonth->window());
        $withRows = count(DB::getQueryLog());

        DB::flushQueryLog();
        (new CustomersReport)->compute(ReportPeriod::Yesterday->window());
        $empty = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $withRows);
        $this->assertSame($empty, $withRows);
    }

    public function test_the_report_is_cached_per_period(): void
    {
        $this->seedJune();
        Cache::flush();

        $first = CustomersReport::for(ReportPeriod::ThisMonth->window());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $second = CustomersReport::for(ReportPeriod::ThisMonth->window());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($first, $second);
        $this->assertSame(0, $queries);
    }

    // ─── Los ayudantes ──────────────────────────────────────────────────────────────────────────

    private function customer(string $createdAtUtc, bool $verified, string $role = 'customer'): User
    {
        $user = User::factory()->create(['email_verified_at' => $verified ? $createdAtUtc : null]);
        $user->roles()->sync([Role::where('name', $role)->value('id')]);
        $user->forceFill(['created_at' => $createdAtUtc, 'updated_at' => $createdAtUtc])->saveQuietly();

        return $user;
    }

    private function registered(User $user, string $method, string $atUtc): void
    {
        AnalyticsEvent::query()->create([
            'event_id' => Visitor::mint(), 'user_id' => $user->id, 'name' => 'user_registered',
            'props' => ['method' => $method], 'occurred_at' => $atUtc, 'received_at' => $atUtc,
        ]);
    }

    private function lookup(string $action, ?User $target, string $atUtc): void
    {
        $row = $action === CustomersReport::ACTION_PROFILE_VIEWED
            ? AuditLogger::log($action, $target, ['via' => 'lookup'])
            : AuditLogger::logSensitive($action, 'dato-tecleado', $target);

        $this->assertInstanceOf(AuditLog::class, $row);
        $row->forceFill(['created_at' => $atUtc])->saveQuietly();
    }
}
