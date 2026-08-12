<?php

namespace App\Domain\Platform\Concerns;

/**
 * Traducciones ligeras sin dependencias externas.
 *
 * Declara los campos traducibles en $translatable; se guardan como JSON
 * {es,en,fr} y se leen con tr('campo') en el idioma activo (con fallback).
 */
trait HasTranslations
{
    /**
     * Devuelve el valor de un campo traducible en el idioma activo.
     * Si es una lista (array de items), la devuelve tal cual para iterarla.
     */
    public function tr(string $field, ?string $locale = null): mixed
    {
        $value = $this->getAttribute($field);

        if (! is_array($value)) {
            return $value;
        }

        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale');

        return $value[$locale]
            ?? $value[$fallback]
            ?? (count($value) ? reset($value) : null);
    }
}
