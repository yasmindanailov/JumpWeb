<?php

namespace Tests\Feature\Site;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\LandingService;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Dom\HTMLDocument;
use Dom\XPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **LA RED DEL ARMAZÓN, ANTES DE REESCRIBIRLO** (`docs/specs/armazon-y-menu.md`, tanda 2c·0).
 *
 * La tanda 2c retira la barra fija y la sustituye por dos racimos flotantes y un menú a
 * pantalla completa. Antes de mover nada hay que poder demostrar qué hace hoy el armazón, y al
 * medirlo salió que **el cajón móvil no lo toca NINGÚN test** —ni PHP ni JS—: 26 reglas de CSS,
 * ~50 líneas de marcado, el trap de foco, el bloqueo de scroll y las cuatro vías de cierre,
 * todo sin red. Reescribir a ojo justo lo único que no tiene red es cómo se cuelan las
 * regresiones que la suite no puede ver.
 *
 * ⚠️⚠️ **Este fichero lee por ELEMENTO, no por subcadena, y no es una preferencia de estilo.**
 * En la tanda 2b un test daba verde con `assertSee('cta-prime')` sin fijar nada: casaba con
 * `cta-prime__ico`, y el texto que creía comprobar lo pintaba también otro sitio de la página
 * (`tema-por-instalacion.md` §12.4). Aquí las clases se buscan como TOKEN dentro de `@class` y
 * los enlaces se recogen del subárbol que les corresponde. La guarda de abajo lo asevera.
 *
 * ▶ **Lo que este fichero NO es**: una foto del diseño. Fija **capacidades** —qué destinos se
 * ofrecen, quién puede cerrar el cajón, qué ve un invitado y qué ve alguien con sesión—, no
 * píxeles. La 2c cambiará el marcado entero y estas aserciones tienen que seguir valiendo; si
 * una deja de valer es que se ha perdido una capacidad, y eso es justo lo que hay que ver.
 */
class ArmazonContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Las vistas que sirven el armazón. **Añadir una es deliberado**: una página pública nueva
     * que se olvide del armazón se queda sin navegación y nadie se entera.
     */
    private const VIEWS_WITH_ARMAZON = [
        'auth/reset-password',
        'auth/verify-email',
        'errors/404',
        'errors/page-maintenance',
        'home',
        // `/atracciones` (carril de diseño, T2d·1): la página de las 23, destino de la única puerta
        // de la sección 03. Se añade a sabiendas — sin armazón se quedaría sin vuelta.
        'pages/attractions',
        'pages/contact',
        'pages/events',
        'pages/pricing',
        'pages/rules',
        'pages/services',
        'pages/text',
        'payments/retry-redirect',
    ];

    /**
     * Páginas que se pueden renderizar por HTTP sin montar un pedido ni un token.
     *
     * Las cuatro que faltan para las doce —restablecer contraseña, verificar correo, la de
     * mantenimiento y el reintento de pago— piden estado que no aporta nada a esta red: su
     * cobertura es la aserción estática de arriba, que es la que de verdad las vigila.
     */
    private const RENDERABLE = [
        '/', '/atracciones', '/precios', '/cumpleanos', '/servicios', '/contacto', '/normas', '/aviso-legal',
    ];

    /** Destinos que el menú ofrece hoy y que la 2c no puede perder. `ruta#ancla`. */
    private const FIXED_DESTINATIONS = [
        '/#zones',
        '/#rides',
        '/#info',
        '/cumpleanos',
        '/precios',
        '/servicios#eventos',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La lectura de clases es por TOKEN, no por subcadena.**
     *
     * ❗ Es la trampa de `#195`: `cta-prime` casa con `cta-prime__ico` si se busca en crudo, y un
     * test escrito así sale verde diga lo que diga el marcado.
     */
    public function test_the_class_matcher_is_token_exact(): void
    {
        $html = '<div class="cta-prime book-bar__cta"><span class="cta-prime__ico"></span>'.
                '<span class="cta-prime__t">x</span></div>';

        $this->assertCount(1, $this->nodes($html, 'cta-prime'), 'la clase exacta debería casar una vez');
        $this->assertCount(1, $this->nodes($html, 'cta-prime__ico'), 'la clase hija debería casar por su nombre');
        $this->assertCount(0, $this->nodes($html, 'cta-prim'), 'un prefijo NO puede casar: sería la trampa de #195');
        $this->assertCount(0, $this->nodes($html, 'book-bar'), 'una clase parcial NO puede casar con `book-bar__cta`');
    }

    /**
     * **Los atributos de Alpine sobreviven al parseo.**
     *
     * ❗ `DOMDocument` tira `@click` y `:class` sin avisar porque no son nombres XML válidos. Un
     * test que preguntara por ellos recibiría cadena vacía y concluiría que el cableado no
     * existe — verde por un lado, ciego por el otro. Este control muerde ese caso exacto.
     */
    public function test_alpine_attributes_survive_the_parser(): void
    {
        $html = '<div class="sonda" @click="abre()" @keydown.escape.window="cierra()" :class="x && \'y\'"></div>'
            .'<div class="sonda-larga" x-on:click="abre()"></div>';

        $node = $this->nodes($html, 'sonda')[0];

        $this->assertSame('abre()', $node->getAttribute('data-alpine-click'), 'se pierde `@click` al parsear');
        $this->assertSame(
            'abre()', $this->nodes($html, 'sonda-larga')[0]->getAttribute('data-alpine-click'),
            'la sintaxis larga `x-on:click` no se normaliza, y el armazón usa LAS DOS',
        );
        $this->assertSame('cierra()', $node->getAttribute('data-alpine-keydown.escape.window'), 'se pierde un `@keydown` con modificadores');
        $this->assertNotSame('', $node->getAttribute('data-bind-class'), 'se pierde un `:class`');
        $this->assertSame('sonda', $node->getAttribute('class'), 'el renombrado ha tocado un atributo normal');
    }

    /**
     * **El lector ve el armazón de verdad en una página real.**
     *
     * Sin esto, un fallo del lector dejaría todo lo de abajo en verde sin haber mirado nada.
     */
    public function test_the_reader_sees_the_armazon_on_a_real_page(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertCount(1, $this->nodes($html, 'nav'), 'no se ve la barra en la portada');
        $this->assertCount(1, $this->nodes($html, 'mob-menu'), 'no se ve el cajón móvil en la portada');
        $this->assertNotEmpty($this->linksIn($html, 'mob-menu'), 'el cajón móvil no ofrece ni un enlace');
        $this->assertEmpty($this->nodes($html, 'zzz-clase-que-no-existe'), 'el lector casa con cualquier cosa');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se fija
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Las doce vistas que sirven el armazón, y solo ésas.**
     *
     * Se comprueba sobre las plantillas y no por HTTP porque cuatro de ellas piden estado —un
     * token, un pedido, el interruptor de mantenimiento— que no aporta nada a esta red.
     */
    public function test_exactly_the_declared_views_serve_the_armazon(): void
    {
        $found = [];

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = (string) preg_replace('/\{\{--.*?--\}\}/s', ' ', (string) file_get_contents($file->getPathname()));

            if (! str_contains($source, '<x-site.nav')) {
                continue;
            }

            $found[] = str_replace('.blade.php', '', ltrim(str_replace(resource_path('views'), '', $file->getPathname()), '/'));
        }

        sort($found);
        $expected = self::VIEWS_WITH_ARMAZON;
        sort($expected);

        $this->assertSame(
            $expected, $found,
            'la lista de vistas que sirven el armazón ha cambiado. Si es una página nueva, '.
            'añádela a `VIEWS_WITH_ARMAZON`; si una la ha perdido, se ha quedado sin navegación '.
            'y nadie se entera.',
        );
    }

    /**
     * **Todas las páginas renderizables sirven la barra Y el cajón móvil.**
     */
    public function test_the_armazon_is_served_on_every_renderable_page(): void
    {
        foreach (array_merge(self::RENDERABLE, ['/no-existe-esta-pagina']) as $path) {
            $html = $this->get($path)->getContent();

            $this->assertCount(1, $this->nodes($html, 'nav'), "`{$path}` no sirve la barra");
            $this->assertCount(1, $this->nodes($html, 'mob-menu'), "`{$path}` no sirve el cajón móvil");
        }
    }

    /**
     * **Ningún destino se pierde a ningún ancho.**
     *
     * ⚠️ **Esta regla se REDACTÓ de otra forma en la 2c·0 y la 2c·1 la movió de sujeto**, no de
     * contenido: entonces los portadores eran la barra y el cajón; ahora son el **menú a
     * pantalla completa** (por encima de 1080 px) y el **cajón** (por debajo). Lo que se
     * comprueba es lo mismo de siempre: si un destino vive en un portador y no en el otro,
     * media plantilla de visitantes no lo encuentra, y eso ni falla ni avisa.
     *
     * ▶ Se comparan como CONJUNTOS, no destino a destino: así también se caza el caso
     * contrario —un destino que llega a un portador y no al otro— sin tener que enumerarlo.
     */
    public function test_no_destination_is_lost_at_any_width(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $menu = $this->linksIn($html, 'menu__list');
        $drawer = $this->linksIn($html, 'mob-menu');

        foreach (self::FIXED_DESTINATIONS as $destination) {
            $this->assertContains($destination, $menu, "el menú no ofrece `{$destination}`");
            $this->assertContains($destination, $drawer, "el cajón móvil no ofrece `{$destination}`");
        }

        sort($menu);
        $drawer = array_values(array_diff($drawer, ['/registro']));   // el alta vive en el pie del cajón, no es un destino
        sort($drawer);

        $this->assertSame(
            $menu, $drawer,
            'el menú a pantalla completa y el cajón de móvil ofrecen destinos DISTINTOS: a uno de '.
            'los dos anchos se le está escondiendo algo.',
        );
    }

    /**
     * **La barra ya no lleva destinos, y eso hay que fijarlo.**
     *
     * La 2c·1 le quitó los dos desplegables y los dos atajos. Sin esta aserción, media migración
     * —los enlaces de vuelta a la barra «mientras tanto»— pasaría inadvertida y el sitio tendría
     * otra vez dos navegaciones que mantener.
     */
    public function test_the_bar_no_longer_carries_destinations(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $bar = $this->linksIn($html, 'nav');

        $filtrados = array_values(array_intersect($bar, self::FIXED_DESTINATIONS));

        $this->assertSame(
            [], $filtrados,
            'la barra ha vuelto a llevar destinos de navegación: '.implode(', ', $filtrados).
            '. Desde la 2c·1 los lleva el menú.',
        );
    }

    /**
     * **Un servicio marcado para el menú llega a los DOS sitios, y desmarcarlo lo retira de los dos.**
     *
     * Lo primero lo cubría a medias `ServicesPageTest`, que solo mira la barra. El cajón móvil
     * lee la misma lista y **nadie comprobaba que llegara**: si el día de mañana se sirve solo a
     * la barra, en móvil el servicio deja de existir.
     */
    public function test_a_service_marked_for_the_nav_reaches_both_the_menu_and_the_drawer(): void
    {
        LandingService::create([
            'slug' => 'sonda-armazon',
            'title' => ['es' => 'Sonda del armazón'],
            'position' => 99,
            'is_active' => true,
            'show_in_nav' => true,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertContains('/servicios#sonda-armazon', $this->linksIn($html, 'menu__list'));
        $this->assertContains('/servicios#sonda-armazon', $this->linksIn($html, 'mob-menu'));

        LandingService::where('slug', 'sonda-armazon')->update(['show_in_nav' => false]);
        Cache::flush();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertNotContains('/servicios#sonda-armazon', $this->linksIn($html, 'menu__list'));
        $this->assertNotContains('/servicios#sonda-armazon', $this->linksIn($html, 'mob-menu'));
    }

    /**
     * **El cajón móvil es un overlay accesible, y sigue siéndolo después de la 2c.**
     *
     * Cada pieza de aquí tapó un defecto en su día y está documentada en el propio marcado: el
     * `role`/`aria-modal`, el trap de foco, las cuatro vías de cierre y —lo más fácil de perder
     * al reescribir— que **cerrado se oculta con `visibility`, no con `aria-hidden`**, porque
     * con `aria-hidden` quedaba foco fantasma.
     *
     * ❗ Es exactamente el defecto que trae el menú del mockup, que se oculta con un recorte
     * circular y deja sus enlaces en el orden de tabulación (`armazon-y-menu.md` §1.7).
     */
    public function test_the_drawer_is_an_accessible_overlay(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $drawer = $this->nodes($html, 'mob-menu')[0];
        $panel = $this->nodes($html, 'mob-menu__panel')[0];

        $this->assertSame('dialog', $panel->getAttribute('role'), 'el panel del cajón no es un diálogo');
        $this->assertSame('true', $panel->getAttribute('aria-modal'), 'el panel no se anuncia como modal');
        $this->assertSame('mobPanel', $panel->getAttribute('x-ref'), 'sin referencia al panel no hay trap de foco ni foco inicial');

        $this->assertNotSame('', $drawer->getAttribute('data-alpine-keydown'), 'el cajón no atrapa el foco');
        $this->assertNotSame('', $drawer->getAttribute('data-alpine-keydown.escape.window'), 'Escape no cierra el cajón');

        $this->assertCount(1, $this->nodes($html, 'mob-menu__backdrop'), 'el cajón no tiene telón');
        $this->assertCount(1, $this->nodes($html, 'mob-menu__close'), 'el cajón no tiene botón de cierre');

        $close = $this->nodes($html, 'mob-menu__close')[0];
        $this->assertNotSame('', $close->getAttribute('aria-label'), 'el botón de cierre no tiene nombre accesible');

        $burger = $this->nodes($html, 'nav__burger')[0];
        $this->assertSame('burger', $burger->getAttribute('x-ref'), 'sin referencia al ☰ no se puede devolver el foco al cerrar');
        $this->assertNotSame('', $burger->getAttribute('aria-label'), 'la hamburguesa no tiene nombre accesible');
    }

    /**
     * **Todo enlace del cajón lo cierra al pulsarlo.**
     *
     * Un ancla de la misma página no recarga: sin esto, el visitante pulsa «Zona Kids», la
     * página salta a la sección y **el cajón se queda abierto encima**, tapando justo lo que
     * acaba de pedir. No lo cubre ningún test y no lo caza ningún gate.
     */
    public function test_every_drawer_link_closes_the_drawer(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $xpath = $this->xpath($html);
        $drawer = $this->nodes($html, 'mob-menu')[0];

        $sinCierre = [];

        /** @var \DOMElement $link */
        foreach ($xpath->query('.//h:a', $drawer) as $link) {
            if (! str_contains($link->getAttribute('data-alpine-click'), 'menuOpen = false')) {
                $sinCierre[] = $link->getAttribute('href');
            }
        }

        $this->assertSame(
            [], $sinCierre,
            'estos enlaces del cajón móvil no lo cierran al pulsarlos, así que en un ancla de la '.
            "misma página el cajón se queda tapando el destino:\n  ".implode("\n  ", $sinCierre),
        );
    }

    /**
     * **El menú conserva los destinos del parque, en su orden y con sus anclas.**
     *
     * ⚠️ **Se MUDÓ desde `HomePageTest::nav_renders_park_dropdown_with_anchor_items`** al retirar
     * la 2c·1 el desplegable de la barra. El sujeto viejo —el desplegable— murió; lo que
     * comprobaba de verdad —las etiquetas, **el orden** y las anclas— sigue vivo y nadie más lo
     * fijaba. Retirar el test en vez de mudarlo habría perdido la cobertura del orden.
     *
     * ▶ Y aquí se lee **acotado al elemento**: la versión anterior aseveraba sobre la página
     * entera, donde el nombre de una zona lo pinta también la sección de zonas.
     *
     * ⚠️⚠️ **RE-APUNTADO en `#341`, y el motivo importa**: hasta entonces esta guarda aseveraba los
     * literales `'Zona Kids'` y `'Zona Jump'` — o sea que **fijaba en su sitio el catálogo de un
     * cliente dentro del producto**, que es justo la fuga que `#341` cierra. Ahora las dos primeras
     * salen de la BD.
     * ▶ Y **no queda más débil que la que sustituye** (la regla de `#295`): antes fijaba cuatro
     * etiquetas y su orden; ahora fija **las zonas de la instalación en el orden que manda su
     * `position`**, seguidas de los dos destinos fijos — o sea que además caza que el menú y el panel
     * discrepen sobre el orden de las zonas, que antes no miraba nadie.
     */
    public function test_the_menu_keeps_the_park_items_in_order(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $zonas = Zone::query()
            ->where('is_active', true)
            ->where('show_in_landing', true)
            ->orderBy('position')
            ->get()
            ->map(fn (Zone $z): string => (string) $z->tr('name'))
            ->all();

        $this->assertNotEmpty($zonas, 'sin zonas en la landing este caso no vigila nada: el fixture perdió su sujeto');

        $titles = $this->menuTitles($html);
        $parque = [...$zonas, 'Atracciones', 'Ubicación y horario'];

        $this->assertSame(
            $parque,
            array_values(array_intersect($titles, $parque)),
            'los destinos del parque han cambiado de orden o han desaparecido del menú',
        );

        $urls = $this->linksIn($html, 'menu__list');
        foreach (['/#zones', '/#rides', '/#info'] as $anchor) {
            $this->assertContains($anchor, $urls, "el menú ya no ancla a `{$anchor}`");
        }
    }

    /**
     * **El menú conserva los servicios, en el orden que manda la BD.**
     *
     * ⚠️ **Se MUDÓ desde `HomePageTest::nav_renders_services_dropdown_with_section_links`**, por
     * el mismo motivo. El orden lo fija `position` en el panel: si el menú dejara de respetarlo,
     * el parque perdería el control de cómo se presentan sus propios servicios.
     */
    public function test_the_menu_keeps_the_services_in_order(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $titles = $this->menuTitles($html);
        $servicios = ['Cumpleaños', 'Excursiones de colegio', 'Empresas', 'Excursión para mayores', 'Otros eventos'];

        $this->assertSame(
            $servicios,
            array_values(array_intersect($titles, $servicios)),
            'los servicios han cambiado de orden o han desaparecido del menú',
        );

        $urls = $this->linksIn($html, 'menu__list');
        foreach (['excursionescolegio', 'teambuilding', 'sesionadultos', 'eventos'] as $slug) {
            $this->assertContains(
                '/servicios#'.$slug, $urls,
                "el menú ya no lleva a la sección `{$slug}` de servicios",
            );
        }
    }

    /**
     * **El salto al contenido existe en las DOCE vistas, es el primer focusable y aterriza.**
     *
     * Existía en **1 de 12** —la home— y las otras once repiten el mismo bloque de navegación sin
     * ofrecer forma de saltarlo. Es el defecto de accesibilidad más viejo del armazón y la 2c·2
     * es el sitio natural de cerrarlo, porque es cuando el armazón pasa a ser una pieza
     * compartida de verdad.
     *
     * ▶ Se comprueban las TRES cosas, porque cualquiera de ellas sola es un salto roto: que
     * exista, que sea **lo primero que recibe el foco** —si va después del armazón no ahorra
     * nada— y que su destino **exista en la página**.
     */
    public function test_the_skip_link_is_the_first_focusable_and_lands_somewhere(): void
    {
        foreach (array_merge(self::RENDERABLE, ['/no-existe-esta-pagina']) as $path) {
            $html = $this->get($path)->getContent();

            $skip = $this->nodes($html, 'skip-link');
            $this->assertCount(1, $skip, "`{$path}` no ofrece salto al contenido");

            $target = $skip[0]->getAttribute('href');
            $this->assertSame('#main', $target, "`{$path}`: el salto no apunta a `#main`");

            $xpath = $this->xpath($html);
            $this->assertSame(
                1, $xpath->query('//*[@id="main"]')->length,
                "`{$path}`: el salto apunta a `#main` y ahí no hay nada",
            );

            $body = $xpath->query('//h:body')->item(0);
            $primero = $xpath->query('.//h:a[@href] | .//h:button | .//h:input | .//h:select | .//h:textarea', $body)->item(0);

            $this->assertSame(
                'skip-link', $primero?->getAttribute('class'),
                "`{$path}`: el salto al contenido no es el primer focusable, así que no ahorra nada",
            );
        }
    }

    /**
     * **TODOS los `<main>` de las vistas con armazón llevan el ancla, no solo el primero.**
     *
     * ❗ Esto existe por un defecto REAL que cazó el test de arriba: `pages/events` tiene **dos**
     * `<main>` en ramas excluyentes, se parcheó el primero y **la página servía el segundo**. El
     * salto al contenido quedaba apuntando a un ancla que no existía en la página que se ve —y
     * eso no falla, no avisa y solo lo nota quien navega con teclado—.
     * ▶ Un `grep` que mira «el primer `<main>`» da un inventario que parece completo.
     */
    public function test_every_main_landmark_carries_the_skip_target(): void
    {
        $sinAncla = [];

        foreach (self::VIEWS_WITH_ARMAZON as $view) {
            $source = (string) file_get_contents(resource_path('views/'.$view.'.blade.php'));

            preg_match_all('/<main\b[^>]*>/', $source, $matches);

            foreach ($matches[0] as $tag) {
                if (! str_contains($tag, 'id="main"')) {
                    $sinAncla[] = $view.' → '.$tag;
                }
            }
        }

        $this->assertSame(
            [], $sinAncla,
            'estos `<main>` no llevan `id="main"`, así que en la rama que los sirva el salto al '.
            "contenido apunta a la nada:\n  ".implode("\n  ", $sinAncla),
        );
    }

    /**
     * **La barra se ha DISUELTO: queda un contenedor transparente con dos racimos.**
     *
     * Lo que desaparece es la barra, no la navegación. Se comprueba en la HOJA porque es donde
     * vive la decisión, y de las cuatro cosas la última es la que no se ve y más duele:
     * **`pointer-events`**. Sin ella, la franja vacía entre los dos racimos se traga los clics de
     * todo el ancho de la pantalla en sus primeros píxeles — y eso ni falla ni avisa.
     */
    public function test_the_bar_has_dissolved_into_two_clusters(): void
    {
        $nav = $this->ruleBody('.nav');

        foreach (['background', 'backdrop-filter', 'border-bottom'] as $prop) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![-\w])'.preg_quote($prop, '/').'\s*:/', $nav,
                "`.nav` sigue declarando `{$prop}`: la barra no se ha disuelto",
            );
        }

        $this->assertMatchesRegularExpression(
            '/pointer-events:\s*none/', $nav,
            '`.nav` no renuncia a los clics: su franja vacía se traga todo lo que haya debajo',
        );

        $this->assertMatchesRegularExpression(
            '/\.nav__left,\s*\.nav__cta\s*\{[^}]*pointer-events:\s*auto/s', $this->stylesheets(),
            'los dos racimos no recuperan los clics, así que el armazón entero sería inerte',
        );
    }

    /**
     * **El armazón consume la coreografía, y su estado lo decide un módulo que SÍ se prueba.**
     *
     * Aquí solo se comprueba el cableado —que la clase se ate al estado—; las reglas (el umbral,
     * la primera pantalla, el overlay abierto) las cubre `nav-choreography.test.js`, que es donde
     * se pueden ejercitar de verdad. Un test de HTML no puede hacer scroll.
     */
    public function test_the_armazon_is_wired_to_the_choreography(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $nav = $this->nodes($html, 'nav')[0];

        $this->assertStringContainsString(
            'navHidden', $nav->getAttribute('data-bind-class'),
            'el armazón no se ata al estado de la coreografía: no se retiraría nunca',
        );
    }

    /**
     * **El menú a pantalla completa es un overlay accesible — y cerrado NO aporta focos.**
     *
     * ❗❗ **Ésta es la aserción que existe por el defecto del mockup.** Su menú se oculta con un
     * recorte circular y `pointer-events:none`, sin `visibility`, sin `inert` y sin
     * `aria-hidden`: cerrado deja sus enlaces en el orden de tabulación. Aquí el recorte es la
     * animación y **`visibility` es el estado**, que es el mecanismo que el cajón ya tenía.
     * Se comprueba en la HOJA, no en el marcado, porque es donde vive la decisión.
     */
    public function test_the_menu_is_an_accessible_overlay_that_leaves_no_focus_when_closed(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $panel = $this->nodes($html, 'menu__inner')[0];

        $this->assertSame('dialog', $panel->getAttribute('role'), 'el menú no es un diálogo');
        $this->assertSame('true', $panel->getAttribute('aria-modal'), 'el menú no se anuncia como modal');
        $this->assertNotSame('', $panel->getAttribute('aria-label'), 'el menú no tiene nombre accesible');
        $this->assertSame('menuPanel', $panel->getAttribute('x-ref'), 'sin referencia al panel no hay trap de foco ni foco inicial');

        $menu = $this->nodes($html, 'menu')[0];
        $this->assertNotSame('', $menu->getAttribute('data-alpine-keydown'), 'el menú no atrapa el foco');
        $this->assertNotSame('', $menu->getAttribute('data-alpine-keydown.escape.window'), 'Escape no cierra el menú');

        $css = $this->stylesheets();

        $this->assertMatchesRegularExpression(
            '/\.menu\s*\{[^}]*visibility:\s*hidden/s', $css,
            'el menú CERRADO no se oculta con `visibility`: sus enlaces se quedarían en el orden '.
            'de tabulación, que es exactamente el defecto del mockup.',
        );
        $this->assertMatchesRegularExpression(
            '/\.menu--open\s*\{[^}]*visibility:\s*visible/s', $css,
            'el menú ABIERTO no se declara visible: no sería alcanzable con el teclado',
        );
    }

    /**
     * **El menú declara superficie de TINTA.**
     *
     * Es el segundo consumidor del mecanismo de la tanda 1, después del hero. Si dejara de
     * declararla, los siete tokens de superficie volverían a los del papel y **todo lo de dentro
     * se pintaría claro sobre claro** sin que fallara nada: es el defecto que `#194` cazó tres
     * veces con el scrim, el texto y el placeholder del hero.
     */
    public function test_the_menu_declares_the_ink_surface(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(
            'ink', $this->nodes($html, 'menu')[0]->getAttribute('data-surface'),
            'el menú ha dejado de declarar superficie de tinta',
        );
    }

    /**
     * **EL MENÚ SE PUEDE CERRAR CON EL RATÓN — y hasta el 2026-08-28 no se podía.**
     *
     * ⚠️⚠️ La hamburguesa hacía `menuOpen = true` a secas. El menú es `inset: 0` y tapa la página
     * entera, así que las ÚNICAS salidas eran `Escape` y pulsar un destino: quien usa el ratón se
     * quedaba encerrado. **Lo cazó el owner mirando la pantalla; ninguna de las 31 aserciones del
     * armazón lo veía**, porque todas comprobaban que el menú se ABRE.
     *
     * Se aseveran las tres mitades, porque con dos parece que funciona: que el botón ALTERNE, que
     * DIGA en qué estado está (`aria-expanded`, lo único que ve quien no ve el dibujo) y que su
     * nombre accesible cambie — el mockup del cliente dice «Abrir menú» incluso estando abierto, y
     * eso es lo único suyo que aquí no se copia.
     */
    public function test_the_burger_opens_and_closes_and_says_which(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $burger = $this->nodes($html, 'nav__burger')[0];

        $this->assertStringContainsString(
            'menuOpen = ! menuOpen', $burger->getAttribute('data-alpine-click'),
            'la hamburguesa ha dejado de ALTERNAR. Si solo abre, el menú a pantalla completa no '.
            'tiene salida con el ratón: tapa la página entera.',
        );

        $this->assertNotSame(
            '', $burger->getAttribute('data-bind-aria-expanded'),
            'la hamburguesa no publica `aria-expanded`: es un revelador, y sin eso quien navega '.
            'con lector de pantalla no sabe si lo que va a pulsar abre o cierra.',
        );

        $this->assertNotSame(
            '', $burger->getAttribute('data-bind-aria-label'),
            'el nombre accesible de la hamburguesa no cambia con el estado: diría «abrir menú» '.
            'estando abierto, que es justo el defecto que el mockup del cliente tiene.',
        );

        $this->assertNotSame(
            '', $burger->getAttribute('aria-label'),
            'sin `aria-label` estático el botón se queda SIN NOMBRE cuando no hay JavaScript.',
        );
    }

    /**
     * **Y la X se FORMA mientras está abierto: las dos rayas GIRAN.**
     *
     * ⚠️⚠️ **RE-APUNTADO en `#217`, y con un coste que este comentario tiene que decir.** Este
     * caso exigía DOS glifos del set de iconos (`x-icons.menu` / `x-icons.close`), porque el
     * dibujo es uno de los tres mecanismos del tema y una instalación tiene que poder
     * sustituirlo. `[DECIDIDO owner, 2026-08-28]`: **gana el mockup**, que dibuja dos rayas y las
     * ROTA — una animación que dos SVG intercambiados no pueden dar—. El hueco de icono por
     * instalación **se pierde en esta pieza**; ficha en `DEUDA.md`.
     * ▶ Lo que el caso protege NO cambia: con el menú abierto el botón enseña un aspa, y lo hace
     * **sin JavaScript de dibujo** —lo alterna el CSS por `.nav--over`—, así que sigue
     * funcionando si Alpine no arranca.
     */
    public function test_the_burger_forms_a_close_glyph_while_open(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        $burger = $this->nodes($html, 'nav__burger')[0];
        $rayas = [];

        foreach ($this->xpath($html)->query('.//h:span', $burger) as $span) {
            if (str_contains($span->getAttribute('class'), 'nav__burger-bar ')) {
                $rayas[] = $span->getAttribute('class');
            }
        }

        $this->assertCount(2, $rayas, 'la hamburguesa dibuja DOS rayas, y dibuja '.count($rayas));

        $css = (string) file_get_contents(public_path('css/landing.css'));

        // ⚠️ Se exige el GIRO de las DOS, no que exista «una regla bajo `.nav--over`»: con una
        // sola girando, el aspa sale con un palo torcido y la guarda pasaría igual.
        foreach ([
            '.nav--over .nav__burger-bar--top' => 'rotate(45deg)',
            '.nav--over .nav__burger-bar--bottom' => 'rotate(-45deg)',
        ] as $selector => $giro) {
            $cuerpo = (string) ($this->cssRules(public_path('css/landing.css'))[$selector] ?? '');
            $this->assertStringContainsString(
                $giro, $cuerpo,
                "`{$selector}` no gira con el menú abierto: el aspa no se forma.",
            );
        }

        // Y el dibujo no puede depender del JavaScript: lo alterna la clase que el armazón ya pone.
        $this->assertStringContainsString('.nav--over .nav__burger-bar--top', $css);
    }

    /**
     * **Con el menú abierto SIEMPRE hay un botón de comprar — y una salida.**
     *
     * ⚠️⚠️ Otro agujero que solo se ve mirando: en la portada el armazón **nace oculto** y lo
     * destapa el scroll. Abrir el menú desde arriba del todo dejaba la pantalla entera —el menú
     * tapa la página— **sin un solo sitio donde comprar**.
     * ▶ Medido en el mockup del 2.º cliente: su menú tampoco lleva CTA propio; usa el de la
     * cabecera, que en el suyo está SIEMPRE visible. La diferencia era ésa, no el botón.
     *
     * ⚠️ **RE-APUNTADO en la 2c·8** (`#216`), no retirado: el sujeto sigue vivo y ahora hay MÁS en
     * juego. Antes se forzaba un botón (`.nav-cta-med`); ahora se fuerza el RACIMO ENTERO
     * (`.nav__cta`), porque desde esta tanda la hamburguesa nace oculta con él — o sea que sin
     * esta regla el menú no solo se quedaría sin compra: se quedaría **sin la X para cerrarlo**,
     * que es justo el defecto que `#211` cerró por otra puerta.
     */
    public function test_the_open_menu_always_offers_the_purchase(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(public_path('css/site.css')));

        // ⚠️ Se asevera el SELECTOR COMPLETO y lo que declara, no una subcadena: con
        // `assertStringContainsString` un selector mal escrito —`.nav-cta-med-NO`— **contiene** la
        // cadena buscada y la guarda pasaba. Lo demostró la mutación, que era el único modo de
        // verlo: el test estaba verde con el CSS roto.
        $encontrada = null;

        foreach (preg_split('/(?<=\})/', $css) ?: [] as $trozo) {
            if (! preg_match('/([^{}]*)\{([^{}]*)\}\s*$/', $trozo, $m)) {
                continue;
            }

            foreach (explode(',', $m[1]) as $selector) {
                if (preg_replace('/\s+/', ' ', trim($selector)) === 'body[data-has-hero] .nav--over .nav__cta') {
                    $encontrada = $m[2];
                }
            }
        }

        $this->assertNotNull(
            $encontrada,
            "Nada revela el racimo del armazón cuando el menú está abierto.\n".
            "▶ En la portada nace oculto bajo el hero, y el menú es `inset: 0`: sin esta regla,\n".
            '  abrir el menú desde arriba deja la pantalla sin comprar Y SIN LA X DE CERRAR.',
        );

        foreach (['opacity: 1', 'pointer-events: auto'] as $declaracion) {
            $this->assertStringContainsString(
                $declaracion, (string) $encontrada,
                "la regla existe pero no declara `{$declaracion}`: el racimo seguiría oculto o sin ".
                'poder pulsarse con el menú abierto.',
            );
        }
    }

    /**
     * **El CTA del armazón CAMBIA DE ROL dentro del menú: de tinta a AVISO** (`#213`).
     *
     * `[DECIDIDO owner, 2026-08-28]` con las tres fuentes del cliente delante, que **no coinciden**:
     * su implementación pinta ese botón de aviso mientras el menú lo tapa, su norma de color dice
     * que el amarillo «nunca» es fondo de botón, y su tabla de orden de página dice que la cabecera
     * domina con el color de acción. Gana la implementación, por decisión suya.
     *
     * ⚠️⚠️ **Y el anillo de foco se asevera aquí porque es la parte que se rompe sola.**
     * `--focus-color` sigue a la superficie y en el paquete de este cliente vale su **mismo**
     * Amarillo Aviso: sin la línea que lo cambia, el foco de teclado sobre este botón es
     * **invisible** — y eso no falla, no avisa y solo lo nota quien no usa ratón.
     */
    public function test_the_frame_cta_turns_into_a_warning_inside_the_menu(): void
    {
        $reglas = $this->cssRules(public_path('css/site.css'));

        $this->assertArrayHasKey(
            '.nav[data-surface="ink"] .cta-med', $reglas,
            'el CTA del armazón ya no cambia de color dentro del menú: se quedaría en tinta sobre '.
            'la tinta del menú, que es un botón invisible.',
        );

        $this->assertStringContainsString('var(--attn)', $reglas['.nav[data-surface="ink"] .cta-med']);
        $this->assertStringContainsString('var(--paper-fg)', $reglas['.nav[data-surface="ink"] .cta-med']);

        $this->assertArrayHasKey(
            '.nav[data-surface="ink"] .cta-med:focus-visible', $reglas,
            "El botón pasa a AVISO dentro del menú y NADIE cambia su anillo de foco.\n".
            "▶ `--focus-color` sigue a la superficie, y en el paquete de este cliente vale su MISMO\n".
            "  Amarillo Aviso: amarillo sobre amarillo. El foco de teclado desaparece sin que nada\n".
            '  falle. Es la misma clase de defecto que `#206` encontró en su paleta.',
        );
    }

    /**
     * **Y su FORMA es la del CTA fijo del mockup** (`#213`): rótulo en la fuente de rótulo y en
     * mayúsculas, sin flecha, y subtítulo que HEREDA el color en vez de calcularlo.
     *
     * ⚠️ Lo del subtítulo no es estilo: el botón pinta de **tres** colores distintos —tinta, marca
     * al pasar el cursor y aviso dentro del menú— así que cualquier token elegido para su texto
     * fallaría en dos de los tres. Heredar y bajar la opacidad es lo único que vale en los tres.
     */
    public function test_the_frame_cta_has_the_shape_of_the_mockup(): void
    {
        $reglas = $this->cssRules(public_path('css/site.css'));

        $this->assertStringContainsString(
            'var(--font-display)', $reglas['.cta-med__t'] ?? '',
            'el rótulo del CTA ha dejado de ir en la fuente de rótulo. ⚠️ Y va por TOKEN, no por '.
            'nombre: así una instalación la cambia con `THEME_FONTS` y esto la sigue.',
        );
        $this->assertStringContainsString('uppercase', $reglas['.cta-med__t'] ?? '');

        $this->assertStringContainsString(
            'color: inherit', $reglas['.cta-med__s'] ?? '',
            'el subtítulo vuelve a calcular su color con un token: fallaría en dos de los tres '.
            'colores que este botón puede tener.',
        );

        foreach (['.cta-med__arrow', '.cta-med:hover .cta-med__arrow'] as $muerta) {
            $this->assertArrayNotHasKey(
                $muerta, $reglas,
                "`{$muerta}` ha vuelto: el CTA fijo del mockup NO lleva flecha, y una regla sin ".
                'marcado que la use es peso muerto en las 12 vistas.',
            );
        }

        $this->assertStringNotContainsString(
            'cta-med__arrow',
            (string) file_get_contents(resource_path('views/components/site/nav.blade.php')),
            'la flecha ha vuelto al marcado del CTA.',
        );
    }

    /**
     * **EL RACIMO DE LA CABECERA ES UN PAR: uno ancho y el otro reducido a su icono** (2c·7).
     *
     * `[DECIDIDO owner, 2026-08-28]`, como el mockup. Primer clic en la colapsada → la expande;
     * segundo → actúa. Se asevera lo que puede romperse por separado:
     *
     *  1. **Que exista el envoltorio con sus dos clases**, que es lo único que reparte los anchos.
     *  2. **Que el estado esté COMPARTIDO** con la barra de móvil. Son la misma decisión, y con dos
     *     copias el visitante que estrecha la ventana vería otra mitad expandida sin haber tocado
     *     nada.
     *  3. **Que el nombre accesible de CADA mitad alterne.** Un botón que cambia de significado al
     *     pulsarlo se pulsa por error; colapsado tiene que llamarse «cambiar a…», no «mi cuenta».
     */
    public function test_the_header_cluster_is_a_double_cta(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $par = $this->nodes($html, 'nav__pair');

        $this->assertCount(1, $par, 'el racimo de la cabecera ha dejado de ser un PAR');

        $clases = $par[0]->getAttribute('data-bind-class');

        // ⚠️ Los modificadores de estado se renombraron en `#225`: pasan de llevar el nombre del
        // SITIO (`nav__pair--…`) al del COMPONENTE (`cta-pair--…`), porque desde esa tanda el par
        // vive en dos sitios —el racimo de la cabecera y la barra de móvil— y son las MISMAS
        // reglas de intercambio. Con el nombre del sitio habría que duplicarlas, que es como se
        // llegó a tener dos botones que debían ser iguales y no lo eran.
        foreach (['cta-pair--account', 'cta-pair--invita'] as $marca) {
            $this->assertStringContainsString(
                $marca, $clases,
                "el par no publica `{$marca}`: sin esa clase el CSS no sabe qué mitad es ancha ".
                '(o la invitación no se apaga nunca).',
            );
        }

        // ⚠️ Se asevera la EXPRESIÓN COMPLETA, no que la cadena `$store.ctaPair` aparezca en algún
        // sitio del atributo: la primera versión pasaba con el reparto de anchos leyendo una
        // variable local, porque la invitación —en el mismo atributo— sí usaba el store. Lo
        // demostró la mutación.
        $this->assertStringContainsString(
            "\$store.ctaPair.mode === 'account'", $clases,
            'el reparto de anchos lee un estado LOCAL en vez del store compartido con la barra de '.
            'móvil: son la misma decisión y con dos copias se separan solas.',
        );

        // Las DOS mitades alternan su nombre accesible.
        foreach (['nav-cta-med', 'nav-cta-ghost'] as $mitad) {
            $nodo = $this->nodes($html, $mitad)[0];

            // ⚠️ Se lee el ATRIBUTO del nodo, no el fuente del Blade: la primera versión de esta
            // comprobación devolvía lo mismo para las dos mitades —buscaba `cta_switch` en el
            // fichero entero— y habría pasado con una sola de las dos cableada. Una aserción que
            // no distingue sus casos no asevera nada.
            $this->assertStringContainsString(
                __('landing.nav.'.($mitad === 'nav-cta-med' ? 'cta_switch_buy' : 'cta_switch_signup')),
                $nodo->getAttribute('data-bind-aria-label'),
                "la mitad `{$mitad}` no ofrece el rótulo de «cambiar a…» cuando está colapsada",
            );
            $this->assertNotSame(
                '', $nodo->getAttribute('data-bind-aria-label'),
                "la mitad `{$mitad}` no alterna su nombre accesible: colapsada diría a dónde lleva ".
                'en vez de decir qué hace, y se pulsaría por error.',
            );
            $this->assertNotSame(
                '', $nodo->getAttribute('aria-label'),
                "la mitad `{$mitad}` se queda sin nombre accesible cuando no hay JavaScript.",
            );
        }
    }

    /**
     * **La mitad de CUENTA dice que también se inicia sesión, y lo dice en los TRES sitios**
     * (`[DECIDIDO owner, 2026-09-02]`, `#351`).
     *
     * ⚠️⚠️ **Nace de que nadie lo vigilaba.** El subtítulo existía desde `#227` pero se pintaba
     * **solo en la barra de móvil** (`@if ($esBarra)`), así que en la cabecera y en el hero el botón
     * decía únicamente «Registrarse» — y quien ya tenía cuenta no se veía invitado, que es
     * exactamente lo que el owner reportó. Al quitar esa condición no se puso rojo ni un test.
     *
     * ⚠️ **Y va en el SUBTÍTULO y no en el rótulo porque está MEDIDO**: el botón del nav tiene 224 px
     * fijos y «Entrar o registrarse» se sale **58** (el del hero, 8). El subtítulo no compite por ese
     * ancho — la fila ya sabe pintar dos líneas, que es lo que hace la mitad de comprar con el precio.
     */
    public function test_the_account_half_says_it_also_logs_you_in_everywhere(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $subtitulo = __('landing.nav.cta_switch_signup_sub');

        $this->assertNotSame('', trim($subtitulo), 'el subtítulo de la mitad de cuenta se ha quedado vacío');

        foreach (['nav-cta-ghost', 'hero-cta-ghost', 'book-bar__cta--alt'] as $mitad) {
            $nodo = $this->nodes($html, $mitad)[0] ?? null;

            $this->assertNotNull($nodo, "no se encuentra la mitad `{$mitad}` en la portada");
            $this->assertStringContainsString(
                $subtitulo, $nodo->textContent,
                "La mitad `{$mitad}` no dice que este botón también inicia sesión.\n".
                "▶ Se pintaba SOLO en la barra de móvil hasta `#351`, y el owner reportó que en la\n".
                "  cabecera el botón parecía servir solo para registrarse.\n".
                '▶ Si estorba en un sitio, se decide con el owner — no se vuelve a poner `@if ($esBarra)`.'
            );
        }
    }

    /**
     * **Las tres ramas del par son enlaces de verdad: sin JavaScript, un solo clic actúa.**
     *
     * ⚠️ Con `<button>` el doble paso no degrada: **no hace nada**. Las tres puertas existen
     * (`/entradas`, `/registro` o la URL del parque, `/mi-cuenta`), así que el clic central, «abrir
     * en pestaña nueva» y un navegador sin JS acaban en la misma pantalla por el camino largo.
     * La rama con sesión era la ÚNICA que seguía siendo un `<button>` y se arregla en la 2c·7.
     */
    public function test_every_half_of_the_pair_works_without_javascript(): void
    {
        $sinSesion = $this->get('/')->assertOk()->getContent();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $conSesion = $this->actingAs($user)->get('/')->assertOk()->getContent();

        foreach ([['invitado', $sinSesion], ['con sesión', $conSesion]] as [$caso, $html]) {
            foreach (['nav-cta-med', 'nav-cta-ghost'] as $mitad) {
                $nodo = $this->nodes($html, $mitad)[0];

                $this->assertSame(
                    'a', strtolower($nodo->nodeName),
                    "({$caso}) la mitad `{$mitad}` no es un enlace: sin JavaScript no haría NADA.",
                );
                $this->assertNotSame(
                    '', $nodo->getAttribute('href'),
                    "({$caso}) la mitad `{$mitad}` es un enlace sin destino.",
                );
            }
        }
    }

    /**
     * **La invitación existe, se apaga al tocar el par y no corre con `prefers-reduced-motion`.**
     *
     * ⚠️ Las tres mitades importan por separado. Sin la primera nadie descubre que el botón
     * colapsado se puede expandir; sin la segunda la página late para siempre y pasa de invitar a
     * molestar; sin la tercera se ignora una preferencia del sistema que existe **porque a alguna
     * gente el movimiento en bucle le produce náuseas**.
     */
    public function test_the_invitation_stops_when_touched_and_respects_reduced_motion(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(public_path('css/site.css')));

        foreach (['@keyframes cta-asoma', '@keyframes cta-aro'] as $paso) {
            $this->assertStringContainsString($paso, $css, "falta la animación `{$paso}` de la invitación");
        }

        // ⚠️ Las DOS piezas por separado. El regex genérico de la primera versión lo cumplía el
        // ARO, así que retirar el asomo dejaba la guarda verde: media invitación es la que no se
        // ve, porque el aro late alrededor de un botón que ya no se mueve.
        foreach (['cta-pair__alt > .cta-ghost' => 'cta-asoma', 'cta-pair__alt-ring' => 'cta-aro'] as $quien => $paso) {
            $this->assertMatchesRegularExpression(
                '/\.cta-pair--invita \.'.preg_quote($quien, '/').'[^{]*\{[^}]*animation:\s*'.$paso.'/', $css,
                "la invitación ha perdido su mitad `{$paso}`: sin ella el par de dos pasos no se ".
                'descubre solo.',
            );
        }

        $reduce = preg_split('/@media \(prefers-reduced-motion: reduce\)/', $css);
        $apagada = false;

        foreach (array_slice($reduce, 1) as $bloque) {
            if (str_contains(substr($bloque, 0, 400), 'cta-pair--invita') && str_contains(substr($bloque, 0, 400), 'animation: none')) {
                $apagada = true;
            }
        }

        $this->assertTrue(
            $apagada,
            "La invitación NO se apaga con `prefers-reduced-motion`.\n".
            '▶ Es decoración en bucle: aquí «reducir» es «no hacerlo», no «hacerlo más despacio».',
        );

        // Y que el apagado dependa de haberlo TOCADO, no de un temporizador.
        $this->assertStringContainsString(
            '! $store.ctaPair.touched',
            $this->nodes($this->get('/')->assertOk()->getContent(), 'nav__pair')[0]->getAttribute('data-bind-class'),
            'la invitación no mira si alguien ya ha usado el par: seguiría llamando después de que '.
            'le hayan hecho caso.',
        );
    }

    /**
     * **Todo enlace del menú lo cierra al pulsarlo.**
     *
     * Mismo motivo que en el cajón: la mitad de los destinos son anclas de la MISMA página, y sin
     * esto el visitante pulsa «Zona Kids», la página salta a la sección y **el menú se queda a
     * pantalla completa encima**, tapando justo lo que acaba de pedir.
     */
    public function test_every_menu_link_closes_the_menu(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $sinCierre = [];

        /** @var \DOMElement $link */
        foreach ($xpath->query('.//h:a', $this->nodes($html, 'menu__list')[0]) as $link) {
            if (! str_contains($link->getAttribute('data-alpine-click'), 'menuOpen = false')) {
                $sinCierre[] = $link->getAttribute('href');
            }
        }

        $this->assertSame([], $sinCierre, 'estos enlaces del menú no lo cierran: '.implode(', ', $sinCierre));
    }

    /**
     * **El número de cada destino es DECORACIÓN, no parte de su nombre.**
     *
     * Sin `aria-hidden`, un lector de pantalla anuncia «cero uno Cumpleaños» en los diez.
     */
    public function test_the_menu_numbers_are_decoration(): void
    {
        // `[DECIDIDO owner, 2026-09-01]` (lanzamiento): los números «01…» SE RETIRARON del menú.
        // Este caso vigilaba que fueran decoración (aria-hidden); ahora vigila que no vuelvan —
        // un número delante de cada destino era ruido y el owner lo quiso fuera.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame([], $this->nodes($html, 'menu__n'), 'el menú vuelve a numerar sus destinos');
        $this->assertNotEmpty($this->nodes($html, 'menu__t'), 'el menú no tiene destinos: el localizador se ha roto');
    }

    /**
     * **El racimo de acción cambia con la sesión, y el chip lleva su nombre accesible en TEXTO.**
     *
     * Lo segundo es lo que la 2c está a punto de tocar: el saludo visible desaparece al pasar a
     * icono, y el nombre accesible **no puede irse con él**.
     */
    public function test_the_action_cluster_swaps_between_guest_and_member(): void
    {
        $guest = $this->get('/')->assertOk()->getContent();

        $this->assertCount(1, $this->nodes($guest, 'nav-cta-ghost'), 'un invitado no ve la puerta de alta');
        $this->assertCount(0, $this->nodes($guest, 'nav__acct'), 'un invitado ve el chip de cuenta');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $member = $this->actingAs($user)->get('/')->assertOk()->getContent();

        // ⚠️ **RE-APUNTADO en la 2c·7, y el sujeto NO cambia.** Antes se comprobaba por la CLASE
        // `nav-cta-ghost`, que con sesión no existía. Desde que la cuenta es **una mitad del PAR**
        // lleva esa misma clase —su caja es la del botón fantasma— así que contarla ya no dice
        // nada. Lo que había que comprobar sigue siendo lo mismo y ahora se comprueba de verdad:
        // **con sesión no se ofrece el alta**, es decir, ninguna mitad apunta a `/registro`.
        $this->assertCount(1, $this->nodes($member, 'nav-cta-ghost'), 'con sesión desaparece la mitad de cuenta del par');

        foreach ($this->nodes($member, 'nav-cta-ghost') as $mitad) {
            $this->assertStringNotContainsString(
                '/registro', $mitad->getAttribute('href'),
                'con sesión el par sigue ofreciendo el ALTA: esa mitad tiene que llevar a la cuenta.',
            );
        }

        $this->assertCount(1, $this->nodes($member, 'nav__acct'), 'con sesión no aparece el chip de cuenta');
        $this->assertCount(1, $this->nodes($member, 'nav-cta-med'), 'con sesión desaparece el CTA de compra');

        $chip = $this->nodes($member, 'nav__acct')[0];
        $this->assertNotSame('', $chip->getAttribute('aria-label'), 'el chip de cuenta se queda sin nombre accesible');
    }

    /**
     * **El botón de cuenta conserva su nombre accesible EN TEXTO, y el aviso también.**
     *
     * ❗ Es lo único que no podía perderse al retirar el saludo visible (tanda 2c·3): el texto
     * «Hola, nombre» **era** el nombre accesible del botón. Al quedarse en icono, ese nombre solo
     * existe en el `aria-label` — si alguien lo borra «porque el icono ya se entiende», el botón
     * se queda mudo para quien no ve el icono, y no falla nada.
     * ▶ Y el aviso de formulario pendiente tiene que estar **en el nombre, no solo en el color**:
     * un punto amarillo no lo lee nadie.
     */
    public function test_the_account_button_keeps_its_accessible_name_in_text(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'Marta']);
        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        $chip = $this->nodes($html, 'nav__acct')[0];

        $this->assertStringContainsString(
            'Marta', $chip->getAttribute('aria-label'),
            'el nombre accesible del botón de cuenta ya no dice de quién es la cuenta',
        );

        // ⚠️ **RE-APUNTADO en la 2c·7, y era lo que el propio mensaje anterior pedía.** El botón
        // recuperó texto visible —«Mi cuenta», FIJO— al convertirse en una mitad expandible del
        // par. `#204` lo había retirado porque el saludo variable («Hola, Marta» / «Hola,
        // Wilhelmina») desalineaba el racimo; un rótulo fijo no tiene ese problema.
        // ▶ Lo que NO se puede perder es «label in name» (WCAG 2.5.3): si hay texto visible, el
        // nombre accesible tiene que EMPEZAR por él, o quien dicta por voz «pulsa Mi cuenta» no
        // activa nada.
        $visible = trim((string) preg_replace('/\s+/', ' ', $chip->textContent));

        $this->assertNotSame('', $visible, 'la mitad de cuenta se ha quedado sin rótulo que expandir');
        $this->assertStringStartsWith(
            $visible, $chip->getAttribute('aria-label'),
            "El texto visible «{$visible}» NO es prefijo del nombre accesible.\n".
            '▶ «Label in name» (WCAG 2.5.3): quien dicta por voz lee lo que VE, y si el nombre '.
            'accesible empieza por otra cosa, el comando no activa el botón.',
        );
    }

    /**
     * **El selector de idioma es UN COMPONENTE — y sin JavaScript sigue habiendo una puerta.**
     *
     * ⚠️⚠️ **CORRECCIÓN, y va delante del texto que corrige** (`#233`, 2026-08-28). Este caso
     * exigía que el selector viviera en UN SOLO SITIO y que **no** estuviera en el pie, por la
     * decisión de `#205`: «dos selectores del mismo idioma son dos sitios que mantener y uno que
     * se queda atrás». Era verdad **mientras fueran dos copias**.
     *
     * `[DECIDIDO owner, 2026-08-28]`: «el footer tiene menos elementos». El mockup lo tiene en los
     * DOS —las cápsulas del menú y el bloque inferior del pie—, y la respuesta correcta a «esto
     * está en dos sitios» no es retirarlo de uno: es que haya **una sola definición**. Con
     * `<x-site.lang-switch>` el coste que `#205` temía no existe.
     *
     * ▶ Por eso lo que se asevera ahora es la CAUSA —una definición— y no el número de sitios: si
     * mañana hace falta un tercero, este caso no estorba; si alguien copia y pega el marcado, salta.
     *
     * ❗ **El `<noscript>` NO se retira con la vuelta del selector**: el desplegable lo abre Alpine,
     * así que sin JavaScript sigue sin abrirse — en el pie igual que en el menú. Los dos hacen
     * falta y por motivos distintos.
     */
    public function test_the_language_switcher_has_one_definition_and_a_no_js_floor(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // ⚠️⚠️ **VUELVE A ESTAR EN UN SOLO SITIO** (`#253`, `[DECIDIDO owner, 2026-08-29]`: «quita
        // el selector de idioma del footer, ya lo tenemos en el menú»). No es un regreso a `#205`
        // por su razón —el coste de las dos copias sigue sin existir, porque hay un componente—:
        // es que **el pie es la mitad de la composición del punto estático del cierre** y esa
        // composición no cabía en la ventana. Lo que se retira aquí es alto que se recupera allí.
        $this->assertNotEmpty(
            $this->within($html, 'menu__chips', 'lang-dd'),
            'el selector de idioma no está en las cápsulas del menú, que es su único sitio.',
        );
        $this->assertEmpty(
            $this->within($html, 'foot', 'lang-dd'),
            "el selector de idioma ha vuelto al pie.\n".
            '▶ `[DECIDIDO owner]` vive solo en el menú. El pie tiene que caber junto a la tarjeta '.
            'del cierre en una pantalla, y cada pieza suya cuenta.',
        );

        // …y los dos salen de la MISMA definición. Esto es lo que de verdad protege.
        $this->assertFileExists(
            resource_path('views/components/site/lang-switch.blade.php'),
            'ha desaparecido el componente del selector de idioma.',
        );
        // ⚠️ **Los COMENTARIOS no cuentan, y este contador ya salió mal por eso**: dio «3 de 2»
        // porque el propio comentario que explica el cambio nombra `<x-site.lang-switch>`. Es la
        // misma trampa que `#226` pagó contando clases del menú: *un `grep` que SÍ encuentra
        // tampoco demuestra que la cosa exista donde crees*.
        $usos = 0;
        foreach (['menu', 'footer'] as $vista) {
            $blade = (string) file_get_contents(resource_path("views/components/site/{$vista}.blade.php"));
            $blade = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $blade);
            $usos += substr_count($blade, '<x-site.lang-switch');
        }
        $this->assertSame(
            1, $usos,
            "El selector de idioma no se sirve del componente ({$usos} usos, se espera 1).\n".
            '▶ La regla no es «que esté en un sitio»: es que haya UNA definición. Dos copias del '.
            'marcado son dos listas de idiomas, y así se acaba ofreciendo uno que la otra no tiene.',
        );

        $xpath = $this->xpath($html);
        $noscript = $xpath->query('//h:noscript[.//h:a[contains(@href, "/lang/")]]');

        $this->assertGreaterThan(
            0, $noscript->length,
            'no queda ningún cambio de idioma sin JavaScript: el desplegable necesita Alpine para '.
            'abrirse, en el pie igual que en el menú.',
        );
    }

    /**
     * **El menú lleva el eslogan, y sale de la MISMA clave que el del hero.**
     *
     * `[DECIDIDO owner, 2026-08-27]`: «la idea es 1:1 al mockup». Su auditoría lo marcaba como
     * repetido (`T-02`, «máx. una vez por página»), y no lo incumple: el menú es `inset: 0` y tapa
     * el hero entero, así que **los dos nunca están en pantalla a la vez**.
     * ▶ Y sale de la misma clave a propósito: dos claves para el mismo copy son dos copys que se
     * separan solos.
     */
    public function test_the_menu_slogan_reuses_the_hero_key(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $slogan = $this->nodes($html, 'menu__slogan');
        $this->assertCount(1, $slogan, 'el menú no lleva eslogan');

        $this->assertSame(
            trim((string) __('landing.hero.kicker')), trim($slogan[0]->textContent),
            'el eslogan del menú ya no sale de la misma clave que el del hero',
        );
    }

    /**
     * **La barra de móvil es un CTA DOBLE, y sus dos mitades llevan a sitios distintos.**
     *
     * `[DECIDIDO owner, 2026-08-27]`. Arranca con **comprar expandido**, y eso es la jerarquía:
     * comprar cuesta un gesto y registrarse dos.
     */
    public function test_the_mobile_bar_is_a_double_cta(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertCount(2, $this->nodes($html, 'book-bar__cta'), 'la barra de móvil no tiene dos mitades');
        $this->assertCount(1, $this->nodes($html, 'book-bar__cta--buy'), 'falta la mitad de comprar');
        $this->assertCount(1, $this->nodes($html, 'book-bar__cta--alt'), 'falta la mitad de la cuenta');

        $destinos = $this->linksIn($html, 'book-bar__pair');
        sort($destinos);

        $this->assertSame(
            ['/entradas', '/registro'], $destinos,
            'las dos mitades no llevan a destinos distintos y reales',
        );
    }

    /**
     * **La mitad COLAPSADA dice qué hace AHORA, no a dónde lleva.**
     *
     * ❗❗ Es la aserción que existe por el riesgo del propio patrón: **un botón que cambia de
     * significado al pulsarlo se pulsa por error**. Colapsada, la pulsación no lleva a ninguna
     * parte: expande. Si su nombre siguiera diciendo «Registrarse», un lector de pantalla
     * anunciaría dos botones que dicen lo mismo y hacen cosas distintas, y el segundo no llevaría
     * a donde dice.
     */
    public function test_the_collapsed_half_says_what_it_does_now(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['book-bar__cta--buy', 'book-bar__cta--alt'] as $mitad) {
            $binding = $this->nodes($html, $mitad)[0]->getAttribute('data-bind-aria-label');

            $this->assertNotSame('', $binding, "`{$mitad}` no cambia de nombre accesible al colapsarse");
            $this->assertStringContainsString(
                __('landing.nav.cta_switch_buy') === $binding ? '' : 'Cambiar', $binding,
                "`{$mitad}`: su nombre colapsado no dice que la pulsación CAMBIA de CTA",
            );
        }
    }

    /**
     * **Sin JavaScript las dos mitades siguen funcionando, y con UNA pulsación.**
     *
     * El doble paso es de Alpine; sin él, cada mitad es un `<a href>` que navega. Por eso el
     * nombre accesible **servido** es el de ACTUAR —que es lo que hacen sin JS— y Alpine lo
     * sustituye por el de «cambiar» solo en la que quede colapsada. Al revés, quien navega sin JS
     * leería «Cambiar a registrarse» en un enlace que se registra.
     */
    public function test_both_halves_work_without_javascript(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['book-bar__cta--buy', 'book-bar__cta--alt'] as $mitad) {
            $node = $this->nodes($html, $mitad)[0];

            // ⚠️ En el DOM de HTML5 `nodeName` viene en MAYÚSCULAS; se normaliza, como ya hace su vecino.
            $this->assertSame('a', strtolower($node->nodeName), "`{$mitad}` no es un enlace: sin JS no haría nada");
            $this->assertNotSame('', $node->getAttribute('href'), "`{$mitad}` no tiene destino");
            $this->assertNotSame('#', $node->getAttribute('href'), "`{$mitad}` apunta a la nada");
            $this->assertStringNotContainsString(
                'Cambiar', $node->getAttribute('aria-label'),
                "`{$mitad}`: el nombre SERVIDO dice «cambiar», y sin JS ese enlace NAVEGA",
            );
        }
    }

    /**
     * **Por defecto —y por tanto también sin JavaScript— la mitad ancha es la de comprar.**
     *
     * El reparto lo decide el CSS a partir de una clase en el contenedor; el JS solo publica el
     * estado. Si el reparto viviera en el `.js`, sería la única parte del tema que un cliente no
     * puede tocar desde su hoja.
     */
    public function test_the_pair_defaults_to_buy_expanded(): void
    {
        $css = $this->stylesheets();

        // ⚠️ **El MECANISMO cambió en `#225` y estas dos aserciones lo siguen.** Antes el reparto
        // era `flex: 1 1 auto` contra `flex: 0 0 auto`, que no se puede ANIMAR: el ancho iba de
        // `auto` a `auto` y el intercambio se fingía plegando el rótulo con `max-width`. Ahora los
        // dos hijos parten de la MISMA base (`--cta-pair-mini`) y lo único que cambia es
        // `flex-grow` —un número, que sí interpola—, así que el intercambio de la barra se ve
        // igual que el del racimo de arriba, que era el encargo.
        // ▶ La regla que se fija sigue siendo la misma: **sin JavaScript, la ancha es la de
        // comprar**, y el par se tiene que poder invertir.
        $this->assertMatchesRegularExpression(
            '/\.book-bar__cta--buy\s*\{[^}]*flex-grow:\s*1/s', $css,
            'la mitad de comprar no arranca expandida',
        );
        $this->assertMatchesRegularExpression(
            '/\.book-bar--signup \.book-bar__pair > \.cta-pair__alt\s*\{[^}]*flex-grow:\s*1/s', $css,
            'la mitad de la cuenta no se expande nunca: el par no se puede invertir',
        );
        // ⚠️ **Y la base COMÚN es lo que hace que el intercambio se pueda animar**: si un lado
        // volviera a `auto`, las dos aserciones de arriba seguirían verdes y el gesto se perdería.
        $this->assertMatchesRegularExpression(
            '/\.book-bar__cta--buy,\s*\.book-bar__pair > \.cta-pair__alt\s*\{[^}]*flex:\s*0 1 var\(--cta-pair-mini\)/s', $css,
            'las dos mitades ya no parten de la misma base: `flex-grow` no puede animar el reparto',
        );
        // ⚠️⚠️ **`min-width: 0`, y NO es cosmético**: sin él un hijo de flex no encoge por debajo
        // del ancho de su contenido (`min-width: auto`), el `flex-basis` de 56 px se ignora y la
        // mitad colapsada mide lo que mida su rótulo. Medido en el navegador antes de ponerlo:
        // **188 px en vez de 56**. Es el fallo que se ve como «no funciona el diseño» y se lee
        // como CSS correcto.
        $this->assertMatchesRegularExpression(
            '/\.book-bar__cta--buy,\s*\.book-bar__pair > \.cta-pair__alt\s*\{[^}]*min-width:\s*0/s', $css,
            'las mitades han perdido `min-width: 0`: la colapsada volverá a medir su contenido',
        );
    }

    /**
     * **La barra de móvil y el racimo de la cabecera son EL MISMO BOTÓN** (`#225`, 2026-08-28).
     *
     * `[DECIDIDO owner]`: «lo quiero igual, mismo tamaño, altura, mismos colores, con la misma
     * animación de invitar». Hasta esa fecha eran DOS componentes —`.cta-prime` abajo,
     * `.cta-med`/`.cta-ghost` arriba— que alguien tenía que mantener sincronizados a mano.
     *
     * ⚠️⚠️ **No se mantuvieron, y ninguna guarda lo veía.** Medido en el navegador a 390 px antes
     * de la refundición: la barra medía **100 px de alto contra 54**, su chip de icono **64×42
     * contra 30×26**, y **las dos mitades salían naranjas** cuando arriba la colapsada es un
     * fantasma de tarjeta. Los 31 casos del armazón pasaban todos: comprobaban que la barra
     * TIENE dos mitades, que son enlaces y que se intercambian — nunca que se PARECEN a nada.
     *
     * ▶ Por eso este caso no asevera medidas (que envejecerían con el diseño) sino la **causa**:
     * que la barra no vuelva a tener forma propia. Aporta COLOCACIÓN; la forma la pone el
     * componente compartido. Si alguien vuelve a escribir aquí una altura o un relleno, el
     * mecanismo ha vuelto a partirse aunque el número que ponga sea el correcto hoy.
     */
    public function test_the_mobile_bar_is_the_same_component_as_the_nav_pair(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // ── 1. Los DOS racimos declaran el mismo componente ──────────────────────────────────
        foreach (['book-bar__pair' => 'la barra de móvil', 'nav__pair' => 'el racimo de la cabecera'] as $sitio => $quien) {
            $nodo = $this->nodes($html, $sitio);
            $this->assertCount(1, $nodo, "no se encuentra {$quien}");
            $this->assertStringContainsString(
                'cta-pair', $nodo[0]->getAttribute('class'),
                "{$quien} no declara `cta-pair`: ha dejado de compartir componente y su forma ".
                'volverá a divergir sin que nada avise.',
            );
        }

        // ── 2. Las mitades de la barra son las MISMAS clases que arriba ──────────────────────
        foreach (['book-bar__cta--buy' => 'cta-med', 'book-bar__cta--alt' => 'cta-ghost'] as $mitad => $componente) {
            $nodo = $this->nodes($html, $mitad);
            $this->assertCount(1, $nodo, "falta la mitad `{$mitad}`");
            $this->assertStringContainsString(
                $componente, $nodo[0]->getAttribute('class'),
                "`{$mitad}` no usa `{$componente}`, que es el botón del nav: ha vuelto a tener ".
                'pieza propia.',
            );
        }

        // ── 3. La COLOCACIÓN no puede redefinir la FORMA ─────────────────────────────────────
        // Éstas son exactamente las propiedades por las que las dos piezas divergieron. La sombra
        // NO está en la lista y es deliberado: la barra flota sobre el contenido (cuarto rol de
        // elevación, `#217`) y el racimo va apoyado, así que es la única diferencia de aspecto
        // que la colocación tiene derecho a declarar.
        $prohibidas = ['height', 'background', 'padding', 'font-size', 'font-family', 'border-radius', 'color'];
        $reglas = $this->cssRules(public_path('css/site.css'));

        foreach ($reglas as $selector => $cuerpo) {
            // Sólo las reglas que apuntan a los BOTONES de la barra: `.book-bar` a secas es el
            // contenedor fijo y sí tiene padding y transform propios, que son su trabajo.
            if (! str_contains($selector, 'book-bar__cta') && ! str_contains($selector, 'book-bar__pair >')) {
                continue;
            }

            foreach ($prohibidas as $prop) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(^|[;{\s])'.preg_quote($prop, '/').'\s*:/', $cuerpo,
                    "`{$selector}` declara `{$prop}`. La barra de móvil aporta COLOCACIÓN, no ".
                    'forma: la forma la pone `.cta-pair`, que comparte con el nav. Declararla '.
                    'aquí es exactamente como se llegó a tener 100 px de alto abajo y 54 arriba.',
                );
            }
        }

        // Guarda de la guarda: si el filtro de arriba no casara con ninguna regla, el bucle
        // pasaría sin mirar nada — el modo de fallo que este repo ya ha pagado varias veces.
        $miradas = array_filter(
            array_keys($reglas),
            fn (string $sel): bool => str_contains($sel, 'book-bar__cta') || str_contains($sel, 'book-bar__pair >'),
        );
        $this->assertGreaterThanOrEqual(
            5, count($miradas),
            'el filtro sólo ve '.count($miradas).' reglas de la barra: se ha roto y no está '.
            'mirando nada.',
        );
    }

    /**
     * **El glifo del botón de alta sigue al DESTINO, no a la posición del botón.**
     *
     * El mismo botón de la esquina sirve a dos destinos según la instalación: si el parque tiene
     * su propio **trámite de registro de acceso** (una URL externa configurada en el panel), eso
     * es un formulario y lleva portapapeles; si no lo tiene, el botón **crea una cuenta** y lleva
     * la pareja de `user`. Con un solo glifo para las dos, el trámite del parque quedaría
     * etiquetado como si fuera un alta de cuenta — y son cosas distintas para el visitante.
     */
    public function test_the_signup_glyph_follows_the_destination(): void
    {
        // ⚠️ Se comprueba **en los DOS portadores**, no en la página: el mismo botón vive en el
        // racimo de la cabecera y en la barra de móvil, y la regla tiene que valer en los dos. Un
        // recuento global daría verde con uno solo bien puesto.
        $portadores = ['nav__cta', 'book-bar__pair'];

        $interno = $this->get('/')->assertOk()->getContent();

        foreach ($portadores as $portador) {
            $this->assertNotEmpty(
                $this->within($interno, $portador, 'user-plus-ico'),
                "`{$portador}`: el alta de CUENTA no lleva su glifo",
            );
        }

        Setting::updateOrCreate(
            ['key' => 'registration.url'],
            ['value' => 'https://registro.ejemplo.test/alta', 'group' => 'registration'],
        );
        Setting::flushMemo();
        Cache::flush();

        $externo = $this->get('/')->assertOk()->getContent();

        foreach ($portadores as $portador) {
            $this->assertEmpty(
                $this->within($externo, $portador, 'user-plus-ico'),
                "`{$portador}`: el TRÁMITE externo lleva el glifo de crear cuenta",
            );
        }

        $this->assertNotEmpty($this->nodes($externo, 'cta-ghost__ico'), 'el trámite externo se ha quedado sin glifo');
    }

    /**
     * **El armazón no dibuja glifos EN LÍNEA, salvo los que la lista declara.**
     *
     * «Dibujos → el set de iconos» es uno de los tres mecanismos del tema
     * (`landing-white-label.md` §4.5): un `<svg>` suelto en el marcado **no lo puede sustituir un
     * cliente**, por muy bien dibujado que esté. La tanda 2c·3 se llevó al set la hamburguesa y
     * el aspa de cerrar.
     *
     * ⚠️ **Los dos galones del selector de idioma se quedan a propósito, y por eso están en la
     * lista**: el set ya tiene un galón, pero con otro trazo y otra caja, así que unificarlos
     * **cambiaría el aspecto del PIE**, que no es de esta tanda. **La lista solo encoge.**
     */
    public function test_the_armazon_draws_no_inline_glyphs(): void
    {
        /** @var array<string, string> */
        $permitidos = [
            'menu.blade.php' => 'el galón del selector de idioma: el del set tiene otro trazo y otra caja, y unificarlo movería el pie',
            'footer.blade.php' => 'ídem, su gemelo en el pie',
        ];

        $sueltos = [];

        foreach (['nav', 'menu', 'footer'] as $componente) {
            $fichero = $componente.'.blade.php';
            $source = (string) file_get_contents(resource_path('views/components/site/'.$fichero));
            $cuantos = preg_match_all('/<svg\b/', $source);
            $tolerados = isset($permitidos[$fichero]) ? 1 : 0;

            if ($cuantos > $tolerados) {
                $sueltos[] = $fichero.': '.$cuantos.' (tolerados '.$tolerados.')';
            }
        }

        $this->assertSame(
            [], $sueltos,
            "el armazón vuelve a dibujar glifos en línea:\n  ".implode("\n  ", $sueltos)."\n".
            'Al set de iconos: un dibujo suelto en el marcado no lo puede sustituir un cliente.',
        );
    }

    /**
     * **El punto de aviso es Amarillo Aviso, y solo aparece cuando hay algo que avisar.**
     *
     * ⚠️ Se comprueba en la HOJA porque el color es donde vive la decisión: el mismo token con el
     * que el panel pinta «tienes un formulario pendiente», de modo que cabecera y panel dicen lo
     * mismo con el mismo color. Que el punto solo se pinte con aviso lo cubre
     * `CustomerAccountContextTest`, que tiene los dos fixtures.
     */
    public function test_the_pending_dot_uses_the_warning_token(): void
    {
        $this->assertMatchesRegularExpression(
            '/background:\s*var\(--attn\)/', $this->ruleBody('.cta-pair__acct-dot'),
            'el punto de aviso no usa el token de aviso: cabecera y panel dirían lo mismo con '.
            'colores distintos',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    /** @var array<string, \DOMXPath> */
    private array $docs = [];

    /**
     * El documento de una página, **memoizado**.
     *
     * ⚠️ Sin memoizar, cada consulta construía un `DOMDocument` nuevo y cruzar un nodo de uno con
     * el XPath de otro lanza «Node from wrong document». Se cazó al primer intento.
     *
     * ⚠️⚠️ **Y los atributos de Alpine hay que renombrarlos ANTES de parsear**: `@click`,
     * `@keydown` y `:class` no son nombres XML válidos y `DOMDocument` **los tira sin avisar**.
     * Un test que preguntara por `@click` recibiría cadena vacía y concluiría, con toda la
     * confianza del mundo, que el cableado no existe. Se renombran a `data-alpine-*` /
     * `data-bind-*` y hay control positivo que lo comprueba.
     */
    /**
     * Los elementos con la clase dada que cuelgan del subárbol de otra.
     *
     * @return list<\DOMElement>
     */
    private function within(string $html, string $rootClass, string $class): array
    {
        $xpath = $this->xpath($html);
        $out = [];

        foreach ($this->nodes($html, $rootClass) as $root) {
            $q = ".//*[contains(concat(' ', normalize-space(@class), ' '), ".$this->quote(' '.$class.' ').')]';

            foreach ($xpath->query($q, $root) as $node) {
                $out[] = $node;
            }
        }

        return $out;
    }

    /**
     * Los títulos de los destinos del menú, **en el orden en que se pintan**.
     *
     * @return list<string>
     */
    private function menuTitles(string $html): array
    {
        $out = [];

        foreach ($this->nodes($html, 'menu__t') as $node) {
            $out[] = trim($node->textContent);
        }

        return $out;
    }

    /**
     * El cuerpo de TODAS las reglas cuyo selector es exactamente el dado, concatenado.
     *
     * Exactamente: `.nav` no puede casar con `.nav__left` ni con `.nav--over`, o la aserción
     * diría cualquier cosa.
     */
    private function ruleBody(string $selector): string
    {
        preg_match_all(
            '/(?:^|\})\s*'.preg_quote($selector, '/').'\s*\{([^}]*)\}/m',
            $this->stylesheets(), $matches,
        );

        $this->assertNotEmpty($matches[1], "no hay ninguna regla `{$selector}` en las hojas");

        return implode(' ', $matches[1]);
    }

    /** Las dos hojas del producto, con los comentarios blanqueados. */
    private function stylesheets(): string
    {
        $out = '';

        foreach (glob(public_path('css/*.css')) ?: [] as $path) {
            $out .= (string) preg_replace('#/\*.*?\*/#s', ' ', (string) file_get_contents($path))."\n";
        }

        return $out;
    }

    /**
     * ⚠️⚠️ **Parser de HTML5, y el cambio arregla un punto CIEGO que llevaba aquí desde el principio.**
     *
     * Esto usaba `DOMDocument::loadHTML`, que es un parser de **HTML4**: no conoce `<footer>`,
     * `<nav>`, `<main>` ni `<section>`, los trata como elementos desconocidos y **rompe el
     * anidamiento** en cuanto uno contiene un `<div>` después de otro desconocido.
     *
     * ▶ **Medido el 2026-08-28** (`#235`), al meter un `<nav>` en el pie: el árbol parseado ponía
     * `div.foot__bottom` colgando del `<body>` y no del `<footer>`, cuyos hijos se quedaban en dos.
     * O sea que **`within($html, 'foot', …)` devolvía 0 pase lo que pase** — y la guarda del
     * selector de idioma, que aseveraba `assertEmpty(...)`, llevaba pasando **sin mirar nada**.
     * ▶ Es la lección que este repo ya tiene escrita tres veces: *cuando un instrumento dice que
     * algo no está, la primera hipótesis es el instrumento.* Aquí decía la verdad al revés.
     *
     * `Dom\HTMLDocument` (PHP 8.4+) sí es un parser de HTML5 y anida bien. Los ayudantes de este
     * fichero solo usan `query`, `getAttribute`, `nodeName`, `textContent` y `childNodes`, que
     * existen igual en `Dom\Element`.
     */
    private function xpath(string $html): XPath
    {
        $key = md5($html);

        if (isset($this->docs[$key])) {
            return $this->docs[$key];
        }

        $doc = HTMLDocument::createFromString(
            $this->renameAlpineAttributes($html),
            LIBXML_NOERROR,
            'UTF-8',
        );

        $xpath = new XPath($doc);
        // ⚠️ **En HTML5 los elementos viven en el espacio de nombres XHTML**, así que `.//a` deja
        // de casar y hay que escribir `.//h:a`. Es el precio de tener un parser que anida bien, y
        // es barato: en este fichero solo ocho consultas nombran etiquetas.
        $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');

        return $this->docs[$key] = $xpath;
    }

    /**
     * `@click` → `data-alpine-click` · `:class` → `data-bind-class`. Solo en posición de atributo.
     *
     * ⚠️ **`x-on:click` se normaliza a `@click` PRIMERO, y eso lo enseñó un falso positivo.** El
     * armazón usa las DOS sintaxis de la misma directiva —el enlace de alta del cajón va con
     * `x-on:`, sus hermanos con `@`—, así que una guarda que solo conociera una acusaba a un
     * enlace que sí cierra el cajón. Un guarda que no conoce todas las formas de escribir lo que
     * vigila miente en las dos direcciones: acusa a los inocentes hoy y absuelve a los culpables
     * mañana.
     */
    private function renameAlpineAttributes(string $html): string
    {
        $html = (string) preg_replace('/\sx-on:([A-Za-z])/', ' @$1', $html);
        $html = (string) preg_replace('/\s@([A-Za-z][\w.:-]*)=/', ' data-alpine-$1=', $html);

        return (string) preg_replace('/\s:([A-Za-z][\w.-]*)=/', ' data-bind-$1=', $html);
    }

    /**
     * Los elementos que llevan la clase EXACTA (token dentro de `@class`, no subcadena).
     *
     * @return list<\DOMElement>
     */
    /**
     * **El par ARRANCA con la cuenta expandida SIN sesión, y con comprar CON sesión**
     * (`[DECIDIDO owner, 2026-09-01]`, `DECISIONES #326`; cambia el arranque de `#8`).
     *
     * Lo que un cliente nuevo necesita al llegar es registrarse, así que esa mitad es la ancha
     * hasta que tiene cuenta. El modo lo dice el SERVIDOR en `<body data-cta-mode>` —el store lo
     * lee al arrancar— y el primer pintado ya sale en ese modo (clase estática `cta-pair--account`
     * en el par), para que no haya salto antes de que Alpine despierte.
     *
     * ⚠️ Se asevera el atributo del `<body>` Y la clase estática: con solo la clase, el JS podría
     * arrancar en comprar y devolver el par al modo viejo un instante después de pintarse.
     */
    public function test_the_pair_starts_on_account_for_guests_and_on_buy_with_session(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-cta-mode="account"', $html, 'sin sesión el body no anuncia el modo cuenta');
        $this->assertStringContainsString('cta-pair--account', $this->nodes($html, 'nav__pair')[0]->getAttribute('class'), 'sin sesión el par de la cabecera no arranca con la cuenta expandida');
        $this->assertStringContainsString('cta-pair--account', $this->nodes($html, 'hero__pair')[0]->getAttribute('class'), 'sin sesión el par del hero no arranca con la cuenta expandida');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $conSesion = $this->actingAs($user)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-cta-mode="buy"', $conSesion, 'con sesión el body no anuncia el modo comprar');
        $this->assertStringNotContainsString('cta-pair--account', $this->nodes($conSesion, 'nav__pair')[0]->getAttribute('class'), 'con sesión el par sigue arrancando con la cuenta expandida');
    }

    private function nodes(string $html, string $class): array
    {
        $out = [];
        $needle = ' '.$class.' ';

        foreach ($this->xpath($html)->query("//*[contains(concat(' ', normalize-space(@class), ' '), ".$this->quote($needle).')]') as $node) {
            $out[] = $node;
        }

        return $out;
    }

    /**
     * Los destinos (`ruta#ancla`) de los enlaces que cuelgan del subárbol de una clase.
     *
     * @return list<string>
     */
    private function linksIn(string $html, string $class): array
    {
        $xpath = $this->xpath($html);
        $roots = $this->nodes($html, $class);
        $out = [];

        foreach ($roots as $root) {
            /** @var \DOMElement $link */
            foreach ($xpath->query('.//h:a[@href]', $root) as $link) {
                $href = $link->getAttribute('href');
                $parts = parse_url($href);
                $path = $parts['path'] ?? '/';
                $out[] = $path.(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Las reglas de una hoja, por selector NORMALIZADO → su cuerpo.
     *
     * ⚠️ Se indexa por selector exacto y no se busca por subcadena: `.cta-med-NO` contiene
     * `.cta-med`, y una aserción por subcadena pasa con el CSS roto. Este repo lo ha pagado tres
     * veces en dos días (`#211`).
     * ⚠️ Y los comentarios se BLANQUEAN conservando longitud: uno pegado al selector se come la
     * cabecera y el localizador da cero reglas donde hay una.
     *
     * @return array<string, string>
     */
    private function cssRules(string $path): array
    {
        $css = (string) preg_replace_callback(
            '#/\*.*?\*/#s',
            fn (array $m): string => str_repeat(' ', strlen($m[0])),
            (string) file_get_contents($path),
        );

        $out = [];

        preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        foreach ($rules as $rule) {
            foreach (explode(',', $rule[1]) as $selector) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $selector));

                if ($selector !== '' && ! str_starts_with($selector, '@')) {
                    $out[$selector] = ($out[$selector] ?? '').' '.$rule[2];
                }
            }
        }

        return $out;
    }

    /** El valor de UNA declaración dentro del cuerpo de una regla. */
    private function declarationValue(string $body, string $property): string
    {
        return preg_match('/(?<![-\w])'.preg_quote($property, '/').'\s*:([^;}]+)/', $body, $m) === 1 ? $m[1] : '';
    }

    /**
     * Expande `var(--x)` con el valor declarado de `--x` hasta que no quede ninguno.
     *
     * Sirve para aseverar de qué depende una declaración **de verdad**, en vez de atarse al nombre
     * del token que tenga hoy en medio. El tope de vueltas es un cortacircuitos: un token que se
     * refiera a sí mismo colgaría el bucle, y en este repo ya hubo un diccionario que ciclaba
     * (`#196`).
     */
    private function resolveVars(string $value, int $vueltas = 8): string
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        for ($i = 0; $i < $vueltas && str_contains($value, 'var('); $i++) {
            $antes = $value;
            $value = (string) preg_replace_callback('/var\((--[\w-]+)[^)]*\)/', function (array $m) use ($css) {
                return preg_match('/'.preg_quote($m[1], '/').'\s*:([^;}]+)/', $css, $d) === 1
                    ? '('.$m[1].' '.trim($d[1]).')'
                    : $m[0];
            }, $value);

            if ($value === $antes) {
                break;
            }
        }

        return $value;
    }

    /* ══ LA 2c·8 — EL ARMAZÓN NACE BAJO EL HERO Y EL CTA ES EL DEL MOCKUP (`#216`) ═══════════ */

    /**
     * **En la portada el armazón nace OCULTO, y los TRES racimos con él.**
     *
     * ⚠️ **Se asevera el SELECTOR COMPLETO, no una subcadena.** Esta suite ha pagado cuatro veces
     * la misma lección: `.nav-cta-med-NO` contiene `.nav-cta-med`, `client-favicon.svg` contiene
     * `favicon.svg`. Aquí se parte el CSS en reglas y se compara el selector normalizado.
     * ⚠️ **Y se exige que la regla cubra los DOS racimos.** Con uno solo, la portada se quedaría a
     * medias: logotipo fuera y hamburguesa dentro, o al revés. La mutación que lo demuestra es
     * quitar `.nav__left` de la lista.
     */
    public function test_the_frame_is_born_hidden_under_the_hero(): void
    {
        $reglas = $this->cssRules(public_path('css/site.css'));

        foreach (['body[data-has-hero] .nav__left', 'body[data-has-hero] .nav__cta'] as $racimo) {
            $cuerpo = (string) ($reglas[$racimo] ?? '');

            // ⚠️ Se asevera que la opacidad LEE `--nav-p`, no que valga exactamente eso: desde
            // `#229` el armazón tiene dos motivos para no estar —todavía no ha entrado bajo el
            // hero, o ya se ha retirado ante el hero del CIERRE— y la fórmula los multiplica.
            // Aseverar el texto literal ataba la guarda a UNA de las dos coreografías.
            $this->assertMatchesRegularExpression(
                '/opacity:[^;]*var\(--nav-p\)/', $cuerpo,
                "`{$racimo}` no sigue a `--nav-p`.\n".
                "▶ `[DECIDIDO owner, 2026-08-28]`: en la portada el armazón nace oculto bajo el hero,\n".
                '  como el mockup. Si un racimo se queda fuera, la primera pantalla sale a medias.',
            );
            // ⚠️⚠️ **Y la retirada se asevera RESOLVIENDO la cadena de tokens, no por el nombre
            // de uno.** Esta aserción decía `var(--cierre-salida)` y se puso ROJA con el armazón
            // sano en cuanto `#250` metió la curva cúbica en un token intermedio: la retirada
            // seguía ahí, con otro nombre. Es la misma lección que la aserción de arriba ya había
            // aprendido con `--nav-p`, una capa más abajo. Ahora se expande `var()` hasta el
            // final y se exige que la opacidad dependa del PROGRESO DEL CIERRE, se llame como se
            // llame el token de en medio.
            $this->assertMatchesRegularExpression(
                '/--cierre-q\b/', $this->resolveVars($this->declarationValue($cuerpo, 'opacity')),
                "`{$racimo}` no se retira ante el hero del cierre (`#229`).\n".
                "▶ La tarjeta de cierre es de TINTA y ocupa la pantalla entera; el botón de comprar\n".
                "  se rellena con `var(--fg)` y quedaría OSCURO SOBRE OSCURO — se vio en la primera\n".
                '  captura: el rótulo «RESERVAR» flotando sin botón debajo.',
            );
            $this->assertStringContainsString(
                'pointer-events: none', $cuerpo,
                "`{$racimo}` es invisible pero SIGUE recibiendo clics: `opacity: 0` no los bloquea.",
            );
        }

        // ⚠️⚠️ **Y el menú abierto conserva su salida.** La retirada ante el cierre oculta el
        // racimo con `visibility: hidden`, que las tres declaraciones de `.nav--over` no pisaban:
        // con el menú abierto al final de la página, la hamburguesa —que ES la forma de cerrarlo—
        // desaparecía y el menú quedaba atrapado. Es el agujero de `#211` por una tercera puerta.
        $this->assertMatchesRegularExpression(
            '/visibility:\s*visible/', (string) ($reglas['body[data-has-hero] .nav--over .nav__cta'] ?? ''),
            'con el menú abierto el racimo no fuerza `visibility: visible`: si el visitante lo abre '.
            'al final de la página, la hamburguesa se oculta con la retirada del cierre y el menú '.
            'se queda sin ninguna forma de cerrarse.',
        );
    }

    /**
     * **Y el suelo SIN JavaScript lo devuelve entero.**
     *
     * ❗ Sin esto, un visitante sin JavaScript —o con el bundle caído— se encuentra una portada sin
     * logotipo, sin menú y sin botón de comprar: no es una degradación, es un sitio roto. `--nav-p`
     * lo publica `heroChoreo`, y si Alpine no arranca no lo publica nadie.
     */
    public function test_without_javascript_the_frame_comes_back(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<noscript><style>[^<]*body\[data-has-hero\][^<]*opacity:1[^<]*<\/style><\/noscript>/', $html,
            "No hay suelo sin JavaScript para el armazón.\n".
            '▶ En la portada nace oculto y lo destapa el JS: sin `<noscript>`, sin JS no hay nada.',
        );

        foreach (['.nav__left', '.nav__cta'] as $racimo) {
            $this->assertMatchesRegularExpression(
                '/<noscript><style>[^<]*'.preg_quote($racimo, '/').'[^<]*<\/style><\/noscript>/', $html,
                "el suelo sin JavaScript no cubre `{$racimo}`.",
            );
        }
    }

    /**
     * **El par intercambia ANCHOS FIJOS, y las dos mitades miden lo mismo.**
     *
     * Medido en `Landing PJP Modos`: 224 px la expandida y 56 la colapsada, y las dos con la misma
     * altura. Antes eran 167 y 70, con 53 y 42 de alto — o sea dos botones hermanos de tamaños
     * distintos.
     * ⚠️ **Se exige el CRUCE, no un número suelto**: que en reposo comprar valga `--cta-pair-w` y
     * la cuenta `--cta-pair-mini`, y que con `--account` sea al revés. Aseverar solo dos de las
     * cuatro reglas deja pasar un par que se expande sin colapsar al otro.
     */
    public function test_the_pair_swaps_fixed_widths(): void
    {
        $reglas = $this->cssRules(public_path('css/site.css'));

        $esperado = [
            // ⚠️ Los anchos FIJOS siguen siendo del nav y sólo del nav (`#225`): abajo, en la
            // barra de móvil, la mitad expandida se estira con `flex` hasta el borde de la
            // pantalla. Por eso el selector lleva las DOS clases —`.nav__pair` (colocación) y
            // `.cta-pair--account` (estado del componente)— y no una sola.
            '.nav__pair .cta-med' => 'var(--cta-pair-w)',
            '.nav__pair .cta-ghost' => 'var(--cta-pair-mini)',
            '.nav__pair.cta-pair--account .cta-med' => 'var(--cta-pair-mini)',
            '.nav__pair.cta-pair--account .cta-ghost' => 'var(--cta-pair-w)',
        ];

        foreach ($esperado as $selector => $ancho) {
            $this->assertArrayHasKey(
                $selector, $reglas,
                "falta la regla `{$selector}`: el par no reparte anchos y las dos mitades quedan al ".
                'tamaño de su contenido, que es de donde venía la asimetría.',
            );
            $this->assertStringContainsString(
                'width: '.$ancho, (string) $reglas[$selector],
                "`{$selector}` no declara `width: {$ancho}`.",
            );
        }
    }

    /**
     * **El fantasma NO se tiñe dentro del menú.**
     *
     * Medido en el mockup: su botón de registro se queda **blanco** con el menú abierto, mientras
     * el de comprar pasa a aviso. Aquí eso se consigue leyendo los alias `--paper-*`, que son los
     * que NO se invierten en superficie oscura: si leyera `--bg-card`, el ámbito de tinta lo
     * repintaría y el par quedaría con dos botones oscuros pegados.
     */
    public function test_the_ghost_half_keeps_its_paper_colours_inside_the_menu(): void
    {
        $reglas = $this->cssRules(public_path('css/site.css'));

        $this->assertArrayHasKey('.cta-ghost', $reglas);
        $cuerpo = (string) $reglas['.cta-ghost'];

        foreach (['background: var(--paper-bg-card)', 'color: var(--paper-fg)'] as $declaracion) {
            $this->assertStringContainsString(
                $declaracion, $cuerpo,
                "`.cta-ghost` no declara `{$declaracion}`.\n".
                "▶ Con los tokens que SÍ se invierten, el menú de tinta lo repinta y el par pierde su\n".
                '  contraste interno: dos botones oscuros, uno al lado del otro.',
            );
        }
    }

    /**
     * **El texto que ENTRA espera; el que se va, no.**
     *
     * Medido en `textoCta` del mockup: `transition: opacity .28s ease <.14s si entra, 0s si sale>`.
     * Sin el retardo asimétrico los dos rótulos se cruzan a mitad de camino y durante unos 200 ms
     * se leen dos cosas a la vez en un racimo de 300 px.
     */
    public function test_the_incoming_label_waits_for_its_room(): void
    {
        $reglas = $this->cssRules(public_path('css/site.css'));

        foreach (['.cta-pair .cta-med__body', '.cta-pair--account .cta-ghost__body'] as $entra) {
            $this->assertArrayHasKey($entra, $reglas, "falta `{$entra}`");
            $this->assertStringContainsString(
                'transition-delay: var(--cta-pair-in', (string) $reglas[$entra],
                "`{$entra}` es el rótulo que ENTRA y no espera a que le hagan sitio.",
            );
        }

        foreach (['.cta-pair .cta-ghost__body', '.cta-pair--account .cta-med__body'] as $sale) {
            $this->assertArrayHasKey($sale, $reglas, "falta `{$sale}`");
            $this->assertStringContainsString(
                'transition-delay: 0s, 0s', (string) $reglas[$sale],
                "`{$sale}` es el rótulo que SE VA y está esperando: el cruce se solapa.",
            );
        }
    }

    /**
     * **El hover del par NO mueve el botón.**
     *
     * Medido: el mockup solo cambia la sombra (`style-hover="box-shadow:…"`). Nuestro par hacía
     * `translateY(-2px) scale(1.02)` en comprar y `translateY(-1px)` en el fantasma. Un botón de
     * DOS PASOS que además salta bajo el cursor se pulsa por error más a menudo.
     */
    public function test_the_pair_does_not_jump_under_the_cursor(): void
    {
        $reglas = $this->cssRules(public_path('css/site.css'));

        foreach (['.cta-med:hover', '.cta-ghost:hover'] as $selector) {
            $cuerpo = (string) ($reglas[$selector] ?? '');
            $this->assertStringNotContainsString(
                'transform:', $cuerpo,
                "`{$selector}` mueve el botón al pasar el cursor, y el CTA fijo del mockup no lo hace.",
            );
        }
    }

    /**
     * **Los dos botones del hero van EN FILA, no apilados.**
     *
     * ⚠️ **`width: 100%` NO es redundante con el `max-width`, y sin él no se ve nada raro: se ve
     * MAL.** El contenido del hero es un flex de columna con `align-items: flex-start`, así que
     * esta fila se encoge a su contenido mínimo —el ancho del botón más ancho— y el segundo botón
     * envuelve debajo. Medido antes de arreglarlo: 327 px de ancho y los dos a `x = 50`; después,
     * 640 px y `x = 50` / `x = 396`.
     * ▶ Es exactamente el tipo de declaración que alguien retira por «redundante» leyendo el CSS
     * sin abrir la página. Por eso hay guarda.
     */
    public function test_the_hero_buttons_sit_in_a_row(): void
    {
        $cuerpo = (string) ($this->cssRules(public_path('css/site.css'))['.hero__acts'] ?? '');

        $this->assertStringContainsString(
            'width: 100%', $cuerpo,
            "`.hero__acts` no declara ancho.\n".
            "▶ Su padre es un flex de columna con `align-items: flex-start`: sin ancho declarado la\n".
            '  fila se encoge a su contenido y el segundo botón cae debajo del primero.',
        );
        $this->assertStringContainsString(
            'max-width: 640px', $cuerpo,
            'sin el tope, la fila cruza el hero entero y los dos botones se estiran.',
        );
    }

    /* ══ LA 2c·9 — LAS TRES PIEZAS QUE EL OJO DEL OWNER VIO DISTINTAS (`#217`) ═══════════════ */

    /**
     * **El logotipo FLOTA: sin pastilla detrás, y se sostiene con `drop-shadow`.**
     *
     * ❗ La pastilla no se borra, **se acota a quien la necesita**: sigue sosteniendo el suelo del
     * producto —el nombre en la fuente de rótulo, que sin nada detrás queda ilegible en cuanto
     * pasa una tarjeta por debajo— y desaparece cuando hay logotipo.
     * ⚠️ **`drop-shadow` y no `box-shadow`, y la diferencia es el motivo de todo esto**:
     * `box-shadow` proyecta la CAJA —un rectángulo, aunque el dibujo tenga forma— y `drop-shadow`
     * sigue el ALFA de la imagen. Es lo único que deja flotar una marca recortada sin caja.
     */
    public function test_the_client_logo_floats_without_a_pill(): void
    {
        $landing = $this->cssRules(public_path('css/landing.css'));
        $site = $this->cssRules(public_path('css/site.css'));

        // ⚠️ **No se prohíbe la CLAVE `.nav__brand`, se prohíbe que PINTE**: esa regla también
        // lleva el layout del hueco (`display`, `gap`), que tiene que quedarse. Aseverar la clave
        // entera es lo que hizo fallar la primera versión de esta guarda con el código correcto.
        $marca = (string) ($landing['.nav__brand'] ?? '');

        foreach (['background:', 'border:'] as $pinta) {
            $this->assertStringNotContainsString(
                $pinta, $marca,
                "`.nav__brand` vuelve a llevar pastilla (`{$pinta}`) para TODOS.\n".
                '▶ Con logotipo tiene que flotar; la pastilla es solo para el suelo de texto.',
            );
        }
        $this->assertArrayHasKey(
            '.nav__brand:has(.nav__brand-row)', $landing,
            "el suelo de TEXTO se quedó sin pastilla.\n".
            '▶ Sin nada detrás, el nombre del sitio es ilegible en cuanto pasa una tarjeta por debajo.',
        );

        $logo = (string) ($site['.nav__brand-logo'] ?? '');

        // ⚠️⚠️ **AQUÍ SE EXIGÍA `filter: drop-shadow`, y `#273` lo RETIRÓ.** El razonamiento era
        // «sin pastilla y sin sombra, flota sobre el contenido sin nada que lo despegue», y lo
        // sustituye una decisión del owner tomada mirando: **el logotipo ya trae su propio relieve
        // horneado** —26 pasos de extrusión por palabra— y eso es lo que lo despega. Añadirle un
        // `drop-shadow` encima es lo que él veía como «demasiada sombra», cuatro veces seguidas.
        // ▶ Que NO la lleve lo vigila `InlineBrandLogoTest::test_the_logo_carries_no_css_shadow`,
        // que es donde vive la decisión; aquí sólo se retira la exigencia contraria para que las
        // dos guardas no se peleen.
        $this->assertStringNotContainsString(
            'box-shadow', $logo,
            '`box-shadow` proyecta la CAJA, no la silueta: sobre un logotipo recortado dibuja un rectángulo.',
        );
        // ⚠️ **70 y no 54, y el número está MEDIDO** (`#218`): el `height:54px` del mockup está en el
        // `<a>` que envuelve el lockup, y el lockup DESBORDA esa caja por sus contornos. Comparando
        // píxeles opacos —tinta contra tinta, sin sombra— el del mockup mide 189 × 68 y el nuestro
        // a 54 daba 147 × 52. Copiar el 54 lo dejaba un 30 % más pequeño.
        // ⚠️ Se asevera el VALOR RESUELTO y no el texto: desde `#252` la altura sale de
        // `--nav-logo-h`, porque el hueco que el hero le deja al racimo se calcula con ella. Atar
        // la guarda al literal la puso roja con el logotipo intacto — segunda vez en dos tandas.
        $this->assertStringContainsString(
            '70px', $this->resolveVars($this->declarationValue($logo, 'height')),
            "el logotipo volvió a una altura que NO es la medida.\n".
            '▶ 70 px es lo que iguala su TINTA con la del mockup (189 × 68), no lo que declara su `<a>`.',
        );
    }

    /**
     * **El botón de menú es el RECTÁNGULO del mockup, con su etiqueta.**
     *
     * Era un círculo de 44 px sin texto. `[DECIDIDO owner]`: idéntico al mockup — rect de 10 px,
     * la altura del racimo, y la palabra al lado.
     * ⚠️ **La etiqueta visible NO es el nombre accesible**, y por eso se comprueban los dos: el
     * `aria-label` dice la ACCIÓN («Abrir menú») y la etiqueta dice dónde estás («Menú»). «Cerrar»
     * a secas no diría qué se cierra.
     */
    /**
     * **EL ALTO DE REPOSO DEL HERO ES UNA SOLA FÓRMULA, Y ES LA DEL MOCKUP** (`#274`).
     *
     * `[DECIDIDO owner]`: «en el móvil el hero es demasiado corto … hay mucho espacio debajo del
     * hero vídeo». Había un override de móvil —`min(72vh, 520px)`— que **no sale del mockup**: el
     * suyo usa `Math.min(vh * 0.78, 660)` para TODAS las ventanas.
     *
     * ▶ Lo que hacía ese segundo tope, medido en el punto estático (`--hero-p = 1`):
     *   390×844 → hero 520 px (61,6 %) y **252 px vacíos** bajo el vídeo (29,9 %)
     *   390×932 → hero 520 px (55,8 %) y **340 px** (36,5 %)
     * porque **muerde en toda ventana de más de 722 px**: el hero deja de crecer y el hueco crece
     * 1:1 con el teléfono. En escritorio la misma pieza ocupa el 73,3 %.
     *
     * ⚠️ **El tope de 660 SÍ es del mockup y se queda.** Lo que no era suyo es el segundo, más bajo
     * y sólo en móvil.
     *
     * ⚠️⚠️ **Y el par `vh` → `svh`**: `--hero-h-ini` y la altura del contenedor lo declaran desde
     * `#252`, y `--hero-h-end` era **la única de las tres expresiones de ventana del hero sin él** —
     * justo la que fija el punto estático. En un teléfono `100vh` incluye la barra del navegador.
     */
    public function test_the_hero_resting_height_is_one_formula_with_its_svh_pair(): void
    {
        $css = (string) preg_replace_callback(
            '#/\*.*?\*/#s',
            fn (array $m): string => str_repeat(' ', strlen($m[0])),
            (string) file_get_contents(public_path('css/site.css')),
        );

        preg_match_all('/--hero-h-end\s*:\s*([^;]+);/', $css, $m);
        $valores = array_map('trim', $m[1] ?? []);

        $this->assertSame(
            ['min(78vh, 660px)', 'min(78svh, 660px)'],
            $valores,
            "El alto de reposo del hero ya no es UNA fórmula con su par `svh`.\n"
            .'Declaraciones encontradas: '.json_encode($valores)."\n"
            .'▶ El mockup usa `Math.min(vh * 0.78, 660)` en TODAS las ventanas. Un segundo tope solo '
            .'para móvil deja el hero clavado y el hueco de debajo crece 1:1 con el teléfono: a 390×932 '
            .'eran 340 px vacíos, el 36,5 % de la pantalla.',
        );
    }

    /**
     * **El hueco que el hero le deja al racimo se CALCULA, no se estima** (`#252`).
     *
     * `[DECIDIDO owner, 2026-08-29]`: en el punto estático el logotipo, el CTA y el icono del menú
     * tienen que verse en su sitio ENCIMA del hero. ⚠️ **No lo estaban**, y la causa era que el
     * hueco se medía en `vh` —`clamp(76px, 9vh, 104px)`— mientras el racimo mide siempre lo mismo:
     * a 1080 px de alto sobraba aire y **a 900 el logotipo se metía 5 px dentro del hero**.
     * ▶ Medido tras derivarlo: **12 px de aire exactos en las once ventanas probadas**. Devolver el
     * `clamp` deja el racimo encima del hero en **4 de ellas**.
     */
    public function test_the_hero_gap_is_derived_from_the_cluster(): void
    {
        $hero = (string) ($this->cssRules(public_path('css/site.css'))['.hero.hero--full'] ?? '');
        $gap = $this->declarationValue($hero, '--hero-top-end');

        foreach (['--nav-pad-block', '--nav-cluster-h', '--hero-top-air'] as $token) {
            $this->assertStringContainsString(
                $token, $gap,
                "el hueco del hero ha dejado de leer `{$token}`.\n".
                '▶ Es la suma de dónde nace el racimo, lo que mide el más alto y el aire entre ambos. '.
                'Cualquier otra fórmula acierta por tramos: la anterior iba en `vh` y fallaba a 900.',
            );
        }

        $this->assertStringContainsString(
            'max(', $this->declarationValue((string) ($this->cssRules(public_path('css/site.css'))[':root'] ?? ''), '--nav-cluster-h'),
            '`--nav-cluster-h` tiene que ser el MÁXIMO de las piezas del racimo: en escritorio manda '.
            'el logotipo (70) y en teléfono la hamburguesa (54), y quedarse con una deja la otra fuera.',
        );
    }

    public function test_the_burger_is_the_mockup_rectangle(): void
    {
        $reglas = $this->cssRules(public_path('css/landing.css'));
        $cuerpo = (string) ($reglas['.nav__burger'] ?? '');

        $this->assertStringContainsString(
            'border-radius: var(--r-md)', $cuerpo,
            'el botón de menú volvió a ser un círculo (o a un radio que no es el del mobiliario).',
        );
        // ⚠️⚠️ **Se asevera que los DOS beben del MISMO token, no que uno cite al otro.** Hasta
        // `#252` esto decía `height: var(--cta-pair-h`… y ese token se declara DENTRO de
        // `.cta-pair`, de la que este botón no es descendiente: leía siempre su valor de reserva
        // (54) y **nunca bajaba a 48 en teléfono**, que es justo lo que su comentario prometía.
        // Una guarda que comprueba la cita y no el origen bendice exactamente ese fallo.
        // ⚠️⚠️ **Y se asevera la lectura DIRECTA, no la cadena resuelta — la primera versión de esta
        // guarda nació CIEGA y lo demostró la mutación.** Al devolver el fallo real
        // (`var(--cta-pair-h, 54px)`), resolver la cadena seguía llegando a `--nav-btn-h` —porque
        // `--cta-pair-h` ahora lo lee— y la guarda pasaba **bendiciendo exactamente el defecto**.
        // Lo que importa aquí no es de dónde viene el número: es que el token esté EN ALCANCE.
        $this->assertMatchesRegularExpression(
            '/height:\s*var\(--nav-btn-h\)/', $cuerpo,
            "el botón de menú no lee el alto del racimo DIRECTAMENTE.\n".
            '▶ Leerlo a través de `--cta-pair-h` no vale: ese token se declara dentro de `.cta-pair` y '.
            'este botón NO es descendiente suyo, así que se queda con el valor de reserva para siempre.',
        );
        $this->assertMatchesRegularExpression(
            '/:root\s*\{[^}]*--nav-btn-h\s*:/s', (string) file_get_contents(public_path('css/site.css')),
            '`--nav-btn-h` tiene que declararse en `:root`: es lo único que lo pone al alcance de las '.
            'tres piezas que lo necesitan —la hamburguesa, el par de CTA y el hueco del hero—.',
        );
        $this->assertStringContainsString(
            '--nav-btn-h',
            $this->declarationValue((string) ($this->cssRules(public_path('css/site.css'))['.cta-pair'] ?? ''), '--cta-pair-h'),
            'el par de CTA ya no comparte alto con la hamburguesa: al lado se ven desalineados.',
        );

        $html = (string) $this->get('/')->assertOk()->getContent();
        $burger = $this->nodes($html, 'nav__burger')[0];

        $this->assertNotSame(
            '', $burger->getAttribute('aria-label'),
            'la etiqueta visible NO sustituye al nombre accesible: «Cerrar» no dice qué se cierra.',
        );
        $this->assertStringContainsString(
            __('landing.nav.burger_label'), $burger->textContent,
            'el botón de menú no lleva su etiqueta visible.',
        );
        $this->assertStringContainsString(
            __('landing.nav.burger_label_open'), $burger->textContent,
            "falta el rótulo de abierto.\n".
            '▶ Se sirven los DOS y elige el CSS: con Alpine cambiando el texto habría parpadeo en la '.
            'primera pintura, y sin JavaScript se vería el que toca porque el menú nace cerrado.',
        );
    }

    /**
     * **Las tres piezas del racimo llevan la sombra del MOBILIARIO, no la de otra cosa.**
     *
     * `#216` las dejó con `--shadow-float`, el rol de «flota sobre el contenido», y con el paquete
     * del 2.º cliente eso rinde su sombra DURA (`5px 5px 0`). Su mockup las pinta difusas, y ahí
     * había una contradicción entre sus fuentes (`M-05` dice que las difusas son solo para modal).
     * `[DECIDIDO owner, 2026-08-28]`: **gana el mockup**, y entra un CUARTO rol —el mobiliario
     * flotante— con los tres pesos y las dos alturas que su racimo declara.
     */
    public function test_the_cluster_uses_the_furniture_shadow(): void
    {
        $site = $this->cssRules(public_path('css/site.css'));
        $landing = $this->cssRules(public_path('css/landing.css'));

        $esperado = [
            '.cta-med' => 'var(--shadow-nav-fill)',
            '.cta-med:hover' => 'var(--shadow-nav-fill-lift)',
            '.cta-ghost' => 'var(--shadow-nav-ghost)',
            '.cta-ghost:hover' => 'var(--shadow-nav-ghost-lift)',
        ];

        foreach ($esperado as $selector => $token) {
            $this->assertStringContainsString(
                'box-shadow: '.$token, (string) ($site[$selector] ?? ''),
                "`{$selector}` no declara `{$token}`: su sombra no es la del mobiliario.",
            );
        }

        $this->assertStringContainsString(
            'box-shadow: var(--shadow-nav)', (string) ($landing['.nav__burger'] ?? ''),
            'el botón de menú se quedó sin sombra: al lado de dos botones que la llevan, parece pegado.',
        );

        // Y los cinco valores existen de verdad, en el sistema del producto y no sueltos en una regla.
        $raiz = (string) file_get_contents(public_path('css/landing.css'));

        foreach (['--shadow-nav:', '--shadow-nav-ghost:', '--shadow-nav-ghost-lift:',
            '--shadow-nav-fill:', '--shadow-nav-fill-lift:'] as $token) {
            $this->assertStringContainsString(
                $token, $raiz,
                "falta el token `{$token}`: sin declarar, `var()` cae a nada y la sombra desaparece en silencio.",
            );
        }
    }

    /* ══ EL HERO EN TELÉFONO — LAS MEDIDAS DEL MOCKUP (`#220`) ═══════════════════════════════ */

    /**
     * **El titular ENCOGE con el hero, y su suelo cabe en un teléfono.**
     *
     * ⚠️⚠️ **El defecto que motiva esta guarda es un `clamp` con el SUELO mal puesto**, y es de
     * los que no fallan: `clamp(63px, 13vw, 96px)` nunca baja de 63 px, así que en un teléfono de
     * 390 pedía 63 px cuando el hueco daba para 49 — y «DIVERSIÓN» se salía por la derecha. Un
     * `clamp` no es una talla adaptable: es una talla con dos topes, y **el suelo manda por debajo
     * de ellos**. Medido: 29 % más grande que el del mockup.
     *
     * ⚠️ **Y el titular sale de la COREOGRAFÍA, no de un tamaño suelto**: en el mockup encoge con
     * la caja (`lerp(f0, f1)`), y sin eso la tarjeta pequeña acaba llena de letra.
     */
    public function test_the_hero_headline_shrinks_with_the_hero_and_fits_a_phone(): void
    {
        $reglas = $this->cssRules(public_path('css/site.css'));

        $titular = (string) ($reglas['.hero__title.hero__title--onvideo'] ?? '');
        $this->assertStringContainsString(
            'var(--hero-p', $titular,
            "el titular no sigue a la coreografía del hero.\n".
            '▶ En el mockup encoge con la caja; si no, el hero se minimiza y el texto se queda igual.',
        );

        // ⚠️ **Sobre el CSS SIN COMENTARIOS, y no es un detalle**: la primera versión de esta guarda
        // leyó el fichero crudo y salió ROJA con el código correcto, porque el `clamp` prohibido
        // aparece en el COMENTARIO que explica por qué se retiró. Un `grep` que encuentra no
        // demuestra que exista: hay que mirar dónde. `stylesheets()` los blanquea.
        $raiz = $this->stylesheets();

        foreach ([
            'clamp(54px, 8.4vw, 124px)' => 'el tamaño de partida en escritorio',
            'clamp(44px, 6vw, 94px)' => 'el de llegada en escritorio',
            'clamp(38px, 12.5vw, 72px)' => 'el de partida en teléfono',
            'clamp(32px, 9.8vw, 58px)' => 'el de llegada en teléfono',
        ] as $valor => $qué) {
            $this->assertStringContainsString(
                $valor, $raiz,
                "falta {$qué} (`{$valor}`), que es el del mockup.",
            );
        }

        // ⚠️ Y el SUELO no puede volver a subir: es lo que sacaba el titular de la pantalla.
        $this->assertStringNotContainsString(
            'clamp(63px, 13vw, 96px)', $raiz,
            "vuelve el `clamp` cuyo SUELO no cabe en un teléfono.\n".
            '▶ A 390 px pedía 63 px con hueco para 49, y el titular se salía por la derecha.',
        );
    }

    /* ══ LA 2c·4b — EL MENÚ A PANTALLA COMPLETA, TAMBIÉN EN MÓVIL (`#221`) ═══════════════════ */

    /**
     * **La navegación es la MISMA en los doce anchos.**
     *
     * Hasta aquí, por debajo de 1080 px el menú era `display: none` y mandaba el cajón lateral —
     * `[PENDIENTE: owner]` desde la 2c·4, esperando su artboard de móvil, que llegó el 28—.
     * ⚠️ Y el cajón queda **apagado**, no borrado: son ~200 líneas y un bloque de marcado que
     * ningún test cubre, así que retirarlo pide su propia pasada (`CONVENCIONES §3.quater`).
     */
    public function test_the_full_screen_menu_also_rules_on_mobile(): void
    {
        $css = $this->stylesheets();

        $this->assertStringNotContainsString(
            '.menu { display: none; }', $css,
            "el menú a pantalla completa vuelve a apagarse en móvil.\n".
            '▶ Desde la 2c·4b la navegación es la misma en los doce anchos.',
        );
        $this->assertStringContainsString(
            '.mob-menu { display: none; }', $css,
            "el cajón lateral vuelve a pintarse.\n".
            '▶ Con el menú en todos los anchos, tener las DOS es tener dos navegaciones a la vez.',
        );
    }

    /**
     * **En móvil los rótulos del menú ENVUELVEN, porque los manda la BD.**
     *
     * ⚠️⚠️ El mockup usa `white-space: nowrap` y puede permitírselo: sus destinos son cortos y
     * fijos («Entradas», «Zonas»). **Los nuestros salen de la base de datos** y hay «Excursiones de
     * colegio». Medido a 390 px con `nowrap`: un ítem medía **671 px dentro de un menú de 390** y
     * el rótulo quedaba cortado a media palabra.
     * ▶ Es el mismo patrón que el titular del hero (`#220`): un `clamp` cuyo **suelo** no cabía —
     * `clamp(30px, min(5.2vw, 9vh), 68px)` da 30 px fijos por debajo de 577 px de ancho.
     */
    public function test_the_menu_labels_wrap_on_mobile(): void
    {
        $reglas = $this->cssRules(public_path('css/site.css'));
        $movil = null;

        // La regla de `.menu__t` dentro del bloque de móvil: `cssRules` concatena las dos, así que
        // basta con que el conjunto declare el ajuste y el tamaño chico.
        $cuerpo = (string) ($reglas['.menu__t'] ?? '');

        $this->assertStringContainsString(
            'white-space: normal', $cuerpo,
            "los rótulos del menú siguen en `nowrap` en móvil.\n".
            '▶ Los manda la BD: «Excursiones de colegio» no cabe en 390 px a ningún tamaño legible.',
        );
        $this->assertStringContainsString(
            'clamp(26px, min(9vw, 7vh), 44px)', $cuerpo,
            'falta el tamaño de móvil del mockup para los rótulos del menú.',
        );

        $this->assertStringContainsString(
            'display: none', (string) ($reglas['.menu__s'] ?? ''),
            'el subtítulo del menú no se retira en móvil: en una fila estrecha compite con el destino.',
        );
    }

    /**
     * **Y hay una VELA que dice que la lista sigue.**
     *
     * Medido a 390 px: la lista mide 521 px y su contenido 806 — **cinco de los diez destinos
     * quedan fuera**—, y la barra de scroll está oculta a propósito (`scrollbar-width: none`).
     * Sin la vela, una lista que continúa parece terminada.
     */
    public function test_the_menu_says_the_list_continues(): void
    {
        $cuerpo = (string) ($this->cssRules(public_path('css/site.css'))['.menu__inner::after'] ?? '');

        $this->assertStringContainsString(
            'linear-gradient', $cuerpo,
            "no hay vela al final de la lista del menú.\n".
            '▶ Con la barra de scroll oculta y la mitad de los destinos fuera, nada dice que haya más.',
        );
        $this->assertStringContainsString(
            'pointer-events: none', $cuerpo,
            'la vela se traga los clics del último destino visible.',
        );
    }

    /**
     * ❗❗ **LO QUE LA BARRA FLOTANTE OCUPA SE DECLARA UNA VEZ, Y QUIEN SE APARTA LO DERIVA**
     * (`#276`, `[DECIDIDO owner]`: «en el móvil el CTA hay que subirlo un pelín»).
     *
     * Subir la barra son tres números, no uno: su propio relleno, **el desplazamiento del lanzador
     * del widget de ofertas** —que se aparta cuando ella aparece— y **la reserva que el hero deja
     * por abajo** para que el bloque centrado no le caiga detrás.
     *
     * ⚠️⚠️ **Los tres estaban escritos a mano y se conocían de memoria.** El lanzador llevaba
     * `92px`, que era `10 + 56 + 10` de la barra más sus 16 de reposo, calculado por alguien y
     * copiado; el hero llevaba `84px`, un redondeo de los 76 que la barra medía entonces. Subir la
     * barra 9 px habría metido la barra por debajo del lanzador **sin que fallara nada**: son dos
     * elementos `fixed` que no se conocen, y ninguna captura de la home los enseña juntos.
     *
     * ▶ Es la misma familia de defecto que `#252` («un token fuera de alcance no falla, rinde su
     * reserva») y que el `92` de esta misma pieza: *un número derivado a mano deja de derivar en
     * cuanto cambia su origen, y no avisa.*
     */
    public function test_what_the_floating_bar_takes_is_declared_once(): void
    {
        $hojas = $this->stylesheets();

        $this->assertMatchesRegularExpression(
            '/--book-bar-block:\s*calc\([^;]*var\(--book-bar-h\)[^;]*\)/',
            $hojas,
            "`--book-bar-block` no se calcula a partir del alto real de la barra.\n".
            '▶ Es la medida de la que cuelgan el lanzador de ofertas y la reserva del hero.',
        );

        foreach ([
            '.book-bar' => ['--book-bar-pad-top', '--book-bar-pad-bottom'],
            '.hero__stage-content' => ['--book-bar-block'],
        ] as $selector => $tokens) {
            $cuerpo = $this->ruleBody($selector);

            foreach ($tokens as $token) {
                $this->assertStringContainsString(
                    $token, $cuerpo,
                    "`{$selector}` ha dejado de leer `{$token}`: su aire vuelve a ser un literal, y ".
                    'lo que se aparta de la barra ya no se entera cuando cambia.',
                );
            }
        }

        // El lanzador que se aparta: su posición elevada tiene que DERIVAR, no repetir el número.
        $elevado = $this->ruleBody('body.book-bar-visible .offw-launch');

        $this->assertStringContainsString(
            'var(--book-bar-block)', $elevado,
            "el lanzador de ofertas vuelve a apartarse con un número escrito a mano.\n".
            '▶ El día que la barra cambie de aire —y ha cambiado— se le mete encima en silencio.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/bottom:\s*calc\(\s*\d+px\s*\+\s*\d+px/',
            $elevado,
            'el desplazamiento del lanzador suma dos literales: uno de ellos es el alto de la barra.',
        );
    }

    /** Literal XPath seguro aunque el texto lleve comillas. */
    private function quote(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return 'concat(\''.str_replace("'", "', \"'\", '", $value).'\')';
    }
}
