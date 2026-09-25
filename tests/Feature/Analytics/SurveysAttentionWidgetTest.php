<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Filament\Pages\AnalyticsPage;
use App\Filament\Widgets\Analytics\SurveysAttentionWidget;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **«Por atender»** (`specs/encuestas.md` §4.4, T4; `DECISIONES #742`): la persona Y su texto libre salen SOLO con
 * `customers.insights`. Quien solo tiene el permiso del cuadro ve el día, el canal, la encuesta y la nota: lo que
 * hay que atender, sin saber quién habló ni qué dijo con sus palabras.
 */
class SurveysAttentionWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-25 12:00:00', 'Europe/Madrid'));
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->value('id')]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function viewDataAs(User $viewer): array
    {
        $this->actingAs($viewer);
        $widget = new SurveysAttentionWidget;
        $widget->pageFilters = ['period' => ReportPeriod::Last30->value];

        return (new \ReflectionMethod($widget, 'getViewData'))->invoke($widget);
    }

    public function test_the_person_and_the_free_text_show_only_with_the_customer_insights_permission(): void
    {
        $survey = Survey::create(['key' => 'visita', 'name' => ['es' => 'Tu visita'], 'kind' => Survey::KIND_INTERNAL, 'active' => true, 'questions' => [
            ['key' => 'ambiente', 'type' => 'scale', 'label' => ['es' => 'Ambiente']],
            ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Algo más']],
        ]]);
        $customer = User::factory()->create(['name' => 'Bea Quejosa']);
        SurveyResponse::create(['survey_id' => $survey->id, 'user_id' => $customer->id, 'channel' => 'internal', 'visited_on' => '2026-09-24', 'answered_at' => '2026-09-24 11:00:00', 'answers' => ['ambiente' => 1, 'comentario' => 'Me llamo Bea y estuvo sucio'], 'locale' => 'es']);

        // El admin tiene la ficha del cliente: ve a quién llamar y lo que dijo.
        $admin = $this->viewDataAs($this->withRole('admin'));
        $this->assertCount(1, $admin['rows']);
        $this->assertSame('Bea Quejosa', $admin['rows'][0]['person']);
        $this->assertStringContainsString('/admin/users/'.$customer->id, (string) $admin['rows'][0]['url']);
        $this->assertSame('Me llamo Bea y estuvo sucio', $admin['rows'][0]['text']);
        $this->assertSame('1 / 5', $admin['rows'][0]['score']);

        // Un miembro del equipo con SOLO el permiso del cuadro: la fila, sin persona ni texto.
        $staff = $this->withRole('staff');
        $staff->roles->first()->permissions()->attach(Permission::where('name', AnalyticsPage::PERMISSION)->value('id'));
        $staff = $staff->fresh();
        $this->assertFalse($staff->hasPermission(SurveysAttentionWidget::PERMISSION_PERSON), 'el fixture no vale si el staff ya tiene la ficha');

        $limited = $this->viewDataAs($staff);
        $this->assertCount(1, $limited['rows']);
        $this->assertNull($limited['rows'][0]['person']);
        $this->assertNull($limited['rows'][0]['url']);
        $this->assertSame('', $limited['rows'][0]['text']);
        $this->assertSame('1 / 5', $limited['rows'][0]['score']);
        $this->assertStringNotContainsString('Bea', json_encode($limited, JSON_UNESCAPED_UNICODE), 'ni el nombre ni el texto llegan a la vista sin el permiso');
        $this->assertSame(__('admin.analytics.surveys.attention_note_no_person'), $limited['description']);
    }
}
