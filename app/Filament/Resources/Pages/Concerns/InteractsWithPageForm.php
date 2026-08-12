<?php

namespace App\Filament\Resources\Pages\Concerns;

use App\Models\Page;

/**
 * Fase 7.9 (iter. 2) — Remapeo i18n del cuerpo de una página legal entre el form y la columna:
 *
 *  - **Cargar (fill):** `body` ({es:[{h,p}], …}) → un Repeater plano por idioma `body_{locale}`
 *    (Filament no liga bien un Repeater a un statePath con punto anidado por idioma; el patrón
 *    del catálogo con `features_{locale}` hace lo mismo).
 *  - **Guardar (save):** los tres repeaters → `body` recompuesta, descartando secciones vacías
 *    (h y p en blanco) e idiomas sin secciones; `null` si no queda nada (columna nullable).
 *  - **Título** i18n: descarta idiomas vacíos. **`is_active`** con default explícito (el Toggle
 *    de Filament dehidrata `false`). El `slug` no se dehidrata (inmutable).
 */
trait InteractsWithPageForm
{
    /** Idiomas soportados por el contenido de la web pública. */
    private const LOCALES = ['es', 'en', 'fr'];

    /**
     * Reparte `body` ({locale:[{h,p}]}) en los repeaters planos `body_{locale}` para el form.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function splitBodyForForm(array $data): array
    {
        $body = is_array($data['body'] ?? null) ? $data['body'] : [];

        foreach (self::LOCALES as $locale) {
            $sections = is_array($body[$locale] ?? null) ? $body[$locale] : [];
            // Solo las claves que el Repeater conoce (h, p), por si hubiera datos legacy extra.
            $data["body_{$locale}"] = array_values(array_map(
                fn ($s): array => ['h' => (string) ($s['h'] ?? ''), 'p' => (string) ($s['p'] ?? '')],
                array_filter($sections, 'is_array'),
            ));
        }

        // La estructura anidada original queda sustituida por los repeaters planos (claridad,
        // mismo patrón que el catálogo con `features_{locale}`); el form no tiene campo `body`.
        unset($data['body']);

        return $data;
    }

    /**
     * Recompone `body` desde los repeaters y normaliza el resto antes de persistir.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function preparePageData(array $data): array
    {
        if (array_key_exists('title', $data)) {
            $data['title'] = $this->compactTranslations($data['title'] ?? []);
        }

        $body = [];
        foreach (self::LOCALES as $locale) {
            $sections = [];
            foreach ((array) ($data["body_{$locale}"] ?? []) as $section) {
                $h = trim((string) ($section['h'] ?? ''));
                $p = trim((string) ($section['p'] ?? ''));
                if ($h === '' && $p === '') {
                    continue; // sección vacía: se descarta
                }
                $sections[] = ['h' => $h, 'p' => $p];
            }
            unset($data["body_{$locale}"]);
            if ($sections !== []) {
                $body[$locale] = $sections;
            }
        }
        $data['body'] = $body === [] ? null : $body;

        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        // Los slugs legales protegidos no pueden quedar inactivos (su enlace es obligatorio desde el
        // pie/sitemap/registro/banner → un 404 indexable). Fuente única: `Page::PROTECTED_ACTIVE_SLUGS`
        // (auditoría Fase 1 · Sistema 6 · W5, antes solo `cookies`).
        if (in_array($this->record?->slug, Page::PROTECTED_ACTIVE_SLUGS, true)) {
            $data['is_active'] = true;
        }

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
