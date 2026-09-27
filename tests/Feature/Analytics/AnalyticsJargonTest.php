<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Widgets\Analytics\DataQualityWidget;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **El cuadro habla en llano** (glosario de `specs/analitica-para-decidir.md` §4.11; T3a, `#759`). Medido el 28-09: en
 * «Conversión» había seis rótulos de jerga (sesiones, primer toque, fuera del recuento, bots, internas, embudo) y en
 * «Fiestas», uno. La jerga técnica solo vive en «Calidad del dato», plegada al final de Marketing.
 *
 * Dos redes: las CLAVES de idioma del cuadro (cubren también las filas que solo se pintan con datos, como el canal de un
 * pedido) y lo PINTADO de cada pestaña (cubre lo que un widget escriba sin pasar por el idioma). Los patrones, a mano.
 */
class AnalyticsJargonTest extends TestCase
{
    use RefreshDatabase;

    /** La jerga de §4.11, como palabra entera y sin mayúsculas. */
    private const JARGON = [
        '/\bsesi(?:ón|ones)\b/iu',
        '/\bprimer toque\b/iu',
        '/\búltimo toque\b/iu',
        '/\banterior a la medición\b/iu',
        '/\bsistema\b/iu',
        '/\bfuera del recuento\b/iu',
        '/\bbots?\b/iu',
        '/\binternas?\b/iu',
        '/\bembudo\b/iu',
    ];

    /** «Iniciar sesión» es entrar con la cuenta, no la sesión de la analítica: no es jerga. */
    private const NOT_JARGON = '/\binici(?:ar|ó|a|en|aron) sesión\b/iu';

    /** Las claves de «Calidad del dato» y de lo que solo se lee allí: el único sitio con permiso para la jerga. */
    private const QUALITY_KEYS = [
        'traffic.identified', 'traffic.excluded', 'traffic.excluded_value', 'traffic.quality_heading', 'traffic.quality_note',
        'traffic.rejected_heading', 'traffic.rejected_total', 'traffic.dropped_events', 'traffic.rejected_reason.*',
        'how.traffic.identified', 'how.traffic.excluded',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        app()->setLocale('es');
    }

    public function test_no_label_of_the_dashboard_speaks_jargon_outside_data_quality(): void
    {
        /** @var array<string, mixed> $labels */
        $labels = trans('admin.analytics', [], 'es');
        $this->assertIsArray($labels);
        $offenders = [];

        foreach (Arr::dot($labels) as $key => $text) {
            if (! is_string($text) || self::isQuality((string) $key)) {
                continue;
            }
            if (($word = self::jargonIn($text)) !== null) {
                $offenders[] = "admin.analytics.{$key}: «{$word}» en «{$text}»";
            }
        }

        $this->assertSame([], $offenders, 'jerga fuera de «Calidad del dato» (§4.11)');
    }

    /** Cada pestaña, pintada: la cabecera, las pestañas, el selector y cada widget con sus tarjetas, gráficos y tablas. */
    public function test_no_tab_shows_jargon_outside_data_quality(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);
        $this->actingAs($admin);

        foreach (AnalyticsPage::TABS as $tab => $widgets) {
            $page = (string) $this->get(AnalyticsPage::getUrl([AnalyticsPage::TAB_QUERY_KEY => $tab]))->assertOk()->getContent();
            $this->assertNull(self::jargonIn(self::text($page)), "la página con «{$tab}» abierta");

            foreach ($widgets as $widget) {
                if ($widget === DataQualityWidget::class) {
                    continue;
                }
                $html = Livewire::test($widget, ['pageFilters' => ['period' => 'this_month']])->html();
                $this->assertNull(self::jargonIn(self::text($html)), "{$widget} en «{$tab}»");
            }
        }
    }

    /** La guarda ve la jerga donde está: si no, un verde no diría nada. */
    public function test_the_guard_sees_the_jargon_where_it_lives(): void
    {
        $this->assertSame('Sesiones', self::jargonIn('Sesiones identificadas'));
        $this->assertSame('embudo', self::jargonIn('El embudo: reservas'));
        $this->assertSame('Sistema', self::jargonIn('Sistema'));
        $this->assertNull(self::jargonIn('Solo las de quien aceptó las cookies e inició sesión.'));
        $this->assertNull(self::jargonIn('El sistemático'), 'palabra entera');
        $this->assertTrue(self::isQuality('traffic.rejected_reason.pii'));
        $this->assertFalse(self::isQuality('traffic.first_touch'));
    }

    private static function isQuality(string $key): bool
    {
        foreach (self::QUALITY_KEYS as $pattern) {
            if (fnmatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    private static function jargonIn(string $text): ?string
    {
        $text = (string) preg_replace(self::NOT_JARGON, '', $text);

        foreach (self::JARGON as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return $m[0];
            }
        }

        return null;
    }

    /** El texto que se lee: sin etiquetas, sin guiones de script ni estilos. */
    private static function text(string $html): string
    {
        $html = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
