<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Filament\Analytics\Metric;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Widgets\Analytics\BookedMoreWidget;
use App\Filament\Widgets\Analytics\GateWidget;
use App\Filament\Widgets\Analytics\MetricsWidget;
use App\Filament\Widgets\Analytics\MoneyMoreWidget;
use App\Filament\Widgets\Analytics\MoneyOverviewWidget;
use App\Filament\Widgets\Analytics\SummaryWidget;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La forma del cuadro** (T3a de `specs/analitica-para-decidir.md` §4.13, `DECISIONES #759`): siete pestañas, solo se
 * pinta la abierta (medido el 28-09: al abrir pedían los widgets de las seis), como mucho seis tarjetas arriba (ocho en
 * «Resumen»), lo que no decide nace plegado, y «Resumen» enseña LAS MISMAS cifras que su pestaña.
 */
class AnalyticsTabsTest extends TestCase
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

    /** Con una pestaña abierta existen sus widgets y ninguno de otra: los de otra ni se pintan ni piden. */
    public function test_only_the_open_tab_is_painted(): void
    {
        $admin = $this->admin();

        foreach (AnalyticsPage::TABS as $tab => $widgets) {
            $page = Livewire::actingAs($admin)->withQueryParams([AnalyticsPage::TAB_QUERY_KEY => $tab])->test(AnalyticsPage::class);
            $page->assertSet('tab', $tab);

            foreach ($widgets as $widget) {
                $page->assertSeeLivewire($widget);
            }
            foreach (AnalyticsPage::TABS as $other => $otherWidgets) {
                if ($other === $tab) {
                    continue;
                }
                foreach ($otherWidgets as $widget) {
                    $page->assertDontSeeLivewire($widget);
                }
            }
        }
    }

    /** Se abre en «Resumen»; las claves de antes de la T3a abren su pestaña de ahora, y una desconocida no rompe nada. */
    public function test_the_old_keys_open_their_tab_and_an_unknown_one_opens_the_summary(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(AnalyticsPage::class)->assertSet('tab', 'summary')->assertSee('¿Cómo vamos?');
        Livewire::actingAs($admin)->withQueryParams([AnalyticsPage::TAB_QUERY_KEY => 'traffic'])->test(AnalyticsPage::class)
            ->assertSet('tab', 'marketing')->assertSee('¿Qué trae visitas y ventas?');
        Livewire::actingAs($admin)->withQueryParams([AnalyticsPage::TAB_QUERY_KEY => 'surveys'])->test(AnalyticsPage::class)
            ->assertSet('tab', 'satisfaction')->assertSee('¿Están contentos?');
        Livewire::actingAs($admin)->withQueryParams([AnalyticsPage::TAB_QUERY_KEY => 'nada'])->test(AnalyticsPage::class)
            ->assertSet('tab', 'summary');

        // El navegador cambia la pestaña (la fila o el selector del móvil): también se normaliza.
        Livewire::actingAs($admin)->test(AnalyticsPage::class)->set('tab', 'surveys')->assertSet('tab', 'satisfaction')
            ->set('tab', '<script>')->assertSet('tab', 'summary');
    }

    /**
     * Cada pestaña empieza por UN grupo de tarjetas a la vista, con seis como mucho (ocho en «Resumen»), y los demás grupos de
     * tarjetas de la pestaña nacen plegados (§2, criterio 1; §4.1.bis).
     */
    public function test_each_tab_opens_with_one_group_of_at_most_six_and_folds_the_rest(): void
    {
        foreach (AnalyticsPage::TABS as $tab => $widgets) {
            $groups = array_values(array_filter($widgets, static fn (string $w): bool => is_subclass_of($w, MetricsWidget::class)));
            $this->assertNotEmpty($groups, "«{$tab}» sin tarjetas");
            $this->assertSame($groups[0], $widgets[0], "«{$tab}» no empieza por sus tarjetas");
            $this->assertFalse($groups[0]::FOLDED, "las tarjetas de arriba de «{$tab}» nacen plegadas");
            $max = $groups[0]::MAX_TOP;
            $this->assertLessThanOrEqual($max, count($groups[0]::KEYS), "«{$tab}» enseña más de {$max} arriba");

            foreach (array_slice($groups, 1) as $folded) {
                $this->assertTrue($folded::FOLDED, "{$folded} está en «{$tab}» y no nace plegado");
            }
        }

        $this->assertSame(6, MetricsWidget::MAX_TOP);
        $this->assertSame(8, SummaryWidget::MAX_TOP);
    }

    /**
     * «Resumen» enseña las cifras clave de §4.5 —tecleadas a mano—, y cada una es LA MISMA de arriba de su pestaña: la misma
     * clave, el mismo valor, la misma definición, con el enlace a esa pestaña. La cartera (T4, §4.8.quater) es la excepción
     * DECLARADA: «Ocupación» ya lleva sus seis tarjetas arriba (§4.1 no la cuenta entre ellas; §4.11, ≤ 6), así que en su
     * pestaña va en su grupo plegado, bajo el gráfico por semana.
     */
    public function test_the_summary_shows_the_same_figures_as_the_top_of_their_tab(): void
    {
        $this->actingAs($this->admin());

        $this->assertSame(
            ['money.net', 'occupancy.entries', 'occupancy.visitors', 'traffic.conversion', 'money.avg_order', 'money.returning', 'surveys.scale_mean', 'booked.cents_30'],
            SummaryWidget::KEYS,
        );

        foreach (self::stats(new SummaryWidget) as $stat) {
            /** @var Metric $metric */
            $metric = $stat->getViewData()['metric'];
            $tab = SummaryWidget::TABS[$metric->key];
            $top = $metric->key === 'booked.cents_30' ? BookedMoreWidget::class : AnalyticsPage::TABS[$tab][0];
            $this->assertContains($top, AnalyticsPage::TABS[$tab], "«{$metric->key}» enlaza a «{$tab}» y su grupo no está allí");
            $this->assertContains($metric->key, $top::KEYS, "«{$metric->key}» enlaza a «{$tab}» y no está arriba allí");

            $there = collect(self::stats(new $top))->first(fn (Stat $s): bool => $s->getViewData()['metric']->key === $metric->key)->getViewData()['metric'];
            $this->assertSame($there->displayValue(), $metric->displayValue());
            $this->assertSame($there->how, $metric->how);
            $this->assertStringEndsWith('/admin/analitica?pestana='.$tab, $stat->getViewData()['link']['url']);
        }
    }

    /**
     * Dónde vive una cifra (T3c·1): la pestaña donde está arriba o plegada —adonde lleva su frase en «lo que ha cambiado»—;
     * «Resumen» no cuenta, solo repite.
     */
    public function test_each_figure_lives_in_a_tab_that_is_not_the_summary(): void
    {
        $this->assertSame('customers', AnalyticsPage::tabOf('occupancy.visitors'), 'está en Resumen y arriba de Clientes');
        $this->assertSame('money', AnalyticsPage::tabOf('money.net'));
        $this->assertSame('money', AnalyticsPage::tabOf('money.collected'), 'plegada');
        $this->assertSame('marketing', AnalyticsPage::tabOf('traffic.identified'), 'en «Calidad del dato»');
        $this->assertNull(AnalyticsPage::tabOf('no.existe'));
    }

    /**
     * Lo plegado nace plegado y recuerda su estado por widget; lo de arriba, no. ⚠️ Lo que decide es el estado INICIAL de
     * Alpine (`isCollapsed: $persist(true)`): la clase `fi-collapsed` la pinta Filament en el servidor siempre que el estado
     * se recuerde, plegado o no (el arnés lo cazó el 28-09: una prueba que miraba la clase no veía nacer abierto lo plegado).
     */
    public function test_folded_groups_are_born_folded_and_the_top_is_open(): void
    {
        $this->actingAs($this->admin());
        $filters = ['pageFilters' => ['period' => 'this_month']];

        $folded = Livewire::test(MoneyMoreWidget::class, $filters)->html();
        $this->assertStringContainsString('data-analytics-group="folded"', $folded);
        $this->assertStringContainsString("isCollapsed: \$persist(true).as(`section-\${'analitica-money-more-widget'", $folded);

        $gate = Livewire::test(GateWidget::class, $filters)->html();
        $this->assertStringContainsString('isCollapsed: $persist(true)', $gate, '«La puerta» es mecánica: plegada');

        $top = Livewire::test(MoneyOverviewWidget::class, $filters)->html();
        $this->assertStringContainsString('data-analytics-group="top"', $top);
        $this->assertStringNotContainsString('$persist(', $top);
        $this->assertStringNotContainsString('fi-collapsible', $top);
    }

    /** En el móvil, un `<select>` nativo con las siete pestañas en su orden, y el filtro tras una píldora que lo dice. */
    public function test_on_the_phone_a_native_select_and_the_filter_pill(): void
    {
        $html = (string) $this->actingAs($this->admin())->get(AnalyticsPage::getUrl([AnalyticsPage::TAB_QUERY_KEY => 'money']))->assertOk()->getContent();

        $this->assertStringContainsString('data-analytics-tab-select', $html);
        $this->assertMatchesRegularExpression('/<select[^>]*wire:model\.live="tab"/', $html);
        preg_match('/<select[^>]*id="analitica-pestana".*?<\/select>/s', $html, $select);
        preg_match_all('/<option value="([a-z]+)"/', $select[0] ?? '', $options);
        $this->assertSame(['summary', 'money', 'occupancy', 'customers', 'marketing', 'parties', 'satisfaction'], $options[1]);

        $this->assertStringContainsString('data-analytics-filter-pill', $html);
        $this->assertStringContainsString('Este mes · frente al periodo anterior', $html);
        $this->assertStringContainsString('aria-controls="analitica-filtros"', $html);
        $this->assertStringContainsString('id="analitica-filtros"', $html);
        // La fila de pestañas se esconde en el móvil (allí se cortaba: 3 de 6 a la vista).
        $this->assertStringContainsString('class="max-md:[&>.fi-tabs]:hidden fi-sc-tabs"', $html);
    }

    /** @return list<Stat> */
    private static function stats(MetricsWidget $widget): array
    {
        return (new \ReflectionMethod($widget, 'getStats'))->invoke($widget);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $user;
    }
}
