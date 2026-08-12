<?php

namespace Tests\Feature\Admin\SlotTemplates;

use App\Filament\Resources\SlotTemplates\Pages\ListSlotTemplates;
use App\Models\Role;
use App\Models\SlotTemplate;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P15 — Generador EN BLOQUE de plantillas de franja (trait `GeneratesSlotTemplates`): de zona +
 * días + horario (inicio→cierre) + duración + aforo crea TODAS las plantillas de golpe (la forma en
 * que se sembraron las de cumpleaños, ahora para cualquier zona). Invariantes: online ≤ total; nunca
 * crea una franja que termine tras el cierre; no duplica; «reemplazar» limpia primero; opcionalmente
 * regenera las franjas concretas.
 */
class GenerateSlotTemplatesActionTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    /** @param array<string,mixed> $overrides */
    private function generate(array $overrides = []): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListSlotTemplates::class)
            ->callAction('generateSlotTemplates', array_merge([
                'zone_id' => $this->zone->id,
                'weekdays' => [1, 2],          // lunes y martes (convenio Carbon)
                'start_time' => '11:00',
                'end_time' => '14:00',
                'duration_min' => 60,
                'interval_min' => null,        // vacío → = duración (franjas seguidas)
                'capacity' => 50,
                'online_capacity' => 30,
                'replace_existing' => false,
                'regenerate_after' => false,
            ], $overrides));
    }

    public function test_generates_a_grid_of_templates_for_the_selected_days(): void
    {
        $this->generate();

        // 11:00–14:00, franjas de 60 min seguidas → inicios 11:00, 12:00, 13:00 (una a las 14:00 terminaría
        // a las 15:00 → fuera del cierre). × 2 días = 6 plantillas.
        $this->assertSame(6, SlotTemplate::count());
        foreach ([1, 2] as $weekday) {
            foreach (['11:00:00', '12:00:00', '13:00:00'] as $time) {
                $this->assertDatabaseHas('slot_templates', [
                    'zone_id' => $this->zone->id, 'weekday' => $weekday, 'start_time' => $time,
                    'duration_min' => 60, 'capacity' => 50, 'online_capacity' => 30, 'is_active' => true,
                ]);
            }
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'slot_templates.generated']);
    }

    public function test_custom_interval_spaces_the_slots(): void
    {
        // Franjas de 60 min pero empezando cada 30 min (oleadas solapadas): 11:00, 11:30, 12:00, 12:30, 13:00.
        $this->generate(['weekdays' => [1], 'interval_min' => 30]);

        $this->assertSame(5, SlotTemplate::where('weekday', 1)->count());
        $this->assertDatabaseHas('slot_templates', ['weekday' => 1, 'start_time' => '11:30:00']);
        $this->assertDatabaseHas('slot_templates', ['weekday' => 1, 'start_time' => '13:00:00']);
    }

    public function test_does_not_duplicate_existing_templates(): void
    {
        $this->generate(); // 6
        $this->generate(); // mismas → 0 nuevas

        $this->assertSame(6, SlotTemplate::count());
    }

    public function test_replace_existing_wipes_then_recreates(): void
    {
        SlotTemplate::create([
            'zone_id' => $this->zone->id, 'weekday' => 1, 'start_time' => '08:00:00',
            'duration_min' => 60, 'capacity' => 10, 'online_capacity' => 10, 'is_active' => true,
        ]);

        $this->generate(['weekdays' => [1], 'replace_existing' => true]);

        $this->assertSame(3, SlotTemplate::where('zone_id', $this->zone->id)->where('weekday', 1)->count());
        $this->assertDatabaseMissing('slot_templates', [
            'zone_id' => $this->zone->id, 'weekday' => 1, 'start_time' => '08:00:00',
        ]);
    }

    public function test_online_above_total_is_rejected(): void
    {
        $this->generate(['capacity' => 20, 'online_capacity' => 50]);

        $this->assertSame(0, SlotTemplate::count());
    }

    public function test_no_slots_fit_is_rejected(): void
    {
        // Duración mayor que la ventana → ninguna franja cabe.
        $this->generate(['start_time' => '11:00', 'end_time' => '11:30', 'duration_min' => 60]);

        $this->assertSame(0, SlotTemplate::count());
    }

    public function test_regenerate_after_materialises_concrete_slots(): void
    {
        $wed = Carbon::now()->next(Carbon::WEDNESDAY);

        $this->generate([
            'weekdays' => [$wed->dayOfWeek],
            'regenerate_after' => true,
        ]);

        // La plantilla del miércoles a las 12:00 se materializó como franja concreta en esa fecha.
        $this->assertDatabaseHas('slots', [
            'zone_id' => $this->zone->id, 'date' => $wed->toDateString(), 'start_time' => '12:00:00',
        ]);
    }
}
