<?php

namespace App\Filament\Resources\Attractions\Concerns;

/**
 * Fase 7.9 (iter. 1) — Normalización compartida por crear/editar una atracción:
 *  - Textos i18n (`name`/`description`/`age`/`badge`): descarta idiomas vacíos; `null` si todos
 *    vacíos (un `badge` sin valor = sin badge, igual que el dato sembrado).
 *  - `image`: ruta recortada; vacío → `null` (la columna es nullable).
 *  - `zone_id` a entero; defaults de columnas NOT NULL (is_active, position) — el Toggle de
 *    Filament dehidrata `false` [[feedback_filament_create_defaults]].
 */
trait InteractsWithAttractionForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prepareAttractionData(array $data): array
    {
        foreach (['name', 'description', 'age', 'badge'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->compactTranslations($data[$field] ?? []);
            }
        }

        if (array_key_exists('image', $data)) {
            $image = trim((string) ($data['image'] ?? ''));
            // Saneo defensivo (revisión #231): ruta con `..`/`\` (traversal) → null. Coherente con
            // el saneo de la imagen de zona; la ruta es relativa a public/ y va a un `<img src>`.
            $data['image'] = ($image === '' || str_contains($image, '..') || str_contains($image, '\\')) ? null : $image;
        }

        if (array_key_exists('zone_id', $data) && $data['zone_id'] !== null) {
            $data['zone_id'] = (int) $data['zone_id'];
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['is_special'] = (bool) ($data['is_special'] ?? false);
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
