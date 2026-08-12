<?php

namespace Tests\Feature\Admin\Users;

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Consent;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.5 — Sección de consentimientos en la ficha del usuario (decisión #180).
 *
 * Visible solo con `consents.view` (admin la tiene vía Gate::before). Las etiquetas
 * de tipo viven en `admin.users.consents.types.*` (panel ES+ZH). Empty-state para
 * cuentas sin consents (p. ej. anonimizadas).
 */
class UserConsentsInfolistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    public function test_consents_section_visible_to_admin_with_consents(): void
    {
        $customer = $this->userWithRole('customer');
        $customer->consents()->create([
            'type' => 'privacy',
            'accepted_at' => now(),
            'ip' => '127.0.0.1',
            'version' => Consent::CURRENT_VERSION,
        ]);

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertSee(__('admin.users.section_consents'))
            ->assertSee(__('admin.users.consents.types.privacy'));
    }

    public function test_consents_empty_state_for_user_without_consents(): void
    {
        $customer = $this->userWithRole('customer'); // sin consents

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $customer->id])
            ->assertSee(__('admin.users.consents.empty'));
    }
}
