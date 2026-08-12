<?php

namespace Tests\Feature\Support;

use App\Domain\Platform\Models\Setting;
use App\Support\CatalogSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Helper defensivo del umbral del buscador del catálogo (#226): tipo + rango + fallback no
 * destructivo, igual que PaymentSettings/PuertaSettings/ThemeSettings.
 */
class CatalogSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_when_unset(): void
    {
        $this->assertSame(CatalogSettings::SEARCH_MIN_ITEMS_DEFAULT, CatalogSettings::searchMinItems());
    }

    public function test_reads_a_configured_value(): void
    {
        Setting::updateOrCreate(['key' => CatalogSettings::SEARCH_MIN_ITEMS_KEY], ['value' => '5', 'group' => 'catalog']);
        $this->assertSame(5, CatalogSettings::searchMinItems());
    }

    public function test_clamps_below_min_and_above_max(): void
    {
        Setting::updateOrCreate(['key' => CatalogSettings::SEARCH_MIN_ITEMS_KEY], ['value' => '-7', 'group' => 'catalog']);
        $this->assertSame(CatalogSettings::SEARCH_MIN_ITEMS_MIN, CatalogSettings::searchMinItems());

        Setting::updateOrCreate(['key' => CatalogSettings::SEARCH_MIN_ITEMS_KEY], ['value' => '999999', 'group' => 'catalog']);
        $this->assertSame(CatalogSettings::SEARCH_MIN_ITEMS_MAX, CatalogSettings::searchMinItems());
    }

    public function test_corrupt_value_falls_back_to_default(): void
    {
        Setting::updateOrCreate(['key' => CatalogSettings::SEARCH_MIN_ITEMS_KEY], ['value' => 'abc', 'group' => 'catalog']);
        $this->assertSame(CatalogSettings::SEARCH_MIN_ITEMS_DEFAULT, CatalogSettings::searchMinItems());

        Setting::updateOrCreate(['key' => CatalogSettings::SEARCH_MIN_ITEMS_KEY], ['value' => '', 'group' => 'catalog']);
        $this->assertSame(CatalogSettings::SEARCH_MIN_ITEMS_DEFAULT, CatalogSettings::searchMinItems());
    }

    public function test_zero_is_a_valid_always_on_threshold(): void
    {
        Setting::updateOrCreate(['key' => CatalogSettings::SEARCH_MIN_ITEMS_KEY], ['value' => '0', 'group' => 'catalog']);
        $this->assertSame(0, CatalogSettings::searchMinItems());
    }
}
