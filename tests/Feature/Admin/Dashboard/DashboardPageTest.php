<?php

namespace Tests\Feature\Admin\Dashboard;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 7.4 iter2 — página `Dashboard` custom con el filtro de periodo compartido.
 * Smoke-test de que la página renderiza (sin doble registro de la Dashboard) con
 * el filtro HOY · ESTA SEMANA · ESTE MES, y del gate de acceso al panel.
 */
class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'UTC', 'group' => 'general']);
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_customer_cannot_access_dashboard(): void
    {
        $this->actingAs($this->customer())->get('/admin')->assertForbidden();
    }

    public function test_staff_sees_dashboard_with_period_filter(): void
    {
        $this->actingAs($this->staff())
            ->get('/admin')
            ->assertOk()
            ->assertSee(__('admin.dashboard.period.today'))
            ->assertSee(__('admin.dashboard.period.week'))
            ->assertSee(__('admin.dashboard.period.month'));
    }
}
