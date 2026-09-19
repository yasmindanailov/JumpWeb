<?php

namespace Tests\Support;

/**
 * **Leer las hojas del sitio como REGLAS, no como texto.**
 *
 * Tres guardas de arquitectura necesitan lo mismo —recorrer `public/css/*.css` quedándose con
 * los bloques de regla y descendiendo a los `@media`— y hasta ahora cada una se lo escribía. Es
 * la misma respuesta que `#233` dio al selector de idioma: *cuando algo está en dos sitios, la
 * salida no es retirarlo de uno, es que haya UNA definición.*
 *
 * ⚠️ **Dos trampas del instrumento, las dos pagadas ya en este repo:**
 * 1. **Los comentarios se BLANQUEAN conservando la longitud, no se borran.** Al borrarlos, un
 *    `/* … *␘/` pegado al selector de la línea de arriba deja el recorrido sin selector y la
 *    guarda encuentra CERO reglas donde hay una.
 * 2. **`client.css` queda fuera.** No es del producto: es el paquete de una instalación, y existe
 *    precisamente para declarar valores literales. Juzgarlo con las reglas del producto sería
 *    prohibirle aquello para lo que existe — y además hacía MENTIR al gate, porque el recuento de
 *    aserciones cambiaba según si la máquina tenía o no un paquete montado.
 * 3. **`cajon.css` queda fuera, y por un motivo distinto**: no es OTRA hoja, es una COPIA generada
 *    de estas mismas reglas para el cajón empaquetable (`#635`). Contándola, toda guarda de «esto
 *    se declara UNA vez» pasa a ver dos —39 casos en rojo el 2026-09-18, el primero el mínimo
 *    táctil: `[["cajon.css","48px"],["site.css","48px"]]`—. Quien la vigila es `HojaDelCajonTest`,
 *    y lo hace comprobando que sea fiel a su fuente, que es la pregunta correcta para un generado.
 */
trait ReadsSiteStylesheets
{
    /** `client.css` es de una INSTALACIÓN; `cajon.css` es una copia GENERADA. Ver el aviso de arriba. */
    public const HOJAS_QUE_NO_SON_DEL_PRODUCTO = ['client.css', 'cajon.css'];

    /** @var list<array{selector: string, body: string, sheet: string}>|null */
    private ?array $cssRules = null;

    /**
     * Todos los bloques de REGLA de las hojas del producto, con su selector normalizado.
     *
     * @return list<array{selector: string, body: string, sheet: string}>
     */
    protected function siteRules(): array
    {
        if ($this->cssRules !== null) {
            return $this->cssRules;
        }

        $out = [];

        foreach ($this->siteSheets() as $sheet => $css) {
            $this->walkStylesheet($css, (string) $sheet, $out);
        }

        return $this->cssRules = $out;
    }

    /**
     * El texto de cada hoja del producto, con los comentarios blanqueados.
     *
     * @return array<string, string>
     */
    protected function siteSheets(): array
    {
        $out = [];

        foreach (glob(base_path('public/css/*.css')) ?: [] as $path) {
            if (in_array(basename($path), self::HOJAS_QUE_NO_SON_DEL_PRODUCTO, true)) {
                continue;
            }

            $out[basename($path)] = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m) => str_repeat(' ', strlen($m[0])),
                (string) file_get_contents($path),
            );
        }

        return $out;
    }

    /**
     * Recorre una hoja quedándose con los bloques de regla y descendiendo a los at-rules que
     * contienen reglas (`@media`, `@supports`, `@keyframes`). Un at-rule que no las contiene
     * —`@font-face`— es una declaración y no una regla, así que no entra.
     *
     * @param  list<array{selector: string, body: string, sheet: string}>  $out
     */
    private function walkStylesheet(string $css, string $sheet, array &$out): void
    {
        $length = strlen($css);
        $start = 0;

        for ($i = 0; $i < $length; $i++) {
            if ($css[$i] === '}') {
                $start = $i + 1;

                continue;
            }

            if ($css[$i] !== '{') {
                continue;
            }

            $selector = trim((string) preg_replace('/\s+/', ' ', substr($css, $start, $i - $start)));
            $close = $this->matchingBrace($css, $i);
            $body = substr($css, $i + 1, $close - $i - 1);

            if (str_starts_with($selector, '@')) {
                if (str_contains($body, '{')) {
                    $this->walkStylesheet($body, $sheet, $out);
                }
            } elseif ($selector !== '') {
                $out[] = ['selector' => $selector, 'body' => $body, 'sheet' => $sheet];
            }

            $i = $close;
            $start = $i + 1;
        }
    }

    /** Índice del `}` que cierra el `{` que hay en `$open`. */
    private function matchingBrace(string $css, int $open): int
    {
        $depth = 0;
        $length = strlen($css);

        for ($i = $open; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $length - 1;
    }
}
