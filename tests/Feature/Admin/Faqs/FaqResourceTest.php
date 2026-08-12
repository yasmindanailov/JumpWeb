<?php

namespace Tests\Feature\Admin\Faqs;

use App\Filament\Resources\Faqs\FaqResource;
use App\Filament\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Resources\Faqs\Pages\EditFaq;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Models\Faq;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.9 (iter. 1) — `FaqResource`: gating por `content.manage`, CRUD, limpieza i18n
 * (idiomas vacíos → null), defaults y borrado con audit.
 */
class FaqResourceTest extends TestCase
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
            'question' => ['es' => '¿Desde qué edad?'],
            'answer' => ['es' => 'Desde los 6 años.'],
            'position' => 1,
            'is_active' => true,
        ], $overrides);
    }

    public function test_gating(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(FaqResource::canViewAny());
        $this->get('/admin/faqs')->assertOk();

        $this->actingAs($this->staff())->get('/admin/faqs')->assertForbidden();
    }

    public function test_create_persists_compacts_i18n_and_audits(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateFaq::class)
            ->fillForm($this->validForm([
                'question' => ['es' => '¿Horario?', 'en' => 'Hours?', 'fr' => ''],
                'answer' => ['es' => 'De 10 a 21.', 'en' => '  ', 'fr' => ''],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $faq = Faq::firstOrFail();
        $this->assertSame(['es' => '¿Horario?', 'en' => 'Hours?'], $faq->question, 'idiomas vacíos descartados');
        $this->assertSame(['es' => 'De 10 a 21.'], $faq->answer, 'el blanco/espacios se descartan');
        $this->assertTrue($faq->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.faq_created', 'target_id' => $faq->id]);
    }

    public function test_create_defaults_active_when_not_touched(): void
    {
        $form = $this->validForm();
        unset($form['is_active']);

        Livewire::actingAs($this->admin())
            ->test(CreateFaq::class)
            ->fillForm($form)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Faq::firstOrFail()->is_active, 'una FAQ nueva nace activa por defecto');
    }

    public function test_create_requires_spanish_question(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateFaq::class)
            ->fillForm($this->validForm(['question' => ['es' => '']]))
            ->call('create')
            ->assertHasFormErrors(['question.es']);

        $this->assertSame(0, Faq::count());
    }

    public function test_edit_updates_and_audits(): void
    {
        $faq = Faq::create(['question' => ['es' => 'P'], 'answer' => ['es' => 'R'], 'position' => 1]);

        Livewire::actingAs($this->admin())
            ->test(EditFaq::class, ['record' => $faq->id])
            ->fillForm(['answer' => ['es' => 'Respuesta nueva']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['es' => 'Respuesta nueva'], $faq->refresh()->answer);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.faq_updated', 'target_id' => $faq->id]);
    }

    public function test_edit_preserves_untouched_locales(): void
    {
        // El form de edición rehidrata los 3 idiomas desde el registro; editar solo `es` NO debe
        // borrar `en`/`fr` (compactTranslations solo descarta los que llegan vacíos).
        $faq = Faq::create([
            'question' => ['es' => 'P', 'en' => 'Q'],
            'answer' => ['es' => 'R', 'en' => 'A'],
            'position' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditFaq::class, ['record' => $faq->id])
            ->fillForm(['answer' => ['es' => 'Respuesta nueva']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['es' => 'Respuesta nueva', 'en' => 'A'], $faq->refresh()->answer, 'el inglés no tocado sobrevive');
    }

    public function test_admin_can_reorder_faqs(): void
    {
        $first = Faq::create(['question' => ['es' => 'Primera'], 'answer' => ['es' => 'R1'], 'position' => 1]);
        $second = Faq::create(['question' => ['es' => 'Segunda'], 'answer' => ['es' => 'R2'], 'position' => 2]);

        Livewire::actingAs($this->admin())
            ->test(ListFaqs::class)
            ->call('reorderTable', [$second->id, $first->id]);

        $this->assertLessThan($first->fresh()->position, $second->fresh()->position);
    }

    public function test_delete_and_audits(): void
    {
        $faq = Faq::create(['question' => ['es' => 'P'], 'answer' => ['es' => 'R']]);

        Livewire::actingAs($this->admin())
            ->test(EditFaq::class, ['record' => $faq->id])
            ->callAction('deleteFaq');

        $this->assertNull($faq->fresh());
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.faq_deleted', 'target_id' => $faq->id]);
    }
}
