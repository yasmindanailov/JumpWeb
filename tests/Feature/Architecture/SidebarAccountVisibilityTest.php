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
     * Los modos que ocultan son EXACTAMENTE tres, y **cada uno por un motivo distinto**.
     *
     * ⚠️⚠️ **Este caso se quedó corto el 2026-08-22 y hay que decirlo**: el paso 1 del área de cliente
     * añadió `is-account` a la regla del CSS y **este test pasó por omisión** —solo aseveraba sobre
     * cuatro modos, y el nuevo no era ninguno—. No estaba roto: es que su título decía «exactamente
     * los del proceso de compra» mientras la regla ya cubría uno que no lo es. Una comprobación que
     * mide una cosa y se lee como otra es peor que no tenerla (`DECISIONES #115`), así que ahora
     * enumera **los tres** y sigue prohibiendo los dos que deben verse.
     *
     * El mapa paso→modo vive en `machine.js::modeOf()` y está bajo paridad con el servidor
     * (`SidebarProgressParityTest`); el modo `account` lo publica `section.js`. Aquí se comprueba que
     * el CSS reacciona a los modos correctos, no se redefine ningún mapa.
     */
    public function test_the_account_block_is_hidden_exactly_in_the_three_modes_that_need_it(): void
    {
        $rule = $this->hideRule();

        // `booking` = día y hora · `cart` = carrito, identificación y PAGO.
        $this->assertStringContainsString('.sidecart__panel.is-booking .acct', $rule);
        $this->assertStringContainsString('.sidecart__panel.is-cart .acct', $rule,
            'Falta el modo `cart`. Es el que cubre carrito, identificación y pago — o sea la mitad '.
            'del embudo donde el bloque más estorba, y donde antes reaparecía entero.');

        // Y el ÁREA DE CLIENTE, por un motivo distinto del de la compra: ahí el bloque no estorba,
        // **sobra** — sus dos botones llevan exactamente a donde el cliente ya está.
        $this->assertStringContainsString('.sidecart__panel.is-account .acct', $rule,
            'Falta el modo `account`. Dentro del área de cliente el bloque es redundante: su botón '.
            '«Mis reservas» lleva a la zona que se está mirando. Y sin esta regla, además, la próxima '.
            'reserva se pintaría DOS veces —el bloque y el índice—, que es justo lo que el índice '.
            'existe para evitar (`specs/area-cliente.md` §4.2).');

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
     * ⚠️⚠️ **EL HUECO TAMBIÉN SE COLAPSA, Y TAMBIÉN TIENE QUE SACAR DEL FOCO** (2026-08-23,
     * `specs/account-context-vue.md` §4.1).
     *
     * Antes de que el motor llegue, el bloque no existe: lo que hay es el hueco con el **suelo**
     * dentro —el formulario de cerrar sesión— y la clase `acct--pending`, que lo mantiene plegado
     * para que no se vea una franja crema con un botón suelto.
     *
     * ⚠️ **Y este colapso oculta ALGO, al revés que una medición previa que lo daba por vacío.** Sin
     * `visibility: hidden`, ese botón sería invisible pero TABULABLE dentro de la trampa de foco del
     * panel — y `a11yPanel.focusFirst()` hace `querySelector` **sin filtro de visibilidad**, así que
     * además se llevaría el foco al abrir el cajón. Es el mismo argumento del caso de arriba, y por
     * eso va aquí y no en otro fichero.
     *
     * ⚠️ **Se exige la regla con DOS clases (`.acct.acct--pending`)**: `.acct` está declarada en dos
     * bloques de la hoja, así que con especificidad (0,1,0) el orden de fuente decidiría y el segundo
     * le devolvería el padding y el borde. El síntoma sería la franja que esto evita, y **ningún test
     * de esta suite podría verla**.
     */
    public function test_the_pending_hole_is_collapsed_and_out_of_the_focus_trap(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.acct\.acct--pending\s*\{/', $css,
            'La regla del hueco tiene que llevar las DOS clases. Con `.acct--pending` a secas empata '.
            'en especificidad con `.acct` y el segundo bloque de la hoja le devuelve la caja: una '.
            'franja crema vacía que ningún test puede ver.'
        );

        $regla = mb_substr($css, (int) mb_strpos($css, '.acct.acct--pending {'));
        $regla = mb_substr($regla, 0, (int) mb_strpos($regla, '}'));

        $this->assertStringContainsString('grid-template-rows: 0fr', $regla, 'el hueco tiene que nacer plegado');
        $this->assertStringContainsString(
            'visibility: hidden', $regla,
            'Sin `visibility: hidden`, el botón de cerrar sesión del SUELO es invisible pero '.
            'TABULABLE dentro de la trampa de foco del panel — y `focusFirst()` se lo llevaría al '.
            'abrir. Ocultar sin sacar del foco no es ocultar.'
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
        // ⚠️⚠️ **Re-apuntado el 2026-08-23**: el marcado ya no es un Blade de Livewire sino el
        // componente Vue que lo sustituye (`specs/account-context-vue.md` §4.9). El sujeto es el
        // mismo —que el envoltorio que recorta siga existiendo— y por eso el caso se muda en vez de
        // morir; lo que cambia es dónde vive el marcado.
        // ⚠️ Se nombra el fichero CONCRETO y no «algún .vue»: el bloque podría partirse en dos
        // componentes, y una guarda que buscara en todos quedaría satisfecha por el que no toca.
        $marcado = (string) file_get_contents(resource_path('js/sidebar/account/AccountPanel.vue'));

        $this->assertStringContainsString('class="acct__inner"', $marcado,
            'Falta el envoltorio `.acct__inner` en el marcado. La técnica `1fr → 0fr` necesita un '.
            'hijo que recorte: sin él el bloque desaparece de golpe en vez de plegarse.');

        // ⚠️ **Y el SUELO servido también cuelga de un `.acct__inner`**, porque el hueco que lo
        // contiene lleva la clase `.acct` y por tanto se colapsa igual. Sin envoltorio, el botón de
        // cerrar sesión se saldría de la fila colapsada mientras dura la animación.
        $layout = (string) file_get_contents(resource_path('views/components/layout.blade.php'));

        $this->assertStringContainsString('class="acct__inner"', $layout,
            'El suelo servido del hueco ha perdido su envoltorio, y el hueco se colapsa igual que el '.
            'bloque: su contenido se saldría de la fila durante la animación.');

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
