<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Drivers;
use App\Domain\Platform\Services\Analytics\Pixels;
use App\Filament\Pages\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La herramienta de análisis en «Ajustes»** (`specs/analitica.md` §4.3, T3a·2): el driver arranca en
 * «ninguno», un driver sin sus datos no se guarda (y se dice), y con ellos queda escrito y activo.
 */
class AnalyticsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::where('email', 'admin@jumpweb.test')->firstOrFail();
    }

    public function test_a_fresh_installation_has_no_driver_and_saves_as_none(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Settings::class)
            ->assertFormSet([Drivers::KEY_DRIVER => Drivers::NONE])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushMemo();
        $this->assertSame(Drivers::NONE, Drivers::active());
    }

    public function test_posthog_without_its_token_is_refused_and_nothing_is_written(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Settings::class)
            ->fillForm([Drivers::KEY_DRIVER => Drivers::POSTHOG, Drivers::KEY_POSTHOG_PROJECT => ''])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.settings.analytics_posthog_requires_token'));

        Setting::flushMemo();
        $this->assertNotSame(Drivers::POSTHOG, Setting::value(Drivers::KEY_DRIVER));
        $this->assertSame(Drivers::NONE, Drivers::active());
    }

    public function test_a_malformed_token_fails_the_field(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Settings::class)
            ->fillForm([Drivers::KEY_DRIVER => Drivers::POSTHOG, Drivers::KEY_POSTHOG_PROJECT => 'no-es-un-token'])
            ->call('save')
            ->assertHasFormErrors([Drivers::KEY_POSTHOG_PROJECT]);
    }

    public function test_posthog_with_its_token_is_saved_and_active(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Settings::class)
            ->fillForm([Drivers::KEY_DRIVER => Drivers::POSTHOG, Drivers::KEY_POSTHOG_PROJECT => 'phc_abcdefghijklmnopqrstuvwxyz0123'])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushMemo();
        $this->assertSame(Drivers::POSTHOG, Drivers::active());
        $this->assertSame('analytics', Setting::where('key', Drivers::KEY_DRIVER)->value('group'));
    }

    public function test_matomo_needs_its_host_and_site_id_and_refuses_http(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Settings::class)
            ->fillForm([Drivers::KEY_DRIVER => Drivers::MATOMO, Drivers::KEY_MATOMO_HOST => 'https://stats.parque.es', Drivers::KEY_MATOMO_SITE_ID => ''])
            ->call('save')
            ->assertNotified(__('admin.settings.analytics_matomo_requires_host'));

        Livewire::actingAs($admin)->test(Settings::class)
            ->fillForm([Drivers::KEY_DRIVER => Drivers::MATOMO, Drivers::KEY_MATOMO_HOST => 'http://stats.parque.es', Drivers::KEY_MATOMO_SITE_ID => '7'])
            ->call('save')
            ->assertHasFormErrors([Drivers::KEY_MATOMO_HOST]);

        Livewire::actingAs($admin)->test(Settings::class)
            ->fillForm([Drivers::KEY_DRIVER => Drivers::MATOMO, Drivers::KEY_MATOMO_HOST => 'https://stats.parque.es', Drivers::KEY_MATOMO_SITE_ID => '7'])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushMemo();
        $this->assertSame(['driver' => 'matomo', 'key' => '7', 'host' => 'https://stats.parque.es'], Drivers::config());
    }

    /** T3b·1: los píxeles de anuncios, ids públicos con su forma; vacío es «sin píxel». */
    public function test_the_pixel_ids_are_saved_with_their_shape_and_a_malformed_one_fails_its_field(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Settings::class)
            ->assertFormSet([Pixels::KEY_GOOGLE_ADS_ID => null, Pixels::KEY_META_PIXEL_ID => null])
            ->fillForm([Pixels::KEY_GOOGLE_ADS_ID => 'G-ABC123', Pixels::KEY_META_PIXEL_ID => 'pixel', Pixels::KEY_TIKTOK_PIXEL_ID => 'c9abc'])
            ->call('save')
            ->assertHasFormErrors([Pixels::KEY_GOOGLE_ADS_ID, Pixels::KEY_META_PIXEL_ID, Pixels::KEY_TIKTOK_PIXEL_ID]);

        Livewire::actingAs($admin)->test(Settings::class)
            ->fillForm([
                Pixels::KEY_GOOGLE_ADS_ID => 'AW-123456789', Pixels::KEY_GOOGLE_ADS_LABEL => 'AbCdEfGh',
                Pixels::KEY_META_PIXEL_ID => '1234567890123456', Pixels::KEY_TIKTOK_PIXEL_ID => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushMemo();
        $this->assertSame([Pixels::GOOGLE_ADS => 'AW-123456789/AbCdEfGh', Pixels::META => '1234567890123456'], Pixels::config());
    }
}
