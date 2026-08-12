<?php

namespace Tests\Feature\Admin\Access;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PermissionCatalog;
use App\Filament\Resources\Roles\Concerns\InteractsWithRoleForm;
use App\Filament\Resources\Roles\Pages\EditRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.11 — La matriz de permisos del rol: el cambio se refleja en `hasPermission` EN VIVO,
 * la matriz del admin es no-op, `access.manage` nunca se asigna, el `name` es inmutable y el
 * audit registra el diff.
 */
class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function staffRole(): Role
    {
        return Role::where('name', 'staff')->firstOrFail();
    }

    public function test_granting_a_permission_to_staff_takes_effect_live(): void
    {
        $staffUser = User::factory()->create();
        $staffUser->roles()->sync([$this->staffRole()->id]);

        $this->assertFalse($staffUser->hasPermission('catalog.manage'), 'precondición: staff no gestiona catálogo');

        Livewire::actingAs($this->admin())
            ->test(EditRole::class, ['record' => $this->staffRole()->id])
            ->fillForm(['permissions_gestion' => ['catalog.manage']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($staffUser->fresh()->hasPermission('catalog.manage'),
            'conceder catalog.manage al rol staff surte efecto en vivo');
        $this->assertTrue($staffUser->fresh()->hasPermission('orders.view'),
            'los permisos de operativa (otro grupo) se conservan');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.role_permissions_updated',
            'target_id' => $this->staffRole()->id,
        ]);
    }

    public function test_revoking_a_permission_from_staff_takes_effect_live(): void
    {
        $staffUser = User::factory()->create();
        $staffUser->roles()->sync([$this->staffRole()->id]);

        $this->assertTrue($staffUser->hasPermission('orders.refund'), 'precondición: staff reembolsa');

        // Reenvía la operativa SIN orders.refund → se revoca.
        Livewire::actingAs($this->admin())
            ->test(EditRole::class, ['record' => $this->staffRole()->id])
            ->fillForm(['permissions_operativa' => ['orders.view']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($staffUser->fresh()->hasPermission('orders.refund'),
            'revocar orders.refund surte efecto en vivo');
        $this->assertTrue($staffUser->fresh()->hasPermission('orders.view'),
            'orders.view sigue concedido');
    }

    public function test_form_blocks_selecting_access_manage(): void
    {
        // Capa 1 (formulario): access.manage es una opción DESHABILITADA → Filament rechaza
        // su selección (no se puede colar ni forjando el payload de la matriz). El resultado
        // de seguridad: nunca queda concedido en el rol.
        Livewire::actingAs($this->admin())
            ->test(EditRole::class, ['record' => $this->staffRole()->id])
            ->fillForm(['permissions_sistema' => ['audit.view', 'access.manage']])
            ->call('save');

        $this->assertFalse($this->staffRole()->permissions()->where('name', 'access.manage')->exists(),
            'access.manage NUNCA entra en el rol staff (defensa de auto-escalada)');
        // Y como la opción inválida hace que Filament rechace TODO el submit, tampoco se aplica
        // el resto del grupo → prueba que el bloqueo es de la capa de formulario (no que el
        // backend "limpie" access.manage pero cuele audit.view).
        $this->assertFalse($this->staffRole()->permissions()->where('name', 'audit.view')->exists(),
            'el submit con una opción deshabilitada se rechaza entero (capa de formulario)');
    }

    public function test_backend_sanitizes_access_manage_out_of_the_submission(): void
    {
        // Capa 2 (backend): aunque el payload llegara con access.manage (saltándose el form),
        // `extractPermissionMatrix` lo descarta (intersección con assignable()).
        $harness = new class
        {
            use InteractsWithRoleForm;

            public ?Role $record = null;

            /**
             * @param  array<string,mixed>  $data
             * @return array<int,string>
             */
            public function run(array $data): array
            {
                [, $names] = $this->extractPermissionMatrix($data);

                return $names;
            }
        };

        $names = $harness->run([
            'permissions_operativa' => ['orders.view'],
            'permissions_sistema' => ['audit.view', 'access.manage'],
        ]);

        $this->assertContains('orders.view', $names);
        $this->assertContains('audit.view', $names);
        $this->assertNotContains('access.manage', $names,
            'el saneo del backend descarta access.manage aunque llegue en el payload');
    }

    public function test_admin_matrix_is_a_no_op(): void
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->assertSame(0, $adminRole->permissions()->count(), 'el admin no tiene filas de permiso (Gate::before)');

        Livewire::actingAs($this->admin())
            ->test(EditRole::class, ['record' => $adminRole->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $adminRole->fresh()->permissions()->count(),
            'guardar el rol admin no le añade permisos');
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'access.role_permissions_updated',
            'target_id' => $adminRole->id,
        ]);
    }

    public function test_role_name_is_immutable(): void
    {
        Livewire::actingAs($this->admin())
            ->test(EditRole::class, ['record' => $this->staffRole()->id])
            ->fillForm(['name' => 'hacked', 'permissions_gestion' => ['catalog.manage']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('staff', Role::find($this->staffRole()->id)->name,
            'el name del rol no cambia al editar (espina dorsal del código)');
    }

    public function test_no_audit_when_nothing_changes(): void
    {
        // El rol staff ya trae sus 11 permisos de operativa; guardar reenviándolos no cambia nada.
        $operativa = PermissionCatalog::GROUPS['operativa'];

        Livewire::actingAs($this->admin())
            ->test(EditRole::class, ['record' => $this->staffRole()->id])
            ->fillForm([
                'permissions_operativa' => $operativa,
                'permissions_gestion' => [],
                'permissions_sistema' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'access.role_permissions_updated',
            'target_id' => $this->staffRole()->id,
        ]);
    }
}
