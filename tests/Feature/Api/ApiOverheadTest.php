<?php

namespace Tests\Feature\Api;

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

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->getJson('/'.ApiSurface::PREFIX.'/__test/empty')->assertOk();

        $this->assertLessThanOrEqual(
            self::QUERY_BUDGET,
            count($queries),
            'El stack de middleware de la API consulta la base de datos más de lo esperado. '.
            "No subas el presupuesto sin entender por qué:\n  ".implode("\n  ", $queries)
        );
    }
}
