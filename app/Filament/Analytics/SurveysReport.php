<?php

namespace App\Filament\Analytics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Surveys\QuestionSchema;
use App\Domain\Platform\Services\Surveys\SurveySeals;
use App\Domain\Platform\Services\Translated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * **LAS ENCUESTAS: cuántas se contestan y qué dicen — ANÓNIMAS** (`docs/specs/encuestas.md` §4.4 y §4.7, T4 y T5;
 * `DECISIONES #740` y `#754`).
 *
 * Lee dos tablas que no se unen: las RESPUESTAS (sin persona, por su día del parque) y las PARTICIPACIONES (a quién se
 * preguntó o mandó, solo para contar envíos y ofertas). Por encuesta —la interna viva, la externa viva y las que
 * tengan respuestas en el periodo—: mandadas, contestadas por canal, «no preguntar», y por pregunta el reparto, la
 * media de la escala, los síes y los noes, y unos textos libres (solo en la pestaña, nunca en el CSV). Dos tasas: la
 * interna, contestadas en la puerta entre las OFERTAS; la externa, contestadas por correo entre mandadas.
 *
 * ⚠️⚠️ **NINGUNA CIFRA SALE DE MENOS DE {@see MIN_CELL} RESPUESTAS** (`#754`): una media, un reparto o una tasa de
 * «volvió» sobre cuatro personas, junto al registro de actividad (quién preguntó a quién ese día), dice lo que
 * contestó cada una. Por debajo, la celda dice «menos de 5» y no lleva valor. Los textos libres salen solo con cinco
 * o más, sin día ni franja ni empleado, y en el orden de su clave ALEATORIA: «el último» junto a la participación de
 * ayer destaparía a quién es. **«Por atender» se retiró** (el owner: «para llamarle, no»): en su lugar, «notas bajas y
 * si volvieron», sin persona.
 *
 * Unas diez consultas por periodo; la caché de cinco minutos las reparte entre los widgets y el CSV.
 */
final class SurveysReport
{
    public const CACHE_SECONDS = 300;

    /** Cuántos textos libres se enseñan por pregunta (una muestra, en orden aleatorio). */
    public const TEXTS_SHOWN = 12;

    /** El mínimo de respuestas detrás de cualquier cifra (`#754`). */
    public const MIN_CELL = 5;

    /** Una respuesta es una «nota baja» si alguna de sus escalas vale esto o menos. */
    public const LOW_MAX_SCORE = 2;

    /** @return array<string, mixed> */
    public static function for(Window $window, Comparison $comparison = Comparison::Previous): array
    {
        $baseline = $comparison->baseline($window);

        return Cache::remember(self::cacheKey($window, $baseline), self::CACHE_SECONDS, fn (): array => (new self)->compute($window, $baseline));
    }

    public static function cacheKey(Window $window, Window $baseline): string
    {
        return 'analytics:surveys:v2:'.$window->timezone.':'.$window->dateFrom().':'.$window->dateTo().':'.$baseline->dateFrom().':'.$baseline->dateTo().':'.app()->getLocale();
    }

    /** ¿Hay base para enseñar una cifra hecha de `$n` respuestas? */
    public static function enough(int $n): bool
    {
        return $n >= self::MIN_CELL;
    }

    /**
     * @param  Window|null  $baseline  con qué se compara; sin ella, el periodo anterior
     * @return array<string, mixed>
     */
    public function compute(Window $window, ?Window $baseline = null): array
    {
        $baseline ??= $window->previous();
        $surveys = $this->surveys($window->timezone);
        $responses = $this->responses($window);
        $sent = $this->sent($window);
        $totals = $this->totals($responses, $sent);
        $totals['visits'] = $this->visits($window);
        $totals['offered'] = $this->offered($surveys, $window);
        $totals['internal_rate_bp'] = $totals['offered'] > 0 ? (int) round(min($totals['answered_internal'], $totals['offered']) / $totals['offered'] * 10000) : 0;
        $totals['external_rate_bp'] = $totals['sent'] > 0 ? (int) round(min($totals['answered_external'], $totals['sent']) / $totals['sent'] * 10000) : 0;

        $perSurvey = $this->perSurvey($surveys, $responses, $sent);

        return [
            'window' => [
                'from' => $window->dateFrom(),
                'to' => $window->dateTo(),
                'days' => $window->days(),
                'granularity' => $window->granularity(),
            ],
            'totals' => $totals,
            'scale' => $this->firstScale($perSurvey),
            'surveys' => $perSurvey,
            'low_scores' => $this->lowScores($surveys, $responses),
            'series' => $this->series($window, $responses, $sent),
            'previous' => $this->totalsOnly($baseline, $surveys),
        ];
    }

    // ─── Las encuestas, las respuestas y los envíos del periodo ──────────────────────────────────

    /**
     * Todas las encuestas, con sus preguntas normalizadas y rotuladas en el idioma del panel, y su ventana en días
     * del parque (para contar las ofertas de la interna).
     *
     * @return array<int, array{id: int, key: string, name: string, kind: string, live: bool, active: bool, starts_on: ?string, ends_on: ?string, questions: list<array{key: string, type: string, label: string, options: array<string, string>}>}>
     */
    private function surveys(string $timezone): array
    {
        $locale = app()->getLocale();
        $out = [];
        foreach (Survey::query()->orderBy('id')->get() as $survey) {
            $questions = [];
            foreach ($survey->questionList() as $q) {
                $options = [];
                foreach ($q['options'] as $o) {
                    $options[$o['key']] = QuestionSchema::label($o['label'], $locale);
                }
                $questions[] = ['key' => $q['key'], 'type' => $q['type'], 'label' => QuestionSchema::label($q['label'], $locale), 'options' => $options];
            }
            $out[(int) $survey->getKey()] = [
                'id' => (int) $survey->getKey(),
                'key' => (string) $survey->key,
                'name' => $survey->displayName($locale),
                'kind' => (string) $survey->kind,
                'live' => $survey->isRunning(),
                'active' => (bool) $survey->active,
                'starts_on' => $survey->starts_at?->copy()->setTimezone($timezone)->toDateString(),
                'ends_on' => $survey->ends_at?->copy()->setTimezone($timezone)->toDateString(),
                'questions' => $questions,
            ];
        }

        return $out;
    }

    /**
     * Las respuestas del periodo, por su DÍA del parque. ⚠️ En el orden de su clave, que es ALEATORIA (UUID v4): nada de
     * lo que sale de aquí va en orden de llegada.
     *
     * @return Collection<int, stdClass>
     */
    private function responses(Window $window): Collection
    {
        return DB::table('survey_responses')
            ->whereBetween('answered_on', [$window->dateFrom(), $window->dateTo()])
            ->select(['id', 'survey_id', 'channel', 'answered_on', 'declined', 'answers', 'returned'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Los correos MANDADOS del periodo (la participación externa, por su hora de envío).
     *
     * @return Collection<int, stdClass>
     */
    private function sent(Window $window): Collection
    {
        return DB::table('survey_participations')
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$window->utcFrom()->format('Y-m-d H:i:s'), $window->utcTo()->format('Y-m-d H:i:s')])
            ->select(['survey_id', 'sent_at'])
            ->get()
            ->filter(static fn (stdClass $row): bool => $window->contains(CarbonImmutable::parse((string) $row->sent_at, 'UTC')))
            ->values();
    }

    /**
     * @param  Collection<int, stdClass>  $responses
     * @param  Collection<int, stdClass>  $sent
     * @return array{sent: int, answered: int, answered_internal: int, answered_external: int, declined: int}
     */
    private function totals(Collection $responses, Collection $sent): array
    {
        $out = ['sent' => $sent->count(), 'answered' => 0, 'answered_internal' => 0, 'answered_external' => 0, 'declined' => 0];
        foreach ($responses as $row) {
            if ((bool) $row->declined) {
                $out['declined']++;

                continue;
            }
            $out['answered']++;
            $out[$row->channel === SurveyResponse::CHANNEL_EXTERNAL ? 'answered_external' : 'answered_internal']++;
        }

        return $out;
    }

    /** Las visitas acreditadas del periodo (día del parque). */
    private function visits(Window $window): int
    {
        return (int) DB::table('customer_visits')->whereBetween('visited_on', [$window->dateFrom(), $window->dateTo()])->count();
    }

    /**
     * **Las OFERTAS de la interna en el periodo**, el denominador de su tasa (spec §4.4): las visitas acreditadas en
     * días en que una encuesta interna estaba viva —por su ventana; `active` no guarda historia, así que una apagada
     * hoy no cuenta ofertas— y de clientes SIN participación previa en ella: a quien ya se le preguntó no se le vuelve a
     * ofrecer, así que su visita no es una oferta. Una participación del MISMO día sí lo es (es la respuesta a esa
     * oferta): por eso se compara su día con el de la visita, estrictamente anterior.
     *
     * @param  array<int, array<string, mixed>>  $surveys
     */
    private function offered(array $surveys, Window $window): int
    {
        $offered = 0;
        foreach ($surveys as $survey) {
            if ($survey['kind'] !== Survey::KIND_INTERNAL || ! $survey['active']) {
                continue;
            }
            $from = max($window->dateFrom(), $survey['starts_on'] ?? $window->dateFrom());
            $to = min($window->dateTo(), $survey['ends_on'] ?? $window->dateTo());
            if ($from > $to) {
                continue;
            }
            $offered += (int) DB::table('customer_visits as v')
                ->whereBetween('v.visited_on', [$from, $to])
                ->whereNotExists(static function ($query) use ($survey): void {
                    $query->selectRaw('1')->from('survey_participations as p')
                        ->whereColumn('p.user_id', 'v.user_id')
                        ->where('p.survey_id', $survey['id'])
                        ->whereColumn('p.asked_on', '<', 'v.visited_on');
                })
                ->count();
        }

        return $offered;
    }

    // ─── Por encuesta y por pregunta ─────────────────────────────────────────────────────────────

    /**
     * En este orden: la interna viva, la externa viva y después las que tengan respuestas o envíos en el periodo. Cada
     * una con sus recuentos y, por pregunta, lo que dicen sus respuestas CONTESTADAS.
     *
     * @param  array<int, array<string, mixed>>  $surveys
     * @param  Collection<int, stdClass>  $responses
     * @param  Collection<int, stdClass>  $sent
     * @return list<array<string, mixed>>
     */
    private function perSurvey(array $surveys, Collection $responses, Collection $sent): array
    {
        $byId = $responses->groupBy('survey_id');
        $sentById = $sent->groupBy('survey_id');
        $ordered = [];
        foreach ([Survey::KIND_INTERNAL, Survey::KIND_EXTERNAL] as $kind) {
            foreach ($surveys as $survey) {
                if ($survey['live'] && $survey['kind'] === $kind) {
                    $ordered[$survey['id']] = $survey;
                }
            }
        }
        foreach ($surveys as $survey) {
            if (! isset($ordered[$survey['id']]) && ($byId->has($survey['id']) || $sentById->has($survey['id']))) {
                $ordered[$survey['id']] = $survey;
            }
        }

        $out = [];
        foreach ($ordered as $survey) {
            /** @var Collection<int, stdClass> $own */
            $own = $byId->get($survey['id'], collect());
            /** @var Collection<int, stdClass> $ownSent */
            $ownSent = $sentById->get($survey['id'], collect());
            $counts = $this->totals($own, $ownSent);
            $answered = $own->reject(static fn (stdClass $row): bool => (bool) $row->declined)->values();

            $out[] = [
                'id' => $survey['id'],
                'key' => $survey['key'],
                'name' => $survey['name'],
                'kind' => $survey['kind'],
                'live' => $survey['live'],
                'sent' => $counts['sent'],
                'answered' => $counts['answered'],
                'answered_internal' => $counts['answered_internal'],
                'answered_external' => $counts['answered_external'],
                'declined' => $counts['declined'],
                'questions' => $this->questions($survey['questions'], $answered),
            ];
        }

        return $out;
    }

    /**
     * Por pregunta: cuántas la contestaron (`n`) y, SOLO con {@see MIN_CELL} o más, su reparto, su media o sus textos;
     * por debajo, `suppressed` y nada más (la celda dirá «menos de 5»).
     *
     * @param  list<array{key: string, type: string, label: string, options: array<string, string>}>  $questions
     * @param  Collection<int, stdClass>  $answered  en el orden de su clave aleatoria
     * @return list<array{key: string, type: string, label: string, n: int, suppressed: bool, mean: float|null, distribution: list<array{key: string, label: string, n: int}>, texts: list<string>}>
     */
    private function questions(array $questions, Collection $answered): array
    {
        $decoded = $answered->map(static fn (stdClass $row): array => is_string($row->answers) ? (array) json_decode($row->answers, true) : [])->all();
        $out = [];

        foreach ($questions as $q) {
            $values = [];
            foreach ($decoded as $answers) {
                if (array_key_exists($q['key'], $answers)) {
                    $values[] = $answers[$q['key']];
                }
            }
            $item = ['key' => $q['key'], 'type' => $q['type'], 'label' => $q['label'], 'n' => count($values), 'suppressed' => false, 'mean' => null, 'distribution' => [], 'texts' => []];

            switch ($q['type']) {
                case QuestionSchema::TYPE_CHOICE:
                case QuestionSchema::TYPE_MULTI:
                    $counts = array_fill_keys(array_keys($q['options']), 0);
                    foreach ($values as $value) {
                        foreach ((array) $value as $picked) {
                            if (is_string($picked) && isset($counts[$picked])) {
                                $counts[$picked]++;
                            }
                        }
                    }
                    foreach ($counts as $key => $n) {
                        $item['distribution'][] = ['key' => (string) $key, 'label' => $q['options'][$key], 'n' => $n];
                    }
                    break;
                case QuestionSchema::TYPE_SCALE:
                    $counts = array_fill(QuestionSchema::SCALE_MIN, QuestionSchema::SCALE_MAX - QuestionSchema::SCALE_MIN + 1, 0);
                    $sum = 0;
                    $n = 0;
                    foreach ($values as $value) {
                        if (is_int($value) && isset($counts[$value])) {
                            $counts[$value]++;
                            $sum += $value;
                            $n++;
                        }
                    }
                    $item['n'] = $n;
                    $item['mean'] = $n > 0 ? round($sum / $n, 1) : null;
                    foreach ($counts as $score => $count) {
                        $item['distribution'][] = ['key' => (string) $score, 'label' => (string) $score, 'n' => $count];
                    }
                    break;
                case QuestionSchema::TYPE_YESNO:
                    $yes = count(array_filter($values, static fn (mixed $v): bool => $v === true));
                    $no = count(array_filter($values, static fn (mixed $v): bool => $v === false));
                    $item['n'] = $yes + $no;
                    $item['distribution'] = [
                        ['key' => 'yes', 'label' => __('admin.analytics.surveys.yes'), 'n' => $yes],
                        ['key' => 'no', 'label' => __('admin.analytics.surveys.no'), 'n' => $no],
                    ];
                    break;
                default:
                    $texts = array_values(array_filter($values, static fn (mixed $v): bool => is_string($v) && trim($v) !== ''));
                    $item['n'] = count($texts);
                    // Una MUESTRA en el orden de la clave aleatoria de su fila: ni los últimos ni por fecha.
                    $item['texts'] = array_map(static fn (string $t): string => mb_substr($t, 0, QuestionSchema::TEXT_MAX), array_slice($texts, 0, self::TEXTS_SHOWN));
            }

            if (! self::enough($item['n'])) {
                $item['suppressed'] = true;
                $item['mean'] = null;
                $item['distribution'] = [];
                $item['texts'] = [];
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * La primera escala con respuestas de la primera encuesta: la cifra de la tarjeta. Con menos de {@see MIN_CELL}
     * respuestas sale SIN media (`suppressed`).
     *
     * @param  list<array<string, mixed>>  $perSurvey
     * @return array{survey: string, question: string, mean: ?float, n: int, suppressed: bool}|null
     */
    private function firstScale(array $perSurvey): ?array
    {
        foreach ($perSurvey as $survey) {
            foreach ($survey['questions'] as $q) {
                if ($q['type'] === QuestionSchema::TYPE_SCALE && $q['n'] > 0) {
                    return [
                        'survey' => $survey['name'],
                        'question' => $q['label'],
                        'mean' => $q['suppressed'] ? null : (float) $q['mean'],
                        'n' => (int) $q['n'],
                        'suppressed' => (bool) $q['suppressed'],
                    ];
                }
            }
        }

        return null;
    }

    // ─── «Notas bajas y si volvieron» ────────────────────────────────────────────────────────────

    /**
     * Las respuestas CONTESTADAS del periodo con alguna escala, partidas en «nota baja» (alguna escala ≤
     * {@see LOW_MAX_SCORE}) y «el resto», y de cada grupo cuántas ya han VUELTO (el sello resuelto a «volvió»), cuántas
     * aún pueden volver y de cuántas NO SE SABE. «Ya han vuelto» sobre las que se saben o aún pueden es una cota que crece
     * con los días, igual para los dos grupos del mismo periodo: por eso se comparan entre sí. Sin persona: son recuentos
     * de filas anónimas. La pantalla aplica el mínimo: el reparto, con el total ≥ 5; la tasa de cada grupo, con su grupo
     * (sin las que no se saben) ≥ 5.
     *
     * ⚠️ **«No se sabe»** es una respuesta SIN resolver cuyo plazo ya pasó: su sello se borró sin anotar nada (su cuenta se
     * anonimizó) o nunca lo tuvo (las filas de antes de `#754`). Se decide por la FECHA y no por el sello, que este
     * informe no lee nunca: sin esto contaría como «aún puede volver» para siempre.
     *
     * @param  array<int, array<string, mixed>>  $surveys
     * @param  Collection<int, stdClass>  $responses
     * @return array{low: array{n: int, returned: int, pending: int, unknown: int}, rest: array{n: int, returned: int, pending: int, unknown: int}}
     */
    private function lowScores(array $surveys, Collection $responses): array
    {
        $out = ['low' => ['n' => 0, 'returned' => 0, 'pending' => 0, 'unknown' => 0], 'rest' => ['n' => 0, 'returned' => 0, 'pending' => 0, 'unknown' => 0]];
        // El último día que aún puede resolverse: el plazo de un día de respuesta D acaba cuando se ha vivido D + 90.
        $stillOpenFrom = DisplayTime::today()->subDays(SurveySeals::DAYS)->toDateString();
        foreach ($responses as $row) {
            $survey = $surveys[(int) $row->survey_id] ?? null;
            if ($survey === null || (bool) $row->declined) {
                continue;
            }
            $answers = is_string($row->answers) ? (array) json_decode($row->answers, true) : [];
            $worst = null;
            foreach ($survey['questions'] as $q) {
                $value = $answers[$q['key']] ?? null;
                if ($q['type'] === QuestionSchema::TYPE_SCALE && is_int($value) && ($worst === null || $value < $worst)) {
                    $worst = $value;
                }
            }
            if ($worst === null) {
                continue;
            }
            $group = $worst <= self::LOW_MAX_SCORE ? 'low' : 'rest';
            $out[$group]['n']++;
            if ($row->returned === null) {
                $out[$group][substr((string) $row->answered_on, 0, 10) >= $stillOpenFrom ? 'pending' : 'unknown']++;
            } elseif ((bool) $row->returned) {
                $out[$group]['returned']++;
            }
        }

        return $out;
    }

    // ─── La serie y el periodo anterior ──────────────────────────────────────────────────────────

    /**
     * @param  Collection<int, stdClass>  $responses
     * @param  Collection<int, stdClass>  $sent
     * @return list<array{key: string, answered: int, declined: int, sent: int}>
     */
    private function series(Window $window, Collection $responses, Collection $sent): array
    {
        $byKey = [];
        $bump = static function (string $key, string $field) use (&$byKey): void {
            $byKey[$key] ??= ['answered' => 0, 'declined' => 0, 'sent' => 0];
            $byKey[$key][$field]++;
        };
        foreach ($responses as $row) {
            // El día del parque a mediodía: un DÍA, no un instante, y así ningún cambio de hora lo mueve de cubo.
            $day = CarbonImmutable::parse(substr((string) $row->answered_on, 0, 10).' 12:00:00', $window->timezone);
            $bump($window->bucketKey($day), (bool) $row->declined ? 'declined' : 'answered');
        }
        foreach ($sent as $row) {
            $bump($window->bucketKey(CarbonImmutable::parse((string) $row->sent_at, 'UTC')), 'sent');
        }

        $series = [];
        foreach ($window->bucketKeys() as $key) {
            $series[] = ['key' => $key] + ($byKey[$key] ?? ['answered' => 0, 'declined' => 0, 'sent' => 0]);
        }

        return $series;
    }

    /**
     * Los totales de una ventana tal como este informe los usa para su periodo comparado: la historia de la T3b (`#790`).
     *
     * @return array{previous: array<string, int>}
     */
    public static function baselineTotals(Window $window): array
    {
        $report = new self;

        return ['previous' => $report->totalsOnly($window, $report->surveys($window->timezone))];
    }

    /**
     * Las cifras de las tarjetas para el periodo de comparación.
     *
     * @param  array<int, array<string, mixed>>  $surveys
     * @return array<string, int>
     */
    private function totalsOnly(Window $window, array $surveys): array
    {
        $totals = $this->totals($this->responses($window), $this->sent($window));
        $totals['visits'] = $this->visits($window);
        $totals['offered'] = $this->offered($surveys, $window);

        return $totals;
    }

    /** El nombre de una encuesta en el idioma del panel, desde su JSON (para quien no tenga el modelo a mano). */
    public static function nameOf(mixed $json, string $fallback): string
    {
        $decoded = is_string($json) ? json_decode($json, true) : $json;

        return is_array($decoded) ? (string) (Translated::pick($decoded, app()->getLocale()) ?? $fallback) : $fallback;
    }
}
