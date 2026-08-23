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
        $sources = (string) file_get_contents(base_path('resources/js/app.js'));

        foreach (['sidecart', 'nav', 'offers'] as $owner) {
            $this->assertStringContainsString(
                "'{$owner}",
                $sources,
                "El superpuesto «{$owner}» ya no pide su llave del cerrojo. O ha desaparecido, o ha ".
                'vuelto a tocar el `<body>` por su cuenta.'
            );
        }
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
