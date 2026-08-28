<?php

namespace App\Filament\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Rótulo de un recurso dentro del buscador del panel (#224).
 *
 * Trece recursos entran en el buscador y todos necesitan lo mismo: decir con qué texto se
 * identifica cada resultado. Casi todos guardan ese texto como **JSON traducible**
 * (`{"es": "...", "en": "..."}`, trait `HasTranslations`), así que leerlo con
 * `$record->name` devolvería un array y pintaría «Array» en la lista de resultados.
 *
 * Aquí vive esa decisión UNA vez: si el valor es un array, se traduce con `tr()` —que ya
 * resuelve idioma activo → fallback → primero disponible—; si no, se usa tal cual.
 *
 * ⚠️ **Los ATRIBUTOS por los que se busca son otra cosa** y siguen declarándose en cada
 * recurso (`getGloballySearchableAttributes()`), porque no siempre coinciden con el rótulo:
 * un pedido se identifica por su código pero se busca también por el nombre y el correo de
 * su cliente. La consulta sobre una columna JSON usa la sintaxis de casa —`name->es`—, la
 * misma que ya emplean las tablas del panel (`CatalogTable`), para que buscar en la lista y
 * buscar arriba den lo mismo.
 */
trait ProvidesGlobalSearch
{
    /**
     * Atributo que identifica al registro en los resultados.
     *
     * ⚠️ Es un MÉTODO y no una propiedad a propósito: desde PHP 8.4, una clase que redeclara
     * una propiedad de un trait **con otro valor inicial** es un error fatal de composición
     * («define the same property … the definition differs»), y aquí cada recurso tiene que
     * decir el suyo. Los métodos sí se sobrescriben sin ceremonia.
     */
    protected static function globalSearchTitleAttribute(): string
    {
        return 'name';
    }

    /**
     * La restricción de búsqueda, en SQL **portable** y sin distinguir mayúsculas.
     *
     * ⚠️⚠️ **Dos trampas medidas, y la segunda casi se cuela.**
     *
     * 1. **MySQL extrae un valor JSON con colación `utf8mb4_bin`**, así que un `LIKE` sobre
     *    `name->es` distingue mayúsculas: buscar «jump» daba **0** filas y «Jump», **5**. Era un
     *    defecto vivo en las tablas del panel (`CatalogTable`, `RateTypeTable`) desde que se
     *    escribieron, y no lo veía ningún test.
     * 2. **La palanca de Filament para eso NO es portable.** `$isGlobalSearchForcedCaseInsensitive`
     *    genera `lower(json_extract(...))` en MySQL —correcto— pero en SQLite emite
     *    `lower(tabla.name->es)` en crudo, que SQLite lee como una columna llamada `es` y
     *    revienta la consulta. **La suite corre en SQLite y producción es MySQL**, así que esa
     *    bandera dejaba el panel bien y la suite roja.
     *
     * La salida es pedirle la columna a la GRAMÁTICA (`wrap()`), que sí sabe traducir `name->es`
     * a lo que toca en cada motor, y envolverla en `LOWER()` a mano.
     *
     * ⚠️ **Y aun así, un test en SQLite NO demuestra la conducta en MySQL**: la sensibilidad a
     * mayúsculas de la extracción JSON es propia de MySQL. La comprobación de que «jump»
     * encuentra «Jump · 1 hora» se hizo **contra la base MySQL local**, además del test.
     *
     * A diferencia de Filament, esto NO parte la búsqueda en palabras: aquí cada recurso busca
     * en uno o dos atributos suyos y casar la frase entera es más predecible.
     */
    protected static function applyGlobalSearchAttributeConstraints(Builder $query, string $search): void
    {
        $needle = '%'.mb_strtolower(trim($search)).'%';
        $grammar = $query->getQuery()->getGrammar();

        $query->where(function (Builder $query) use ($grammar, $needle): void {
            foreach (static::getGloballySearchableAttributes() as $attribute) {
                $query->orWhereRaw('LOWER('.$grammar->wrap($attribute).') LIKE ?', [$needle]);
            }
        });
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $attribute = static::globalSearchTitleAttribute();
        $value = $record->getAttribute($attribute);

        if (is_array($value) && method_exists($record, 'tr')) {
            $value = $record->tr($attribute);
        }

        // Una FECHA es la identidad de algunos registros —un día especial ES su día— y como
        // Eloquent la devuelve casteada a Carbon, `is_scalar()` la descartaba y el resultado
        // salía titulado «—». Lo cazó el sondeo, no un test: en la caja de búsqueda un título
        // vacío no rompe nada, solo deja una fila que no dice qué es.
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('d/m/Y');
        }

        $value = is_scalar($value) ? (string) $value : '';

        return $value !== '' ? $value : '—';
    }
}
