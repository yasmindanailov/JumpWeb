<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Resources\Catalog\Pages\CreateCatalog;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use App\Models\RateType;
use App\Models\TicketType;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Post-form de datos por invitado (#217, iter. 1) — editor del esquema `guest_fields` en el
 * catálogo: saneo + claves únicas (gemelo de `event_fields`), exclusivo de packs, auditado,
 * y PRESERVADO al editar otros campos del pack.
 */
class CatalogGuestFieldsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);

        RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Día normal'],
            'is_special' => false, 'priority' => 0, 'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
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
            'deposit_type' => TicketType::DEPOSIT_NONE,
            'deposit_value' => 0,
            'guest_fields' => TicketType::DEFAULT_GUEST_FIELDS,
            'seats_per_unit' => 1,
            'tax_rate' => 21,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 9,
        ], $overrides));
    }

    // ─── Saneo + claves únicas (gemelo de event_fields) ──────────────────────

    public function test_guest_fields_are_sanitized(): void
    {
        $page = new EditCatalog;
        $method = new ReflectionMethod(EditCatalog::class, 'sanitizeGuestFields');
        $method->setAccessible(true);

        $clean = $method->invoke($page, [
            ['key' => 'name', 'type' => 'bogus', 'required' => '1', 'label' => ['es' => 'Nombre', 'en' => '']],
            ['key' => '', 'type' => 'text', 'label' => ['es' => 'Vacío']],   // sin clave → se descarta
        ]);

        $this->assertCount(1, $clean);
        $this->assertSame('name', $clean[0]['key']);
        $this->assertSame('text', $clean[0]['type']);          // tipo inválido → text
        $this->assertTrue($clean[0]['required']);
        $this->assertSame(['es' => 'Nombre'], $clean[0]['label']); // en vacío descartado
    }

    public function test_guest_fields_duplicate_key_halts_save(): void
    {
        $page = new EditCatalog;
        $method = new ReflectionMethod(EditCatalog::class, 'sanitizeGuestFields');
        $method->setAccessible(true);

        $this->expectException(Halt::class);
        $method->invoke($page, [
            ['key' => 'name', 'type' => 'text', 'label' => ['es' => 'Nombre']],
            ['key' => 'name', 'type' => 'text', 'label' => ['es' => 'Repetida']],
        ]);
    }

    // ─── Exclusivo de packs ──────────────────────────────────────────────────

    public function test_non_pack_create_strips_guest_fields(): void
    {
        $this->actingAs($this->admin());

        $page = new CreateCatalog;
        $method = new ReflectionMethod(CreateCatalog::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $result = $method->invoke($page, [
            'type' => TicketType::TYPE_ENTRY,
            'name' => ['es' => 'Entrada'],
            'seats_per_unit' => 1,
            // Aunque llegue (tampering / dehidratación de una sección oculta), una entrada no
            // debe persistir datos por-niño.
            'guest_fields' => [['key' => 'name', 'label' => ['es' => 'Nombre']]],
        ]);

        $this->assertArrayNotHasKey('guest_fields', $result);
    }

    public function test_non_pack_edit_strips_guest_fields(): void
    {
        // Defensa en profundidad: ni siquiera con un payload manipulado (tampering / dehidratación)
        // una ENTRADA debe persistir datos por-niño al EDITAR (paridad con el alta).
        $this->actingAs($this->admin());
        $entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);

        $page = new EditCatalog;
        $page->record = $entry;

        $method = new ReflectionMethod(EditCatalog::class, 'mutateFormDataBeforeSave');
        $method->setAccessible(true);
        $result = $method->invoke($page, [
            'guest_fields' => [['key' => 'name', 'label' => ['es' => 'Nombre']]],
            'event_fields' => [['key' => 'celebrant', 'label' => ['es' => 'Homenajeado']]],
        ]);

        $this->assertArrayNotHasKey('guest_fields', $result);
        $this->assertArrayNotHasKey('event_fields', $result);
    }

    // ─── i18n (panel = es + zh_CN, #123) ─────────────────────────────────────

    public function test_guest_field_i18n_keys_have_es_zh_parity(): void
    {
        foreach (['field_guest_fields', 'guest_fields_hint', 'guest_field_add', 'guest_field_duplicate'] as $key) {
            foreach (['es', 'zh_CN'] as $locale) {
                $this->assertTrue(
                    Lang::has("admin.catalog.{$key}", $locale),
                    "Falta la clave admin.catalog.{$key} en el idioma {$locale}",
                );
            }
        }
    }

    // ─── Edición: saneo en el guardado + auditoría + preservación ────────────

    public function test_guest_fields_are_sanitized_and_audited_on_save(): void
    {
        $this->actingAs($this->admin());
        $pack = $this->makePack(['guest_fields' => [
            ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
        ]]);

        $page = new EditCatalog;
        $page->record = $pack;

        $method = new ReflectionMethod(EditCatalog::class, 'mutateFormDataBeforeSave');
        $method->setAccessible(true);
        $result = $method->invoke($page, [
            'guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
                ['key' => 'allergy', 'type' => 'bogus', 'required' => false, 'label' => ['es' => 'Alergia']],
            ],
        ]);

        // Saneado: 2 columnas, tipo inválido normalizado.
        $this->assertCount(2, $result['guest_fields']);
        $this->assertSame('text', $result['guest_fields'][1]['type']);

        // El cambio queda en el diff de auditoría (por nombre, sin volcar contenido).
        $payloadProp = new ReflectionProperty(EditCatalog::class, 'auditPayload');
        $payloadProp->setAccessible(true);
        $payload = $payloadProp->getValue($page);
        $this->assertContains('guest_fields', $payload['texts_changed'] ?? []);
    }

    public function test_editing_other_fields_preserves_guest_fields(): void
    {
        // Gotcha #211: editar NO debe perder lo no tocado (Filament rehidrata el repeater).
        $pack = $this->makePack();

        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $pack->fresh();
        $this->assertFalse($fresh->is_active);
        // Las 4 columnas por-niño siguen ahí tras un guardado que no las tocó.
        $this->assertSame(['name', 'allergy', 'notes', 'special_menu'], array_column($fresh->guestFields(), 'key'));
    }

    public function test_guest_fields_change_is_audited_end_to_end(): void
    {
        $pack = $this->makePack(['guest_fields' => null]);

        // Edita el pack rellenando guest_fields desde el repeater del form.
        Livewire::actingAs($this->admin())
            ->test(EditCatalog::class, ['record' => $pack->id])
            ->fillForm(['guest_fields' => [
                ['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['name'], array_column($pack->fresh()->guestFields(), 'key'));
        $log = AuditLog::where('action', 'catalog.updated')->where('target_id', $pack->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertContains('guest_fields', $log->payload['texts_changed'] ?? []);
    }
}
