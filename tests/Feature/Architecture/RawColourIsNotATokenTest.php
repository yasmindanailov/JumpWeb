<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **UN COLOR QUE YA TIENE TOKEN NO SE VUELVE A ESCRIBIR A MANO** (`DECISIONES #143`).
 *
 * ⚠️⚠️ Nació de una MEDIDA, no de una simetría. `specs/landing-white-label.md` §4.5.1 hablaba de
 * «76 colores en crudo» y planteaba la tarea como «decidir cuáles suben a token». Al medirlo con un
 * instrumento que ve también los `rgba()` y los valores MULTILÍNEA salieron **234 ocurrencias**, y el
 * reparto era otro: **144 de ellas no necesitaban ningún token nuevo** —eran tokens que ya existían,
 * reescritos a mano—. 114 eran `--fg` escrito como `rgba(20,19,15,α)` con **28 alfas distintas**.
 *
 * ▶ Traducido a lo que le pasa a un cliente: cambiaba `--fg` desde su paquete de tema y **113 sombras
 * y bordes seguían siendo del primer cliente**. Nada fallaba, nada avisaba, y por eso duró.
 *
 * ▶ Y el caso que enseña por qué esta guarda mira VALORES y no nombres: `#139` retiró `--jump-*` y
 * `--kids-*` del `:root` y `ZoneAccentIsNotAClassNameTest` vigila que no vuelvan **por su nombre**.
 * Pero en `.hero__stage-placeholder` los mismos dos colores estaban escritos en decimal
 * —`rgba(255,91,34,.22)` y `rgba(198,255,58,.16)`— dentro de un `background` de tres líneas, y
 * sobrevivieron a las dos cosas: al barrido de `#139` y al inventario que lo midió con un `grep` de
 * `#hex` línea a línea.
 *
 * Prohíbe el MECANISMO, no persigue el síntoma: hermana de `ZoneAccentIsNotAClassNameTest` y
 * `LedgerSingleSourceTest`.
 */
class RawColourIsNotATokenTest extends TestCase
{
    /** Las hojas que sirve el layout público. Si nace una nueva, entra aquí sola (`glob`). */
    private const SHEETS = 'public/css/*.css';

    /**
     * Tokens cuyo valor NO se puede volver a teclear. **No se listan sus colores aquí**: se leen del
     * `:root` en tiempo de test, para que cambiar un valor por defecto no obligue a tocar esta guarda
     * —y, sobre todo, para que no pueda quedarse describiendo una paleta que el CSS ya no tiene—.
     *
     * @var list<string>
     */
    private const BASE_TOKENS = [
        '--fg', '--fg-mute', '--bg', '--bg-soft', '--bg-card', '--zone-1', '--zone-2',
    ];

    /**
     * Colores de MARCA del primer cliente que ya no tienen token equivalente: teclearlos es heredar
     * su paleta aunque ningún token la nombre. `#C6FF3A` es la lima de la zona «Kids».
     *
     * @var array<string,string>
     */
    private const FIRST_CLIENT_VALUES = [
        'C6FF3A' => 'la lima de la zona «Kids» del primer cliente — usa `--zone-2`',
    ];

    /**
     * Sitios donde un literal está PERMITIDO, cada uno con su motivo. **La lista solo encoge**: una
     * entrada nueva es una instalación que pierde ese color, y hay que decir por qué se acepta.
     *
     * La clave es una subcadena del selector; el valor, el motivo que se imprime al fallar.
     *
     * @var array<string,string>
     */
    private const ALLOWED_SELECTORS = [
        // ⚠️ `.invite-card` y `.invite-field` vivían aquí (los literales del objetivo de captura
        // de html2canvas) y SE RETIRARON (T9): el bloque `.invite-*` era el editor ANTERIOR a
        // `bd-editor` y llevaba muerto desde aquel rediseño. La lista queda VACÍA — encogió hasta
        // el final, que es exactamente lo que prometía.
    ];

    /**
     * Propiedades donde un color no es un color. `mask`/`mask-image` usan el negro como MÁSCARA de un
     * degradado: sustituirlo por un token de marca teñiría un recorte que no se ve.
     *
     * @var list<string>
     */
    private const ALLOWED_PROPERTIES = [
        'mask', '-webkit-mask', 'mask-image', '-webkit-mask-image',
    ];

    /** @var ?list<array{file: string, line: int, selector: string, property: string, value: string}> */
    private ?array $declarations = null;

    /**
     * **La guarda de la guarda**: el escaneo ve de verdad el corpus.
     *
     * Sin esto, un parser roto deja el test verde para siempre sin mirar nada — el modo de fallo de
     * toda comprobación por `grep`, y uno que este repo ya pagó dos veces. Se aseveran las dos mitades
     * que pueden romperse por separado: que hay declaraciones, y que dentro de ellas se ven colores.
     */
    public function test_the_scan_actually_sees_the_stylesheets(): void
    {
        $declarations = $this->declarations();

        $this->assertGreaterThan(
            2000, count($declarations),
            'el escaneo ve muy pocas declaraciones: ¿ha cambiado el CSS, o se ha roto el parser?',
        );

        $withColour = array_filter($declarations, fn (array $d): bool => $this->literalsIn($d['value']) !== []);

        $this->assertGreaterThan(
            30, count($withColour),
            'el escaneo no encuentra literales de color. Con 0 hallazgos esta guarda estaría verde '.
            'sin mirar nada — que es peor que no tenerla.',
        );

        $this->assertNotEmpty(
            $this->rootValues(),
            'no se ha podido leer ni un token base del `:root`: sin la tabla de valores, la '.
            'comparación de abajo no compara nada y el test pasa siempre.',
        );
    }

    /**
     * **La guarda de la guarda, 2ª mitad**: el detector caza sus propios ejemplos.
     *
     * ⚠️ Incluye el caso que este trabajo destapó: un `rgba()` dentro de un `radial-gradient()` y en
     * la SEGUNDA línea de un valor de tres. El instrumento con el que se midió el inventario la
     * primera vez no lo veía, y por eso el inventario parecía completo sin serlo.
     */
    public function test_the_detector_catches_its_own_examples(): void
    {
        $samples = [
            'rgba(20, 19, 15, 0.18)' => ['20,19,15,0.18'],
            '#14130F' => ['20,19,15,1'],
            '#fff' => ['255,255,255,1'],
            "radial-gradient(ellipse at 22% 38%, rgba(255, 91, 34, 0.22) 0%, transparent 55%),\n".
            '    radial-gradient(ellipse at 78% 78%, rgba(198, 255, 58, 0.16) 0%, transparent 55%)' => ['255,91,34,0.22', '198,255,58,0.16'],
        ];

        foreach ($samples as $value => $expected) {
            $found = array_map(
                fn (array $l): string => implode(',', $l['rgba']),
                $this->literalsIn($value),
            );

            $this->assertSame(
                $expected, $found,
                "el detector ha dejado de ver sus propios ejemplos en «{$value}»: la guarda es decorativa",
            );
        }

        // Y al revés: lo que NO es un literal no debe contarse, o la guarda ahoga en falsos positivos.
        foreach ([
            'var(--fg)',
            'color-mix(in srgb, var(--fg) 18%, transparent)',
            'rgba(var(--rgb-fg), 0.2)',
            '#sidecart-spa',
        ] as $notALiteral) {
            $this->assertSame(
                [], $this->literalsIn($notALiteral),
                "el detector cuenta «{$notALiteral}» como literal de color, y no lo es",
            );
        }
    }

    /**
     * **Ningún literal repite un color que ya tiene token.**
     *
     * La comparación es por VALOR RGB, no por texto: `#14130F`, `rgba(20,19,15,1)` y `#14130Fff` son
     * el mismo color escrito de tres formas, y las tres atan la instalación al primer cliente.
     */
    public function test_no_literal_repeats_a_colour_that_already_has_a_token(): void
    {
        $root = $this->rootValues();
        $findings = [];

        foreach ($this->declarations() as $d) {
            if ($this->isAllowed($d)) {
                continue;
            }

            foreach ($this->literalsIn($d['value']) as $literal) {
                $rgb = array_slice($literal['rgba'], 0, 3);
                $token = array_search($rgb, $root, true);

                if ($token === false) {
                    continue;
                }

                $alpha = $literal['rgba'][3];
                $fix = $alpha >= 1
                    ? "var({$token})"
                    : sprintf('color-mix(in srgb, var(%s) %s%%, transparent)', $token, rtrim(rtrim(sprintf('%.4F', $alpha * 100), '0'), '.'));

                $findings[] = sprintf(
                    '%s:%d  %s { %s: … %s … }  →  %s',
                    $d['file'], $d['line'], $this->shorten($d['selector']), $d['property'], $literal['text'], $fix,
                );
            }
        }

        $this->assertSame([], $findings, implode("\n", array_merge(
            ['Un color que YA tiene token vuelve a estar escrito a mano:'],
            array_map(fn (string $f): string => '  · '.$f, $findings),
            ['',
                '▶ Un cliente que cambie ese token desde su paquete de tema NO cambiará esto,',
                '  y nada fallará: se queda con el color del primer cliente, en silencio.',
                '▶ Opaco → `var(--token)`.  Con alfa → `color-mix(in srgb, var(--token) N%, transparent)`,',
                '  que premultiplica y rinde EXACTAMENTE el mismo color (la conversión no mueve un píxel).',
                '▶ Si de verdad tiene que ser un literal, decláralo en `ALLOWED_SELECTORS` con su motivo.'],
        )));
    }

    /**
     * **La paleta del primer cliente tampoco sobrevive como VALOR.**
     *
     * `ZoneAccentIsNotAClassNameTest` vigila que `--jump-*`/`--kids-*` no vuelvan por su NOMBRE. Esta
     * mitad cubre el hueco por el que se colaron dos: escritos en decimal, dentro de un degradado.
     */
    public function test_the_first_clients_palette_does_not_survive_as_a_value(): void
    {
        $findings = [];

        foreach ($this->declarations() as $d) {
            if ($this->isAllowed($d)) {
                continue;
            }

            foreach ($this->literalsIn($d['value']) as $literal) {
                $hex = strtoupper(sprintf('%02X%02X%02X', ...array_slice($literal['rgba'], 0, 3)));

                if (isset(self::FIRST_CLIENT_VALUES[$hex])) {
                    $findings[] = sprintf(
                        '%s:%d  %s { %s: … %s … }  →  %s',
                        $d['file'], $d['line'], $this->shorten($d['selector']), $d['property'],
                        $literal['text'], self::FIRST_CLIENT_VALUES[$hex],
                    );
                }
            }
        }

        $this->assertSame([], $findings, implode("\n", array_merge(
            ['Un CSS vuelve a llevar la paleta del primer cliente, escrita como VALOR:'],
            array_map(fn (string $f): string => '  · '.$f, $findings),
            ['',
                '▶ Retirar el token (`#139`) no basta si el color se puede teclear en decimal.',
                '▶ Toda instalación heredaría una marca que no es la suya, y nada fallaría.'],
        )));
    }

    /**
     * **Cada excepción declarada sigue teniendo sujeto.**
     *
     * Una lista de excepciones que nadie poda se convierte en un permiso permanente: la entrada
     * sobrevive al selector que la justificaba y la guarda deja de mirar un trozo del CSS sin que
     * nadie lo decida. La lista solo encoge, y esto es lo que la obliga.
     */
    public function test_every_declared_exception_still_has_a_subject(): void
    {
        $sheets = implode("\n", array_map(
            fn (string $p): string => (string) file_get_contents($p),
            // ⚠️ La hoja de una INSTALACIÓN queda fuera (ver el filtro de `sheetContents`): un
            // paquete de cliente está HECHO de literales, y juzgarlo con las reglas del producto
            // sería prohibirle existir.
            array_values(array_filter(
                glob(base_path(self::SHEETS)) ?: [],
                static fn (string $path): bool => basename($path) !== 'client.css',
            )),
        ));

        foreach (array_keys(self::ALLOWED_SELECTORS) as $selector) {
            $this->assertStringContainsString(
                $selector, $sheets,
                "`{$selector}` ya no existe en ningún CSS: borra su entrada de `ALLOWED_SELECTORS`. ".
                'Una excepción sin sujeto es un permiso que ya no protege nada.',
            );
        }

        // T9: la lista llegó a VACIARSE (las dos entradas `.invite-*` se fueron con su bloque
        // muerto). El caso se queda por si vuelve a crecer; mientras tanto, esta aserción evita
        // que el runner lo marque «risky» por no aseverar nada.
        $this->assertLessThanOrEqual(
            2, count(self::ALLOWED_SELECTORS),
            'la lista de literales permitidos ha CRECIDO por encima de donde llegó a estar: cada '.
            'entrada nueva es una instalación que pierde ese color, y hay que justificarla aquí.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────────

    /** @param array{file: string, line: int, selector: string, property: string, value: string} $d */
    private function isAllowed(array $d): bool
    {
        // Una DECLARACIÓN de token es la definición del color: es su único sitio legítimo.
        if (str_starts_with($d['property'], '--')) {
            return true;
        }

        if (in_array($d['property'], self::ALLOWED_PROPERTIES, true)) {
            return true;
        }

        foreach (array_keys(self::ALLOWED_SELECTORS) as $selector) {
            if (str_contains($d['selector'], $selector)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los valores RGB de los tokens base, leídos del `:root` de las hojas.
     *
     * @return array<string, array{int, int, int}>
     */
    private function rootValues(): array
    {
        $values = [];

        foreach ($this->declarations() as $d) {
            if (! str_contains($d['selector'], ':root') || ! in_array($d['property'], self::BASE_TOKENS, true)) {
                continue;
            }

            $literals = $this->literalsIn($d['value']);

            if ($literals !== []) {
                $values[$d['property']] = array_slice($literals[0]['rgba'], 0, 3);
            }
        }

        return $values;
    }

    /**
     * Literales de color dentro de un VALOR css.
     *
     * ⚠️ Se exige que los componentes de `rgb()/rgba()` sean numéricos: `rgba(var(--x), .2)` NO es un
     * literal —el color lo pone el token— y contarlo sería un falso positivo que obligaría a declarar
     * excepciones para código correcto.
     *
     * @return list<array{text: string, rgba: array{int, int, int, float}}>
     */
    private function literalsIn(string $value): array
    {
        $out = [];

        // El `(?<![\w-])` evita casar el `#id` de un selector o el sufijo de un `--token-#…`.
        preg_match_all(
            '/(?<![\w-])#[0-9a-fA-F]{3,8}\b|\brgba?\(\s*\d[\d.]*\s*[, ]\s*\d[\d.]*\s*[, ]\s*\d[\d.]*\s*(?:[,\/]\s*[\d.]+%?\s*)?\)/',
            $value, $matches, PREG_SET_ORDER,
        );

        foreach ($matches as $m) {
            $text = $m[0];

            if (str_starts_with($text, '#')) {
                $hex = substr($text, 1);

                if (strlen($hex) === 3) {
                    $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                }

                if (strlen($hex) !== 6 && strlen($hex) !== 8) {
                    continue;   // #RRGGBBA / #RRGGB… no es un color válido
                }

                $out[] = ['text' => $text, 'rgba' => [
                    (int) hexdec(substr($hex, 0, 2)),
                    (int) hexdec(substr($hex, 2, 2)),
                    (int) hexdec(substr($hex, 4, 2)),
                    strlen($hex) === 8 ? round(hexdec(substr($hex, 6, 2)) / 255, 4) : 1.0,
                ]];

                continue;
            }

            preg_match_all('/[\d.]+/', $text, $parts);
            $n = $parts[0];

            $out[] = ['text' => $text, 'rgba' => [
                (int) $n[0], (int) $n[1], (int) $n[2],
                isset($n[3]) ? (float) $n[3] : 1.0,
            ]];
        }

        return $out;
    }

    /**
     * Todas las declaraciones de las hojas, con su selector y su nº de línea.
     *
     * ⚠️ Por REGLAS COMPLETAS y no línea a línea, que es como se pierden los valores multilínea: el
     * `background` de `.hero__stage-placeholder` ocupa tres, y sus dos fugas de marca vivían en la
     * primera y la segunda.
     *
     * @return list<array{file: string, line: int, selector: string, property: string, value: string}>
     */
    private function declarations(): array
    {
        if ($this->declarations !== null) {
            return $this->declarations;
        }

        $declarations = [];

        foreach (glob(base_path(self::SHEETS)) ?: [] as $path) {
            // ⚠️ **La hoja de una INSTALACIÓN queda fuera, y no es un descuido.** `client.css`
            // no es del producto: existe precisamente para que un cliente declare sus valores
            // —literales incluidos, que es de lo que está hecho un paquete de tema— y juzgarla
            // con las reglas del producto sería prohibirle hacer aquello para lo que existe.
            // ▶ Y además la hacía MENTIR al gate: una guarda que asevera por hoja cambiaba el
            // recuento de aserciones según si la máquina tenía o no un paquete instalado, así
            // que el `pre-push` bloqueaba en una máquina o en la otra. Medido el 2026-08-28 al
            // montar el paquete del segundo cliente. Mismo criterio que `SidebarStyleWiringTest`,
            // que enumera las hojas del producto en vez de barrer la carpeta.
            if (basename($path) === 'client.css') {
                continue;
            }

            $raw = (string) file_get_contents($path);

            // Los comentarios se BLANQUEAN preservando cada offset: así el nº de línea que se
            // imprime es el real, y un color documentado en prosa no se cuenta como servido.
            $css = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]),
                $raw,
            );

            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($rules as $rule) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1][0]));
                $body = $rule[2][0];
                $bodyOffset = $rule[2][1];
                $cursor = 0;

                foreach (explode(';', $body) as $chunk) {
                    $offset = $bodyOffset + $cursor;
                    $cursor += strlen($chunk) + 1;

                    if (! str_contains($chunk, ':')) {
                        continue;
                    }

                    [$property, $value] = explode(':', $chunk, 2);
                    $property = trim($property);

                    if ($property === '' || str_starts_with($property, '@')) {
                        continue;
                    }

                    $declarations[] = [
                        'file' => str_replace(base_path().'/', '', $path),
                        'line' => substr_count($css, "\n", 0, $offset) + 1,
                        'selector' => $selector,
                        'property' => $property,
                        'value' => $value,
                    ];
                }
            }
        }

        return $this->declarations = $declarations;
    }

    private function shorten(string $selector): string
    {
        return mb_strlen($selector) > 60 ? mb_substr($selector, 0, 57).'…' : $selector;
    }
}
