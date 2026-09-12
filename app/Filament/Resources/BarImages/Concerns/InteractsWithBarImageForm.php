<?php

namespace App\Filament\Resources\BarImages\Concerns;

use App\Domain\Content\Models\BarImage;

/**
 * Normalización compartida por crear/editar una imagen del bar (`#536`), molde de `#270`:
 *  - `alt` i18n: descarta idiomas vacíos; `null` si todos vacíos.
 *  - defaults de columnas NOT NULL (`is_active`, `position`, `kind`) — el Toggle de Filament
 *    dehidrata `false` y un `position` vacío llega como `''`.
 *
 * ⚠️ **`kind` se sanea contra la lista del modelo.** El `Select` ya la acota, pero el valor llega
 * del navegador y un `wire:model` se puede empujar con cualquier cosa: un tipo desconocido dejaría
 * una fila que la página nunca pinta y que nadie entiende al mirar la tabla.
 */
trait InteractsWithBarImageForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prepareBarImageData(array $data): array
    {
        if (array_key_exists('alt', $data)) {
            $data['alt'] = $this->compactTranslations($data['alt'] ?? []);
        }

        $kind = (string) ($data['kind'] ?? BarImage::KIND_MENU);
        $data['kind'] = in_array($kind, BarImage::KINDS, true) ? $kind : BarImage::KIND_MENU;

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
