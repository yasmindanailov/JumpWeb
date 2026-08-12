<?php

namespace Tests\Feature\Admin\Schedule;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Pages\WeeklySchedule;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.7 (#207) — Horario semanal (`opening_hours`) editable desde el panel: gating por
 * `slots.manage`, carga/guardado de los 7 días, validación cierre>apertura, y que las
 * reservas (vía `OperatingSchedule`) respetan lo guardado.
 */
class WeeklySchedulePageTest extends TestCase
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

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    public function test_admin_can_access_staff_cannot(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(WeeklySchedule::canAccess());

        $staff = $this->staff();
        $this->assertFalse($staff->hasPermission('slots.manage'));
        $this->actingAs($staff)->get('/admin/horario')->assertForbidden();
    }

    public function test_save_upserts_the_seven_days(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WeeklySchedule::class)
            ->fillForm([
                'day_1_open' => '16:00', 'day_1_close' => '22:00',   // lunes abierto
                'day_0_closed' => true,                               // domingo cerrado
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('opening_hours', 7); // se upsertan los 7 días

        $monday = OpeningHour::where('weekday', 1)->firstOrFail();
        $this->assertSame('16:00', substr((string) $monday->open_time, 0, 5));
        $this->assertSame('22:00', substr((string) $monday->close_time, 0, 5));
        $this->assertFalse($monday->is_closed);

        $this->assertTrue(OpeningHour::where('weekday', 0)->firstOrFail()->is_closed);
        $this->assertDatabaseHas('audit_logs', ['action' => 'slots.weekly_schedule_updated']);
    }

    public function test_save_rejects_close_before_open(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WeeklySchedule::class)
            ->fillForm(['day_1_open' => '22:00', 'day_1_close' => '16:00'])
            ->call('save');

        $this->assertDatabaseMissing('opening_hours', ['weekday' => 1, 'open_time' => '22:00:00']);
    }

    public function test_saved_schedule_is_respected_by_bookings(): void
    {
        // Un miércoles concreto.
        $wednesday = Carbon::parse('2026-06-10');

        Livewire::actingAs($this->admin())
            ->test(WeeklySchedule::class)
            ->fillForm([
                'day_'.$wednesday->dayOfWeek.'_open' => '16:00',
                'day_'.$wednesday->dayOfWeek.'_close' => '22:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $hours = (new OperatingSchedule)->effectiveFor($wednesday);
        $this->assertSame('16:00:00', $hours['open']);
        $this->assertSame('22:00:00', $hours['close']);
    }

    public function test_idempotent_save_does_not_record_a_spurious_audit(): void
    {
        // Primer guardado.
        Livewire::actingAs($this->admin())
            ->test(WeeklySchedule::class)
            ->fillForm(['day_1_open' => '16:00', 'day_1_close' => '22:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $auditCount = AuditLog::where('action', 'slots.weekly_schedule_updated')->count();

        // Re-guardar sin tocar nada (mount carga lo guardado) NO debe registrar otro cambio.
        Livewire::actingAs($this->admin())
            ->test(WeeklySchedule::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($auditCount, AuditLog::where('action', 'slots.weekly_schedule_updated')->count());
    }
}
