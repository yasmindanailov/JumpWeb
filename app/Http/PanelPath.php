<?php

namespace App\Http;

use Illuminate\Http\Request;

/**
 * **La dirección del panel, en UN sitio** (`docs/specs/panel-a-salvo.md` §4.2, `DECISIONES #850`).
 *
 * Sale de `config('panel.path')` (`PANEL_PATH`; `admin` por defecto). La leen Filament (`AdminPanelProvider`), las rutas del
 * personal de `routes/web.php`, las vistas que enlazan al panel y los middleware que lo excluyen por ruta (el mantenimiento,
 * los clics de los correos y el visitante de la analítica). Escribir `admin` a mano en cualquiera de ellos dejaría esa
 * pieza en la dirección de siempre cuando el panel ya no está ahí.
 */
final class PanelPath
{
    public static function path(): string
    {
        return (string) config('panel.path', 'admin');
    }

    /** ¿La petición es del panel (su raíz o cualquier cosa debajo)? */
    public static function matches(Request $request): bool
    {
        $path = self::path();

        return $request->is($path, $path.'/*');
    }

    /** La URL absoluta de algo del panel: `url()` sin sufijo es su raíz. */
    public static function url(string $suffix = ''): string
    {
        $suffix = ltrim($suffix, '/');

        return url('/'.self::path().($suffix === '' ? '' : '/'.$suffix));
    }
}
