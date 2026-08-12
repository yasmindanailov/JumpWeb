<?php

namespace App\Filament\Resources\Offers\Concerns;

/**
 * Normalización compartida por crear/editar una oferta (#270):
 *  - `title` i18n: descarta idiomas vacíos; `null` si todos vacíos.
 *  - defaults de columnas NOT NULL (`is_active`, `position`) — el Toggle de Filament dehidrata
 *    `false` [[feedback_filament_create_defaults]].
 *
 * La imagen la gestiona el `FileUpload` (guarda la ruta relativa dentro del disco): no necesita el
 * saneo anti-traversal de la ruta manual que usan zonas/atracciones/servicios.
 */
trait InteractsWithOfferForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prepareOfferData(array $data): array
    {
        if (array_key_exists('title', $data)) {
            $data['title'] = $this->compactTranslations($data['title'] ?? []);
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
