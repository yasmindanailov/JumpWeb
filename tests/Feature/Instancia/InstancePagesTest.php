<?php

namespace Tests\Feature\Instancia;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Http\Instancia\InstancePages;
use App\Http\Instancia\InstanceViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
