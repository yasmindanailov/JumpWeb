<?php

namespace Tests\Feature\Admin\SpecialDates;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\SpecialDates\Pages\CreateSpecialDate;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Auditoría Fase 1 — separación de privilegios: cerrar el parque / fijar horario especial
 * (`special_dates.is_closed`/`open_time`/`close_time`) es OPERATIVO → requiere `slots.manage`,
 * no `prices.manage`. Antes todo el recurso estaba bajo `prices.manage`, así que un rol de
 * solo-precios podía cerrar el parque. El campo se gatea con disabled+dehydrated (el valor se
 * conserva al guardar para un rol sin el permiso).
 */
class SpecialDateClosePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_can_toggle_park_closure(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        Livewire::actingAs($admin)
            ->test(CreateSpecialDate::class)
            ->assertFormFieldIsEnabled('is_closed');
    }

    public function test_prices_only_role_cannot_close_the_park(): void
    {
        // Staff con SOLO `prices.manage` (puede gestionar tarifas/fechas, NO franjas): ve el recurso
        // pero el toggle de cierre está deshabilitado.
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $user->roles()->first()->permissions()->sync(
            Permission::whereIn('name', ['prices.manage'])->pluck('id')
        );

        $this->assertTrue($user->hasPermission('prices.manage'));
        $this->assertFalse($user->hasPermission('slots.manage'));

        Livewire::actingAs($user)
            ->test(CreateSpecialDate::class)
            ->assertFormFieldIsDisabled('is_closed');
    }
}
