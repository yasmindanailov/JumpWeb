<?php

namespace Tests\Feature\Waiver;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PuertaSettings;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\Settings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 6 · waiver — los TRES modos (`DECISIONES #142`, spec §4.1) y su compatibilidad con el
 * interruptor de #216: una instalación existente NO cambia de conducta al desplegar.
 */
class WaiverSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function set(string $key, string $value, string $group = 'waiver'): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }

    public function test_without_any_row_the_mode_is_external_as_before(): void
    {
        $this->assertSame('externo', WaiverSettings::mode());
        $this->assertTrue(WaiverSettings::isEnabled());
        $this->assertFalse(WaiverSettings::isInternal());
        $this->assertTrue(PuertaSettings::waiverCheckEnabled());
    }

    public function test_the_legacy_switch_still_turns_the_check_off_when_no_mode_is_set(): void
    {
        $this->set('puerta.waiver_check_enabled', '0', 'puerta');

        $this->assertSame('desactivado', WaiverSettings::mode());
        $this->assertFalse(WaiverSettings::isEnabled());
        $this->assertFalse(PuertaSettings::waiverCheckEnabled());
    }

    public function test_an_explicit_mode_wins_over_the_legacy_switch(): void
    {
        $this->set('puerta.waiver_check_enabled', '0', 'puerta');
        $this->set('waiver.mode', 'interno');

        $this->assertSame('interno', WaiverSettings::mode());
        $this->assertTrue(WaiverSettings::isInternal());
        $this->assertTrue(PuertaSettings::waiverCheckEnabled());
    }

    public function test_an_invalid_mode_falls_back_to_the_derived_one(): void
    {
        $this->set('waiver.mode', 'lo-que-sea');
        $this->assertSame('externo', WaiverSettings::mode());

        $this->set('puerta.waiver_check_enabled', '0', 'puerta');
        $this->assertSame('desactivado', WaiverSettings::mode());
    }

    public function test_the_retention_period_is_read_defensively(): void
    {
        $this->assertNull(WaiverSettings::retentionMonths());
        $this->set('waiver.retention_months', '24');
        $this->assertSame(24, WaiverSettings::retentionMonths());
        $this->set('waiver.retention_months', '');
        $this->assertNull(WaiverSettings::retentionMonths());
    }

    // ─── La página de ajustes del panel ──────────────────────────────────────

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        foreach ([
            ['business.name', 'SaltoPark', 'business'],
            ['contact.email', 'hola@saltopark.example', 'contact'],
            ['sales.hold_minutes', '15', 'payment'],
            ['sales.purchase_horizon_months', '6', 'payment'],
            ['puerta.validate_rate_limit_per_minute', '100', 'puerta'],
            ['redsys_environment', 'test', 'payment'],
            ['redsys_currency', '978', 'payment'],
        ] as [$key, $value, $group]) {
            $this->set($key, $value, $group);
        }

        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    public function test_the_settings_page_hydrates_the_effective_mode_so_that_saving_does_not_change_behaviour(): void
    {
        $admin = $this->admin();
        $this->set('puerta.waiver_check_enabled', '0', 'puerta');

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->assertFormSet(['waiver.mode' => 'desactivado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('desactivado', Setting::value('waiver.mode'));
        $this->assertSame('desactivado', WaiverSettings::mode());
    }

    public function test_the_settings_page_saves_the_mode_and_the_retention_and_mirrors_the_legacy_switch(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->fillForm(['waiver.mode' => 'interno', 'waiver.retention_months' => 36])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('interno', Setting::value('waiver.mode'));
        $this->assertSame('36', Setting::value('waiver.retention_months'));
        $this->assertSame(36, WaiverSettings::retentionMonths());
        $this->assertSame('1', Setting::value('puerta.waiver_check_enabled'), 'el espejo heredado sigue al modo');

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->fillForm(['waiver.mode' => 'desactivado', 'waiver.retention_months' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0', Setting::value('puerta.waiver_check_enabled'));
        $this->assertNull(WaiverSettings::retentionMonths());
    }

    public function test_the_settings_page_rejects_a_retention_out_of_range(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['waiver.mode' => 'interno', 'waiver.retention_months' => 0])
            ->call('save')
            ->assertHasFormErrors(['waiver.retention_months']);
    }
}
