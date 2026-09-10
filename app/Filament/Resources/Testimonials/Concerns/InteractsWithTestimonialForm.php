<?php

namespace App\Filament\Resources\Testimonials\Concerns;

/**
 * Normalización compartida por crear/editar una opinión (`#490`), molde de `InteractsWithFaqForm`:
 * descarta idiomas vacíos (no persistir `''`, que se leería como traducción válida) y fija los
 * defaults de las columnas NOT NULL — el Toggle de Filament dehidrata `false`.
 */
trait InteractsWithTestimonialForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function prepareTestimonialData(array $data): array
    {
        if (array_key_exists('text', $data)) {
            $data['text'] = $this->compactTranslations($data['text'] ?? []);
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['position'] = (int) ($data['position'] ?? 0);

        return $data;
    }

    /** @return array<string,string>|null */
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
