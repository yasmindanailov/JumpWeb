<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Catalog\CatalogResource;
use App\Filament\Resources\Catalog\Pages\CreateCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.6 iter. 2 (#192) — Alta de un producto nuevo del catálogo: elección de tipo,
 * normalización por tipo, precios en el alta, integridad del pack, auditoría y la
 * redirección a la ficha de edición.
 */
class CatalogCreateTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private RateType $normal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->zone = Zone::create([
            'slug' => 'jump',
            'name' => ['es' => 'Jump'],
            'accent' => 'jump',
            'color' => '#FF5B22',
            'position' => 1,
            'is_active' => true,
        ]);

        $this->normal = RateType::create([
            'key' => RateType::KEY_NORMAL,
            'label' => ['es' => 'Día normal'],
            'is_special' => false,
            'priority' => 0,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    // ─── Alta por tipo ───────────────────────────────────────────────────────

    public function test_admin_can_create_an_entry(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ENTRY,
                'name' => ['es' => 'Jump · 1 hora'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
                'duration_min' => 60,
                'price_rate_'.$this->normal->id => '9.90',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = TicketType::where('type', TicketType::TYPE_ENTRY)->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertSame('Jump · 1 hora', $entry->tr('name', 'es'));
        $this->assertSame($this->zone->id, $entry->zone_id);
        $this->assertNull($entry->min_qty);  // una entrada no lleva campos de pack
        $this->assertSame(990, (int) $entry->prices()->where('rate_type_id', $this->normal->id)->value('amount_cents'));
    }

    public function test_admin_can_create_a_pack_with_event_fields_and_prices(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_PACK,
                'name' => ['es' => 'Cumpleaños Jump'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
                'duration_min' => 120,
                'min_qty' => 8,
                'max_qty' => 20,
                'deposit_type' => TicketType::DEPOSIT_FIXED,
                'deposit_value' => 3000,
                'event_fields' => [
                    ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado']],
                ],
                'price_rate_'.$this->normal->id => '199',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pack = TicketType::where('type', TicketType::TYPE_PACK)->latest('id')->first();
        $this->assertNotNull($pack);
        $this->assertSame(8, $pack->min_qty);
        $this->assertSame(20, $pack->max_qty);
        $this->assertSame(3000, $pack->deposit_value);
        $this->assertSame(1, (int) $pack->seats_per_unit); // L2: un pack siempre es «1 niño = 1 plaza»
        $this->assertSame('celebrant', $pack->eventFields()[0]['key']);
        $this->assertSame(19900, (int) $pack->prices()->where('rate_type_id', $this->normal->id)->value('amount_cents'));
    }

    public function test_migration_normalizes_pack_seats_per_unit_leaving_entries_intact(): void
    {
        // L2 (auditoría Fase 1): la migración repara packs LEGACY con seats_per_unit>1 (sobreventa: el
        // checkout valida el cupo en unidades pero lo consume en plazas). Las entradas no se tocan.
        $pack = TicketType::create([
            'name' => ['es' => 'Cumple legacy'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 8, 'max_qty' => 20, 'seats_per_unit' => 3,
            'is_sellable' => true, 'is_active' => true, 'position' => 10,
        ]);
        $entry = TicketType::create([
            'name' => ['es' => 'Grupo 4'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 4, 'is_sellable' => true, 'is_active' => true, 'position' => 11,
        ]);

        (require database_path('migrations/2026_06_12_000002_normalize_pack_seats_per_unit.php'))->up();

        $this->assertSame(1, (int) $pack->fresh()->seats_per_unit);  // pack normalizado a 1
        $this->assertSame(4, (int) $entry->fresh()->seats_per_unit); // entrada intacta (grupo de 4)
    }

    public function test_admin_can_create_an_addon_without_zone_or_pack_fields(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ADDON,
                'name' => ['es' => 'Calcetines'],
                'price_rate_'.$this->normal->id => '2',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $addon = TicketType::where('type', TicketType::TYPE_ADDON)->latest('id')->first();
        $this->assertNotNull($addon);
        $this->assertNull($addon->zone_id);
        $this->assertNull($addon->min_qty);
        $this->assertSame(TicketType::DEPOSIT_NONE, $addon->deposit_type);
        $this->assertSame(200, (int) $addon->prices()->where('rate_type_id', $this->normal->id)->value('amount_cents'));
    }

    // ─── Validación ──────────────────────────────────────────────────────────

    public function test_type_is_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => '',
                'name' => ['es' => 'Sin tipo'],
            ])
            ->call('create')
            ->assertHasFormErrors(['type']);
    }

    public function test_name_es_is_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ENTRY,
                'name' => ['es' => ''],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['name.es']);
    }

    public function test_pack_max_qty_cannot_be_below_min_qty(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_PACK,
                'name' => ['es' => 'Pack inválido'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
                'min_qty' => 10,
                'max_qty' => 5,
            ])
            ->call('create')
            ->assertHasFormErrors(['max_qty']);

        $this->assertDatabaseMissing('ticket_types', ['name->es' => 'Pack inválido']);
    }

    public function test_sellable_entry_without_price_is_still_created(): void
    {
        // Sin precio: NO bloquea el alta (solo avisa). El producto queda creado, no vendible
        // hasta fijarle precio.
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ENTRY,
                'name' => ['es' => 'Sin precio'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
                'is_sellable' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('ticket_types', ['name->es' => 'Sin precio']);
    }

    // ─── Transformaciones / efectos ──────────────────────────────────────────

    public function test_features_textarea_is_stored_as_i18n_list_on_create(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ENTRY,
                'name' => ['es' => 'Con ventajas'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
                'features_es' => "Uno\n  Dos  \n\nTres",
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = TicketType::where('name->es', 'Con ventajas')->first();
        $this->assertSame(['Uno', 'Dos', 'Tres'], $entry->features['es']);
    }

    public function test_new_product_position_is_assigned_at_the_end(): void
    {
        TicketType::create([
            'name' => ['es' => 'Existente'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'seats_per_unit' => 1, 'position' => 5,
            'is_active' => true, 'is_sellable' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ENTRY,
                'name' => ['es' => 'Nuevo al final'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(6, TicketType::where('name->es', 'Nuevo al final')->value('position'));
    }

    public function test_create_is_audited(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ENTRY,
                'name' => ['es' => 'Auditado'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = TicketType::where('name->es', 'Auditado')->first();
        $log = AuditLog::where('action', 'catalog.created')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($created->id, (int) $log->target_id);
        $this->assertSame(TicketType::TYPE_ENTRY, $log->payload['type']);
    }

    public function test_new_product_is_active_by_default(): void
    {
        // Sin tocar los toggles: un Toggle de Filament se dehidrata como false y al crear
        // sobreescribiría el default de la BD → un producto nuevo nacería oculto. Los
        // ->default() del form lo evitan: nace activo (visible) pero no vendible (borrador).
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ENTRY,
                'name' => ['es' => 'Por defecto'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = TicketType::where('name->es', 'Por defecto')->first();
        $this->assertTrue($entry->is_active, 'Un producto nuevo debe nacer activo (visible).');
        $this->assertFalse($entry->is_sellable, 'Un producto nuevo no debe nacer vendible (borrador seguro).');
        $this->assertFalse($entry->featured);
    }

    public function test_pack_can_be_created_without_setting_deposit_type(): void
    {
        // deposit_type es NOT NULL: sin tocar el desplegable de señal debe quedar 'none'
        // (default del form + coerción del trait), no null que rompería el insert.
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_PACK,
                'name' => ['es' => 'Pack sin señal'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
                'min_qty' => 8,
                'max_qty' => 20,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pack = TicketType::where('name->es', 'Pack sin señal')->first();
        $this->assertNotNull($pack);
        $this->assertSame(TicketType::DEPOSIT_NONE, $pack->deposit_type);
        $this->assertSame(0, $pack->deposit_value);
    }

    public function test_creating_with_price_audits_prices_and_invalidates_cta_cache(): void
    {
        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ENTRY,
                'name' => ['es' => 'Con precio'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
                'is_sellable' => true,
                'price_rate_'.$this->normal->id => '8.00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = TicketType::where('name->es', 'Con precio')->first();
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.prices_updated', 'target_id' => $created->id]);
        // El CTA cacheado "desde X €" se invalida al fijar precio en el alta.
        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }

    // ─── Visibilidad de campos por tipo (auditoría de consumo) ───────────────

    public function test_entry_form_shows_entry_only_text_fields(): void
    {
        // Por defecto el tipo es entrada: badge, destacado y unidad de precio se muestran.
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm(['type' => TicketType::TYPE_ENTRY])
            ->assertFormFieldVisible('badge.es')
            ->assertFormFieldVisible('featured')
            ->assertFormFieldVisible('period_label.es')
            ->assertFormFieldVisible('description.es');
    }

    public function test_pack_form_shows_badge_and_hides_featured(): void
    {
        // El pack SÍ lleva la etiqueta destacada desde `#585` (la web la pinta en las tarjetas de
        // cumpleaños, en `/cumpleanos` y en `/servicios`); «destacado» sigue siendo de entradas:
        // es quien lidera su zona en el carril de tarifas. Usa también unidad de precio y descripción.
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm(['type' => TicketType::TYPE_PACK])
            ->assertFormFieldVisible('badge.es')
            ->assertFormFieldHidden('featured')
            ->assertFormFieldVisible('period_label.es')
            ->assertFormFieldVisible('description.es');
    }

    public function test_addon_form_hides_unused_text_fields(): void
    {
        // El complemento solo consume nombre + ventajas; oculta badge/destacado/unidad/descripción.
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm(['type' => TicketType::TYPE_ADDON])
            ->assertFormFieldHidden('badge.es')
            ->assertFormFieldHidden('featured')
            ->assertFormFieldHidden('period_label.es')
            ->assertFormFieldHidden('description.es')
            ->assertFormFieldVisible('name.es');
    }

    public function test_create_redirects_to_edit_page(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ENTRY,
                'name' => ['es' => 'Redirige'],
                'zone_id' => $this->zone->id,
                'seats_per_unit' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(CatalogResource::getUrl('edit', [
                'record' => TicketType::where('name->es', 'Redirige')->first(),
            ]));
    }
}
