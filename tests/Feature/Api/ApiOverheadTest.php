<?php

namespace Tests\Feature\Api;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Http\Api\ApiSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Fase 3 · paso 0 — **cuánto cuesta el grupo de middleware** antes de que el endpoint haga nada
 * (`PERF-02`).
 *
 * Nace de una sospecha concreta al montar el grupo: `SecurityHeaders` calcula la CSP leyendo
 * `settings` (entorno de Redsys, hosts de embeds sociales), y meterlo en una superficie de alta
 * frecuencia —`catalog/*` y `availability/*` llegan en el paso 1— podía significar consultas por
 * petición justo donde no sobran. **Medido: 1 consulta**, el `pluck` único que `PERF-02` ya dejó
 * memoizado por petición. No hay problema que arreglar, y ahora hay un testigo que avisará si
 * alguien lo reintroduce.
 *
 * Mismo espíritu que `HomePageTest::test_anonymous_home_get_stays_within_query_budget`: si un
 * cambio rompe este presupuesto, ES una regresión, no un test que hay que subir.
 */
class ApiOverheadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Techo generoso sobre lo medido (1) para no volverlo frágil ante un cambio legítimo, y
     * suficientemente bajo para que un N+1 o una lectura de settings sin memoizar lo rompan.
     */
    private const QUERY_BUDGET = 5;

    public function test_the_api_middleware_stack_barely_touches_the_database(): void
    {
        Route::middleware('api')->prefix(ApiSurface::PREFIX)
            ->get('__test/empty', static fn (): array => ['ok' => true]);

        $queries = $this->queriesOf(fn () => $this->getJson('/'.ApiSurface::PREFIX.'/__test/empty')->assertOk());

        $this->assertLessThanOrEqual(
            self::QUERY_BUDGET,
            count($queries),
            'El stack de middleware de la API consulta la base de datos más de lo esperado. '.
            "No subas el presupuesto sin entender por qué:\n  ".implode("\n  ", $queries)
        );
    }

    /**
     * Fase 3 · paso 1b — el catálogo es la primera pantalla del flujo de compra y la superficie de
     * API con más tráfico previsible, así que su coste no puede crecer con el número de productos.
     *
     * La forma de medirlo no es un techo fijo —que envejece mal y se acaba subiendo— sino la
     * PENDIENTE: se pide el catálogo con un producto y con doce, y el número de consultas tiene que
     * ser exactamente el mismo. Un `with()` que alguien quite deja de ser una micro-regresión
     * invisible para convertirse en un test rojo con la consulta repetida en el mensaje.
     */
    public function test_listing_the_catalog_does_not_grow_with_the_number_of_products(): void
    {
        $this->catalogFixture(1);
        $this->warmUp('/catalog/products');
        $one = $this->queriesOf(fn () => $this->getJson('/'.ApiSurface::PREFIX.'/catalog/products')->assertOk());

        $this->catalogFixture(11);
        $many = $this->queriesOf(fn () => $this->getJson('/'.ApiSurface::PREFIX.'/catalog/products')->assertOk());

        $this->assertCount(
            count($one),
            $many,
            "Listar 12 productos cuesta más consultas que listar 1: hay un N+1 en el catálogo.\n  ".
            implode("\n  ", array_diff($many, $one))
        );
    }

    /**
     * Lo mismo para la ficha: sus complementos se cargan en bloque, no uno a uno. Es el N+1 más
     * fácil de reintroducir, porque el precio de cada complemento se resuelve por separado.
     */
    public function test_the_product_detail_does_not_grow_with_the_number_of_addons(): void
    {
        $product = $this->catalogFixture(1)[0];
        $this->attachAddons($product, 1);
        $this->warmUp('/catalog/products/'.$product->id);
        $one = $this->queriesOf(fn () => $this->getJson('/'.ApiSurface::PREFIX.'/catalog/products/'.$product->id)->assertOk());

        $this->attachAddons($product, 6);
        $many = $this->queriesOf(fn () => $this->getJson('/'.ApiSurface::PREFIX.'/catalog/products/'.$product->id)->assertOk());

        $this->assertCount(
            count($one),
            $many,
            "La ficha con 7 complementos cuesta más consultas que con 1: hay un N+1.\n  ".
            implode("\n  ", array_diff($many, $one))
        );
    }

    /**
     * Petición de calentamiento que NO se mide.
     *
     * La primera petición de un proceso paga el `select value, key from settings` que `PERF-02`
     * memoiza para la CSP; la segunda ya no. Sin calentar, la medición de «1 producto» llevaría esa
     * consulta y la de «12» no, y la comparación mediría el memo en vez del catálogo.
     */
    private function warmUp(string $path): void
    {
        $this->getJson('/'.ApiSurface::PREFIX.$path)->assertOk();
    }

    /**
     * Consultas SQL que dispara la llamada dada.
     *
     * @return list<string>
     */
    private function queriesOf(callable $call): array
    {
        // Query log y no `DB::listen`: un listener no se puede quitar, así que medir dos veces en el
        // mismo test acumulaba los dos oyentes y contaba cada consulta por duplicado — la primera
        // versión de este test «detectaba» un N+1 que no existía. El log sí se vacía.
        DB::flushQueryLog();
        DB::enableQueryLog();

        $call();

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $queries;
    }

    /**
     * Fase 3 · paso 4a — presupuestar una cesta no puede costar una consulta por línea.
     *
     * Es la medición que destapó el motivo de fondo para extraer la tarificación: cuando vivía en el
     * componente Livewire, cada render llamaba a los TRES métodos (desglose, total y lo que se cobra
     * online) y cada uno resolvía otra vez el precio de cada línea, porque
     * `RateResolver::priceCents()` consulta por llamada —la misma trampa que el spec §10.ter 17
     * documentó en el read-model del catálogo—. Ahora la tarifa de cada fecha se resuelve una vez y
     * el precio se lee de la relación ya cargada.
     *
     * Se mide por PENDIENTE y no con un techo: doce líneas del mismo día tienen que costar
     * exactamente lo mismo que una.
     *
     * ⚠️ **Lo que este test NO cubre**, dicho en voz alta para que no se lea como «coste plano»: las
     * líneas CON complementos sí crecen, y su pendiente la fija el test siguiente.
     */
    public function test_quoting_a_cart_does_not_grow_with_the_number_of_lines(): void
    {
        $product = $this->catalogFixture(1)[0];
        $date = now()->addDays(2)->toDateString();

        $body = fn (int $lines): array => ['items' => array_map(static fn (int $i): array => [
            'product_id' => $product->id,
            'date' => $date,
            'time' => str_pad((string) (8 + $i), 2, '0', STR_PAD_LEFT).':00:00',
            'quantity' => 8,
        ], range(0, $lines - 1))];

        // La primera petición de un proceso paga el `select` de `settings` que `PERF-02` memoiza.
        $this->postJson('/'.ApiSurface::PREFIX.'/orders/quote', $body(1))->assertOk();

        $one = $this->queriesOf(fn () => $this->postJson('/'.ApiSurface::PREFIX.'/orders/quote', $body(1))->assertOk());
        $many = $this->queriesOf(fn () => $this->postJson('/'.ApiSurface::PREFIX.'/orders/quote', $body(12))->assertOk());

        $this->assertCount(
            count($one),
            $many,
            "Presupuestar 12 líneas cuesta más consultas que presupuestar 1: hay un precio resuelto por línea.\n  ".
            implode("\n  ", array_diff($many, $one))
        );
    }

    /**
     * Fase 3 · paso 4a — el coste por COMPLEMENTO, que sí crece: **2 consultas por complemento
     * resuelto** (medido 2026-08-13: 1 → 8 consultas, 3 → 12, 6 → 18).
     *
     * La causa no está en la tarificación sino en `AddonResolver::resolve()`, que pide el precio de
     * cada complemento con `RateResolver::priceCents()` —y cada llamada son dos consultas: la tarifa
     * del día y el importe—. Es código de dinero COMPARTIDO con `OrderCreator`, que lo ejecuta
     * dentro de la transacción que sostiene los locks de aforo, así que ahí duele más que aquí.
     *
     * **No se arregla en este paso a propósito**: tocar el resolutor de complementos cambia el
     * camino del cobro y exige los verificadores de concurrencia; hacerlo de tapadillo dentro de una
     * extracción es justo lo que el spec §10.ter 18 dejó escrito que no se hace. Queda en
     * `DEUDA.md` con esta medición. Lo que hace este test mientras tanto es que no EMPEORE: fija la
     * pendiente conocida, no la bendice.
     */
    public function test_the_known_cost_per_addon_does_not_get_worse(): void
    {
        $product = $this->catalogFixture(1)[0];
        $this->attachAddons($product, 6);
        $addonIds = $product->addons()->pluck('ticket_types.id')->all();
        $date = now()->addDays(2)->toDateString();

        $body = fn (int $addons): array => ['items' => [[
            'product_id' => $product->id, 'date' => $date, 'time' => '10:00:00', 'quantity' => 8,
            'addons' => array_map(
                static fn (int $id): array => ['product_id' => $id, 'quantity' => 1],
                array_slice($addonIds, 0, $addons),
            ),
        ]]];

        $this->postJson('/'.ApiSurface::PREFIX.'/orders/quote', $body(1))->assertOk();

        $one = $this->queriesOf(fn () => $this->postJson('/'.ApiSurface::PREFIX.'/orders/quote', $body(1))->assertOk());
        $six = $this->queriesOf(fn () => $this->postJson('/'.ApiSurface::PREFIX.'/orders/quote', $body(6))->assertOk());

        $this->assertLessThanOrEqual(
            count($one) + 2 * 5,
            count($six),
            "El coste por complemento ha crecido por encima de las 2 consultas medidas.\n  ".
            implode("\n  ", array_diff($six, $one))
        );
    }

    /**
     * Fase 4 · paso 4.0b·5 — resolver los complementos es la pantalla con MÁS clics del embudo.
     *
     * Una configuración típica son 8–12 clics y cada uno pide esta respuesta, así que su pendiente
     * es la que decide si la SPA se nota lenta (spec §4.4.3). El endpoint hace a propósito dos cosas
     * en una petición —resolver los complementos y tarificar la línea— y por eso paga DOS veces la
     * pendiente conocida de `RateResolver::priceCents()`: una al componer el modelo de vista y otra
     * al tarificar. Se midió antes de decidirlo: repartirlo en dos endpoints cuesta las mismas
     * consultas, con el doble de viajes y de fichas de `throttle`.
     *
     * Lo que fija este test es esa pendiente, para que nadie añada una tercera pasada sin verlo.
     * **No la bendice**: el N+1 de fondo está en `DEUDA.md`, y arreglarlo toca el resolutor —camino
     * del cobro— así que exige paso propio y los verificadores de concurrencia.
     */
    public function test_resolving_addons_pays_the_known_slope_and_no_more(): void
    {
        $product = $this->catalogFixture(1)[0];
        $this->attachAddons($product, 6);
        $addonIds = $product->addons()->pluck('ticket_types.id')->all();
        $date = now()->addDays(2)->toDateString();
        $path = '/'.ApiSurface::PREFIX.'/catalog/products/'.$product->id.'/addons';

        $body = fn (int $addons): array => [
            'quantity' => 8, 'date' => $date, 'time' => '10:00:00',
            'addons' => array_map(
                static fn (int $id): array => ['product_id' => $id, 'quantity' => 1],
                array_slice($addonIds, 0, $addons),
            ),
        ];

        // Calentamiento: la primera petición del proceso paga el memo de settings (`PERF-02`).
        $this->postJson($path, $body(1))->assertOk();

        $one = $this->queriesOf(fn () => $this->postJson($path, $body(1))->assertOk());
        $six = $this->queriesOf(fn () => $this->postJson($path, $body(6))->assertOk());

        // Los SEIS complementos se recorren siempre —están enganchados al producto—, así que lo que
        // crece con la selección es solo lo que se tarifica: 5 complementos más × 2 pasadas.
        $this->assertLessThanOrEqual(
            count($one) + 5 * 2,
            count($six),
            "Resolver complementos ha crecido por encima de la pendiente medida.\n  ".
            implode("\n  ", array_diff($six, $one))
        );
    }

    /**
     * Fase 3 · paso 4b — el calendario no puede costar una consulta por día ofrecido.
     *
     * Es el N+1 más fácil de reintroducir aquí, porque el precio de cada día se resuelve por
     * separado y un calendario tiene decenas de días: era exactamente lo que hacía el sidebar antes
     * de extraer esto (`RateResolver::priceCents` por celda, hasta 42 veces por render).
     *
     * Se mide por PENDIENTE: un horizonte con muchos más días tiene que costar lo mismo que uno con
     * pocos, porque la tarifa se resuelve por día DISTINTO y estos comparten la de siempre.
     */
    public function test_listing_the_offered_dates_does_not_grow_with_the_number_of_days(): void
    {
        $product = $this->catalogFixture(1)[0];
        $this->seedSlots($product, days: 2);
        $this->warmUp('/availability/'.$product->id.'/dates');
        $few = $this->queriesOf(fn () => $this->getJson('/'.ApiSurface::PREFIX.'/availability/'.$product->id.'/dates')->assertOk());

        $this->seedSlots($product, days: 20);
        $many = $this->queriesOf(fn () => $this->getJson('/'.ApiSurface::PREFIX.'/availability/'.$product->id.'/dates')->assertOk());

        $this->assertCount(
            count($few),
            $many,
            "Un calendario de 20 días cuesta más consultas que uno de 2: hay un precio resuelto por día.\n  ".
            implode("\n  ", array_diff($many, $few))
        );
    }

    /**
     * Catálogo mínimo pero completo: zona operativa, tarifa y productos con precio (sin precio, el
     * eager load de precios no se ejercería y la medición no valdría).
     *
     * @return list<TicketType>
     */
    private function catalogFixture(int $products): array
    {
        $zone = Zone::first() ?? Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);

        $rateId = (int) (RateType::first()?->id ?? RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id);

        $created = [];
        for ($i = 0; $i < $products; $i++) {
            $product = TicketType::create([
                'name' => ['es' => 'Producto '.$i], 'type' => TicketType::TYPE_PACK,
                'zone_id' => $zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
                'min_qty' => 8, 'is_sellable' => true, 'is_active' => true,
                'position' => (int) TicketType::max('position') + 1,
            ]);
            $product->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 1500]);
            $created[] = $product;
        }

        return $created;
    }

    /** Franjas vendibles del producto para los `$days` días siguientes (una por día, basta). */
    private function seedSlots(TicketType $product, int $days): void
    {
        for ($i = 1; $i <= $days; $i++) {
            $date = now()->addDays($i)->toDateString();

            Slot::firstOrCreate(
                ['zone_id' => $product->zone_id, 'date' => $date, 'start_time' => '10:00:00'],
                ['end_time' => '11:00:00', 'capacity' => 50, 'online_capacity' => 50],
            );
        }
    }

    private function attachAddons(TicketType $product, int $count): void
    {
        $rateId = (int) RateType::first()->id;

        for ($i = 0; $i < $count; $i++) {
            $addon = TicketType::create([
                'name' => ['es' => 'Complemento '.$product->id.'-'.$i], 'type' => TicketType::TYPE_ADDON,
                'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true,
                'position' => (int) TicketType::max('position') + 1,
            ]);
            $addon->prices()->create(['rate_type_id' => $rateId, 'amount_cents' => 500]);
            $product->configurableAddons()->attach($addon->id, [
                'quantity_mode' => 'fixed', 'position' => $i + 1,
            ]);
        }
    }
}
