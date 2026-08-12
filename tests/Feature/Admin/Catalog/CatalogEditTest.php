<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Catalog\CatalogResource;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use App\Filament\Resources\Catalog\Pages\ListCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Fase 7.6 — Edición del catálogo (iter. 1): transformaciones i18n, validación,
 * guardas defense-in-depth (zona bloqueada en productos vendidos), editor de
 * `event_fields`, borrado seguro y auditoría.
 */
class CatalogEditTest extends TestCase
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

    private function makeEntry(array $overrides = []): TicketType
    {
        return TicketType::create(array_merge([
            'name' => ['es' => 'Jump · 1 hora'],
            'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id,
            'duration_min' => 60,
            'seats_per_unit' => 1,
            'tax_rate' => 21,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 1,
        ], $overrides));
    }

    private function makePack(array $overrides = []): TicketType
    {
        return TicketType::create(array_merge([
            'name' => ['es' => 'Cumpleaños Jump'],
            'type' => TicketType::TYPE_PACK,
            'zone_id' => $this->zone->id,
            'duration_min' => 120,
            'min_qty' => 8,
            'max_qty' => 20,
            'deposit_type' => TicketType::DEPOSIT_FIXED,
            'deposit_value' => 3000,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Homenajeado']],
            ],
            'seats_per_unit' => 1,
            'tax_rate' => 21,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 9,
        ], $overrides));
    }

    /** Da por vendido un producto: crea un pedido con una línea que lo referencia. */
    private function sell(TicketType $product): void
    {
        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
        ]);
        $order->items()->create([
            'ticket_type_id' => $product->id,
            'slot_id' => null,
            'quantity' => 1,
            'unit_price' => 1000,
            'seats' => 1,
        ]);
    }

    // ─── Edición básica + transformaciones i18n ─────────────────────────────

    public function test_admin_can_edit_basic_fields(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['is_active' => false, 'seats_per_unit' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $entry->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertSame(2, $fresh->seats_per_unit);
    }

    public function test_addon_edit_page_renders_without_zone_or_pack_sections(): void
    {
        // Para un complemento, el form oculta zona/aforo/pack → solo contenido + estado + precio.
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 20,
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/catalog/'.$addon->id.'/edit')
            ->assertOk();
    }

    public function test_admin_can_reorder_products_in_the_list(): void
    {
        // El orden de aparición se ajusta arrastrando en el listado (sustituye al campo
        // numérico de la ficha de edición).
        $first = $this->makeEntry(['position' => 1]);
        $second = $this->makeEntry(['position' => 2, 'name' => ['es' => 'Segundo']]);

        Livewire::actingAs($this->admin())
            ->test(ListCatalog::class)
            ->call('reorderTable', [$second->id, $first->id]);

        // Tras arrastrar 'segundo' delante de 'primero', su posición pasa a ser menor.
        $this->assertLessThan($first->fresh()->position, $second->fresh()->position);
    }

    public function test_features_textarea_is_stored_as_i18n_list(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            // Líneas en blanco y espacios deben descartarse.
            ->fillForm(['features_es' => "Uno\n  Dos  \n\nTres"])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Uno', 'Dos', 'Tres'], $entry->fresh()->features['es']);
    }

    public function test_empty_i18n_strings_are_compacted_to_null(): void
    {
        // badge con solo es, en/fr vacíos → se guarda solo es; si todo vacío → null.
        $entry = $this->makeEntry(['badge' => ['es' => 'Top', 'en' => 'Top', 'fr' => 'Top']]);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['badge' => ['es' => '', 'en' => '', 'fr' => '']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($entry->fresh()->badge);
    }

    public function test_update_is_audited_with_diff(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $log = AuditLog::where('action', 'catalog.updated')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($entry->id, (int) $log->target_id);
        $this->assertArrayHasKey('changed', $log->payload);
        $this->assertArrayHasKey('is_active', $log->payload['changed']);
        $this->assertFalse($log->payload['changed']['is_active']['to']);
    }

    // ─── Validación ──────────────────────────────────────────────────────────

    public function test_name_es_is_required(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['name' => ['es' => '']])
            ->call('save')
            ->assertHasFormErrors(['name.es']);
    }

    public function test_pack_max_qty_cannot_be_below_min_qty(): void
    {
        $pack = $this->makePack();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->fillForm(['min_qty' => 10, 'max_qty' => 5])
            ->call('save')
            ->assertHasFormErrors(['max_qty']);
    }

    /**
     * Un pack VÁLIDO debe poder guardarse. Cubre el bug crítico de la comparación
     * entre campos: `->rule('gte:min_qty')` no resuelve bajo el statePath `data.` de
     * Filament y bloqueaba CUALQUIER guardado del pack; el fix usa `->gte('min_qty')`.
     */
    public function test_admin_can_save_a_valid_pack(): void
    {
        $pack = $this->makePack();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->fillForm(['min_qty' => 6, 'max_qty' => 25])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $pack->fresh();
        $this->assertSame(6, $fresh->min_qty);
        $this->assertSame(25, $fresh->max_qty);
    }

    // ─── Guarda: zona bloqueada en productos vendidos (defense in depth) ──────

    public function test_zone_change_is_reverted_and_audited_for_sold_product(): void
    {
        $this->actingAs($this->admin());
        $pack = $this->makePack();
        $this->sell($pack);

        $otherZone = Zone::create([
            'slug' => 'kids', 'name' => ['es' => 'Kids'], 'accent' => 'kids',
            'color' => '#C6FF3A', 'position' => 2, 'is_active' => true,
        ]);

        $page = new EditCatalog;
        $page->record = $pack;

        $method = new ReflectionMethod(EditCatalog::class, 'mutateFormDataBeforeSave');
        $method->setAccessible(true);
        $result = $method->invoke($page, ['zone_id' => $otherZone->id]);

        // La zona se revierte a la original pese al intento de cambiarla.
        $this->assertSame($pack->zone_id, $result['zone_id']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.update_blocked']);
    }

    public function test_zone_change_is_allowed_for_unsold_product(): void
    {
        $this->actingAs($this->admin());
        $entry = $this->makeEntry();

        $otherZone = Zone::create([
            'slug' => 'kids', 'name' => ['es' => 'Kids'], 'accent' => 'kids',
            'color' => '#C6FF3A', 'position' => 2, 'is_active' => true,
        ]);

        $page = new EditCatalog;
        $page->record = $entry;

        $method = new ReflectionMethod(EditCatalog::class, 'mutateFormDataBeforeSave');
        $method->setAccessible(true);
        $result = $method->invoke($page, ['zone_id' => $otherZone->id]);

        $this->assertSame($otherZone->id, $result['zone_id']);
    }

    // ─── Editor de event_fields (saneo + claves únicas) ─────────────────────

    public function test_event_fields_are_sanitized(): void
    {
        $page = new EditCatalog;
        $method = new ReflectionMethod(EditCatalog::class, 'sanitizeEventFields');
        $method->setAccessible(true);

        $clean = $method->invoke($page, [
            ['key' => 'celebrant', 'type' => 'bogus', 'required' => '1', 'label' => ['es' => 'Nombre', 'en' => '']],
            ['key' => '', 'type' => 'text', 'label' => ['es' => 'Vacío']],   // sin clave → se descarta
        ]);

        $this->assertCount(1, $clean);
        $this->assertSame('celebrant', $clean[0]['key']);
        $this->assertSame('text', $clean[0]['type']);          // tipo inválido → normalizado a text
        $this->assertTrue($clean[0]['required']);
        $this->assertSame(['es' => 'Nombre'], $clean[0]['label']); // en vacío descartado
    }

    public function test_event_fields_duplicate_key_halts_save(): void
    {
        $page = new EditCatalog;
        $method = new ReflectionMethod(EditCatalog::class, 'sanitizeEventFields');
        $method->setAccessible(true);

        $this->expectException(Halt::class);
        $method->invoke($page, [
            ['key' => 'x', 'type' => 'text', 'label' => ['es' => 'X']],
            ['key' => 'x', 'type' => 'text', 'label' => ['es' => 'Y']],
        ]);
    }

    // ─── Borrado seguro ──────────────────────────────────────────────────────

    public function test_unsold_product_can_be_deleted_and_cleaned_up(): void
    {
        $admin = $this->admin();
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);
        $addon = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON,
            'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 20,
        ]);
        \DB::table('product_addons')->insert(['product_id' => $entry->id, 'addon_id' => $addon->id, 'position' => 0]);

        Livewire::actingAs($admin)
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->callAction('deleteProduct');

        $this->assertModelMissing($entry);
        $this->assertDatabaseMissing('prices', [
            'priceable_type' => $entry->getMorphClass(),
            'priceable_id' => $entry->id,
        ]);
        $this->assertDatabaseMissing('product_addons', ['product_id' => $entry->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.deleted']);
    }

    public function test_sold_product_delete_action_is_hidden(): void
    {
        $pack = $this->makePack();
        $this->sell($pack);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->assertActionHidden('deleteProduct');
    }

    public function test_can_delete_policy_respects_sales(): void
    {
        $this->actingAs($this->admin());

        $unsold = $this->makeEntry();
        $sold = $this->makePack();
        $this->sell($sold);

        $this->assertTrue(CatalogResource::canDelete($unsold));
        $this->assertFalse(CatalogResource::canDelete($sold));
    }

    // ─── Precios (7.8): matriz editable en la ficha ─────────────────────────

    public function test_admin_can_set_product_prices_per_rate(): void
    {
        $special = RateType::create([
            'key' => RateType::KEY_SPECIAL, 'label' => ['es' => 'Especial'],
            'is_special' => true, 'weekdays' => [0, 6], 'priority' => 10, 'is_active' => true,
        ]);
        $entry = $this->makeEntry();  // sin precios todavía

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm([
                'price_rate_'.$this->normal->id => '12.50',
                'price_rate_'.$special->id => '15',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1250, (int) $entry->prices()->where('rate_type_id', $this->normal->id)->value('amount_cents'));
        $this->assertSame(1500, (int) $entry->prices()->where('rate_type_id', $special->id)->value('amount_cents'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.prices_updated', 'target_id' => $entry->id]);
    }

    public function test_existing_price_is_prefilled_in_euros(): void
    {
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->assertFormSet(['price_rate_'.$this->normal->id => '9.90']);
    }

    public function test_clearing_a_price_deletes_the_row(): void
    {
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['price_rate_'.$this->normal->id => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('prices', [
            'priceable_type' => $entry->getMorphClass(),
            'priceable_id' => $entry->id,
            'rate_type_id' => $this->normal->id,
        ]);
    }

    public function test_negative_price_is_rejected(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['price_rate_'.$this->normal->id => '-5'])
            ->call('save')
            ->assertHasFormErrors(['price_rate_'.$this->normal->id]);
    }

    public function test_saving_without_price_changes_does_not_audit_prices(): void
    {
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);

        // Cambia OTRO campo (no el precio, que llega prefijado e igual) → no debe auditar precios.
        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'catalog.prices_updated', 'target_id' => $entry->id]);
        $this->assertSame(990, (int) $entry->prices()->where('rate_type_id', $this->normal->id)->value('amount_cents'));
    }

    public function test_price_is_stored_in_exact_cents(): void
    {
        // 19.99 € en float es 1998.9999…; `round` debe dar 1999 cts (sin pérdida).
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['price_rate_'.$this->normal->id => '19.99'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1999, (int) $entry->prices()->where('rate_type_id', $this->normal->id)->value('amount_cents'));
    }

    public function test_price_above_the_cap_is_rejected(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['price_rate_'.$this->normal->id => '100000'])  // > 99 999,99 € → desbordaría amount_cents
            ->call('save')
            ->assertHasFormErrors(['price_rate_'.$this->normal->id]);
    }

    public function test_changing_a_price_invalidates_the_landing_cta_cache(): void
    {
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);
        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['price_rate_'.$this->normal->id => '8.00'])
            ->call('save')
            ->assertHasNoFormErrors();

        // El CTA cacheado ya NO muestra el precio obsoleto (se invalidó; al recomputarse refleja
        // el nuevo, 800). Robusto tanto si queda vacío como si un view-composer lo recalcula.
        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }

    public function test_deactivating_a_product_invalidates_the_landing_cta_cache(): void
    {
        // Auditoría Fase 1 · Sistema 6 · W3: antes la invalidación vivía DENTRO de `if ($priceChanges)`
        // → marcar no vendible / desactivar la entrada más barata SIN tocar su precio dejaba el ancla
        // «desde X €» obsoleta hasta 15 min. Ahora `afterSave` invalida siempre.
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);
        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }

    public function test_deleting_a_product_invalidates_the_landing_cta_cache(): void
    {
        // Auditoría Fase 1 · Sistema 6 · W3: borrar la entrada más barata mueve el mínimo del CTA →
        // la caché debe invalidarse dentro de la transacción de borrado (antes `deleteProduct` no la tocaba).
        $entry = $this->makeEntry();
        $entry->prices()->create(['rate_type_id' => $this->normal->id, 'amount_cents' => 990, 'currency' => 'EUR']);
        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $entry->id])
            ->callAction('deleteProduct');

        $this->assertModelMissing($entry);
        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }
}
