<?php

namespace Tests\Feature\Admin\LandingServices;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Models\LandingService;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\LandingServices\LandingServiceResource;
use App\Filament\Resources\LandingServices\Pages\CreateLandingService;
use App\Filament\Resources\LandingServices\Pages\EditLandingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F4/F6 (#256) — `LandingServiceResource`: gating por `content.manage`, CRUD con i18n + specs
 * (repeater) + toggles, slug único/inmutable, y que vincular un pack lo saca de Cumpleaños.
 */
class LandingServiceResourceTest extends TestCase
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

    /** @return array<string,mixed> */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'eventos-empresa',
            'position' => 1,
            'is_active' => true,
            'title' => ['es' => 'Eventos de empresa'],
        ], $overrides);
    }

    public function test_gating(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(LandingServiceResource::canViewAny());
        $this->get('/admin/landing-services')->assertOk();

        $this->actingAs($this->staff())->get('/admin/landing-services')->assertForbidden();
    }

    public function test_create_persists_with_i18n_specs_and_toggles(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateLandingService::class)
            ->fillForm($this->validForm([
                'image' => '  images/attractions/park_jump.webp  ',
                'title' => ['es' => 'Eventos de empresa', 'en' => 'Company events', 'fr' => ''],
                'specs' => ['es' => [['label' => 'Grupo', 'value' => 'Mín. 30'], ['label' => '', 'value' => '']]],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $svc = LandingService::firstOrFail();
        $this->assertSame('eventos-empresa', $svc->slug);
        $this->assertSame('images/attractions/park_jump.webp', $svc->image, 'ruta recortada');
        $this->assertSame(['es' => 'Eventos de empresa', 'en' => 'Company events'], $svc->title, 'fr vacío descartado');
        $this->assertSame([['label' => 'Grupo', 'value' => 'Mín. 30']], $svc->tr('specs', 'es'), 'fila vacía descartada');
        $this->assertNull($svc->ticket_type_id, 'sin pack = solo contacto');
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.landing_service_created', 'target_id' => $svc->id]);
    }

    public function test_slug_required_and_unique(): void
    {
        LandingService::create(['slug' => 'dup', 'title' => ['es' => 'X']]);

        Livewire::actingAs($this->admin())
            ->test(CreateLandingService::class)
            ->fillForm($this->validForm(['slug' => 'dup']))
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_create_requires_spanish_title(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateLandingService::class)
            ->fillForm($this->validForm(['title' => ['es' => '']]))
            ->call('create')
            ->assertHasFormErrors(['title.es']);
    }

    public function test_linking_a_pack_moves_it_out_of_the_birthday_surface(): void
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Pack empresa'], 'type' => TicketType::TYPE_PACK,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(CreateLandingService::class)
            ->fillForm($this->validForm(['slug' => 'pack-empresa', 'ticket_type_id' => $pack->id]))
            ->call('create')
            ->assertHasNoFormErrors();

        $svc = LandingService::firstOrFail();
        $this->assertSame($pack->id, $svc->ticket_type_id);
        $this->assertTrue($pack->fresh()->landingService()->exists());
        $this->assertFalse(
            TicketType::birthdaySurfacePacks()->pluck('id')->contains($pack->id),
            'un pack con LandingService sale de la superficie Cumpleaños',
        );
    }

    /**
     * ⚠️⚠️ **Editar un servicio NO reescribe `show_in_nav`** (`#521`). El formulario dejó de pintar
     * su interruptor, y la normalización lo forzaba a `true` cuando no llegaba: con esa línea viva,
     * **todo servicio que se editara quedaba marcado** sin que nadie lo hubiera pedido. La columna se
     * conserva con el valor que tuviera, así que el guardado tiene que dejarla quieta.
     */
    public function test_edit_toggles_and_slug_stays(): void
    {
        $svc = LandingService::create(['slug' => 'colegios', 'title' => ['es' => 'Colegios'], 'is_active' => true, 'show_in_nav' => false]);

        Livewire::actingAs($this->admin())
            ->test(EditLandingService::class, ['record' => $svc->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $svc->refresh();
        $this->assertFalse($svc->is_active);
        $this->assertFalse(
            $svc->show_in_nav,
            'guardar el formulario reescribió `show_in_nav`, un dato que el formulario ya no enseña',
        );
        $this->assertSame('colegios', $svc->slug, 'el slug (anchor) es inmutable al editar');
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.landing_service_updated', 'target_id' => $svc->id]);
    }

    public function test_delete_and_audits(): void
    {
        $svc = LandingService::create(['slug' => 'x', 'title' => ['es' => 'X']]);

        Livewire::actingAs($this->admin())
            ->test(EditLandingService::class, ['record' => $svc->id])
            ->callAction('deleteLandingService');

        $this->assertNull($svc->fresh());
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.landing_service_deleted', 'target_id' => $svc->id]);
    }
}
