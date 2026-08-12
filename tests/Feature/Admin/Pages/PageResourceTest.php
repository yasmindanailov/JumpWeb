<?php

namespace Tests\Feature\Admin\Pages;

use App\Domain\Content\Models\Page;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.9 (iter. 2) — `PageResource` (páginas legales): gating por `content.manage`, EDIT-ONLY
 * (sin crear/borrar), slug inmutable, cuerpo por secciones {h,p}×idioma con roundtrip correcto,
 * `is_active` que controla el 404 público, e interpolación de tokens fiscales (#206) en el render.
 */
class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        app()->setLocale('es');
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

    private function legalPage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'slug' => 'privacidad',
            'title' => ['es' => 'Política de privacidad'],
            'body' => ['es' => [['h' => 'Responsable', 'p' => 'Texto original.']]],
            'is_active' => true,
        ], $overrides));
    }

    public function test_gating(): void
    {
        $this->legalPage();

        $this->actingAs($this->admin());
        $this->assertTrue(PageResource::canViewAny());
        $this->get('/admin/pages')->assertOk();

        $this->actingAs($this->staff())->get('/admin/pages')->assertForbidden();
    }

    public function test_resource_is_edit_only(): void
    {
        $this->assertFalse(PageResource::canCreate(), 'no se crean páginas legales a mano');
        $this->assertFalse(PageResource::canDelete($this->legalPage()), 'no se borran (romperían su ruta)');
        $this->assertArrayNotHasKey('create', PageResource::getPages());
    }

    public function test_edit_updates_title_and_body_in_order_and_audits(): void
    {
        $page = $this->legalPage();

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->id])
            ->fillForm([
                'title' => ['es' => 'Privacidad (rev.)'],
                'body_es' => [
                    ['h' => 'Primero', 'p' => 'Párrafo uno.'],
                    ['h' => '', 'p' => 'Párrafo sin encabezado.'],
                    ['h' => '', 'p' => ''], // sección vacía → se descarta
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame(['es' => 'Privacidad (rev.)'], $page->title);
        $this->assertSame([
            ['h' => 'Primero', 'p' => 'Párrafo uno.'],
            ['h' => '', 'p' => 'Párrafo sin encabezado.'],
        ], $page->body['es'], 'orden preservado y sección vacía descartada');
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.page_updated', 'target_id' => $page->id]);
    }

    public function test_edit_preserves_untouched_locale_body(): void
    {
        $page = $this->legalPage([
            'body' => [
                'es' => [['h' => 'ES', 'p' => 'Texto es.']],
                'en' => [['h' => 'EN', 'p' => 'Text en.']],
            ],
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->id])
            ->fillForm(['body_es' => [['h' => 'ES nuevo', 'p' => 'Texto es nuevo.']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame([['h' => 'ES nuevo', 'p' => 'Texto es nuevo.']], $page->body['es']);
        $this->assertSame([['h' => 'EN', 'p' => 'Text en.']], $page->body['en'], 'el cuerpo en inglés no tocado sobrevive');
    }

    public function test_slug_is_immutable(): void
    {
        $page = $this->legalPage(['slug' => 'privacidad']);

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->id])
            ->fillForm(['title' => ['es' => 'Otro título']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('privacidad', $page->refresh()->slug);
    }

    public function test_legal_pages_cannot_be_deactivated_via_the_panel(): void
    {
        // Auditoría Fase 1 · Sistema 6 · W5: TODAS las páginas legales están enlazadas de forma
        // OBLIGATORIA desde el pie, el `sitemap.xml` y el consentimiento del registro → ninguna puede
        // quedar inactiva (un 404 indexable + pie roto). El form fuerza `is_active=true` al guardar
        // (antes solo protegía `cookies`; ahora las 5 vía `Page::PROTECTED_ACTIVE_SLUGS`). `cookies`
        // tiene su propio test (#219); aquí cubrimos las otras 4 legales. (El 404 del controlador para
        // una página inactiva por escritura DIRECTA en BD sigue cubierto por
        // `PagesRoutesTest::test_inactive_page_returns_404`.)
        foreach (['privacidad', 'condiciones', 'waiver', 'aviso-legal'] as $slug) {
            $page = $this->legalPage(['slug' => $slug, 'title' => ['es' => 'Legal '.$slug]]);

            Livewire::actingAs($this->admin())
                ->test(EditPage::class, ['record' => $page->id])
                ->fillForm(['is_active' => false])
                ->call('save')
                ->assertHasNoFormErrors();

            $this->assertTrue($page->refresh()->is_active, "la página legal '{$slug}' se mantiene activa");
            $this->get('/'.$slug)->assertOk(); // sigue alcanzable (no 404 en sitemap/footer)
        }
    }

    public function test_cookies_page_cannot_be_deactivated(): void
    {
        // #219: la política de cookies está enlazada de forma obligatoria desde el banner de
        // consentimiento → no puede quedar inactiva (dejaría ese enlace legal en 404).
        $page = $this->legalPage(['slug' => 'cookies', 'title' => ['es' => 'Política de cookies']]);

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->id])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($page->refresh()->is_active, 'la página de cookies se mantiene activa');
        $this->get('/cookies')->assertOk();
    }

    public function test_edit_omits_empty_untouched_locales(): void
    {
        // Un idioma nunca rellenado no se persiste (su repeater llega vacío → se omite del JSON).
        $page = $this->legalPage(['body' => ['es' => [['h' => 'ES', 'p' => 'Solo español.']]]]);

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->id])
            ->fillForm(['body_es' => [['h' => 'ES', 'p' => 'Solo español, editado.']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $body = $page->refresh()->body;
        $this->assertArrayHasKey('es', $body);
        $this->assertArrayNotHasKey('en', $body, 'un idioma vacío no se persiste');
        $this->assertArrayNotHasKey('fr', $body);
    }

    public function test_view_on_web_action_links_to_public_page(): void
    {
        $page = $this->legalPage(['slug' => 'privacidad']);

        // La acción «Ver en la web» de la ficha apunta a la ruta pública legal.{slug}.
        $this->actingAs($this->admin())
            ->get('/admin/pages/'.$page->id.'/edit')
            ->assertOk()
            ->assertSee(route('legal.privacidad'), false);
    }

    public function test_is_active_field_shows_warning_helper(): void
    {
        $page = $this->legalPage();

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->id])
            ->assertSee(__('admin.pages.is_active_hint'));
    }

    public function test_listing_shows_all_legal_pages_in_slug_order(): void
    {
        // Las 5 páginas legales fijas, listadas y ordenadas por slug (alfabético).
        $avisoLegal = $this->legalPage(['slug' => 'aviso-legal', 'title' => ['es' => 'Aviso legal']]);
        $condiciones = $this->legalPage(['slug' => 'condiciones', 'title' => ['es' => 'Condiciones'], 'is_active' => false]);
        $cookies = $this->legalPage(['slug' => 'cookies', 'title' => ['es' => 'Cookies']]);
        $privacidad = $this->legalPage(['slug' => 'privacidad', 'title' => ['es' => 'Privacidad']]);
        $waiver = $this->legalPage(['slug' => 'waiver', 'title' => ['es' => 'Waiver'], 'is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(ListPages::class)
            ->assertCanSeeTableRecords([$avisoLegal, $condiciones, $cookies, $privacidad, $waiver], inOrder: true);
    }

    public function test_edit_reflects_on_public_page_with_token_interpolation(): void
    {
        Setting::create(['key' => 'business.legal_name', 'value' => 'Saltarina SL', 'group' => 'business']);
        $page = $this->legalPage();

        // El operador edita el cuerpo usando el token fiscal.
        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->id])
            ->fillForm([
                'body_es' => [['h' => 'Responsable', 'p' => 'El responsable es :legal_name.']],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // En la web pública el token se sustituye por el dato real (#206).
        $this->get('/privacidad')
            ->assertOk()
            ->assertSee('El responsable es Saltarina SL.')
            ->assertDontSee(':legal_name');
    }
}
