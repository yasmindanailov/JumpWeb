<?php

namespace Tests\Feature\Api;

use App\Domain\Booking\Models\RateType;
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
