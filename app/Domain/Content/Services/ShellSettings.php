<?php

namespace App\Domain\Content\Services;

use App\Domain\Platform\Models\Setting;

/**
 * **LA CARCASA DE LA COMPRA de esta instalación**: el cajón lateral de siempre o la ISLA del sistema de diseño
 * nuevo (`specs/isla-y-landing-nueva.md` §4.1 y §4.10, `DECISIONES #682`: una segunda carcasa del producto,
 * apagada por defecto, que cada cliente elige).
 *
 * Es igual para todos los visitantes, así que viaja en la mitad CACHEABLE del arranque (`SidebarBoot::shared()`),
 * y quien la usa es el controlador del paquete: con la isla, **la compra abre la isla y la cuenta abre el lateral**
 * hasta que «Mi cuenta» viva también en ella (T5).
 *
 * ⚠️ Helper DEFENSIVO, el patrón de `ThemeSettings` y `CatalogSettings`: cualquier valor que no sea uno de los dos
 * —la fila que falta, un texto corrupto, la BD sin migrar— es el CAJÓN, que es la conducta de siempre. Nunca lanza.
 * ⚠️ La isla se viste con las hojas del sistema nuevo (la de Saltia y la de la isla de la instancia), que cargan
 * las páginas nuevas (T4). Encendida sobre la landing de hoy, sale con sus respaldos neutros: el ajuste lo dice.
 */
class ShellSettings
{
    public const KEY = 'sidebar.shell';

    public const CAJON = 'cajon';

    public const ISLA = 'isla';

    /** @var list<string> */
    public const OPTIONS = [self::CAJON, self::ISLA];

    public static function shell(): string
    {
        $raw = rescue(fn (): mixed => Setting::value(self::KEY), null, false);

        return is_string($raw) && in_array(trim($raw), self::OPTIONS, true) ? trim($raw) : self::CAJON;
    }
}
