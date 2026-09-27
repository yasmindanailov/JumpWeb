<?php

namespace Tests\Feature\Analytics;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyParticipation;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\SurveysReport;
use App\Filament\Widgets\Analytics\SurveysBreakdownWidget;
use App\Filament\Widgets\Analytics\SurveysLowScoresWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **El informe de las encuestas, ANÓNIMAS** (`docs/specs/encuestas.md` §4.4 y §4.7, T4 y T5; `DECISIONES #740` y `#754`):
 * por DÍA DE LA RESPUESTA, las dos tasas (ofertas y envíos desde las participaciones), por encuesta y por pregunta el
 * reparto, la serie, el periodo anterior, el presupuesto de consultas, «notas bajas y si volvieron» y los textos
 * libres SOLO en la pestaña. Y los MÍNIMOS: ninguna cifra de menos de 5 respuestas, los textos en el orden de su clave
 * aleatoria y nunca por fecha.
 *
 * El fixture de junio, calculado a mano (las cifras de cada test salen de aquí, no del código):
 *  · «visita» (interna, viva): contestan ana 5 (el 5), bea 2 (el 5), dan 4 (el 20), hugo 1 (el 21), ines 3 (el 22), juan 5
 *    (el 23) → Ambiente n = 6, media 20/6 = 3,3; «Cómo» lo contestan cinco (google 3 · amigos 2); «Zonas» y «Volverías»
 *    solo dos → por debajo del mínimo; cinco textos. Cai dice «no preguntar» el 12.
 *  · «que-tal» (externa, viva): a eva se le manda el 29 y contesta 1; a gil se le manda el 15 y no contesta; a fon en mayo.
 *  · «vieja» (apagada): ana contesta 3 el 10.
 *  · Visitas de junio: ana (5 y 25), bea 5, cai 12, dan 20, hugo 21, ines 22, juan 23, eva 28 → 9; ofertas de la interna
 *    viva: todas menos la segunda de ana (ya se le había preguntado) → 8.
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

        foreach (['ana', 'bea', 'cai', 'dan', 'eva', 'fon', 'gil', 'hugo', 'ines', 'juan'] as $name) {
            $this->users[$name] = User::factory()->create(['name' => ucfirst($name).' Test', 'email' => $name.'@example.com']);
        }
        foreach (['ana' => '2026-06-05', 'bea' => '2026-06-05', 'cai' => '2026-06-12', 'dan' => '2026-06-20', 'hugo' => '2026-06-21', 'ines' => '2026-06-22', 'juan' => '2026-06-23', 'eva' => '2026-06-28', 'fon' => '2026-05-30'] as $name => $day) {
            app(GateVisits::class)->register($this->users[$name], null, Carbon::parse($day));
        }
        // Ana vuelve el 25: ya se le preguntó el 5, así que esa visita NO es una oferta (una por cliente y encuesta).
        app(GateVisits::class)->register($this->users['ana'], null, Carbon::parse('2026-06-25'));

        // En la puerta. Las claves, a mano y DESORDENADAS respecto a las fechas: el informe va por la clave aleatoria.
        $this->inPerson($this->internal, 'ana', '2026-06-05', ['ambiente' => 5, 'como' => 'google', 'zonas' => ['jump'], 'volveria' => true, 'comentario' => 'Todo genial'], 'aaaaaaaa-0000-4000-8000-000000000000', true);
        $this->inPerson($this->internal, 'bea', '2026-06-05', ['ambiente' => 2, 'como' => 'amigos', 'zonas' => ['jump', 'kids'], 'volveria' => false, 'comentario' => 'Mucha cola'], 'eeeeeeee-0000-4000-8000-000000000000', true);
        $this->inPerson($this->internal, 'cai', '2026-06-12', null, 'cccccccc-0000-4000-8000-000000000000');
        $this->inPerson($this->internal, 'dan', '2026-06-20', ['ambiente' => 4, 'como' => 'google'], 'dddddddd-0000-4000-8000-000000000000', false);
        $this->inPerson($this->internal, 'hugo', '2026-06-21', ['ambiente' => 1, 'como' => 'amigos', 'comentario' => 'Sucio'], '11111111-0000-4000-8000-000000000000');
        $this->inPerson($this->internal, 'ines', '2026-06-22', ['ambiente' => 3, 'como' => 'google', 'comentario' => 'Bien'], '55555555-0000-4000-8000-000000000000');
        $this->inPerson($this->internal, 'juan', '2026-06-23', ['ambiente' => 5, 'comentario' => 'Volveremos'], '00000000-0000-4000-8000-000000000000', true);
        $this->inPerson($this->old, 'ana', '2026-06-10', ['x' => 3], '77777777-0000-4000-8000-000000000000');

        // Por correo: a Eva se le manda el 29 y contesta ese día (nota 1); a Gil se le manda el 15 y no contesta; a Fon
        // se le mandó y contestó en MAYO (el periodo anterior).
        $this->mailed($this->external, 'eva', '2026-06-28', '2026-06-29 08:00:00', '2026-06-29', ['nota' => 1, 'texto' => 'Frío']);
        $this->mailed($this->external, 'gil', '2026-06-14', '2026-06-15 08:00:00');
        $this->mailed($this->external, 'fon', '2026-05-30', '2026-05-30 08:00:00', '2026-05-31', ['nota' => 3]);
    }

    /** @param  array<string, mixed>|null  $answers  `null` = «no preguntar» */
    private function inPerson(Survey $survey, string $user, string $day, ?array $answers, string $key, ?bool $returned = null): void
    {
        SurveyParticipation::create(['survey_id' => $survey->id, 'user_id' => $this->users[$user]->id, 'channel' => 'internal', 'asked_on' => $day]);
        $response = new SurveyResponse(['survey_id' => $survey->id, 'channel' => 'internal', 'answered_on' => $day, 'band' => 'morning', 'declined' => $answers === null, 'answers' => $answers]);
        $response->forceFill(['id' => $key, 'returned' => $returned])->save();
    }

    /** @param  array<string, mixed>|null  $answers */
    private function mailed(Survey $survey, string $user, string $visitedOn, string $sentAtUtc, ?string $answeredOn = null, ?array $answers = null): void
    {
        SurveyParticipation::create(['survey_id' => $survey->id, 'user_id' => $this->users[$user]->id, 'channel' => 'external', 'asked_on' => $visitedOn, 'sent_at' => $sentAtUtc, 'locale' => 'es']);
        if ($answeredOn !== null) {
            SurveyResponse::create(['survey_id' => $survey->id, 'channel' => 'external', 'answered_on' => $answeredOn, 'band' => 'midday', 'answers' => $answers]);
        }
    }

    // ─── Las cifras ───────────────────────────────────────────────────────────

    public function test_the_totals_the_two_rates_and_the_previous_period(): void
    {
        $this->seedJune();

        $r = (new SurveysReport)->compute($this->june());

        // Contestadas en la puerta: las seis de «visita» y la de «vieja» → 7; por correo, 1. Ofertas 8 → 87,5 %.
        $this->assertSame(['sent' => 2, 'answered' => 8, 'answered_internal' => 7, 'answered_external' => 1, 'declined' => 1, 'visits' => 9, 'offered' => 8, 'internal_rate_bp' => 8750, 'external_rate_bp' => 5000], $r['totals']);
        $this->assertSame(['survey' => 'Tu visita', 'question' => 'Ambiente', 'mean' => 3.3, 'n' => 6, 'suppressed' => false], $r['scale'], 'la primera escala de la interna viva');

        // Mayo: lo de Fon (mandada y contestada) y su visita, que fue una oferta sin respuesta.
        $this->assertSame(['sent' => 1, 'answered' => 1, 'answered_internal' => 0, 'answered_external' => 1, 'declined' => 0, 'visits' => 1, 'offered' => 1], $r['previous']);

        $sum = static fn (string $field) => array_sum(array_column($r['series'], $field));
        $this->assertSame(8, $sum('answered'));
        $this->assertSame(1, $sum('declined'));
        $this->assertSame(2, $sum('sent'));
        $bucket = array_column($r['series'], null, 'key');
        $this->assertSame(['key' => '2026-06-05', 'answered' => 2, 'declined' => 0, 'sent' => 0], $bucket['2026-06-05']);
        $this->assertSame(['key' => '2026-06-29', 'answered' => 1, 'declined' => 0, 'sent' => 1], $bucket['2026-06-29']);
    }

    public function test_per_survey_in_order_and_every_question_above_the_minimum(): void
    {
        $this->seedJune();

        $surveys = (new SurveysReport)->compute($this->june())['surveys'];

        $this->assertSame(['visita', 'que-tal', 'vieja'], array_column($surveys, 'key'), 'la interna viva, la externa viva y después las que tengan filas');
        $visita = $surveys[0];
        $this->assertSame([0, 6, 0, 1], [$visita['sent'], $visita['answered'], $visita['answered_external'], $visita['declined']]);

        $q = array_column($visita['questions'], null, 'key');
        $this->assertFalse($q['ambiente']['suppressed']);
        $this->assertSame([1, 1, 1, 1, 2], array_column($q['ambiente']['distribution'], 'n'));
        $this->assertSame(3.3, $q['ambiente']['mean']);
        $this->assertSame([['key' => 'google', 'label' => 'Google', 'n' => 3], ['key' => 'amigos', 'label' => 'Amigos', 'n' => 2]], $q['como']['distribution'], 'cinco la contestaron: justo el mínimo');

        $queTal = $surveys[1];
        $this->assertSame([2, 1, 1], [$queTal['sent'], $queTal['answered'], $queTal['answered_external']]);
    }

    /**
     * `#754`: **una cifra de menos de cinco respuestas no se pinta.** Dos contestaron «Zonas» y «Volverías», uno la nota
     * del correo: ni reparto, ni media, ni textos — solo cuántos. Con el registro de la puerta, un reparto de dos
     * personas dice qué contestó cada una.
     */
    public function test_a_question_answered_by_fewer_than_five_is_not_painted(): void
    {
        $this->seedJune();

        $surveys = array_column((new SurveysReport)->compute($this->june())['surveys'], null, 'key');
        $q = array_column($surveys['visita']['questions'], null, 'key');
        foreach (['zonas', 'volveria'] as $key) {
            $this->assertSame(2, $q[$key]['n']);
            $this->assertTrue($q[$key]['suppressed'], "«{$key}» con dos respuestas");
            $this->assertSame([], $q[$key]['distribution']);
        }
        $nota = array_column($surveys['que-tal']['questions'], null, 'key')['nota'];
        $this->assertSame([1, true, null, []], [$nota['n'], $nota['suppressed'], $nota['mean'], $nota['distribution']]);
        $texto = array_column($surveys['que-tal']['questions'], null, 'key')['texto'];
        $this->assertSame([1, true, []], [$texto['n'], $texto['suppressed'], $texto['texts']], 'un solo texto no sale: sería el de eva');

        // Y la tabla lo dice con palabras, con cuántas hubo.
        $widget = new SurveysBreakdownWidget;
        $widget->pageFilters = ['period' => ReportPeriod::Last30->value];
        $rows = collect((new \ReflectionMethod($widget, 'getViewData'))->invoke($widget)['tables'])
            ->firstWhere('heading', __('admin.analytics.surveys.by_question', ['survey' => 'Tu visita']))['rows'];
        $this->assertContains(['Zonas', 'menos de 5', '2'], $rows);
        $this->assertContains(['Volverías', 'menos de 5', '2'], $rows);
    }

    /** El borde exacto: con CUATRO respuestas la media no sale; con la QUINTA, sí. */
    public function test_four_answers_are_below_the_minimum_and_the_fifth_paints_it(): void
    {
        $survey = Survey::create([
            'key' => 'borde', 'name' => ['es' => 'Borde'], 'kind' => Survey::KIND_INTERNAL, 'active' => true,
            'questions' => [['key' => 'nota', 'type' => 'scale', 'label' => ['es' => 'Nota']]],
        ]);
        foreach ([4, 4, 5, 5] as $score) {
            SurveyResponse::create(['survey_id' => $survey->id, 'channel' => 'internal', 'answered_on' => '2026-06-10', 'band' => 'morning', 'answers' => ['nota' => $score]]);
        }

        $four = (new SurveysReport)->compute($this->june());
        $this->assertSame(['survey' => 'Borde', 'question' => 'Nota', 'mean' => null, 'n' => 4, 'suppressed' => true], $four['scale']);

        SurveyResponse::create(['survey_id' => $survey->id, 'channel' => 'internal', 'answered_on' => '2026-06-11', 'band' => 'morning', 'answers' => ['nota' => 3]]);
        $five = (new SurveysReport)->compute($this->june());
        $this->assertSame(['survey' => 'Borde', 'question' => 'Nota', 'mean' => 4.2, 'n' => 5, 'suppressed' => false], $five['scale']);
    }

    /**
     * Los textos libres: solo con cinco o más, sin día, y en el orden de la clave ALEATORIA de su fila — ni los últimos ni
     * por fecha: «el último» junto a la participación de ayer destaparía a quién es. Las claves del fixture están puestas
     * a propósito para que ese orden no coincida con el de las fechas en ningún sentido.
     */
    public function test_the_free_texts_come_in_the_order_of_their_random_key_never_by_date(): void
    {
        $this->seedJune();

        $visita = (new SurveysReport)->compute($this->june())['surveys'][0];
        $texts = array_column($visita['questions'], null, 'key')['comentario'];

        $this->assertSame(5, $texts['n']);
        $this->assertSame(['Volveremos', 'Sucio', 'Bien', 'Todo genial', 'Mucha cola'], $texts['texts']);
        $this->assertNotSame(['Todo genial', 'Mucha cola', 'Sucio', 'Bien', 'Volveremos'], $texts['texts'], 'no por fecha');
        $this->assertNotSame(['Volveremos', 'Bien', 'Sucio', 'Mucha cola', 'Todo genial'], $texts['texts'], 'ni la más reciente primero');
    }

    /**
     * «Notas bajas y si volvieron» (`#754`, en lugar de «Por atender»): tres notas bajas (bea 2, hugo 1, eva 1) y cinco
     * del resto (ana 5 y 3, dan 4, ines 3, juan 5). Volvieron: bea; ana (de «visita»), juan. Aún pueden: hugo, eva; ines y
     * la de «vieja». Sin persona, y el grupo de tres NO enseña su «volvió».
     */
    public function test_the_low_scores_and_whether_they_came_back_without_a_person_and_with_the_minimum(): void
    {
        $this->seedJune();

        $low = (new SurveysReport)->compute($this->june())['low_scores'];

        $this->assertSame(['low' => ['n' => 3, 'returned' => 1, 'pending' => 2, 'unknown' => 0], 'rest' => ['n' => 5, 'returned' => 2, 'pending' => 2, 'unknown' => 0]], $low);

        $widget = new SurveysLowScoresWidget;
        $widget->pageFilters = ['period' => ReportPeriod::Last30->value];
        $rows = (new \ReflectionMethod($widget, 'getViewData'))->invoke($widget)['tables'][0]['rows'];
        $this->assertSame([
            ['Nota baja (1 o 2)', '3', 'menos de 5', '—'],
            ['El resto (3 a 5)', '5', "2 · 40,0\u{00A0}%", '2'],
        ], $rows);
        $json = json_encode($rows, JSON_UNESCAPED_UNICODE);
        foreach (['Ana', 'Bea', 'Hugo', 'Eva'] as $name) {
            $this->assertStringNotContainsString($name, (string) $json);
        }
    }

    /**
     * **«No se sabe»**: una respuesta sin resolver cuyo plazo de 90 días ya pasó —su cuenta se anonimizó y su sello se
     * borró sin anotar nada, o es de antes de medir— no es «aún puede volver» para siempre, ni un «no»: queda fuera de la
     * tasa. El mismo junio visto a finales de septiembre: hugo y eva (bajas) e ines y la de «vieja» (resto) no se saben,
     * y al resto le quedan TRES que se saben: por debajo del mínimo, su tasa ya no se enseña.
     */
    public function test_an_unresolved_answer_past_its_ninety_days_is_unknown_and_leaves_the_rate(): void
    {
        $this->seedJune();
        $this->travelTo(Carbon::parse('2026-09-30 12:00:00', self::TZ));

        $low = (new SurveysReport)->compute($this->june())['low_scores'];

        $this->assertSame(['low' => ['n' => 3, 'returned' => 1, 'pending' => 0, 'unknown' => 2], 'rest' => ['n' => 5, 'returned' => 2, 'pending' => 0, 'unknown' => 2]], $low);

        $widget = new SurveysLowScoresWidget;
        $widget->pageFilters = ['period' => ReportPeriod::Custom->value, 'from' => '2026-06-01', 'to' => '2026-06-30'];
        $rows = (new \ReflectionMethod($widget, 'getViewData'))->invoke($widget)['tables'][0]['rows'];
        $this->assertSame(['El resto (3 a 5)', '5', 'menos de 5', '—'], $rows[1]);
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
        $this->assertStringNotContainsString('Volveremos', json_encode($csv, JSON_UNESCAPED_UNICODE));
    }
}
