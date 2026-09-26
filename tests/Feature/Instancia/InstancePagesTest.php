<?php

namespace Tests\Feature\Instancia;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Pixels;
use App\Http\Instancia\InstancePages;
use App\Http\Instancia\InstanceViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * **Las páginas que declara el paquete de la instancia** (T4b de `docs/specs/isla-y-landing-nueva.md` §4.2 y §4.12;
 * `DECISIONES #681` y `#761`).
 *
 * «Kids» o «Jump» no son rutas del producto: las declara el paquete en `config/paginas.php` y el producto las sirve
 * con un controlador genérico que le pasa a la vista los hechos que pidió, con **el MISMO JSON que la API**. Lo que
 * no cuadra —un slug que pisa una ruta del producto, un hecho fuera de la lista blanca, una vista que no existe— no
 * se registra, y las demás páginas siguen en pie.
 */
class InstancePagesTest extends TestCase
{
    use RefreshDatabase;

    private string $paquete;

    protected function setUp(): void
    {
        parent::setUp();

        // Un paquete de mentira FUERA del árbol del producto, que es donde tiene que estar uno de verdad (`SEC-12`).
        $this->paquete = sys_get_temp_dir().'/instancia-paginas-'.getmypid();
        File::ensureDirectoryExists($this->paquete.'/web');
        File::ensureDirectoryExists($this->paquete.'/config');
        File::ensureDirectoryExists($this->paquete.'/lang/es');

        File::put($this->paquete.'/web/kids.blade.php', <<<'BLADE'
<h1>{{ __('instancia::kids.titular') }}</h1>
<p id="url">{{ $pagina['url'] }}</p>
<script type="application/json" id="hechos">{!! json_encode($hechos) !!}</script>
BLADE);
        File::put($this->paquete.'/lang/es/kids.php', "<?php return ['titular' => 'Saltan hasta caer rendidos'];");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->paquete);

        parent::tearDown();
    }

    /** Escribe la declaración y hace lo que el arranque hace con ella: vistas, textos y rutas. */
    private function declarar(array $paginas): void
    {
        File::put($this->paquete.'/config/paginas.php', '<?php return '.var_export($paginas, true).';');

        config(['instancia.ruta' => $this->paquete]);
        app(InstanceViews::class)->registrar();
        app()->forgetInstance(InstancePages::class);
        // DENTRO del grupo `web`, como las registra `routes/web.php` (que se carga en él): fuera, la página saldría
        // sin las cabeceras de seguridad y el caso mediría otra cosa que la web (medido: sin CSP).
        Route::middleware('web')->group(fn () => app(InstancePages::class)->registrarRutas(app('router')));
        app('router')->getRoutes()->refreshNameLookups();
    }

    /** @return array<string, mixed> */
    private function hechosDe(string $url): array
    {
        $html = (string) $this->get($url)->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<script type="application/json" id="hechos">(.*?)</script>#s', $html, $m));

        return json_decode($m[1], true);
    }

    /**
     * **Una página declarada se sirve en su slug, con sus textos y con los hechos que pidió, IGUALES a los de la
     * API.** Es la promesa de §4.2: la vista no recibe una copia de la lógica, recibe lo mismo que una landing por
     * HTTP.
     */
    public function test_a_declared_page_is_served_with_the_same_facts_as_the_api(): void
    {
        $this->declarar(['kids' => ['vista' => 'kids', 'hechos' => ['site', 'zones'], 'prioridad' => '0.9']]);

        $respuesta = $this->get('/kids')->assertOk()->assertSee('Saltan hasta caer rendidos')->assertSee(route('instancia.kids'));

        // Las guardas de cualquier página de la web, también en ésta: no se cachea y lleva su CSP.
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertNotEmpty($respuesta->headers->get('Content-Security-Policy'));

        $hechos = $this->hechosDe('/kids');

        $this->assertSame(['site', 'zones'], array_keys($hechos));

        // Y la vista recibe EXACTAMENTE su contrato: ni una variable de menos (rompería la página del cliente en su
        // servidor) ni una de más sin declarar (`#649`).
        $recibidas = array_keys($this->get('/kids')->original->getData());
        $prometidas = InstanceViews::CONTRATO_DE_PAGINA;
        sort($recibidas);
        sort($prometidas);
        $this->assertSame($prometidas, $recibidas);
        $this->assertSame($this->getJson('/api/v1/site')->assertOk()->json(), $hechos['site']);
        $this->assertSame($this->getJson('/api/v1/catalog/zones')->assertOk()->json(), $hechos['zones']);
    }

    /**
     * **Una página NUNCA pisa una ruta del producto**: ni una página (`precios`) ni un prefijo (`api`, que no es una
     * ruta por sí solo). Se descartan, y la del producto sigue sirviendo lo suyo.
     */
    public function test_a_page_never_shadows_a_product_route(): void
    {
        $this->declarar([
            'kids' => ['vista' => 'kids', 'hechos' => []],
            'precios' => ['vista' => 'kids', 'hechos' => []],
            'api' => ['vista' => 'kids', 'hechos' => []],
        ]);

        $this->assertTrue(Route::has('instancia.kids'));
        $this->assertFalse(Route::has('instancia.precios'), 'una página no tapa /precios');
        $this->assertFalse(Route::has('instancia.api'), 'ni un prefijo del producto');
        $this->get('/precios')->assertDontSee('Saltan hasta caer rendidos');
    }

    /**
     * **Lo que no cuadra se queda fuera, y lo demás sigue en pie**: un hecho fuera de la lista blanca (la API
     * pública no se amplía desde un paquete), una vista que no existe y un slug con mayúsculas.
     */
    public function test_an_invalid_page_is_left_out_without_taking_the_others_down(): void
    {
        $this->declarar([
            'kids' => ['vista' => 'kids', 'hechos' => ['prices']],
            'secretos' => ['vista' => 'kids', 'hechos' => ['settings']],
            'sin-vista' => ['vista' => 'no-existe', 'hechos' => []],
            'Mayusculas' => ['vista' => 'kids', 'hechos' => []],
        ]);

        $this->assertSame(['kids'], array_keys(app(InstancePages::class)->todas()));
        $this->get('/kids')->assertOk();
        $this->get('/secretos')->assertNotFound();
    }

    /**
     * **El sitemap las publica con lo que ellas declaran**, y no publica la que se descartó: una página que pisaba
     * una ruta del producto no tiene URL propia que dar a Google.
     */
    public function test_the_sitemap_lists_the_pages_with_their_own_priority(): void
    {
        $this->declarar([
            'kids' => ['vista' => 'kids', 'hechos' => [], 'prioridad' => '0.9', 'frecuencia' => 'daily'],
            'precios' => ['vista' => 'kids', 'hechos' => []],
        ]);

        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(route('instancia.kids'), '#').'</loc>.*?<changefreq>daily</changefreq><priority>0.9</priority>#', $xml);
        $this->assertSame(1, substr_count($xml, '<loc>'.route('precios').'</loc>'), '/precios sale una sola vez: la del producto');
    }

    /**
     * **Una página puede pedir las FICHAS del catálogo, con el mismo JSON que la API** (T4c·8, `#763`). El precio de un
     * complemento («2 € el par» de calcetines) no está en ninguna lista: vive en la ficha de cada producto
     * (`GET /catalog/products/{id}`, `addons[].price_cents`). `product_details` las trae todas, en el orden del
     * catálogo y sin copiar lógica: el controlador de la ficha, una vez por producto.
     */
    public function test_a_page_can_ask_for_the_product_details_with_the_same_json_as_the_api(): void
    {
        $tarifa = RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zona = Zone::create(['slug' => 'kids', 'name' => ['es' => 'Kids'], 'position' => 1, 'is_active' => true]);
        $entrada = TicketType::create([
            'name' => ['es' => 'Kids · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zona->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $entrada->prices()->create(['rate_type_id' => $tarifa->id, 'amount_cents' => 800]);
        $calcetines = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON, 'zone_id' => null,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 0, 'position' => 2,
        ]);
        $calcetines->prices()->create(['rate_type_id' => $tarifa->id, 'amount_cents' => 200]);
        $entrada->addons()->attach($calcetines->id, [
            'position' => 1, 'is_included' => false, 'included_quantity' => 1, 'is_mandatory' => false,
            'quantity_mode' => 'fixed', 'allow_extra' => true, 'choice_group' => null, 'max_qty' => null, 'requires_addon_id' => null,
        ]);
        $this->declarar(['kids' => ['vista' => 'kids', 'hechos' => ['product_details']]]);

        $fichas = $this->hechosDe('/kids')['product_details'];

        $ids = array_column($this->getJson('/api/v1/catalog/products')->assertOk()->json('data'), 'id');
        $this->assertSame([$entrada->id], $ids);
        $this->assertSame(
            array_map(fn (int $id): array => $this->getJson("/api/v1/catalog/products/{$id}")->assertOk()->json(), $ids),
            $fichas,
        );
        $this->assertSame(200, $fichas[0]['addons'][0]['price_cents'], 'el precio del complemento, el de su ficha');
    }

    /**
     * **Los componentes del paquete se pintan como `<x-instancia::…>`, desde su `web/components/`** (T4c·0, `#762`).
     * No hay mecanismo propio: Laravel resuelve un componente con espacio contra la carpeta `components/` de ese
     * espacio de vistas, subcarpetas incluidas. Lo que se guarda es la PROMESA hacia la instancia, que escribe sus
     * piezas contra ella. ❗ Y la otra mitad, que es de seguridad (`SEC-12`, como la de las vistas): un componente del
     * paquete que se llame como uno del producto NO lo suplanta; `<x-lucide>` sigue siendo el del producto.
     */
    public function test_the_package_components_are_painted_under_its_namespace_and_never_shadow_the_product_ones(): void
    {
        File::ensureDirectoryExists($this->paquete.'/web/components/grupo');
        File::put($this->paquete.'/web/components/pieza.blade.php', "@props(['n'])\n<b data-n=\"{{ \$n }}\">{{ \$slot }}</b>");
        File::put($this->paquete.'/web/components/grupo/hija.blade.php', '<i {{ $attributes }}>hija</i>');
        File::put($this->paquete.'/web/components/lucide.blade.php', 'el icono del intruso');
        // Una vista con nombre PROPIO: los demás casos compilan `kids` y, reescrita en el mismo segundo, Blade no la
        // daría por caducada (compara fechas de fichero) y pintaría la compilada de antes.
        File::put($this->paquete.'/web/con-piezas.blade.php', '<x-instancia::pieza n="7">hola</x-instancia::pieza><x-instancia::grupo.hija class="z" /><x-lucide name="clock" />');
        $this->declarar(['kids' => ['vista' => 'con-piezas', 'hechos' => []]]);

        $html = (string) $this->get('/kids')->assertOk()->getContent();

        $this->assertStringContainsString('<b data-n="7">hola</b>', $html);
        $this->assertStringContainsString('<i class="z">hija</i>', $html);
        $this->assertStringNotContainsString('el icono del intruso', $html);
        $this->assertStringContainsString('<svg', $html, '<x-lucide> es el del producto');
    }

    /**
     * **Una página pide las entradas del producto por su NOMBRE** (`scripts` de `<x-pagina>`, T4d·4 de §4.12): el
     * cargador del cajón y la calculadora, y nada más —un nombre que el producto no conoce no carga nada: la instancia
     * no nombra ficheros del producto—. Con la calculadora, el layout da lo que solo sabe el producto: sus textos y el
     * TITULAR de la cesta, que tiene que ser el MISMO que el arranque del motor (`SidebarMountTest`): leer la cesta
     * guardada con otro titular la PURGA (`cart.js::decideOwnership`), así que un titular distinto borraría la cesta de
     * quien solo mira la página.
     */
    public function test_a_page_asks_for_the_product_entries_by_name_and_gets_the_same_cart_owner_as_the_engine(): void
    {
        File::put($this->paquete.'/web/con-calculadora.blade.php', <<<'BLADE'
<x-pagina titulo="Kids" :scripts="['cajon', 'calculadora', 'resources/js/app.js', 'otra']"><p>hola</p></x-pagina>
BLADE);
        File::put($this->paquete.'/web/sin-calculadora.blade.php', '<x-pagina titulo="Kids"><p>hola</p></x-pagina>');
        $this->declarar(['kids' => ['vista' => 'con-calculadora', 'hechos' => []], 'jump' => ['vista' => 'sin-calculadora', 'hechos' => []]]);

        $html = (string) $this->get('/kids')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<script type="module" src="[^"]*/build/assets/paquete-[\w-]+\.js"#', $html);
        $this->assertMatchesRegularExpression('#<script type="module" src="[^"]*/build/assets/montar-[\w-]+\.js"#', $html);
        $this->assertDoesNotMatchRegularExpression('#/build/assets/app-[\w-]+\.js#', $html, 'un nombre que no es de la lista no carga nada, tampoco una ruta');
        // El paquete del cajón son DOS líneas (`#636`): con el cargador, su hoja; si no, el lateral de la cuenta sale sin
        // estilo (T4e·2, medido).
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*/css/cajon\.css\?v=#', $html);

        $motor = fn (string $html): array => preg_match('#<script type="application/json" id="jw-calculadora-motor">(.*?)</script>#s', $html, $m) === 1
            ? json_decode($m[1], true, 512, JSON_THROW_ON_ERROR) : [];
        $invitado = $motor($html);
        $this->assertArrayHasKey('owner', $invitado);
        $this->assertNull($invitado['owner']);
        $this->assertSame(__('isla.calculadora'), $invitado['textos']['calculadora']);
        $this->assertSame(__('isla.pieza'), $invitado['textos']['pieza']);

        $user = User::factory()->create();
        $this->assertSame($user->id, $motor((string) $this->actingAs($user)->get('/kids')->getContent())['owner'] ?? null);

        // Sin pedirla, ni su entrada ni lo del motor.
        $sin = (string) $this->get('/jump')->assertOk()->getContent();
        $this->assertStringNotContainsString('jw-calculadora-motor', $sin);
        $this->assertDoesNotMatchRegularExpression('#/build/assets/(montar|paquete)-#', $sin);
        $this->assertStringNotContainsString('css/cajon.css', $sin, 'Sin el cajón, ni su hoja.');
    }

    /**
     * **Las horas de HOY de cada entrada** (`availability_today`, T4e): de ellas salen «Quedan huecos», «Reservar para hoy»
     * y «Hoy, 1 hora cuesta…». Es el único hecho que no pasa por su controlador (medido: 160–180 ms por el catálogo sin
     * memorizar), así que se prueba que da el MISMO JSON que `POST /availability/{id}/times` con la fecha de hoy DEL
     * PARQUE, y solo para las entradas: un pack no pregunta «¿quedan huecos hoy?».
     */
    public function test_a_page_gets_todays_times_of_each_entry_with_the_same_json_as_the_api(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 10:00', 'Europe/Madrid'));
        $tarifa = RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zona = Zone::create(['slug' => 'kids', 'name' => ['es' => 'Kids'], 'position' => 1, 'is_active' => true, 'max_guests_per_slot' => 60, 'max_per_slot' => 0, 'prep_blocks_cupo' => false]);
        foreach (['17:00:00', '18:00:00'] as $inicio) {
            Slot::create(['zone_id' => $zona->id, 'date' => '2026-10-06', 'start_time' => $inicio, 'end_time' => Carbon::parse($inicio)->addHour()->format('H:i:s'), 'capacity' => 10, 'online_capacity' => 10]);
        }
        $entrada = TicketType::create(['name' => ['es' => 'Kids · 1 hora'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $zona->id, 'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1]);
        $entrada->prices()->create(['rate_type_id' => $tarifa->id, 'amount_cents' => 800]);
        $pack = TicketType::create(['name' => ['es' => 'Pack'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zona->id, 'duration_min' => 120, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 2]);
        $pack->prices()->create(['rate_type_id' => $tarifa->id, 'amount_cents' => 1500]);
        $this->declarar(['kids' => ['vista' => 'kids', 'hechos' => ['availability_today']]]);

        $hoy = $this->hechosDe('/kids')['availability_today'];

        $this->assertSame([$entrada->id], array_column($hoy, 'product_id'), 'Solo las entradas.');
        $api = $this->postJson("/api/v1/availability/{$entrada->id}/times", ['date' => '2026-10-06'])->assertOk()->json('data');
        $this->assertCount(2, $api, 'El caso necesita horas de verdad: una lista vacía coincidiría por casualidad.');
        $this->assertSame($api, $hoy[0]['data']);
        Carbon::setTestNow();
    }

    /**
     * **La ISLA de una página, por su nombre** (T4e de §4.12): con `isla` en `scripts`, la página carga la entrada de la
     * isla y el layout le da, en `#jw-isla-pagina`, lo de la página (su `isla`) más lo que solo sabe el producto —si hay
     * sesión, dónde está la política de cookies y los textos de la isla SIN los de la compra ni la calculadora, que
     * viajan con ellas—. Sin pedirla, nada.
     */
    public function test_a_page_asks_for_the_isla_by_name_and_gets_its_config_texts_and_session(): void
    {
        File::put($this->paquete.'/web/con-isla.blade.php', <<<'BLADE'
<x-pagina titulo="Kids" :scripts="['isla']" :isla="['page' => ['kind' => 'producto', 'product' => 'kids', 'action' => ['label' => 'Reservar Kids', 'href' => '#precio'], 'from' => 'Desde 8 €']]"><div data-jw-isla></div></x-pagina>
BLADE);
        File::put($this->paquete.'/web/sin-isla.blade.php', '<x-pagina titulo="Kids"><p>hola</p></x-pagina>');
        $this->declarar(['kids' => ['vista' => 'con-isla', 'hechos' => []], 'jump' => ['vista' => 'sin-isla', 'hechos' => []]]);

        $isla = function (string $html): ?array {
            return preg_match('#<script type="application/json" id="jw-isla-pagina">(.*?)</script>#s', $html, $m) === 1
                ? json_decode($m[1], true, 512, JSON_THROW_ON_ERROR) : null;
        };

        $html = (string) $this->get('/kids')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<script type="module" src="[^"]*/build/assets/montar-[\w-]+\.js"#', $html);
        $invitado = $isla($html);
        $this->assertSame('Reservar Kids', $invitado['config']['page']['action']['label']);
        $this->assertNull($invitado['config']['owner']);
        $this->assertSame(route('legal.cookies'), $invitado['config']['cookiesUrl']);
        $this->assertSame(__('isla.hoy'), $invitado['textos']['hoy']);
        $this->assertArrayNotHasKey('compra', $invitado['textos'], 'Los textos de la compra viajan con la compra, no aquí.');
        $this->assertArrayNotHasKey('calculadora', $invitado['textos']);
        // T5c: los de Mi cuenta viven en el motor y viajan con la sesión; aquí eran 4 KB en cada página (`PERF-02`).
        $this->assertArrayNotHasKey('mi_cuenta', $invitado['textos']);
        $this->assertArrayNotHasKey('mi_cuenta_alta', $invitado['textos']);
        $this->assertNull($invitado['config']['cuenta'], 'sin sesión, nada de la cuenta');
        // T5e·2 (`#779`): el `status` que deja el servidor al volver (Google, el correo…) viaja AQUÍ, con su tono: el
        // motor pide su arranque después, con el `status` ya gastado. Sin él, nada.
        $this->assertNull($invitado['config']['aviso']);
        $conAviso = $isla((string) $this->withSession(['status' => 'google-provider-taken'])->get('/kids')->getContent());
        $this->assertSame(['texto' => __('account.status.google-provider-taken'), 'tono' => 'danger'], $conAviso['config']['aviso'] ?? null);

        $user = User::factory()->create();
        $conSesion = $isla((string) $this->actingAs($user)->get('/kids')->getContent())['config'] ?? [];
        $this->assertSame($user->id, $conSesion['owner'] ?? null);
        $this->assertSame(['pending' => false, 'pendingText' => null, 'task' => null, 'bookingToday' => null], $conSesion['cuenta'] ?? null, 'con sesión y sin reservas: la cuenta, sin nada que decir');

        $this->assertNull($isla((string) $this->get('/jump')->assertOk()->getContent()), 'Sin pedirla, ni su JSON.');
    }

    /**
     * **El `<body>` de una página nueva lleva EXACTAMENTE el estado de la web de siempre** (T4b·4 de §4.12): el
     * consentimiento, la analítica y los píxeles que leen el aviso de cookies y sus cargadores. Sin él, las páginas
     * nuevas saldrían sin aviso y ciegas para la analítica. Se compara atributo a atributo con la portada (el layout de
     * siempre), con un píxel configurado para que la lista no sea solo la de cookies.
     */
    public function test_a_page_carries_exactly_the_same_body_state_as_the_classic_layout(): void
    {
        Setting::updateOrCreate(['key' => Pixels::KEY_META_PIXEL_ID], ['value' => '1234567890123456', 'group' => 'marketing']);
        File::put($this->paquete.'/web/limpia.blade.php', '<x-pagina titulo="Kids"><p>hola</p></x-pagina>');
        $this->declarar(['kids' => ['vista' => 'limpia', 'hechos' => []]]);

        $estado = function (string $html): array {
            preg_match('#<body\b([^>]*)>#s', $html, $body) === 1 || $this->fail('sin <body>');
            preg_match_all('#\b(data-(?:cookie|consent|analytics|pixel)[\w-]*)="([^"]*)"#', $body[1], $m, PREG_SET_ORDER);

            return array_column($m, 2, 1);
        };

        $nueva = $estado((string) $this->get('/kids')->assertOk()->getContent());
        $this->assertSame($estado((string) $this->get('/')->assertOk()->getContent()), $nueva);
        $this->assertSame('1234567890123456', $nueva['data-pixel-meta'] ?? null, 'El píxel configurado viaja también en la página nueva.');
        $this->assertArrayHasKey('data-consent-categories', $nueva);
    }

    /**
     * **Las transiciones entre páginas, a petición de la página** (Z2 de §4.14, `#781`): con `transiciones`, la regla
     * `@view-transition` va EN LÍNEA y antes de la primera hoja —desde una hoja externa, Chromium no la ve a tiempo en
     * una página grande y la transición no ocurre nunca (medido)—. Sin pedirla, ninguna: es del diseño del paquete.
     */
    public function test_a_page_asks_for_page_transitions_and_gets_the_rule_inline_before_any_sheet(): void
    {
        File::put($this->paquete.'/web/con.blade.php', '<x-pagina titulo="Kids" transiciones :hojas="[\'instancia/css/saltia.css\']"><p>hola</p></x-pagina>');
        File::put($this->paquete.'/web/sin.blade.php', '<x-pagina titulo="Kids" :hojas="[\'instancia/css/saltia.css\']"><p>hola</p></x-pagina>');
        $this->declarar(['kids' => ['vista' => 'con', 'hechos' => []], 'jump' => ['vista' => 'sin', 'hechos' => []]]);

        $html = (string) $this->get('/kids')->assertOk()->getContent();
        $regla = strpos($html, '<style>@view-transition { navigation: auto; }</style>');
        $hoja = strpos($html, '<link rel="stylesheet"');
        $this->assertNotFalse($regla, 'con `transiciones`, la regla en línea');
        $this->assertNotFalse($hoja, 'el caso necesita una hoja: sin ella, «antes» se cumpliría por casualidad');
        $this->assertLessThan($hoja, $regla, 'antes de toda hoja');
        $this->assertLessThan(strpos($html, '</head>'), $regla);

        $this->assertStringNotContainsString('@view-transition', (string) $this->get('/jump')->assertOk()->getContent(), 'sin pedirla, ninguna');
    }

    /** Sin paquete, o con un paquete que no declara páginas, no hay ninguna: el estado normal, no un error. */
    public function test_without_a_declaration_there_are_no_pages(): void
    {
        config(['instancia.ruta' => null]);
        app()->forgetInstance(InstancePages::class);

        $this->assertSame([], app(InstancePages::class)->todas());

        config(['instancia.ruta' => $this->paquete]);
        app()->forgetInstance(InstancePages::class);

        $this->assertSame([], app(InstancePages::class)->todas(), 'un paquete sin config/paginas.php no declara ninguna');
    }
}
