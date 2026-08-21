<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **El bloque de cuenta se OCULTA durante el proceso de compra** (2026-08-22, decisión del owner:
 * dentro del embudo solo genera ruido).
 *
 * Antes «se minimizaba», y solo en modo `booking`. Eso dejaba dos efectos que nadie quería: el bloque
 * **reaparecía entero** justo en carrito, identificación y pago —donde más estorba— y en el paso de
 * identificación enseñaba sus dos botones **deshabilitados** (`$store.purchase.identifying` los
 * bloquea porque el flujo ya pide identificarse abajo): ruido con botones muertos.
 *
 * ⚠️ **Por qué esto es un test y no «se ve al mirar»**: la conducta la produce el CRUCE de tres cosas
 * que viven en tres ficheros distintos —la clase de modo que pone `layout.blade.php`, el mapa
 * paso→modo de `machine.js::modeOf()` y las reglas de `site.css`—. Ninguna de las tres falla sola si
 * otra cambia: el bloque simplemente vuelve a verse, **sin que nada avise**. Es la misma forma de
 * fallo que `DECISIONES #117`, donde los dos extremos de una costura estaban probados y nadie
 * vigilaba el medio.
 */
class SidebarAccountVisibilityTest extends TestCase
{
    private function css(): string
    {
        $path = public_path('css/site.css');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** El bloque de reglas que oculta la cuenta, aislado para poder aseverar sobre su interior. */
    private function hideRule(): string
    {
        $css = $this->css();
        $needle = '.sidecart__panel.is-booking .acct,';
        $at = mb_strpos($css, $needle);

        $this->assertIsInt(
            $at,
            'Ha desaparecido la regla que oculta el bloque de cuenta durante la compra. Sin ella el '.
            'saludo y los CTA de sesión vuelven a aparecer en mitad del embudo — incluido el paso de '.
            'identificación, donde además salen DESHABILITADOS.',
        );

        $end = mb_strpos($css, '}', $at);

        return mb_substr($css, $at, $end - $at);
    }

    /**
     * Los modos que ocultan son EXACTAMENTE los del proceso de compra. El mapa paso→modo vive en
     * `machine.js::modeOf()` y está bajo paridad con el servidor (`SidebarProgressParityTest`):
     * aquí se comprueba que el CSS reacciona a los modos correctos, no se redefine el mapa.
     */
    public function test_the_account_block_is_hidden_exactly_during_the_purchase_flow(): void
    {
        $rule = $this->hideRule();

        // `booking` = día y hora · `cart` = carrito, identificación y PAGO.
        $this->assertStringContainsString('.sidecart__panel.is-booking .acct', $rule);
        $this->assertStringContainsString('.sidecart__panel.is-cart .acct', $rule,
            'Falta el modo `cart`. Es el que cubre carrito, identificación y pago — o sea la mitad '.
            'del embudo donde el bloque más estorba, y donde antes reaparecía entero.');

        $this->assertStringNotContainsString('.sidecart__panel.is-catalog .acct', $rule,
            'En el CATÁLOGO el bloque debe verse: el cliente aún no ha entrado en el proceso y ahí '.
            'el CTA de iniciar sesión sí sirve para algo.');
        $this->assertStringNotContainsString('.sidecart__panel.is-result .acct', $rule,
            'En el DESENLACE debe volver: el proceso ya terminó, que es literalmente lo que se pidió '.
            '(«hasta finalizar»), y es cuando un invitado tiene más motivo para registrarse.');
    }

    /**
     * ⚠️ La parte que no se ve y sí importa: dentro del panel hay una TRAMPA DE FOCO (`a11yPanel` en
     * `app.js`). Colapsar solo con la rejilla y la opacidad dejaría los dos botones invisibles pero
     * TABULABLES dentro de la trampa, y anunciados por el lector de pantalla.
     */
    public function test_hiding_it_also_takes_it_out_of_the_focus_trap(): void
    {
        $this->assertStringContainsString(
            'visibility: hidden', $this->hideRule(),
            'Sin `visibility: hidden` el bloque desaparece a la vista pero SIGUE siendo tabulable, y '.
            'el panel tiene trampa de foco: el cliente tabularía a dos botones que no puede ver. '.
            'Ocultar sin sacar del foco no es ocultar.',
        );
    }

    /**
     * ⚠️ **La costura de la técnica de colapso, vigilada por los DOS extremos** — que es la lección de
     * `#117`. La animación usa `grid-template-rows: 1fr → 0fr` (el idioma que este repo ya usa en
     * `.catalog-acc__body`), y eso **exige** que el contenido cuelgue de un hijo con `overflow:
     * hidden` y `min-height: 0`.
     *
     * Si alguien quita el `<div class="acct__inner">` del marcado «porque no hace nada», el colapso
     * deja de animar y el bloque **da un tirón**; si alguien quita la regla CSS, el marcado se queda
     * con un envoltorio huérfano. Ninguna de las dos cosas rompe ningún otro test.
     */
    public function test_the_collapse_technique_is_wired_at_both_ends(): void
    {
        $blade = (string) file_get_contents(resource_path('views/livewire/site/account-context.blade.php'));

        $this->assertStringContainsString('class="acct__inner"', $blade,
            'Falta el envoltorio `.acct__inner` en el marcado. La técnica `1fr → 0fr` necesita un '.
            'hijo que recorte: sin él el bloque desaparece de golpe en vez de plegarse.');

        $css = $this->css();

        $this->assertMatchesRegularExpression('/\.acct__inner\s*\{[^}]*overflow:\s*hidden/', $css,
            'El envoltorio tiene que recortar (`overflow: hidden`), o el contenido se sale de la fila '.
            'colapsada y se ve durante toda la animación.');
        $this->assertMatchesRegularExpression('/\.acct__inner\s*\{[^}]*min-height:\s*0/', $css,
            '`min-height: 0` es lo que permite que la fila baje de la altura de su contenido. Sin él '.
            'la rejilla se niega a colapsar y no pasa nada.');
        $this->assertMatchesRegularExpression('/\.acct\s*\{[^}]*grid-template-rows:\s*1fr/', $css,
            'El root `.acct` tiene que ser la rejilla de una fila que se colapsa.');
    }
}
