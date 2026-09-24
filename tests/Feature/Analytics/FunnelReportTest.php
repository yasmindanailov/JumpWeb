<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\EventIngestor;
use App\Domain\Platform\Services\Analytics\RejectedEvents;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Filament\Analytics\FunnelReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **El embudo y las fuentes, contra un libro sembrado** (`specs/analitica.md` §4.5 y §6, T2c; `#735`).
 *
 * El parque en `Europe/Madrid`, el reloj en el miércoles 2026-06-10 a las 09:00 UTC. Junio lleva ocho sesiones
 * limpias con el embudo repartido (dos compran, una de ellas a las 22:30 UTC del 9 = el 10 en el parque), un bot,
 * una interna, una de mayo; fuentes de Google (utm), de Google por `gclid` sin utm, de Instagram, directas y una
 * referida; dos pedidos web sellados con primer y último toque distintos, más uno del panel y uno anterior a la
 * medición que NO entran en el embudo; contactos; una sesión con fallo técnico; un lote con dos eventos
 * rechazados por la ingesta.
 */
class FunnelReportTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $jump;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        Cache::flush();

        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->jump = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    // ─── El fixture ─────────────────────────────────────────────────────────────────────────────

    private function seedJune(): void
    {
        // S1: Google cpc «verano», móvil, es: mira, abre el cajón, elige día, cesta, se identifica, paga y COMPRA
        // (a las 22:30 UTC del 9 = las 00:30 del 10 en Madrid). El pedido lleva primer toque instagram (de una
        // visita anterior) y último toque google.
        $s1 = $this->visit('2026-06-09 22:30:00', ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'verano', 'device' => 'mobile', 'locale' => 'es', 'entry_route' => '/', 'user_id' => User::factory()->create()->id]);
        $this->events($s1, ['page_viewed' => '/', 'drawer_opened' => null, 'product_chosen' => ['product' => (string) $this->jump->id], 'date_chosen' => null, 'line_added' => null, 'identified' => null, 'pay_started' => null, 'page_viewed:2' => '/gracias']);
        $this->webOrder(4000, '2026-06-09 22:35:00', ['instagram', 'social', 'reels'], ['google', 'cpc', 'verano']);

        // S2: Google por gclid sin utm, ordenador, en: llega a la cesta y se va (con un fallo técnico).
        $s2 = $this->visit('2026-06-05 10:00:00', ['click_ids' => ['gclid' => 'abc'], 'device' => 'desktop', 'locale' => 'en', 'entry_route' => '/precios']);
        $this->events($s2, ['page_viewed' => '/precios', 'drawer_opened' => null, 'product_chosen' => ['product' => (string) $this->jump->id], 'date_chosen' => null, 'line_added' => null, 'request_failed' => ['route' => '/availability', 'status' => 500, 'offline' => false]]);

        // S3: Instagram, móvil: abre el cajón y elige día, y se va.
        $s3 = $this->visit('2026-06-06 17:00:00', ['utm_source' => 'instagram', 'utm_medium' => 'social', 'utm_campaign' => 'reels', 'device' => 'mobile', 'locale' => 'es', 'entry_route' => '/']);
        $this->events($s3, ['page_viewed' => '/', 'drawer_opened' => null, 'date_chosen' => null, 'page_viewed:2' => '/servicios']);

        // S4: directa, móvil: solo mira y toca «Llamar».
        $s4 = $this->visit('2026-06-07 12:00:00', ['device' => 'mobile', 'locale' => 'es', 'entry_route' => '/']);
        $this->events($s4, ['page_viewed' => '/', 'call_clicked' => null]);

        // S5: directa, tableta, fr: solo mira.
        $s5 = $this->visit('2026-06-07 12:30:00', ['device' => 'tablet', 'locale' => 'fr', 'entry_route' => '/normas']);
        $this->events($s5, ['page_viewed' => '/normas']);

        // S6: referida desde un blog, ordenador: abre el cajón, cesta, se identifica, paga y compra (último toque = la misma).
        $s6 = $this->visit('2026-06-08 18:00:00', ['referrer_host' => 'blog.example', 'device' => 'desktop', 'locale' => 'es', 'entry_route' => '/', 'user_id' => User::factory()->create()->id]);
        $this->events($s6, ['page_viewed' => '/', 'drawer_opened' => null, 'date_chosen' => null, 'line_added' => null, 'identified' => null, 'pay_started' => null]);
        $this->webOrder(3000, '2026-06-08 18:10:00', ['blog.example', 'referral', null], ['blog.example', 'referral', null]);

        // S7: Google cpc «verano» otra vez, sin nada más (dos visitas de la campaña).
        $s7 = $this->visit('2026-06-09 10:00:00', ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'verano', 'device' => 'mobile', 'locale' => 'es', 'entry_route' => '/']);
        $this->events($s7, ['page_viewed' => '/']);

        // S8: directa, un contacto por formulario (hecho de servidor, con sesión) y WhatsApp.
        $s8 = $this->visit('2026-06-09 11:00:00', ['device' => 'desktop', 'locale' => 'es', 'entry_route' => '/contacto']);
        $this->events($s8, ['page_viewed' => '/contacto', 'contact_form_started' => null, 'contact_received' => ['topic' => 'birthday'], 'whatsapp_clicked' => null]);

        // Un bot y una interna: fuera de todo; una de mayo: el periodo anterior.
        $bot = $this->visit('2026-06-05 03:00:00', ['is_bot' => true, 'device' => 'desktop']);
        $this->events($bot, ['page_viewed' => '/', 'drawer_opened' => null]);
        $internal = $this->visit('2026-06-05 09:00:00', ['is_internal' => true, 'device' => 'desktop']);
        $this->events($internal, ['page_viewed' => '/', 'drawer_opened' => null, 'date_chosen' => null]);
        $mayo = $this->visit('2026-05-20 10:00:00', ['utm_source' => 'google', 'utm_medium' => 'cpc', 'device' => 'mobile', 'locale' => 'es', 'entry_route' => '/']);
        $this->events($mayo, ['page_viewed' => '/']);
        $this->webOrder(2000, '2026-05-20 10:30:00', ['google', 'cpc', null], ['google', 'cpc', null]);

        // Un pedido del PANEL y otro anterior a la medición: dinero sí, embudo no.
        $panel = $this->order(1500, '2026-06-07 16:00:00', 'panel');
        $panel->forceFill(['attribution_source' => 'phone', 'attribution_medium' => 'offline'])->saveQuietly();
        $this->payment($panel, 800, '2026-06-07 16:00:00', 'cash');
        $before = $this->order(900, '2026-06-03 10:00:00', null);
        $this->payment($before, 900, '2026-06-03 10:00:00');

        // Un lote del cliente con un evento bueno y dos malos: la ingesta los cuenta para la semana.
        $bad = app(EventIngestor::class)->ingest(Visitor::mint(), [
            ['name' => 'section_viewed', 'event_id' => Visitor::mint(), 'props' => ['section' => 'hero']],
            ['name' => 'no_existe', 'event_id' => Visitor::mint()],
            ['name' => 'order_paid', 'event_id' => Visitor::mint()],
        ], ['webdriver' => true], Request::create('/api/v1/events', 'POST'));
        $this->assertSame(2, count($bad['rejected']));

        // Y un lote tirado por el navegador.
        AnalyticsEvent::query()->create(['event_id' => Visitor::mint(), 'session_id' => $s7->id, 'visitor_id' => $s7->visitor_id, 'name' => 'batch_dropped', 'props' => ['count' => 7, 'status' => 429], 'occurred_at' => '2026-06-09 10:05:00', 'received_at' => '2026-06-09 10:05:00']);
    }

    // ─── Las visitas y el embudo ────────────────────────────────────────────────────────────────

    public function test_visits_are_clean_sessions_of_the_period_and_the_funnel_counts_sessions_per_step(): void
    {
        $this->seedJune();

        $r = (new FunnelReport)->compute(ReportPeriod::ThisMonth->window());

        // Dos bots: el sembrado y el que abre el lote ingerido con `webdriver` (la sonda cuenta como bot).
        $this->assertSame(['visits' => 8, 'identified' => 2, 'bots' => 2, 'internal' => 1], $r['traffic']);

        $funnel = collect($r['funnel'])->keyBy('step');
        $this->assertSame(8, $funnel['visits']['reached']);
        $this->assertSame(6, $funnel['interest']['reached'], 'S1, S2, S3, S6 abren el cajón; S4 llama; S8 contacta');
        $this->assertSame(7500, $funnel['interest']['of_previous_bp']);
        $this->assertSame(4, $funnel['intent']['reached']);
        $this->assertSame(3, $funnel['cart']['reached']);
        $this->assertSame(2, $funnel['identified']['reached']);
        $this->assertSame(2, $funnel['pay_started']['reached']);
        $this->assertSame(2500, $funnel['pay_started']['of_visits_bp']);

        $this->assertSame([
            'visit' => ['stuck' => 2, 'technical' => 0],       // S5 y S7 solo miraron
            'interest' => ['stuck' => 2, 'technical' => 0],    // S4 y S8, tras contactar
            'intent' => ['stuck' => 1, 'technical' => 0],      // S3
            'cart' => ['stuck' => 1, 'technical' => 1],        // S2, con su fallo
            'identified' => ['stuck' => 0, 'technical' => 0],
        ], $r['abandonment']);
    }

    public function test_purchases_are_web_orders_not_sessions_and_the_panel_and_the_past_stay_out(): void
    {
        $this->seedJune();

        $r = (new FunnelReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame(['orders' => 2, 'sold' => 7000, 'revenue' => 7000, 'conversion_bp' => 2500], $r['purchases']);
        $this->assertSame(['visits' => 1, 'orders' => 1, 'revenue' => 2000, 'conversion_bp' => 10000], $r['previous']);

        $series = collect($r['series'])->keyBy('key');
        $this->assertSame(['key' => '2026-06-10', 'visits' => 1, 'purchases' => 1], $series['2026-06-10'], 'las 22:30 UTC del 9 son el 10 en el parque');
        $this->assertSame(0, $series['2026-06-09']['purchases']);
        $this->assertSame(2, $series['2026-06-09']['visits'], 'S7 y S8; S1 ya es del 10 en el parque');
        $this->assertSame(['key' => '2026-06-08', 'visits' => 1, 'purchases' => 1], $series['2026-06-08']);
    }

    // ─── Las fuentes ────────────────────────────────────────────────────────────────────────────

    public function test_sources_merge_the_visits_of_the_sessions_with_the_orders_of_the_seal(): void
    {
        $this->seedJune();

        $sources = collect((new FunnelReport)->compute(ReportPeriod::ThisMonth->window())['sources'])
            ->keyBy(static fn (array $r): string => $r['source'].'/'.$r['medium'].'/'.($r['campaign'] ?? ''));

        $this->assertSame(['source' => 'google', 'medium' => 'cpc', 'campaign' => 'verano', 'visits' => 2, 'orders' => 0, 'sold' => 0, 'collected' => 0], $sources['google/cpc/verano']);
        $this->assertSame(['source' => 'google', 'medium' => 'cpc', 'campaign' => null, 'visits' => 1, 'orders' => 0, 'sold' => 0, 'collected' => 0], $sources['google/cpc/'], 'el gclid sin utm es google/cpc');
        $this->assertSame(['source' => 'direct', 'medium' => 'none', 'campaign' => null, 'visits' => 3, 'orders' => 0, 'sold' => 0, 'collected' => 0], $sources['direct/none/']);
        $this->assertSame(['source' => 'instagram', 'medium' => 'social', 'campaign' => 'reels', 'visits' => 1, 'orders' => 1, 'sold' => 4000, 'collected' => 4000], $sources['instagram/social/reels'], 'el primer toque del pedido de S1');
        $this->assertSame(['source' => 'blog.example', 'medium' => 'referral', 'campaign' => null, 'visits' => 1, 'orders' => 1, 'sold' => 3000, 'collected' => 3000], $sources['blog.example/referral/']);
        $this->assertArrayNotHasKey('phone/offline/', $sources->all(), 'el panel no es una fuente web');

        $last = collect((new FunnelReport)->compute(ReportPeriod::ThisMonth->window())['last_touch'])
            ->keyBy(static fn (array $r): string => $r['source'].'/'.$r['medium'].'/'.($r['campaign'] ?? ''));
        $this->assertSame(['source' => 'google', 'medium' => 'cpc', 'campaign' => 'verano', 'orders' => 1, 'sold' => 4000], $last['google/cpc/verano'], 'la campaña que cerró la compra de S1');
        $this->assertSame(1, $last['blog.example/referral/']['orders']);
    }

    // ─── Las páginas, el dispositivo, el idioma, las horas, el producto, el contacto ────────────

    public function test_pages_devices_locales_hours_products_and_contact(): void
    {
        $this->seedJune();

        $r = (new FunnelReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame([['route' => '/', 'visits' => 5], ['route' => '/contacto', 'visits' => 1], ['route' => '/normas', 'visits' => 1], ['route' => '/precios', 'visits' => 1]], collect($r['entries'])->sortBy([['visits', 'desc'], ['route', 'asc']])->values()->all());
        $exits = collect($r['exits'])->keyBy('route');
        $this->assertSame(1, $exits['/gracias']['count'], 'la última vista de S1');
        $this->assertSame(1, $exits['/servicios']['count'], 'la última vista de S3');
        $this->assertSame(3, $exits['/']['count'], 'S4, S6 y S7 se fueron desde la portada');

        $this->assertSame(['mobile' => 4, 'desktop' => 3, 'tablet' => 1], $r['devices']);
        $this->assertSame(['es' => 6, 'en' => 1, 'fr' => 1], $r['locales']);

        $this->assertSame(1, $r['hours'][0], 'S1 a las 22:30 UTC es la hora 0 en Madrid');
        $this->assertSame(2, $r['hours'][14], 'S4 y S5 a las 12:00 y 12:30 UTC son las 14 en Madrid');
        $this->assertSame(8, array_sum($r['hours']));

        $this->assertSame([['product' => 'Jump 1h', 'count' => 2]], $r['products'], 'el id se traduce al nombre');
        $this->assertSame(['contact_received' => 1, 'call_clicked' => 1, 'whatsapp_clicked' => 1, 'map_clicked' => 0, 'contact_form_started' => 1], $r['contact']);
    }

    public function test_rejected_events_of_the_week_come_from_the_ingestion_counters_and_dropped_from_the_ledger(): void
    {
        $this->seedJune();

        $rejected = (new FunnelReport)->compute(ReportPeriod::ThisMonth->window())['rejected'];

        $this->assertSame(7, $rejected['days']);
        $this->assertSame(2, $rejected['total']);
        $this->assertSame(1, $rejected['by_reason']['unknown_event']);
        $this->assertSame(1, $rejected['by_reason']['server_only']);
        $this->assertSame(0, $rejected['by_reason']['pii']);
        $this->assertSame(2, $rejected['by_day']['2026-06-10']);
        $this->assertSame(7, $rejected['dropped_events']);

        // El contador vive en la caché con su día del parque: sin caché, no hay semana.
        Cache::flush();
        $this->assertSame(0, RejectedEvents::lastDays()['total']);
    }

    public function test_an_empty_period_is_all_zeros(): void
    {
        $r = (new FunnelReport)->compute(ReportPeriod::Yesterday->window());

        $this->assertSame(0, $r['traffic']['visits']);
        $this->assertSame(0, $r['purchases']['conversion_bp']);
        $this->assertSame([], $r['sources']);
        $this->assertSame(array_fill(0, 24, 0), $r['hours']);
        $this->assertSame(0, collect($r['funnel'])->sum('reached'));
    }

    // ─── El presupuesto y la caché ──────────────────────────────────────────────────────────────

    public function test_the_query_budget_does_not_grow_with_the_rows(): void
    {
        $this->seedJune();
        ReportPeriod::ThisMonth->window();

        DB::flushQueryLog();
        DB::enableQueryLog();
        (new FunnelReport)->compute(ReportPeriod::ThisMonth->window());
        $withRows = count(DB::getQueryLog());

        DB::flushQueryLog();
        (new FunnelReport)->compute(ReportPeriod::Yesterday->window());
        $empty = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(24, $withRows);
        // Con filas hay UNA consulta más, la que traduce los ids de producto a nombres, y solo si hay productos.
        $this->assertLessThanOrEqual(1, $withRows - $empty);
    }

    public function test_the_report_is_cached_per_period(): void
    {
        $this->seedJune();
        Cache::flush();

        $first = FunnelReport::for(ReportPeriod::ThisMonth);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $second = FunnelReport::for(ReportPeriod::ThisMonth);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($first, $second);
        $this->assertSame(0, $queries);
    }

    // ─── Los ayudantes ──────────────────────────────────────────────────────────────────────────

    /**
     * Una sesión del libro. ⚠️ Se llama `visit()` porque `session()` ya es un método PÚBLICO del `TestCase` de
     * Laravel, y declararlo privado aquí es un fatal al cargar la clase.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function visit(string $startedAtUtc, array $attributes = []): AnalyticsSession
    {
        return AnalyticsSession::query()->create(array_merge([
            'visitor_id' => Visitor::mint(), 'started_at' => $startedAtUtc, 'last_seen_at' => $startedAtUtc,
            'surface' => 'web', 'is_bot' => false, 'is_internal' => false,
        ], $attributes));
    }

    /**
     * Los hechos de una sesión, en orden y un minuto después cada uno. La clave es el nombre (con `:n` para
     * repetirlo) y el valor la ruta (string), las `props` (array) o nada.
     *
     * @param  array<string, string|array<string, mixed>|null>  $events
     */
    private function events(AnalyticsSession $session, array $events): void
    {
        $at = Carbon::parse($session->started_at, 'UTC');
        foreach ($events as $key => $value) {
            $name = explode(':', $key)[0];
            $at = $at->copy()->addMinute();
            AnalyticsEvent::query()->create([
                'event_id' => Visitor::mint(), 'session_id' => $session->id, 'visitor_id' => $session->visitor_id,
                'user_id' => $session->user_id, 'name' => $name,
                'route' => is_string($value) ? $value : null,
                'props' => is_array($value) ? $value : null,
                'occurred_at' => $at, 'received_at' => $at,
            ]);
        }
    }

    /**
     * Un pedido web cobrado, sellado con su primer y su último toque.
     *
     * @param  array{0: string, 1: string, 2: ?string}  $first
     * @param  array{0: string, 1: string, 2: ?string}  $last
     */
    private function webOrder(int $total, string $paidAtUtc, array $first, array $last): Order
    {
        $order = $this->order($total, $paidAtUtc, 'web');
        $order->forceFill([
            'attribution_source' => $first[0], 'attribution_medium' => $first[1], 'attribution_campaign' => $first[2],
            'attribution' => [
                'first_touch' => ['source' => $first[0], 'medium' => $first[1], 'campaign' => $first[2]],
                'last_touch' => ['source' => $last[0], 'medium' => $last[1], 'campaign' => $last[2]],
            ],
        ])->saveQuietly();
        $this->payment($order, $total, $paidAtUtc);

        return $order;
    }

    private function order(int $total, string $paidAtUtc, ?string $channel): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'JW-FN'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR', 'paid_at' => $paidAtUtc,
        ]);
        $order->forceFill(['attribution_channel' => $channel])->saveQuietly();

        return $order;
    }

    private function payment(Order $order, int $amount, string $paidAtUtc, string $provider = 'redsys'): void
    {
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id, 'amount' => $amount, 'currency' => 'EUR',
            'provider' => $provider, 'status' => Payment::STATUS_PAID, 'paid_at' => $paidAtUtc,
            'gateway_order' => str_pad((string) (800000 + ++$this->counter), 10, '0', STR_PAD_LEFT),
        ]);
    }
}
