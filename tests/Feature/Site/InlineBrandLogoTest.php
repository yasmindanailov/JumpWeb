<?php

namespace Tests\Feature\Site;

use App\Domain\Content\Services\InlineSvg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **EL LOGOTIPO EN LÍNEA — lo que lo hace posible y lo que lo hace SEGURO** (`DECISIONES #254`).
 *
 * `[DECIDIDO owner, 2026-08-29]`: el logotipo se anima, y para eso deja de servirse por `<img>` —
 * un `<img>` no se puede animar por dentro— y pasa a incrustarse en el documento.
 *
 * ⚠️⚠️ **Ese cambio abre una puerta que el `<img>` tenía cerrada.** Dentro de un `<img>`, un SVG es
 * inerte: el navegador no ejecuta sus scripts ni carga nada externo. **En línea, sí.** El fichero lo
 * pone el operador al instalar el paquete del cliente, así que no viene de un desconocido — pero
 * «lo puso alguien de confianza» es una suposición, no una defensa. Y una suposición sobre un
 * fichero que se copia por `rsync` desde la máquina de otra persona.
 *
 * ▶ Por eso la regla es **lista blanca y todo o nada**: si el fichero trae cualquier cosa
 * ejecutable, no se sirve el logotipo. Un logotipo que falta se ve; un script que se cuela, no.
 * La plantilla tiene su suelo —el nombre del sitio en texto—, así que quedarse sin dibujo degrada.
 */
class InlineBrandLogoTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/inline-svg-'.getmypid().'.svg';
        InlineSvg::forget();
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
        InlineSvg::forget();
        parent::tearDown();
    }

    private function serve(string $contents): string
    {
        file_put_contents($this->tmp, $contents);
        InlineSvg::forget();

        return InlineSvg::brand($this->tmp);
    }

    /** **Un SVG limpio SÍ se sirve** — sin esto, todo lo de abajo se cumpliría con un `return ''`. */
    public function test_a_clean_svg_is_served_whole(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><g id="fig"><path d="M0 0h10v10H0z"/></g></svg>';

        $servido = $this->serve($svg);

        $this->assertStringContainsString('id="fig"', $servido, 'la pieza que se anima tiene que llegar entera');
        $this->assertStringContainsString('<svg', $servido);
    }

    /** **Y las referencias INTERNAS se conservan**: son justamente lo que la animación necesita. */
    public function test_internal_references_survive(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><defs><path id="u1" d="M0 0"/></defs><use href="#u1"/></svg>';

        $this->assertStringContainsString('href="#u1"', $this->serve($svg));
    }

    /**
     * **Lo ejecutable NO se sanea: se descarta el fichero entero.**
     *
     * @dataProvider peligros
     */
    #[DataProvider('peligros')]
    public function test_anything_executable_makes_the_logo_disappear(string $caso, string $svg): void
    {
        $this->assertSame(
            '', $this->serve($svg),
            "un SVG con {$caso} se ha servido en línea.\n".
            '▶ La regla es todo o nada: sanear a medias deja al siguiente creyendo que está limpio. '.
            'Sin logotipo la plantilla cae a su suelo de texto, que es una degradación, no un fallo.',
        );
    }

    /** @return array<string, array{string, string}> */
    public static function peligros(): array
    {
        return [
            'un <script>' => ['un `<script>`', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'un manejador en línea' => ['un `onload=`', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><path d="M0 0"/></svg>'],
            'un onclick en un hijo' => ['un `onclick=` en un hijo', '<svg xmlns="http://www.w3.org/2000/svg"><path onclick="alert(1)" d="M0 0"/></svg>'],
            'un javascript: en un enlace' => ['un `javascript:`', '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><path d="M0 0"/></a></svg>'],
            'un foreignObject' => ['un `<foreignObject>`', '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml">x</body></foreignObject></svg>'],
            'una referencia externa' => ['un `<use>` externo', '<svg xmlns="http://www.w3.org/2000/svg"><use href="https://ajeno.example/x.svg#a"/></svg>'],
            'una imagen remota' => ['una `<image>` remota', '<svg xmlns="http://www.w3.org/2000/svg"><image href="//ajeno.example/x.png"/></svg>'],
            'algo que no es un SVG' => ['algo que no es un SVG', '<html><body>hola</body></html>'],
        ];
    }

    /** **Sin paquete instalado no hay logotipo, y eso NO es un error**: este repo no lleva marca. */
    public function test_a_missing_package_is_not_an_error(): void
    {
        $this->assertSame('', InlineSvg::brand(sys_get_temp_dir().'/no-existe-'.getmypid().'.svg'));
    }

    /**
     * **La cabecera XML y el DOCTYPE se van.**
     *
     * Dentro de un documento HTML no pintan nada, y un `<?xml` suelto en mitad del marcado es basura
     * que algunos parsers arrastran hasta el árbol.
     */
    public function test_the_xml_prologue_is_dropped(): void
    {
        $servido = $this->serve('<?xml version="1.0"?><!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>');

        $this->assertStringNotContainsString('<?xml', $servido);
        $this->assertStringNotContainsString('DOCTYPE', $servido);
        $this->assertStringStartsWith('<svg', $servido);
    }

    /**
     * **Y el armazón lo sirve DE VERDAD, con su nombre accesible.**
     *
     * ⚠️ El `<span role="img" aria-label>` no es adorno: al incrustar el SVG se pierde el `alt` que
     * daba el `<img>`, y este enlace es **el único que toda página tiene**. Sin nombre, quien navega
     * por voz se encuentra «enlace» a secas.
     */
    public function test_the_frame_serves_it_inline_with_an_accessible_name(): void
    {
        if (! is_file(public_path('img/client-logo.svg'))) {
            $this->markTestSkipped('no hay paquete de marca instalado en esta máquina: es lo normal en el producto');
        }

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('nav__brand-logo--inline', $html, 'el armazón no sirve el logotipo en línea');
        $this->assertMatchesRegularExpression(
            '/nav__brand-logo--inline"[^>]*role="img"[^>]*aria-label="[^"]+"/',
            $html,
            'el logotipo en línea se ha quedado sin nombre accesible: es el único enlace de TODA página.',
        );
        $this->assertStringContainsString('id="fig"', $html, 'no llega la pieza que se anima');
    }
}
