<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **El bloqueo de scroll del fondo tiene UN SOLO DUEÑO** (`docs/specs/sidebar-spa.md` §6).
 *
 * Es el último ítem del plan de verificación de Fase 4 que seguía sin cumplirse, y no era una
 * pulcritud: se midió que **seis sitios de tres ficheros** escribían `body.no-scroll` por su cuenta —el
 * cajón de compra, el modal de auth, el cajón del nav móvil, el modal de ofertas, el de «gestionar
 * reserva» de Mis pedidos y un `x-init` suelto en el layout—. Con un booleano en el `<body>` y varios
 * escritores, **el último en cerrar manda**.
 *
 * ⚠️ **El fallo es alcanzable con dos clics**: con el cajón de compra abierto, su bloque de cuenta
 * ofrece «Iniciar sesión» y abre el modal de auth; al cerrarlo, su `remove()` desbloqueaba el scroll con
 * el panel todavía delante. Y la SPA lo hace más probable, no menos: el cajón se queda montado.
 *
 * Este test es la versión EJECUTABLE de «un solo dueño», el mismo trato que `SidebarEntryTest` da a las
 * claves de sesión del desenlace y `AccessRevocationTest` a las credenciales. La conducta del cerrojo la
 * prueba `resources/js/ui/scroll-lock.test.js` con `node --test`; esto vigila que nadie lo esquive.
 */
class ScrollLockOwnerTest extends TestCase
{
    /** El dueño. Es el único fichero que puede tocar la clase del `<body>`. */
    private const OWNER = 'resources/js/ui/scroll-lock.js';

    /**
     * Dónde se busca. `public/css` queda fuera a propósito: ahí vive la DEFINICIÓN de la clase
     * (`body.no-scroll { overflow: hidden }`), que es justo lo que el dueño enciende y apaga.
     *
     * @var list<string>
     */
    private const ROOTS = ['resources/js', 'resources/views'];

    /**
     * Lo que cuenta como manipular la clase.
     *
     * ⚠️ **Se busca `classList` junto a `no-scroll`, no `no-scroll` a secas**, y la distinción es la que
     * hace el test falsable sin ser insufrible: media docena de comentarios del sistema citan el nombre
     * de la clase para explicar por qué las cosas son como son —incluido el propio dueño—, y contarlos
     * como infracciones obligaría a no poder documentar nada. Lo que no se puede hacer es TOCARLA.
     */
    private const MANIPULATION = '/classList\s*\.\s*(add|remove|toggle)\s*\(\s*[\'"`]no-scroll[\'"`]/';

    public function test_only_the_scroll_lock_module_touches_the_body_class(): void
    {
        $owner = base_path(self::OWNER);
        $violations = [];

        foreach (self::ROOTS as $root) {
            foreach ($this->filesIn(base_path($root)) as $file) {
                if ($file === $owner) {
                    continue;
                }

                $source = (string) file_get_contents($file);

                if (preg_match(self::MANIPULATION, $source) === 1) {
                    $violations[] = '  '.mb_substr($file, mb_strlen(base_path()) + 1);
                }
            }
        }

        $this->assertSame(
            [], $violations,
            "Alguien vuelve a escribir `body.no-scroll` por su cuenta:\n".implode("\n", $violations)."\n\n".
            "Usa el cerrojo con nombre: `\$store.scrollLock.lock('lo-tuyo')` y `.unlock('lo-tuyo')`\n".
            "(o `.set('lo-tuyo', abierto)` si vives de un booleano observado).\n".
            "⚠️ Con varios escritores el último en cerrar manda: cerrar un modal abierto ENCIMA del\n".
            'cajón de compra desbloquearía el scroll con el cajón todavía delante.'
        );
    }

    /**
     * ⚠️ **La guarda de la guarda.** Un patrón que no encuentra nada pasa en verde para siempre: si
     * alguien renombra la clase o el módulo, el test de arriba dejaría de mirar sin decirlo.
     *
     * Aquí se comprueba lo contrario de lo que comprueba aquél — que el dueño SÍ la toca — y, de paso,
     * que el patrón encuentra de verdad una manipulación cuando la hay.
     */
    public function test_the_owner_actually_manipulates_the_class(): void
    {
        $source = (string) file_get_contents(base_path(self::OWNER));

        $this->assertMatchesRegularExpression(
            self::MANIPULATION, $source,
            "El dueño ya no toca `body.no-scroll`, así que buscarlo en los demás no demuestra nada.\n".
            'O se ha renombrado la clase, o el `apply` que la aplica se ha ido a otro fichero.'
        );

        // Y el patrón encuentra una manipulación real cuando se le pone delante.
        $this->assertSame(
            1, preg_match(self::MANIPULATION, "document.body.classList.add('no-scroll');"),
            'el patrón ha dejado de reconocer una manipulación de la clase'
        );
    }

    /**
     * ⚠️⚠️ **EL FONDO NO SE MUEVE, Y ESO SON DOS COSAS, NO UNA** (2026-08-23).
     *
     * Se abrió el cajón, se hizo scroll dentro y **la página de detrás se movía**. Medido, había dos
     * causas independientes y arreglar solo una deja el síntoma:
     *
     *  1. **el bloqueo estaba incompleto**: solo `body.no-scroll { overflow: hidden }`. Cuando el
     *     elemento que scrollea es el DOCUMENTO —lo normal si el `<body>` no acota su altura, y
     *     prácticamente siempre en táctil— esa regla recorta el desbordamiento del body pero **no
     *     congela la página**;
     *  2. **el gesto se ENCADENA**: al llegar al tope del scroll interno, el navegador se lo pasa al
     *     ancestro. Congelar el documento no lo impide; lo corta `overscroll-behavior: contain`, que
     *     hasta ese día **no aparecía ni una vez en todo el repo**.
     *
     * ⚠️ Se asevera sobre el CSS y no sobre una conducta porque **ningún test de esta suite puede
     * hacer scroll**: no hay navegador. Lo que sí puede es exigir que las dos piezas estén.
     */
    public function test_the_background_is_frozen_and_the_scroll_does_not_chain(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        $this->assertMatchesRegularExpression(
            '/html\.no-scroll\s*,\s*\n?\s*body\.no-scroll\s*\{[^}]*overflow:\s*hidden/',
            $css,
            "El bloqueo ha vuelto a aplicarse solo al `<body>`.\n".
            '⚠️ No basta: cuando quien scrollea es el documento —casi siempre en táctil— la página de '.
            'detrás sigue moviéndose con el cajón abierto.'
        );

        $this->assertMatchesRegularExpression(
            '/\.purchase__scroll\s*\{[^}]*overscroll-behavior:\s*contain/',
            $css,
            "El scroll del cajón ha vuelto a ENCADENARSE con el de la página.\n".
            '⚠️ Congelar el fondo no lo impide: al llegar al tope de este contenedor, el navegador le '.
            'pasa el gesto al ancestro. Es la OTRA mitad del arreglo, y sin ella el síntoma vuelve.'
        );
    }

    /**
     * ⚠️ **Y el dueño escribe la clase en los DOS elementos.** Sigue siendo un solo escritor y un solo
     * nombre —que es lo que `DECISIONES #58` compró—, pero si volviera a escribirla solo en el
     * `<body>`, la regla de arriba quedaría inerte: estaría en el CSS sin que nadie la encienda.
     */
    public function test_the_owner_locks_the_document_too(): void
    {
        $owner = (string) file_get_contents(base_path(self::OWNER));

        $this->assertMatchesRegularExpression(
            '/documentElement\.classList\s*\.\s*toggle\(\s*[\'"`]no-scroll/',
            $owner,
            'El dueño ha dejado de bloquear el DOCUMENTO. La regla `html.no-scroll` del CSS se queda '.
            'entonces sin nadie que la encienda: presente y muerta.'
        );
    }

    /**
     * Cada superpuesto pide su llave con un nombre PROPIO.
     *
     * ⚠️ El caso que lo obligaba: «Mis pedidos» pintaba un modal por pedido, así que su llave llevaba
     * el id dentro (`order-manage:`). Con una llave compartida, abrir A, abrir B y cerrar A soltaría
     * el scroll con B delante — el mismo fallo, dentro de una sola pantalla.
     *
     * ⚠️⚠️ **Ese superpuesto ya no existe**: la página se retiró en la tanda 3 del área de cliente
     * (`DECISIONES #120(u)`) y su modal con ella. La lista baja a cuatro, MEDIDA. La regla que
     * enseñó —una llave por instancia, no por tipo— sigue viva en el cerrojo y en su test de unidad;
     * lo que se retira aquí es el inventario de un superpuesto que ya no se pinta.
     *
     * ⚠️⚠️ **Y baja a TRES el 2026-08-23** (`DECISIONES #122`): el modal de auth se retiró porque
     * entrar, darse de alta y recuperar la contraseña son ahora zonas del cajón. Su llave se va con
     * él — y la que importa, la de `sidecart`, es justamente la que ahora cubre esos tres casos.
     * ▶ El inventario encoge, la regla no: sigue habiendo una llave por superpuesto, y el cerrojo
     * sigue siendo el ÚNICO que toca `body.no-scroll` (`DECISIONES #58`).
     */
    public function test_every_overlay_asks_with_its_own_key(): void
    {
        // ⚠️⚠️ **Re-apuntado en F4 · T2 y T3b, con el inventario intacto**: el cajón pide y suelta su llave en
        // su CONTROLADOR sin framework, no en `app.js` —primero al abrir y cerrar (T2), y desde la T3b también
        // cuando NACE abierto, que antes era un `lock('sidecart')` suelto en la entrada del producto—. Los
        // otros dos superpuestos siguen donde estaban. Mirar un solo fichero dejaría media regla sin vigilar.
        // ⚠️ **Eran TRES superpuestos y son DOS desde `#668`** (F5 · T3): el widget de ofertas se
        // retiró con su recurso del panel (`#631`) y su llave se fue con él. No se relaja la regla
        // —quien tape la pantalla sigue teniendo que pedir su propia llave—: lo que hay es un
        // superpuesto menos. ▶ Comprobado que los dos que quedan la siguen pidiendo, o esto sería
        // una guarda que encoge hasta no vigilar nada.
        $porFichero = [
            'resources/js/app.js' => ['nav'],
            'resources/js/cajon/controller.js' => ['sidecart'],
        ];

        foreach ($porFichero as $fichero => $owners) {
            $sources = (string) file_get_contents(base_path($fichero));

            foreach ($owners as $owner) {
                $this->assertStringContainsString(
                    "'{$owner}",
                    $sources,
                    "El superpuesto «{$owner}» ya no pide su llave del cerrojo en «{$fichero}». O ha ".
                    'desaparecido, o ha vuelto a tocar el `<body>` por su cuenta.'
                );
            }
        }

        // Y las TRES puertas por las que el cajón la pide o la suelta: abrir, cerrar y nacer abierto.
        $cajon = (string) file_get_contents(base_path('resources/js/cajon/controller.js'));

        $this->assertSame(
            2, substr_count($cajon, "scrollLock.lock('sidecart')"),
            'El cajón pide su llave en DOS caminos —`open()` y `start()`, el del cajón que nace abierto—: si '.
            'uno se pierde, la página de detrás sigue rodando bajo el panel y nada falla.'
        );
        $this->assertStringContainsString(
            "scrollLock.unlock('sidecart')", $cajon,
            'El cajón ya no suelta su llave al cerrar: el scroll quedaría bloqueado con el panel fuera.'
        );
    }

    /** @return list<string> */
    private function filesIn(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (in_array($file->getExtension(), ['js', 'php'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
