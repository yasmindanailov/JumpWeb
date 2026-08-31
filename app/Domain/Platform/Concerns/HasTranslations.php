<?php

namespace App\Domain\Platform\Concerns;

use App\Domain\Platform\Services\Translated;

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

        // La cadena «idioma activo → respaldo → el primero» vive en `Translated`, que es la misma que
        // leen los datos traducibles que viajan copiados fuera de un modelo (el sello de una reserva).
        return Translated::pick($value, $locale);
    }
}
