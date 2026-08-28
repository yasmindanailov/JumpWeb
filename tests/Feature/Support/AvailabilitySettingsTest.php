<?php

namespace Tests\Feature\Support;

use App\Domain\Booking\Services\AvailabilitySettings;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Helper defensivo del umbral del aviso «casi llena» (`DECISIONES #239`): tipo + rango + fallback no
 * destructivo, igual que `CatalogSettings`/`PaymentSettings`/`PuertaSettings`.
 *
 * ⚠️ **Lo que de verdad importa aquí es el `0`**, y por eso tiene dos casos propios: no significa
 * «avisar cuando no queden plazas» sino **no avisar nunca**. Si esa rama se pierde, un operador que
 * apaga el aviso lo ve aparecer justo en la franja donde más chirría.
 */
class AvailabilitySettingsTest extends TestCase
{
    use RefreshDatabase;

    /** ⚠️ No se puede llamar `put()`: colisiona con el helper HTTP de `TestCase` (error FATAL). */
    private function setThreshold(string $value): void
    {
        Setting::updateOrCreate(['key' => AvailabilitySettings::LOW_MAX_KEY], ['value' => $value, 'group' => 'booking']);
    }

    public function test_default_when_unset(): void
    {
        $this->assertSame(AvailabilitySettings::LOW_MAX_DEFAULT, AvailabilitySettings::lowMax());
    }

    public function test_reads_a_configured_value(): void
    {
        $this->setThreshold('3');

        $this->assertSame(3, AvailabilitySettings::lowMax());
    }

    public function test_clamps_below_min_and_above_max(): void
    {
        $this->setThreshold('-7');
        $this->assertSame(AvailabilitySettings::LOW_MAX_MIN, AvailabilitySettings::lowMax());

        $this->setThreshold('999999');
        $this->assertSame(AvailabilitySettings::LOW_MAX_MAX, AvailabilitySettings::lowMax());
    }

    public function test_corrupt_value_falls_back_to_default(): void
    {
        $this->setThreshold('abc');
        $this->assertSame(AvailabilitySettings::LOW_MAX_DEFAULT, AvailabilitySettings::lowMax());

        $this->setThreshold('');
        $this->assertSame(AvailabilitySettings::LOW_MAX_DEFAULT, AvailabilitySettings::lowMax());
    }

    public function test_zero_is_a_valid_value_and_means_never_warn(): void
    {
        $this->setThreshold('0');

        $this->assertSame(0, AvailabilitySettings::lowMax());
    }

    // ── El predicado, que es lo que las dos superficies comparten ─────────────────────────────

    /** El borde ENTRA: el operador escribe «avisa cuando queden 8 o menos», no «menos de 8». */
    public function test_the_threshold_is_inclusive(): void
    {
        $this->assertTrue(AvailabilitySettings::isLow(8, 8));
        $this->assertFalse(AvailabilitySettings::isLow(9, 8));
        $this->assertTrue(AvailabilitySettings::isLow(1, 8));
    }

    /**
     * ⚠️ **Con `0` no se avisa NI SIQUIERA sin plazas.** Sin la puerta explícita, `0 <= 0` daría
     * `true` y el aviso aparecería exactamente donde el operador pidió silencio.
     */
    public function test_zero_silences_the_warning_even_with_no_seats_left(): void
    {
        $this->assertFalse(AvailabilitySettings::isLow(0, 0));
        $this->assertFalse(AvailabilitySettings::isLow(3, 0));
    }

    /** Sin umbral explícito lee el ajuste, para que el panel y el cajón no puedan discrepar. */
    public function test_without_an_explicit_threshold_it_reads_the_setting(): void
    {
        $this->setThreshold('2');

        $this->assertTrue(AvailabilitySettings::isLow(2));
        $this->assertFalse(AvailabilitySettings::isLow(3));
    }
}
