<?php

namespace Tests\Feature\Admin\Settings;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Surveys\SurveySettings;
use App\Filament\Pages\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T3 de `specs/encuestas.md` §4.3 — el plazo entre dos correos de encuesta a la misma persona es DATA-DRIVEN: se
 * edita en Ajustes → «Puerta» y lo lee el comando (`SurveySettings`) sin constante en medio; el panel no guarda
 * nada fuera del rango que el dominio acepta.
 */
class SurveysCooldownSettingTest extends TestCase
{
    use RefreshDatabase;

    private function page(): Testable
    {
        $this->seed(DatabaseSeeder::class);

        return Livewire::actingAs(User::where('email', 'admin@jumpweb.test')->firstOrFail())->test(Settings::class);
    }

    public function test_the_cooldown_saved_from_the_panel_is_what_the_command_reads(): void
    {
        $page = $this->page();
        $this->assertSame(30, SurveySettings::cooldownDays(), 'sin fila: los 30 días de la spec');

        $page->fillForm([SurveySettings::KEY_COOLDOWN_DAYS => 7])->call('save')->assertHasNoFormErrors();

        $this->assertSame('7', Setting::value(SurveySettings::KEY_COOLDOWN_DAYS));
        $this->assertSame(7, SurveySettings::cooldownDays());
    }

    public function test_the_panel_refuses_a_cooldown_outside_the_domain_range_and_an_empty_field_keeps_the_default(): void
    {
        $page = $this->page();

        foreach ([-1, 366] as $bad) {
            $page->fillForm([SurveySettings::KEY_COOLDOWN_DAYS => $bad])->call('save')->assertHasFormErrors([SurveySettings::KEY_COOLDOWN_DAYS]);
        }
        $this->assertNull(Setting::value(SurveySettings::KEY_COOLDOWN_DAYS), 'nada se guardó');

        $page->fillForm([SurveySettings::KEY_COOLDOWN_DAYS => ''])->call('save')->assertHasNoFormErrors();
        $this->assertSame(30, SurveySettings::cooldownDays());

        // Cero es válido: «sin plazo».
        $page->fillForm([SurveySettings::KEY_COOLDOWN_DAYS => 0])->call('save')->assertHasNoFormErrors();
        $this->assertSame(0, SurveySettings::cooldownDays());
    }
}
