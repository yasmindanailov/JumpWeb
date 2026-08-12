<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Services\SpecialRateLabelBackfill;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backfill del label de la tarifa especial (migración 2026_07_07_000001) para llevar el copy nuevo
 * «Findes y festivos» a producción SIN resembrar. Patrón `legacy-data-repair`: lógica testeable,
 * idempotente y QUIRÚRGICA (solo la tarifa `special`, solo si su label sigue siendo el default viejo).
 */
class SpecialRateLabelBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function backfill(): SpecialRateLabelBackfill
    {
        return new SpecialRateLabelBackfill;
    }

    /** Crea la tarifa `special` con un label dado (simula el estado de una BD ya sembrada). */
    private function seedSpecialWithLabel(array $label): RateType
    {
        return RateType::create([
            'key' => RateType::KEY_SPECIAL,
            'label' => $label,
            'is_special' => true,
            'weekdays' => [0, 6],
            'priority' => 10,
            'is_active' => true,
        ]);
    }

    public function test_apply_renames_the_old_default_label(): void
    {
        // Simula PRODUCCIÓN: la tarifa existe con el default viejo (sembrada en go-live, no resembrada).
        $rate = $this->seedSpecialWithLabel(SpecialRateLabelBackfill::oldLabel());

        $affected = $this->backfill()->apply();

        $this->assertSame(1, $affected);
        $this->assertEquals(SpecialRateLabelBackfill::newLabel(), $rate->fresh()->label);
    }

    public function test_apply_is_idempotent(): void
    {
        $this->seedSpecialWithLabel(SpecialRateLabelBackfill::oldLabel());

        $this->assertSame(1, $this->backfill()->apply());  // 1.ª pasada renombra
        $this->assertSame(0, $this->backfill()->apply());  // 2.ª pasada NO-OP (ya es el nuevo)
    }

    public function test_apply_does_not_clobber_a_customized_label(): void
    {
        // Si la clienta ya personalizó el label desde el panel, el backfill NO lo pisa.
        $custom = ['es' => 'Tarifa de finde a mi manera', 'en' => 'My weekend rate', 'fr' => 'Mon tarif week-end'];
        $rate = $this->seedSpecialWithLabel($custom);

        $this->assertSame(0, $this->backfill()->apply());
        $this->assertEquals($custom, $rate->fresh()->label);
    }

    public function test_apply_is_noop_on_a_fresh_install(): void
    {
        // Sin tarifas (estado al correr la migración antes de los seeders) → 0 filas.
        $this->assertSame(0, RateType::count());
        $this->assertSame(0, $this->backfill()->apply());
    }

    public function test_revert_restores_the_old_default(): void
    {
        $rate = $this->seedSpecialWithLabel(SpecialRateLabelBackfill::newLabel());

        $this->assertSame(1, $this->backfill()->revert());
        $this->assertEquals(SpecialRateLabelBackfill::oldLabel(), $rate->fresh()->label);
    }

    public function test_new_label_matches_the_seeder(): void
    {
        // GUARDA ANTI-DRIFT: el label congelado del backfill DEBE ser idéntico al que produce el
        // seeder, para que producción reciba exactamente el mismo copy que dev/instalación nueva.
        $this->seed(LandingContentSeeder::class);

        $this->assertEquals(
            RateType::where('key', RateType::KEY_SPECIAL)->first()->label,
            SpecialRateLabelBackfill::newLabel(),
        );
    }
}
