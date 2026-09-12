<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\BarImage;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Collection;

/**
 * **LO QUE PUBLICA `/bar`** (`DECISIONES #536`, carril de diseño Fase 3 · T3b, artboard `Bar PJP`).
 *
 * Compone en un sitio lo que la página, el menú y el pie necesitan saber del bar. Todo sale del
 * panel: los TEXTOS de `settings` (`bar.*`) y las IMÁGENES de `bar_images`.
 *
 * ❗❗❗ **SIN NOMBRE, EL BAR NO EXISTE PARA LA WEB.** El titular de la página es el nombre del local
 * —lo dejó escrito el propio canvas: *«el nombre real del bar, que ya estaba pendiente de 03 y aquí
 * es el titular»*— y el producto no puede inventárselo. Sin él: la ruta da **404**, el destino no
 * sale ni en el menú ni en el pie, y la tarjeta de la portada no se pinta. ▶ *Falla hacia invisible*,
 * que es como fallan el resto de las piezas del armazón.
 *
 * ⚠️ **`isPublished()` no consulta `bar_images`, solo `settings`** — y eso importa porque lo llaman
 * el menú y el pie, o sea **las doce vistas**. `Setting::value` memoiza la tabla entera, así que el
 * coste es cero consultas nuevas por petición.
 */
class BarPage
{
    /** ¿Se publica el bar? Manda el NOMBRE: sin él no hay titular, y sin titular no hay página. */
    public static function isPublished(): bool
    {
        return self::name() !== null;
    }

    /**
     * El nombre del bar en el idioma activo, con respaldo al español.
     *
     * ⚠️ **No hay valor por defecto a propósito.** «El bar» es el marcador que el canvas usó
     * mientras esperaba el nombre real; escribirlo en el producto lo convertiría en el nombre del
     * local de cualquier instalación que no lo rellene.
     */
    public static function name(): ?string
    {
        return self::text('bar.name');
    }

    /** La frase de presentación. Opcional: sin ella la cabecera va sin entradilla. */
    public static function lede(): ?string
    {
        return self::text('bar.lede');
    }

    /** El pie de la foto del local. Opcional: la foto sale sin pie. */
    public static function photoCaption(): ?string
    {
        return self::text('bar.photo_caption');
    }

    /**
     * ¿Se puede entrar solo al bar? `'yes'` · `'no'` · `null` (sin decidir).
     *
     * ⚠️ **`null` no es «no»**: es «nadie lo ha decidido», y entonces la página no dice nada. Un
     * booleano habría afirmado «no se puede entrar» en toda instalación recién montada.
     */
    public static function freeEntry(): ?string
    {
        $v = trim((string) Setting::value('bar.free_entry', ''));

        return in_array($v, ['yes', 'no'], true) ? $v : null;
    }

    /**
     * Las caras de la CARTA, activas y en orden.
     *
     * @return Collection<int, BarImage>
     */
    public static function menu(): Collection
    {
        return BarImage::query()
            ->ofKind(BarImage::KIND_MENU)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * La FOTO del local, o `null`.
     *
     * ⚠️ **La primera activa por orden manda.** No se impone unicidad en el esquema porque eso
     * obligaría a borrar la foto vieja ANTES de subir la nueva —justo cuando un parque se queda sin
     * foto—; la resolución es la misma que `#479` le dio a dos tarifas destacadas.
     */
    public static function venuePhoto(): ?BarImage
    {
        return BarImage::query()
            ->ofKind(BarImage::KIND_VENUE)
            ->active()
            ->ordered()
            ->first();
    }

    /** Un texto de `settings` por idioma, con respaldo al español y `null` si está vacío. */
    private static function text(string $key): ?string
    {
        $locale = app()->getLocale();

        foreach ([$locale, 'es'] as $loc) {
            $value = trim((string) Setting::value($key.'.'.$loc, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
