<?php

namespace Tests\Feature\Admin\Puerta;

use App\Models\Setting;
use App\Support\PuertaSettings;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.1a — Helper defensivo del setting de rate limit de la puerta (#126).
 *
 * Patrón espejo de `PaymentSettingsTest` (#113 M2): cualquier valor que se escriba
 * desde el panel admin debe ser tolerado sin reventar la puerta en operación real.
 */
class PuertaSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeded_value_is_100(): void
    {
        $this->seed(LandingContentSeeder::class);
        $this->assertSame('100', Setting::value('puerta.validate_rate_limit_per_minute'));
        $this->assertSame(100, PuertaSettings::validateRateLimit());
    }

    public function test_helper_returns_default_when_setting_missing(): void
    {
        // No sembramos LandingContentSeeder; el setting no existe.
        $this->assertSame(100, PuertaSettings::validateRateLimit());
    }

    public function test_helper_respects_admin_set_value_within_range(): void
    {
        Setting::create([
            'key' => 'puerta.validate_rate_limit_per_minute',
            'value' => '250',
            'group' => 'puerta',
        ]);
        $this->assertSame(250, PuertaSettings::validateRateLimit());
    }

    public function test_helper_falls_back_for_non_numeric(): void
    {
        // El panel admin acepta string; si se escribe basura, NO debe reventar la puerta.
        Setting::create([
            'key' => 'puerta.validate_rate_limit_per_minute',
            'value' => 'abc',
            'group' => 'puerta',
        ]);
        $this->assertSame(100, PuertaSettings::validateRateLimit(),
            'Valor no numérico → fallback al default.');
    }

    public function test_helper_falls_back_for_out_of_range(): void
    {
        // Por encima del max: protección anti-DoS de la propia tabla audit_logs.
        Setting::create([
            'key' => 'puerta.validate_rate_limit_per_minute',
            'value' => '99999999',
            'group' => 'puerta',
        ]);
        $this->assertSame(100, PuertaSettings::validateRateLimit());

        // Por debajo del min: 0 dejaría la puerta inutilizable.
        Setting::where('key', 'puerta.validate_rate_limit_per_minute')->update(['value' => '0']);
        $this->assertSame(100, PuertaSettings::validateRateLimit());

        // Negativo:
        Setting::where('key', 'puerta.validate_rate_limit_per_minute')->update(['value' => '-50']);
        $this->assertSame(100, PuertaSettings::validateRateLimit());
    }

    public function test_constants_are_documented_correctly(): void
    {
        $this->assertSame(100, PuertaSettings::VALIDATE_RATE_LIMIT_DEFAULT);
        $this->assertSame(1, PuertaSettings::VALIDATE_RATE_LIMIT_MIN);
        $this->assertSame(10000, PuertaSettings::VALIDATE_RATE_LIMIT_MAX);
    }
}
