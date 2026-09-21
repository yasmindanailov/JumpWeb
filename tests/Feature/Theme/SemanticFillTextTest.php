<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **EL TEXTO SOBRE UN RELLENO SEMÁNTICO ES UN TOKEN, Y NADA BAJA DE 10 PX** (auditoría de diseño
 * C3, `DECISIONES #434`).
 *
 * «Incluido» pintaba `#fff` quemado sobre `--ok`: con el verde del 2.º cliente da 2,95 (su propia
 * tabla de contraste lo marca «Blanco sobre Verde ✕ NUNCA»). No hay luminancia en CSS, así que el
 * PAR lo declara quien declara el color: el producto pone `--on-ok/--on-err` sobre sus
 * verde y rojo oscuros y el paquete del cliente los redefine para los suyos. Un `color: #fff` junto a
 * un relleno semántico vuelve a romperlo sin que falle nada.
 *
 * Y el sub-rótulo del CTA del armazón daba 2,61 en las doce vistas: no era el gris de otra
 * superficie sino `--fg-mute` atenuado al 62 % por una regla compartida con el relleno de tinta.
 *
 * Lo que este fichero vigila:
 *  1. Que los tres tokens existan en el `:root` del producto.
 *  2. Que ninguna regla combine `background: var(--ok|--err)` con un blanco quemado.
 *
 * ⚠️ **Eran TRES hasta `#564`.** `--warn` se retiró con el aviso sobre papel: era el MISMO rol que
 * `--attn` con otro nombre y un hex del primer cliente, y su par `--on-warn` tenía **cero
 * consumidores** —ninguna regla rellenaba con él— ni lo declaraba el paquete del cliente. *Un par de
 * texto para un relleno que nadie usa no vigila nada.* El tercer tono rellena hoy con `--attn`, y
 * sobre amarillo el texto es tinta por aritmética (11,26), no por elección: no necesita par.
 *  3. Que las cuatro SUPERFICIES de aviso se DERIVEN de su color sólido y que nadie pinte ese
 *     color como texto sobre ellas (`#564`, grieta 10).
 *  4. Que `.cta-ghost__s` lea el gris de PAPEL a opacidad 1.
 *  5. El suelo tipográfico de `docs/archivo/design-producto-2026-09-03.md` §3 (el `design.md` de la
 *     raíz hasta `#452`; la regla sigue vigente aunque el documento esté archivado): ningún
 *     `font-size` literal por debajo de 10 px en la
 *     web pública. ⚠️ La lista de excepciones es del CAJÓN (aparcado) y solo encoge.
 */
class SemanticFillTextTest extends TestCase
{
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css'];

    /**
     * Reglas del CAJÓN que aún bajan de 10 px. **Solo encoge** — y con la grieta 00 queda VACÍA:
     * `.bk-seg__label` era la última (9,5 px) y pasa al nivel **Etiqueta** (12), que es el escalón
     * más pequeño que el sistema declara. La lista se conserva, no el hueco: si mañana alguien
     * vuelve a bajar de 10 dentro del cajón, este fichero se lo dirá en vez de tener que decidirlo.
     *
     * @var list<string>
     */
    private const CAJON_BAJO_EL_SUELO = [];

    public function test_the_scan_sees_the_corpus(): void
    {
        $this->assertGreaterThan(1500, count($this->rules()), 'el localizador de reglas se ha roto');
    }

    /**
     * **LAS SUPERFICIES DE AVISO SE DERIVAN DE SU COLOR, NUNCA SE QUEMAN** (`#564`, grieta 10).
     *
     * ⚠️⚠️ **El defecto que esto cierra estaba MEDIDO y publicado**: `--ok` y `--err` SÍ los
     * sobrescribe el paquete del cliente y sus fondos claros NO —eran siete hex del PRIMER cliente—,
     * así que «Pagado» pintaba Verde Salta sobre un verde azulado ajeno y daba **2,64** de contraste;
     * «Cancelado» **3,88**; y «Gratis» **2,64**, éste también en la landing. *Un fondo que no sigue a
     * su color no es un tono más claro: es otro color.*
     *
     * ⚠️ **Y la segunda mitad es la que de verdad se rompe sola**: el color de marca **como TEXTO**
     * sobre su propio tinte no llega —2,67 · 4,10 · 1,49 sobre papel—, así que pintar
     * `color: var(--ok)` encima de `background: var(--ok-bg)` es exactamente el defecto que había.
     * El texto de un aviso va en tinta; el tono se queda en el fondo y en el borde.
     */
    public function test_the_warning_surfaces_are_derived_and_never_burnt(): void
    {
        $root = $this->rootOf('public/css/site.css');

        foreach (['--ok-bg', '--ok-border', '--err-bg', '--err-border', '--attn-bg', '--attn-border'] as $token) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($token, '/').':\s*color-mix\(in srgb, var\(--(ok|err|attn)\) \d+%, var\(--bg\)\)/',
                $root,
                "`{$token}` ha dejado de DERIVARSE de su color sólido.\n".
                "⚠️ Un hex aquí vuelve al defecto de `#564`: el paquete del cliente redefine `--ok`/`--err`\n".
                "y no el tinte, así que el fondo deja de seguir al color — medido, «Pagado» daba 2,64.\n".
                'Se mezcla con `var(--bg)` para que sobre superficie de tinta salga oscuro solo.'
            );
        }

        $culpables = [];

        foreach ($this->rules() as $selector => $body) {
            if (! preg_match('/background(?:-color)?:\s*var\(--(ok|err|attn)-bg\)/', $body, $m)) {
                continue;
            }
            if (preg_match('/(?<![-\w])color:\s*var\(--'.$m[1].'\)/', $body)) {
                $culpables[] = $selector;
            }
        }

        $this->assertSame(
            [], $culpables,
            "El color de marca pintado como TEXTO sobre su propio tinte:\n  ".implode("\n  ", $culpables)."\n".
            "⚠️ No llega al suelo y está medido: 2,67 · 4,10 · 1,49 sobre papel con el paquete puesto.\n".
            'El texto de un aviso va en `var(--fg)`; el tono se queda en el fondo y en el borde.'
        );
    }

    /**
     * **LOS CINCO HEX DEL PRIMER CLIENTE NO VUELVEN, Y LO PASADO NO SE DICE CON OPACIDAD** (`#564`).
     *
     * ⚠️ Los cinco se retiraron porque **ninguno fue elegido para esta marca**: `--warn` era el mismo
     * rol que `--attn` con otro nombre, `--err-strong` un texto que hoy es tinta, y `--refund` un
     * color para algo que **no es error ni éxito** —la grieta 04 lo cerró: una devolución se dice con
     * su signo y su fecha, no con un color—. Declarar cualquiera de ellos otra vez es reabrir el
     * hueco, y hacerlo no rompe nada: solo devuelve a la instalación los colores del parque anterior.
     *
     * ⚠️⚠️ **Y el velo**: `opacity` sobre una tarjeta entera mueve el fondo, la tinta y el gris a la
     * vez, así que la FECHA —lo único que se viene a mirar en el historial— caía a **3,10** contra un
     * suelo de 4,5. Lo inerte se dice **cambiando de superficie**. Es una regla que este sistema ya
     * había pagado en el carril con foco y que aquí se estaba pagando otra vez.
     */
    public function test_the_inherited_hexes_stay_out_and_the_past_is_a_surface(): void
    {
        $root = $this->rootOf('public/css/site.css');

        foreach (['--warn', '--warn-hover', '--err-strong', '--refund', '--refund-bg', '--on-warn'] as $token) {
            $this->assertDoesNotMatchRegularExpression(
                '/'.preg_quote($token, '/').':/',
                $root,
                "`{$token}` ha vuelto al `:root`. Era un hex del PRIMER cliente y su rol ya tiene dueño:\n".
                '`--attn` el aviso, `--fg` el texto sobre un tinte y `--money` cualquier cifra.'
            );
        }

        $this->assertArrayHasKey(
            '.orders__item--past', $this->rules(),
            'la regla del historial ha desaparecido: sin sujeto, esta guarda no vigila nada'
        );
        $this->assertStringNotContainsString(
            'opacity', $this->rules()['.orders__item--past'],
            "El historial ha vuelto a atenuarse con OPACIDAD.\n".
            '⚠️ Eso mueve fondo, tinta y gris a la vez: medido, la fecha de la visita cae a 3,10 '.
            'contra un suelo de 4,5. Lo pasado se dice con otra SUPERFICIE.'
        );
    }

    /**
     * **LA CIFRA DE COLOR ES LA YA COBRADA; LO PENDIENTE VA EN TINTA** (`#565`).
     *
     * ⚠️⚠️ **Este error lo cometió la propia tanda que lo arregla, y solo lo vio la CAPTURA.** Al sacar
     * el naranja heredado del dinero se pusieron los cinco importes en `--money`, que **por defecto
     * vale la tinta** — así que en la suite y en un clon sin paquete no cambia nada. Con el paquete del
     * cliente, `--money` es Lima 800: el libro quedó con «A pagar en el parque» en COLOR y «Pagado» en
     * tinta, **al revés que el artboard**.
     *
     * ▶ El criterio: un importe **pendiente** es un dato del pedido, no un aviso, y teñirlo lo
     * convierte en una alarma; el color se reserva a lo que **ya se cobró**. Una devolución tampoco lo
     * lleva: lo dicen su signo y su fecha (grieta 04). *Un token que por defecto vale tinta esconde su
     * propio error hasta que alguien instala un paquete.*
     */
    public function test_only_what_is_already_collected_wears_the_money_role(): void
    {
        /*
         * ⚠️⚠️ **Eran CINCO y son TRES desde `#667`, y no es que la regla se relaje**: `.orders__gate-amount`
         * y `.orders__refund-amount` eran marcado del cajón **Livewire**, que la Fase 4 retiró. Su CSS se
         * quedó huérfano en `site.css` y esta guarda lo seguía vigilando: *vigilaba una regla que ya no
         * pintaba nada*. La poda de huérfanas de la T2c se lo llevó y este caso lo destapó — no se rompió,
         * se despertó.
         * ▶ **Comprobado que no pierde fuerza antes de recortar la lista**: los tres que quedan SÍ los
         * pinta el cajón de hoy (`PurchaseCard.vue`), así que la regla —lo pendiente y lo devuelto van en
         * tinta, el color es para lo ya cobrado— conserva sus tres sujetos vivos.
         */
        $pendientes = [
            '.orders__balance--pay_at_park',
            '.orders__balance--refund_at_park',
            '.orders__mov--neg',
        ];

        // ⚠️ Se busca la regla POR SU SELECTOR y no por clave exacta: varias viven en un selector
        // agrupado (`.orders__balance--pay_at_park strong, .orders__balance--pay_online strong`), y
        // una clave literal se queda sin sujeto en cuanto alguien agrupa o desagrupa una.
        $reglas = $this->rules();
        $culpables = [];
        $vistos = [];

        foreach ($reglas as $selector => $body) {
            foreach ($pendientes as $pendiente) {
                if (! str_contains($selector, $pendiente)) {
                    continue;
                }
                $vistos[$pendiente] = true;
                if (str_contains($body, 'var(--money)')) {
                    $culpables[] = $selector;
                }
            }
        }

        $encontrados = array_keys($vistos);
        sort($encontrados);

        $this->assertSame(
            $pendientes, $encontrados,
            'alguno de los importes pendientes ya no existe: esta guarda se ha quedado sin sujeto'
        );
        $this->assertSame(
            [], $culpables,
            "Importes PENDIENTES o DEVUELTOS con el rol de cifra:\n  ".implode("\n  ", $culpables)."\n".
            '⚠️ Con el paquete del cliente eso los pinta de color y se leen como una alarma. Van en tinta.'
        );

        $this->assertStringContainsString(
            'var(--money)', $reglas['.orders__ledger--cash .orders__final strong'] ?? '',
            'lo ya COBRADO ha dejado de llevar el rol de cifra, que es lo único a lo que le toca'
        );
    }

    public function test_the_on_tokens_are_declared_in_the_product_root(): void
    {
        $root = $this->rootOf('public/css/site.css');

        foreach (['--on-ok', '--on-err'] as $token) {
            $this->assertStringContainsString(
                $token.':',
                $root,
                "`{$token}` ya no está en el `:root` de `site.css`: el texto sobre ese relleno vuelve a ser un literal.",
            );
        }
    }

    public function test_no_rule_burns_white_text_on_a_semantic_fill(): void
    {
        $culpables = [];

        foreach ($this->rules() as $selector => $body) {
            if (! preg_match('/background(?:-color)?:\s*var\(--(ok|err)\)/', $body)) {
                continue;
            }
            if (preg_match('/(?<![-\w])color:\s*(#fff\b|#ffffff\b|white\b)/i', $body)) {
                $culpables[] = $selector;
            }
        }

        $this->assertSame(
            [],
            $culpables,
            "Texto blanco QUEMADO sobre un relleno semántico (con el verde del 2.º cliente da 2,95):\n  ".
            implode("\n  ", $culpables)."\nUsa `var(--on-ok)` / `var(--on-err)`.",
        );
    }

    public function test_the_ghost_cta_sublabel_reads_the_paper_grey_at_full_opacity(): void
    {
        $rules = $this->rules();

        $this->assertArrayHasKey('.cta-ghost__s', $rules, 'la regla `.cta-ghost__s` ya no existe');
        $this->assertMatchesRegularExpression('/color:\s*var\(--paper-fg-mute\)/', $rules['.cta-ghost__s'], 'el sub-rótulo del fantasma ya no lee el gris de papel');
        $this->assertMatchesRegularExpression('/opacity:\s*1\b/', $rules['.cta-ghost__s'], 'el sub-rótulo vuelve a heredar la opacidad del relleno de tinta (2,61 sobre blanco)');
    }

    public function test_no_literal_font_size_below_ten_pixels_in_the_public_web(): void
    {
        $culpables = [];

        foreach ($this->rules() as $selector => $body) {
            if (! preg_match_all('/font-size:\s*(\d+(?:\.\d+)?)px/', $body, $m)) {
                continue;
            }
            foreach ($m[1] as $px) {
                if ((float) $px < 10 && ! in_array($selector, self::CAJON_BAJO_EL_SUELO, true)) {
                    $culpables[] = "{$selector} ({$px}px)";
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($culpables)),
            "`font-size` por debajo del suelo de 10 px (`docs/archivo/design-producto-2026-09-03.md` §3):\n  ".implode("\n  ", $culpables),
        );
    }

    public function test_the_exception_list_still_has_a_subject(): void
    {
        // ⚠️ La lista quedó VACÍA con la grieta 00, y un `foreach` sobre una lista vacía no asevera
        // nada: PHPUnit lo marca «risky» y con razón — un caso que no comprueba nada se lee como
        // verde. Se asevera el estado final que se quiere conservar: ninguna excepción abierta.
        if (self::CAJON_BAJO_EL_SUELO === []) {
            $this->assertSame([], self::CAJON_BAJO_EL_SUELO, 'la lista de excepciones está vacía: el cajón ya no baja de 10 px');

            return;
        }

        $rules = $this->rules();
        foreach (self::CAJON_BAJO_EL_SUELO as $selector) {
            $this->assertArrayHasKey($selector, $rules, "`{$selector}` ya no existe: retíralo de la lista de excepciones, que solo encoge");
        }
    }

    private function rootOf(string $sheet): string
    {
        $css = (string) file_get_contents(base_path($sheet));
        $blind = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
        preg_match_all('/(?:^|\n):root\s*\{([^{}]*)\}/', $blind, $m);

        return implode("\n", $m[1]);
    }

    /** @return array<string, string> selector → cuerpo, con los comentarios blanqueados */
    private function rules(): array
    {
        $out = [];

        foreach (self::SHEETS as $sheet) {
            $css = (string) file_get_contents(base_path($sheet));
            $blind = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $blind, $matches, PREG_SET_ORDER);

            foreach ($matches as $rule) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));
                if ($selector === '' || str_starts_with($selector, '@')) {
                    continue;
                }
                $out[$selector] = ($out[$selector] ?? '').' '.trim($rule[2]);
            }
        }

        return $out;
    }
}
