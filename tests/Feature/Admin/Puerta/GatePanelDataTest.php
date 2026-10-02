<?php

namespace Tests\Feature\Admin\Puerta;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\WristbandColor;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\WristbandWheel;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\AdminSettingsHub;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Catalog\Pages\CreateCatalog;
use App\Filament\Resources\WristbandColors\Pages\CreateWristbandColor;
use App\Filament\Resources\WristbandColors\Pages\EditWristbandColor;
use App\Filament\Resources\WristbandColors\Pages\ListWristbandColors;
use App\Filament\Resources\WristbandColors\Tables\WristbandColorTable;
use App\Filament\Resources\WristbandColors\WristbandColorResource;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Lo de la P2 que pone el PARQUE en su panel** (`docs/specs/puerta-nueva.md` §4.4, la P2): los colores de las pulseras
 * («Ajustes → Pulseras»), la rueda (dos ajustes de la Puerta) y la sección «En la puerta» de cada producto —el color fijo,
 * la zona de salto de un pack y lo que se entrega—. Con colores neutros: el producto no sabe de qué color es ningún parque.
 */
class GatePanelDataTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private RateType $normal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        app()->setLocale('es');
        $this->zone = Zone::create(['slug' => 'kids', 'name' => ['es' => 'Kids'], 'position' => 1, 'is_active' => true]);
        $this->normal = RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'is_special' => false, 'priority' => 0, 'is_active' => true]);
    }

    private function as(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function colour(string $one, string $hex, bool $inWheel = true, int $position = 0): WristbandColor
    {
        return WristbandColor::create(['name_one' => $one, 'name_other' => $one.'s', 'hex' => $hex, 'in_wheel' => $inWheel, 'position' => $position]);
    }

    // ─── La pantalla de los colores ───────────────────────────────────────────

    public function test_the_colours_screen_is_for_whoever_manages_the_catalogue_and_lives_in_settings(): void
    {
        $this->actingAs($this->as('admin'));
        $this->assertTrue(WristbandColorResource::canViewAny());
        $this->get(WristbandColorResource::getUrl('index'))->assertOk();
        $urls = collect((new AdminSettingsHub)->visibleAreas())->flatMap(static fn (array $area): array => array_column($area['items'], 'url'))->all();
        $this->assertContains(WristbandColorResource::getUrl('index'), $urls, 'la tarjeta de «Ajustes» lleva a las pulseras');

        $this->actingAs($this->as('staff'));
        $this->assertFalse(WristbandColorResource::canViewAny());
        $this->get(WristbandColorResource::getUrl('index'))->assertForbidden();
    }

    public function test_a_new_colour_goes_last_with_its_hex_in_lower_case_and_a_trace(): void
    {
        $this->colour('primero', '#111111', position: 4);

        Livewire::actingAs($this->as('admin'))
            ->test(CreateWristbandColor::class)
            ->assertSchemaStateSet(['in_wheel' => true])
            ->fillForm(['name_one' => 'pulsera lila', 'name_other' => 'pulseras lilas', 'hex' => '#A883F0'])
            ->call('create')
            ->assertHasNoFormErrors();

        $colour = WristbandColor::query()->where('name_one', 'pulsera lila')->sole();
        $this->assertSame('#a883f0', $colour->hex);
        $this->assertSame(5, $colour->position, 'al final de la lista: el orden lo cambia quien arrastra');
        $this->assertTrue($colour->in_wheel);
        $this->assertSame(['name_one' => 'pulsera lila', 'name_other' => 'pulseras lilas', 'hex' => '#a883f0', 'in_wheel' => true, 'created' => true], AuditLog::query()->where('action', 'catalog.wristband_saved')->sole()->payload);
    }

    public function test_a_colour_needs_both_phrases_and_a_real_hex(): void
    {
        Livewire::actingAs($this->as('admin'))
            ->test(CreateWristbandColor::class)
            ->fillForm(['name_one' => '', 'name_other' => '', 'hex' => 'red;background:url(x)'])
            ->call('create')
            ->assertHasFormErrors(['name_one', 'name_other', 'hex']);
        $this->assertSame(0, WristbandColor::query()->count());
    }

    /** Un color que lleva FIJO un producto no se borra (la FK lo pasaría a la rueda sin que nadie lo pida); uno libre, sí. */
    public function test_a_colour_in_use_is_not_deleted_and_a_free_one_is_with_a_trace(): void
    {
        $used = $this->colour('fija', '#777777', inWheel: false);
        TicketType::create(['name' => ['es' => 'Kids · Ilimitada'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id, 'wristband_color_id' => $used->id, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1]);
        $free = $this->colour('libre', '#222222');
        $admin = $this->as('admin');

        $this->actingAs($admin);
        $this->assertFalse(WristbandColorResource::canDelete($used));
        Livewire::actingAs($admin)->test(EditWristbandColor::class, ['record' => $used->id])->assertActionHidden('deleteWristband');

        Livewire::actingAs($admin)->test(EditWristbandColor::class, ['record' => $free->id])->callAction('deleteWristband');
        $this->assertDatabaseMissing('wristband_colors', ['id' => $free->id]);
        $this->assertDatabaseHas('wristband_colors', ['id' => $used->id]);
        $this->assertSame(1, AuditLog::query()->where('action', 'catalog.wristband_deleted')->count());
    }

    /** Arriba de la lista, la rueda tal como la verá la Puerta: así se comprueba sin abrirla. */
    public function test_the_list_tells_the_wheel_as_the_gate_will_read_it(): void
    {
        $this->colour('pulsera segunda', '#222222', position: 2);
        $this->colour('pulsera fija', '#777777', inWheel: false, position: 0);
        $this->colour('pulsera primera', '#111111', position: 1);

        $this->assertSame('Sin rueda: las pulseras solo llevan el color fijo de su producto. Para repartir las horas, marca colores «En la rueda» y pon la hora del primero en Ajustes → Avanzado → Puerta.', WristbandColorTable::wheelSummary());

        Setting::updateOrCreate(['key' => WristbandWheel::KEY_START], ['value' => '23:30', 'group' => 'puerta']);
        $this->assertSame('La rueda, cada 30 min: 23:30 pulsera primera · 00:00 pulsera segunda… y vuelta a empezar.', WristbandColorTable::wheelSummary(), 'solo las de la rueda, en su orden, y la medianoche da la vuelta');

        Livewire::actingAs($this->as('admin'))->test(ListWristbandColors::class)->assertSee('23:30 pulsera primera');
    }

    // ─── La rueda en los ajustes de la Puerta ─────────────────────────────────

    public function test_the_settings_save_the_wheel_and_refuse_an_hour_that_is_not_one(): void
    {
        $admin = $this->as('admin');
        // Lo que la página de ajustes exige para guardar (el molde de `WaiverSettingsTest`).
        foreach ([
            ['business.name', 'SaltoPark', 'business'], ['contact.email', 'hola@saltopark.example', 'contact'],
            ['sales.hold_minutes', '15', 'payment'], ['sales.purchase_horizon_months', '6', 'payment'],
            ['puerta.validate_rate_limit_per_minute', '100', 'puerta'], ['redsys_environment', 'test', 'payment'],
            ['redsys_currency', '978', 'payment'],
        ] as [$key, $value, $group]) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->fillForm([WristbandWheel::KEY_START => '25:00'])
            ->call('save')
            ->assertHasFormErrors([WristbandWheel::KEY_START]);

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->fillForm([WristbandWheel::KEY_START => '11:00', WristbandWheel::KEY_STEP => 45])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('11:00', Setting::value(WristbandWheel::KEY_START));
        $this->assertSame(45, WristbandWheel::step());
    }

    // ─── «En la puerta», en cada producto ─────────────────────────────────────

    public function test_each_product_type_shows_only_its_part_of_the_gate_section(): void
    {
        $page = Livewire::actingAs($this->as('admin'))->test(CreateCatalog::class);

        $page->fillForm(['type' => TicketType::TYPE_ENTRY])
            ->assertFormFieldIsVisible('wristband_color_id')
            ->assertFormFieldIsHidden('gate_zone_id')
            ->assertFormFieldIsHidden('handed_at_gate');
        $page->fillForm(['type' => TicketType::TYPE_PACK])
            ->assertFormFieldIsVisible('wristband_color_id')
            ->assertFormFieldIsVisible('gate_zone_id')
            ->assertFormFieldIsHidden('handed_at_gate');
        $page->fillForm(['type' => TicketType::TYPE_ADDON])
            ->assertFormFieldIsHidden('wristband_color_id')
            ->assertFormFieldIsVisible('handed_at_gate')
            ->assertFormFieldIsHidden('gate_label_one');
        $page->fillForm(['handed_at_gate' => true])
            ->assertFormFieldIsVisible('gate_label_one')
            ->assertFormFieldIsVisible('gate_label_other');
    }

    public function test_the_gate_section_is_saved_with_the_product(): void
    {
        $red = $this->colour('pulsera de fiesta', '#aa0000', inWheel: false);
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 2, 'is_active' => true]);
        $admin = $this->as('admin');

        Livewire::actingAs($admin)->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_PACK, 'name' => ['es' => 'Cumpleaños Jump'], 'zone_id' => $this->zone->id,
                'seats_per_unit' => 1, 'duration_min' => 120, 'min_qty' => 8, 'max_qty' => 20,
                'wristband_color_id' => $red->id, 'gate_zone_id' => $jump->id,
                'price_rate_'.$this->normal->id => '199',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $pack = TicketType::query()->where('name->es', 'Cumpleaños Jump')->sole();
        $this->assertSame([$red->id, $jump->id], [(int) $pack->wristband_color_id, (int) $pack->gate_zone_id]);

        Livewire::actingAs($admin)->test(CreateCatalog::class)
            ->fillForm([
                'type' => TicketType::TYPE_ADDON, 'name' => ['es' => 'Calcetines antideslizantes'],
                'handed_at_gate' => true, 'gate_label_one' => 'par de calcetines', 'gate_label_other' => 'pares de calcetines',
                'price_rate_'.$this->normal->id => '2',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        // Por su nombre: las migraciones ya crean complementos (los del cumpleaños mixto).
        $socks = TicketType::query()->where('name->es', 'Calcetines antideslizantes')->sole();
        $this->assertTrue($socks->handed_at_gate);
        $this->assertSame(['par de calcetines', 'pares de calcetines'], [$socks->gate_label_one, $socks->gate_label_other]);
    }
}
