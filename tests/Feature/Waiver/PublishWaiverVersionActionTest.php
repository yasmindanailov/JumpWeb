<?php

namespace Tests\Feature\Waiver;

use App\Domain\Content\Models\Page;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Filament\Resources\Pages\Pages\EditPage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 6 · waiver — «publicar es un acto» (`docs/specs/waiver-probatorio.md` §4.2): la acción de la
 * ficha de la página congela el texto GUARDADO, con los tokens fiscales resueltos, como versión
 * firmable. Y la segunda guarda del subsistema (§6·2) en su forma de tanda 1: editar la `Page`
 * después NO cambia la versión publicada.
 */
class PublishWaiverVersionActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        app()->setLocale('es');
        Setting::updateOrCreate(['key' => 'business.legal_name'], ['value' => 'Saltos y Botes SL', 'group' => 'business']);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function waiverPage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'slug' => 'waiver',
            'title' => ['es' => 'Exención de responsabilidad', 'en' => 'Waiver'],
            'body' => [
                'es' => [['h' => 'Titular', 'p' => 'Aceptas ante :legal_name que saltar implica riesgos.']],
                'en' => [['h' => 'Owner', 'p' => 'You accept before :legal_name that jumping involves risks.']],
            ],
            'is_active' => true,
        ], $overrides));
    }

    /**
     * ⚠️ **El nombre de este caso decía «only on the waiver page» y desde `#348` eso ya no es cierto**:
     * publicar sirve también a las CONDICIONES, que se aceptan al contratar y de las que hay que poder
     * decir qué versión regía cada compra. La lista es cerrada (`LegalDocuments::PUBLISHABLE`).
     *
     * ⚠️⚠️ **La privacidad sigue FUERA y es el control que da valor al caso**: el RGPD no pide que se
     * «acepte» una política —el art. 13 pide INFORMAR—, así que no hay ninguna aceptación que fechar
     * contra una versión. Sin esta mitad, el caso solo diría que el botón existe.
     */
    public function test_the_action_is_visible_on_the_documents_that_are_published_by_version(): void
    {
        $waiver = $this->waiverPage();
        $terms = Page::create(['slug' => 'condiciones', 'title' => ['es' => 'Condiciones'], 'body' => ['es' => [['h' => 'x', 'p' => 'y']]], 'is_active' => true]);
        $privacy = Page::create(['slug' => 'privacidad', 'title' => ['es' => 'Privacidad'], 'body' => ['es' => [['h' => 'x', 'p' => 'y']]], 'is_active' => true]);

        foreach ([$waiver, $terms] as $publishable) {
            Livewire::actingAs($this->admin())
                ->test(EditPage::class, ['record' => $publishable->id])
                ->assertActionVisible('publishVersion');
        }

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $privacy->id])
            ->assertActionHidden('publishVersion');
    }

    public function test_publishing_freezes_the_saved_text_with_the_tokens_resolved(): void
    {
        $page = $this->waiverPage();

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->id])
            ->callAction('publishVersion')
            ->assertNotified();

        $rows = LegalDocumentVersion::where('slug', 'waiver')->get();
        $this->assertCount(2, $rows);
        $es = $rows->firstWhere('locale', 'es');
        $this->assertSame(1, $es->version);
        $this->assertSame('Exención de responsabilidad', $es->title);
        $this->assertSame('Aceptas ante Saltos y Botes SL que saltar implica riesgos.', $es->sections()[0]['p']);
        $this->assertStringNotContainsString(':legal_name', json_encode($es->body));
        $this->assertTrue($es->verifyHash());
        $this->assertDatabaseHas('audit_logs', ['action' => 'legal.version_published']);
    }

    public function test_publishing_twice_creates_version_two(): void
    {
        $page = $this->waiverPage();
        $component = Livewire::actingAs($this->admin())->test(EditPage::class, ['record' => $page->id]);

        $component->callAction('publishVersion');
        $component->callAction('publishVersion');

        $this->assertSame([1, 2], LegalDocumentVersion::where('locale', 'es')->orderBy('version')->pluck('version')->all());
    }

    /** §6·2 (tanda 1) — el snapshot NO sigue al CMS: editar la página después no lo toca. */
    public function test_editing_the_page_afterwards_does_not_change_the_published_version(): void
    {
        $page = $this->waiverPage();
        $component = Livewire::actingAs($this->admin())->test(EditPage::class, ['record' => $page->id]);
        $component->callAction('publishVersion');
        $published = LegalDocumentVersion::where('locale', 'es')->first();

        $component
            ->fillForm(['body_es' => [['h' => 'Titular', 'p' => 'Texto CAMBIADO después de publicar.']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Texto CAMBIADO después de publicar.', $page->fresh()->body['es'][0]['p']);
        $this->assertSame('Aceptas ante Saltos y Botes SL que saltar implica riesgos.', $published->fresh()->sections()[0]['p']);
        $this->assertTrue($published->fresh()->verifyHash());
    }

    /** §8.1 — el bloqueante como mecanismo: el borrador del seeder no se publica. */
    public function test_a_draft_text_is_refused_and_nothing_is_published(): void
    {
        $page = $this->waiverPage(['body' => [
            'es' => [['h' => 'Aceptación', 'p' => 'Este texto es un borrador. [PENDIENTE: redacción definitiva].']],
        ]]);

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->id])
            ->callAction('publishVersion')
            ->assertNotified(__('admin.legal.publish.refused_draft'));

        $this->assertSame(0, LegalDocumentVersion::count());
    }

    public function test_an_empty_body_is_refused(): void
    {
        $page = $this->waiverPage(['body' => ['es' => []]]);

        Livewire::actingAs($this->admin())
            ->test(EditPage::class, ['record' => $page->id])
            ->callAction('publishVersion')
            ->assertNotified(__('admin.legal.publish.nothing'));

        $this->assertSame(0, LegalDocumentVersion::count());
    }
}
