<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AnalyticsGoal;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\AnalyticsGoals;
use App\Filament\Analytics\GoalsForm;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Widgets\Analytics\MetricsWidget;
use App\Filament\Widgets\Analytics\MoneyOverviewWidget;
use App\Filament\Widgets\Analytics\SummaryWidget;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Los objetivos del mes: guardarlos y verlos** (T3c·2 de `specs/analitica-para-decidir.md` §4.13, `DECISIONES #759`): un
 * solo escritor con rastro (el antes y el después, sin datos personales), el formulario en la unidad del operador, solo este
 * mes y el siguiente, el permiso `analytics.manage` re-exigido al guardar (`SEC-04`) y la tarjeta que lo dice en su pestaña
 * y en «Resumen».
 *
 * El reloj: el 10 de junio de 2026 a las 11:00 en Madrid (las 09:00 UTC), con el 32 % del mes pasado.
 */
class AnalyticsGoalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        app()->setLocale('es');
    }

    public function test_saving_puts_changes_and_removes_goals_with_one_trail_each_time(): void
    {
        $june = CarbonImmutable::parse('2026-06-17');
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->assertTrue(AnalyticsGoals::save($june, ['money.net' => 2_000_000, 'traffic.conversion' => 800], $admin->id));
        $this->assertSame(['money.net' => 2_000_000, 'traffic.conversion' => 800], AnalyticsGoals::forMonth($june));
        $row = AnalyticsGoal::query()->where('metric_key', 'money.net')->firstOrFail();
        $this->assertSame('2026-06-01', $row->month->toDateString(), 'el mes se guarda por su primer día');
        $this->assertSame($admin->id, $row->set_by);

        $trail = AuditLog::query()->where('action', 'analytics.goals_updated')->sole();
        $this->assertSame([
            'month' => '2026-06',
            'before' => ['money.net' => null, 'traffic.conversion' => null],
            'after' => ['money.net' => 2_000_000, 'traffic.conversion' => 800],
        ], $trail->payload);

        // Lo mismo otra vez: nada cambia, nada se escribe.
        $this->assertFalse(AnalyticsGoals::save($june, ['money.net' => 2_000_000, 'traffic.conversion' => 800], $admin->id));
        $this->assertSame(1, AuditLog::query()->where('action', 'analytics.goals_updated')->count());

        // Cambiar uno y quitar otro: el rastro lleva solo lo que cambió; lo que no viene, no se toca.
        $this->assertTrue(AnalyticsGoals::save($june, ['money.net' => 2_500_000, 'traffic.conversion' => null], $admin->id));
        $this->assertSame(['money.net' => 2_500_000], AnalyticsGoals::forMonth($june));
        $last = AuditLog::query()->where('action', 'analytics.goals_updated')->latest('id')->firstOrFail();
        $this->assertSame([
            'month' => '2026-06',
            'before' => ['money.net' => 2_000_000, 'traffic.conversion' => 800],
            'after' => ['money.net' => 2_500_000, 'traffic.conversion' => null],
        ], $last->payload);

        AnalyticsGoals::save($june, ['money.sold' => 100], $admin->id);
        $this->assertSame(['money.net' => 2_500_000, 'money.sold' => 100], AnalyticsGoals::forMonth($june), 'la clave que no viene sigue');
        $this->assertSame([], AnalyticsGoals::forMonth(CarbonImmutable::parse('2026-07-01')), 'cada mes, los suyos');
    }

    /** Leer el mes lo memoriza; guardar lo olvida: la tarjeta dice el objetivo nuevo en la petición siguiente. */
    public function test_saving_forgets_the_month_and_refuses_what_is_not_a_goal(): void
    {
        $june = CarbonImmutable::parse('2026-06-01');
        $this->assertSame([], AnalyticsGoals::forMonth($june));

        AnalyticsGoals::save($june, ['money.orders' => 120], null);
        $this->assertSame(['money.orders' => 120], AnalyticsGoals::forMonth($june));

        foreach ([['<script>' => 1], ['money.net' => 0], ['money.net' => -5]] as $bad) {
            try {
                AnalyticsGoals::save($june, $bad, null);
                $this->fail('tenía que rechazar '.json_encode($bad));
            } catch (\InvalidArgumentException) {
                // lo esperado
            }
        }
        $this->assertSame(1, AnalyticsGoal::query()->count());
    }

    /** El operador escribe euros, personas y porcentajes; se guardan céntimos, unidades y puntos básicos. */
    public function test_the_form_speaks_the_unit_of_the_operator(): void
    {
        $targets = GoalsForm::targets([
            'goal_money__net' => '20000',
            'goal_money__sold' => '1234.56',
            'goal_money__orders' => '120',
            'goal_occupancy__visitors' => '',
            'goal_parties__parties' => '0',
            'goal_traffic__conversion' => '8.5',
            'goal_occupancy__entries' => '150',
        ]);

        $this->assertSame([
            'money.net' => 2_000_000,
            'money.sold' => 123_456,
            'money.orders' => 120,
            'occupancy.visitors' => null,
            'parties.parties' => null,
            'customers.registrations' => null,
            'occupancy.entries' => 10_000,
            'traffic.conversion' => 850,
        ], $targets, 'vacío o cero, sin objetivo; una tasa, como mucho el 100 %');

        AnalyticsGoals::save(CarbonImmutable::parse('2026-06-01'), $targets, null);
        $fill = GoalsForm::fill(CarbonImmutable::parse('2026-06-01'));
        $this->assertSame('2026-06', $fill['month']);
        $this->assertEquals(20000, $fill['goal_money__net']);
        $this->assertEquals(1234.56, $fill['goal_money__sold']);
        $this->assertSame(120, $fill['goal_money__orders']);
        $this->assertEquals(8.5, $fill['goal_traffic__conversion']);
        $this->assertNull($fill['goal_occupancy__visitors']);
    }

    public function test_only_this_month_and_the_next_can_be_touched(): void
    {
        $this->assertSame(['2026-06', '2026-07'], array_keys(GoalsForm::months()));
        $this->assertSame('2026-07-01', GoalsForm::month('2026-07')?->toDateString());
        $this->assertNull(GoalsForm::month('2026-05'), 'el mes pasado ya no se toca');
        $this->assertNull(GoalsForm::month('2026-08'));
        $this->assertNull(GoalsForm::month(['2026-06']));
    }

    public function test_the_admin_sets_the_goals_from_the_summary_and_the_card_says_it(): void
    {
        $admin = $this->admin();

        // El botón vive al pie de «Resumen» y de ninguna otra pestaña (Filament resuelve la acción por su nombre aunque no se
        // pinte: por eso se mira lo pintado). ⚠️ `withQueryParams` se QUEDA para la siguiente `test()` del mismo caso: las dos
        // lo dicen.
        Livewire::actingAs($admin)->withQueryParams([AnalyticsPage::TAB_QUERY_KEY => 'money'])->test(AnalyticsPage::class)
            ->assertDontSee('Objetivos del mes');

        Livewire::actingAs($admin)->withQueryParams([AnalyticsPage::TAB_QUERY_KEY => 'summary'])->test(AnalyticsPage::class)
            ->assertSee('Objetivos del mes')
            ->assertActionVisible('goals')
            ->callAction('goals', data: ['month' => '2026-06', 'goal_money__net' => '20000', 'goal_traffic__conversion' => '8'])
            ->assertHasNoActionErrors()
            ->assertNotified('Objetivos guardados')
            ->assertDispatched(AnalyticsPage::GOALS_SAVED_EVENT);

        $this->assertSame(['money.net' => 2_000_000, 'traffic.conversion' => 800], AnalyticsGoals::forMonth(CarbonImmutable::parse('2026-06-01')));
        $this->assertSame($admin->id, AnalyticsGoal::query()->where('metric_key', 'money.net')->value('set_by'));

        $this->actingAs($admin);
        $line = "Objetivo del mes: 20.000 €. Por detrás del ritmo: llevas el 0\u{00A0}% y ha pasado el 32\u{00A0}% del mes.";

        // En «Resumen» y en su pestaña: la misma tarjeta.
        foreach ([SummaryWidget::class, MoneyOverviewWidget::class] as $widget) {
            $html = Livewire::test($widget, ['pageFilters' => ['period' => 'this_month']])->assertSee($line)->html();
            $this->assertMatchesRegularExpression('/data-metric="money\.net".*?data-metric-goal="behind"\s+data-metric-goal-tone="watch"/s', $html);
        }
        // La conversión, un nivel: sin visitas es el 0 %, por debajo del 8 %.
        Livewire::test(SummaryWidget::class, ['pageFilters' => ['period' => 'this_month']])
            ->assertSee("Objetivo del mes: 8,0\u{00A0}%. Por debajo.");
    }

    /**
     * Tras guardar, las tarjetas se vuelven a pintar SIN recargar: escuchan el evento de la página. En el navegador, sin el
     * oyente nadie las vuelve a pedir (lo mide la sonda del panel); aquí se comprueba que el oyente está, porque en una prueba
     * de Livewire cualquier ida y vuelta repinta.
     */
    public function test_the_cards_listen_to_the_goals_being_saved(): void
    {
        $listens = array_filter(
            (new \ReflectionClass(MetricsWidget::class))->getMethods(),
            static fn (\ReflectionMethod $method): bool => array_filter(
                $method->getAttributes(On::class),
                static fn (\ReflectionAttribute $on): bool => ($on->getArguments()[0] ?? null) === AnalyticsPage::GOALS_SAVED_EVENT,
            ) !== [],
        );

        $this->assertNotSame([], $listens, 'las tarjetas no escuchan «'.AnalyticsPage::GOALS_SAVED_EVENT.'»');
    }

    public function test_outside_a_month_the_card_says_no_goal(): void
    {
        AnalyticsGoals::save(CarbonImmutable::parse('2026-06-01'), ['money.net' => 2_000_000], null);
        $this->actingAs($this->admin());

        Livewire::test(SummaryWidget::class, ['pageFilters' => ['period' => 'last_week']])
            ->assertDontSee('Objetivo del mes')
            ->assertDontSeeHtml('data-metric-goal');
        Livewire::test(SummaryWidget::class, ['pageFilters' => ['period' => 'last_month']])
            ->assertDontSeeHtml('data-metric-goal', 'mayo no tiene objetivo: el de junio no se le pega');
    }

    public function test_without_the_permission_the_button_is_not_there(): void
    {
        Livewire::actingAs($this->withPermissions(['reports.view']))->test(AnalyticsPage::class)->assertActionHidden('goals');
        Livewire::actingAs($this->withPermissions(['reports.view', 'analytics.manage']))->test(AnalyticsPage::class)->assertActionVisible('goals');
    }

    /**
     * `SEC-04`: con el formulario abierto le quitan el permiso; al guardar, no se guarda nada ni queda rastro. Lo para
     * Filament, que al enviar vuelve a evaluar `visible()` (`callMountedAction` → `isDisabled()` → `isHidden()`, medido el
     * 28-09): si una versión dejara de hacerlo, este caso se pone en rojo.
     */
    public function test_a_permission_revoked_while_the_form_is_open_saves_nothing(): void
    {
        $analyst = $this->withPermissions(['reports.view', 'analytics.manage']);

        $page = Livewire::actingAs($analyst)->test(AnalyticsPage::class)
            ->mountAction('goals')
            ->fillForm(['month' => '2026-06', 'goal_money__net' => '20000']);

        $analyst->roles()->firstOrFail()->permissions()->detach(Permission::query()->where('name', 'analytics.manage')->value('id'));

        $page->callMountedAction()->assertNotNotified('Objetivos guardados');

        $this->assertSame(0, AnalyticsGoal::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'analytics.goals_updated')->count());
    }

    /** Un cuerpo forjado con el mes pasado no lo toca, aunque el formulario solo ofrezca este y el siguiente. */
    public function test_a_forged_month_saves_nothing(): void
    {
        Livewire::actingAs($this->admin())->test(AnalyticsPage::class)
            ->callAction('goals', data: ['month' => '2026-05', 'goal_money__net' => '20000'])
            ->assertHasActionErrors(['month']);

        $this->assertSame(0, AnalyticsGoal::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'analytics.goals_updated')->count());
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $user;
    }

    /**
     * Un empleado cuyo rol tiene EXACTAMENTE esos permisos.
     *
     * @param  list<string>  $permissions
     */
    private function withPermissions(array $permissions): User
    {
        $role = Role::query()->where('name', 'staff')->firstOrFail();
        $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
