<?php

namespace App\Domain\Platform\Services\Surveys;

use App\Domain\Platform\Services\Translated;

/**
 * **Las preguntas de una encuesta, normalizadas** (`docs/specs/encuestas.md` §4.1, T1): el mismo molde que los
 * esquemas de campos del catálogo (`{key, type, required, label{es,en,fr}}`), con la lista de opciones cuando el
 * tipo la pide. Fuente única: el panel guarda lo que esto devuelve, y la puerta, el correo y el cuadro leen
 * lo mismo.
 *
 * Reglas (las de `TicketType::normalizeFieldSchema()`): una fila sin clave o con la clave repetida se descarta; un
 * tipo desconocido cae a `choice`; `required` se castea; `choice` y `multi` sin al menos dos opciones válidas se
 * descartan (no hay nada que elegir); `scale` es de 1 a 5 y `yesno` de dos valores fijos, sin opciones.
 */
final class QuestionSchema
{
    public const TYPE_CHOICE = 'choice';

    public const TYPE_MULTI = 'multi';

    public const TYPE_SCALE = 'scale';

    public const TYPE_YESNO = 'yesno';

    public const TYPE_TEXT = 'text';

    /** @var list<string> */
    public const TYPES = [self::TYPE_CHOICE, self::TYPE_MULTI, self::TYPE_SCALE, self::TYPE_YESNO, self::TYPE_TEXT];

    /** Los tipos que llevan una lista de opciones. */
    public const WITH_OPTIONS = [self::TYPE_CHOICE, self::TYPE_MULTI];

    public const KEY_RE = '/^[a-z][a-z0-9_-]{0,39}$/';

    public const SCALE_MIN = 1;

    public const SCALE_MAX = 5;

    public const TEXT_MAX = 300;

    /**
     * @return list<array{key: string, type: string, required: bool, label: array<string, string>, options: list<array{key: string, label: array<string, string>}>}>
     */
    public static function normalize(mixed $raw): array
    {
        $out = [];
        $seen = [];

        foreach (is_array($raw) ? $raw : [] as $question) {
            if (! is_array($question)) {
                continue;
            }
            $key = is_string($question['key'] ?? null) ? trim($question['key']) : '';
            if ($key === '' || preg_match(self::KEY_RE, $key) !== 1 || isset($seen[$key])) {
                continue;
            }
            $type = in_array($question['type'] ?? null, self::TYPES, true) ? (string) $question['type'] : self::TYPE_CHOICE;
            $options = in_array($type, self::WITH_OPTIONS, true) ? self::options($question['options'] ?? null) : [];
            if (in_array($type, self::WITH_OPTIONS, true) && count($options) < 2) {
                continue;
            }

            $seen[$key] = true;
            $out[] = [
                'key' => $key,
                'type' => $type,
                'required' => (bool) ($question['required'] ?? false),
                'label' => self::labels($question['label'] ?? null, $key),
                'options' => $options,
            ];
        }

        return $out;
    }

    /** El rótulo de una pregunta o de una opción en un idioma, con el español de respaldo. */
    public static function label(array $labels, ?string $locale = null): string
    {
        return (string) (Translated::pick($labels, $locale) ?? '');
    }

    /** ¿Es un valor válido para esta pregunta? (lo que la puerta y la página aceptan). */
    public static function accepts(array $question, mixed $value): bool
    {
        return match ($question['type']) {
            self::TYPE_CHOICE => is_string($value) && in_array($value, array_column($question['options'], 'key'), true),
            self::TYPE_MULTI => is_array($value) && $value !== [] && array_diff($value, array_column($question['options'], 'key')) === [],
            self::TYPE_SCALE => is_int($value) && $value >= self::SCALE_MIN && $value <= self::SCALE_MAX,
            self::TYPE_YESNO => is_bool($value),
            self::TYPE_TEXT => is_string($value) && mb_strlen(trim($value)) > 0 && mb_strlen($value) <= self::TEXT_MAX,
            default => false,
        };
    }

    /**
     * **Lo que llega de un formulario → valores TIPADOS por pregunta** (T2). Un formulario manda cadenas («3»,
     * «1», la clave de una opción) y listas de cadenas; aquí cada una se convierte al tipo de su pregunta y lo
     * que no viene —o viene vacío— se queda FUERA (una pregunta sin contestar no es una respuesta inválida:
     * {@see validate()} decide si era obligatoria). Es fuente única para la puerta y para la página del correo.
     *
     * @param  list<array{key: string, type: string, required: bool, label: array<string, string>, options: list<array{key: string, label: array<string, string>}>}>  $questions
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function fromForm(array $questions, array $raw): array
    {
        $out = [];
        foreach ($questions as $question) {
            $value = $raw[$question['key']] ?? null;
            $typed = match ($question['type']) {
                self::TYPE_CHOICE => is_string($value) && $value !== '' ? $value : null,
                self::TYPE_MULTI => is_array($value) ? array_values(array_filter($value, 'is_string')) : null,
                self::TYPE_SCALE => is_int($value) || (is_string($value) && preg_match('/^\d+$/', $value) === 1) ? (int) $value : null,
                self::TYPE_YESNO => match (true) {
                    $value === true, $value === 1, $value === '1', $value === 'true' => true,
                    $value === false, $value === 0, $value === '0', $value === 'false' => false,
                    default => null,
                },
                self::TYPE_TEXT => is_string($value) && trim($value) !== '' ? trim($value) : null,
                default => null,
            };
            if ($typed !== null && $typed !== []) {
                $out[$question['key']] = $typed;
            }
        }

        return $out;
    }

    /**
     * Las respuestas tipadas contra sus preguntas: `required` para una obligatoria que falta, `invalid` para un
     * valor que la pregunta no acepta. Vacío si todo vale.
     *
     * @param  list<array{key: string, type: string, required: bool, label: array<string, string>, options: list<array{key: string, label: array<string, string>}>}>  $questions
     * @param  array<string, mixed>  $typed
     * @return array<string, string>
     */
    public static function validate(array $questions, array $typed): array
    {
        $errors = [];
        foreach ($questions as $question) {
            if (! array_key_exists($question['key'], $typed)) {
                if ($question['required']) {
                    $errors[$question['key']] = 'required';
                }

                continue;
            }
            if (! self::accepts($question, $typed[$question['key']])) {
                $errors[$question['key']] = 'invalid';
            }
        }

        return $errors;
    }

    /** @return list<array{key: string, label: array<string, string>}> */
    private static function options(mixed $raw): array
    {
        $out = [];
        $seen = [];
        foreach (is_array($raw) ? $raw : [] as $option) {
            if (! is_array($option)) {
                continue;
            }
            $key = is_string($option['key'] ?? null) ? trim($option['key']) : '';
            if ($key === '' || preg_match(self::KEY_RE, $key) !== 1 || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['key' => $key, 'label' => self::labels($option['label'] ?? null, $key)];
        }

        return $out;
    }

    /** @return array<string, string> */
    private static function labels(mixed $raw, string $fallback): array
    {
        if (is_string($raw)) {
            return ['es' => trim($raw) !== '' ? trim($raw) : $fallback];
        }
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $locale => $text) {
            if (is_string($locale) && is_string($text) && trim($text) !== '') {
                $out[$locale] = trim($text);
            }
        }
        if (($out['es'] ?? '') === '') {
            $out['es'] = $fallback;
        }

        return $out;
    }
}
