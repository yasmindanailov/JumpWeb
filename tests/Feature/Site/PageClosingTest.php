<?php

namespace Tests\Feature\Site;

use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL CIERRE DE LAS PÁGINAS INTERIORES** (`DECISIONES #526`, carril de diseño Fase 3 · T3a·4,
 * `specs/rediseno-desde-canvas.md` §5.5). Artboard `Layout Paginas PJP` 1a/1b.
 *
 * `[DECIDIDO owner, 2026-09-11]`: la tarjeta de la portada **sin el juego, sin el eslogan y con un
 * solo botón**, dentro de la banda de tinta del pie (la opción A, elegida sobre dos renderizadas).
 *
 * ▶ Lo que se vigila son las cosas que se rompen **en silencio**: que el cierre salga donde toca y
 * solo ahí, que no arrastre las piezas que el owner quitó, que la portada no pierda las suyas, y que
 * «Reservar» lleve al MISMO sitio en la portada y en una página — la regla vive en un componente
 * compartido precisamente para eso.
 */
class PageClosingTest extends TestCase
{
    use RefreshDatabase;

    /** Las páginas del inventario que hoy existen: las seis cierran con la tarjeta. */
    private const CON_CIERRE = ['/precios', '/normas', '/contacto', '/atracciones', '/cumpleanos', '/servicios'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    /**
     * **Cada página del inventario cierra con UNA tarjeta, dentro de la banda de tinta y antes del
     * resto del pie**, con la anatomía que el owner decidió.
     */
    public function test_every_inventory_page_closes_with_the_card_inside_the_ink_band(): void
    {
        $this->assertNotSame('', trim((string) Setting::value('contact.phone')),
            'la semilla no trae teléfono: el caso no distinguiría «un botón» de «el segundo no se pinta por falta de dato»');

        foreach (self::CON_CIERRE as $ruta) {
            $x = $this->xpath($this->get($ruta)->assertOk()->getContent());

            $cierres = $x->query('//'.$this->clase('section', 'reserve--pagina'));
            $this->assertSame(1, $cierres->length, "«{$ruta}» no cierra con exactamente una tarjeta");
            $cierre = $cierres->item(0);

            $pie = $x->query('ancestor::footer', $cierre)->item(0);
            $this->assertInstanceOf(DOMElement::class, $pie, "el cierre de «{$ruta}» no está dentro del pie");
            $this->assertSame('ink', $pie->getAttribute('data-surface'), "el pie de «{$ruta}» no es de tinta");

            // Lo PRIMERO de la banda: tarjeta y pie son un bloque que empieza por la tarjeta.
            $primero = $x->query('./'.$this->clase('div', 'foot__inner').'/*[1]', $pie)->item(0);
            $this->assertTrue($primero?->isSameNode($cierre) ?? false, "en «{$ruta}» la tarjeta no abre la banda del pie");

            $cuenta = fn (string $q): int => $x->query('.//'.$q, $cierre)->length;
            $this->assertSame(0, $cuenta($this->clase('p', 'reserve__slogan')), "«{$ruta}» arrastra el eslogan de la portada");
            $this->assertSame(0, $cuenta('canvas'), "«{$ruta}» arrastra el minijuego de la portada");
            $this->assertSame(1, $cuenta($this->clase('a', 'reserve__act')), "«{$ruta}» no tiene un solo botón");
            $this->assertSame(0, $cuenta($this->clase('a', 'reserve__act--alt')), "«{$ruta}» vuelve a ofrecer «Llamar»");
            $this->assertSame(1, $cuenta($this->clase('span', 'reserve__tag')), "«{$ruta}» perdió el hueco de la chapa");
            $this->assertSame(1, $cuenta($this->clase('div', 'grain')), "«{$ruta}» perdió la trama");
        }
    }

    /**
     * **La portada conserva su cierre entero** (el eslogan, el juego y los dos botones) y no se lleva
     * además la tarjeta de las interiores. CONTROL del caso de arriba: prueba que el componente
     * compartido no le quitó nada.
     */
    public function test_the_home_keeps_its_own_closing_and_no_page_card(): void
    {
        $x = $this->xpath($this->get('/')->assertOk()->getContent());

        $this->assertSame(0, $x->query('//'.$this->clase('section', 'reserve--pagina'))->length,
            'la portada pinta también el cierre de las interiores');

        $cierre = $x->query('//section[@id="reserve"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $cierre, 'la portada perdió su cierre');

        $cuenta = fn (string $q): int => $x->query('.//'.$q, $cierre)->length;
        $this->assertSame(1, $cuenta($this->clase('p', 'reserve__slogan')), 'la portada perdió el eslogan del cierre');
        $this->assertSame(1, $cuenta('canvas'), 'la portada perdió el minijuego');
        $this->assertSame(2, $cuenta($this->clase('a', 'reserve__act')), 'la portada no ofrece «Reservar» y «Llamar»');
    }

    /**
     * **Ni las legales ni las pantallas de servicio cierran con «Reservar»**: no son páginas del
     * inventario, y una tarjeta de compra en la pantalla de restablecer la contraseña o en la de
     * mantenimiento sería ruido en el peor momento.
     */
    public function test_legal_and_service_screens_do_not_close_with_a_card(): void
    {
        foreach (['/privacidad' => 200, '/email/verificar' => 200, '/restablecer-contrasena/token-de-prueba' => 200] as $ruta => $estado) {
            $x = $this->xpath($this->get($ruta)->assertStatus($estado)->getContent());
            $this->assertSame(0, $x->query('//'.$this->clase('section', 'reserve--pagina'))->length, "«{$ruta}» cierra con una tarjeta de compra");
        }

        $this->ajuste('maintenance.page.precios', '1', 'maintenance');
        $x = $this->xpath($this->get('/precios')->assertStatus(503)->getContent());
        $this->assertSame(0, $x->query('//'.$this->clase('section', 'reserve--pagina'))->length,
            'la pantalla de mantenimiento cierra con una tarjeta de compra');
    }

    /**
     * **«Reservar» lleva al MISMO sitio en la portada y en una página**, en los tres estados de la
     * venta: online → la compra · cerrada con teléfono → el teléfono · cerrada sin teléfono → las
     * tarifas. Es la regla que el componente compartido existe para no duplicar.
     */
    public function test_reserve_leads_to_the_same_place_on_the_home_and_on_a_page(): void
    {
        $casos = [
            'venta online' => [['sales.online_enabled' => '1'], fn (string $h) => $h === route('entradas')],
            'cerrada con teléfono' => [['sales.online_enabled' => '0'], fn (string $h) => str_starts_with($h, 'tel:')],
            'cerrada sin teléfono' => [['sales.online_enabled' => '0', 'contact.phone' => ''], fn (string $h) => $h === route('precios')],
        ];

        foreach ($casos as $nombre => [$ajustes, $esperado]) {
            foreach ($ajustes as $clave => $valor) {
                $this->ajuste($clave, $valor, explode('.', $clave)[0]);
            }

            $enPortada = $this->primerBoton('/', '//section[@id="reserve"]');
            $enPagina = $this->primerBoton('/normas', '//'.$this->clase('section', 'reserve--pagina'));

            $this->assertTrue($esperado($enPortada), "{$nombre}: «Reservar» de la portada lleva a «{$enPortada}»");
            $this->assertSame($enPortada, $enPagina, "{$nombre}: la portada y la página mandan a sitios distintos");
        }
    }

    /**
     * **La tarjeta la mide su contenido, se separa de la banda con el filete, reserva el sitio de la
     * chapa y no el del juego, y ocupa la fila entera del pie.**
     *
     * ⚠️ La última es el defecto que midió la sonda: desde 1024 px el pie reparte su fila con `flex`,
     * y sin estar en la lista de «fila entera» la tarjeta se quedaba con 610 px de 1120.
     */
    public function test_the_card_is_sized_by_its_content_and_takes_the_whole_row(): void
    {
        $site = $this->reglas('public/css/site.css');

        $caja = $this->declaracion($site, '.reserve--pagina .reserve__box');
        $this->assertMatchesRegularExpression('/min-height:\s*0\b/', $caja, 'la tarjeta vuelve a medir «el hueco que deja el pie», que aquí no significa nada');
        $this->assertMatchesRegularExpression('/border:\s*1px solid var\(--line\)/', $caja, 'tinta sobre tinta sin filete: la tarjeta se funde con la banda');

        // ⚠️ Hay DOS reglas `.reserve--pagina` —la base y la de escritorio, con su margen—: se busca la
        // que redefine el hueco, no «la» regla.
        $hueco = array_filter($site, fn (array $r): bool => $r[0] === ['.reserve--pagina'] && str_contains($r[1], '--salta-hueco:'));
        $this->assertNotEmpty($hueco, 'la tarjeta hereda el hueco del minijuego (122–156 px de aire muerto) en vez del de la chapa');

        $fila = array_filter($this->reglas('public/css/landing.css'),
            fn (array $r): bool => in_array('.reserve--pagina', $r[0], true) && in_array('.foot__colophon', $r[0], true)
                && preg_match('/flex:\s*0 0 100%/', $r[1]) === 1);
        $this->assertNotEmpty($fila, 'la tarjeta no está en la lista de lo que ocupa la fila entera del pie');
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    private function ajuste(string $clave, string $valor, string $grupo): void
    {
        Setting::updateOrCreate(['key' => $clave], ['value' => $valor, 'group' => $grupo]);
        // ⚠️ `Setting::value` memoiza la tabla ENTERA: sin esto la petición siguiente lee el valor viejo
        // y el caso pasa sin haber cambiado nada (la trampa de `cumple-mixto` §25.10).
        Setting::flushMemo();
    }

    private function primerBoton(string $ruta, string $cierre): string
    {
        $x = $this->xpath($this->get($ruta)->assertOk()->getContent());
        $boton = $x->query($cierre.'//'.$this->clase('a', 'reserve__act'))->item(0);
        $this->assertInstanceOf(DOMElement::class, $boton, "«{$ruta}» no tiene el botón del cierre");

        return $boton->getAttribute('href');
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    /** Un elemento por clase EXACTA: por subcadena, `reserve__act` casaría con `reserve__act--alt`. */
    private function clase(string $tag, string $clase): string
    {
        return $tag."[contains(concat(' ', normalize-space(@class), ' '), ' {$clase} ')]";
    }

    /** @return list<array{0: list<string>, 1: string}> */
    private function reglas(string $hoja): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(base_path($hoja)));
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER);

        return array_map(fn (array $r): array => [
            array_values(array_filter(array_map('trim', explode(',', $r[1])))),
            $r[2],
        ], $m);
    }

    private function declaracion(array $reglas, string $selector): string
    {
        $hay = array_values(array_filter($reglas, fn (array $r): bool => $r[0] === [$selector]));
        $this->assertCount(1, $hay, "se esperaba una regla `{$selector}`");

        return $hay[0][1];
    }
}
