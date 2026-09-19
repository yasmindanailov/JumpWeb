<?php

namespace Tests\Feature\Architecture;

use App\Domain\Content\Services\ThemeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL TEMA DE LA INSTALACIÓN MANDA DENTRO DEL CAJÓN** (`DECISIONES #637`, F4 · T4–T5).
 *
 * El white-label del producto se sostiene sobre un orden de cascada que no se ve en ningún sitio y que se
 * rompe con un selector de más:
 *
 *   tema de la INSTALACIÓN (`client.css`)  →  valores del PAQUETE  →  lo que traiga el ANFITRIÓN
 *
 * ⚠️⚠️ **Y se rompió de verdad, durante un rato, en la T4.** La hoja del cajón empaquetable declara sus
 * valores por defecto sobre `:where(.sidecart)` —tiene que hacerlo: así el `--bg` de una landing ajena no le
 * repinta el cajón—. Para que el juez de esa hoja diera cero en la página del PRODUCTO se scopeó también el
 * `<style id="jj-theme">` del panel a `:root, .sidecart`… y eso puso el tema del panel MÁS CERCA de los nodos
 * del cajón que el `:root` donde tematiza `client.css`. **Medido con el `client.css` de producción**: dentro
 * del cajón, `--on-brand` pasaba de `#101418` (el del cliente) a `#14130F` (el del panel). Un despliegue
 * habría cambiado los colores del cajón de una instalación sin que nadie tocara su tema.
 *
 * No hacía falta para nada: en la landing del producto no se carga `cajon.css`, y en una página ajena ese
 * `<style>` ni existe. Quien tenía que compensar el escenario artificial era el instrumento
 * (`scripts/huella-maquetacion.mjs`, que re-escopa el bloque EN LA PÁGINA antes de comparar), no el producto.
 *
 * ▶ Este fichero vigila las dos mitades del trato: que el producto declare su tema donde siempre, y que la
 * hoja del paquete ponga sus valores por defecto con especificidad CERO para poder perder contra el tema.
 */
class TemaDeInstalacionMandaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * **El tema que inyecta el panel se declara SOLO en `:root`.**
     *
     * Un `.sidecart` aquí no rompe ningún test de los de antes: la landing se ve igual, el cajón se ve igual
     * en la máquina de quien lo escribe —porque su `client.css` de desarrollo probablemente ya declare los
     * dos selectores— y el daño aparece en la instalación del cliente, después de desplegar.
     */
    public function test_the_panel_theme_is_declared_only_on_root(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(
            1, preg_match('/<style id="jj-theme">([^{]*)\{/', (string) $html, $m),
            'la página ya no pinta el bloque de tema del panel (`<style id="jj-theme">`)',
        );

        $this->assertSame(
            ':root', trim($m[1]), implode("\n", [
                'El tema del panel se declara sobre «'.trim($m[1]).'» y tiene que ser `:root` a secas.',
                '',
                'Si ahí aparece `.sidecart`, el tema del PRODUCTO pasa a estar más cerca de los nodos del',
                'cajón que el `:root` donde tematiza `client.css`, y le gana: la instalación pierde sus',
                'colores dentro del cajón. Medido en la T4 con el tema real de PlayJump.',
                '',
                '▶ Si esto lo has puesto para que cuadre una medición, el arreglo va en el instrumento.',
            ]),
        );
    }

    /**
     * **La otra mitad: la hoja del paquete pone sus valores por defecto con especificidad CERO.**
     *
     * `:where(.sidecart)` gana al `:root` de un anfitrión por PROXIMIDAD —la herencia mira el ancestro más
     * cercano que declare, no la especificidad— y pierde contra cualquiera que declare sobre `.sidecart`,
     * que es lo que hace el tema de una instalación desde `INSTALACION-CLIENTE.md` §4. Sin el `:where`, el
     * paquete ganaría también al tema y el cajón de todas las instalaciones se vería igual.
     */
    public function test_the_package_defaults_have_zero_specificity(): void
    {
        $hoja = (string) file_get_contents(public_path('css/cajon.css'));

        $this->assertStringContainsString(
            ':where(.sidecart) {', $hoja,
            'la hoja del paquete ya no declara sus tokens sobre `:where(.sidecart)`',
        );

        // Y ningún TOKEN sobre `.sidecart` a secas, que es el cambio de una letra que se lleva el
        // white-label por delante.
        // ⚠️ La regla `.sidecart { position: fixed; inset: 0; … }` SÍ existe y es correcta: es la geometría
        // del propio cajón, no tema. Lo que no puede haber ahí es una custom property. La primera versión de
        // esta guarda prohibía el selector entero y salía roja contra código sano — una guarda que acusa a un
        // inocente se acaba desactivando, y con ella la que sí importa.
        preg_match_all('/(?<![(\w-])\.sidecart\s*\{([^{}]*)\}/', $hoja, $reglas);

        foreach ($reglas[1] as $cuerpo) {
            $this->assertSame(
                0, preg_match('/(^|;)\s*--[\w-]+\s*:/', $cuerpo),
                'la hoja del paquete declara un token sobre `.sidecart` a secas: con eso le gana al tema de '.
                'la instalación, que declara sobre `:root, .sidecart`. Los valores por defecto van en '.
                '`:where(.sidecart)`, que tiene especificidad cero.',
            );
        }
    }

    /**
     * **Y el tema del panel sigue teniendo algo que decir**, que si no las dos guardas de arriba se cumplen
     * solas con un bloque vacío. Es la guarda de la guarda.
     */
    public function test_the_panel_theme_actually_declares_tokens(): void
    {
        $declaraciones = ThemeSettings::cssRootDeclarations();

        $this->assertMatchesRegularExpression(
            '/--[\w-]+\s*:/', $declaraciones,
            'el tema del panel no declara ni un token: las guardas de arriba no estarían comprobando nada',
        );
    }
}
