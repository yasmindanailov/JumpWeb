<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 4.1 — Cimientos de datos de autenticación: campos de perfil/legales,
 * roles + permisos (con admin super-admin) y consentimientos.
 */
class AuthFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_profile_and_legal_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'phone', 'locale', 'last_login_at', 'marketing_opt_in',
            'privacy_accepted_at', 'terms_accepted_at', 'waiver_accepted_at',
        ]));
    }

    public function test_roles_permissions_and_consents_tables_exist(): void
    {
        foreach (['roles', 'permissions', 'role_user', 'permission_role', 'consents'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function test_user_can_be_assigned_a_role(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'customer', 'label' => 'Cliente']);

        $user->roles()->attach($role);

        $this->assertTrue($user->hasRole('customer'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_permission_is_granted_through_a_role(): void
    {
        $role = Role::create(['name' => 'staff', 'label' => 'Empleado']);
        $permission = Permission::create(['name' => 'orders.refund', 'label' => 'Reembolsar pedidos']);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('orders.refund'));
        $this->assertFalse($user->hasPermission('content.edit'));
    }

    public function test_admin_is_super_admin_for_permissions_and_gates(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::create(['name' => 'admin', 'label' => 'Administrador']));

        // Super-admin: cualquier permiso/gate, aunque no exista definido.
        $this->assertTrue($admin->hasPermission('anything.at.all'));
        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('any.ability'));
    }

    public function test_non_admin_without_permission_is_denied_by_gate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(Gate::allows('any.ability'));
    }

    public function test_consent_can_be_stored_and_read(): void
    {
        $user = User::factory()->create();

        $user->consents()->create([
            'type' => 'waiver',
            'accepted_at' => now(),
            'ip' => '127.0.0.1',
            'version' => '2026-05-23',
        ]);

        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'type' => 'waiver',
            'version' => '2026-05-23',
        ]);
        $this->assertSame('waiver', $user->consents()->first()->type);
        $this->assertInstanceOf(Consent::class, $user->consents->first());
    }

    public function test_role_seeder_creates_initial_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        $this->assertDatabaseHas('roles', ['name' => 'customer']);
    }

    public function test_user_must_verify_email_contract(): void
    {
        $this->assertInstanceOf(
            MustVerifyEmail::class,
            User::factory()->make(),
        );
    }
}
