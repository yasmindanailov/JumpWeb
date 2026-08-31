<?php

namespace Tests\Feature\Theme;

use App\Domain\Content\Services\IllustrationKit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **EL HUECO DE ILUSTRACIÓN POR INSTALACIÓN** (`specs/hueco-ilustracion.md`, U1 y U2).
 *
 * El tercer hueco tras el logotipo y el icono, y el primero para ilustración y no para marca.
 * Hermano de {@see ClientThemePackageTest}: mismas tres piezas de despliegue, mismo modo de fallo.
 *
 * ⚠️⚠️ **Lo que este fichero vigila NO es sobre todo seguridad.** Medido en Chrome con control, por
 * `<use>` externo el SVG del cliente es INERTE —ni su script corre ni su baliza sale—. La barrera
 * que de verdad sostiene el mecanismo es la de **presentación**: un `<style>` interno del cliente
 * gana al `fill` que pone el producto, y un `stroke-width` propio del `<symbol>` gana al del
 * `<use>`. Las dos fugas son silenciosas —el dibujo aparece, con el color del cliente, y nada
 * falla—, y por eso cada regla de aquí viene con su mutación.
 *
 * ⚠️ **Y con la mitad que este repo olvida siempre**: además de cazar sus ejemplos, la guarda tiene
 * que **aceptar los no-ejemplos**. `fill` casa dentro de `fill-opacity` y `color` dentro de
 * `stop-color` si a alguien se le olvida el `(?<![-\w])`, y entonces la guarda rechaza kits sanos y
 * el fallo se lee al revés: parece que el kit está mal.
 */
class IllustrationKitTest extends TestCase
{
    use RefreshDatabase;

    /** Los slugs que las claves `zone-*` del kit de prueba pueden usar. */
    private const ZONAS = ['foam-pit', 'trampolines'];

    /** Un kit VÁLIDO. Todas las mutaciones parten de aquí. */
    private function kit(string $symbolAttrs = '', string $body = '<path d="M8 8h48v48H8Z"/>'): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg">'
            .'<symbol id="zone-foam-pit" viewBox="0 0 64 64"'.$symbolAttrs.'>'
            .'<title>Foso de espuma</title>'.$body
            .'</symbol></svg>';
    }

    /** @return list<string> */
    private function problems(string $svg): array
    {
        return IllustrationKit::problems($svg, self::ZONAS);
    }

    // ══ EL SUELO: un kit sano no da problemas ═══════════════════════════════════════════════════

    /**
     * **Sin este caso, todos los demás valen lo mismo con la clase devolviendo siempre un problema.**
     * Es el control: lo que tiene que salir DISTINTO.
     */
    public function test_a_clean_kit_reports_no_problems(): void
    {
        $this->assertSame([], $this->problems($this->kit()));
    }

    // ══ CASO 7 · seguridad y fuga de presentación por `<style>` ═════════════════════════════════

    /** El `<style>` del cliente GANA al `fill` del producto: medido, `#00FF00` sobre un `<use fill>`. */
    public function test_it_rejects_an_internal_style_block(): void
    {
        $svg = str_replace('<symbol', '<style>path{fill:#0F0}</style><symbol', $this->kit());

        $this->assertNotEmpty($this->problems($svg), 'un `<style>` interno pasa el filtro');
    }

    /** @return array<string, array{string}> */
    public static function vectoresDeCodigo(): array
    {
        return [
            'script' => ['<script>alert(1)</script>'],
            'foreignObject' => ['<foreignObject><b>x</b></foreignObject>'],
            'animate' => ['<animate attributeName="x" to="9"/>'],
        ];
    }

    #[DataProvider('vectoresDeCodigo')]
    public function test_it_rejects_code_carrying_tags(string $tag): void
    {
        $this->assertNotEmpty($this->problems($this->kit('', '<path d="M0 0h1v1H0Z"/>'.$tag)));
    }

    public function test_it_rejects_inline_handlers_and_javascript_urls_and_external_refs(): void
    {
        $this->assertNotEmpty($this->problems($this->kit(' onload="x()"')), 'pasa un manejador `on…=`');
        $this->assertNotEmpty(
            $this->problems($this->kit('', '<a href="javascript:x()"><path d="M0 0h1v1H0Z"/></a>')),
            'pasa un `javascript:`',
        );
        $this->assertNotEmpty(
            $this->problems($this->kit('', '<image href="https://ajeno.example/x.png"/>')),
            'pasa una referencia EXTERNA: el dibujo de un cliente no llama a casa',
        );
    }

    // ══ CASOS 4, 5 y 6 · los atributos de presentación, que son la barrera de verdad ════════════

    /** @return array<string, array{string}> */
    public static function atributosQueGanan(): array
    {
        return [
            'fill' => ['fill="#000"'],
            'stroke' => ['stroke="#000"'],
            'stroke-width' => ['stroke-width="8"'],
            'style' => ['style="fill:#000"'],
            'opacity' => ['opacity="0.5"'],
            'color' => ['color="#000"'],
        ];
    }

    /**
     * **CASO 4 · en la RAÍZ del símbolo.**
     */
    #[DataProvider('atributosQueGanan')]
    public function test_it_rejects_a_presentation_attribute_on_the_symbol_root(string $attr): void
    {
        $this->assertNotEmpty(
            $this->problems($this->kit(' '.$attr)),
            "el símbolo trae `{$attr}` propio y pasa: el producto no podrá recolorearlo y nada fallará",
        );
    }

    /**
     * **CASO 6 · y en cualquier DESCENDIENTE**, que es el mismo defecto un nivel más abajo.
     *
     * ⚠️ El diseño original solo miraba la raíz. Mover el `fill` a un `<path>` interior lo dejaba
     * pasar entero.
     */
    #[DataProvider('atributosQueGanan')]
    public function test_it_rejects_a_presentation_attribute_on_a_descendant(string $attr): void
    {
        $this->assertNotEmpty(
            $this->problems($this->kit('', '<g><path d="M0 0h1v1H0Z" '.$attr.'/></g>')),
            "un descendiente trae `{$attr}` y pasa: basta con bajarlo un nivel para saltarse la regla",
        );
    }

    /**
     * ⚠️⚠️ **CASO 5, y es el que nació CIEGO en el diseño original**: sus doce mutaciones aseveraban
     * `fill` y salían VERDES con el `stroke-width` puesto. Medido en navegador: un `<symbol>` con
     * `stroke-width` propio ignora el del `<use>` (21,4 % de tinta, idéntico al control sin
     * normalizar, frente al 46,4 % del normalizado).
     */
    public function test_the_stroke_width_rule_is_not_the_fill_rule_in_disguise(): void
    {
        $problemas = $this->problems($this->kit(' stroke-width="8"'));

        $this->assertNotEmpty($problemas, 'un `stroke-width` propio pasa el filtro');
        $this->assertStringContainsString(
            'stroke-width', implode(' | ', $problemas),
            'se rechaza, pero el mensaje no nombra `stroke-width`: si la regla que muerde fuera la '.
            'de `fill`, esta guarda estaría vigilando otra cosa y no se notaría.',
        );
    }

    // ══ LA OTRA MITAD: los NO-ejemplos tienen que pasar ═════════════════════════════════════════

    /**
     * ⚠️⚠️ **La mitad que este repo olvida.** Sin el `(?<![-\w])` de la clase, `fill` casa dentro de
     * `fill-rule`, `color` dentro de `stop-color` y `width` dentro de `stroke-width` — y la guarda
     * empieza a rechazar kits sanos. El fallo se lee al revés: parece que el kit está mal.
     *
     * ▶ Y las tres de aquí no son casos rebuscados: `fill-rule` lo necesita cualquier figura con
     * hueco —o sea el TROQUEL, que es uno de los tres tratamientos— y `data-stroke` es por donde
     * viaja el grosor que el diseñador quiere, ya que `stroke-width` no puede.
     *
     * @return array<string, array{string}>
     */
    public static function noEjemplos(): array
    {
        return [
            'fill-rule es geometría, no color' => ['fill-rule="evenodd"'],
            'clip-rule igual' => ['clip-rule="evenodd"'],
            'data-stroke es el canal del grosor' => ['data-stroke="4.1"'],
        ];
    }

    #[DataProvider('noEjemplos')]
    public function test_it_accepts_the_attributes_that_are_not_presentation(string $attr): void
    {
        $this->assertSame(
            [], $this->problems($this->kit('', '<path d="M0 0h1v1H0Z" '.$attr.'/>')),
            "se rechaza `{$attr}`, que es legítimo. Falta el `(?<![-\\w])` en algún patrón: la guarda ".
            'está cazando dentro de otro nombre de atributo.',
        );
    }

    /** Y `data-stroke` sí se valida como número: es un dato que el producto va a aplicar. */
    public function test_data_stroke_must_be_a_positive_number(): void
    {
        $this->assertNotEmpty($this->problems($this->kit(' data-stroke="gordo"')));
        $this->assertNotEmpty($this->problems($this->kit(' data-stroke="-2"')));
        $this->assertSame([], $this->problems($this->kit(' data-stroke="4.1"')));
    }

    // ══ CASO 11 · la gramática CERRADA ══════════════════════════════════════════════════════════

    /**
     * **Con sufijos libres ninguna guarda de paridad puede existir.** El diseñador retira una clave
     * en su próximo export y todo lo que la tuviera guardada degrada en silencio — que es lo que
     * `ProductIcon::CHOICES` ya dejó escrito.
     */
    public function test_it_rejects_a_key_outside_the_closed_grammar(): void
    {
        foreach (['pose-1', 'mancha-3', 'loquesea'] as $clave) {
            $svg = str_replace('id="zone-foam-pit"', 'id="'.$clave.'"', $this->kit());

            $this->assertNotEmpty($this->problems($svg), "la clave libre «{$clave}» pasa la gramática");
        }
    }

    /** Una clave `zone-*` que no apunta a ninguna zona viva es un dibujo que nadie puede pedir. */
    public function test_it_rejects_a_zone_key_with_no_live_zone(): void
    {
        $svg = str_replace('id="zone-foam-pit"', 'id="zone-inexistente"', $this->kit());

        $this->assertNotEmpty($this->problems($svg));
        $this->assertSame([], $this->problems($this->kit()), 'y la que SÍ existe sigue pasando');
    }

    /**
     * **La gramática de ranuras es CERRADA: solo valen las que el producto DECLARA.**
     *
     * ⚠️⚠️ **La lista se INYECTA, no se lee de la constante, y eso fue una corrección.** La primera
     * versión aseveraba con `IllustrationKit::SLOTS[0]` y con «la lista no está vacía»: en cuanto
     * las dos ranuras se retiraron —porque sus consumidores se retiraron— el caso reventó con el
     * producto SANO. *Una guarda atada a cuántas cosas hay hoy vigila el inventario, no la regla.*
     * Lo que se vigila aquí es el MECANISMO: sin declarar se rechaza, declarada se acepta.
     */
    public function test_only_declared_slots_are_accepted(): void
    {
        $svg = str_replace('id="zone-foam-pit"', 'id="slot-inventada"', $this->kit());

        $this->assertNotEmpty(
            $this->problems($svg),
            'se admite una ranura que el producto no declara: con sufijos libres ninguna guarda de '.
            'paridad puede existir, y una clave retirada degradaría en silencio.',
        );

        $this->assertSame(
            [], IllustrationKit::problems($svg, self::ZONAS, ['slot-inventada']),
            'la ranura declarada se rechaza igualmente: la tubería está rota, no cerrada.',
        );
    }

    /**
     * **Toda ranura declarada tiene forma de ranura** — y la lista **puede estar VACÍA**.
     *
     * ⚠️ Vacía es el estado correcto cuando ninguna pantalla pinta nada: una ranura sin consumidor
     * es lo que dejó los 19 dibujos de `#257` esperando años. Por eso este caso NO exige que haya
     * alguna: exige que las que haya estén bien formadas.
     */
    public function test_every_declared_slot_has_the_shape_of_a_slot(): void
    {
        $this->assertSame(
            IllustrationKit::SLOTS,
            array_values(array_filter(IllustrationKit::SLOTS, static fn ($s) => str_starts_with($s, 'slot-'))),
            'alguna ranura declarada no empieza por `slot-`: quedaría fuera de la gramática y su '.
            'propio dibujo se rechazaría.',
        );
    }

    // ══ LOS SÍMBOLOS TIENEN QUE SER PEDIBLES ════════════════════════════════════════════════════

    public function test_a_symbol_needs_id_viewbox_and_title(): void
    {
        $this->assertNotEmpty(
            $this->problems(str_replace(' viewBox="0 0 64 64"', '', $this->kit())),
            'sin `viewBox` no hay contorno ni troquel, y pasa',
        );
        $this->assertNotEmpty(
            $this->problems(str_replace('<title>Foso de espuma</title>', '', $this->kit())),
            'sin `<title>` pasa',
        );
        $this->assertNotEmpty(
            $this->problems('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1v1H0Z"/></svg>'),
            'un fichero SIN NINGÚN símbolo pasa: el kit no podría servir ni un dibujo',
        );
    }

    // ══ U1 · LAS TRES PIEZAS DEL DESPLIEGUE ═════════════════════════════════════════════════════

    /**
     * **CASO 1 · el kit no se versiona**: es arte de un cliente y este repo es el PRODUCTO.
     *
     * ⚠️ Anclado a PRINCIPIO DE LÍNEA. Una regla comentada (`# /public/img/client-kit.svg`) contiene
     * la misma subcadena y no ignora nada — es el defecto exacto con el que nació
     * {@see ClientThemePackageTest}.
     */
    public function test_the_kit_is_not_versioned(): void
    {
        $this->assertMatchesRegularExpression(
            '#^/public/img/client-kit\.svg\s*$#m',
            (string) file_get_contents(base_path('.gitignore')),
            'el kit de ilustración no está ignorado (o su regla está comentada): el arte de un '.
            'cliente acabaría versionado en el repo del producto.',
        );
    }

    /**
     * **CASO 2 · y el despliegue no se lo lleva.**
     *
     * ⚠️⚠️ Aquí muerde más que en las otras piezas del paquete: no es un dibujo, **son todos a la
     * vez**. Y el hueco falla hacia invisible a propósito, así que no queda ninguna caja rota que
     * delate la pérdida — la instalación simplemente deja de tener ilustración.
     */
    public function test_the_deploy_does_not_delete_the_kit(): void
    {
        $deploy = (string) file_get_contents(base_path('scripts/deploy.sh'));

        // Solo el array que recibe el rsync: un `--exclude` en un comentario de la cabecera del
        // script no excluye nada.
        preg_match('/RSYNC_EXCLUDES=\((.*?)^\)/ms', $deploy, $block);

        $this->assertNotEmpty(
            $block,
            'no se ha encontrado el array `RSYNC_EXCLUDES=( … )` en `deploy.sh`: ¿ha cambiado de '.
            'nombre? Sin él esta comprobación no mira nada.',
        );

        $this->assertMatchesRegularExpression(
            "#^\s*--exclude='/public/img/client-kit\.svg'#m",
            $block[1],
            "`deploy.sh` no excluye el kit del `rsync --delete` (o la línea está comentada).\n".
            "▶ Está gitignorado y no existe en local, así que `--delete` lo BORRARÍA del servidor en\n".
            '  el primer despliegue, y la instalación se quedaría sin ilustración EN SILENCIO.',
        );
    }

    /**
     * **U5 · y el despliegue MIRA el fichero real.**
     *
     * ⚠️⚠️ Es el único punto del sistema que lo hace. `kit:build` valida al INSTALAR; un sprite
     * copiado a mano en el servidor no lo revisa nadie, y las fugas de este hueco son SILENCIOSAS
     * —el dibujo aparece, con el color del cliente, y nada falla—.
     *
     * ⚠️ Se asevera que la guarda corre el comando **en modo `--check`**: sin él escribiría, y una
     * comprobación de salud que MUTA el servidor no es una comprobación de salud.
     */
    public function test_the_deploy_checks_the_installed_kit(): void
    {
        $deploy = (string) file_get_contents(base_path('scripts/deploy.sh'));

        $this->assertMatchesRegularExpression(
            '/^\s*[\w_]+=\$\(remote_php\s+"artisan kit:build --check"/m',
            $deploy,
            "`deploy.sh` no valida el kit instalado.\n".
            "▶ Es el ÚNICO punto que ve el fichero REAL del servidor: `kit:build` corre al instalar,\n".
            '  y un sprite copiado a mano se serviría sin que nadie lo revisara.',
        );

        $this->assertMatchesRegularExpression(
            '/^\s*check "GUARDA 7 · /m',
            $deploy,
            'la comprobación existe pero no está registrada como GUARDA con nombre: no saldría en el '.
            'informe de salud del despliegue, que es donde alguien la lee.',
        );
    }

    /**
     * **La guarda de la guarda**: las dos aserciones de fichero caen con su mutación.
     *
     * Sobre el texto, sin tocar los ficheros reales. Una línea comentada tiene que dejar de casar —
     * que es lo que la primera versión de {@see ClientThemePackageTest} no hacía, y por eso pasaba
     * con el despliegue borrando la hoja del cliente.
     */
    public function test_the_two_file_assertions_reject_a_commented_out_line(): void
    {
        foreach ([
            "#^\s*--exclude='/public/img/client-kit\.svg'#m" => [
                "    --exclude='/public/img/client-kit.svg'" => true,
                "    #--exclude='/public/img/client-kit.svg'" => false,
                "#   --exclude='/public/img/client-kit.svg'  (lo de abajo)" => false,
            ],
            '#^/public/img/client-kit\.svg\s*$#m' => [
                '/public/img/client-kit.svg' => true,
                '# /public/img/client-kit.svg' => false,
                '#/public/img/client-kit.svg' => false,
            ],
        ] as $pattern => $samples) {
            foreach ($samples as $sample => $shouldMatch) {
                $this->assertSame(
                    $shouldMatch, preg_match($pattern, $sample) === 1,
                    "el patrón «{$pattern}» ".($shouldMatch ? 'ha dejado de cazar' : 'caza').
                    " «{$sample}», y no debería: la guarda vuelve a estar ciega a una línea comentada",
                );
            }
        }
    }
}
