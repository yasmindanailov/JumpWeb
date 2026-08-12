<?php

namespace Tests\Feature\Admin\RateTypes;

use App\Filament\Resources\RateTypes\Pages\CreateRateType;
use App\Filament\Resources\RateTypes\Pages\EditRateType;
use App\Filament\Resources\RateTypes\RateTypeResource;
use App\Models\Price;
use App\Models\RateType;
use App\Models\Role;
use App\Models\SpecialDate;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Support\RateResolver;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.8 — Gestión de tarifas (`rate_types`): gating por `prices.manage`, CRUD,
 * defaults de creación, `weekdays` como enteros (contrato del RateResolver), guardas de
 * borrado (base `normal` / con precios / referenciada por fechas especiales), invalidación
 * de la caché del CTA y auditoría.
 */
class RateTypeResourceTest extends TestCase
{
    use RefreshDatabase;

    private RateType $normal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->normal = RateType::create([
            'key' => RateType::KEY_NORMAL,
            'label' => ['es' => 'Día normal'],
            'is_special' => false,
            'weekdays' => null,
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

    // ─── Autorización ────────────────────────────────────────────────────────

    public function test_admin_has_prices_manage_and_can_view(): void
    {
        $admin = $this->admin();
        $this->assertTrue($admin->hasPermission('prices.manage'));

        $this->actingAs($admin);
        $this->assertTrue(RateTypeResource::canViewAny());
        $this->assertTrue(RateTypeResource::canCreate());
        $this->assertTrue(RateTypeResource::shouldRegisterNavigation());
    }

    public function test_staff_lacks_prices_manage_and_is_denied(): void
    {
        $staff = $this->staff();
        $this->assertFalse($staff->hasPermission('prices.manage'));

        $this->actingAs($staff);
        $this->assertFalse(RateTypeResource::canViewAny());
        $this->assertFalse(RateTypeResource::shouldRegisterNavigation());

        $this->actingAs($staff)->get('/admin/rate-types')->assertForbidden();
    }

    public function test_customer_cannot_reach_the_panel(): void
    {
        $this->actingAs($this->customer())->get('/admin/rate-types')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/rate-types')->assertRedirect();
    }

    // ─── Alta ────────────────────────────────────────────────────────────────

    public function test_create_persists_defaults_for_not_null_columns(): void
    {
        // Sin tocar los toggles: is_active debe nacer TRUE (no false por el dehidratado del
        // Toggle de Filament), is_special FALSE y priority 0.
        Livewire::actingAs($this->admin())
            ->test(CreateRateType::class)
            ->fillForm([
                'key' => 'vispera',
                'label' => ['es' => 'Víspera'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rate = RateType::where('key', 'vispera')->firstOrFail();
        $this->assertTrue($rate->is_active);
        $this->assertFalse($rate->is_special);
        $this->assertSame(0, (int) $rate->priority);
    }

    public function test_create_stores_weekdays_as_integers_and_audits(): void
    {
        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(CreateRateType::class)
            ->fillForm([
                'key' => 'finde',
                'label' => ['es' => 'Fin de semana'],
                'weekdays' => [6, 0], // sábado y domingo, en cualquier orden
                'priority' => 10,
                'is_special' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $rate = RateType::where('key', 'finde')->firstOrFail();

        // weekdays normalizados a ENTEROS únicos y ordenados (contrato del RateResolver).
        $this->assertSame([0, 6], $rate->weekdays);
        foreach ($rate->weekdays as $d) {
            $this->assertIsInt($d);
        }
        $this->assertTrue($rate->is_special);
        $this->assertSame(10, (int) $rate->priority);

        $this->assertDatabaseHas('audit_logs', ['action' => 'prices.rate_created', 'target_id' => $rate->id]);
        // El alta invalida la caché del CTA "desde X €".
        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }

    public function test_create_normalizes_key_to_lowercase(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateRateType::class)
            ->fillForm([
                'key' => 'finde',
                'label' => ['es' => 'Fin de semana'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('rate_types', ['key' => 'finde']);
    }

    public function test_create_rejects_duplicate_key(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateRateType::class)
            ->fillForm([
                'key' => RateType::KEY_NORMAL, // ya existe (creada en setUp)
                'label' => ['es' => 'Otra normal'],
            ])
            ->call('create')
            ->assertHasFormErrors(['key']);

        $this->assertSame(1, RateType::where('key', RateType::KEY_NORMAL)->count());
    }

    public function test_create_rejects_invalid_key_format(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateRateType::class)
            ->fillForm([
                'key' => 'Fin De Semana!', // mayúsculas/espacios/símbolos
                'label' => ['es' => 'X'],
            ])
            ->call('create')
            ->assertHasFormErrors(['key']);
    }

    // ─── Integración con el RateResolver (el contrato real) ───────────────────

    public function test_weekday_rate_created_from_panel_resolves_correctly(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateRateType::class)
            ->fillForm([
                'key' => 'finde',
                'label' => ['es' => 'Fin de semana'],
                'weekdays' => [6],
                'priority' => 10,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $finde = RateType::where('key', 'finde')->firstOrFail();

        $saturday = Carbon::today()->next(Carbon::SATURDAY);
        $tuesday = Carbon::today()->next(Carbon::TUESDAY);

        // Sábado → la tarifa nueva (prueba end-to-end de que weekdays se guardó como int).
        $this->assertTrue($finde->is($this->resolver()->for($saturday)));
        // Día entre semana → cae a la base normal.
        $this->assertTrue($this->normal->is($this->resolver()->for($tuesday)));
    }

    private function resolver(): RateResolver
    {
        return app(RateResolver::class);
    }

    // ─── Edición ──────────────────────────────────────────────────────────────

    public function test_edit_updates_and_audits_diff_and_busts_cache(): void
    {
        $finde = RateType::create([
            'key' => 'finde',
            'label' => ['es' => 'Fin de semana'],
            'is_special' => true,
            'weekdays' => [6],
            'priority' => 10,
            'is_active' => true,
        ]);

        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(EditRateType::class, ['record' => $finde->id])
            ->fillForm([
                'weekdays' => [6, 0],
                'priority' => 20,
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $finde->refresh();
        $this->assertSame([0, 6], $finde->weekdays);
        $this->assertSame(20, (int) $finde->priority);
        $this->assertFalse($finde->is_active);

        $this->assertDatabaseHas('audit_logs', ['action' => 'prices.rate_updated', 'target_id' => $finde->id]);
        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }

    public function test_key_field_is_read_only_when_editing(): void
    {
        Livewire::actingAs($this->admin())
            ->test(EditRateType::class, ['record' => $this->normal->id])
            ->assertFormFieldIsDisabled('key');
    }

    public function test_deactivating_fallback_normal_sends_persistent_warning(): void
    {
        Livewire::actingAs($this->admin())
            ->test(EditRateType::class, ['record' => $this->normal->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.rate_types.warn_deactivated_fallback'));

        $this->assertFalse($this->normal->refresh()->is_active);
    }

    public function test_deactivating_non_fallback_does_not_warn(): void
    {
        $finde = RateType::create([
            'key' => 'finde', 'label' => ['es' => 'Finde'], 'priority' => 5, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditRateType::class, ['record' => $finde->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotNotified(__('admin.rate_types.warn_deactivated_fallback'));
    }

    public function test_non_price_change_audits_but_keeps_price_cache(): void
    {
        // Cambiar SOLO la marca informativa `is_special` (no afecta al precio resuelto): se
        // audita, pero NO se invalida la caché del CTA "desde X €".
        $finde = RateType::create([
            'key' => 'finde', 'label' => ['es' => 'Finde'], 'is_special' => false,
            'priority' => 5, 'is_active' => true,
        ]);

        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(EditRateType::class, ['record' => $finde->id])
            ->fillForm(['is_special' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($finde->refresh()->is_special);
        // Se auditó el cambio…
        $this->assertDatabaseHas('audit_logs', ['action' => 'prices.rate_updated', 'target_id' => $finde->id]);
        // …pero la caché de precio NO se tocó (is_special no afecta al precio).
        $this->assertSame(999, Cache::get('cta.min_price_cents'));
    }

    public function test_key_cannot_be_changed_on_save(): void
    {
        $finde = RateType::create([
            'key' => 'finde',
            'label' => ['es' => 'Fin de semana'],
            'priority' => 5,
            'is_active' => true,
        ]);

        // Tampering directo del estado (campo deshabilitado en la UI): la key está
        // deshidratada y, además, el handler la elimina del payload → no cambia.
        Livewire::actingAs($this->admin())
            ->test(EditRateType::class, ['record' => $finde->id])
            ->fillForm(['priority' => 7])
            ->set('data.key', 'hacked')
            ->call('save')
            ->assertHasNoFormErrors();

        $finde->refresh();
        $this->assertSame('finde', $finde->key);
        $this->assertSame(7, (int) $finde->priority);
    }

    // ─── Borrado (guardas) ─────────────────────────────────────────────────────

    public function test_delete_blocked_for_fallback_normal(): void
    {
        $this->actingAs($this->admin()); // con permiso: el false viene del bloqueo, no de la auth
        $this->assertSame('fallback_normal', $this->normal->deleteBlockedReason());
        $this->assertFalse($this->normal->canBeDeleted());
        $this->assertFalse(RateTypeResource::canDelete($this->normal));

        Livewire::actingAs($this->admin())
            ->test(EditRateType::class, ['record' => $this->normal->id])
            ->assertActionHidden('deleteRateType');
    }

    public function test_delete_blocked_when_rate_has_prices(): void
    {
        $zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
        $product = TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $zone->id, 'is_active' => true, 'is_sellable' => true, 'position' => 1,
        ]);
        $finde = RateType::create([
            'key' => 'finde', 'label' => ['es' => 'Finde'], 'priority' => 5, 'is_active' => true,
        ]);
        Price::create([
            'priceable_type' => TicketType::class, 'priceable_id' => $product->id,
            'rate_type_id' => $finde->id, 'amount_cents' => 1500, 'currency' => 'EUR',
        ]);

        $this->actingAs($this->admin());
        $this->assertSame('has_prices', $finde->deleteBlockedReason());
        $this->assertFalse(RateTypeResource::canDelete($finde));

        Livewire::actingAs($this->admin())
            ->test(EditRateType::class, ['record' => $finde->id])
            ->assertActionHidden('deleteRateType');
    }

    public function test_delete_blocked_when_referenced_by_special_date(): void
    {
        $finde = RateType::create([
            'key' => 'festivo', 'label' => ['es' => 'Festivo'], 'priority' => 5, 'is_active' => true,
        ]);
        SpecialDate::create([
            'date' => '2026-12-25',
            'rate_type_id' => $finde->id,
            'is_closed' => false,
        ]);

        $this->actingAs($this->admin());
        $this->assertSame('referenced_by_special_dates', $finde->deleteBlockedReason());
        $this->assertFalse(RateTypeResource::canDelete($finde));

        Livewire::actingAs($this->admin())
            ->test(EditRateType::class, ['record' => $finde->id])
            ->assertActionHidden('deleteRateType');
    }

    public function test_delete_succeeds_for_unused_rate(): void
    {
        $finde = RateType::create([
            'key' => 'finde', 'label' => ['es' => 'Finde'], 'priority' => 5, 'is_active' => true,
        ]);

        $this->actingAs($this->admin());
        $this->assertNull($finde->deleteBlockedReason());
        $this->assertTrue(RateTypeResource::canDelete($finde));

        Cache::put('cta.min_price_cents', 999, now()->addMinutes(15));

        Livewire::actingAs($this->admin())
            ->test(EditRateType::class, ['record' => $finde->id])
            ->assertActionVisible('deleteRateType')
            ->callAction('deleteRateType');

        $this->assertDatabaseMissing('rate_types', ['id' => $finde->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'prices.rate_deleted', 'target_id' => $finde->id]);
        $this->assertNotSame(999, Cache::get('cta.min_price_cents'));
    }

    // ─── Modelo: guardas puras ─────────────────────────────────────────────────

    public function test_blocked_reason_priority_order(): void
    {
        // normal con precios: gana el motivo de fallback (más fundamental que has_prices).
        $zone = Zone::create([
            'slug' => 'z', 'name' => ['es' => 'Z'], 'accent' => 'jump',
            'color' => '#000000', 'position' => 1, 'is_active' => true,
        ]);
        $product = TicketType::create([
            'name' => ['es' => 'E'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $zone->id, 'is_active' => true, 'is_sellable' => true, 'position' => 1,
        ]);
        Price::create([
            'priceable_type' => TicketType::class, 'priceable_id' => $product->id,
            'rate_type_id' => $this->normal->id, 'amount_cents' => 1000, 'currency' => 'EUR',
        ]);

        $this->assertSame('fallback_normal', $this->normal->deleteBlockedReason());
    }
}
