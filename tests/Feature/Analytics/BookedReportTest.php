<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OccupancyReader;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Setting;
use App\Filament\Analytics\BookedReport;
use App\Filament\Analytics\Changes;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Metrics\BookedMetrics;
use App\Filament\Analytics\Metrics\CustomersMetrics;
use App\Filament\Analytics\Metrics\MarketingMetrics;
use App\Filament\Analytics\Metrics\MoneyMetrics;
use App\Filament\Analytics\Metrics\OccupancyMetrics;
use App\Filament\Analytics\Metrics\PartiesMetrics;
use App\Filament\Analytics\Metrics\SurveysMetrics;
use App\Filament\Widgets\Analytics\BookedChart;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **La cartera** (T4 de `specs/analitica-para-decidir.md` §4.8.quater), con fechas fijas: lo ya vendido para lo que viene y
 * «a estas alturas» —lo cobrado ANTES del instante y no cancelado antes de él—; los complementos con su visita; un pedido
 * cancelado sin fecha, fuera; la foto de ahora es la de `paidLines()`; la referencia, el año con −364 días o la media de cuatro
 * semanas; una foto de antes de que el sistema midiera no vale; y las semanas, también con el cambio de hora.
 *
 * El reloj: el martes 20 de octubre de 2026 a las 12:00 en Madrid (las 10:00 UTC); el domingo 25 cambia la hora.
 */
class BookedReportTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Europe/Madrid';

    private Zone $zone;

    private TicketType $entry;

    private TicketType $extra;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => self::TZ, 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
        Cache::flush();
        app()->setLocale('es');

        $this->zone = Zone::create(['slug' => 'z-'.Str::lower(Str::random(5)), 'name' => ['es' => 'Zona']]);
        $this->entry = TicketType::create(['name' => ['es' => 'Salto libre'], 'zone_id' => $this->zone->id, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1]);
        $this->extra = TicketType::create(['name' => ['es' => 'Calcetines'], 'zone_id' => $this->zone->id, 'type' => TicketType::TYPE_ADDON, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 0, 'position' => 2]);
        $this->user = User::factory()->create();
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-20 10:00:00', 'UTC');
    }

    // ─── La foto ────────────────────────────────────────────────────────────────

    /** Una línea cuenta en el instante *s* si se cobró antes y no se había cancelado antes; la ventana es del día de *s*. */
    public function test_a_snapshot_counts_what_was_paid_and_not_yet_cancelled_at_that_instant(): void
    {
        $line = static fn (string $date, string $paid, ?string $cancelled = null, int $seats = 2, bool $principal = true): array => [
            'date' => $date, 'principal' => $principal, 'seats' => $principal ? $seats : 0, 'cents' => 1000, 'paid_at' => $paid, 'cancelled_at' => $cancelled,
        ];
        $lines = [
            $line('2026-10-22', '2026-10-10 09:00:00'),                           // cobrada antes de las dos fotos
            $line('2026-10-22', '2026-10-15 09:00:00'),                           // cobrada DESPUÉS de la del 13
            $line('2026-10-16', '2026-10-01 09:00:00', '2026-10-14 09:00:00'),    // cancelada después del 13: contaba
            $line('2026-10-16', '2026-10-01 09:00:00', '2026-10-12 09:00:00'),    // cancelada antes del 13: no
            $line('2026-10-22', '2026-10-10 09:00:00', null, 0, false),           // un complemento: euros, sin plazas
            $line('2026-11-30', '2026-10-10 09:00:00'),                           // fuera de los 7 días
        ];

        $thirteenth = BookedReport::snapshot($lines, CarbonImmutable::parse('2026-10-13 10:00:00', 'UTC'), self::TZ, 0, 6);
        $this->assertSame(['seats' => 2, 'cents' => 1000, 'lines' => 1], $thirteenth, 'del 13 al 19: la cancelada después, sí; la del 22, fuera de la ventana');

        $now = BookedReport::snapshot($lines, $this->now(), self::TZ, 0, 6);
        $this->assertSame(['seats' => 4, 'cents' => 3000, 'lines' => 2], $now, 'del 20 al 26: las dos del 22 y su complemento');

        // El borde: la del 22 cobrada el 15 a las 09:00:00 cuenta en la foto de ese mismo segundo y no en la del anterior.
        $this->assertSame(4, BookedReport::snapshot($lines, CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'), self::TZ, 7, 7)['seats']);
        $this->assertSame(2, BookedReport::snapshot($lines, CarbonImmutable::parse('2026-10-15 08:59:59', 'UTC'), self::TZ, 7, 7)['seats']);
    }

    // ─── El lector ──────────────────────────────────────────────────────────────

    public function test_the_reader_brings_addons_with_their_visit_and_leaves_out_an_undated_cancellation(): void
    {
        $slot = $this->slot('2026-10-22');
        $order = $this->order('2026-10-10 09:00:00');
        $principal = $this->line($order, $slot, seats: 3, price: 1500);
        $this->line($order, null, seats: 0, price: 300, type: $this->extra, parent: $principal);                                // sin franja: la de su principal
        $this->line($order, null, seats: 0, price: 200, type: $this->extra, parent: $principal, cancelled: '2026-10-11 09:00:00');

        $cancelled = $this->order('2026-10-09 09:00:00', Order::STATUS_CANCELLED);
        $this->line($cancelled, $slot, seats: 5, price: 1000, cancelled: '2026-10-12 09:00:00');                               // con fecha: contaba hasta el 12
        $legacy = $this->order('2026-10-09 09:00:00', Order::STATUS_CANCELLED);
        $this->line($legacy, $slot, seats: 7, price: 1000);                                                                    // SIN fecha: nunca
        $pending = $this->order(null, Order::STATUS_PENDING);
        $this->line($pending, $slot, seats: 9, price: 1000);

        $lines = app(OccupancyReader::class)->bookedLines('2026-10-20', '2026-10-31');

        $this->assertCount(4, $lines, 'la principal, sus dos complementos y la del pedido cancelado con fecha');
        $this->assertSame(['2026-10-22'], array_values(array_unique(array_column($lines, 'date'))), 'el complemento cuelga de la visita de su principal');
        $this->assertSame([3, 0, 0, 5], array_column($lines, 'seats'));
        $this->assertSame([4500, 300, 200, 5000], array_column($lines, 'cents'));
        $this->assertSame([null, null, '2026-10-11 09:00:00', '2026-10-12 09:00:00'], array_column($lines, 'cancelled_at'));
    }

    /** El complemento de una línea cancelada cae con ella, aunque él no tenga fecha propia. */
    public function test_an_addon_falls_with_its_cancelled_principal(): void
    {
        $order = $this->order('2026-10-10 09:00:00');
        $principal = $this->line($order, $this->slot('2026-10-22'), seats: 2, price: 1000, cancelled: '2026-10-15 09:00:00');
        $this->line($order, null, seats: 0, price: 300, type: $this->extra, parent: $principal);

        $lines = app(OccupancyReader::class)->bookedLines('2026-10-20', '2026-10-31');

        $this->assertSame(['2026-10-15 09:00:00', '2026-10-15 09:00:00'], array_column($lines, 'cancelled_at'));
    }

    /** La foto de AHORA es lo que la ocupación cuenta como vendido (`paidLines()`): las plazas de cada horizonte. */
    public function test_the_cartera_of_now_is_the_paid_lines(): void
    {
        foreach (['2026-10-20', '2026-10-24', '2026-11-05', '2026-12-20', '2027-02-01'] as $i => $date) {
            $order = $this->order('2026-10-0'.($i + 1).' 09:00:00');
            $this->line($order, $this->slot($date), seats: $i + 2, price: 1000);
        }
        $gone = $this->order('2026-10-02 09:00:00');
        $this->line($gone, $this->slot('2026-10-23'), seats: 4, price: 1000, cancelled: '2026-10-18 09:00:00');

        $r = (new BookedReport)->compute($this->now());

        foreach (BookedReport::HORIZONS as $days) {
            $to = CarbonImmutable::parse('2026-10-20')->addDays($days - 1)->toDateString();
            $paid = array_sum(array_column(app(OccupancyReader::class)->paidLines('2026-10-20', $to), 'seats'));
            $this->assertSame($paid, $r['horizons'][$days]['seats'], "{$days} días");
        }
        $this->assertSame([5, 9, 14], [$r['horizons'][7]['seats'], $r['horizons'][30]['seats'], $r['horizons'][90]['seats']]);
    }

    // ─── La referencia ──────────────────────────────────────────────────────────

    /**
     * Sin año anterior, la media de las fotos de hace 1, 2, 3 y 4 semanas al mismo horizonte; con él, la foto de hace 364 días
     * (el martes 21 de octubre de 2025: el mismo día de la semana).
     */
    public function test_the_reference_is_a_year_ago_with_364_days_or_the_mean_of_four_weeks(): void
    {
        $this->oldOrder('2026-05-01 09:00:00');
        // Una visita cada semana, cobrada 5 días antes: en cada foto semanal, la de su semana ya está vendida.
        foreach ([0, 7, 14, 21, 28] as $back) {
            $visit = CarbonImmutable::parse('2026-10-22')->subDays($back);
            $order = $this->order($visit->subDays(5)->format('Y-m-d').' 09:00:00');
            $this->line($order, $this->slot($visit->toDateString()), seats: 10 + $back, price: 1000);
        }

        $weeks = (new BookedReport)->compute($this->now());
        $this->assertSame(['kind' => BookedReport::BASELINE_WEEKS, 'n' => 4], $weeks['baseline']);
        $this->assertSame(10, $weeks['horizons'][7]['seats']);
        $this->assertEqualsWithDelta((17 + 24 + 31 + 38) / 4, $weeks['horizons'][7]['baseline']['seats'], 0.001, 'la media de las cuatro semanas');

        // Con pedidos desde hace más de un año: la foto de hace 364 días.
        $this->oldOrder('2025-06-01 09:00:00');
        $year = $this->order('2025-10-16 09:00:00');
        $this->line($year, $this->slot('2025-10-23'), seats: 6, price: 2000);       // el jueves de aquella semana: dentro
        $late = $this->order('2025-10-22 09:00:00');
        $this->line($late, $this->slot('2025-10-24'), seats: 50, price: 2000);      // cobrada DESPUÉS de aquel martes: fuera
        $monday = $this->order('2025-10-15 09:00:00');
        $this->line($monday, $this->slot('2025-10-20'), seats: 3, price: 2000);     // el LUNES de antes: con 365 días entraría
        Cache::flush();

        $r = (new BookedReport)->compute($this->now());
        $this->assertSame(['kind' => BookedReport::BASELINE_YEAR, 'n' => 1], $r['baseline']);
        $this->assertSame(['seats' => 6.0, 'cents' => 12000.0], $r['horizons'][7]['baseline'], 'del martes 21 al lunes 27 de octubre de 2025');
    }

    /**
     * El margen sale de la ANTELACIÓN (el p95 del último año), no es fijo: comprando con 20 días, una foto necesita 20 días de
     * pedidos detrás. Pedidos desde el 10 de septiembre: valen las fotos del 13 y del 6 de octubre, no la del 29 de septiembre.
     */
    public function test_the_margin_is_the_p95_of_the_lead_time(): void
    {
        foreach (['2026-09-10', '2026-09-24', '2026-10-08'] as $paid) {
            $this->line($this->order($paid.' 09:00:00'), $this->slot(CarbonImmutable::parse($paid)->addDays(20)->toDateString()), seats: 2, price: 1000);
        }

        $r = (new BookedReport)->compute($this->now());

        $this->assertSame(20, $r['margin_days']);
        $this->assertSame(['kind' => BookedReport::BASELINE_WEEKS, 'n' => 2], $r['baseline']);
    }

    /** Una foto de antes de que el sistema midiera (menos la antelación) no vale: ni como referencia ni como historia. */
    public function test_a_snapshot_before_the_system_measured_does_not_count(): void
    {
        // Pedidos desde el 5 de octubre; la antelación, siempre 2 días → el margen es el mínimo, 7.
        foreach (['2026-10-05', '2026-10-12', '2026-10-19'] as $paid) {
            $order = $this->order($paid.' 09:00:00');
            $this->line($order, $this->slot(CarbonImmutable::parse($paid)->addDays(2)->toDateString()), seats: 3, price: 1000);
        }

        $r = (new BookedReport)->compute($this->now());

        $this->assertSame(BookedReport::MIN_MARGIN_DAYS, $r['margin_days']);
        $this->assertSame(['kind' => BookedReport::BASELINE_WEEKS, 'n' => 1], $r['baseline'], 'solo vale la del 13 (el 13 menos 7 es el 6: ya se medía)');
        $this->assertCount(1, $r['horizons'][7]['history']['seats']);

        $metric = BookedMetrics::from($r)['booked.seats_7'];
        $this->assertSame(Metric::VERDICT_NO_HISTORY, $metric->verdict()['state'] ?? null, 'con una semana no se dice si es normal');
    }

    // ─── Las semanas ────────────────────────────────────────────────────────────

    /** Esta semana desde hoy y las 12 siguientes, de lunes a domingo, también la del cambio de hora. */
    public function test_each_week_goes_from_monday_to_sunday_from_today_across_the_time_change(): void
    {
        $this->line($this->order('2026-10-10 09:00:00'), $this->slot('2026-10-25'), seats: 4, price: 1000);   // el domingo del cambio
        $this->line($this->order('2026-10-10 09:00:00'), $this->slot('2026-10-26'), seats: 6, price: 1000);   // el lunes siguiente

        $weeks = (new BookedReport)->compute($this->now())['weeks'];

        $this->assertCount(BookedReport::WEEKS, $weeks);
        $this->assertSame(['2026-10-20', '2026-10-25'], [$weeks[0]['from'], $weeks[0]['to']], 'la de hoy, desde hoy');
        $this->assertSame(['2026-10-26', '2026-11-01'], [$weeks[1]['from'], $weeks[1]['to']], 'la siguiente entera, pese al cambio de hora');
        $this->assertSame(['2027-01-11', '2027-01-17'], [$weeks[12]['from'], $weeks[12]['to']]);
        $this->assertSame([4, 6], [$weeks[0]['seats'], $weeks[1]['seats']]);
        $this->assertSame('20 oct. – 25 oct.', BookedChart::weekLabel($weeks[0]), 'con el formato de fechas del panel («Del 21 al 27 sep.»)');

        // Y en primavera, cuando la noche del cambio dura 23 horas: los días se cuentan en el calendario del parque.
        $spring = (new BookedReport)->compute(CarbonImmutable::parse('2027-03-23 10:00:00', 'UTC'))['weeks'];
        $this->assertSame(['2027-03-29', '2027-04-04'], [$spring[1]['from'], $spring[1]['to']], 'la semana tras el cambio del 28 de marzo');
    }

    /** El gráfico: una barra por semana con lo vendido ya y, cuando hay referencia, otra con «a estas alturas». */
    public function test_the_chart_has_a_bar_per_week_and_the_reference_when_there_is_one(): void
    {
        $this->oldOrder('2026-05-01 09:00:00');
        $this->line($this->order('2026-10-12 09:00:00'), $this->slot('2026-10-22'), seats: 4, price: 1000);

        $categories = (new \ReflectionMethod(BookedChart::class, 'categories'))->invoke(new BookedChart);

        $this->assertCount(BookedReport::WEEKS, $categories['labels']);
        $this->assertSame(['Vendido ya', 'A estas alturas'], array_column($categories['datasets'], 'label'));
        $this->assertSame(4, $categories['datasets'][0]['data'][0]);
    }

    /** La cartera entra en «lo que ha cambiado» como las demás cifras (su historia son las fotos semanales). */
    public function test_the_cartera_is_judged_with_the_rest_in_what_has_changed(): void
    {
        $this->oldOrder('2026-05-01 09:00:00');
        $window = ReportPeriod::fromValue('this_month')->window();
        $six = MoneyMetrics::for($window, Comparison::Previous) + OccupancyMetrics::for($window, Comparison::Previous)
            + CustomersMetrics::for($window, Comparison::Previous) + MarketingMetrics::for($window, Comparison::Previous)
            + PartiesMetrics::for($window, Comparison::Previous) + SurveysMetrics::for($window, Comparison::Previous);

        $booked = Changes::select(BookedMetrics::for())['judged'];

        $this->assertSame(6, $booked, 'las seis, con doce semanas de historia');
        $this->assertSame(Changes::select($six)['judged'] + $booked, Changes::for($window, Comparison::Previous)['judged']);
    }

    // ─── Lo que dice la tarjeta ─────────────────────────────────────────────────

    public function test_each_figure_says_the_other_measure_and_the_reference_without_colour(): void
    {
        $report = [
            'margin_days' => 10,
            'baseline' => ['kind' => BookedReport::BASELINE_WEEKS, 'n' => 4],
            'horizons' => array_fill_keys(BookedReport::HORIZONS, [
                'seats' => 148, 'cents' => 222445, 'lines' => 23, 'baseline' => ['seats' => 140.5, 'cents' => 235449.5],
                'history' => ['seats' => [170, 149, 122, 121, 148, 158, 231, 207], 'cents' => [1, 2, 3, 4, 5, 6, 7, 8]],
            ]),
            'weeks' => [],
        ];

        $m = BookedMetrics::from($report);

        $this->assertSame(BookedMetrics::KEYS, array_keys($m), 'euros y después plazas, a 7, 30 y 90 días');
        $this->assertSame('Vendido para los próximos 30 días', $m['booked.cents_30']->label);
        $this->assertSame('2.224 €', $m['booked.cents_30']->displayValue());
        // 2.354,495 € de media: 235.450 céntimos redondeados, que la tarjeta escribe sin céntimos (2.355 €).
        $this->assertSame("148 plazas · a estas alturas, de media en las 4 semanas anteriores: 2.355 € (−6\u{00A0}%)", $m['booked.cents_30']->detail);
        $this->assertSame("2.224 € · a estas alturas, de media en las 4 semanas anteriores: 141 (+5\u{00A0}%)", $m['booked.seats_30']->detail);
        $this->assertNull($m['booked.cents_30']->reading(Comparison::Previous)['line'], 'sin línea de cambio ni color: las fotos se solapan');
        $this->assertSame(Metric::VERDICT_NORMAL, $m['booked.seats_30']->verdict()['state'], '148 cae entre 121 y 231');

        $report['baseline'] = ['kind' => BookedReport::BASELINE_YEAR, 'n' => 1];
        $this->assertStringEndsWith("a estas alturas hace un año: 2.355 € (−6\u{00A0}%)", (string) BookedMetrics::from($report)['booked.cents_30']->detail);
        $report['baseline'] = ['kind' => null, 'n' => 0];
        $this->assertStringEndsWith('aún sin con qué comparar a estas alturas', (string) BookedMetrics::from($report)['booked.cents_30']->detail);
    }

    // ─── El fixture ─────────────────────────────────────────────────────────────

    private function slot(string $date): Slot
    {
        return Slot::firstOrCreate(
            ['zone_id' => $this->zone->id, 'date' => $date, 'start_time' => '10:00:00'],
            ['end_time' => '11:00:00', 'capacity' => 100, 'online_capacity' => 100],
        );
    }

    private function order(?string $paidAt, string $status = Order::STATUS_PAID): Order
    {
        return Order::create([
            'user_id' => $this->user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => $status,
            'paid_at' => $paidAt === null ? null : Carbon::parse($paidAt), 'total' => 1000, 'expires_at' => now()->addMinutes(30),
        ]);
    }

    /** Un pedido viejo, lejos de toda ventana: marca desde cuándo mide el sistema. */
    private function oldOrder(string $paidAt): void
    {
        $this->line($this->order($paidAt), $this->slot(CarbonImmutable::parse($paidAt)->addDays(3)->toDateString()), seats: 1, price: 100);
    }

    private function line(Order $order, ?Slot $slot, int $seats, int $price, ?TicketType $type = null, ?OrderItem $parent = null, ?string $cancelled = null): OrderItem
    {
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => ($type ?? $this->entry)->id, 'slot_id' => $slot?->id,
            'quantity' => max(1, $seats), 'seats' => $seats, 'unit_price' => $price, 'parent_item_id' => $parent?->id,
        ]);
        if ($cancelled !== null) {
            $item->forceFill(['cancelled_at' => Carbon::parse($cancelled)])->save();
        }

        return $item;
    }
}
