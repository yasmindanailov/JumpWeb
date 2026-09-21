<?php

namespace Tests\Feature\Admin\Attractions;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Attractions\Pages\CreateAttraction;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `AttractionResource`: marcar una atracción como «Destacada» (`is_special`) desde el panel.
 *
 * ⚠️ **Este fichero era de `#228` y cubría DOS cosas**: el complemento de pago vinculado —con su
 * aviso cuando no estaba enganchado a una entrada vendible de la zona— y «Destacada». El
 * complemento se retiró entero en `#668` (`#632`·P3: son presentación y **0 de 23** lo usaban), así
 * que aquí queda lo que sigue teniendo sujeto. *Se parte por lo que AFIRMA cada caso, no se borra
 * el fichero porque su título nombre la pieza que se fue.*
 */
class AttractionComplementPanelTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    /**
     * ⚠️ **Este caso era «con complemento Y destacada» y pierde su primera mitad** (`#668`): el
     * selector de complemento se retiró con la pieza (`#632`·P3, **0 de 23** lo usaban). Lo que
     * sigue vigilando es lo que se queda: que «Destacada» persista y que el alta deje su rastro en
     * la auditoría.
     */
    public function test_create_with_special_persists_and_audits(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAttraction::class)
            ->fillForm([
                'zone_id' => $this->zone->id,
                'name' => ['es' => 'Tirolina aérea'],
                'position' => 1,
                'is_active' => true,
                'is_special' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $attraction = Attraction::firstOrFail();
        $this->assertTrue($attraction->is_special);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'content.attraction_created', 'target_id' => $attraction->id,
        ]);
    }

    public function test_is_special_defaults_to_false_when_untouched(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAttraction::class)
            ->fillForm(['zone_id' => $this->zone->id, 'name' => ['es' => 'Foam Pit'], 'position' => 1])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(Attraction::firstOrFail()->is_special);
    }
}
