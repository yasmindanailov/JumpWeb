<?php

namespace App\Filament\Analytics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\Surveys\QuestionSchema;
use App\Domain\Platform\Services\Translated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * **LAS ENCUESTAS: cuántas se contestan y qué dicen** (`docs/specs/encuestas.md` §4.4, T4; `DECISIONES #740`).
 *
 * **La unidad de tiempo es el DÍA DE LA RESPUESTA** (`answered_at` → día del parque); lo mandado va por el día del
 * envío y lo declinado por el suyo. Por encuesta —la interna viva, la externa viva y las que tengan respuestas en
 * el periodo—: mandadas, contestadas por canal, declinadas, y por pregunta el reparto de las opciones, la media y el
 * reparto de la escala, los síes y los noes, y los ÚLTIMOS textos libres (solo en la pestaña, nunca en el CSV).
 *
 * Dos tasas: la interna es contestadas en la puerta entre visitas acreditadas del periodo (la encuesta se ofrece al
 * acreditar, `#741`); la externa, contestadas por correo entre mandadas. **«Por atender»** son las respuestas de los
 * últimos 30 días con una escala ≤ 2: la persona sale solo con `customers.insights`, y eso lo decide el widget.
 *
 * ⚠️ Solo agregados salvo «Por atender» y los textos. Seis consultas por periodo; la caché de cinco minutos las
 * reparte entre los widgets y el CSV.
 */
final class SurveysReport
{
    public const CACHE_SECONDS = 300;

    /** Cuántos textos libres se enseñan por pregunta (los últimos). */
    public const TEXTS_SHOWN = 12;

    public const ATTENTION_DAYS = 30;

    public const ATTENTION_MAX_SCORE = 2;

    public const ATTENTION_ROWS = 50;

    /** @return array<string, mixed> */
    public static function for(Window $window, Comparison $comparison = Comparison::Previous): array
    {
        $baseline = $comparison->baseline($window);

        return Cache::remember(self::cacheKey($window, $baseline), self::CACHE_SECONDS, fn (): array => (new self)->compute($window, $baseline));
    }

    public static function cacheKey(Window $window, Window $baseline): string
    {
        return 'analytics:surveys:v1:'.$window->timezone.':'.$window->dateFrom().':'.$window->dateTo().':'.$baseline->dateFrom().':'.$baseline->dateTo().':'.app()->getLocale();
    }

    /**
     * @param  Window|null  $baseline  con qué se compara; sin ella, el periodo anterior
     * @return array<string, mixed>
     */
    public function compute(Window $window, ?Window $baseline = null): array
    {
        $baseline ??= $window->previous();
        $surveys = $this->surveys($window->timezone);
        $rows = $this->rows($window);
        $totals = $this->totals($rows, $window);
        $totals['visits'] = $this->visits($window);
        $totals['offered'] = $this->offered($surveys, $window);
        $totals['internal_rate_bp'] = $totals['offered'] > 0 ? (int) round(min($totals['answered_internal'], $totals['offered']) / $totals['offered'] * 10000) : 0;
        $totals['external_rate_bp'] = $totals['sent'] > 0 ? (int) round(min($totals['answered_external'], $totals['sent']) / $totals['sent'] * 10000) : 0;

        $perSurvey = $this->perSurvey($surveys, $rows, $window);

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
            'attention' => $this->attention($surveys),
            'series' => $this->series($window, $rows),
            'previous' => $this->totalsOnly($baseline, $surveys),
        ];
    }

    // ─── Las encuestas y las respuestas del periodo ──────────────────────────────────────────────

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
     * **Las OFERTAS de la interna en el periodo**, el denominador de su tasa (spec §4.4): las visitas acreditadas en
     * días en que una encuesta interna estaba viva —por su ventana; `active` no guarda historia, así que una
     * apagada hoy no cuenta ofertas— y de clientes SIN fila previa para ella: a quien ya contestó o declinó no se le
     * vuelve a ofrecer, así que su visita no es una oferta. Una fila del MISMO día sí lo es (es la respuesta a esa
     * oferta): el MOMENTO de la fila —mandada, contestada o declinada, lo primero que haya— se compara con la
     * medianoche del día de la visita. ⚠️ No `created_at`: en un fixture lo escribe el reloj del test, no el hecho.
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
                    $query->selectRaw('1')->from('survey_responses as r')
                        ->whereColumn('r.user_id', 'v.user_id')
                        ->where('r.survey_id', $survey['id'])
                        ->whereRaw('COALESCE(r.sent_at, r.answered_at, r.declined_at) < v.visited_on');
                })
                ->count();
        }

        return $offered;
    }

    /**
     * Las filas que TOCAN la ventana por alguno de sus tres momentos (mandada, contestada, declinada). Una fila
     * externa mandada y contestada en el mismo periodo cuenta en los dos: son dos hechos distintos.
     *
     * @return Collection<int, stdClass>
     */
    private function rows(Window $window): Collection
    {
        $from = $window->utcFrom()->format('Y-m-d H:i:s');
        $to = $window->utcTo()->format('Y-m-d H:i:s');

        return DB::table('survey_responses')
            ->where(static function ($query) use ($from, $to): void {
                $query->whereBetween('answered_at', [$from, $to])
                    ->orWhereBetween('declined_at', [$from, $to])
                    ->orWhereBetween('sent_at', [$from, $to]);
            })
            ->select(['id', 'survey_id', 'user_id', 'channel', 'sent_at', 'answered_at', 'declined_at', 'answers'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return array{sent: int, answered: int, answered_internal: int, answered_external: int, declined: int}
     */
    private function totals(Collection $rows, Window $window): array
    {
        $out = ['sent' => 0, 'answered' => 0, 'answered_internal' => 0, 'answered_external' => 0, 'declined' => 0];
        foreach ($rows as $row) {
            if ($this->inWindow($row->sent_at, $window)) {
                $out['sent']++;
            }
            if ($this->inWindow($row->answered_at, $window)) {
                $out['answered']++;
                $out[$row->channel === 'external' ? 'answered_external' : 'answered_internal']++;
            }
            if ($this->inWindow($row->declined_at, $window)) {
                $out['declined']++;
            }
        }

        return $out;
    }

    private function inWindow(mixed $timestamp, Window $window): bool
    {
        return is_string($timestamp) && $timestamp !== '' && $window->contains(CarbonImmutable::parse($timestamp, 'UTC'));
    }

    /** Las visitas acreditadas del periodo (día del parque): el denominador de la tasa interna. */
    private function visits(Window $window): int
    {
        return (int) DB::table('customer_visits')->whereBetween('visited_on', [$window->dateFrom(), $window->dateTo()])->count();
    }

    // ─── Por encuesta y por pregunta ─────────────────────────────────────────────────────────────

    /**
     * En este orden: la interna viva, la externa viva y después las que tengan filas en el periodo. Cada una con sus
     * recuentos y, por pregunta, lo que dicen las respuestas CONTESTADAS en el periodo.
     *
     * @param  array<int, array<string, mixed>>  $surveys
     * @param  Collection<int, stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    private function perSurvey(array $surveys, Collection $rows, Window $window): array
    {
        $byId = $rows->groupBy('survey_id');
        $ordered = [];
        foreach (['internal', 'external'] as $kind) {
            foreach ($surveys as $survey) {
                if ($survey['live'] && $survey['kind'] === $kind) {
                    $ordered[$survey['id']] = $survey;
                }
            }
        }
        foreach ($surveys as $survey) {
            if (! isset($ordered[$survey['id']]) && $byId->has($survey['id'])) {
                $ordered[$survey['id']] = $survey;
            }
        }

        $out = [];
        foreach ($ordered as $survey) {
            /** @var Collection<int, stdClass> $own */
            $own = $byId->get($survey['id'], collect());
            $counts = $this->totals($own, $window);
            $answered = $own->filter(fn (stdClass $row): bool => $this->inWindow($row->answered_at, $window))->values();

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
     * @param  list<array{key: string, type: string, label: string, options: array<string, string>}>  $questions
     * @param  Collection<int, stdClass>  $answered
     * @return list<array{key: string, type: string, label: string, n: int, mean: float|null, distribution: list<array{key: string, label: string, n: int}>, texts: list<string>}>
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
            $item = ['key' => $q['key'], 'type' => $q['type'], 'label' => $q['label'], 'n' => count($values), 'mean' => null, 'distribution' => [], 'texts' => []];

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
                    // Los ÚLTIMOS: las filas llegan por id ascendente, así que el final es lo más reciente.
                    $item['texts'] = array_map(static fn (string $t): string => mb_substr($t, 0, QuestionSchema::TEXT_MAX), array_reverse(array_slice($texts, -self::TEXTS_SHOWN)));
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * La primera escala con respuestas de la primera encuesta: la cifra de la tarjeta.
     *
     * @param  list<array<string, mixed>>  $perSurvey
     * @return array{survey: string, question: string, mean: float, n: int}|null
     */
    private function firstScale(array $perSurvey): ?array
    {
        foreach ($perSurvey as $survey) {
            foreach ($survey['questions'] as $q) {
                if ($q['type'] === QuestionSchema::TYPE_SCALE && $q['mean'] !== null) {
                    return ['survey' => $survey['name'], 'question' => $q['label'], 'mean' => (float) $q['mean'], 'n' => (int) $q['n']];
                }
            }
        }

        return null;
    }

    // ─── «Por atender» ───────────────────────────────────────────────────────────────────────────

    /**
     * Las respuestas de los últimos {@see ATTENTION_DAYS} días con alguna escala ≤ {@see ATTENTION_MAX_SCORE}: el
     * día, el canal, la encuesta, la peor nota, el primer texto libre y QUIÉN (id y nombre; el widget decide si lo
     * enseña). Las peores primero, y dentro de la misma nota las más recientes.
     *
     * @param  array<int, array<string, mixed>>  $surveys
     * @return list<array{response_id: int, on: string, channel: string, survey: string, score: int, text: ?string, user_id: ?int, user_name: ?string}>
     */
    private function attention(array $surveys): array
    {
        $since = CarbonImmutable::now('UTC')->subDays(self::ATTENTION_DAYS)->format('Y-m-d H:i:s');
        $rows = DB::table('survey_responses as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.answered_at', '>=', $since)
            ->select(['r.id', 'r.survey_id', 'r.user_id', 'r.channel', 'r.answered_at', 'r.answers', 'u.name as user_name'])
            ->orderByDesc('r.answered_at')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $survey = $surveys[(int) $row->survey_id] ?? null;
            if ($survey === null) {
                continue;
            }
            $answers = is_string($row->answers) ? (array) json_decode($row->answers, true) : [];
            $worst = null;
            $text = null;
            foreach ($survey['questions'] as $q) {
                $value = $answers[$q['key']] ?? null;
                if ($q['type'] === QuestionSchema::TYPE_SCALE && is_int($value) && ($worst === null || $value < $worst)) {
                    $worst = $value;
                }
                if ($q['type'] === QuestionSchema::TYPE_TEXT && $text === null && is_string($value) && trim($value) !== '') {
                    $text = $value;
                }
            }
            if ($worst === null || $worst > self::ATTENTION_MAX_SCORE) {
                continue;
            }
            $out[] = [
                'response_id' => (int) $row->id,
                'on' => (string) $row->answered_at,
                'channel' => (string) $row->channel,
                'survey' => $survey['name'],
                'score' => $worst,
                'text' => $text,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'user_name' => $row->user_name === null ? null : (string) $row->user_name,
            ];
        }

        usort($out, static fn (array $a, array $b): int => [$a['score'], $b['on']] <=> [$b['score'], $a['on']]);

        return array_slice($out, 0, self::ATTENTION_ROWS);
    }

    // ─── La serie y el periodo anterior ──────────────────────────────────────────────────────────

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<array{key: string, answered: int, declined: int, sent: int}>
     */
    private function series(Window $window, Collection $rows): array
    {
        $byKey = [];
        $bump = static function (string $key, string $field) use (&$byKey): void {
            $byKey[$key] ??= ['answered' => 0, 'declined' => 0, 'sent' => 0];
            $byKey[$key][$field]++;
        };
        foreach ($rows as $row) {
            foreach (['answered_at' => 'answered', 'declined_at' => 'declined', 'sent_at' => 'sent'] as $column => $field) {
                if ($this->inWindow($row->{$column}, $window)) {
                    $bump($window->bucketKey(CarbonImmutable::parse((string) $row->{$column}, 'UTC')->setTimezone($window->timezone)), $field);
                }
            }
        }

        $series = [];
        foreach ($window->bucketKeys() as $key) {
            $series[] = ['key' => $key] + ($byKey[$key] ?? ['answered' => 0, 'declined' => 0, 'sent' => 0]);
        }

        return $series;
    }

    /**
     * Las cifras de las tarjetas para el periodo de comparación.
     *
     * @param  array<int, array<string, mixed>>  $surveys
     * @return array<string, int>
     */
    private function totalsOnly(Window $window, array $surveys): array
    {
        $totals = $this->totals($this->rows($window), $window);
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
