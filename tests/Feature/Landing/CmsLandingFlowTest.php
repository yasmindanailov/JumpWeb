<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Models\Faq;
use App\Domain\Content\Models\VenueRule;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Attractions\Pages\CreateAttraction;
use App\Filament\Resources\Attractions\Pages\EditAttraction;
use App\Filament\Resources\Attractions\Pages\ListAttractions;
use App\Filament\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Resources\Faqs\Pages\EditFaq;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Filament\Resources\ParkRules\Pages\CreateParkRule;
use App\Filament\Resources\ParkRules\Pages\EditParkRule;
use App\Filament\Resources\ParkRules\Pages\ListParkRules;
use Database\Seeders\LandingContentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.9 (iter. 1) — Verificación END-TO-END del flujo PANEL → LANDING del contenido CMS
 * (atracciones, FAQ, normas): lo creado/editado/reordenado/desactivado/borrado DESDE el panel
 * se refleja correctamente en la web pública (home `/` y `/normas`), en el ORDEN correcto, con
 * coherencia para añadir y quitar, y respetando el idioma activo.
 *
 * Cierra el hueco entre los tests de recurso (panel persiste) y el render real de la landing.
 */
class CmsLandingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(LandingContentSeeder::class); // contenido completo para que la home renderice
        app()->setLocale('es');
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    /** Zona visible en la landing, con su propio slider de atracciones (aislada para asertar orden). */
    private function landingZone(): Zone
    {
        return Zone::create([
            'slug' => 'zona-test-cms', 'name' => ['es' => 'ZonaTestCMS'], 'accent' => 'zona-test-cms',
            'is_active' => true, 'show_in_landing' => true, 'position' => 50,
        ]);
    }

    // ─────────────────────────── Atracciones ───────────────────────────

    /*
     * ⚠️⚠️ **ESTOS CASOS MIRAN A `/atracciones`, NO A LA PORTADA, y el cambio es de `#482`.** La
     * sección 03 pasó a un mosaico de CINCO fotos, así que la portada dejó de ser el sitio donde se
     * ve el catálogo entero: una atracción nueva puede no salir ahí y estar publicada igualmente.
     * ▶ `/atracciones` sí las enseña todas, así que es la superficie donde el flujo panel → web es
     * observable. *Re-apuntar no es debilitar: aquí la aserción vale para las 23 y antes valía para
     * las que cupieran en el carril.*
     */
    public function test_attraction_created_in_panel_appears_on_landing(): void
    {
        $zone = $this->landingZone();

        Livewire::actingAs($this->admin())
            ->test(CreateAttraction::class)
            ->fillForm([
                'zone_id' => $zone->id,
                'name' => ['es' => 'AtraccionNuevaZZ'],
                'description' => ['es' => 'Una atracción de prueba.'],
                'position' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Auth::logout();
        $this->get('/atracciones')->assertOk()->assertSee('AtraccionNuevaZZ');
    }

    public function test_attraction_reorder_in_panel_reflects_on_landing(): void
    {
        $zone = $this->landingZone();
        $first = Attraction::create(['zone_id' => $zone->id, 'name' => ['es' => 'AtraccionUnoZZ'], 'position' => 1]);
        $second = Attraction::create(['zone_id' => $zone->id, 'name' => ['es' => 'AtraccionDosZZ'], 'position' => 2]);

        // Orden inicial: Uno antes que Dos.
        $this->get('/atracciones')->assertSeeInOrder(['AtraccionUnoZZ', 'AtraccionDosZZ']);

        // Reordenar en el panel (arrastrar Dos delante de Uno).
        Livewire::actingAs($this->admin())
            ->test(ListAttractions::class)
            ->call('reorderTable', [$second->id, $first->id]);

        $this->assertLessThan($first->fresh()->position, $second->fresh()->position, 'el reorden persistió en BD');

        Auth::logout();
        $this->get('/atracciones')->assertSeeInOrder(['AtraccionDosZZ', 'AtraccionUnoZZ']);
    }

    public function test_attraction_badge_and_image_render_on_landing(): void
    {
        $zone = $this->landingZone();
        Attraction::create([
            'zone_id' => $zone->id,
            'name' => ['es' => 'AtraccionConExtrasZZ'],
            'badge' => ['es' => 'BadgeZZ'],
            'image' => 'images/test-zz.jpg',
            'position' => 1,
        ]);

        $html = $this->get('/atracciones')->assertOk();
        $html->assertSee('AtraccionConExtrasZZ');
        $html->assertSee('BadgeZZ');          // sub-badge
        $html->assertSee('images/test-zz.jpg'); // src de la imagen (vía asset())
    }

    public function test_deactivated_attraction_disappears_from_landing(): void
    {
        $zone = $this->landingZone();
        $attraction = Attraction::create(['zone_id' => $zone->id, 'name' => ['es' => 'AtraccionVisibleZZ'], 'position' => 1]);

        $this->get('/atracciones')->assertSee('AtraccionVisibleZZ');

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        Auth::logout();
        $this->get('/atracciones')->assertDontSee('AtraccionVisibleZZ');
    }

    public function test_attraction_in_hidden_zone_does_not_appear_on_landing(): void
    {
        // Coherencia documentada: una atracción activa en una zona NO mostrada en la landing
        // (show_in_landing=false) no aparece — porque la landing itera solo zonas visibles.
        $hidden = Zone::create([
            'slug' => 'zona-oculta-test', 'name' => ['es' => 'ZonaOcultaTest'],
            'is_active' => true, 'show_in_landing' => false, 'position' => 51,
        ]);
        Attraction::create(['zone_id' => $hidden->id, 'name' => ['es' => 'AtraccionOcultaZZ'], 'position' => 1, 'is_active' => true]);

        $this->get('/atracciones')->assertOk()->assertDontSee('AtraccionOcultaZZ');
    }

    public function test_deleted_attraction_disappears_from_landing(): void
    {
        $zone = $this->landingZone();
        $attraction = Attraction::create(['zone_id' => $zone->id, 'name' => ['es' => 'AtraccionBorrableZZ'], 'position' => 1]);

        $this->get('/atracciones')->assertSee('AtraccionBorrableZZ');

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->id])
            ->callAction('deleteAttraction');

        Auth::logout();
        $this->get('/atracciones')->assertDontSee('AtraccionBorrableZZ');
    }

    public function test_attraction_renders_in_active_locale(): void
    {
        $zone = $this->landingZone();
        Attraction::create([
            'zone_id' => $zone->id,
            'name' => ['es' => 'AtraccionEspanolZZ', 'en' => 'AttractionEnglishZZ'],
            'position' => 1,
        ]);

        $this->get('/atracciones')->assertSee('AtraccionEspanolZZ')->assertDontSee('AttractionEnglishZZ');
        $this->withSession(['locale' => 'en'])->get('/atracciones')->assertSee('AttractionEnglishZZ');
    }

    // ─────────────────────────────── FAQ ───────────────────────────────

    public function test_faq_created_in_panel_appears_on_landing(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateFaq::class)
            ->fillForm([
                'question' => ['es' => 'PreguntaNuevaZZ'],
                'answer' => ['es' => 'Respuesta de prueba.'],
                'position' => 99,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Auth::logout();
        $this->get('/')->assertOk()->assertSee('PreguntaNuevaZZ');
    }

    public function test_faq_reorder_in_panel_reflects_on_landing(): void
    {
        // Posiciones altas para quedar detrás de las FAQ sembradas y poder asertar su orden relativo.
        $first = Faq::create(['question' => ['es' => 'PreguntaUnoZZ'], 'answer' => ['es' => 'R1'], 'position' => 90]);
        $second = Faq::create(['question' => ['es' => 'PreguntaDosZZ'], 'answer' => ['es' => 'R2'], 'position' => 91]);

        $this->get('/')->assertSeeInOrder(['PreguntaUnoZZ', 'PreguntaDosZZ']);

        Livewire::actingAs($this->admin())
            ->test(ListFaqs::class)
            ->call('reorderTable', [$second->id, $first->id]);

        $this->assertLessThan($first->fresh()->position, $second->fresh()->position, 'el reorden persistió en BD');

        Auth::logout();
        $this->get('/')->assertSeeInOrder(['PreguntaDosZZ', 'PreguntaUnoZZ']);
    }

    public function test_deactivated_faq_disappears_from_landing(): void
    {
        $faq = Faq::create(['question' => ['es' => 'PreguntaVisibleZZ'], 'answer' => ['es' => 'R'], 'position' => 92]);

        $this->get('/')->assertSee('PreguntaVisibleZZ');

        Livewire::actingAs($this->admin())
            ->test(EditFaq::class, ['record' => $faq->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        Auth::logout();
        $this->get('/')->assertDontSee('PreguntaVisibleZZ');
    }

    // ────────────────────────────── Normas ─────────────────────────────

    /**
     * **Una norma creada en el panel sale en la portada Y en `/normas`.**
     *
     * ⚠️⚠️ **Este caso ha cambiado de contrato DOS veces y conviene saberlo antes de tocarlo.** La
     * T1 del idioma visual (`#292`) recortó la portada a las TRES primeras normas, y este caso pasó
     * a exigir `assertDontSee` con `position: 99`. El 2026-08-31 el owner pidió **revertir la T1
     * entera** (`#300`, con la consecuencia delante), así que la portada vuelve a listarlas TODAS y
     * el caso vuelve a su forma original.
     * ▶ **El tope de tres ya no existe**, y con él se va el caso que lo fijaba: una guarda de un
     * contrato retirado no protege nada — pasa en verde diga lo que diga el producto.
     */
    public function test_rule_created_in_panel_appears_on_home_and_normas(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateParkRule::class)
            ->fillForm([
                'name' => ['es' => 'NormaNuevaZZ'],
                'description' => ['es' => 'Descripción de la norma.'],
                // ⚠️ **Posición 0 y no 99, y el cambio NO afloja el caso** (`#309`): desde que la
                // portada asoma solo las CUATRO primeras normas, una creada al final del orden no
                // sale allí **por diseño**, no por un defecto. El sujeto —que una norma creada en
                // el panel llegue a las páginas públicas— se comprueba con una que SÍ entre en el
                // asomo; el tope tiene su propio caso, abajo.
                'position' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Auth::logout();
        $this->get('/normas')->assertOk()->assertSee('NormaNuevaZZ');
        $this->get('/')->assertOk()->assertSee('NormaNuevaZZ');
    }

    /**
     * **La portada ASOMA cuatro normas; `/normas` las tiene todas** (`#309`).
     *
     * El tope lo declara la VISTA, no el panel: el parque decide QUÉ normas y en qué orden, y
     * cuántas caben en la portada es diseño. ⚠️ Y no es cosmético — la columna de requisitos es
     * `sticky` y solo se nota si la de normas es más alta.
     */
    public function test_the_home_only_peeks_four_rules_and_normas_has_them_all(): void
    {
        VenueRule::query()->delete();
        foreach (range(1, 6) as $i) {
            VenueRule::create(['name' => ['es' => "NormaPeekZZ{$i}"], 'position' => $i]);
        }

        $home = $this->get('/')->assertOk();
        foreach ([1, 2, 3, 4] as $i) {
            $home->assertSee("NormaPeekZZ{$i}");
        }
        $home->assertDontSee('NormaPeekZZ5')->assertDontSee('NormaPeekZZ6');

        $todas = $this->get('/normas')->assertOk();
        foreach (range(1, 6) as $i) {
            $todas->assertSee("NormaPeekZZ{$i}");
        }
    }

    public function test_rule_reorder_in_panel_reflects_on_normas(): void
    {
        $first = VenueRule::create(['name' => ['es' => 'NormaUnoZZ'], 'position' => 90]);
        $second = VenueRule::create(['name' => ['es' => 'NormaDosZZ'], 'position' => 91]);

        $this->get('/normas')->assertSeeInOrder(['NormaUnoZZ', 'NormaDosZZ']);

        Livewire::actingAs($this->admin())
            ->test(ListParkRules::class)
            ->call('reorderTable', [$second->id, $first->id]);

        $this->assertLessThan($first->fresh()->position, $second->fresh()->position, 'el reorden persistió en BD');

        Auth::logout();
        $this->get('/normas')->assertSeeInOrder(['NormaDosZZ', 'NormaUnoZZ']);
    }

    public function test_deactivating_all_rules_leaves_pages_coherent(): void
    {
        // Coherencia al QUITAR: si se desactivan todas las normas, /normas y la home siguen
        // cargando (200) y simplemente no muestran ninguna norma sembrada (sin romper el layout).
        VenueRule::query()->update(['is_active' => false]);

        $this->get('/')->assertOk()->assertDontSee('Conducta');
        $this->get('/normas')->assertOk()->assertDontSee('Conducta');
    }

    public function test_deactivated_rule_disappears_from_home_and_normas(): void
    {
        // ⚠️ Posición 0: la portada solo asoma las cuatro primeras (`#309`), así que una norma al
        // final del orden no llegaría a estar visible ni antes de desactivarla y el caso probaría
        // «no se ve» contra «no se ve».
        $rule = VenueRule::create(['name' => ['es' => 'NormaVisibleZZ'], 'position' => 0]);

        $this->get('/')->assertSee('NormaVisibleZZ');
        $this->get('/normas')->assertSee('NormaVisibleZZ');

        Livewire::actingAs($this->admin())
            ->test(EditParkRule::class, ['record' => $rule->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        Auth::logout();
        $this->get('/')->assertDontSee('NormaVisibleZZ');
        $this->get('/normas')->assertDontSee('NormaVisibleZZ');
    }
}
