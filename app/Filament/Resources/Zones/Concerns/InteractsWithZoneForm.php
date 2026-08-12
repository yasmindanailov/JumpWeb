<?php

namespace App\Filament\Resources\Zones\Concerns;

/**
 * Fase 7.9 (adelanto) — Normalización compartida por crear/editar una zona:
 *  - Textos i18n: descarta idiomas vacíos; `null` si quedan todos vacíos (no persistir '').
 *  - Cupo de packs por zona: vacío → `null` (usa el ajuste global); el tri-estado de
 *    `prep_blocks_cupo` ('' / '1' / '0') → null / true / false.
 *  - Defaults de columnas con sentido (is_active, position).
 */
trait InteractsWithZoneForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prepareZoneData(array $data): array
    {
        foreach (['name', 'subtitle', 'description', 'age_label', 'age_range'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $data[$field] = $this->compactTranslations($data[$field] ?? []);
        }

        // Imagen de zona: ruta recortada; vacío → null (la columna es nullable → card sin foto).
        // Saneo defensivo (revisión #231): una ruta con `..` o `\` (traversal) → null; la ruta es
        // una URL relativa a public/ que va a un `<img src>` (no inclusión de fichero), pero no
        // tiene sentido permitir traversal y deja la columna limpia.
        if (array_key_exists('image', $data)) {
            $image = trim((string) ($data['image'] ?? ''));
            $data['image'] = ($image === '' || str_contains($image, '..') || str_contains($image, '\\')) ? null : $image;
        }

        // Cupo por zona: vacío = null (usa el global). 0 es un override válido ("sin tope").
        foreach (['max_per_slot', 'max_guests_per_slot'] as $field) {
            $value = $data[$field] ?? null;
            $data[$field] = ($value === null || $value === '') ? null : (int) $value;
        }

        // Tri-estado de prep_blocks_cupo: '' → null (global), '1' → true, '0' → false.
        $prep = $data['prep_blocks_cupo'] ?? '';
        $data['prep_blocks_cupo'] = ($prep === '' || $prep === null) ? null : (bool) (int) $prep;

        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['show_in_landing'] = (bool) ($data['show_in_landing'] ?? true);
        $data['position'] = (int) ($data['position'] ?? 0);

        return $data;
    }

    /**
     * @param  array<string,mixed>  $values
     * @return array<string,string>|null
     */
    private function compactTranslations(array $values): ?array
    {
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
