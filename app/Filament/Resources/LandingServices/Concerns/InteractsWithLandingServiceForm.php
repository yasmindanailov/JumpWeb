<?php

namespace App\Filament\Resources\LandingServices\Concerns;

/**
 * Normalización compartida por crear/editar un servicio de la landing (#256):
 *  - Textos i18n (`title`/`accent_word`/`zone_label`/`body`): descarta idiomas vacíos; `null` si
 *    todos vacíos. (`nav_subtitle` salió del formulario con `#521` y ya no llega aquí.)
 *  - `specs`: por idioma, descarta filas vacías (sin label ni value); `null` si todas vacías.
 *  - `image`: ruta recortada con saneo anti-traversal; vacío → `null`.
 *  - `ticket_type_id`: entero o `null` (servicio de solo-contacto).
 *  - defaults de columnas NOT NULL (is_active, position) — el Toggle de Filament
 *    dehidrata `false` [[feedback_filament_create_defaults]].
 *
 * ⚠️⚠️ `show_in_nav` YA NO se toca aquí, y no es un olvido (`#521`): el formulario dejó de pintar su
 * interruptor, así que forzarlo a `true` cuando no llega **reescribiría el valor de todo servicio que
 * se editara** sin que nadie lo hubiera pedido. Al crear, lo pone el `default(true)` de la columna.
 */
trait InteractsWithLandingServiceForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prepareLandingServiceData(array $data): array
    {
        foreach (['title', 'accent_word', 'zone_label', 'body'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->compactTranslations($data[$field] ?? []);
            }
        }

        if (array_key_exists('specs', $data)) {
            $data['specs'] = $this->compactSpecs($data['specs'] ?? []);
        }

        if (array_key_exists('image', $data)) {
            $image = trim((string) ($data['image'] ?? ''));
            // Saneo defensivo (igual que atracciones/zonas): ruta con `..`/`\` (traversal) → null.
            $data['image'] = ($image === '' || str_contains($image, '..') || str_contains($image, '\\')) ? null : $image;
        }

        if (array_key_exists('slug', $data) && $data['slug'] !== null) {
            $data['slug'] = trim((string) $data['slug']);
        }

        // Pack vinculado (#256): entero o null (servicio de solo-contacto).
        $data['ticket_type_id'] = ! empty($data['ticket_type_id']) ? (int) $data['ticket_type_id'] : null;

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

    /**
     * `specs` i18n = {locale: [{label,value}, …]}. Por idioma descarta las filas totalmente vacías
     * y reindexa; descarta el idioma si queda sin filas; null si no queda ninguno.
     *
     * @return array<string, list<array{label:string,value:string}>>|null
     */
    private function compactSpecs(mixed $specs): ?array
    {
        if (! is_array($specs)) {
            return null;
        }

        $clean = [];
        foreach ($specs as $locale => $rows) {
            if (! is_array($rows)) {
                continue;
            }

            $rowsClean = [];
            foreach ($rows as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                if ($label !== '' || $value !== '') {
                    $rowsClean[] = ['label' => $label, 'value' => $value];
                }
            }

            if ($rowsClean !== []) {
                $clean[$locale] = $rowsClean;
            }
        }

        return $clean === [] ? null : $clean;
    }
}
