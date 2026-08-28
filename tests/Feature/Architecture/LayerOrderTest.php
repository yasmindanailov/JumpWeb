<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **El orden de las capas de la web pública** (`DECISIONES #237`).
 *
 * ⚠️⚠️ **Medido en un iPhone (390×844) sobre `/entradas`, donde el cajón NACE ABIERTO**: el aviso de
 * cookies tapaba **296 px — el 45 % del cajón** — y con ellos el botón que hace avanzar la compra.
 * O sea que **todo cliente nuevo en móvil** se encontraba el paso de fecha a medias. Estuvo así
 * desde que existe el cajón y **no lo veía ningún test, porque ninguno mide dos capas a la vez**:
 * cada componente se comprueba solo, y una oclusión solo existe cuando hay dos.
 *
 * El `z-index: 1000` del aviso no salía de ninguna escala —en toda la hoja no había nada por encima
 * de 210—: era «muy arriba», no un sitio. Lo que este caso fija es el ORDEN por rol:
 *
 *  · la página (cabeceras pegajosas, nav) …………… hasta 110
 *  · el **aviso de cookies** ……………………………………… 140
 *  · lo que el cliente ABRE a propósito (modal, cajón) … 150–160
 *  · los avisos efímeros (toasts) ……………………………… 200+
 *
 * ▶ **El consentimiento no se pierde ni se esconde**: el aviso sigue ahí y vuelve a mandar en cuanto
 * se cierra el cajón —verificado en navegador: con el cajón cerrado gana él y «Aceptar» funciona—.
 * Lo que deja de hacer es tapar una compra en curso. `RGPD-05` habla de la ATOMICIDAD del
 * consentimiento, no de capas.
 *
 * ❗ Lo que este caso NO puede ver es la oclusión real: eso son dos rectángulos y un navegador. Se
 * comprobó con `document.elementFromPoint()` en el centro del aviso —devuelve el cajón— y se anotó
 * en la spec. Aquí se fija la DECISIÓN, que es lo que un test puede sostener.
 */
class LayerOrderTest extends TestCase
{
    /**
     * ⚠️ **Las DOS hojas del producto, y no solo `site.css`** (`#253`). El armazón declara su capa
     * en `landing.css` —es de las reglas heredadas del mockup— y buscando en una sola hoja el
     * localizador devolvía `null`, o sea que una aserción sobre él se caía por «no lo encuentro»
     * en vez de por lo que quería medir. Una escala de capas que solo lee media hoja no es una
     * escala: es la mitad que alguien miró.
     */
    private const CSS = ['public/css/site.css', 'public/css/landing.css'];

    /** El `z-index` declarado en la primera regla de un selector. */
    private function layer(string $selector): ?int
    {
        $css = implode("\n", array_map(
            fn (string $hoja) => (string) file_get_contents(base_path($hoja)),
            self::CSS,
        ));
        $quoted = preg_quote($selector, '/');

        if (! preg_match('/'.$quoted.'\s*\{[^}]*z-index:\s*(\d+)/s', $css, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    public function test_the_cookie_notice_never_covers_a_purchase_in_progress(): void
    {
        $aviso = $this->layer('.cookie');
        $cajon = $this->layer('.sidecart');

        $this->assertNotNull($aviso, 'No se encuentra el `z-index` del aviso de cookies.');
        $this->assertNotNull($cajon, 'No se encuentra el `z-index` del cajón.');

        $this->assertLessThan(
            $cajon,
            $aviso,
            "El aviso de cookies ({$aviso}) vuelve a estar por encima del cajón ({$cajon}). Medido en "
            .'un móvil: así tapa 296 px del cajón —el 45 %— y con ellos el botón que hace avanzar la '
            .'compra, en `/entradas`, donde el cajón nace abierto. El consentimiento no necesita estar '
            .'encima de una compra en curso: vuelve a la vista en cuanto el cajón se cierra.',
        );
    }

    /**
     * Y la otra mitad, que es la que importa legalmente: el aviso tiene que seguir **por encima de la
     * página**. Bajarlo demasiado lo dejaría detrás del nav o de una cabecera pegajosa, y entonces
     * habríamos cambiado un problema por otro peor.
     */
    public function test_the_cookie_notice_still_sits_above_the_page(): void
    {
        $aviso = $this->layer('.cookie');

        foreach (['.nav--over' => 'la barra de navegación'] as $selector => $que) {
            $capa = $this->layer($selector);
            $this->assertNotNull($capa, "No se encuentra el `z-index` de {$que} ({$selector}).");
            $this->assertGreaterThan(
                $capa,
                $aviso,
                "El aviso de cookies ({$aviso}) ha quedado por DEBAJO de {$que} ({$capa}). Tiene que "
                .'estar encima de la página: lo único que puede taparlo es algo que el cliente haya '
                .'abierto a propósito.',
            );
        }
    }

    /**
     * **La tarjeta del cierre es PÁGINA, no un superpuesto** (`#253`).
     *
     * ⚠️⚠️ **Y esto no es teoría de capas: es el fallo que el owner vio en un teléfono.** La tarjeta
     * del hero del cierre estaba en `z-index: 210` —«muy arriba», fuera de toda escala— y con eso
     * quedaba **por encima del cajón de compra**. Al pulsar «Reservar» al terminar la partida el
     * cajón se abría de verdad, con su cerrojo de scroll incluido, pero **detrás del juego**: lo que
     * se veía era el juego, sin cajón y sin poder desplazar. Un superpuesto invisible que además
     * congela la página es la peor forma de este fallo.
     *
     * ▶ Lo que se fija aquí es que **nada de la página se cuele por encima de lo que el cliente
     * abre a propósito**. La tarjeta puede —y debe— tapar el pie y el armazón; no el menú, ni el
     * aviso, ni el modal, ni el cajón.
     */
    public function test_the_closing_card_never_covers_what_the_client_opened(): void
    {
        $tarjeta = $this->layer('.reserve--fija .reserve__box');

        $this->assertNotNull($tarjeta, 'No se encuentra el `z-index` de la tarjeta del cierre.');

        foreach ([
            '.menu' => 'el menú a pantalla completa',
            '.cookie' => 'el aviso de cookies',
            '.modal' => 'el modal',
            '.sidecart' => 'el cajón de compra',
        ] as $selector => $que) {
            $capa = $this->layer($selector);
            $this->assertNotNull($capa, "No se encuentra el `z-index` de {$que} ({$selector}).");
            $this->assertLessThan(
                $capa,
                $tarjeta,
                "La tarjeta del cierre ({$tarjeta}) tapa {$que} ({$capa}).\n".
                '▶ Es contenido de PÁGINA: crece hasta llenar la pantalla, pero sigue estando debajo '.
                'de todo lo que el visitante abre a propósito. Con 210 el cajón se abría DETRÁS del '.
                'juego —invisible y con el scroll ya bloqueado por su cerrojo—.',
            );
        }

        // Y la otra mitad: sí tiene que pasar por encima del armazón, o al crecer se le quedarían
        // el logotipo y los botones flotando sobre la tinta mientras se retiran.
        $nav = $this->layer('.nav');
        $this->assertNotNull($nav, 'No se encuentra el `z-index` del armazón.');
        $this->assertGreaterThan(
            $nav,
            $tarjeta,
            "La tarjeta del cierre ({$tarjeta}) ha quedado por DEBAJO del armazón ({$nav}): mientras ".
            'se retira, sus botones se verían recortados sobre la tarjeta de tinta.',
        );
    }
}
