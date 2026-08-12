<?php

namespace App\Filament\Resources\Faqs\Concerns;

/**
 * Fase 7.9 (iter. 1) — Normalización compartida por crear/editar una FAQ:
 *  - Textos i18n (`question`/`answer`): descarta idiomas vacíos; `null` si quedan todos vacíos
 *    (no persistir '' que se mostraría como traducción válida).
 *  - Defaults de columnas NOT NULL (is_active, position) — el Toggle de Filament dehidrata
 *    `false`, así que se fija el default explícito [[feedback_filament_create_defaults]].
 */
trait InteractsWithFaqForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prepareFaqData(array $data): array
    {
        foreach (['question', 'answer'] as $field) {
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
