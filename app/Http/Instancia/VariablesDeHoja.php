<?php

namespace App\Http\Instancia;

/**
 * **LAS VARIABLES DE UNAS HOJAS, resueltas en el servidor** (`#815`; `fiesta-sistema-nuevo.md` §4.19): lo que valdría cada
 * propiedad personalizada del `:root` si el navegador cargara esas hojas en ese orden. Lo necesita quien PINTA sin
 * navegador —la imagen de la invitación al compartir, dibujada con GD— con los colores de la instancia: los roles de la
 * fiesta encadenan prefijos (`--fiesta-agua-500: var(--aqua-500)` en una hoja, `--aqua-500: #17c8f5` en otra), y la
 * cadena se sigue hasta el valor.
 *
 * ▶ Lo mismo que hace el navegador con el `:root`, y nada más:
 *  · solo los `:root` de PRIMER nivel (`:root { … }`) y los `:where(:root) { … }`; un selector compuesto
 *    (`:root:not([data-theme=…])`) es el modo oscuro o un caso aparte, y no es de este lector;
 *  · `:where(:root)` pesa CERO (así declara sus neutros la hoja del producto, para que la instalación gane siempre): un
 *    `:root` le gana sea cual sea el orden (medido: con solo `:root`, la hoja del producto daba cero variables);
 *  · lo de dentro de una regla `@` (`@media`, `@supports`, `@font-face`, `@keyframes`…) no se lee;
 *  · en orden: lo declarado después gana;
 *  · `var(--x)` y `var(--x, respaldo)` se resuelven hasta {@see VUELTAS} saltos; un ciclo, una variable que no está y no
 *    trae respaldo, o una cadena más larga, se quedan FUERA (sin valor), nunca a medias.
 *
 * ⚠️ No valida qué es un valor: eso lo hace quien lo usa (un color, {@see hex}).
 */
final class VariablesDeHoja
{
    /** Los saltos de `var()` que se siguen como mucho. */
    public const VUELTAS = 6;

    /**
     * @param  list<string>  $hojas  rutas absolutas, en el orden en que se cargarían
     * @return array<string, string> nombre sin los dos guiones => valor resuelto
     */
    public static function de(array $hojas): array
    {
        // Dos pesos, como la especificidad: primero lo de `:where(:root)` (cero) y encima lo de `:root`; en cada uno,
        // lo declarado después gana.
        $neutras = [];
        $crudas = [];
        foreach ($hojas as $hoja) {
            if (! is_file($hoja)) {
                continue;
            }
            $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($hoja));
            // Las reglas `@` con su bloque (y un nivel de bloques dentro: `@media { a { … } }`), fuera.
            $css = (string) preg_replace('/@[a-z-]+[^{;]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/i', '', $css);
            preg_match_all('/(?<![\w-])(:where\(\s*:root\s*\)|:root)\s*\{([^{}]*)\}/', $css, $raices, PREG_SET_ORDER);
            foreach ($raices as [, $selector, $cuerpo]) {
                preg_match_all('/--([a-zA-Z0-9_-]+)\s*:\s*([^;]+);?/', $cuerpo, $m, PREG_SET_ORDER);
                foreach ($m as [, $nombre, $valor]) {
                    if ($selector === ':root') {
                        $crudas[$nombre] = trim($valor);
                    } else {
                        $neutras[$nombre] = trim($valor);
                    }
                }
            }
        }
        $crudas = array_merge($neutras, $crudas);

        $resueltas = [];
        foreach (array_keys($crudas) as $nombre) {
            $valor = self::resolver($nombre, $crudas);
            if ($valor !== null) {
                $resueltas[$nombre] = $valor;
            }
        }

        return $resueltas;
    }

    /**
     * El color `#rgb`/`#rrggbb` normalizado a `#rrggbb` en minúsculas, o `null` si el valor no es eso (un `rgba()`, un
     * `color-mix()`, una palabra): quien pinta con GD necesita los tres canales y nada más.
     */
    public static function hex(?string $valor): ?string
    {
        if ($valor === null || preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($valor), $m) !== 1) {
            return null;
        }
        $h = strtolower($m[1]);

        return '#'.(strlen($h) === 3 ? $h[0].$h[0].$h[1].$h[1].$h[2].$h[2] : $h);
    }

    /** @param  array<string, string>  $crudas */
    private static function resolver(string $nombre, array $crudas): ?string
    {
        $valor = $crudas[$nombre];
        for ($vuelta = 0; $vuelta <= self::VUELTAS; $vuelta++) {
            if (preg_match('/^var\(\s*--([a-zA-Z0-9_-]+)\s*(?:,\s*(.+))?\)$/s', $valor, $v) !== 1) {
                return $valor;
            }
            $siguiente = $crudas[$v[1]] ?? (isset($v[2]) ? trim($v[2]) : null);
            if ($siguiente === null) {
                return null;
            }
            $valor = $siguiente;
        }

        return null; // más saltos que VUELTAS: un ciclo o una cadena que no acaba
    }
}
