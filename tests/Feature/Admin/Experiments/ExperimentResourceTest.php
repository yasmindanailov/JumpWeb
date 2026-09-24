<?php

namespace Tests\Feature\Admin\Experiments;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Experiment;
use App\Domain\Platform\Services\Analytics\Experiments;
use App\Domain\Platform\Services\Analytics\Visitor;
use App\Filament\Pages\AdminSettingsHub;
use App\Filament\Resources\Experiments\ExperimentResource;
use App\Filament\Resources\Experiments\Pages\CreateExperiment;
use App\Filament\Resources\Experiments\Pages\EditExperiment;
use App\Filament\Resources\Experiments\Pages\ListExperiments;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **T5b de la analítica — los experimentos desde el panel** (`docs/specs/analitica.md` §4.4): gating por
 * `settings.manage` y la tarjeta en «Ajustes»; el alta con sus variantes y su rastro; la forma de la clave y de las
 * variantes; que un experimento VIVO no deja tocar la clave ni las variantes pero sí apagarse; y que borrarlo deja
 * de asignar al instante, con rastro.
 */
class ExperimentResourceTest extends TestCase
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
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $user;
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function validForm(array $overrides = []): array
    {
        return $overrides + [
            'key' => 'shell',
            'name' => 'Cajón o isla',
            'active' => true,
            'variants' => [['key' => 'cajon', 'weight' => 1], ['key' => 'isla', 'weight' => 1]],
        ];
    }

    public function test_gating_and_the_card_in_settings(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(ExperimentResource::canViewAny());
        $this->get('/admin/experimentos')->assertOk();

        $urls = collect((new AdminSettingsHub)->visibleAreas())->flatMap(static fn (array $area): array => array_column($area['items'], 'url'))->all();
        $this->assertContains(ExperimentResource::getUrl('index'), $urls, 'la tarjeta de «Ajustes» lleva a los experimentos');

        $this->actingAs($this->staff());
        $this->assertFalse(ExperimentResource::canViewAny());
        $this->get('/admin/experimentos')->assertForbidden();
    }

    public function test_create_persists_the_variants_in_order_audits_and_starts_assigning(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateExperiment::class)
            ->fillForm($this->validForm(['variants' => [['key' => 'isla', 'weight' => 90], ['key' => 'cajon', 'weight' => 10]]]))
            ->call('create')
            ->assertHasNoFormErrors();

        $experiment = Experiment::firstOrFail();
        $this->assertSame('shell', $experiment->key);
        $this->assertTrue($experiment->active);
        $this->assertSame(['isla' => 90, 'cajon' => 10], $experiment->weightedVariants(), 'las variantes se guardan en el orden del formulario');
        $this->assertDatabaseHas('audit_logs', ['action' => 'experiments.saved', 'target_id' => $experiment->id]);

        $this->assertArrayHasKey('shell', Experiments::assignments(Visitor::mint(), null), 'un experimento creado activo asigna en la petición siguiente');
    }

    public function test_the_key_and_the_variants_have_a_form(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateExperiment::class)
            ->fillForm($this->validForm(['key' => 'Mal Clave']))
            ->call('create')
            ->assertHasFormErrors(['key']);

        Livewire::actingAs($this->admin())
            ->test(CreateExperiment::class)
            ->fillForm($this->validForm(['variants' => [['key' => 'solo', 'weight' => 1]]]))
            ->call('create')
            ->assertHasFormErrors(['variants']);

        Experiment::create($this->validForm());

        Livewire::actingAs($this->admin())
            ->test(CreateExperiment::class)
            ->fillForm($this->validForm(['name' => 'Otro con la misma clave']))
            ->call('create')
            ->assertHasFormErrors(['key']);

        $this->assertSame(1, Experiment::count());
    }

    /**
     * ⚠️ La asignación es `hash(clave | sujeto)` repartido por pesos en orden: tocar la clave, los pesos o el orden con
     * el experimento VIVO rebaraja a todo el mundo. El formulario los bloquea (un campo deshabilitado no viaja al
     * guardar), y lo que sí se puede es apagarlo.
     */
    public function test_a_live_experiment_keeps_its_key_and_variants_but_can_be_switched_off(): void
    {
        $experiment = Experiment::create($this->validForm());
        $visitor = Visitor::mint();
        $before = Experiments::assignments($visitor, null)['shell'];

        Livewire::actingAs($this->admin())
            ->test(EditExperiment::class, ['record' => $experiment->id])
            ->fillForm(['key' => 'otra', 'name' => 'Renombrado', 'variants' => [['key' => 'a', 'weight' => 1], ['key' => 'b', 'weight' => 1]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $experiment->refresh();
        $this->assertSame('shell', $experiment->key, 'la clave de un experimento vivo no cambia');
        $this->assertSame(['cajon' => 1, 'isla' => 1], $experiment->weightedVariants(), 'las variantes de un experimento vivo no cambian');
        $this->assertSame('Renombrado', $experiment->name, 'el nombre sí');
        $this->assertSame($before, Experiments::assignments($visitor, null)['shell'], 'nadie se rebaraja');

        Livewire::actingAs($this->admin())
            ->test(EditExperiment::class, ['record' => $experiment->id])
            ->fillForm(['active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($experiment->refresh()->active);
        $this->assertSame([], Experiments::assignments($visitor, null), 'apagado deja de asignar al instante');
        $this->assertSame(2, AuditLog::where('action', 'experiments.saved')->where('target_id', $experiment->id)->count());
    }

    public function test_deleting_audits_and_stops_assigning_at_once(): void
    {
        $experiment = Experiment::create($this->validForm());
        $visitor = Visitor::mint();
        $this->assertArrayHasKey('shell', Experiments::assignments($visitor, null));

        Livewire::actingAs($this->admin())
            ->test(EditExperiment::class, ['record' => $experiment->id])
            ->callAction('deleteExperiment');

        $this->assertDatabaseMissing('experiments', ['id' => $experiment->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'experiments.deleted', 'target_id' => $experiment->id]);
        $this->assertSame([], Experiments::assignments($visitor, null));
    }

    public function test_the_list_shows_the_real_state_of_each_experiment(): void
    {
        $running = Experiment::create($this->validForm());
        $scheduled = Experiment::create($this->validForm(['key' => 'futuro', 'started_at' => now()->addDay()]));
        $finished = Experiment::create($this->validForm(['key' => 'pasado', 'ended_at' => now()->subDay()]));
        $off = Experiment::create($this->validForm(['key' => 'apagado', 'active' => false]));

        Livewire::actingAs($this->admin())
            ->test(ListExperiments::class)
            ->assertCanSeeTableRecords([$running, $scheduled, $finished, $off])
            ->assertSee(__('admin.experiments.state.running'))
            ->assertSee(__('admin.experiments.state.scheduled'))
            ->assertSee(__('admin.experiments.state.finished'))
            ->assertSee(__('admin.experiments.state.inactive'))
            ->assertSee('cajon 1 · isla 1');
    }
}
