<?php

namespace App\Filament\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\Analytics\AttributionContext;
use App\Domain\Platform\Services\Analytics\RejectedEvents;
use App\Domain\Platform\Services\Analytics\Reports\SqlJson;
use App\Domain\Platform\Services\Analytics\Reports\SqlTime;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\Translated;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * **EL EMBUDO Y LAS FUENTES** (`docs/specs/analitica.md` §4.5, T2c; la definición de conversión, §4.2): lo que
 * cuenta el libro de eventos del cliente —sesiones y hechos— cruzado con la verdad de las compras, que son los
 * pedidos y sus cobros.
 *
 * **La visita** es una sesión limpia (ni bot ni interna) que EMPEZÓ en el periodo. **El embudo** se cuenta por
 * sesión: interés (`drawer_opened` o un contacto), intención (`date_chosen`), cesta (`line_added`), identificada
 * (`identified`) y pago iniciado (`pay_started`), cada paso como sesiones que lo alcanzaron y su conversión
 * respecto al anterior. **La compra NO se cuenta por sesión**: un visitante sin la categoría `analytics` no ata su
 * `order_paid` a la sesión, así que las compras son los pedidos cobrados por `web`/`app` en el periodo, y la
 * conversión es compras entre visitas. El abandono es la sesión que se quedó en un paso y no llegó al pago
 * iniciado; lo que pasó después de iniciar el pago lo cuentan el banco y el dinero (rechazados, caducados).
 *
 * **Las fuentes** vienen de dos sitios que no se pisan: las VISITAS de las sesiones, con la misma regla del toque
 * que sella un pedido (`AttributionContext::touchOf()`: un solo dueño), y los PEDIDOS del sello —las columnas
 * planas son el PRIMER toque; el último vive en el JSON—. Un pedido `web` sin sesión (sin cookie) sale como «sin
 * dato», y uno anterior a la medición no entra en el embudo (no era `web` ni `app`).
 *
 * ⚠️ Solo agregados; ninguna ruta lleva un token (la ingesta las enmascara) y las campañas se escapan al pintar.
 * Veinte consultas por periodo, medidas; la caché de cinco minutos las reparte entre los widgets.
 */
final class FunnelReport
{
    public const CACHE_SECONDS = 300;

    public const TOP_ROWS = 12;

    /** Los pasos del embudo por sesión, en orden, y los hechos que los alcanzan. */
    public const STEPS = [
        'interest' => ['drawer_opened', 'contact_received', 'call_clicked', 'whatsapp_clicked'],
        'intent' => ['date_chosen'],
        'cart' => ['line_added'],
        'identified' => ['identified'],
        'pay_started' => ['pay_started'],
    ];

    /** Los hechos de contacto que se cuentan aparte. */
    public const CONTACT_EVENTS = ['contact_received', 'call_clicked', 'whatsapp_clicked', 'map_clicked', 'contact_form_started'];

    public const TECHNICAL_EVENTS = ['request_failed', 'client_error'];

    /** La fuente de un pedido `web`/`app` que nació sin sesión: no es «directo», es que no se sabe. */
    public const SOURCE_UNKNOWN = 'unknown';

    /** @var list<string> */
    private const COLLECTED_STATUSES = [Order::STATUS_PAID, Order::STATUS_REFUNDED];

    /** @var list<string> */
    private const WEB_CHANNELS = [AttributionContext::CHANNEL_WEB, AttributionContext::CHANNEL_APP];

    /** @return array<string, mixed> */
    public static function for(ReportPeriod $period): array
    {
        $window = $period->window();

        return Cache::remember(self::cacheKey($window), self::CACHE_SECONDS, fn (): array => (new self)->compute($window));
    }

    public static function cacheKey(Window $window): string
    {
        return 'analytics:funnel:v1:'.$window->timezone.':'.$window->dateFrom().':'.$window->dateTo().':'.app()->getLocale();
    }

    /** @return array<string, mixed> */
    public function compute(Window $window): array
    {
        $sessionRows = $this->sessionsByBucket($window);
        $sessions = $this->foldSessions($window, $sessionRows);
        $visits = $sessions['visits'];

        $steps = $this->steps($window);
        $funnel = $this->funnel($visits, $steps['reached']);
        // Las sesiones sin NINGÚN hecho del embudo no tienen fila: solo miraron, y se quedaron en la visita.
        $abandonment = $steps['abandonment'];
        $abandonment['visit']['stuck'] += max(0, $visits - $steps['with_events']);
        $purchaseRows = $this->purchasesByBucket($window);
        $purchases = ['orders' => 0, 'sold' => 0];
        $purchasesByKey = [];
        foreach ($purchaseRows as $row) {
            $key = $window->bucketKey(SqlTime::bucketStart((string) $row->bucket));
            $purchasesByKey[$key] = ($purchasesByKey[$key] ?? 0) + (int) $row->n;
            $purchases['orders'] += (int) $row->n;
            $purchases['sold'] += (int) $row->sold;
        }
        $purchases['revenue'] = $this->revenue($window);
        $purchases['conversion_bp'] = $visits > 0 ? (int) round($purchases['orders'] / $visits * 10000) : 0;

        return [
            'window' => [
                'from' => $window->dateFrom(),
                'to' => $window->dateTo(),
                'days' => $window->days(),
                'granularity' => $window->granularity(),
            ],
            // ⚠️ `traffic` y no `sessions`: `AccessRevocationTest` escanea `app/` buscando el literal de la tabla de
            // credenciales `sessions`, y una clave de array con ese nombre es un falso positivo que no merece
            // ampliar su lista blanca.
            'traffic' => [
                'visits' => $visits,
                'identified' => $sessions['identified'],
                'bots' => $sessions['bots'],
                'internal' => $sessions['internal'],
            ],
            'funnel' => $funnel,
            'purchases' => $purchases,
            'abandonment' => $abandonment,
            'sources' => $this->sources($window),
            'last_touch' => $this->lastTouch($window),
            'entries' => $this->entries($window),
            'exits' => $this->exits($window),
            'devices' => $this->groupedSessions($window, 'device'),
            'locales' => $this->groupedSessions($window, 'locale'),
            'hours' => $sessions['hours'],
            'products' => $this->products($window),
            'contact' => $this->contact($window),
            'rejected' => RejectedEvents::lastDays() + ['dropped_events' => $this->droppedEvents($window)],
            'previous' => $this->totalsOnly($window->previous()),
            'series' => $this->series($window, $sessions['by_key'], $purchasesByKey),
        ];
    }

    // ─── Las sesiones ────────────────────────────────────────────────────────────────────────────

    /** @return Collection<int, stdClass> */
    private function sessionsByBucket(Window $window): Collection
    {
        $bucket = SqlTime::hourBucket('started_at');

        return $this->between(DB::table('analytics_sessions'), 'started_at', $window)
            ->selectRaw(
                "{$bucket} AS bucket, "
                .'SUM(CASE WHEN is_bot = 0 AND is_internal = 0 THEN 1 ELSE 0 END) AS clean, '
                .'SUM(CASE WHEN is_bot = 0 AND is_internal = 0 AND user_id IS NOT NULL THEN 1 ELSE 0 END) AS identified, '
                .'SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) AS bots, '
                .'SUM(CASE WHEN is_bot = 0 AND is_internal = 1 THEN 1 ELSE 0 END) AS internal',
            )
            ->groupByRaw($bucket)
            ->get();
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return array{visits: int, identified: int, bots: int, internal: int, by_key: array<string, int>, hours: list<int>}
     */
    private function foldSessions(Window $window, Collection $rows): array
    {
        $out = ['visits' => 0, 'identified' => 0, 'bots' => 0, 'internal' => 0, 'by_key' => [], 'hours' => array_fill(0, 24, 0)];

        foreach ($rows as $row) {
            $start = SqlTime::bucketStart((string) $row->bucket);
            $key = $window->bucketKey($start);
            $clean = (int) $row->clean;
            $out['visits'] += $clean;
            $out['identified'] += (int) $row->identified;
            $out['bots'] += (int) $row->bots;
            $out['internal'] += (int) $row->internal;
            $out['by_key'][$key] = ($out['by_key'][$key] ?? 0) + $clean;
            $out['hours'][(int) $start->setTimezone($window->timezone)->format('G')] += $clean;
        }

        return $out;
    }

    // ─── El embudo y el abandono ─────────────────────────────────────────────────────────────────

    /**
     * Una fila por sesión limpia con algún hecho del embudo: qué pasos alcanzó y si sufrió un fallo técnico.
     * De ahí salen a la vez los pasos alcanzados y el abandono (el paso más alto de cada sesión).
     *
     * @return array{reached: array<string, int>, abandonment: array<string, array{stuck: int, technical: int}>, with_events: int}
     */
    private function steps(Window $window): array
    {
        $select = [];
        $bindings = [];
        foreach (self::STEPS as $step => $events) {
            $marks = implode(', ', array_fill(0, count($events), '?'));
            $select[] = "MAX(CASE WHEN e.name IN ({$marks}) THEN 1 ELSE 0 END) AS {$step}";
            array_push($bindings, ...$events);
        }
        $technicalMarks = implode(', ', array_fill(0, count(self::TECHNICAL_EVENTS), '?'));
        $select[] = "MAX(CASE WHEN e.name IN ({$technicalMarks}) THEN 1 ELSE 0 END) AS technical";
        array_push($bindings, ...self::TECHNICAL_EVENTS);

        $rows = $this->cleanEvents($window)
            ->whereIn('e.name', [...array_merge(...array_values(self::STEPS)), ...self::TECHNICAL_EVENTS])
            ->selectRaw(implode(', ', $select), $bindings)
            ->groupBy('e.session_id')
            ->get();

        $stepNames = array_keys(self::STEPS);
        $reached = array_fill_keys($stepNames, 0);
        // Se abandona ANTES del pago iniciado; lo que pasa después lo cuentan el banco y el dinero.
        $abandonment = array_fill_keys(['visit', ...array_slice($stepNames, 0, -1)], ['stuck' => 0, 'technical' => 0]);
        $withEvents = 0;

        foreach ($rows as $row) {
            $withEvents++;
            $highest = 'visit';
            foreach ($stepNames as $step) {
                if ((int) $row->{$step} === 1) {
                    $reached[$step]++;
                    $highest = $step;
                }
            }
            if ($highest !== 'pay_started') {
                $abandonment[$highest]['stuck']++;
                $abandonment[$highest]['technical'] += (int) $row->technical;
            }
        }

        return ['reached' => $reached, 'abandonment' => $abandonment, 'with_events' => $withEvents];
    }

    /**
     * El embudo como lista: cada paso con sus sesiones, su conversión respecto al paso anterior y respecto a
     * las visitas, en puntos básicos (10000 = 100 %) para que el widget no redondee dos veces.
     *
     * @param  array<string, int>  $reached
     * @return list<array{step: string, reached: int, of_previous_bp: int, of_visits_bp: int}>
     */
    private function funnel(int $visits, array $reached): array
    {
        $funnel = [['step' => 'visits', 'reached' => $visits, 'of_previous_bp' => 10000, 'of_visits_bp' => 10000]];
        $previous = $visits;

        foreach (array_keys(self::STEPS) as $step) {
            $count = min($reached[$step], $previous);
            $funnel[] = [
                'step' => $step,
                'reached' => $count,
                'of_previous_bp' => $previous > 0 ? (int) round($count / $previous * 10000) : 0,
                'of_visits_bp' => $visits > 0 ? (int) round($count / $visits * 10000) : 0,
            ];
            $previous = $count;
        }

        return $funnel;
    }

    // ─── Las compras ─────────────────────────────────────────────────────────────────────────────

    /** Los pedidos cobrados por la web o la app, por hora UTC de `paid_at`. @return Collection<int, stdClass> */
    private function purchasesByBucket(Window $window): Collection
    {
        $bucket = SqlTime::hourBucket('paid_at');

        return $this->webOrders($window)
            ->selectRaw("{$bucket} AS bucket, COUNT(*) AS n, COALESCE(SUM(total), 0) AS sold")
            ->groupByRaw($bucket)
            ->get();
    }

    /** Lo cobrado por esos pedidos (los `payments` con éxito), en céntimos. */
    private function revenue(Window $window): int
    {
        return (int) $this->between(DB::table('payments as p'), 'p.paid_at', $window)
            ->join('orders as o', 'o.id', '=', 'p.payable_id')
            ->where('p.payable_type', (new Order)->getMorphClass())
            ->where('p.status', Payment::STATUS_PAID)
            ->whereIn('o.attribution_channel', self::WEB_CHANNELS)
            ->sum('p.amount');
    }

    // ─── Las fuentes ─────────────────────────────────────────────────────────────────────────────

    /**
     * Fuente · medio · campaña del PRIMER toque: las visitas desde las sesiones (con la regla del toque) y los
     * pedidos, lo vendido y lo cobrado desde el sello. Ordenadas por visitas y después por pedidos.
     *
     * @return list<array{source: string, medium: string, campaign: ?string, visits: int, orders: int, sold: int, collected: int}>
     */
    private function sources(Window $window): array
    {
        $gclid = SqlJson::string('click_ids', '$.gclid');
        $hasGclid = "CASE WHEN {$gclid} IS NULL THEN 0 ELSE 1 END";

        $sessions = $this->cleanSessions($window)
            ->selectRaw("utm_source, utm_medium, utm_campaign, ref, referrer_host, {$hasGclid} AS gclid, COUNT(*) AS n")
            ->groupByRaw("utm_source, utm_medium, utm_campaign, ref, referrer_host, {$hasGclid}")
            ->get();

        $rows = [];
        foreach ($sessions as $row) {
            $touch = AttributionContext::touchOf($row->utm_source, $row->utm_medium, $row->utm_campaign, $row->ref, $row->referrer_host, (int) $row->gclid === 1);
            $key = self::touchKey($touch['source'], $touch['medium'], $touch['campaign']);
            $rows[$key] ??= $touch + ['visits' => 0, 'orders' => 0, 'sold' => 0, 'collected' => 0];
            $rows[$key]['visits'] += (int) $row->n;
        }

        $orders = $this->webOrders($window)
            ->selectRaw('attribution_source AS source, attribution_medium AS medium, attribution_campaign AS campaign, COUNT(*) AS n, COALESCE(SUM(total), 0) AS sold')
            ->groupBy('attribution_source', 'attribution_medium', 'attribution_campaign')
            ->get();
        foreach ($orders as $row) {
            $key = self::touchKey($row->source, $row->medium, $row->campaign);
            $rows[$key] ??= self::unknownTouch($row->source, $row->medium, $row->campaign) + ['visits' => 0, 'orders' => 0, 'sold' => 0, 'collected' => 0];
            $rows[$key]['orders'] += (int) $row->n;
            $rows[$key]['sold'] += (int) $row->sold;
        }

        $payments = $this->between(DB::table('payments as p'), 'p.paid_at', $window)
            ->join('orders as o', 'o.id', '=', 'p.payable_id')
            ->where('p.payable_type', (new Order)->getMorphClass())
            ->where('p.status', Payment::STATUS_PAID)
            ->whereIn('o.attribution_channel', self::WEB_CHANNELS)
            ->selectRaw('o.attribution_source AS source, o.attribution_medium AS medium, o.attribution_campaign AS campaign, COALESCE(SUM(p.amount), 0) AS collected')
            ->groupBy('o.attribution_source', 'o.attribution_medium', 'o.attribution_campaign')
            ->get();
        foreach ($payments as $row) {
            $key = self::touchKey($row->source, $row->medium, $row->campaign);
            $rows[$key] ??= self::unknownTouch($row->source, $row->medium, $row->campaign) + ['visits' => 0, 'orders' => 0, 'sold' => 0, 'collected' => 0];
            $rows[$key]['collected'] += (int) $row->collected;
        }

        usort($rows, static fn (array $a, array $b): int => [$b['visits'], $b['orders']] <=> [$a['visits'], $a['orders']]);

        return array_slice($rows, 0, self::TOP_ROWS);
    }

    /**
     * El ÚLTIMO toque de los pedidos, desde el JSON del sello: la campaña que cerró la compra.
     *
     * @return list<array{source: string, medium: string, campaign: ?string, orders: int, sold: int}>
     */
    private function lastTouch(Window $window): array
    {
        $source = SqlJson::string('attribution', '$.last_touch.source');
        $medium = SqlJson::string('attribution', '$.last_touch.medium');
        $campaign = SqlJson::string('attribution', '$.last_touch.campaign');

        return $this->webOrders($window)
            ->selectRaw("{$source} AS source, {$medium} AS medium, {$campaign} AS campaign, COUNT(*) AS n, COALESCE(SUM(total), 0) AS sold")
            ->groupByRaw("{$source}, {$medium}, {$campaign}")
            ->orderByDesc('n')
            ->limit(self::TOP_ROWS)
            ->get()
            ->map(static fn (stdClass $row): array => self::unknownTouch($row->source, $row->medium, $row->campaign) + [
                'orders' => (int) $row->n,
                'sold' => (int) $row->sold,
            ])
            ->values()
            ->all();
    }

    private static function touchKey(?string $source, ?string $medium, ?string $campaign): string
    {
        return ($source ?? self::SOURCE_UNKNOWN).'|'.($medium ?? self::SOURCE_UNKNOWN).'|'.($campaign ?? '');
    }

    /** @return array{source: string, medium: string, campaign: ?string} */
    private static function unknownTouch(mixed $source, mixed $medium, mixed $campaign): array
    {
        return [
            'source' => is_string($source) && $source !== '' ? $source : self::SOURCE_UNKNOWN,
            'medium' => is_string($medium) && $medium !== '' ? $medium : self::SOURCE_UNKNOWN,
            'campaign' => is_string($campaign) && $campaign !== '' ? $campaign : null,
        ];
    }

    // ─── Las páginas, el dispositivo, el idioma, el producto, el contacto ───────────────────────

    /** @return list<array{route: string, visits: int}> */
    private function entries(Window $window): array
    {
        return $this->cleanSessions($window)
            ->selectRaw('entry_route AS route, COUNT(*) AS n')
            ->groupBy('entry_route')
            ->orderByDesc('n')
            ->limit(self::TOP_ROWS)
            ->get()
            ->map(static fn (stdClass $row): array => ['route' => (string) ($row->route ?? ''), 'visits' => (int) $row->n])
            ->values()
            ->all();
    }

    /**
     * La ÚLTIMA vista de cada sesión limpia: por dónde se fue.
     *
     * @return list<array{route: string, count: int}>
     */
    private function exits(Window $window): array
    {
        return DB::table('analytics_events')
            ->whereIn('id', function (Builder $query) use ($window): void {
                $this->cleanEvents($window, $query)
                    ->where('e.name', 'page_viewed')
                    ->selectRaw('MAX(e.id)')
                    ->groupBy('e.session_id');
            })
            ->selectRaw('route, COUNT(*) AS n')
            ->groupBy('route')
            ->orderByDesc('n')
            ->limit(self::TOP_ROWS)
            ->get()
            ->map(static fn (stdClass $row): array => ['route' => (string) ($row->route ?? ''), 'count' => (int) $row->n])
            ->values()
            ->all();
    }

    /**
     * Las sesiones limpias agrupadas por una columna (`device`, `locale`), de más a menos.
     *
     * @return array<string, int>
     */
    private function groupedSessions(Window $window, string $column): array
    {
        $out = [];
        $rows = $this->cleanSessions($window)
            ->selectRaw("{$column} AS k, COUNT(*) AS n")
            ->groupBy($column)
            ->orderByDesc('n')
            ->orderBy($column)   // los empates, en orden estable
            ->get();
        foreach ($rows as $row) {
            $out[(string) ($row->k ?? '')] = (int) $row->n;
        }

        return $out;
    }

    /**
     * Los productos elegidos en el cajón (`product_chosen.props.product`), por sesiones distintas. Si la clave es
     * el id del producto, se traduce a su nombre.
     *
     * @return list<array{product: string, count: int}>
     */
    private function products(Window $window): array
    {
        $product = SqlJson::string('e.props', '$.product');

        $rows = $this->cleanEvents($window)
            ->where('e.name', 'product_chosen')
            ->selectRaw("{$product} AS product, COUNT(DISTINCT e.session_id) AS n")
            ->groupByRaw($product)
            ->orderByDesc('n')
            ->limit(self::TOP_ROWS)
            ->get();

        $ids = $rows->pluck('product')->filter(static fn ($v): bool => is_numeric($v))->map(static fn ($v): int => (int) $v)->all();
        $names = $ids === [] ? [] : DB::table('ticket_types')->whereIn('id', $ids)->pluck('name', 'id')->all();

        return $rows->map(static function (stdClass $row) use ($names): array {
            $key = (string) ($row->product ?? '');
            $name = $names[(int) $key] ?? null;
            $decoded = is_string($name) ? json_decode($name, true) : null;

            return [
                'product' => is_array($decoded) ? (string) Translated::pick($decoded, app()->getLocale()) : $key,
                'count' => (int) $row->n,
            ];
        })->values()->all();
    }

    /**
     * Los hechos de contacto: los del servidor (`contact_received`) cuentan siempre; los del cliente, solo desde
     * sesiones limpias.
     *
     * @return array<string, int>
     */
    private function contact(Window $window): array
    {
        $rows = $this->between(DB::table('analytics_events as e'), 'e.received_at', $window)
            ->leftJoin('analytics_sessions as s', 's.id', '=', 'e.session_id')
            ->whereIn('e.name', self::CONTACT_EVENTS)
            ->where(function (Builder $query): void {
                $query->whereNull('s.id')->orWhere(function (Builder $clean): void {
                    $clean->where('s.is_bot', false)->where('s.is_internal', false);
                });
            })
            ->selectRaw('e.name AS name, COUNT(*) AS n')
            ->groupBy('e.name')
            ->get();

        $out = array_fill_keys(self::CONTACT_EVENTS, 0);
        foreach ($rows as $row) {
            $out[(string) $row->name] = (int) $row->n;
        }

        return $out;
    }

    /** Los eventos que el cliente tiró por no poder enviarlos (`batch_dropped.props.count`), sumados. */
    private function droppedEvents(Window $window): int
    {
        $count = SqlJson::string('props', '$.count');

        return (int) $this->between(DB::table('analytics_events'), 'received_at', $window)
            ->where('name', 'batch_dropped')
            ->selectRaw("COALESCE(SUM({$count}), 0) AS n")
            ->value('n');
    }

    // ─── El periodo anterior y la serie ─────────────────────────────────────────────────────────

    /** @return array<string, int> */
    private function totalsOnly(Window $window): array
    {
        $visits = $this->cleanSessions($window)->count();
        $orders = $this->webOrders($window)->count();

        return [
            'visits' => $visits,
            'orders' => $orders,
            'revenue' => $this->revenue($window),
            'conversion_bp' => $visits > 0 ? (int) round($orders / $visits * 10000) : 0,
        ];
    }

    /**
     * @param  array<string, int>  $visits
     * @param  array<string, int>  $purchases
     * @return list<array{key: string, visits: int, purchases: int}>
     */
    private function series(Window $window, array $visits, array $purchases): array
    {
        $series = [];
        foreach ($window->bucketKeys() as $key) {
            $series[] = ['key' => $key, 'visits' => $visits[$key] ?? 0, 'purchases' => $purchases[$key] ?? 0];
        }

        return $series;
    }

    // ─── Las consultas base ──────────────────────────────────────────────────────────────────────

    /** Las sesiones LIMPIAS que empezaron en la ventana. */
    private function cleanSessions(Window $window): Builder
    {
        return $this->between(DB::table('analytics_sessions'), 'started_at', $window)
            ->where('is_bot', false)
            ->where('is_internal', false);
    }

    /** Los eventos (`e`) de las sesiones limpias (`s`) que empezaron en la ventana. */
    private function cleanEvents(Window $window, ?Builder $query = null): Builder
    {
        $query ??= DB::table('analytics_events as e');

        return $query
            ->from('analytics_events as e')
            ->join('analytics_sessions as s', 's.id', '=', 'e.session_id')
            ->where('s.started_at', '>=', $window->utcFrom())
            ->where('s.started_at', '<', $window->utcTo())
            ->where('s.is_bot', false)
            ->where('s.is_internal', false);
    }

    /** Los pedidos cobrados por la web o la app dentro de la ventana (por `paid_at`). */
    private function webOrders(Window $window): Builder
    {
        return $this->between(DB::table('orders'), 'paid_at', $window)
            ->whereIn('status', self::COLLECTED_STATUSES)
            ->whereIn('attribution_channel', self::WEB_CHANNELS);
    }

    private function between(Builder $query, string $column, Window $window): Builder
    {
        return $query
            ->where($column, '>=', $window->utcFrom())
            ->where($column, '<', $window->utcTo());
    }
}
