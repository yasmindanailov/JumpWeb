<?php

namespace Tests\Feature\Instancia;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\VenueRule;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **El ANFITRIÓN MÍNIMO de `/normas`** — lo que el producto sirve sin paquete de instancia (F5 · T2b,
 * `DECISIONES #655`). Es marcado del PRODUCTO, y por eso aquí SÍ se mira el HTML (`#649`): tiene que
 * pintar TODO lo que el contrato de vista le da —cada norma, agrupada y sin agrupar, con su porqué solo
 * si lo tiene; la escala; la chapa del descargo si la instalación lo usa; la fecha de revisión—.
 *
 * ⚠️ Es también el sujeto de los mutantes de vista de `mutar-normas.py`: una norma sin momento que se
 * calla, un porqué que se pinta vacío.
 */
class AnfitrionNormasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    private function html(): string
    {
        return (string) $this->get('/normas')->assertOk()->assertViewIs('anfitrion.normas')->getContent();
    }

    public function test_it_paints_every_rule_grouped_by_moment_and_the_ungrouped_at_the_end(): void
    {
        VenueRule::create(['name' => ['es' => 'NormaSinMomentoZZ'], 'description' => ['es' => 'Suelta.'], 'position' => 99, 'is_active' => true]);

        $html = $this->html();

        $this->assertStringContainsString(__('site.rules_headline'), $html);
        preg_match_all('#class="rules-group__label">([^<]*)<#', $html, $m);
        $this->assertSame(
            [__('site.rules_moment.before.label'), __('site.rules_moment.gate.label'), __('site.rules_moment.inside.label')],
            array_map(trim(...), $m[1]),
            'los grupos no salen en el orden de la visita',
        );

        $this->assertSame(VenueRule::where('is_active', true)->count(), substr_count($html, '<li class="rule-card">'), 'no se pinta una tarjeta por norma activa');
        $this->assertStringContainsString('NormaSinMomentoZZ', $html, 'una norma sin momento desapareció de la página');
        $this->assertStringContainsString(__('site.rules_other'), $html, 'las sin momento no van bajo su rótulo');
        $this->assertGreaterThan(strpos($html, 'rules-inside'), strpos($html, 'NormaSinMomentoZZ'), 'las sin momento no van al final');
    }

    /** El porqué se pinta solo si está: uno por norma que lo tenga, ni uno más. */
    public function test_the_reason_is_painted_only_when_there_is_one(): void
    {
        $html = $this->html();

        $this->assertSame(
            VenueRule::where('is_active', true)->whereNotNull('reason')->count(),
            substr_count($html, 'rule-card__why'),
            'se pinta un porqué por cada norma que lo tiene, ni uno más',
        );
    }

    /** La fecha es la de la norma, escrita por `LocalDate` (`#656`): en inglés, sin el «de». */
    public function test_the_updated_line_is_written_in_the_language(): void
    {
        Carbon::setTestNow('2026-09-12');
        VenueRule::query()->update(['updated_at' => Carbon::parse('2026-03-04')]);

        $this->assertStringContainsString(__('site.rules_updated', ['fecha' => 'marzo de 2026']), $this->html());

        $this->withSession(['locale' => 'en']);
        app()->setLocale('en');
        $en = $this->html();
        $this->assertStringContainsString('March 2026', $en);
        $this->assertStringNotContainsString('March de 2026', $en, 'la fecha inglesa lleva el «de» del español');
    }

    public function test_the_height_scale_and_the_waiver_plate_follow_the_data(): void
    {
        $zonas = Zone::where('is_active', true)->where('show_in_landing', true)->orderBy('position')->get();
        $zonas[0]?->forceFill(['height_min_cm' => 130])->save();

        $html = $this->html();
        $this->assertStringContainsString('rules-axis__band', $html, 'con una zona con altura no se pinta la escala');
        $this->assertStringContainsString('rules-waiver', $html, 'con la exención en uso no se pinta la chapa');

        Zone::query()->update(['height_min_cm' => null, 'height_max_cm' => null]);
        Setting::query()->updateOrCreate(['key' => 'waiver.mode'], ['value' => WaiverSettings::MODE_OFF]);
        Setting::flushMemo();

        $html = $this->html();
        $this->assertStringNotContainsString('rules-axis', $html, 'se pinta un eje vacío');
        $this->assertStringNotContainsString('rules-waiver', $html, 'se ofrece un descargo que la instalación no usa');
    }
}
