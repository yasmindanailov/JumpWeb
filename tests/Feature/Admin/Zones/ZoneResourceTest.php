<?php

namespace Tests\Feature\Admin\Zones;

use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Zones\Pages\CreateZone;
use App\Filament\Resources\Zones\Pages\EditZone;
use App\Filament\Resources\Zones\ZoneResource;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.9 (adelanto) — `ZoneResource`: gating por `content.manage`, CRUD, unicidad de slug,
 * cupo de packs por zona (override nullable) y borrado seguro (bloqueado si la zona tiene
 * productos o franjas).
 */
class ZoneResourceTest extends TestCase
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
            'slug' => 'eventos',
            'name' => ['es' => 'Eventos'],
            'accent' => 'jump',
            'position' => 3,
            'is_active' => true,
            'prep_blocks_cupo' => '',
        ], $overrides);
    }

    public function test_gating(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(ZoneResource::canViewAny());

        $this->actingAs($this->staff())->get('/admin/zones')->assertForbidden();
    }

    public function test_create_persists_with_null_cupo_and_audits(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateZone::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $zone = Zone::where('slug', 'eventos')->firstOrFail();
        $this->assertSame('Eventos', $zone->tr('name'));
        $this->assertNull($zone->max_per_slot, 'cupo vacío = null (usa el global)');
        $this->assertNull($zone->max_guests_per_slot);
        $this->assertNull($zone->prep_blocks_cupo);
        $this->assertTrue($zone->is_active);
        $this->assertTrue($zone->show_in_landing, 'por defecto, una zona nueva se muestra en la landing');
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.zone_created', 'target_id' => $zone->id]);
    }

    public function test_create_can_hide_zone_from_landing(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateZone::class)
            ->fillForm($this->validForm(['show_in_landing' => false]))
            ->call('create')
            ->assertHasNoFormErrors();

        $zone = Zone::where('slug', 'eventos')->firstOrFail();
        $this->assertTrue($zone->is_active, 'sigue operativa');
        $this->assertFalse($zone->show_in_landing, 'pero oculta de la landing');
    }

    public function test_create_with_per_zone_cupo_override(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateZone::class)
            ->fillForm($this->validForm([
                'max_per_slot' => 2,
                'max_guests_per_slot' => 15,
                'prep_blocks_cupo' => '0',
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $zone = Zone::where('slug', 'eventos')->firstOrFail();
        $this->assertSame(2, $zone->max_per_slot);
        $this->assertSame(15, $zone->max_guests_per_slot);
        $this->assertFalse($zone->prep_blocks_cupo);
    }

    public function test_create_persists_image_path(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateZone::class)
            ->fillForm($this->validForm(['image' => '  images/attractions/park_jump.webp  ']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'images/attractions/park_jump.webp',
            Zone::where('slug', 'eventos')->value('image'),
            'la ruta de imagen se recorta y persiste (la landing pinta la card con foto)',
        );
    }

    public function test_blank_image_is_stored_as_null(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateZone::class)
            ->fillForm($this->validForm(['image' => '   ']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(
            Zone::where('slug', 'eventos')->value('image'),
            'imagen vacía → null (la card cae al diseño sin foto)',
        );
    }

    public function test_accent_rejects_unsafe_characters(): void
    {
        // El acento viaja al DOM dentro de directivas Alpine (`goToRides('…')`). Un carácter como
        // la comilla rompería la expresión JS (el navegador decodifica la entidad antes de Alpine)
        // → se restringe a [a-z0-9-_] como el slug (revisión adversarial #230).
        Livewire::actingAs($this->admin())
            ->test(CreateZone::class)
            ->fillForm($this->validForm(['accent' => "jump'); alert(1); //"]))
            ->call('create')
            ->assertHasFormErrors(['accent']);

        $this->assertSame(0, Zone::where('slug', 'eventos')->count(), 'un acento inseguro no crea la zona');
    }

    public function test_image_rejects_path_traversal(): void
    {
        // Saneo defensivo (revisión #231): una ruta con `..` (traversal) se descarta → null (sin
        // foto), en vez de persistir una ruta que escapa de public/.
        Livewire::actingAs($this->admin())
            ->test(CreateZone::class)
            ->fillForm($this->validForm(['image' => '../../../etc/passwd']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Zone::where('slug', 'eventos')->value('image'), 'una ruta con .. se sanea a null');
    }

    public function test_edit_preserves_false_prep_blocks_cupo_override(): void
    {
        // Regresión: el tri-estado (null/true/false) debe sobrevivir a una edición que NO toca
        // el Select (antes, false→'' al hidratar y se degradaba a null al guardar).
        $zone = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos'], 'prep_blocks_cupo' => false]);

        Livewire::actingAs($this->admin())
            ->test(EditZone::class, ['record' => $zone->id])
            ->fillForm(['max_guests_per_slot' => 25])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($zone->refresh()->prep_blocks_cupo, 'el override false sobrevive a la edición');
    }

    public function test_edit_preserves_true_prep_blocks_cupo_override(): void
    {
        $zone = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos'], 'prep_blocks_cupo' => true]);

        Livewire::actingAs($this->admin())
            ->test(EditZone::class, ['record' => $zone->id])
            ->fillForm(['max_guests_per_slot' => 25])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($zone->refresh()->prep_blocks_cupo);
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Otra']]);

        Livewire::actingAs($this->admin())
            ->test(CreateZone::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasFormErrors(['slug']);

        $this->assertSame(1, Zone::where('slug', 'eventos')->count());
    }

    public function test_slug_is_immutable_on_edit(): void
    {
        $zone = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos'], 'position' => 3]);

        Livewire::actingAs($this->admin())
            ->test(EditZone::class, ['record' => $zone->id])
            ->fillForm(['name' => ['es' => 'Eventos renombrados']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('eventos', $zone->refresh()->slug, 'el slug no cambia al editar (clave del seeder)');
    }

    public function test_edit_updates_and_audits(): void
    {
        $zone = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos'], 'position' => 3]);

        Livewire::actingAs($this->admin())
            ->test(EditZone::class, ['record' => $zone->id])
            ->fillForm(['max_guests_per_slot' => 30])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(30, $zone->refresh()->max_guests_per_slot);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.zone_updated', 'target_id' => $zone->id]);
    }

    public function test_delete_blocked_when_zone_has_products_or_slots(): void
    {
        $zone = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos']]);
        TicketType::create([
            'name' => ['es' => 'Pack'], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditZone::class, ['record' => $zone->id])
            ->callAction('deleteZone');

        $this->assertNotNull($zone->fresh(), 'no se borra una zona con productos');
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.zone_delete_blocked', 'target_id' => $zone->id]);
    }

    public function test_delete_allowed_when_zone_is_empty(): void
    {
        $zone = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos']]);

        Livewire::actingAs($this->admin())
            ->test(EditZone::class, ['record' => $zone->id])
            ->callAction('deleteZone');

        $this->assertNull($zone->fresh());
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.zone_deleted', 'target_id' => $zone->id]);
    }

    public function test_delete_blocked_when_zone_has_slots(): void
    {
        $zone = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos']]);
        Slot::create([
            'zone_id' => $zone->id, 'date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 10, 'online_capacity' => 10,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditZone::class, ['record' => $zone->id])
            ->callAction('deleteZone');

        $this->assertNotNull($zone->fresh(), 'no se borra una zona con franjas (cascade destruiría ventas/tickets)');
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.zone_delete_blocked', 'target_id' => $zone->id]);
    }

    public function test_saving_a_zone_invalidates_the_landing_cta_cache(): void
    {
        // Auditoría Fase 1 · Sistema 6 · W2/W3: desactivar una zona cambia qué ENTRADAS son operativas
        // (comprables) → mueve el mínimo del CTA «desde X €», que filtra por `inOperationalZone()`.
        // `EditZone::afterSave` debe invalidar la caché para no anunciar un precio de zona desactivada.
        $zone = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos'], 'accent' => 'jump', 'is_active' => true]);
        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(EditZone::class, ['record' => $zone->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }
}
