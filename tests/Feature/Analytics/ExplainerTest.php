<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Analytics\Contract;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\Changes;
use App\Filament\Analytics\Explainer;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Metrics\CustomersMetrics;
use App\Filament\Analytics\Metrics\MarketingMetrics;
use App\Filament\Analytics\Metrics\MoneyMetrics;
use App\Filament\Analytics\Metrics\OccupancyMetrics;
use App\Filament\Analytics\Metrics\PartiesMetrics;
use App\Filament\Analytics\Metrics\SurveysMetrics;
use App\Filament\Analytics\Polarity;
use App\Filament\Pages\AnalyticsPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **«Explícamelo con IA»** (T3d de `specs/analitica-para-decidir.md` §4.7 y §4.13): el texto dice lo que pide la spec —las
 * instrucciones, el negocio sin su nombre, el periodo, lo que ha cambiado, cada cifra con su cambio, su referencia y su
 * definición, y cuánto fiarse—, en el idioma del panel; **nada de una persona sale en él** (`#793`) y, si algo con pinta de
 * correo o de teléfono llegara, NO se enseña; cabe en {@see Explainer::MAX_BYTES} en el peor caso; y el botón es de quien
 * puede descargar el CSV y deja su rastro, sin PII.
 */
class ExplainerTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que NUNCA puede salir en el texto: está en la base, en pedidos y encuestas del periodo. */
    private const PII = [
        'Zoraida Quintanilla', 'zoraida.q@correo-privado.test', '611222333', '611 222 333',
        'Me encantó el tobogán azul', 'Bernardino Ulloa', 'b.ulloa@correo-privado.test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-07-01 10:00:00'));
        Cache::flush();
        app()->setLocale('es');
    }

    private function june(): Window
    {
        return ReportPeriod::Custom->window('2026-06-01', '2026-06-30');
    }

    // ─── Lo que dice ────────────────────────────────────────────────────────────

    public function test_the_text_carries_what_the_spec_asks_in_the_panel_language(): void
    {
        $how = static fn (string $key): string => __('admin.analytics.how.'.$key);
        $weeks = [900_00, 1_000_00, 1_100_00, 1_200_00, 1_300_00, 1_400_00, 1_000_00, 1_100_00];
        $metrics = [
            // Arriba y fuera de lo normal: en «lo que ha cambiado» y en su tabla.
            'money.net' => Metric::money('money.net', 'Ingresos netos', 1_500_00, 1_000_00, 30, 25, Polarity::UpIsGood, $how('money.net'))->withHistory($weeks, 'week'),
            'money.orders' => Metric::count('money.orders', 'Pedidos cobrados', 30, 25, Polarity::UpIsGood, $how('money.orders'))->withHistory([20, 22, 24, 26, 28, 30, 31, 25], 'week'),
            // Plegada y fuera de lo normal, con escala: entra en la tabla de su pestaña.
            'money.collected' => Metric::money('money.collected', 'Cobrado', 3_000_00, 1_000_00, 40, 30, Polarity::UpIsGood, $how('money.collected'))->withHistory($weeks, 'week'),
            // Plegada y normal: solo se cuenta.
            'customers.repeat' => Metric::count('customers.repeat', 'Vinieron dos o más días', 3, 3, Polarity::UpIsGood, $how('customers.repeat')),
            'traffic.identified' => Metric::text('traffic.identified', 'Visitas identificadas', '12', $how('traffic.identified')),
        ];
        $top = ['money' => ['money.net', 'money.orders']];

        $text = Explainer::compose($metrics, $top, $this->june(), Comparison::Previous, [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK], ['days' => 7, 'total' => 0])['text'];

        $this->assertStringContainsString('## Lo que te pido', $text);
        $this->assertStringContainsString('Tres cosas que van bien y tres que vigilar', $text);
        $this->assertStringContainsString('no inventes cifras ni causas', $text);
        $this->assertStringContainsString('Responde en español.', $text);
        $this->assertStringContainsString('Vende entradas por franja horaria, fiestas de cumpleaños (packs).', $text, 'el negocio, desde lo que vende; sin su nombre');
        $this->assertStringContainsString('Del 1 al 30 jun. 2026, comparado con el periodo anterior (del 2 al 31 may. 2026).', $text);
        $this->assertStringContainsString('- Ingresos netos: 1.500 €, por encima de lo normal: 900 €–1.400 € (8).', $text);
        $this->assertStringContainsString('| Cifra | Valor | Cambio | Normal |', $text);
        $this->assertStringContainsString('| Pedidos cobrados | 30 | +5 (+20 %), puede ser azar | normal: 20–31 (8) |', $text, 'con base y sin prueba clara: la de la tarjeta');
        $this->assertStringContainsString('| Cobrado | 3.000 € |', $text, 'la plegada que se sale de lo normal, en la tabla de su pestaña');
        $this->assertStringContainsString('Otra cifra no va aquí: está plegada en el panel.', $text, 'la plegada normal se cuenta, no se calla');
        $this->assertStringNotContainsString('Vinieron dos o más días', $text);
        $this->assertStringContainsString('- **Ingresos netos**: Lo cobrado menos lo devuelto en el periodo: el dinero que de verdad entró.', $text, 'la definición es la primera frase de «¿Cómo se calcula?»');
        $this->assertStringNotContainsString('Sale de las mismas filas', $text, 'y solo la primera');
        $this->assertStringContainsString('- Visitas identificadas: 12.', $text, 'la calidad del dato, en su sección');
        $this->assertStringContainsString('Aún no hay referencias del sector', $text);
        $this->assertStringNotContainsString('descartados', $text, 'sin descartes, no se dice');

        $withRejected = Explainer::compose($metrics, $top, $this->june(), Comparison::Previous, [], ['days' => 7, 'total' => 12])['text'];
        $this->assertStringContainsString('descartados por no ser válidos (últimos 7 días): 12.', $withRejected);
        $this->assertStringContainsString('Vende productos de ocio.', $withRejected);

        app()->setLocale('zh_CN');
        $zh = Explainer::compose($metrics, $top, $this->june(), Comparison::Previous, [TicketType::TYPE_ENTRY], ['days' => 7, 'total' => 0])['text'];
        $this->assertStringContainsString('请用中文回答', $zh, 'en el idioma del panel, y la IA contesta en él');
        $this->assertStringContainsString('| 数字 | 数值 | 变化 | 正常 |', $zh);
        $this->assertStringNotContainsString('Lo que te pido', $zh);
    }

    /**
     * El cambio se juzga COMO EN LA TARJETA, con la duración de cada ventana ({@see Comparison::share()}): julio (31 días) contra
     * junio (30). 61 frente a 41 es claro si las dos duraran lo mismo (z ≈ 1,98) y puede ser azar con la duración real (z ≈ 1,81).
     */
    public function test_the_change_is_judged_like_the_card_with_the_length_of_each_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 10:00:00'));
        $july = ReportPeriod::fromValue('last_month')->window();
        $share = Comparison::Previous->share($july);
        $this->assertEqualsWithDelta(31 / 61, $share, 0.001, 'julio contra junio');

        $visits = Metric::count('traffic.visits', 'Visitas a la web', 61, 41, Polarity::UpIsGood, __('admin.analytics.how.traffic.visits'));
        $text = Explainer::compose(['traffic.visits' => $visits], ['marketing' => ['traffic.visits']], $july, Comparison::Previous, [], ['days' => 7, 'total' => 0])['text'];

        $this->assertStringContainsString('| Visitas a la web | 61 | +20 (+49 %), puede ser azar |', $text);
        $this->assertContains(__('admin.analytics.metric.unclear'), $visits->reading(Comparison::Previous, $share)['notes'], 'y la tarjeta dice lo mismo');
    }

    // ─── Nada de una persona ────────────────────────────────────────────────────

    public function test_nothing_of_a_person_reaches_the_text_and_a_hidden_mean_says_it_is_hidden(): void
    {
        $this->personalFixture();

        foreach (['es', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            Cache::flush();
            $r = Explainer::for($this->june(), Comparison::Previous);

            $this->assertFalse($r['refused'], "{$locale}: un texto normal no dispara la guarda");
            $this->assertIsString($r['text']);
            foreach (self::PII as $pii) {
                $this->assertStringNotContainsString($pii, $r['text'], "{$locale}: «{$pii}» no puede salir");
            }
            $this->assertSame(0, preg_match(Contract::PII_VALUE_RE, $r['text']), "{$locale}: nada con pinta de correo o teléfono");
            $this->assertSame(strlen($r['text']), $r['bytes']);
            $this->assertLessThanOrEqual(Explainer::MAX_BYTES, $r['bytes']);
        }

        app()->setLocale('es');
        Cache::flush();
        $text = (string) Explainer::for($this->june(), Comparison::Previous)['text'];
        $this->assertStringContainsString('Vende entradas por franja horaria. Los importes', $text, 'solo lo activo y a la venta: el pack viejo, no');
        $this->assertStringContainsString('| Nota media | Sin nota |', $text, 'una media de 2 respuestas no se enseña, y no se lee como la nota');
        $this->assertStringContainsString('- **Nota media**: La media de la primera pregunta de escala (del 1 al 5) en las respuestas del periodo; con menos de 5 respuestas no se enseña («Sin nota»), porque son anónimas.', $text, 'y su definición dice por qué');
    }

    public function test_something_like_an_email_or_a_phone_is_never_shown_and_is_logged_without_it(): void
    {
        Log::spy();

        foreach (['Escríbenos a hola@parque.test', 'Llama al +34 611 222 333', 'Del 2026-06-01 - 2026-06-30'] as $leak) {
            $r = Explainer::guarded(['text' => "# Las cifras\n\n{$leak}\n", 'metrics' => 3]);
            $this->assertTrue($r['refused'], "«{$leak}»");
            $this->assertNull($r['text']);
        }
        Log::shouldHaveReceived('warning')->with('analytics.explain_refused', ['matches' => 1])->times(3);

        $fine = Explainer::guarded(['text' => "Del 21 al 27 sep. 2026 · 1.234.567 € · 12,3 % · 9.876 visitas · 2 de 8\n", 'metrics' => 1]);
        $this->assertFalse($fine['refused'], 'importes, porcentajes y fechas como las escribe el panel no son un teléfono');

        // Y el modal de un texto rechazado dice por qué, sin caja de texto que copiar.
        $html = view('filament.pages.analytics.explain', ['explanation' => ['text' => null, 'bytes' => 0, 'metrics' => 3, 'refused' => true]])->render();
        $this->assertStringContainsString(__('admin.analytics.explain.refused'), $html);
        $this->assertStringNotContainsString('data-explain-text', $html);
    }

    // ─── El tamaño ──────────────────────────────────────────────────────────────

    /**
     * El PEOR caso: las 58 cifras reales (sus rótulos y sus definiciones), cada una con valores largos, historia y rangos de
     * siete cifras; las de arriba, normales; y SEIS plegadas fuera de lo normal —cinco se nombran y una más se cuenta—.
     */
    public function test_the_worst_case_fits_in_the_ceiling_in_both_languages(): void
    {
        $sizes = [];
        foreach (['es', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            Cache::flush();
            $window = ReportPeriod::fromValue('last_month')->window();
            $real = MoneyMetrics::for($window, Comparison::Previous) + OccupancyMetrics::for($window, Comparison::Previous)
                + CustomersMetrics::for($window, Comparison::Previous) + MarketingMetrics::for($window, Comparison::Previous)
                + PartiesMetrics::for($window, Comparison::Previous) + SurveysMetrics::for($window, Comparison::Previous);
            $top = array_merge(...array_values(AnalyticsPage::topKeys()));
            $outside = 0;
            $metrics = [];
            foreach ($real as $key => $m) {
                $out = ! in_array($key, $top, true) && ! in_array($key, Explainer::QUALITY_KEYS, true) && $m->unit !== Metric::UNIT_TEXT && $outside < 6;
                $outside += $out ? 1 : 0;
                $metrics[$key] = self::loud($m, $out);
            }

            $bytes = strlen(Explainer::compose($metrics, AnalyticsPage::topKeys(), $window, Comparison::Previous, [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK, TicketType::TYPE_ADDON], ['days' => 7, 'total' => 12_345])['text']);

            $this->assertSame(1, Changes::select($metrics)['more'], 'cinco plegadas se nombran y la sexta se cuenta');
            $sizes[$locale] = $bytes;
        }

        $this->assertLessThanOrEqual(Explainer::MAX_BYTES, max($sizes), 'el peor caso, en bytes: '.json_encode($sizes));
    }

    // ─── El botón ───────────────────────────────────────────────────────────────

    public function test_the_button_is_for_who_can_export_and_leaves_a_trail_without_a_person(): void
    {
        $this->personalFixture();

        Livewire::actingAs($this->withPermissions(['reports.view']))->test(AnalyticsPage::class)->assertActionHidden('explain');
        Livewire::actingAs($this->withPermissions(['reports.view', 'reports.export']))->test(AnalyticsPage::class)->assertActionVisible('explain');

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);
        $page = Livewire::actingAs($admin)->test(AnalyticsPage::class)
            ->set('filters', ['period' => ReportPeriod::Custom->value, 'from' => '2026-06-01', 'to' => '2026-06-30', 'compare' => Comparison::Previous->value])
            ->mountAction('explain');

        // ⚠️ En Livewire 4 los modales de Filament son un `wire:partial` que el HTML de la prueba no trae (`WaiverProofActionTest`):
        // lo que pinta el modal se mira en su `modalContent`, renderizado.
        $content = $page->instance()->getMountedAction()?->getModalContent();
        $this->assertInstanceOf(View::class, $content);
        $html = $content->render();
        $this->assertStringContainsString('data-explain-text', $html);
        $this->assertStringContainsString('Lo que te pido', $html);
        $this->assertStringContainsString('Del 1 al 30 jun. 2026', $html, 'el periodo del filtro');
        $this->assertStringContainsString(__('admin.analytics.explain.copy'), $html);
        $this->assertStringNotContainsString('Zoraida', $html);

        $trail = AuditLog::query()->where('action', 'analytics.explained')->sole();
        $this->assertSame($admin->id, $trail->user_id);
        $this->assertSame(['period', 'from', 'to', 'compare', 'locale', 'bytes', 'metrics'], array_keys($trail->payload));
        $this->assertSame(['custom', '2026-06-01', '2026-06-30', 'previous', 'es'], array_slice(array_values($trail->payload), 0, 5));
        $this->assertGreaterThan(0, $trail->payload['bytes']);
        $this->assertStringNotContainsString('@', (string) json_encode($trail->payload));
    }

    // ─── El fixture ─────────────────────────────────────────────────────────────

    /** Dos clientes con pedidos cobrados en junio y una encuesta de DOS respuestas, una con texto libre. */
    private function personalFixture(): void
    {
        $zone = Zone::create(['slug' => 'z-'.Str::lower(Str::random(5)), 'name' => ['es' => 'Zona']]);
        $slot = Slot::create(['zone_id' => $zone->id, 'date' => '2026-06-15', 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 50, 'online_capacity' => 50]);
        $type = TicketType::create(['name' => ['es' => 'Salto libre'], 'zone_id' => $zone->id, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]);
        // Un pack que ya no se vende: el negocio se describe por lo que vende HOY.
        TicketType::create(['name' => ['es' => 'Cumple viejo'], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK, 'is_sellable' => true, 'is_active' => false, 'seats_per_unit' => 1, 'position' => 2]);

        foreach ([['Zoraida Quintanilla', 'zoraida.q@correo-privado.test', '+34611222333'], ['Bernardino Ulloa', 'b.ulloa@correo-privado.test', '611 222 333']] as $i => [$name, $email, $phone]) {
            $user = User::factory()->create(['name' => $name, 'email' => $email, 'phone' => $phone, 'marketing_opt_in' => true]);
            $order = Order::create(['user_id' => $user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => Order::STATUS_PAID, 'paid_at' => Carbon::parse('2026-06-1'.$i.' 10:00:00'), 'total' => 3000, 'expires_at' => now()->addMinutes(30)]);
            OrderItem::create(['order_id' => $order->id, 'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 3000]);
        }

        $survey = Survey::create([
            'key' => 'visita', 'name' => ['es' => 'Tu visita'], 'kind' => Survey::KIND_INTERNAL, 'active' => true,
            'questions' => [
                ['key' => 'nota', 'type' => 'scale', 'required' => true, 'label' => ['es' => 'Nota']],
                ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Algo más']],
            ],
        ]);
        foreach ([[5, 'Me encantó el tobogán azul'], [4, null]] as [$score, $comment]) {
            SurveyResponse::create(['survey_id' => $survey->id, 'channel' => 'internal', 'answered_on' => '2026-06-20', 'band' => 'morning', 'answers' => array_filter(['nota' => $score, 'comentario' => $comment])]);
        }
    }

    /** La misma cifra con valores largos y una historia de rangos de siete cifras; fuera de lo normal si `$out`. */
    private static function loud(Metric $m, bool $out): Metric
    {
        $history = [1_234_567_00, 9_876_543_00, 2_345_678_00, 8_765_432_00, 3_456_789_00, 7_654_321_00, 4_567_890_00, 6_543_210_00, 5_678_901_00, 1_111_111_00, 9_999_999_00, 5_555_555_00];

        return match ($m->unit) {
            Metric::UNIT_MONEY => Metric::money($m->key, $m->label, $out ? 12_345_678_00 : 5_432_109_87, 4_321_098_76, 999, 999, $m->polarity, $m->how, mean: $m->isMean)->withHistory($history, 'month'),
            Metric::UNIT_RATE => Metric::rate($m->key, $m->label, $out ? 9_999 : 4_321, 10_000, 3_210, 10_000, $m->polarity, $m->how)->withHistory($out ? [100, 200, 300, 400, 500, 600, 700, 800] : [1_234, 5_678, 2_345, 6_789, 3_456, 7_890, 4_567, 8_901], 'month'),
            Metric::UNIT_COUNT => Metric::count($m->key, $m->label, $out ? 12_345_678 : 5_432_109, 4_321_098, $m->polarity, $m->how)->withHistory(array_map(static fn (int $v): int => intdiv($v, 100), $history), 'month'),
            default => $m,
        };
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
