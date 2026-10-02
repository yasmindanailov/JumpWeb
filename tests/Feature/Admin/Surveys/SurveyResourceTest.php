<?php

namespace Tests\Feature\Admin\Surveys;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Filament\Pages\AdminSettingsHub;
use App\Filament\Resources\Surveys\Concerns\GuardsSurveyForm;
use App\Filament\Resources\Surveys\Pages\CreateSurvey;
use App\Filament\Resources\Surveys\Pages\EditSurvey;
use App\Filament\Resources\Surveys\Pages\ListSurveys;
use App\Filament\Resources\Surveys\SurveyResource;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **T1 de las encuestas — el panel** (`docs/specs/encuestas.md` §4.4; `#740`): gating por `settings.manage` y la
 * tarjeta en «Ajustes»; el alta con sus preguntas normalizadas y su rastro; la forma de la clave y de las preguntas;
 * una viva por clase; con respuestas, la clave, la clase y la estructura de las preguntas quedan bloqueadas (los
 * rótulos no) y la encuesta no se borra; sin respuestas, se borra con rastro; y la lista dice el estado real.
 */
class SurveyResourceTest extends TestCase
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
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $user;
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function validForm(array $overrides = []): array
    {
        return $overrides + [
            'key' => 'visita',
            'kind' => Survey::KIND_INTERNAL,
            'name' => ['es' => 'Tu visita de hoy', 'en' => 'Your visit today'],
            'intro' => ['es' => 'Dos preguntas rápidas'],
            'active' => true,
            'questions' => [
                ['key' => 'ambiente', 'type' => 'scale', 'required' => true, 'label' => ['es' => '¿Qué tal el ambiente?']],
                ['key' => 'mejorar', 'type' => 'choice', 'required' => false, 'label' => ['es' => '¿Qué mejorarías?'], 'options' => [
                    ['key' => 'bar', 'label' => ['es' => 'El bar']],
                    ['key' => 'colas', 'label' => ['es' => 'Las colas']],
                ]],
            ],
        ];
    }

    private function stored(array $overrides = []): Survey
    {
        return Survey::create($this->validForm($overrides));
    }

    /**
     * Una respuesta ANÓNIMA (`#754`): la que bloquea la estructura es la respuesta, sin persona.
     *
     * @param  array<string, mixed>  $answers
     */
    private function answered(Survey $survey, array $answers): void
    {
        SurveyResponse::create(['survey_id' => $survey->id, 'channel' => 'internal', 'answered_on' => now()->toDateString(), 'band' => 'morning', 'answers' => $answers]);
    }

    public function test_gating_and_the_card_in_settings(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(SurveyResource::canViewAny());
        $this->get('/admin/encuestas')->assertOk();

        $urls = collect((new AdminSettingsHub)->visibleAreas())->flatMap(static fn (array $area): array => array_column($area['items'], 'url'))->all();
        $this->assertContains(SurveyResource::getUrl('index'), $urls, 'la tarjeta de «Ajustes» lleva a las encuestas');

        $this->actingAs($this->staff());
        $this->assertFalse(SurveyResource::canViewAny());
        $this->get('/admin/encuestas')->assertForbidden();
    }

    public function test_create_normalizes_the_questions_keeps_their_order_and_audits(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSurvey::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $survey = Survey::firstOrFail();
        $this->assertSame('visita', $survey->key);
        $this->assertTrue($survey->active);
        $this->assertSame('Tu visita de hoy', $survey->displayName('es'));
        $this->assertSame('Your visit today', $survey->displayName('en'));
        $this->assertSame(['ambiente', 'mejorar'], array_column($survey->questionList(), 'key'));
        $this->assertSame(['bar', 'colas'], array_column($survey->questionList()[1]['options'], 'key'));
        $this->assertTrue($survey->isRunning());
        $this->assertDatabaseHas('audit_logs', ['action' => 'surveys.saved', 'target_id' => $survey->id]);
    }

    public function test_the_key_the_questions_and_the_options_have_a_form(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSurvey::class)
            ->fillForm($this->validForm(['key' => 'Mal Clave']))
            ->call('create')
            ->assertHasFormErrors(['key']);

        Livewire::actingAs($this->admin())
            ->test(CreateSurvey::class)
            ->fillForm($this->validForm(['questions' => [['key' => 'solo', 'type' => 'choice', 'label' => ['es' => 'x'], 'options' => [['key' => 'a', 'label' => ['es' => 'A']]]]]]))
            ->call('create')
            ->assertHasFormErrors();

        $this->stored();
        Livewire::actingAs($this->admin())
            ->test(CreateSurvey::class)
            ->fillForm($this->validForm(['name' => ['es' => 'Otra con la misma clave'], 'active' => false]))
            ->call('create')
            ->assertHasFormErrors(['key']);

        $this->assertSame(1, Survey::count());
    }

    /** `#740`: una interna y una externa vivas como máximo. La segunda de la misma clase se rechaza al encender. */
    public function test_only_one_live_survey_per_kind(): void
    {
        $this->stored();

        Livewire::actingAs($this->admin())
            ->test(CreateSurvey::class)
            ->fillForm($this->validForm(['key' => 'otra-interna']))
            ->call('create')
            ->assertHasFormErrors(['active']);

        Livewire::actingAs($this->admin())
            ->test(CreateSurvey::class)
            ->fillForm($this->validForm(['key' => 'externa', 'kind' => Survey::KIND_EXTERNAL]))
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::actingAs($this->admin())
            ->test(CreateSurvey::class)
            ->fillForm($this->validForm(['key' => 'interna-apagada', 'active' => false]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(3, Survey::count());
        $this->assertSame('visita', Survey::runningOfKind(Survey::KIND_INTERNAL)?->key);
        $this->assertSame('externa', Survey::runningOfKind(Survey::KIND_EXTERNAL)?->key);
    }

    /**
     * Con respuestas, la clave, la clase y la estructura de las preguntas no cambian aunque el cuerpo lo pida (un
     * campo deshabilitado no viaja, y el guardado lo vuelve a asegurar); los rótulos y el encendido, sí.
     */
    public function test_with_responses_the_structure_is_locked_but_the_labels_and_the_switch_are_not(): void
    {
        $survey = $this->stored();
        $this->answered($survey, ['ambiente' => 4]);

        Livewire::actingAs($this->admin())
            ->test(EditSurvey::class, ['record' => $survey->id])
            // `#754`: el aviso para el OPERADOR, delante de las preguntas. Texto del producto, tecleado a mano (`#734`).
            ->assertSee('Las encuestas son anónimas: no preguntes el nombre, el teléfono ni el correo.')
            ->fillForm([
                'key' => 'otra',
                'kind' => Survey::KIND_EXTERNAL,
                'name' => ['es' => 'Renombrada'],
                'questions' => [
                    ['key' => 'ambiente', 'type' => 'text', 'required' => false, 'label' => ['es' => 'El ambiente, del 1 al 5']],
                    ['key' => 'nueva', 'type' => 'yesno', 'required' => true, 'label' => ['es' => 'Nueva']],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $survey->refresh();
        $this->assertSame('visita', $survey->key, 'la clave no cambia con respuestas');
        $this->assertSame(Survey::KIND_INTERNAL, $survey->kind, 'la clase tampoco');
        $this->assertSame('Renombrada', $survey->displayName('es'), 'el nombre sí');
        $questions = $survey->questionList();
        $this->assertSame(['ambiente', 'mejorar'], array_column($questions, 'key'), 'ni se quitan ni se añaden preguntas');
        $this->assertSame('scale', $questions[0]['type'], 'el tipo no cambia');
        $this->assertSame('El ambiente, del 1 al 5', $questions[0]['label']['es'], 'el rótulo sí');
        $this->assertFalse($questions[0]['required'], 'y la obligatoriedad también');

        Livewire::actingAs($this->admin())
            ->test(EditSurvey::class, ['record' => $survey->id])
            ->fillForm(['active' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($survey->refresh()->active);
        $this->assertSame(2, AuditLog::where('action', 'surveys.saved')->where('target_id', $survey->id)->count());
    }

    /**
     * `#819`: A QUIÉN se elige en cada encuesta —a todos por defecto, o solo en su primera visita—, viaja en su rastro, se ve
     * en la lista y, con respuestas, queda fijo como la clase. Un valor que no es de los dos no entra.
     */
    public function test_the_audience_is_chosen_per_survey_and_fixed_once_answered(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSurvey::class)
            ->assertSchemaStateSet(['audience' => Survey::AUDIENCE_ALL])
            ->fillForm($this->validForm(['audience' => 'todo-el-mundo']))
            ->call('create')
            ->assertHasFormErrors(['audience']);
        $this->assertSame(0, Survey::query()->count());

        Livewire::actingAs($this->admin())
            ->test(CreateSurvey::class)
            ->fillForm($this->validForm(['audience' => Survey::AUDIENCE_FIRST_VISIT]))
            ->call('create')
            ->assertHasNoFormErrors();
        $survey = Survey::query()->sole();
        $this->assertSame(Survey::AUDIENCE_FIRST_VISIT, $survey->audience);
        $this->assertSame(Survey::AUDIENCE_FIRST_VISIT, AuditLog::where('action', 'surveys.saved')->where('target_id', $survey->id)->sole()->payload['audience'] ?? null);

        Livewire::actingAs($this->admin())->test(ListSurveys::class)->assertSee(__('admin.surveys.audience.first_visit'));

        $this->answered($survey, ['ambiente' => 4]);
        Livewire::actingAs($this->admin())
            ->test(EditSurvey::class, ['record' => $survey->id])
            ->fillForm(['audience' => Survey::AUDIENCE_ALL])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(Survey::AUDIENCE_FIRST_VISIT, $survey->refresh()->audience, 'con respuestas, a quién no cambia');
    }

    /**
     * La SEGUNDA defensa, la del guardado (`GuardsSurveyForm`; ocultar no es autorizar, `SEC-04`): el formulario ya rechaza
     * un valor ajeno y no manda un campo bloqueado, pero un cuerpo forjado que llegara hasta aquí tampoco pasa. Se llama
     * directamente porque por el formulario no se llega.
     */
    public function test_the_save_guard_fixes_the_audience_whatever_the_body_says(): void
    {
        $guard = new class
        {
            use GuardsSurveyForm;

            /** @param  array<string, mixed>  $data */
            public function run(array $data, ?Survey $record): array
            {
                return $this->guard($data, $record);
            }
        };
        $questions = $this->validForm()['questions'];

        $this->assertSame(Survey::AUDIENCE_ALL, $guard->run(['kind' => Survey::KIND_INTERNAL, 'audience' => 'todo-el-mundo', 'questions' => $questions], null)['audience']);
        $this->assertSame(Survey::AUDIENCE_FIRST_VISIT, $guard->run(['kind' => Survey::KIND_INTERNAL, 'audience' => Survey::AUDIENCE_FIRST_VISIT, 'questions' => $questions], null)['audience']);

        $locked = $this->stored(['audience' => Survey::AUDIENCE_FIRST_VISIT, 'active' => false]);
        $this->answered($locked, ['ambiente' => 4]);
        $this->assertSame(Survey::AUDIENCE_FIRST_VISIT, $guard->run(['audience' => Survey::AUDIENCE_ALL, 'questions' => $questions], $locked)['audience']);
    }

    public function test_a_survey_with_responses_is_not_deleted_and_one_without_is_deleted_with_a_trace(): void
    {
        $withResponses = $this->stored();
        $this->answered($withResponses, ['ambiente' => 4]);
        $this->actingAs($this->admin());
        $this->assertFalse(SurveyResource::canDelete($withResponses));
        Livewire::actingAs($this->admin())
            ->test(EditSurvey::class, ['record' => $withResponses->id])
            ->assertActionHidden('deleteSurvey');

        $empty = $this->stored(['key' => 'vacia', 'active' => false]);
        Livewire::actingAs($this->admin())
            ->test(EditSurvey::class, ['record' => $empty->id])
            ->callAction('deleteSurvey');

        $this->assertDatabaseMissing('surveys', ['id' => $empty->id]);
        $this->assertDatabaseHas('surveys', ['id' => $withResponses->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'surveys.deleted', 'target_id' => $empty->id]);
    }

    public function test_the_list_shows_the_real_state_and_the_responses_of_each_survey(): void
    {
        $running = $this->stored();
        $this->answered($running, ['ambiente' => 5]);
        $scheduled = $this->stored(['key' => 'futura', 'kind' => Survey::KIND_EXTERNAL, 'starts_at' => now()->addDay()]);
        $finished = $this->stored(['key' => 'pasada', 'kind' => Survey::KIND_EXTERNAL, 'ends_at' => now()->subDay()]);
        $off = $this->stored(['key' => 'apagada', 'active' => false]);

        Livewire::actingAs($this->admin())
            ->test(ListSurveys::class)
            ->assertCanSeeTableRecords([$running, $scheduled, $finished, $off])
            ->assertSee(__('admin.surveys.state.running'))
            ->assertSee(__('admin.surveys.state.scheduled'))
            ->assertSee(__('admin.surveys.state.finished'))
            ->assertSee(__('admin.surveys.state.inactive'))
            ->assertSee(__('admin.surveys.kind.internal'))
            ->assertSee(__('admin.surveys.kind.external'))
            ->assertSee('Tu visita de hoy');
    }
}
