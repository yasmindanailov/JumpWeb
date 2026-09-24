<?php

namespace App\Filament\Resources\Surveys\Concerns;

use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Services\Surveys\QuestionSchema;
use Illuminate\Validation\ValidationException;

/**
 * Lo que las dos páginas (alta y edición) hacen con el formulario ANTES de escribir (`docs/specs/encuestas.md`
 * §4.1 y §4.4): normalizar las preguntas por `QuestionSchema` —lo que entra en la BD es lo que leen la puerta, el
 * correo y el cuadro— y rechazar encender una segunda encuesta viva de la misma clase (`#740`: una por clase).
 *
 * ⚠️ Con respuestas guardadas los campos bloqueados no viajan (deshabilitados en el formulario): aquí se vuelve a
 * asegurar por si un cuerpo forjado los mandara (ocultar no es autorizar, `SEC-04`).
 */
trait GuardsSurveyForm
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function guard(array $data, ?Survey $record): array
    {
        if ($record !== null && $record->hasResponses()) {
            $data['key'] = $record->key;
            $data['kind'] = $record->kind;
            $data['questions'] = self::keepStructure($record->questionList(), QuestionSchema::normalize($data['questions'] ?? []));
        } else {
            $data['questions'] = QuestionSchema::normalize($data['questions'] ?? []);
        }

        if ($data['questions'] === []) {
            throw ValidationException::withMessages(['data.questions' => __('admin.surveys.errors.no_questions')]);
        }

        $kind = (string) ($data['kind'] ?? $record->kind ?? Survey::KIND_INTERNAL);
        $active = (bool) ($data['active'] ?? false);
        $starts = $data['starts_at'] ?? null;
        $ends = $data['ends_at'] ?? null;
        $wouldRun = $active
            && ($starts === null || now()->gte($starts))
            && ($ends === null || now()->lt($ends));

        if ($wouldRun && Survey::anotherRunning($kind, $record?->id)) {
            throw ValidationException::withMessages(['data.active' => __('admin.surveys.errors.one_live_per_kind')]);
        }

        return $data;
    }

    /**
     * Con respuestas: las claves, los tipos y las claves de las opciones se quedan como estaban; los rótulos y
     * `required` toman lo que vino.
     *
     * @param  list<array<string, mixed>>  $stored
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    private static function keepStructure(array $stored, array $incoming): array
    {
        $byKey = [];
        foreach ($incoming as $question) {
            $byKey[$question['key']] = $question;
        }

        $out = [];
        foreach ($stored as $question) {
            $edited = $byKey[$question['key']] ?? null;
            if ($edited !== null) {
                $question['label'] = $edited['label'];
                $question['required'] = $edited['required'];
                $labels = [];
                foreach ($edited['options'] as $option) {
                    $labels[$option['key']] = $option['label'];
                }
                foreach ($question['options'] as $i => $option) {
                    $question['options'][$i]['label'] = $labels[$option['key']] ?? $option['label'];
                }
            }
            $out[] = $question;
        }

        return $out;
    }

    /** @return array<string, mixed> el rastro de auditoría de una encuesta, sin PII */
    protected function trail(Survey $record): array
    {
        return [
            'key' => $record->key,
            'name' => $record->displayName('es'),
            'kind' => $record->kind,
            'active' => $record->active,
            'questions' => count($record->questionList()),
        ];
    }
}
