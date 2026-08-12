<?php

namespace Tests\Feature\Admin\Settings;

use App\Domain\Booking\Services\CatalogSettings;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Services\Redsys;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\Settings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.10 (iter. 1) — Configuración del negocio: gating por `settings.manage`, carga de
 * los valores actuales, guardado con auditoría, validación de rangos (defensa en
 * profundidad sobre los helpers) y NO exposición de secretos ni del contador de pagos.
 */
class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        // Baseline válido para los campos obligatorios + los secretos/contador a proteger.
        $seed = [
            ['business.name', 'SaltoPark', 'business'],
            ['business.legal_name', '[PENDIENTE]', 'business'],
            ['contact.email', 'hola@saltopark.example', 'contact'],
            ['sales.hold_minutes', '15', 'payment'],
            ['sales.purchase_horizon_months', '6', 'payment'],
            ['puerta.validate_rate_limit_per_minute', '100', 'puerta'],
            ['payment.tax_rate', '21', 'payment'],
            ['packs.prep_blocks_cupo', '1', 'packs'],
            ['redsys_environment', 'test', 'payment'],
            ['redsys_currency', '978', 'payment'],
            // SECRETOS / contador que NUNCA deben tocarse desde el panel:
            ['redsys_secret_key', 'sandbox-secret-xyz', 'payment'],
            ['security.turnstile_secret', 'turnstile-secret-xyz', 'security'],
            ['redsys_next_gateway_order', '100000', 'payment'],
        ];
        foreach ($seed as [$key, $value, $group]) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }
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

    private function customer(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return $u;
    }

    // ─── Autorización ────────────────────────────────────────────────────────

    public function test_admin_can_access(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->assertTrue(Settings::canAccess());
        $this->assertTrue($admin->hasPermission('settings.manage'));

        $this->actingAs($admin)->get('/admin/settings')->assertSuccessful();
    }

    public function test_staff_is_denied(): void
    {
        $staff = $this->staff();
        $this->assertFalse($staff->hasPermission('settings.manage'));

        $this->actingAs($staff);
        $this->assertFalse(Settings::canAccess());
        $this->actingAs($staff)->get('/admin/settings')->assertForbidden();
    }

    public function test_customer_is_denied(): void
    {
        $this->actingAs($this->customer())->get('/admin/settings')->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/settings')->assertRedirect();
    }

    // ─── Carga ────────────────────────────────────────────────────────────────

    public function test_form_loads_current_values(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->assertSet('data.business.name', 'SaltoPark')
            ->assertSet('data.contact.email', 'hola@saltopark.example')
            ->assertSet('data.sales.hold_minutes', '15')
            ->assertSet('data.redsys_environment', 'test') // clave plana (sin punto) → estado plano
            ->assertSet('data.packs.prep_blocks_cupo', true); // '1' → bool
    }

    // ─── Guardado ──────────────────────────────────────────────────────────────

    public function test_save_persists_changes_and_audits(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'business.name' => 'SaltoPark Villaparque',
                'contact.email' => 'info@saltopark.example',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('SaltoPark Villaparque', Setting::value('business.name'));
        $this->assertSame('info@saltopark.example', Setting::value('contact.email'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_bool_setting_persists_as_string(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['packs.prep_blocks_cupo' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0', Setting::value('packs.prep_blocks_cupo'));
    }

    // ─── Validación (defensa en profundidad sobre los helpers) ─────────────────

    public function test_save_rejects_hold_minutes_out_of_range(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['sales.hold_minutes' => 9999]) // > 240
            ->call('save')
            ->assertHasFormErrors(['sales.hold_minutes']);

        $this->assertSame('15', Setting::value('sales.hold_minutes')); // sin cambios
    }

    public function test_save_persists_catalog_search_threshold(): void
    {
        // #226 punto 7: el umbral del buscador del catálogo es editable desde el panel.
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['catalog.search_min_items' => 6])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('6', Setting::value('catalog.search_min_items'));
        $this->assertSame(6, CatalogSettings::searchMinItems());
    }

    public function test_save_rejects_catalog_search_threshold_out_of_range(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['catalog.search_min_items' => 9999]) // > 100 (CatalogSettings::SEARCH_MIN_ITEMS_MAX)
            ->call('save')
            ->assertHasFormErrors(['catalog.search_min_items']);
    }

    public function test_save_rejects_invalid_email(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['contact.email' => 'no-es-un-email'])
            ->call('save')
            ->assertHasFormErrors(['contact.email']);
    }

    public function test_save_rejects_invalid_currency(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['redsys_currency' => 'abc'])
            ->call('save')
            ->assertHasFormErrors(['redsys_currency']);

        $this->assertSame('978', Setting::value('redsys_currency'));
    }

    // ─── Secretos y contador: NO editables desde el panel ──────────────────────

    public function test_secrets_and_counter_are_untouched_on_save(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['business.name' => 'Cambio cualquiera'])
            ->call('save')
            ->assertHasNoFormErrors();

        // El guardado solo toca las claves gestionadas; los secretos y el contador siguen igual.
        $this->assertSame('sandbox-secret-xyz', Setting::value('redsys_secret_key'));
        $this->assertSame('turnstile-secret-xyz', Setting::value('security.turnstile_secret'));
        $this->assertSame('100000', Setting::value('redsys_next_gateway_order'));
    }

    // ─── Hallazgos de la revisión adversarial ──────────────────────────────────

    public function test_save_rejects_purchase_horizon_out_of_range(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['sales.purchase_horizon_months' => 25]) // > 24
            ->call('save')
            ->assertHasFormErrors(['sales.purchase_horizon_months']);

        $this->assertSame('6', Setting::value('sales.purchase_horizon_months'));
    }

    public function test_save_rejects_rate_limit_out_of_range(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['puerta.validate_rate_limit_per_minute' => 10001]) // > 10000
            ->call('save')
            ->assertHasFormErrors(['puerta.validate_rate_limit_per_minute']);

        $this->assertSame('100', Setting::value('puerta.validate_rate_limit_per_minute'));
    }

    public function test_save_creates_unseeded_managed_key_with_correct_group(): void
    {
        // business.city NO se sembró en setUp: al guardar debe crearse con su group ('business').
        $this->assertNull(Setting::value('business.city'));

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['business.city' => 'Murcia'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', ['key' => 'business.city', 'value' => 'Murcia', 'group' => 'business']);
    }

    public function test_save_blocks_switch_to_live_without_credentials(): void
    {
        // merchant_code/terminal no se sembraron → vacíos. Pasar a 'live' debe rechazarse.
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['redsys_environment' => 'live'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.settings.redsys_live_requires_credentials'));

        // Guardado atómico rechazado: el entorno sigue en 'test'.
        $this->assertSame('test', Setting::value('redsys_environment'));
    }

    public function test_save_allows_switch_to_live_with_credentials(): void
    {
        // Clave secreta efectiva válida (32 chars, no la de sandbox): simula el secreto real ya
        // instalado en el servidor. Sin esto, la guarda del secreto (runbook §5) bloquearía el 'live'.
        Setting::updateOrCreate(['key' => 'redsys_secret_key'], ['value' => str_repeat('K', 32), 'group' => 'payment']);

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'redsys_environment' => 'live',
                'redsys_merchant_code' => '999008881',
                'redsys_terminal' => '001',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('live', Setting::value('redsys_environment'));
        $this->assertSame('999008881', Setting::value('redsys_merchant_code'));
    }

    public function test_save_blocks_switch_to_live_with_invalid_length_secret(): void
    {
        // Credenciales no-secretas presentes (pasa la 1.ª guarda) pero la clave secreta efectiva es
        // el placeholder de la semilla ('sandbox-secret-xyz', 17 chars) → la 2.ª guarda lo rechaza.
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'redsys_environment' => 'live',
                'redsys_merchant_code' => '999008881',
                'redsys_terminal' => '001',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.settings.redsys_live_requires_secret'));

        // Guardado atómico rechazado: el entorno sigue en 'test'.
        $this->assertSame('test', Setting::value('redsys_environment'));
    }

    public function test_save_blocks_switch_to_live_with_sandbox_secret(): void
    {
        // Clave de 32 chars pero IGUAL a la pública de sandbox → la guarda la rechaza igualmente
        // (un go-live con la clave de pruebas dejaría todos los cobros sin firma válida).
        Setting::updateOrCreate(['key' => 'redsys_secret_key'], ['value' => Redsys::SANDBOX_SECRET_KEY, 'group' => 'payment']);

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'redsys_environment' => 'live',
                'redsys_merchant_code' => '999008881',
                'redsys_terminal' => '001',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.settings.redsys_live_requires_secret'));

        $this->assertSame('test', Setting::value('redsys_environment'));
    }

    public function test_save_persists_valid_google_maps_embed_url(): void
    {
        $embed = 'https://www.google.com/maps/embed?pb=!1m18!2sMurcia';

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['address.maps_embed_url' => $embed])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($embed, Setting::value('address.maps_embed_url'));
    }

    public function test_save_rejects_non_google_maps_embed_url(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['address.maps_embed_url' => 'https://evil.example.com/iframe'])
            ->call('save')
            ->assertHasFormErrors(['address.maps_embed_url']);

        $this->assertNull(Setting::value('address.maps_embed_url'));
    }

    public function test_save_extracts_clean_url_when_full_iframe_or_attributes_pasted(): void
    {
        $url = 'https://www.google.com/maps/embed?pb=!1m18!2sCasa!5e0!3m2!1ses!2ses';
        // Escenario reportado: el operador pega el <iframe> completo (o el src con atributos detrás).
        $pasted = '<iframe src="'.$url.'" width="600" height="450" style="border:0;" allowfullscreen loading="lazy"></iframe>';

        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['address.maps_embed_url' => $pasted])
            ->call('save')
            ->assertHasNoFormErrors();

        // Se almacena SOLO la URL limpia (sin los atributos del iframe).
        $this->assertSame($url, Setting::value('address.maps_embed_url'));
    }

    public function test_save_keeps_existing_map_and_warns_when_paste_unrecognized(): void
    {
        // Hay un mapa válido configurado.
        $good = 'https://www.google.com/maps/embed?pb=GOODTOKEN';
        Setting::updateOrCreate(['key' => 'address.maps_embed_url'], ['value' => $good, 'group' => 'contact']);

        // El operador pega algo que pasa el regex (contiene la URL) pero no es parseable
        // (URL sin parámetros): NO se sobreescribe el mapa actual y se avisa.
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm(['address.maps_embed_url' => 'https://www.google.com/maps/embed'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.settings.maps_embed_not_recognized'));

        // El mapa anterior se preserva (no se pierde en silencio).
        $this->assertSame($good, Setting::value('address.maps_embed_url'));
    }

    // ─── L2 (Fase 3 · Plan B) — Reorganización en pestañas ─────────────────────

    public function test_page_renders_the_four_top_level_tabs(): void
    {
        // Fija la reorganización L2: las 4 pestañas de nivel superior se renderizan.
        $this->actingAs($this->admin())
            ->get('/admin/settings')
            ->assertSuccessful()
            ->assertSee(__('admin.settings.tab_business'))
            ->assertSee(__('admin.settings.tab_web'))
            ->assertSee(__('admin.settings.tab_fiscal'))
            ->assertSee(__('admin.settings.tab_advanced'));
    }

    public function test_single_save_persists_fields_across_all_tabs_atomically(): void
    {
        // Un solo Guardar persiste claves de las 4 pestañas a la vez, incluida una sección
        // COLAPSADA de «Avanzado» (colapsado ≠ oculto: sigue hidratándose y guardándose). Prueba
        // que envolver el formulario en Tabs/Sections NO rompió la atomicidad del guardado.
        Livewire::actingAs($this->admin())
            ->test(Settings::class)
            ->fillForm([
                'business.name' => 'SaltoPark Centro',   // pestaña «Tu negocio» (Identidad)
                'business.nif' => 'B12345678',         // pestaña «Datos fiscales»
                'payment.tax_rate' => 10,              // pestaña «Datos fiscales» (IVA)
                'catalog.search_min_items' => 8,       // pestaña «Textos y aspecto web» (Aspecto)
                'packs.max_per_slot' => 12,            // pestaña «Avanzado» → sección «Aforo» (COLAPSADA)
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('SaltoPark Centro', Setting::value('business.name'));
        $this->assertSame('B12345678', Setting::value('business.nif'));
        $this->assertSame('10', Setting::value('payment.tax_rate'));
        $this->assertSame('8', Setting::value('catalog.search_min_items'));
        $this->assertSame('12', Setting::value('packs.max_per_slot'));
    }
}
