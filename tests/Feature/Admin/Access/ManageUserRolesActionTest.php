<?php

namespace Tests\Feature\Admin\Access;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.11 — Acción "Gestionar roles" en la ficha del usuario: asignar/quitar roles con
 * guardas anti-bloqueo (no auto-degradarte de admin, no quitar el último admin) y audit.
 */
class ManageUserRolesActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function roleId(string $name): int
    {
        return Role::where('name', $name)->value('id');
    }

    private function userWithRoles(string ...$roles): User
    {
        $u = User::factory()->create();
        $u->roles()->sync(array_map(fn (string $r): int => $this->roleId($r), $roles));

        return $u;
    }

    public function test_assigning_staff_role_promotes_customer_to_panel(): void
    {
        $admin = $this->userWithRoles('admin');
        $customer = $this->userWithRoles('customer');

        $this->assertFalse($customer->canAccessPanel(Filament::getPanel('admin')), 'precondición: el cliente no entra al panel');

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $customer->id])
            ->callAction('manageRoles', ['roles' => [$this->roleId('customer'), $this->roleId('staff')]]);

        $customer->refresh();
        $this->assertTrue($customer->hasRole('staff'));
        $this->assertTrue($customer->hasRole('customer'), 'mantiene su rol de cliente');
        $this->assertTrue($customer->canAccessPanel(Filament::getPanel('admin')), 'ahora sí entra al panel');

        $log = AuditLog::where('action', 'access.user_roles_updated')
            ->where('target_id', $customer->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame(['staff'], $log->payload['added']);
        $this->assertSame([], $log->payload['removed']);
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $admin = $this->userWithRoles('admin');

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $admin->id])
            ->callAction('manageRoles', ['roles' => []]);

        $this->assertTrue($admin->fresh()->hasRole('admin'), 'no puedes quitarte a ti mismo el admin');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.user_roles_update_blocked',
            'target_id' => $admin->id,
        ]);
        $blocked = AuditLog::where('action', 'access.user_roles_update_blocked')->latest('id')->first();
        $this->assertSame('self_admin_demotion', $blocked->payload['reason']);
    }

    public function test_can_demote_a_non_last_admin(): void
    {
        $actor = $this->userWithRoles('admin');
        $target = $this->userWithRoles('admin');

        Livewire::actingAs($actor)
            ->test(ViewUser::class, ['record' => $target->id])
            ->callAction('manageRoles', ['roles' => [$this->roleId('customer')]]);

        $target->refresh();
        $this->assertFalse($target->hasRole('admin'), 'con 2 admins, sí se puede degradar a uno');
        $this->assertTrue($target->hasRole('customer'));

        $log = AuditLog::where('action', 'access.user_roles_updated')->where('target_id', $target->id)->latest('id')->first();
        $this->assertSame(['admin'], $log->payload['removed']);
    }

    public function test_cannot_remove_the_last_admin(): void
    {
        // Único admin del sistema.
        $onlyAdmin = $this->userWithRoles('admin');

        // Actor NO-admin con access.manage + users.manage concedidos directamente en BD
        // (escenario de defensa en profundidad: la matriz nunca concede access.manage, pero
        // si por manipulación directa lo tuviera, la guarda sigue protegiendo al último admin).
        $actor = $this->userWithRoles('staff');
        Role::where('name', 'staff')->first()->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', ['access.manage', 'users.manage'])->pluck('id'),
        );
        $this->assertTrue($actor->fresh()->hasPermission('access.manage'));

        Livewire::actingAs($actor->fresh())
            ->test(ViewUser::class, ['record' => $onlyAdmin->id])
            ->callAction('manageRoles', ['roles' => [$this->roleId('customer')]]);

        $this->assertTrue($onlyAdmin->fresh()->hasRole('admin'), 'no se puede quitar el último admin');
        $blocked = AuditLog::where('action', 'access.user_roles_update_blocked')
            ->where('target_id', $onlyAdmin->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($blocked);
        $this->assertSame('last_admin', $blocked->payload['reason']);
    }

    public function test_action_hidden_for_users_without_access_manage(): void
    {
        $staff = $this->userWithRoles('staff'); // staff base no tiene access.manage ni users.manage
        $customer = $this->userWithRoles('customer');

        // staff base ni siquiera puede abrir la ficha (gated por users.manage) → forbidden.
        $this->actingAs($staff)
            ->get(UserResource::getUrl('view', ['record' => $customer]))
            ->assertForbidden();
    }

    public function test_action_hidden_for_user_with_users_manage_but_not_access_manage(): void
    {
        // Un usuario que puede VER usuarios (users.manage) pero no gestionar accesos
        // (access.manage): abre la ficha, pero la acción "Gestionar roles" está oculta (capa 1).
        Role::where('name', 'staff')->first()->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', ['users.manage'])->pluck('id'),
        );
        $actor = $this->userWithRoles('staff');
        $customer = $this->userWithRoles('customer');

        $this->assertTrue($actor->fresh()->hasPermission('users.manage'));
        $this->assertFalse($actor->fresh()->hasPermission('access.manage'));

        Livewire::actingAs($actor->fresh())
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertActionHidden('manageRoles');
    }

    public function test_demoting_down_to_the_last_admin_is_blocked_sequentially(): void
    {
        // Dos admins: el actor degrada al otro (OK) → queda 1 admin. Después, ese último admin
        // no puede ser degradado (lo intenta un actor no-admin con access.manage concedido en BD,
        // escenario de defensa en profundidad; la concurrencia real la cubre el lockForUpdate).
        $actor = $this->userWithRoles('admin');
        $second = $this->userWithRoles('admin');

        Livewire::actingAs($actor)
            ->test(ViewUser::class, ['record' => $second->id])
            ->callAction('manageRoles', ['roles' => [$this->roleId('customer')]]);
        $this->assertFalse($second->fresh()->hasRole('admin'), '1.er paso: con 2 admins se puede degradar');

        // Ahora solo queda 1 admin = $actor. Un actor con access.manage (no-admin) intenta quitárselo.
        Role::where('name', 'staff')->first()->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', ['access.manage', 'users.manage'])->pluck('id'),
        );
        $accessManager = $this->userWithRoles('staff');

        Livewire::actingAs($accessManager->fresh())
            ->test(ViewUser::class, ['record' => $actor->id])
            ->callAction('manageRoles', ['roles' => [$this->roleId('customer')]]);

        $this->assertTrue($actor->fresh()->hasRole('admin'), 'el último admin restante no se puede degradar');
        $this->assertSame('last_admin', AuditLog::where('action', 'access.user_roles_update_blocked')
            ->where('target_id', $actor->id)->latest('id')->first()?->payload['reason']);
    }
}
