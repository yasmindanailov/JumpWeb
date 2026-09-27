<?php

namespace Tests\Feature\Surveys;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyParticipation;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Analytics\Contract;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * **Las guardas del ANONIMATO** (`docs/specs/encuestas.md` §4.7, T5; `[DECIDIDO owner]` `DECISIONES #754`): lo que hace
 * que «nadie, desde el panel o sus exportaciones, puede saber qué contestó una persona; y a los 90 días, tampoco con
 * acceso a la base de datos» sea una propiedad del esquema y no una buena intención.
 *
 * Cada censo está TECLEADO a mano: una columna nueva en la respuesta (un `user_id`, un `created_at`, un `locale`) o un
 * lector nuevo del sello ponen esto en rojo hasta que alguien lo decida en la spec.
 */
class SurveyAnonymityTest extends TestCase
{
    use RefreshDatabase;

    /** Lo ÚNICO que guarda una respuesta: sin cliente, sin hora, sin idioma, sin token, y la clave es una cadena. */
    private const RESPONSE_COLUMNS = [
        'id', 'survey_id', 'channel', 'answered_on', 'band', 'asked_by', 'first_visit', 'visit_kind', 'declined', 'answers',
        'seal', 'returned', 'returned_after_days',
    ];

    /** A quién se preguntó: sin nada de lo contestado, sin desenlace, sin hora de la puerta. */
    private const PARTICIPATION_COLUMNS = ['id', 'survey_id', 'user_id', 'channel', 'asked_by', 'asked_on', 'sent_at', 'token_hash', 'locale'];

    /** Los ÚNICOS ficheros de `app/` que pueden nombrar la columna del sello. */
    private const SEAL_READERS = [
        'app/Domain/Platform/Services/Surveys/SurveySeals.php',
        'app/Domain/Platform/Models/SurveyResponse.php',
    ];

    public function test_the_response_table_keeps_no_person_no_time_no_language_and_no_sequential_key(): void
    {
        $columns = Schema::getColumnListing('survey_responses');
        sort($columns);
        $expected = self::RESPONSE_COLUMNS;
        sort($expected);

        $this->assertSame($expected, $columns);
        $id = collect(Schema::getColumns('survey_responses'))->firstWhere('name', 'id');
        $this->assertFalse((bool) ($id['auto_increment'] ?? false), 'una clave autoincremental ordenaría las respuestas como sus participaciones');
    }

    public function test_the_participation_table_keeps_no_answer_and_no_outcome(): void
    {
        $columns = Schema::getColumnListing('survey_participations');
        sort($columns);
        $expected = self::PARTICIPATION_COLUMNS;
        sort($expected);

        $this->assertSame($expected, $columns);
        $this->assertSame(['hash'], Schema::getColumnListing('survey_spent_tokens'), 'el token gastado: un hash, sin fecha ni clave foránea');
    }

    /** La clave es un UUID **v4**: aleatoria. El `HasUuids` de Laravel da v7, ordenado por tiempo, y uniría las tablas. */
    public function test_the_response_key_is_a_random_uuid_v4(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 11:00:00', 'Europe/Madrid'));
        $survey = Survey::create([
            'key' => 'visita', 'name' => ['es' => 'Tu visita'], 'kind' => Survey::KIND_INTERNAL, 'active' => true,
            'questions' => [['key' => 'nota', 'type' => 'scale', 'label' => ['es' => 'Nota']]],
        ]);
        foreach (range(1, 3) as $i) {
            app(SurveyResponses::class)->answerInPerson($survey, User::factory()->create()->id, ['nota' => $i], null);
        }

        $ids = SurveyResponse::query()->pluck('id')->all();
        $this->assertCount(3, $ids);
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', (string) $id);
        }
    }

    /**
     * **El sello lo nombra UNA clase** (y el modelo, para esconderlo): el panel, el cuadro, el CSV y la API no lo leen
     * nunca. Se busca la columna por su nombre entre comillas y como propiedad.
     */
    public function test_only_the_seals_service_names_the_seal_column(): void
    {
        $offenders = [];
        $root = base_path('app');
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            /** @var SplFileInfo $file */
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = 'app/'.ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
            if (in_array($relative, self::SEAL_READERS, true)) {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            // La PROPIEDAD `->seal`, no un método `->seal(…)`: hay dos ajenos con ese nombre (el sellador de edades de la
            // fiesta mixta y el de atribución), y cazarlos sería acusar a quien no toca la columna.
            if (preg_match('/[\'"]seal[\'"]|->seal\b(?!\s*\()/', $code) === 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'el sello solo lo leen `SurveySeals` (y el modelo lo esconde)');
    }

    public function test_the_models_never_serialize_the_seal_or_the_token_hash(): void
    {
        $response = new SurveyResponse(['survey_id' => 1, 'channel' => 'internal', 'answered_on' => '2026-09-25', 'band' => 'morning']);
        $response->forceFill(['seal' => 'x']);
        $this->assertArrayNotHasKey('seal', $response->toArray());
        $this->assertNotContains('seal', $response->getFillable(), 'el sello no se asigna en masa');

        $participation = new SurveyParticipation(['survey_id' => 1, 'channel' => 'external', 'asked_on' => '2026-09-25', 'token_hash' => 'h']);
        $this->assertArrayNotHasKey('token_hash', $participation->toArray());
    }

    /** Al libro va el ENVÍO (de la participación) y nada más: contestar y declinar no existen en el contrato. */
    public function test_the_ledger_only_knows_that_a_survey_was_sent(): void
    {
        $surveyEvents = array_values(array_filter(array_keys(Contract::EVENTS), static fn (string $name): bool => str_starts_with($name, 'survey_')));

        $this->assertSame(['survey_sent'], $surveyEvents);
    }
}
