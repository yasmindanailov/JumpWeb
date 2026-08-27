<?php

namespace Tests\Feature\Site;

use App\Domain\Content\Models\LandingService;
use App\Domain\Identity\Models\User;
use Database\Seeders\LandingContentSeeder;
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
        '/', '/precios', '/cumpleanos', '/servicios', '/contacto', '/normas', '/aviso-legal',
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
        foreach ($xpath->query('.//a', $drawer) as $link) {
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
     * **El menú conserva los cuatro destinos del parque, en su orden y con sus anclas.**
     *
     * ⚠️ **Se MUDÓ desde `HomePageTest::nav_renders_park_dropdown_with_anchor_items`** al retirar
     * la 2c·1 el desplegable de la barra. El sujeto viejo —el desplegable— murió; lo que
     * comprobaba de verdad —las etiquetas, **el orden** y las anclas— sigue vivo y nadie más lo
     * fijaba. Retirar el test en vez de mudarlo habría perdido la cobertura del orden.
     *
     * ▶ Y aquí se lee **acotado al elemento**: la versión anterior aseveraba sobre la página
     * entera, donde «Zona Kids» lo pinta también la sección de zonas.
     */
    public function test_the_menu_keeps_the_park_items_in_order(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $titles = $this->menuTitles($html);
        $parque = ['Zona Kids', 'Zona Jump', 'Atracciones', 'Ubicación y horario'];

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
        foreach ($xpath->query('.//a', $this->nodes($html, 'menu__list')[0]) as $link) {
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
        $html = $this->get('/')->assertOk()->getContent();
        $numeros = $this->nodes($html, 'menu__n');

        $this->assertNotEmpty($numeros, 'el menú no numera sus destinos');

        foreach ($numeros as $n) {
            $this->assertSame(
                'true', $n->getAttribute('aria-hidden'),
                'un número del menú entra en el nombre accesible del enlace',
            );
        }
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

        $this->assertCount(0, $this->nodes($member, 'nav-cta-ghost'), 'con sesión sigue ofreciéndose el alta');
        $this->assertCount(1, $this->nodes($member, 'nav__acct'), 'con sesión no aparece el chip de cuenta');
        $this->assertCount(1, $this->nodes($member, 'nav-cta-med'), 'con sesión desaparece el CTA de compra');

        $chip = $this->nodes($member, 'nav__acct')[0];
        $this->assertNotSame('', $chip->getAttribute('aria-label'), 'el chip de cuenta se queda sin nombre accesible');
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

    /** Las dos hojas del producto, con los comentarios blanqueados. */
    private function stylesheets(): string
    {
        $out = '';

        foreach (glob(public_path('css/*.css')) ?: [] as $path) {
            $out .= (string) preg_replace('#/\*.*?\*/#s', ' ', (string) file_get_contents($path))."\n";
        }

        return $out;
    }

    private function xpath(string $html): \DOMXPath
    {
        $key = md5($html);

        if (isset($this->docs[$key])) {
            return $this->docs[$key];
        }

        $doc = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$this->renameAlpineAttributes($html));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $this->docs[$key] = new \DOMXPath($doc);
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
            foreach ($xpath->query('.//a[@href]', $root) as $link) {
                $href = $link->getAttribute('href');
                $parts = parse_url($href);
                $path = $parts['path'] ?? '/';
                $out[] = $path.(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
            }
        }

        return array_values(array_unique($out));
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
