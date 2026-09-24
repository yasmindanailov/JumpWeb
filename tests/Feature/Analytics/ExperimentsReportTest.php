<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Models\Experiment;
use App\Domain\Platform\Services\Analytics\AttributionContext;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Filament\Analytics\ExperimentsReport;
use App\Filament\Widgets\Analytics\ExperimentsWidget;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La conversión por variante** (`specs/analitica.md` §4.4, T5b): cada regla con su caso y su CONTROL. La exposición
 * cuenta a un visitante UNA vez por variante; la compra vale si el sello del pedido lleva ese visitante y se pagó
 * DESPUÉS de la primera exposición; un bot no cuenta; quien vio dos variantes es contaminado; y el intervalo es el de
 * Wilson, comprobado contra el cálculo de libro.
 */
class ExperimentsReportTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Europe/Madrid';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-24 12:00:00', self::TZ));
        Cache::flush();
    }

    private function today(): Window
    {
        $day = CarbonImmutable::parse('2026-09-24', self::TZ);

        return Window::ofDays($day, $day, self::TZ);
    }

    /** Una sesión limpia del libro (`session()` es de `TestCase`: el nombre está cogido). */
    private function visit(?string $visitor = null, bool $bot = false): AnalyticsSession
    {
        return AnalyticsSession::create([
            'visitor_id' => $visitor ?? Visitor::mint(),
            'started_at' => now()->subHours(3),
            'last_seen_at' => now()->subHours(2),
            'surface' => 'web',
            'is_bot' => $bot,
            'is_internal' => false,
        ]);
    }

    private function exposure(AnalyticsSession $session, string $key, string $variant, ?Carbon $at = null, ?int $userId = null): void
    {
        $at ??= now()->subHours(2);

        AnalyticsEvent::create([
            'event_id' => Visitor::mint(),
            'session_id' => $session->id,
            'visitor_id' => $session->visitor_id,
            'user_id' => $userId,
            'name' => 'experiment_exposed',
            'route' => '/',
            'props' => ['key' => $key, 'variant' => $variant],
            'occurred_at' => $at,
            'received_at' => $at,
        ]);
    }

    /** Un pedido cobrado por la web; con `$sealed`, su sello lleva el visitante (la categoría «análisis» o «anuncios»). */
    private function purchase(string $visitor, Carbon $paidAt, bool $sealed = true): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
            'paid_at' => $paidAt,
            'total' => 3000,
            'expires_at' => now()->addMinutes(30),
        ]);
        $order->forceFill([
            'attribution_channel' => AttributionContext::CHANNEL_WEB,
            'attribution' => $sealed ? ['visitor_id' => $visitor] : [],
        ])->saveQuietly();

        return $order;
    }

    private function shell(): Experiment
    {
        return Experiment::create(['key' => 'shell', 'name' => 'Cajón o isla', 'active' => true, 'variants' => [['key' => 'cajon', 'weight' => 1], ['key' => 'isla', 'weight' => 1]]]);
    }

    public function test_the_wilson_interval_is_the_textbook_one(): void
    {
        // 2 de 20: 10 % a pelo; Wilson al 95 % dice 2,8 %–30,1 %.
        $this->assertSame(1000, ExperimentsReport::wilson(2, 20)['rate_bp']);
        $this->assertEqualsWithDelta(279, ExperimentsReport::wilson(2, 20)['low_bp'], 2);
        $this->assertEqualsWithDelta(3010, ExperimentsReport::wilson(2, 20)['high_bp'], 2);

        $this->assertSame(['rate_bp' => 0, 'low_bp' => 0, 'high_bp' => 0], ExperimentsReport::wilson(0, 0), 'sin expuestos, todo a cero');
        $this->assertSame(10000, ExperimentsReport::wilson(20, 20)['high_bp'], 'el techo es el 100 %');
        $this->assertEqualsWithDelta(8389, ExperimentsReport::wilson(20, 20)['low_bp'], 2);
        $this->assertSame(0, ExperimentsReport::wilson(0, 20)['low_bp'], 'el suelo es el 0 %');
    }

    public function test_exposed_visitors_count_once_and_a_conversion_needs_a_sealed_order_after_the_exposure(): void
    {
        $this->shell();

        $bought = $this->visit();                                   // isla, expuesto dos veces, compró después: convierte
        $this->exposure($bought, 'shell', 'isla');
        $this->exposure($bought, 'shell', 'isla', now()->subHour());
        $this->purchase($bought->visitor_id, now()->subMinutes(30));

        $before = $this->visit();                                   // isla, compró ANTES de verla: no convierte
        $this->exposure($before, 'shell', 'isla');
        $this->purchase($before->visitor_id, now()->subHours(5));

        $unsealed = $this->visit();                                 // cajon, compró sin consentir: no se puede atar
        $this->exposure($unsealed, 'shell', 'cajon');
        $this->purchase($unsealed->visitor_id, now()->subMinutes(30), sealed: false);

        $looked = $this->visit();                                   // cajon, no compró
        $this->exposure($looked, 'shell', 'cajon');

        $bot = $this->visit(bot: true);                             // un bot no cuenta
        $this->exposure($bot, 'shell', 'isla');
        $this->exposure($this->visit(), 'otro', 'b');               // otro experimento, sin fila en la tabla

        $report = (new ExperimentsReport)->compute($this->today());

        $this->assertSame(['otro', 'shell'], array_column($report['experiments'], 'key'), 'por clave; el que no tiene fila se llama como su clave');
        $this->assertSame('otro', $report['experiments'][0]['name']);

        $shell = $report['experiments'][1];
        $this->assertSame('Cajón o isla', $shell['name']);
        $this->assertSame(0, $shell['contaminated_visitors']);
        $this->assertSame([
            ['variant' => 'cajon', 'exposed' => 2, 'converted' => 0] + ExperimentsReport::wilson(0, 2),
            ['variant' => 'isla', 'exposed' => 2, 'converted' => 1] + ExperimentsReport::wilson(1, 2),
        ], $shell['variants']);
    }

    public function test_a_visitor_with_two_variants_is_contaminated_and_a_user_with_two_variants_is_counted(): void
    {
        $this->shell();

        $torn = $this->visit();                                     // vio las dos: fuera de ambas
        $this->exposure($torn, 'shell', 'isla');
        $this->exposure($torn, 'shell', 'cajon');
        $this->purchase($torn->visitor_id, now()->subMinutes(30));

        $phone = $this->visit();                                    // la misma CUENTA en dos dispositivos
        $laptop = $this->visit();
        $this->exposure($phone, 'shell', 'isla', userId: 7);
        $this->exposure($laptop, 'shell', 'cajon', userId: 7);
        $this->exposure($this->visit(), 'shell', 'isla', userId: 8); // una cuenta con una sola variante: limpia

        $shell = (new ExperimentsReport)->compute($this->today())['experiments'][0];

        $this->assertSame(1, $shell['contaminated_visitors']);
        $this->assertSame(1, $shell['contaminated_users']);
        $this->assertSame([['cajon', 1, 0], ['isla', 2, 0]], array_map(static fn (array $v): array => [$v['variant'], $v['exposed'], $v['converted']], $shell['variants']), 'el contaminado no cuenta en ninguna');
    }

    public function test_only_the_window_counts_and_the_report_is_cached_per_window(): void
    {
        $this->shell();
        $this->exposure($this->visit(), 'shell', 'isla', now()->subDays(2));

        $this->assertSame([], ExperimentsReport::for($this->today())['experiments'], 'una exposición de anteayer no es de hoy');

        $this->exposure($this->visit(), 'shell', 'isla');
        $this->assertSame([], ExperimentsReport::for($this->today())['experiments'], 'cinco minutos de caché por ventana');
        $this->assertCount(1, (new ExperimentsReport)->compute($this->today())['experiments']);
    }

    public function test_the_widget_paints_each_experiment_with_its_interval(): void
    {
        $this->shell();
        $seen = $this->visit();
        $this->exposure($seen, 'shell', 'isla');
        $this->purchase($seen->visitor_id, now()->subMinutes(30));

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        Livewire::actingAs($admin)
            ->test(ExperimentsWidget::class)
            ->assertSee('Cajón o isla')
            ->assertSee('isla')
            ->assertSee('100,0');
    }
}
