<?php

namespace Tests\Feature\Sales;

use App\Livewire\Tickets\Purchase;
use App\Models\RateType;
use App\Models\Setting;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\CatalogSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El sidebar de compra organiza el catálogo POR TIPO en dos secciones-acordeón: «Entradas»
 * (type=entry) y «Servicios» (type=pack). Lista plana (los nombres ya distinguen la zona; la
 * zona es un constructo operativo —franjas + aforo—, no una categoría de catálogo). Se eliminó
 * el antiguo toggle Entradas/Packs (robaba atención); ya NO hay «modo» que conmutar: entradas y
 * servicios conviven en un solo render. El buscador es progresivo (solo si el catálogo es grande).
 */
class PurchaseCatalogGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function pack(Zone $zone, string $name): TicketType
    {
        $type = TicketType::create([
            'name' => ['es' => $name], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'min_qty' => 8, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $type->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1500]);

        return $type;
    }

    private function entry(Zone $zone, array $extra = []): TicketType
    {
        $entry = TicketType::create(array_merge([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ], $extra));
        $entry->prices()->create(['rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000]);

        return $entry;
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        app()->setLocale('es');
    }

    public function test_packs_appear_flat_in_the_services_section_without_zone_subheaders(): void
    {
        // Dos packs en DOS zonas operativas distintas: ambos salen en la sección «Servicios», en
        // lista plana. NO se sub-agrupan por zona (el antiguo subtítulo de zona `--zone-color`
        // desaparece): la zona es operativa, no una categoría de catálogo.
        $cumple = Zone::create(['slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'accent' => 'cumpleanos', 'color' => '#C026D3', 'is_active' => true, 'show_in_landing' => false, 'position' => 3]);
        $eventos = Zone::create(['slug' => 'eventos', 'name' => ['es' => 'Eventos'], 'accent' => 'eventos', 'color' => '#0EA5E9', 'is_active' => true, 'show_in_landing' => false, 'position' => 4]);
        $this->pack($cumple, 'Cumpleaños Jump');
        $this->pack($eventos, 'Evento Empresa');

        Livewire::test(Purchase::class)
            ->assertSee(__('tickets.section_services'))   // la sección por TIPO
            ->assertSee('Cumpleaños Jump')                // ambos packs, en la misma sección
            ->assertSee('Evento Empresa')
            ->assertDontSee('--zone-color', false);       // ya no hay subcabeceras de zona
    }

    public function test_product_in_an_inactive_zone_is_not_shown_nor_selectable(): void
    {
        // Una zona desactivada (no opera) saca a sus productos del catálogo Y de la selección,
        // de forma coherente (lo renderizado == lo seleccionable). #210.
        $inactive = Zone::create(['slug' => 'cerrada', 'name' => ['es' => 'ZonaCerrada'], 'is_active' => false, 'show_in_landing' => false, 'position' => 5]);
        $pack = $this->pack($inactive, 'Pack Cerrado');

        Livewire::test(Purchase::class)
            ->assertDontSee('Pack Cerrado')   // no se muestra en el catálogo
            ->call('selectType', $pack->id)
            ->assertSet('typeId', null)       // ni se puede seleccionar por id forjado
            ->assertSet('step', 1);
    }

    public function test_entries_appear_in_the_entries_section(): void
    {
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true, 'show_in_landing' => true, 'position' => 1]);
        $this->entry($jump);

        Livewire::test(Purchase::class)
            ->assertSee(__('tickets.section_entries'))   // sección «Entradas»
            ->assertSee('Jump 1h');                      // la entrada, en lista plana
    }

    public function test_catalog_shows_entries_and_services_together_in_one_view(): void
    {
        // La fusión: sin toggle, un solo render contiene AMBAS secciones y sus productos.
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true, 'show_in_landing' => true, 'position' => 1]);
        $cumple = Zone::create(['slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false, 'position' => 3]);
        $this->entry($jump);
        $this->pack($cumple, 'Cumpleaños Jump');

        Livewire::test(Purchase::class)
            ->assertSet('step', 1)
            ->assertSee(__('tickets.section_entries'))
            ->assertSee(__('tickets.section_services'))
            ->assertSee('Jump 1h')
            ->assertSee('Cumpleaños Jump');
    }

    public function test_each_catalog_item_carries_a_search_data_attribute(): void
    {
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true, 'show_in_landing' => true, 'position' => 1]);
        $this->entry($jump);

        Livewire::test(Purchase::class)
            ->assertSee('data-search', false); // alimenta el filtro client-side del buscador
    }

    public function test_search_field_is_hidden_for_a_small_catalog(): void
    {
        // Catálogo pequeño (≤ umbral) → el buscador NO aparece (no añade cromo inútil).
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true, 'show_in_landing' => true, 'position' => 1]);
        $this->entry($jump);

        Livewire::test(Purchase::class)
            ->assertDontSee(__('tickets.catalog_search'));
    }

    public function test_search_field_appears_for_a_large_catalog(): void
    {
        // Catálogo grande (> umbral, 12) → el buscador progresivo aparece solo.
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true, 'show_in_landing' => true, 'position' => 1]);
        for ($i = 1; $i <= 13; $i++) {
            $this->entry($jump, ['name' => ['es' => "Entrada $i"], 'position' => $i]);
        }

        Livewire::test(Purchase::class)
            ->assertSee(__('tickets.catalog_search'));
    }

    public function test_search_threshold_is_configurable_from_settings(): void
    {
        // #226 punto 7: el umbral del buscador es configurable desde el panel (helper CatalogSettings).
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true, 'show_in_landing' => true, 'position' => 1]);
        $this->entry($jump); // 1 solo producto

        // Umbral 0 → el buscador aparece con un único producto (siempre visible).
        Setting::updateOrCreate(['key' => CatalogSettings::SEARCH_MIN_ITEMS_KEY], ['value' => '0', 'group' => 'catalog']);
        Livewire::test(Purchase::class)->assertSee(__('tickets.catalog_search'));

        // Umbral alto → el buscador NO aparece aunque haya producto.
        Setting::updateOrCreate(['key' => CatalogSettings::SEARCH_MIN_ITEMS_KEY], ['value' => '50', 'group' => 'catalog']);
        Livewire::test(Purchase::class)->assertDontSee(__('tickets.catalog_search'));
    }

    public function test_entry_shows_ticket_icon_badge_and_featured_highlight(): void
    {
        // #222: icono de «entrada» en todos los productos + badge editable (panel) + resaltado
        // de las destacadas según el toggle `featured` del panel.
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true, 'show_in_landing' => true, 'position' => 1]);
        $this->entry($jump, ['featured' => true, 'badge' => ['es' => 'Top']]);

        Livewire::test(Purchase::class)
            ->assertSee('catalog__tk', false)         // icono de ticket
            ->assertSee('catalog__item--feat', false) // resaltado de la destacada
            ->assertSee('catalog__badge', false)
            ->assertSee('Top');                       // texto del badge (i18n)
    }

    public function test_plain_entry_has_no_badge_or_featured_highlight_but_keeps_the_icon(): void
    {
        $jump = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true, 'show_in_landing' => true, 'position' => 1]);
        $this->entry($jump, ['featured' => false, 'badge' => null]);

        Livewire::test(Purchase::class)
            ->assertSee('catalog__tk', false)            // el icono va en todos
            ->assertDontSee('catalog__item--feat', false)
            ->assertDontSee('catalog__badge', false);
    }
}
