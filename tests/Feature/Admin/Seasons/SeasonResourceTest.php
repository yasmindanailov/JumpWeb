<?php

namespace Tests\Feature\Admin\Seasons;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Seasons\Pages\CreateSeason;
use App\Filament\Resources\Seasons\Pages\EditSeason;
use App\Filament\Resources\Seasons\SeasonResource;
use App\Models\OpeningHour;
use App\Models\Season;
use App\Support\ParkSchedule;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.7 (#207) — Temporadas: gating por `slots.manage`, CRUD, validación
 * (fin≥inicio, cierre>apertura), borrado y que las reservas (vía `ParkSchedule`) aplican
 * la temporada vigente.
 */
class SeasonResourceTest extends TestCase
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

    public function test_gating(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(SeasonResource::canViewAny());

        $this->actingAs($this->staff())->get('/admin/seasons')->assertForbidden();
    }

    public function test_create_persists_and_audits(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSeason::class)
            ->fillForm([
                'name' => 'Verano',
                'start_date' => '2026-07-01',
                'end_date' => '2026-08-31',
                'open_time' => '11:00',
                'close_time' => '22:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $season = Season::where('name', 'Verano')->firstOrFail();
        $this->assertTrue($season->is_active);
        $this->assertSame('2026-07-01', $season->start_date->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'slots.season_created', 'target_id' => $season->id]);
    }

    public function test_create_rejects_end_before_start(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSeason::class)
            ->fillForm([
                'name' => 'Mala', 'start_date' => '2026-08-31', 'end_date' => '2026-07-01',
                'open_time' => '11:00', 'close_time' => '22:00',
            ])
            ->call('create');

        $this->assertDatabaseMissing('seasons', ['name' => 'Mala']);
    }

    public function test_create_rejects_close_before_open(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSeason::class)
            ->fillForm([
                'name' => 'Mala', 'start_date' => '2026-07-01', 'end_date' => '2026-08-31',
                'open_time' => '22:00', 'close_time' => '11:00',
            ])
            ->call('create');

        $this->assertDatabaseMissing('seasons', ['name' => 'Mala']);
    }

    public function test_delete_removes_and_audits(): void
    {
        $season = Season::create([
            'name' => 'Verano', 'start_date' => '2026-07-01', 'end_date' => '2026-08-31',
            'open_time' => '11:00:00', 'close_time' => '22:00:00', 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditSeason::class, ['record' => $season->id])
            ->callAction('deleteSeason');

        $this->assertDatabaseMissing('seasons', ['id' => $season->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'slots.season_deleted', 'target_id' => $season->id]);
    }

    public function test_created_season_is_applied_by_bookings(): void
    {
        $date = Carbon::parse('2026-07-15'); // dentro del rango
        OpeningHour::create(['weekday' => $date->dayOfWeek, 'open_time' => '16:00:00', 'close_time' => '21:00:00']);

        Livewire::actingAs($this->admin())
            ->test(CreateSeason::class)
            ->fillForm([
                'name' => 'Verano', 'start_date' => '2026-07-01', 'end_date' => '2026-08-31',
                'open_time' => '11:00', 'close_time' => '22:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $hours = (new ParkSchedule)->effectiveFor($date);
        $this->assertSame('11:00:00', $hours['open']); // gana la temporada sobre el semanal
        $this->assertSame('22:00:00', $hours['close']);
    }
}
