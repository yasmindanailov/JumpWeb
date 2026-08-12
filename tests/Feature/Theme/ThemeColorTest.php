<?php

namespace Tests\Feature\Theme;

use App\Domain\Content\Services\ThemeSettings;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\Settings;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\OrderConfirmation;
use Database\Seeders\LandingContentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.10 (iter. 2) — Color de marca white-label. Verifica la fuente defensiva `ThemeSettings`
 * y su aplicación en las TRES superficies: web (inyección en `:root`), panel (vía el helper) y
 * emails (acento del header). Los colores POR ZONA (#210) se mantienen y conviven con la marca.
 */
class ThemeColorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(LandingContentSeeder::class); // settings (incl. theme.brand) + zonas + contenido
        // El seeder deja `address.maps_url='#'` (placeholder), que NO pasa la validación url() del
        // form de Ajustes (#205) — ruido ajeno a esta feature. Se normaliza para poder guardar.
        Setting::updateOrCreate(['key' => 'address.maps_url'], ['value' => '', 'group' => 'contact']);
        app()->setLocale('es');
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    // ─────────────────────── Helper defensivo ───────────────────────

    public function test_brand_returns_setting_when_valid(): void
    {
        Setting::updateOrCreate(['key' => 'theme.brand'], ['value' => '#123abc', 'group' => 'theme']);
        $this->assertSame('#123ABC', ThemeSettings::brand(), 'normaliza a mayúsculas');
    }

    public function test_brand_falls_back_when_missing_or_invalid(): void
    {
        Setting::where('key', 'theme.brand')->delete();
        $this->assertSame(ThemeSettings::DEFAULT_BRAND, ThemeSettings::brand(), 'sin setting → default');

        Setting::updateOrCreate(['key' => 'theme.brand'], ['value' => 'no-es-un-color', 'group' => 'theme']);
        $this->assertSame(ThemeSettings::DEFAULT_BRAND, ThemeSettings::brand(), 'valor inválido → default (no rompe el render)');
    }

    public function test_zone_color_comes_from_zone_or_falls_back(): void
    {
        Zone::where('accent', 'jump')->update(['color' => '#AABBCC']);
        $this->assertSame('#AABBCC', ThemeSettings::zoneColor('jump'));

        // Un accent sin zona → fallback del mockup (no lanza).
        $this->assertSame('#FF5B22', ThemeSettings::zoneColor('inexistente'));
    }

    public function test_css_root_declarations_contains_brand_and_zone_accents(): void
    {
        Setting::updateOrCreate(['key' => 'theme.brand'], ['value' => '#0A0B0C', 'group' => 'theme']);
        Zone::where('accent', 'jump')->update(['color' => '#111111']);
        Zone::where('accent', 'kids')->update(['color' => '#222222']);

        $css = ThemeSettings::cssRootDeclarations();
        $this->assertStringContainsString('--brand:#0A0B0C', $css);
        $this->assertStringContainsString('--zone-1:var(--brand)', $css, 'lo genérico de la web sigue la marca');
        $this->assertStringContainsString('--jump-1:#111111', $css, 'el acento Jump deriva del color de la zona');
        $this->assertStringContainsString('--kids-1:#222222', $css);
    }

    // ─────────────────────────── Web ────────────────────────────────

    public function test_landing_injects_theme_into_root(): void
    {
        Setting::updateOrCreate(['key' => 'theme.brand'], ['value' => '#0A0B0C', 'group' => 'theme']);
        Zone::where('accent', 'jump')->update(['color' => '#111111']);

        $res = $this->get('/')->assertOk();
        $res->assertSee('--brand:#0A0B0C', false);
        $res->assertSee('--zone-1:var(--brand)', false);
        $res->assertSee('--jump-1:#111111', false);
    }

    // ───────────────────────── Settings ─────────────────────────────

    public function test_settings_page_persists_theme_brand_and_audits(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['theme.brand' => '#0A0B0C'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('#0A0B0C', ThemeSettings::brand());
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_settings_page_rejects_invalid_hex(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['theme.brand' => '#GGGGGG'])
            ->call('save')
            ->assertHasFormErrors(['theme.brand']);
    }

    public function test_settings_theme_section_gated_to_admin(): void
    {
        $this->actingAs($this->staff())->get('/admin/settings')->assertForbidden();
    }

    // ─────────────────────────── Emails ─────────────────────────────

    public function test_email_header_follows_brand_color(): void
    {
        Setting::updateOrCreate(['key' => 'theme.brand'], ['value' => '#0A0B0C', 'group' => 'theme']);

        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'JJ-THEME1', 'status' => Order::STATUS_PAID,
            'subtotal' => 2500, 'total' => 2500, 'currency' => 'EUR',
        ]);

        $message = (new OrderConfirmation($order))->toMail($user);
        $html = (string) app(Markdown::class)->render('notifications::email', $message->toArray());

        // El wordmark del header (brand-dot) lleva el color de marca inline en TODOS los emails.
        $this->assertStringContainsString('#0A0B0C', $html);
        // Y el botón primary (OrderConfirmation tiene CTA) sigue la marca en el fondo (gana al
        // `.button-primary` estático al inlinear el CSS del tema).
        $this->assertStringContainsString('background-color: #0A0B0C', $html, 'el botón del email sigue la marca');
    }

    // ───────────────── Cobertura adicional (revisión adversarial) ─────────────────

    public function test_landing_sliders_carry_validated_zone_color(): void
    {
        Zone::where('accent', 'jump')->update(['color' => '#111111']);

        $this->get('/')->assertOk()->assertSee('data-color="#111111"', false);
    }

    public function test_landing_sanitizes_corrupt_zone_color(): void
    {
        // Color corrupto en BD (saltándose el form): el render lo sanea al default de su accent,
        // no lo escupe crudo al DOM (defensa en el punto de salida).
        Zone::where('accent', 'jump')->update(['color' => 'corrupto']);

        $res = $this->get('/')->assertOk();
        $res->assertDontSee('corrupto', false);
        $res->assertSee('data-color="#FF5B22"', false);
    }

    public function test_panel_loads_with_custom_brand(): void
    {
        // Color::hex(brand) se resuelve al registrar el panel en CADA request /admin/*: con una
        // marca custom el panel debe cargar sin romper (integración en vivo).
        Setting::updateOrCreate(['key' => 'theme.brand'], ['value' => '#0A0B0C', 'group' => 'theme']);

        $this->actingAs($this->admin())->get('/admin/settings')->assertOk();
    }

    public function test_empty_brand_falls_back_end_to_end(): void
    {
        Setting::updateOrCreate(['key' => 'address.maps_url'], ['value' => '', 'group' => 'contact']);

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['theme.brand' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(ThemeSettings::DEFAULT_BRAND, ThemeSettings::brand());
        $this->get('/')->assertSee('--brand:'.ThemeSettings::DEFAULT_BRAND, false);
    }

    public function test_settings_page_normalizes_lowercase_hex(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['theme.brand' => '#ff5b22'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('#FF5B22', ThemeSettings::brand());
    }
}
