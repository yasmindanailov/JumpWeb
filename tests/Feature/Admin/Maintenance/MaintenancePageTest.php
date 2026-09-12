<?php

namespace Tests\Feature\Admin\Maintenance;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\MaintenanceSettings;
use App\Filament\Pages\Maintenance;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Página de panel «Mantenimiento» (#218): gating por `settings.manage`, carga del estado actual,
 * guardado con auditoría y roundtrip del toggle booleano ('1'/'0'). Patrón espejo de
 * `SettingsPageTest`.
 */
class MaintenancePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        foreach ([
            ['maintenance.site', '0', 'maintenance'],
            ['maintenance.message.es', '', 'maintenance'],
            ['maintenance.message.en', '', 'maintenance'],
            ['maintenance.message.fr', '', 'maintenance'],
            ['reservations.paused', '0', 'maintenance'],
            ['reservations.title.es', '', 'maintenance'],
            ['reservations.title.en', '', 'maintenance'],
            ['reservations.title.fr', '', 'maintenance'],
            ['reservations.message.es', '', 'maintenance'],
            ['reservations.message.en', '', 'maintenance'],
            ['reservations.message.fr', '', 'maintenance'],
        ] as [$key, $value, $group]) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }

        /*
         * ⚠️⚠️ **Las páginas se DERIVAN de `PAGE_KEYS`, no se copian aquí** (`#536`). Estaban
         * escritas a mano —la TERCERA copia de la misma lista, con la constante y el seeder—, y al
         * entrar `/bar` este `setUp` dejó de sembrar su fila: `save()` veía `''` donde esperaba
         * `'0'`, lo contaba como cambio y `test_save_without_changes_does_not_audit` se puso rojo
         * **con el producto sano**. *Un fixture que copia una lista del producto caduca el día que
         * la lista crece, y lo hace acusando al código.*
         */
        foreach (MaintenanceSettings::PAGE_KEYS as $page) {
            Setting::updateOrCreate(
                ['key' => 'maintenance.page.'.$page],
                ['value' => '0', 'group' => 'maintenance'],
            );
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

    // ─── Autorización ────────────────────────────────────────────────────────────

    public function test_admin_can_access(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->assertTrue(Maintenance::canAccess());
        $this->actingAs($admin)->get('/admin/maintenance')->assertSuccessful();
    }

    public function test_staff_is_denied(): void
    {
        $this->actingAs($this->staff())->get('/admin/maintenance')->assertForbidden();
    }

    public function test_customer_is_denied(): void
    {
        $this->actingAs($this->customer())->get('/admin/maintenance')->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/maintenance')->assertRedirect();
    }

    // ─── Carga ────────────────────────────────────────────────────────────────────

    public function test_form_loads_current_state(): void
    {
        Setting::updateOrCreate(['key' => 'maintenance.site'], ['value' => '1', 'group' => 'maintenance']);
        Setting::updateOrCreate(['key' => 'maintenance.message.es'], ['value' => 'Hasta el 20 de junio', 'group' => 'maintenance']);

        Livewire::actingAs($this->admin())
            ->test(Maintenance::class)
            ->assertSet('data.maintenance.site', true)   // '1' → bool
            ->assertSet('data.maintenance.message.es', 'Hasta el 20 de junio');
    }

    // ─── Guardado ──────────────────────────────────────────────────────────────────

    public function test_save_enables_maintenance_and_audits(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Maintenance::class)
            ->fillForm([
                'maintenance.site' => true,
                'maintenance.message.es' => 'Volvemos el lunes',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('1', Setting::value('maintenance.site'));
        $this->assertSame('Volvemos el lunes', Setting::value('maintenance.message.es'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.updated']);
    }

    public function test_toggle_roundtrips_as_string(): void
    {
        // ON
        Livewire::actingAs($this->admin())
            ->test(Maintenance::class)
            ->fillForm(['maintenance.site' => true])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('1', Setting::value('maintenance.site'));

        // OFF
        Livewire::actingAs($this->admin())
            ->test(Maintenance::class)
            ->fillForm(['maintenance.site' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('0', Setting::value('maintenance.site'));
    }

    public function test_save_pauses_reservations(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Maintenance::class)
            ->fillForm(['reservations.paused' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('1', Setting::value('reservations.paused'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.updated']);
    }

    public function test_save_persists_reservations_paused_texts(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Maintenance::class)
            ->fillForm([
                'reservations.paused' => true,
                'reservations.title.es' => 'Reservas en pausa',
                'reservations.message.es' => 'Volvemos el 20 de junio',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Reservas en pausa', Setting::value('reservations.title.es'));
        $this->assertSame('Volvemos el 20 de junio', Setting::value('reservations.message.es'));
    }

    public function test_save_puts_a_single_page_in_maintenance(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Maintenance::class)
            ->fillForm(['maintenance.page.precios' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('1', Setting::value('maintenance.page.precios'));
        // El resto de páginas siguen disponibles.
        $this->assertNotSame('1', (string) Setting::value('maintenance.page.home'));
    }

    public function test_save_without_changes_does_not_audit(): void
    {
        // Estado inicial = todo por defecto; guardar sin tocar nada no debe registrar auditoría.
        Livewire::actingAs($this->admin())
            ->test(Maintenance::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'maintenance.updated']);
    }
}
