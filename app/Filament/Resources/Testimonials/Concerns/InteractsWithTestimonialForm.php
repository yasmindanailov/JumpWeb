<?php

namespace App\Filament\Resources\Testimonials\Concerns;

use App\Domain\Content\Models\Testimonial;

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

        // Las PÁGINAS (`#771`): en minúscula, sin espacios de sobra y sin repetir. Vacío = en ninguna página nueva.
        if (array_key_exists('tags', $data)) {
            $tags = array_values(array_unique(array_filter(array_map(
                fn (mixed $t): string => is_scalar($t) ? mb_strtolower(trim((string) $t)) : '',
                (array) ($data['tags'] ?? []),
            ), fn (string $t): bool => $t !== '')));
            $data['tags'] = $tags === [] ? null : $tags;
        }

        // Una escrita en el panel no tiene «original» en ningún sitio: sin enlace.
        if (($data['origin'] ?? null) !== Testimonial::ORIGIN_GOOGLE) {
            $data['source_url'] = null;
        }

        foreach (['author_meta', 'reply', 'source_url'] as $campo) {
            if (array_key_exists($campo, $data)) {
                $data[$campo] = ($v = trim((string) ($data[$campo] ?? ''))) === '' ? null : $v;
            }
        }

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
