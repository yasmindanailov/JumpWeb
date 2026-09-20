<?php

namespace Tests\Feature\Instancia;

use App\Domain\Content\Services\SiteDestinations;
use App\Http\Instancia\InstanceViews;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **El ANFITRIÓN MÍNIMO de la PORTADA** — lo que el producto sirve sin paquete de instancia (F5 · T2b,
 * `DECISIONES #666`).
 *
 * ⚠️⚠️ **Este fichero NO repite lo que ya vigilan otros, y ésa es la diferencia con sus ocho hermanos.**
 * Los anfitriones de `/precios`, `/contacto` y compañía necesitaron guarda propia porque sus páginas
 * dejaron de pedirse desde ningún sitio; la portada la piden **682 casos en 60 ficheros**, que desde esta
 * tanda miran justamente este marcado. Aquí solo viven las tres cosas que, medido, **no tenían sujeto**:
 *
 *  1. que el anfitrión **consuma el contrato ENTERO** — el criterio que salió de `#666`;
 *  2. que pinte **las cinco anclas que el producto anuncia** en el menú y el pie de las doce vistas;
 *  3. que **no dé por hecho material del cliente** — ni vídeo, ni fotos con su ruta tecleada.
 */
class AnfitrionPortadaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    private function html(): string
    {
        return (string) $this->get('/')->assertOk()->assertViewIs('anfitrion.portada')->getContent();
    }

    private function fuente(): string
    {
        return (string) file_get_contents(resource_path('views/anfitrion/portada.blade.php'));
    }

    /**
     * **EL ANFITRIÓN CONSUME EL CONTRATO ENTERO**, y no es limpieza: es el criterio que `#666` pagó.
     *
     * ❗❗ Un anfitrión «mínimo» que solo renderiza deja **datos compuestos sin consumidor en el producto**
     * —el velo teñido de la zona, la frontera de la escala de altura, los puntos del carril—: nadie los
     * pinta aquí, nadie los prueba aquí, y el día que se rompan se ve en la web de un cliente. Es el mismo
     * defecto que `#658` cazó en `/precios` con tres variables que viajaban a la vista sin leerse, solo que
     * del otro lado: allí sobraba el dato, aquí falta el consumidor.
     *
     * ⚠️ Se mira el FUENTE y no el HTML servido a propósito: una clave puede quedarse sin pintar en la
     * rama que este seeder no alcanza —sin packs no hay sección 04— y seguiría siendo un dato consumido.
     *
     * ❗❗ **Lo del COMPOSER se excluye, y eso lo enseñó esta guarda el día que nació**: al escribirla salió
     * roja con seis claves —`heroStatus`, `offers`, los dos `ctaMinPrice*` y las dos de cookies— y ninguna
     * era un olvido. `CONTRATO_DE_VISTAS` mezcla **dos contratos**: lo que pone el CONTROLADOR, que es de
     * la página y tiene que pintarlo ella, y lo que el composer global reparte a TODA vista, que lo
     * consumen el layout, el nav y el pie —o sea el ARMAZÓN, que es del producto—. La portada mudada
     * tampoco las usaba. *Exigirle a una página que pinte lo que pinta su marco es pedirle que lo pinte
     * dos veces.*
     */
    public function test_el_anfitrion_consume_todas_las_claves_del_contrato(): void
    {
        $fuente = $this->fuente();
        $sinConsumir = [];

        foreach (InstanceViews::CONTRATO_DE_VISTAS['portada']['datos'] as $clave) {
            if (in_array($clave, InstanceViews::DEL_COMPOSER, true)) {
                continue;
            }

            if (! str_contains($fuente, '$'.$clave)) {
                $sinConsumir[] = $clave;
            }
        }

        // Guarda de la guarda: si la lista del contrato se vaciara, el bucle pasaría sin mirar nada.
        $delControlador = array_diff(
            InstanceViews::CONTRATO_DE_VISTAS['portada']['datos'],
            InstanceViews::DEL_COMPOSER,
        );
        $this->assertGreaterThanOrEqual(20, count($delControlador), 'el contrato de la portada se ha vaciado');

        $this->assertSame([], $sinConsumir, implode("\n", [
            'El anfitrión de la portada NO pinta lo que el producto le da: '.implode(', ', $sinConsumir),
            '',
            '▶ Un anfitrión no es «lo mínimo que renderiza»: es lo que CONSUME EL CONTRATO ENTERO (`#666`).',
            '  Un dato que el producto compone y que ninguna vista suya pinta no lo prueba nadie aquí, y',
            '  se rompe en la web de una instalación.',
            '  O se pinta, o se retira del contrato — y retirarlo sube el MAYOR y hay que avisar a todas.',
        ]));
    }

    /**
     * **LAS CINCO ANCLAS QUE EL PRODUCTO ANUNCIA TIENEN QUE EXISTIR EN LA PÁGINA QUE ÉL SIRVE.**
     *
     * `SiteDestinations::HOME_SECTIONS` las publica en el menú y en el pie de las DOCE vistas. ⚠️⚠️ Un
     * ancla a una sección que no está **no falla**: el navegador se queda donde estaba, así que no hay
     * error, no hay log y nadie se entera — la peor forma de romper una navegación.
     *
     * ⚠️ La lista se lee del INVENTARIO y no se teclea aquí: tecleada, el caso se compararía consigo mismo
     * el día que alguien añada una sección al menú (la lección 2 de `#660`).
     */
    public function test_pinta_las_anclas_que_el_menu_y_el_pie_anuncian(): void
    {
        $html = $this->html();
        $faltan = [];

        foreach (array_keys(SiteDestinations::HOME_SECTIONS) as $ancla) {
            if (! str_contains($html, 'id="'.$ancla.'"')) {
                $faltan[] = '#'.$ancla;
            }
        }

        $this->assertSame([], $faltan, implode("\n", [
            'El menú y el pie anuncian anclas que la portada del producto NO pinta: '.implode(', ', $faltan),
            '▶ Un ancla a una sección que no está no falla y no avisa: el navegador se queda donde estaba.',
        ]));

        // Guarda de la guarda: si el inventario se quedara vacío, el bucle de arriba pasaría sin mirar.
        $this->assertGreaterThanOrEqual(5, count(SiteDestinations::HOME_SECTIONS));
    }

    /**
     * **EL ANFITRIÓN NO DA POR HECHO MATERIAL DEL CLIENTE** (`#663`, `#664`, `#666`).
     *
     * ❗❗❗ Es la guarda que le faltaba a la casa, y su historia lo dice todo: `HomePageTest` exigía
     * `hero__video` como guarda de la guarda del hero, y esa aserción **salía verde en esta máquina y roja
     * en una instalación recién montada** — el vídeo es del cliente y desde `#663` ni siquiera vive en
     * `main`. El mismo error lo cometieron tres casos que dependían de que las fotos estuvieran en disco.
     *
     * ▶ Lo que se vigila: el anfitrión **no monta un `<video>`** —el estado «sin vídeo» que el CSS tiene
     * diseñado es el suyo, y así por fin se alcanza (`#664`)— y **no teclea rutas de material**: las que
     * pinta salen del modelo (`Attraction::imageUrl()`, `ZoneCards`), que devuelve `null` cuando no hay.
     */
    public function test_no_da_por_hecho_material_del_cliente(): void
    {
        $fuente = $this->fuente();

        $this->assertStringNotContainsString('<video', $fuente,
            'El anfitrión monta un vídeo. El vídeo del hero es material del CLIENTE (`#663`): aquí '.
            'dejaría una instalación recién montada con un `<source>` a un fichero que no existe.');

        foreach (['videos/', 'images/attractions/', 'client-'] as $ruta) {
            $this->assertStringNotContainsString($ruta, $fuente,
                "El anfitrión teclea la ruta de material del cliente (`{$ruta}`). Lo que pinta tiene que ".
                'salir del modelo, que devuelve `null` cuando el fichero no está.');
        }

        // Y lo positivo, o lo de arriba se cumpliría con una página en blanco: el hero se pinta y DICE
        // que no hay vídeo, que es el estado que el CSS tenía diseñado y que hasta `#666` no se alcanzaba.
        $this->assertStringContainsString('data-has-video="false"', $this->html(),
            'El hero del anfitrión ha dejado de declarar su estado sin vídeo.');
    }
}
