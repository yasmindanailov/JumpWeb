<?php

namespace App\Filament\Resources\ParkRules\Concerns;

/**
 * Fase 7.9 (iter. 1) — Normalización compartida por crear/editar una norma:
 *  - Textos i18n (`name`/`description`): descarta idiomas vacíos; `null` si todos vacíos.
 *  - Defaults de columnas NOT NULL (is_active, position) — el Toggle de Filament dehidrata
 *    `false` [[feedback_filament_create_defaults]].
 */
trait InteractsWithParkRuleForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prepareParkRuleData(array $data): array
    {
        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->compactTranslations($data[$field] ?? []);
            }
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['position'] = (int) ($data['position'] ?? 0);

        return $data;
    }

    /**
     * @return array<string,string>|null
     */
    private function compactTranslations(mixed $values): ?array
    {
        if (! is_array($values)) {
            return null;
        }

        $clean = [];
        foreach ($values as $locale => $text) {
            $text = trim((string) $text);
            if ($text !== '') {
                $clean[$locale] = $text;
            }
        }

        return $clean === [] ? null : $clean;
    }
}
