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
    private const CSS = 'public/css/site.css';

    /** El `z-index` declarado en la primera regla de un selector. */
    private function layer(string $selector): ?int
    {
        $css = (string) file_get_contents(base_path(self::CSS));
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
}
