<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **EL CUERPO DEL CAJÓN NO BAJA DEL SUELO DEL SISTEMA** — la grieta 00, con trinquete.
 *
 * El canvas auditó nuestro código (`Auditoria Sistema SPA PJP`) y su grieta **00** era la mayor y la
 * única que el cliente nota en las 25 pantallas: el cuerpo del cajón iba a **13 px** —con notas a 11
 * y un 9— contra un suelo de **16**. `[DECIDIDO owner, 2026-09-11]`: **16 en todas**.
 *
 * Lo que este fichero impide es que vuelva a bajar. No vigila un número por regla —eso sería
 * cementar el diseño—, vigila **la escala**: dentro del cajón, un tamaño solo puede ser uno de los
 * cuatro niveles que el sistema declara para texto.
 *
 *   · `--fs-body` (16/17) — lo que se lee.
 *   · `--fs-body-s` (15) — el apoyo: pistas, notas, metadatos.
 *   · `--fs-button` (16) — el rótulo de un botón.
 *   · `--fs-label` (12) — la etiqueta: mono, versalitas o chapa. **Es el escalón más pequeño**.
 *
 * ⚠️ **Por qué la lista de PREFIJOS y no un fichero**: el CSS del cajón **no vive en un bloque**.
 * Está repartido entre las líneas ~850 y ~6200 de `site.css` (medido), así que acotar por posición
 * dejaría fuera a `.auth__*`, `.acct__*`, `.whoblock__*` y `.guardnote__*` —que es exactamente donde
 * el primer censo de esta tanda se quedó corto—. Lo que define al cajón es **qué clase emite**, no
 * dónde está escrita su regla.
 *
 * ⚠️ **Y las clases COMPARTIDAS no se tocan en su sitio**: `.form__label`, `.check`, `.zone-tab`,
 * `.cal__*` y la familia `.btn` las emiten también el formulario de contacto, el de recuperar
 * contraseña, los chips de la landing, el post-form, el justificante y el calendario de «Crear
 * pedido» del panel. `[DECIDIDO owner]`: se suben **acotadas al panel**, en un bloque al final de la
 * hoja. Por eso aquí se comprueban dos cosas distintas: que las del cajón estén en la escala, y que
 * las compartidas tengan su regla acotada.
 */
class SidebarBodySizeTest extends TestCase
{
    /** Lo que emite el cajón, por vocabulario de clase (no por posición en la hoja). */
    private const PREFIJOS_DEL_CAJON = [
        'acc-tile', 'acct', 'account__', 'addons__', 'auth__', 'bk-', 'cal-more', 'cal__', 'cart',
        'cartbar', 'catalog', 'daystrip', 'dep-pick', 'entry__', 'guardnote', 'orders__', 'paydue',
        'prod-ico', 'purchase', 'qr-pass', 'qtybox', 'timestrip', 'whoblock', 'wiz__',
    ];

    // ⚠️ `dep-pick` y `qr-pass` entraron DESPUÉS, y conviene decir por qué: el primer censo de la
    // tanda los perdió —el filtro de «esto es de la web» descartaba todo lo que empezara por `qr-`—
    // y en pantalla salieron igual, porque la SONDA mide lo que el navegador computa y no lo que un
    // `grep` cree. *Una lista de prefijos escrita a mano se queda corta en silencio: lo que la
    // corrige es medir la pantalla, no releerla.*

    /** Los cuatro niveles de TEXTO del sistema, más los que ya están por encima del suelo. */
    private const NIVELES = ['--fs-body', '--fs-body-s', '--fs-button', '--fs-label'];

    /**
     * Lo que NO es texto y por eso se exceptúa, con su motivo. **Solo encoge.**
     *
     * `.acct__alert-ico` fija el cuerpo de un GLIFO dentro de un círculo de 18 px: subirlo no lee
     * mejor, desborda su caja.
     */
    private const NO_ES_TEXTO = ['.acct__alert-ico'];

    /** Las compartidas, con el nivel al que se suben DENTRO del panel. */
    private const ACOTADAS = [
        '.form__field > .form__label', '.form__field textarea', '.form__field select',
        '.form__error', '.form__hint', '.check', '.switch', '.auth__sub', '.auth__errors',
        '.eventfields__label', '.addons__moreinfo', '.addons__features li', '.cal__month',
        '.cal__day', '.cal__wd', '.acct__btn', '.zone-tab', '.purchase__note', '.btn',
        // ⚠️ Las cuatro últimas no salieron del censo, sino del chequeo de COMPLETITUD: cruzar las
        // clases que el cajón EMITE de verdad con las reglas por debajo de 16. Se escapaban a la vez
        // de los bloques del cajón y de los prefijos de arriba.
        '.eventfields input', '.eventfields select', '.eventfields textarea', '.pagination__info',
    ];

    public function test_the_scanner_sees_the_drawer(): void
    {
        $this->assertGreaterThan(
            100,
            count($this->reglasDelCajon()),
            'el escáner ve muy pocas reglas del cajón: ¿ha cambiado el vocabulario de clases?',
        );
    }

    public function test_no_drawer_rule_declares_a_size_below_the_scale(): void
    {
        $culpables = [];

        foreach ($this->reglasDelCajon() as $selector => $cuerpo) {
            if (in_array($selector, self::NO_ES_TEXTO, true)) {
                continue;
            }

            // ⚠️ Las COMPARTIDAS no se miden aquí, y la primera versión de este caso lo hacía: su
            // regla BASE se queda pequeña **a propósito** (`[DECIDIDO owner]`, para no mover cinco
            // superficies que nadie ha revisado) y quien las sube dentro del panel es el bloque
            // acotado, que comprueban los dos casos de abajo. Medirlas aquí pedía justo lo contrario
            // de lo decidido — y con razón puso en rojo las ocho.
            if ($this->esCompartida($selector)) {
                continue;
            }

            if (! preg_match('/font-size:\s*([^;]+)/', $cuerpo, $m)) {
                continue;
            }

            $valor = trim($m[1]);

            // Un token de la escala con nombre: correcto por construcción.
            if (in_array($valor, array_map(fn (string $t): string => "var({$t})", self::NIVELES), true)) {
                continue;
            }

            // `--fs-N` es la escala multiplicativa vieja (`#42`): vale mientras no baje del suelo.
            if (preg_match('/^var\(--fs-(\d+(?:\.\d+)?)\)$/', $valor, $n) && (float) $n[1] >= 16) {
                continue;
            }

            // Un literal o un `clamp()` por encima del suelo tampoco es esta grieta.
            if (preg_match('/^(\d+(?:\.\d+)?)px$/', $valor, $n) && (float) $n[1] >= 16) {
                continue;
            }

            if (str_starts_with($valor, 'clamp(') || $valor === 'inherit') {
                continue;
            }

            $culpables[] = "{$selector} → {$valor}";
        }

        $this->assertSame(
            [],
            $culpables,
            "Reglas del CAJÓN por debajo de la escala del sistema (grieta 00):\n  ".implode("\n  ", $culpables).
            "\nUsa `--fs-body` (16/17), `--fs-body-s` (15), `--fs-button` (16) o `--fs-label` (12), que es el suelo.",
        );
    }

    public function test_every_shared_class_is_raised_only_inside_the_panel(): void
    {
        $hoja = (string) file_get_contents(base_path('public/css/site.css'));

        foreach (self::ACOTADAS as $selector) {
            $this->assertStringContainsString(
                ".sidecart__panel {$selector} {",
                $hoja,
                "`{$selector}` se comparte con otra superficie (la web, el post-form, el justificante o el ".
                'panel): su talla dentro del cajón vive acotada a `.sidecart__panel`, no en su regla base.',
            );
        }
    }

    public function test_the_shared_rules_do_not_leak_out_of_the_panel(): void
    {
        // El contrario del caso de arriba: si alguien «termina el trabajo» subiendo la regla BASE de
        // una compartida, cinco superficies que nadie ha revisado cambian de tipografía en silencio.
        $reglas = $this->reglas();

        // ⚠️ Se busca por CLASE y no por la clave exacta del selector: la del rótulo de campo está
        // escrita AGRUPADA (`.form__field > span, .form__field > .form__label`), así que pedirla
        // literal leía vacío y el caso fallaba con el producto sano. *Aseverar cómo está escrito un
        // selector ata la guarda a su redacción; lo que hay que aseverar es qué declara.*
        foreach (['.form__label' => 13, '.check' => 13, '.zone-tab' => 13] as $clase => $antes) {
            $base = '';

            foreach ($reglas as $selector => $cuerpo) {
                if (str_starts_with($selector, '.sidecart__panel ')) {
                    continue; // ése es el bloque acotado: justo lo que NO es la regla base
                }

                if (preg_match('/'.preg_quote($clase, '/').'(?![\w-])/', $selector)) {
                    $base .= ' '.$cuerpo;
                }
            }

            $this->assertMatchesRegularExpression(
                '/font-size:\s*var\(--fs-'.$antes.'\)/',
                $base,
                "`{$clase}` es de la web tanto como del cajón: su regla base se queda como está ".
                '(la sube el carril que vista esa superficie), y dentro del panel manda el bloque acotado.',
            );
        }
    }

    public function test_the_exception_list_still_has_a_subject(): void
    {
        $reglas = $this->reglas();

        foreach (self::NO_ES_TEXTO as $selector) {
            $this->assertArrayHasKey(
                $selector,
                $reglas,
                "`{$selector}` ya no existe: sácalo de la lista de excepciones, que solo encoge.",
            );
        }
    }

    /**
     * ¿El selector cita una clase COMPARTIDA con otra superficie?
     *
     * ⚠️ Se compara con frontera de palabra y no por subcadena: `.cal__day-price` **contiene**
     * `.cal__day` y es del cajón en exclusiva — darla por compartida la dejaría sin vigilar. Es la
     * trampa de la subcadena que este proyecto ya ha pagado tres veces (`#253`).
     */
    private function esCompartida(string $selector): bool
    {
        foreach (self::ACOTADAS as $acotada) {
            // De `.form__field > .form__label` interesa la ÚLTIMA clase: es la que lleva la talla.
            $clase = (string) preg_replace('/^.*[\s>](?=\.)/', '', $acotada);

            if (preg_match('/'.preg_quote($clase, '/').'(?![\w-])/', $selector)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> las reglas cuyo selector es vocabulario del cajón */
    private function reglasDelCajon(): array
    {
        $out = [];

        foreach ($this->reglas() as $selector => $cuerpo) {
            // El bloque acotado se mide aparte: su sitio es justo lo que este fichero comprueba.
            if (str_starts_with($selector, '.sidecart__panel ')) {
                continue;
            }

            foreach (self::PREFIJOS_DEL_CAJON as $prefijo) {
                if (str_contains($selector, '.'.$prefijo)) {
                    $out[$selector] = $cuerpo;
                    break;
                }
            }
        }

        return $out;
    }

    /** @return array<string, string> selector → cuerpo, con los comentarios blanqueados */
    private function reglas(): array
    {
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

                $out[$selector] = ($out[$selector] ?? '').' '.trim($regla[2]);
            }
        }

        return $out;
    }
}
