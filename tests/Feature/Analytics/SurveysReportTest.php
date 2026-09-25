<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\SurveysReport;
use App\Filament\Widgets\Analytics\SurveysBreakdownWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **El informe de las encuestas** (`docs/specs/encuestas.md` §4.4, T4; `DECISIONES #740`): por DÍA DE LA RESPUESTA,
 * las dos tasas, por encuesta y por pregunta el reparto, «Por atender» con las peores notas de los últimos 30 días,
 * la serie, el periodo anterior, el presupuesto de consultas y los textos libres SOLO en la pestaña.
 */
class SurveysReportTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Europe/Madrid';

    private Survey $internal;

    private Survey $external;

    private Survey $old;

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-30 12:00:00', self::TZ));
    }

    private function june(): Window
    {
        return Window::ofDays(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'), self::TZ);
    }

    // ─── El fixture ───────────────────────────────────────────────────────────

    /** ⚠️ No se llama `seed()`: `TestCase` ya tiene uno público y PHP no deja restringirlo (fatal). */
    private function seedJune(): void
    {
        $this->internal = Survey::create([
            'key' => 'visita', 'name' => ['es' => 'Tu visita'], 'kind' => Survey::KIND_INTERNAL, 'active' => true,
            'questions' => [
                ['key' => 'ambiente', 'type' => 'scale', 'required' => true, 'label' => ['es' => 'Ambiente']],
                ['key' => 'como', 'type' => 'choice', 'label' => ['es' => 'Cómo nos conociste'], 'options' => [['key' => 'google', 'label' => ['es' => 'Google']], ['key' => 'amigos', 'label' => ['es' => 'Amigos']]]],
                ['key' => 'zonas', 'type' => 'multi', 'label' => ['es' => 'Zonas'], 'options' => [['key' => 'jump', 'label' => ['es' => 'Jump']], ['key' => 'kids', 'label' => ['es' => 'Kids']]]],
                ['key' => 'volveria', 'type' => 'yesno', 'label' => ['es' => 'Volverías']],
                ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Algo más']],
            ],
        ]);
        $this->external = Survey::create([
            'key' => 'que-tal', 'name' => ['es' => 'Qué tal ayer'], 'kind' => Survey::KIND_EXTERNAL, 'active' => true,
            'questions' => [
                ['key' => 'nota', 'type' => 'scale', 'label' => ['es' => 'Nota']],
                ['key' => 'texto', 'type' => 'text', 'label' => ['es' => 'Cuéntanos']],
            ],
        ]);
        $this->old = Survey::create([
            'key' => 'vieja', 'name' => ['es' => 'La vieja'], 'kind' => Survey::KIND_INTERNAL, 'active' => false,
            'questions' => [['key' => 'x', 'type' => 'scale', 'label' => ['es' => 'X']]],
        ]);

        foreach (['ana', 'bea', 'cai', 'dan', 'eva', 'fon', 'gil'] as $name) {
            $this->users[$name] = User::factory()->create(['name' => ucfirst($name).' Test', 'email' => $name.'@example.com']);
        }
        foreach (['ana' => '2026-06-05', 'bea' => '2026-06-05', 'cai' => '2026-06-12', 'dan' => '2026-06-20', 'eva' => '2026-06-28', 'fon' => '2026-05-30'] as $name => $day) {
            app(GateVisits::class)->register($this->users[$name], null, Carbon::parse($day));
        }
        // Ana vuelve el 25: ya contestó el 5, así que esa visita NO es una oferta (una por cliente y encuesta).
        app(GateVisits::class)->register($this->users['ana'], null, Carbon::parse('2026-06-25'));

        // En la puerta, en junio: dos contestan el 5 (una nota baja con texto), una declina el 12, otra contesta el 20.
        $this->answered($this->internal, 'ana', '2026-06-05 10:00:00', ['ambiente' => 5, 'como' => 'google', 'zonas' => ['jump'], 'volveria' => true, 'comentario' => 'Genial']);
        $this->answered($this->internal, 'bea', '2026-06-05 11:00:00', ['ambiente' => 2, 'como' => 'amigos', 'zonas' => ['jump', 'kids'], 'volveria' => false, 'comentario' => 'Mucha cola']);
        SurveyResponse::create(['survey_id' => $this->internal->id, 'user_id' => $this->users['cai']->id, 'channel' => 'internal', 'visited_on' => '2026-06-12', 'declined_at' => '2026-06-12 10:00:00']);
        $this->answered($this->internal, 'dan', '2026-06-20 10:00:00', ['ambiente' => 4, 'como' => 'google']);

        // Por correo: a Eva se le manda y contesta el 29 (nota 1 con texto); a Gil se le manda el 15 y no contesta;
        // a Fon se le mandó y contestó en MAYO (el periodo anterior).
        $this->sent($this->external, 'eva', '2026-06-29 08:00:00', '2026-06-29 12:00:00', ['nota' => 1, 'texto' => 'Sucio']);
        $this->sent($this->external, 'gil', '2026-06-15 08:00:00');
        $this->sent($this->external, 'fon', '2026-05-30 08:00:00', '2026-05-31 09:00:00', ['nota' => 3]);

        // La encuesta apagada con una respuesta en junio: sale la tercera.
        $this->answered($this->old, 'ana', '2026-06-10 10:00:00', ['x' => 3]);
    }

    /** @param  array<string, mixed>  $answers */
    private function answered(Survey $survey, string $user, string $atUtc, array $answers): void
    {
        SurveyResponse::create(['survey_id' => $survey->id, 'user_id' => $this->users[$user]->id, 'channel' => 'internal', 'visited_on' => substr($atUtc, 0, 10), 'answered_at' => $atUtc, 'answers' => $answers, 'locale' => 'es']);
    }

    /** @param  array<string, mixed>|null  $answers */
    private function sent(Survey $survey, string $user, string $sentAtUtc, ?string $answeredAtUtc = null, ?array $answers = null): void
    {
        SurveyResponse::create(['survey_id' => $survey->id, 'user_id' => $this->users[$user]->id, 'channel' => 'external', 'token' => Str::random(40), 'sent_at' => $sentAtUtc, 'answered_at' => $answeredAtUtc, 'answers' => $answers, 'locale' => 'es']);
    }

    // ─── Las cifras ───────────────────────────────────────────────────────────

    public function test_the_totals_the_two_rates_and_the_previous_period(): void
    {
        $this->seedJune();

        $r = (new SurveysReport)->compute($this->june());

        // Seis visitas, cinco OFERTAS (la segunda de Ana no lo es: ya había contestado) y cuatro contestadas en la
        // puerta → 80 %. Sobre las visitas a secas saldría 66,7 %: la tasa se mide sobre a quién se le preguntó.
        $this->assertSame(['sent' => 2, 'answered' => 5, 'answered_internal' => 4, 'answered_external' => 1, 'declined' => 1, 'visits' => 6, 'offered' => 5, 'internal_rate_bp' => 8000, 'external_rate_bp' => 5000], $r['totals']);
        $this->assertSame(['survey' => 'Tu visita', 'question' => 'Ambiente', 'mean' => 3.7, 'n' => 3], $r['scale'], 'la primera escala de la interna viva');

        // Mayo: lo de Fon (mandada y contestada) y su visita, que fue una oferta sin respuesta.
        $this->assertSame(['sent' => 1, 'answered' => 1, 'answered_internal' => 0, 'answered_external' => 1, 'declined' => 0, 'visits' => 1, 'offered' => 1], $r['previous']);

        $sum = static fn (string $field) => array_sum(array_column($r['series'], $field));
        $this->assertSame(5, $sum('answered'));
        $this->assertSame(1, $sum('declined'));
        $this->assertSame(2, $sum('sent'));
    }

    public function test_per_survey_in_order_and_the_distribution_of_every_question_type(): void
    {
        $this->seedJune();

        $surveys = (new SurveysReport)->compute($this->june())['surveys'];

        $this->assertSame(['visita', 'que-tal', 'vieja'], array_column($surveys, 'key'), 'la interna viva, la externa viva y después las que tengan filas');
        $visita = $surveys[0];
        $this->assertSame([0, 3, 0, 1], [$visita['sent'], $visita['answered'], $visita['answered_external'], $visita['declined']]);

        $q = array_column($visita['questions'], null, 'key');
        $this->assertSame([0, 1, 0, 1, 1], array_column($q['ambiente']['distribution'], 'n'));
        $this->assertSame(3.7, $q['ambiente']['mean']);
        $this->assertSame([['key' => 'google', 'label' => 'Google', 'n' => 2], ['key' => 'amigos', 'label' => 'Amigos', 'n' => 1]], $q['como']['distribution']);
        $this->assertSame([2, 1], array_column($q['zonas']['distribution'], 'n'), 'la múltiple cuenta cada opción marcada');
        $this->assertSame([1, 1], array_column($q['volveria']['distribution'], 'n'));
        $this->assertSame(2, $q['volveria']['n'], 'quien no contestó el sí/no no cuenta');
        $this->assertSame(['Mucha cola', 'Genial'], $q['comentario']['texts'], 'los últimos textos, el más reciente primero');

        $queTal = $surveys[1];
        $this->assertSame([2, 1, 1], [$queTal['sent'], $queTal['answered'], $queTal['answered_external']]);
        $this->assertSame(1.0, array_column($queTal['questions'], null, 'key')['nota']['mean']);
    }

    public function test_attention_lists_the_low_scores_of_the_last_thirty_days_worst_first_with_the_person(): void
    {
        $this->seedJune();

        $attention = (new SurveysReport)->compute($this->june())['attention'];

        $this->assertSame([1, 2], array_column($attention, 'score'));
        $this->assertSame('Eva Test', $attention[0]['user_name']);
        $this->assertSame($this->users['eva']->id, $attention[0]['user_id']);
        $this->assertSame('Sucio', $attention[0]['text']);
        $this->assertSame('external', $attention[0]['channel']);
        $this->assertSame('Mucha cola', $attention[1]['text']);
        $this->assertSame('Tu visita', $attention[1]['survey']);

        // Treinta y un días después, la de Bea (5 de junio) ya no está por atender.
        $this->travelTo(Carbon::parse('2026-07-06 12:00:00', self::TZ));
        $later = (new SurveysReport)->compute($this->june())['attention'];
        $this->assertSame([1], array_column($later, 'score'));
    }

    public function test_the_report_runs_in_a_constant_budget_of_queries(): void
    {
        $this->seedJune();

        DB::enableQueryLog();
        DB::flushQueryLog();
        (new SurveysReport)->compute($this->june());
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(20, $queries, "el informe de las encuestas hace {$queries} consultas");
    }

    /** Los textos libres van SOLO en la pestaña: el CSV fuerza la ventana y no los lleva. */
    public function test_the_free_texts_are_in_the_tab_but_never_in_the_csv(): void
    {
        $this->seedJune();

        $widget = new SurveysBreakdownWidget;
        $widget->pageFilters = ['period' => ReportPeriod::Last30->value];
        $tab = (new \ReflectionMethod($widget, 'getViewData'))->invoke($widget)['tables'];
        $tabHeadings = array_column($tab, 'heading');
        $this->assertContains(__('admin.analytics.surveys.texts_heading', ['survey' => 'Tu visita']), $tabHeadings);
        $this->assertStringContainsString('Mucha cola', json_encode($tab, JSON_UNESCAPED_UNICODE));

        $csv = (new SurveysBreakdownWidget)->tablesFor($this->june());
        $this->assertNotContains(__('admin.analytics.surveys.texts_heading', ['survey' => 'Tu visita']), array_column($csv, 'heading'));
        $this->assertStringNotContainsString('Mucha cola', json_encode($csv, JSON_UNESCAPED_UNICODE), 'un texto libre puede llevar un nombre: el CSV no lo lleva');
        $this->assertStringNotContainsString('Sucio', json_encode($csv, JSON_UNESCAPED_UNICODE));
    }
}
