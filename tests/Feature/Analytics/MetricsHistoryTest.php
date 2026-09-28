<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Setting;
use App\Filament\Analytics\Metrics\MarketingMetrics;
use App\Filament\Analytics\Metrics\MoneyMetrics;
use App\Filament\Widgets\Analytics\MoneyOverviewWidget;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La historia de una cifra** (T3b de `specs/analitica-para-decidir.md` §4.13; `[DECIDIDO owner]` `#790`), con un fixture
 * A MANO: pedidos cobrados el día 5 de cada mes, de septiembre de 2025 a junio de 2026, uno más cada mes (1 en septiembre…
 * 10 en junio). Hoy es el 10 de junio de 2026 a las 11:00 del parque: «este mes» es del 1 al 10 de junio, y sus 12 periodos
 * comparables, del 1 al 10 de cada mes anterior, de mayo de 2025 hacia atrás… hasta junio de 2025.
 *
 * El primer pedido es del 5 de septiembre: ese mes EMPEZÓ antes de medir y no cuenta (un «cero» de agosto tampoco). Quedan
 * ocho —octubre a mayo, de 2 a 9 pedidos— y junio, con 10, es el más alto: «Bien».
 */
class MetricsHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));   // 11:00 en el parque
        app()->setLocale('es');

        $month = Carbon::parse('2025-09-05 08:00:00');
        for ($n = 1; $n <= 10; $n++) {
            for ($i = 0; $i < $n; $i++) {
                $this->paid($month->copy()->addMinutes($i));
            }
            $month->addMonthNoOverflow();
        }
    }

    private function paid(Carbon $at): void
    {
        Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'R-'.Str::upper(Str::random(8)), 'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => $at,
        ]);
    }

    public function test_the_history_is_the_same_stretch_of_the_previous_months_since_measuring(): void
    {
        $orders = MoneyMetrics::for(ReportPeriod::ThisMonth->window(), Comparison::Previous)['money.orders'];

        $this->assertSame(10, $orders->value);
        $this->assertSame([9, 8, 7, 6, 5, 4, 3, 2], $orders->history, 'de mayo a octubre; septiembre empezó antes de medir');
        $this->assertSame('month', $orders->historyUnit);
        $this->assertSame(['state' => 'high', 'tone' => 'good', 'low' => 2, 'high' => 9, 'n' => 8], $orders->verdict());
        $this->assertSame('Bien: la más alta de tus últimos 8 meses (iba de 2 a 9).', $orders->verdictLine());

        // Los compradores comparados viven en OTRO sitio del informe (`customers.previous`, T0c): también cambian de periodo.
        // Cada pedido es de un cliente nuevo, así que los compradores nuevos de cada mes son sus pedidos.
        $new = MoneyMetrics::for(ReportPeriod::ThisMonth->window(), Comparison::Previous)['money.new'];
        $this->assertSame([9, 8, 7, 6, 5, 4, 3, 2], $new->history);
    }

    /**
     * PARIDAD: el periodo más reciente de la historia es el periodo anterior de la tarjeta, con la MISMA fórmula (el
     * catálogo lee los totales de ese periodo como «comparado»). Si la historia usara otra cuenta, aquí se vería.
     */
    public function test_the_latest_period_of_the_history_is_the_previous_period_of_the_tile(): void
    {
        foreach (MoneyMetrics::for(ReportPeriod::ThisMonth->window(), Comparison::Previous) as $key => $metric) {
            if ($metric->previous === null) {
                $this->assertNull($metric->history, "«{$key}» no tiene comparación: tampoco historia");

                continue;
            }
            $this->assertSame($metric->previous, $metric->history[0] ?? null, "«{$key}»: el más reciente de su historia no es su comparado");
        }
    }

    /** Una fuente que aún no mide (las visitas a la web, sin ninguna sesión) no tiene historia: ni un cero cuenta. */
    public function test_a_source_that_does_not_measure_yet_has_no_history(): void
    {
        $visits = MarketingMetrics::for(ReportPeriod::ThisMonth->window(), Comparison::Previous)['traffic.visits'];

        $this->assertSame([], $visits->history);
        $this->assertSame('Aún sin historia para decir si es normal (0 de 8 meses).', $visits->verdictLine());
    }

    /** Por días: los 12 mismos días de la semana anteriores (el 10-06-2026 es miércoles). */
    public function test_a_day_looks_back_at_the_same_weekday(): void
    {
        $today = MoneyMetrics::for(ReportPeriod::Today->window(), Comparison::Previous)['money.orders'];

        $this->assertSame('day:3', $today->historyUnit);
        $this->assertCount(12, $today->history, 'doce miércoles, todos después del primer pedido');
        $this->assertSame(array_fill(0, 12, 0), $today->history, 'ningún pedido cayó en miércoles antes de las 11:00: CERO pedidos es un dato');

        // …pero el valor medio de un día SIN pedidos no vale 0 €: no existe, y no entra en la historia.
        $avg = MoneyMetrics::for(ReportPeriod::Today->window(), Comparison::Previous)['money.avg_order'];
        $this->assertSame([], $avg->history);
        $this->assertSame('Aún sin historia para decir si es normal (0 de 8 miércoles).', $avg->verdictLine());
    }

    /** La tarjeta lo pinta: la frase, su estado y su tono, y la explicación de la banda dentro de «¿Cómo se calcula?». */
    public function test_the_tile_says_the_verdict(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);
        $this->actingAs($admin);

        $html = Livewire::test(MoneyOverviewWidget::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertSee('Bien: la más alta de tus últimos 8 meses (iba de 2 a 9).')
            ->assertSee('«Normal para ti» es el tramo entre la cifra más baja y la más alta de los 12 periodos anteriores')
            ->html();

        // El «bien» va en verde; y lo normal, en gris: el color refuerza la palabra, nunca la sustituye.
        $this->assertMatchesRegularExpression('/<p\s[^>]*text-success-700[^>]*data-metric-verdict="high"\s+data-metric-tone="good"/', $html);
        $this->assertMatchesRegularExpression('/<p\s[^>]*text-gray-700[^>]*data-metric-verdict="normal"\s+data-metric-tone="neutral"/', $html);
    }
}
