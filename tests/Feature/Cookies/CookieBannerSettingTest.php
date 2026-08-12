<?php

namespace Tests\Feature\Cookies;

use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #219 — Helper defensivo del toggle del banner (`cookies.banner_enabled`). Default ON; solo el
 * literal '0' lo apaga; un valor corrupto NO lo desactiva (fallback no destructivo, patrón
 * `PuertaSettings::waiverCheckEnabled`).
 */
class CookieBannerSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_is_on_without_setting(): void
    {
        $this->assertTrue(CookieConsent::bannerEnabled());
    }

    public function test_explicit_zero_disables(): void
    {
        Setting::updateOrCreate(['key' => 'cookies.banner_enabled'], ['value' => '0', 'group' => 'cookies']);

        $this->assertFalse(CookieConsent::bannerEnabled());
    }

    public function test_garbage_value_stays_on(): void
    {
        Setting::updateOrCreate(['key' => 'cookies.banner_enabled'], ['value' => 'xyz', 'group' => 'cookies']);

        $this->assertTrue(CookieConsent::bannerEnabled());
    }

    public function test_seeder_enables_banner_by_default(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->assertTrue(CookieConsent::bannerEnabled());
        $this->assertSame('1', Setting::value('cookies.banner_enabled'));
    }
}
