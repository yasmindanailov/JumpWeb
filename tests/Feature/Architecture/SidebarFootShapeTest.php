<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **LA FORMA DEL PIE DEL CAJÓN** (`DECISIONES #554`, T4·4a del carril del SPA).
 *
 * El pie es la única pieza que el cliente usa en TODAS las pantallas del embudo, así que es la que hay
 * que acertar. Esta guarda fija las cuatro propiedades que la tanda decidió y que, si alguien las
 * deshace, **no rompen nada**: la pantalla sigue funcionando y solo queda peor.
 *
 * ❗❗ **Se miden PROPIEDADES, no sintaxis.** La lección se pagó en esta misma tanda: el caso de
 * `SidebarActionRoleTest` que contaba literales `sells: false` se puso rojo con el producto sano en
 * cuanto el módulo se escribió de otra forma —y **su propio comentario advertía de que ya le había
 * pasado antes**—. Aquí el alto del botón no se compara contra un texto: se CALCULA (relleno + línea)
 * y se compara con el alto declarado del sistema, así que muerde igual si alguien toca el relleno, el
 * cuerpo de letra o el interlineado.
 *
 * ⚠️ **Lo que esta guarda NO puede ver** es cómo queda en pantalla: eso lo mide
 * `scripts/sonda-armazon.mjs` en navegador, y sus cifras están en el registro de la decisión.
 */
class SidebarFootShapeTest extends TestCase
{
    /** El alto del tamaño GRANDE de la hoja de componentes del cliente. No hay token: es un literal suyo. */
    private const ALTO_GRANDE = 56;

    /** @var array<string, string>|null selector → cuerpo */
    private ?array $reglas = null;

    /**
     * **La guarda de la guarda.** Un localizador roto deja todos los casos de abajo pasando en verde
     * sobre un corpus vacío, que es como este proyecto ha perdido guardas enteras sin enterarse.
     */
    public function test_the_scanner_sees_the_foot(): void
    {
        $reglas = $this->reglas();

        $this->assertGreaterThan(1500, count($reglas), 'el localizador de reglas se ha roto');

        foreach (['.bk-foot', '.bk-foot__row', '.bk-foot__total', '.bk-cta', '.bk-paybreakdown'] as $selector) {
            $this->assertArrayHasKey($selector, $reglas, "el escáner no encuentra «{$selector}»: sin sujeto, sus casos no vigilan nada");
        }
    }

    /**
     * **El CTA mide el alto grande del sistema, y el número SALE de sus partes.**
     *
     * `min-height` solo pone un suelo: si el relleno o la línea crecen, la caja natural lo pasa y el
     * botón deja de medir 56 **sin que el `min-height` cambie ni una cifra**. Por eso aquí se suma
     * relleno + línea y se comprueba que coinciden: es la única forma de que la guarda siga diciendo
     * la verdad cuando alguien toque una de las tres.
     */
    public function test_the_cta_is_exactly_the_system_large_size(): void
    {
        $cuerpo = $this->reglas()['.bk-cta'];

        $relleno = $this->px($this->declaracion($cuerpo, 'padding'), 0);
        $tamano = $this->px($this->declaracion($cuerpo, 'font-size'));
        $linea = (float) $this->declaracion($cuerpo, 'line-height');
        $minimo = $this->px($this->declaracion($cuerpo, 'min-height'));

        $this->assertGreaterThan(0, $linea, 'el CTA tiene que declarar su `line-height`: heredado, la línea mide 21 y el botón sale a 57');

        $natural = 2 * $relleno + round($tamano * $linea);

        $this->assertSame(
            self::ALTO_GRANDE,
            (int) $minimo,
            'El CTA del pie ha dejado de declarar el alto GRANDE del sistema (56).',
        );

        $this->assertSame(
            self::ALTO_GRANDE,
            (int) $natural,
            "El CTA suma {$relleno}+{$tamano}×{$linea}+{$relleno} = {$natural} px, y el alto grande del sistema es ".self::ALTO_GRANDE.".\n".
            "▶ Con la caja natural por encima del `min-height`, el botón mide lo que suman sus partes y el 56 declarado deja de significar nada.\n".
            '▶ Si el tamaño grande del sistema cambia, cámbialo en `ALTO_GRANDE` y en la hoja a la vez.',
        );

        $this->assertSame(
            '800',
            $this->declaracion($cuerpo, 'font-weight', resolver: true),
            'El CTA del pie va en el peso del botón del sistema (800). Con 700 se lee como un botón secundario de la web.',
        );
    }

    /**
     * **El CTA ocupa la fila entera, debajo del total** (`[DECIDIDO owner]` con las dos formas
     * renderizadas delante).
     *
     * Compartía fila y no cabía: el botón grande con «Añadir al carrito» dentro pide ~250 px y el
     * total otros 95 de los 350 que hay en el cajón a 390. Volver a ponerlos en fila **no falla**:
     * encoge el botón por debajo de su tamaño declarado y aprieta el importe contra él.
     */
    public function test_the_cta_does_not_share_its_row_with_the_total(): void
    {
        $fila = $this->reglas()['.bk-foot__row'];

        $this->assertSame(
            'column',
            $this->declaracion($fila, 'flex-direction'),
            "El pie ha vuelto a poner el total y el CTA en la misma fila.\n".
            '▶ Medido a 390: no caben — el botón bajaba a 51 de alto con un relleno de 15 que no es ningún tamaño del sistema.',
        );

        $cta = $this->reglas()['.bk-cta'];

        $this->assertSame(
            '100%',
            $this->declaracion($cta, 'width'),
            'El CTA ha dejado de ocupar la anchura entera: con ella el objetivo táctil es toda la fila (350 px a 390).',
        );

        $this->assertNull(
            $this->declaracion($cta, 'flex'),
            'El CTA ha recuperado su `flex`, que es lo que le dejaba repartirse el ancho con el total.',
        );
    }

    /** El rótulo a un extremo y la cifra al otro, alineados por su BASE: son dos tallas distintas (15 y 22). */
    public function test_the_total_puts_its_amount_at_the_far_end(): void
    {
        $total = $this->reglas()['.bk-foot__total'];

        $this->assertSame('space-between', $this->declaracion($total, 'justify-content'), 'el rótulo y la cifra dejan de estar a los dos extremos del pie');
        $this->assertSame('baseline', $this->declaracion($total, 'align-items'), 'con `center` el rótulo flota por encima de la línea de la cifra');
    }

    /**
     * **En el pie que COBRA el rótulo deja de ser apoyo.**
     *
     * En las cuatro pantallas anteriores «Total» acompaña a una cifra informativa; en la de pagar
     * acompaña a la que va a la tarjeta, y tiene justo encima una banda donde «Total» SÍ es apoyo. Con
     * los dos en el mismo gris, las tres cifras de esa esquina pesan igual — y eso no falla: solo
     * deshace la jerarquía que la tanda construyó.
     */
    public function test_the_selling_foot_raises_its_label_out_of_the_support_grey(): void
    {
        $reglas = $this->reglas();
        $clave = '.bk-foot:has(.bk-cta--sells) .bk-foot__l';

        $this->assertArrayHasKey(
            $clave,
            $reglas,
            "El pie que cobra ha dejado de subir su rótulo.\n".
            '▶ Se ata a `.bk-cta--sells` a propósito: quién cobra lo dice `foot.js` con su `sells`, y '.
            'marcarlo otra vez en el marcado sería la misma regla escrita dos veces.',
        );

        $this->assertSame('var(--fg)', $this->declaracion($reglas[$clave], 'color'), 'el rótulo del pie que cobra ha vuelto al gris de apoyo');
        $this->assertSame(
            '700',
            $this->declaracion($reglas[$clave], 'font-weight', resolver: true),
            'el rótulo del pie que cobra ha vuelto al peso de apoyo',
        );
    }

    /**
     * **La banda del pago y el pie son DOS superficies, y entre ellas no hay línea.**
     *
     * Desde que el pie ancla en lo que se cobra, la banda lleva el TOTAL —el dato de apoyo— y el pie
     * la cifra que toca el botón: que sean superficies distintas es lo que dice cuál manda. Con las
     * dos en papel eran tres cifras del mismo peso en 100 px, y una línea encima del cambio de
     * superficie es la línea de más que el artboard no dibuja.
     */
    public function test_the_pay_band_is_another_surface_and_needs_no_rule_against_the_foot(): void
    {
        $banda = $this->reglas()['.bk-paybreakdown'];
        $pie = $this->reglas()['.bk-foot'];

        $this->assertNotSame(
            $this->declaracion($pie, 'background'),
            $this->declaracion($banda, 'background'),
            'La banda del pago ha vuelto a la superficie del pie: sin el escalón, el total y lo que se cobra pesan igual.',
        );

        $this->assertNull(
            $this->declaracion($banda, 'border-bottom'),
            'La banda ha recuperado su línea de abajo: contra el pie ya separa el cambio de superficie, y ahí salen dos.',
        );
    }

    /**
     * Devuelve el valor de una declaración del cuerpo de una regla, o `null` si no está.
     *
     * ⚠️ Se ancla al principio de la declaración (`;` o inicio) para que `padding` no case dentro de
     * `padding-left`, que es el mismo tropiezo que `width` dentro de `stroke-width` (`#257`).
     *
     * @param  bool  $resolver  sustituye `var(--token)` por su valor
     */
    private function declaracion(string $cuerpo, string $propiedad, bool $resolver = false): ?string
    {
        if (! preg_match('/(?:^|;)\s*'.preg_quote($propiedad, '/').'\s*:\s*([^;]+)/', $cuerpo, $m)) {
            return null;
        }

        $valor = trim($m[1]);

        return $resolver ? $this->token($valor) : $valor;
    }

    /**
     * El valor en píxeles de una declaración, resolviendo `var(--token)`.
     *
     * @param  int|null  $parte  cuál de los valores separados por espacio (para `padding: A B`)
     */
    private function px(?string $valor, ?int $parte = null): float
    {
        if ($valor === null) {
            return 0.0;
        }

        // `padding: var(--sp-18) 34px` → los `var()` traen paréntesis pero no espacios dentro.
        $partes = preg_split('/\s+/', $valor) ?: [];
        $uno = $parte === null ? $valor : ($partes[$parte] ?? '');

        return (float) rtrim($this->token($uno), 'px');
    }

    /**
     * Resuelve `var(--token)` contra la declaración del token en las hojas.
     *
     * ⚠️ La escala se declara como `calc(var(--sp-unit) * N)` con la unidad en **1px**. Si esa unidad
     * dejara de ser 1px, esta resolución mentiría — así que se comprueba en vez de suponerse.
     */
    private function token(string $valor): string
    {
        if (! preg_match('/var\(\s*(--[\w-]+)\s*\)/', $valor, $m)) {
            return $valor;
        }

        $css = $this->hojas();

        if (! preg_match('/'.preg_quote($m[1], '/').'\s*:\s*([^;]+)/', $css, $def)) {
            $this->fail("El token «{$m[1]}» no está declarado en ninguna hoja.");
        }

        $bruto = trim($def[1]);

        if (preg_match('/calc\(\s*var\(\s*(--[\w-]+)\s*\)\s*\*\s*([\d.]+)\s*\)/', $bruto, $c)) {
            preg_match('/'.preg_quote($c[1], '/').'\s*:\s*([^;]+)/', $css, $unidad);
            $this->assertSame(
                '1px',
                trim($unidad[1] ?? ''),
                "La unidad base «{$c[1]}» ha dejado de ser 1px: esta guarda resuelve la escala suponiéndolo y mentiría.",
            );

            return $c[2].'px';
        }

        return $bruto;
    }

    private function hojas(): string
    {
        return (string) file_get_contents(base_path('public/css/site.css'))
            .(string) file_get_contents(base_path('public/css/landing.css'))
            .(string) file_get_contents(base_path('public/css/client.css'));
    }

    /**
     * @return array<string, string> selector → cuerpo, con los comentarios blanqueados
     */
    private function reglas(): array
    {
        if ($this->reglas !== null) {
            return $this->reglas;
        }

        $out = [];

        foreach (['public/css/site.css', 'public/css/landing.css'] as $hoja) {
            $css = (string) file_get_contents(base_path($hoja));
            // ⚠️ Los comentarios se BLANQUEAN, no se borran: esta hoja tiene llaves y nombres de clase
            // dentro de ellos (`#482`), y un escáner que no los enmascara abre reglas donde no las hay.
            $ciego = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $ciego, $matches, PREG_SET_ORDER);

            foreach ($matches as $regla) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $regla[1]));

                if ($selector === '' || str_starts_with($selector, '@')) {
                    continue;
                }

                $out[$selector] = trim(($out[$selector] ?? '').' '.trim($regla[2]));
            }
        }

        return $this->reglas = $out;
    }
}
