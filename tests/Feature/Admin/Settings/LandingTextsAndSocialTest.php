<?php

namespace Tests\Feature\Admin\Settings;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\Settings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * #215 — Textos de la landing editables por idioma (eslogan, coletilla del copyright, título web)
 * + feed social de social «en directo». Cubre: guardado/saneo en el panel, override y
 * fallback en la web, render del iframe del feed, CSP y subtítulo «Panel de Control».
 */
class LandingTextsAndSocialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        // Baseline mínimo para los campos obligatorios del formulario de Settings.
        foreach ([
            ['business.name', 'SaltoPark', 'business'],
            ['contact.email', 'hola@saltopark.example', 'contact'],
            ['sales.hold_minutes', '15', 'payment'],
            ['sales.purchase_horizon_months', '6', 'payment'],
            ['puerta.validate_rate_limit_per_minute', '100', 'puerta'],
            ['redsys_environment', 'test', 'payment'],
            ['redsys_currency', '978', 'payment'],
        ] as [$key, $value, $group]) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    public function test_saves_trilingual_texts_and_sanitizes_social_feed(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'seo.title.es' => 'SALTOPARK - Parque de saltos',
                'landing.tagline.es' => 'Eslogan a medida · Murcia',
                'landing.footer_rights.es' => 'Hecho para botar.',
                'landing.tagline.en' => 'Custom tagline · Murcia',
                'social.feed_embed_url' => '<iframe src="https://snapwidget.com/embed/999" scrolling="no"></iframe>',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('SALTOPARK - Parque de saltos', Setting::value('seo.title.es'));
        $this->assertSame('Eslogan a medida · Murcia', Setting::value('landing.tagline.es'));
        $this->assertSame('Hecho para botar.', Setting::value('landing.footer_rights.es'));
        $this->assertSame('Custom tagline · Murcia', Setting::value('landing.tagline.en'));
        // El feed se guarda LIMPIO (solo el src), no el iframe completo.
        $this->assertSame('https://snapwidget.com/embed/999', Setting::value('social.feed_embed_url'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_saves_whatsapp_as_digits_and_external_registration(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'contact.whatsapp' => '+34 600 11 22 33',
                'registration.url' => 'https://registro.ejemplo.com/alta',
                'registration.label.es' => 'Registro',
                'registration.subtitle.es' => 'Registro para el parque',
                'waiver.mode' => 'desactivado',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('34600112233', Setting::value('contact.whatsapp'), 'WhatsApp se guarda solo en dígitos');
        $this->assertSame('https://registro.ejemplo.com/alta', Setting::value('registration.url'));
        $this->assertSame('Registro', Setting::value('registration.label.es'));
        $this->assertSame('Registro para el parque', Setting::value('registration.subtitle.es'));
        // Fase 6 · waiver: el toggle de #216 es ahora el MODO; el ajuste heredado se escribe como espejo.
        $this->assertSame('desactivado', Setting::value('waiver.mode'));
        $this->assertSame('0', Setting::value('puerta.waiver_check_enabled'), 'el espejo heredado sigue al modo');
    }

    public function test_unrecognized_social_feed_is_not_saved(): void
    {
        Setting::updateOrCreate(['key' => 'social.feed_embed_url'], ['value' => 'https://snapwidget.com/embed/keep', 'group' => 'social']);

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['social.feed_embed_url' => '<iframe src="https://evil.example.com/x"></iframe>'])
            ->call('save');

        // El valor anterior se preserva (no se sobreescribe en silencio con basura).
        $this->assertSame('https://snapwidget.com/embed/keep', Setting::value('social.feed_embed_url'));
    }

    public function test_landing_uses_setting_overrides_when_present(): void
    {
        Setting::updateOrCreate(['key' => 'landing.tagline.es'], ['value' => 'ESLOGAN PERSONALIZADO', 'group' => 'landing']);
        Setting::updateOrCreate(['key' => 'landing.footer_rights.es'], ['value' => 'COLETILLA PERSONALIZADA', 'group' => 'landing']);
        Setting::updateOrCreate(['key' => 'seo.title.es'], ['value' => 'TITULO WEB PERSONALIZADO', 'group' => 'seo']);

        $response = $this->get('/')
            ->assertOk()
            ->assertSee('<title>TITULO WEB PERSONALIZADO</title>', false);

        // ⚠️⚠️ **Ni el lema ni la coletilla se pintan ya en el pie de la web** (`#522`, `[DECIDIDO owner]`:
        // el colofón es «Nombre · Ciudad · © año»). Los dos siguen vivos —el lema es el `<title>` de la
        // portada cuando NO hay «Título web», y la coletilla, el pie del formulario de invitados—, así que
        // lo que se comprueba aquí es que los ajustes siguen llegando al payload del que esos dos leen.
        // ⚠️ **Y con «Título web» puesto, como en este caso, el lema no sale en NINGÚN sitio de la web**:
        // es la consecuencia de retirarlo del pie, dicha al owner y escrita en la ayuda del campo.
        $this->assertSame('ESLOGAN PERSONALIZADO', $response->viewData('site')['tagline']);
        $this->assertSame('COLETILLA PERSONALIZADA', $response->viewData('site')['footer_rights']);
        $response->assertDontSee('COLETILLA PERSONALIZADA');
    }

    public function test_landing_falls_back_to_i18n_when_settings_empty(): void
    {
        // Sin ajustes → textos por defecto de lang/landing (es). El lema es el título de la portada;
        // la coletilla se comprueba en el payload por lo mismo que en el caso de arriba.
        $response = $this->get('/')
            ->assertOk()
            ->assertSee('Parque de saltos para toda la familia · Murcia');

        $this->assertSame('Hecho para reír.', $response->viewData('site')['footer_rights']);
    }

    // ⚠️⚠️ **AQUÍ HABÍA `test_social_feed_renders_iframe_or_falls_back_to_gallery` Y SE HA RETIRADO
    // CON SU SUJETO** (`#309`, `[DECIDIDO owner]`: «la sección "en directo" quítala»).
    // ▶ **Lo que deja dicho, y es lo que importa**: el ajuste `social.feed_embed_url` sigue en el
    // panel y **ya no lo lee ninguna pantalla pública**. Es exactamente el defecto que `#304`
    // documentó —un campo que se puede rellenar y no consume nadie—, creado a sabiendas porque el
    // hueco es el que van a ocupar las reseñas (`specs/google-reviews.md`) y desmontar la fontanería
    // para rehacerla sería churn. Ficha en `DEUDA.md`, con las dos salidas.
    // ⚠️ `test_csp_allows_social_widget_hosts` SIGUE: la CSP es defensa en profundidad y no depende
    // de que hoy haya una pantalla que use el widget.

    public function test_csp_allows_social_widget_hosts(): void
    {
        $response = $this->get('/')->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('snapwidget.com', $csp);
        $this->assertStringContainsString('lightwidget.com', $csp);
    }

    /**
     * ⚠️ Se llamaba `..._shows_panel_de_control_subtitle` y aseveraba «Panel de Control». El SUJETO
     * no ha cambiado —que el panel pinte su subtítulo bajo la marca—, solo el texto: `#461` lo pasó
     * a «Administración» por decisión del owner. Se re-apunta, no se borra.
     */
    public function test_admin_panel_shows_its_subtitle_under_the_brand(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee(__('admin.panel_subtitle'));
    }
}
