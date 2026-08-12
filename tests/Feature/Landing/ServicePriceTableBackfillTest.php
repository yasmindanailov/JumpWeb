<?php

namespace Tests\Feature\Landing;

use App\Models\LandingService;
use App\Support\ServicePriceTableBackfill;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backfill de `price_table` (migración 2026_06_17_000001) para llevar las tablas de tarifas de los
 * servicios a producción SIN resembrar. Patrón `legacy-data-repair`: lógica testeable, idempotente y
 * QUIRÚRGICA (solo `price_table`, solo 2 slugs, solo donde está NULL; nada más se toca).
 */
class ServicePriceTableBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function backfill(): ServicePriceTableBackfill
    {
        return new ServicePriceTableBackfill;
    }

    public function test_apply_fills_a_seeded_service_whose_price_table_is_null(): void
    {
        // Simula PRODUCCIÓN: el servicio existe (sembrado en go-live) pero `price_table` quedó NULL
        // (la columna se añadió por migración, el deploy no resiembra).
        $svc = LandingService::create([
            'slug' => 'excursionescolegio',
            'title' => ['es' => 'Excursiones de colegio'],
            'is_active' => true,
        ]);
        $this->assertNull($svc->price_table);

        $affected = $this->backfill()->apply();

        $this->assertSame(1, $affected);
        $this->assertEquals(
            ServicePriceTableBackfill::tables()['excursionescolegio'],
            $svc->fresh()->price_table,
        );
    }

    public function test_apply_is_idempotent(): void
    {
        LandingService::create(['slug' => 'excursionescolegio', 'title' => ['es' => 'X'], 'is_active' => true]);

        $this->assertSame(1, $this->backfill()->apply());  // 1.ª pasada rellena
        $this->assertSame(0, $this->backfill()->apply());  // 2.ª pasada NO-OP (ya no hay NULLs)
    }

    public function test_apply_never_overwrites_an_existing_price_table(): void
    {
        // Si `price_table` ya tiene algo, el backfill NO lo pisa (whereNull).
        $custom = ['unit' => 'kids', 'zones' => [['label' => 'Custom', 'accent' => 'kids', 'durations' => []]]];
        $svc = LandingService::create([
            'slug' => 'excursionescolegio', 'title' => ['es' => 'X'], 'is_active' => true,
            'price_table' => $custom,
        ]);

        $this->assertSame(0, $this->backfill()->apply());
        $this->assertEquals($custom, $svc->fresh()->price_table);
    }

    public function test_apply_does_not_touch_other_services(): void
    {
        $colegio = LandingService::create(['slug' => 'excursionescolegio', 'title' => ['es' => 'X'], 'is_active' => true]);
        // Un servicio FUERA de la lista, editado en vivo: debe quedar 100% intacto.
        $otro = LandingService::create([
            'slug' => 'sesionadultos',
            'title' => ['es' => 'Mi título editado en el panel'],
            'body' => ['es' => 'Texto que NO se debe tocar'],
            'is_active' => true,
        ]);

        $this->backfill()->apply();

        $this->assertNotNull($colegio->fresh()->price_table);     // el gestionado SÍ se rellena
        $otro = $otro->fresh();
        $this->assertNull($otro->price_table);                    // el ajeno NO se toca
        $this->assertEquals(['es' => 'Mi título editado en el panel'], $otro->title);
        $this->assertEquals(['es' => 'Texto que NO se debe tocar'], $otro->body);
    }

    public function test_revert_clears_only_the_managed_slugs(): void
    {
        $colegio = LandingService::create([
            'slug' => 'excursionescolegio', 'title' => ['es' => 'X'], 'is_active' => true,
            'price_table' => ServicePriceTableBackfill::tables()['excursionescolegio'],
        ]);
        $otro = LandingService::create([
            'slug' => 'sesionadultos', 'title' => ['es' => 'Y'], 'is_active' => true,
            'price_table' => ['unit' => 'people', 'zones' => []],
        ]);

        $this->backfill()->revert();

        $this->assertNull($colegio->fresh()->price_table);                                   // gestionado → vaciado
        $this->assertEquals(['unit' => 'people', 'zones' => []], $otro->fresh()->price_table); // ajeno → intacto
    }

    public function test_backfill_data_matches_the_seeder(): void
    {
        // GUARDA ANTI-DRIFT: el snapshot congelado del backfill DEBE ser idéntico al que produce el
        // seeder, para que producción reciba exactamente las mismas tablas que dev/instalación nueva.
        $this->seed(LandingContentSeeder::class);

        $this->assertEquals(
            LandingService::where('slug', 'excursionescolegio')->first()->price_table,
            ServicePriceTableBackfill::tables()['excursionescolegio'],
        );
        $this->assertEquals(
            LandingService::where('slug', 'teambuilding')->first()->price_table,
            ServicePriceTableBackfill::tables()['teambuilding'],
        );
    }
}
