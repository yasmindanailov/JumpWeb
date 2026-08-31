<?php

namespace App\Domain\Platform\Services;

/**
 * Lectura de un valor traducible que viaja FUERA de un modelo.
 *
 * `HasTranslations::tr()` resuelve el idioma de un campo de un modelo. Pero un dato traducible
 * también viaja COPIADO: el nombre de un producto guardado en el sello de una reserva
 * (`specs/cumple-mixto.md` §21.3), un rótulo escrito en el `context` de un ajuste. Ahí no hay
 * modelo al que preguntarle, y cada sitio reescribía la cadena «idioma activo → idioma de
 * respaldo → el primero que haya» a su manera. Esta es la única, y `tr()` la usa también.
 */
final class Translated
{
    /**
     * @param  array<string, mixed>  $value  el campo traducible entero (`{es, en, fr}`)
     */
    public static function pick(array $value, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $fallback = (string) config('app.fallback_locale');

        return $value[$locale]
            ?? $value[$fallback]
            ?? (count($value) ? reset($value) : null);
    }
}
