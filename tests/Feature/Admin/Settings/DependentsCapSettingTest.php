<?php

namespace Tests\Feature\Admin\Settings;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentSettings;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 6 · menores a cargo — el tope por cuenta es DATA-DRIVEN (`specs/menores-a-cargo.md` §4.5): se
 * edita en Ajustes → «Puerta» y lo lee el dominio (`DependentSettings`) sin pasar por ninguna
 * constante. Y el panel no puede abrirlo fuera del rango que el dominio acepta: un valor que el
 * panel guardara y el dominio ignorara sería un ajuste que miente.
 */
class DependentsCapSettingTest extends TestCase
{
    use RefreshDatabase;

    private function page(): Testable
    {
        $this->seed(DatabaseSeeder::class);

        return Livewire::actingAs(User::where('email', 'admin@jumpweb.test')->firstOrFail())->test(Settings::class);
    }

    public function test_the_cap_saved_from_the_panel_is_what_the_domain_reads(): void
    {
        $page = $this->page();
        $this->assertSame(20, DependentSettings::maxPerAccount(), 'sin fila: el tope de la spec');

        $page->fillForm([DependentSettings::KEY_MAX_PER_ACCOUNT => 7])->call('save')->assertHasNoFormErrors();

        $this->assertSame('7', Setting::value(DependentSettings::KEY_MAX_PER_ACCOUNT));
        $this->assertSame(7, DependentSettings::maxPerAccount());
    }

    public function test_the_panel_refuses_a_cap_outside_the_domain_range(): void
    {
        $page = $this->page();

        foreach ([0, 101] as $bad) {
            $page->fillForm([DependentSettings::KEY_MAX_PER_ACCOUNT => $bad])
                ->call('save')
                ->assertHasFormErrors([DependentSettings::KEY_MAX_PER_ACCOUNT]);
        }

        $this->assertNull(Setting::value(DependentSettings::KEY_MAX_PER_ACCOUNT), 'nada se guardó');
        $this->assertSame(20, DependentSettings::maxPerAccount());
    }

    public function test_an_empty_field_keeps_the_default_cap(): void
    {
        $this->page()->fillForm([DependentSettings::KEY_MAX_PER_ACCOUNT => ''])->call('save')->assertHasNoFormErrors();

        $this->assertSame(20, DependentSettings::maxPerAccount());
    }
}
