<?php

namespace Tests\Feature\Mail;

use App\Domain\Content\Services\Lucide;
use App\Domain\Platform\Models\Setting;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailIcons;
use App\Notifications\Support\MailTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LOS ICONOS DE LOS CORREOS** (la R1b, `specs/correos-rediseno.md` §4.1.3): máscaras PNG de PALETA, generadas del Lucide
 * versionado (`scripts/correo-mascaras.mjs`) y teñidas reescribiendo SOLO su `PLTE` —sin GD ni Imagick en producción—, servidas
 * por una ruta aislada como el píxel que no apunta nada.
 *
 * ⚠️ Los colores esperados van escritos A MANO: leerlos de `MailTheme::COLORES` probaría la tabla contra sí misma.
 */
class MailIconsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MailIcons::olvidar();
        MailTheme::olvidar();
    }

    /**
     * Los trozos de un PNG, en orden: tipo → datos. Y comprueba el CRC de cada uno: un teñido que se lo dejara mal daría una
     * imagen que unos gestores enseñan y otros no.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function trozos(string $png): array
    {
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $trozos = [];
        for ($pos = 8; $pos + 12 <= strlen($png);) {
            $largo = unpack('N', substr($png, $pos, 4))[1];
            $tipo = substr($png, $pos + 4, 4);
            $datos = substr($png, $pos + 8, $largo);
            $this->assertSame(unpack('N', substr($png, $pos + 8 + $largo, 4))[1], crc32($tipo.$datos), "el CRC de {$tipo} no cuadra");
            $trozos[] = [$tipo, $datos];
            $pos += 12 + $largo;
        }

        return $trozos;
    }

    /** Las máscaras son las del manifiesto, byte a byte, y cada una es un PNG de paleta con su alfa en `tRNS`. */
    public function test_every_mask_matches_its_manifest_and_is_a_palette_png(): void
    {
        $m = MailIcons::manifiesto();
        $this->assertSame('lucide-static@'.Lucide::VERSION, $m['origen'], 'las máscaras salen del Lucide que usa el producto');
        $this->assertSame(72, $m['lado']);
        $this->assertNotEmpty($m['iconos']);

        $enDisco = array_map(static fn (string $f): string => basename($f, '.png'), glob(resource_path(MailIcons::CARPETA.'/*.png')) ?: []);
        sort($enDisco);
        $this->assertSame(array_keys($m['iconos']), $enDisco, 'una máscara sin manifiesto, o un icono del manifiesto sin máscara');

        foreach ($m['iconos'] as $nombre => $datos) {
            $png = (string) file_get_contents(resource_path(MailIcons::CARPETA."/{$nombre}.png"));
            $this->assertSame($datos['sha256'], hash('sha256', $png), "{$nombre}: la máscara no es la del manifiesto");
            $this->assertFileExists(Lucide::path($nombre), "{$nombre}: no es un icono de Lucide");

            $trozos = $this->trozos($png);
            $this->assertSame(['IHDR', 'PLTE', 'tRNS', 'IDAT', 'IEND'], array_column($trozos, 0), "{$nombre}: los trozos de una máscara");
            $ihdr = unpack('Nancho/Nalto/Cbits/Ccolor', $trozos[0][1]);
            $this->assertSame([72, 72, 8, 3], [$ihdr['ancho'], $ihdr['alto'], $ihdr['bits'], $ihdr['color']], "{$nombre}: 72×72, 8 bits, de paleta");
            $this->assertSame(768, strlen($trozos[1][1]), "{$nombre}: las 256 entradas de paleta");
            $this->assertSame(implode('', array_map('chr', range(0, 255))), $trozos[2][1], "{$nombre}: el alfa de cada entrada es su índice");
        }
    }

    /**
     * ❗❗ **TEÑIR CAMBIA SOLO EL `PLTE`**: la cabecera, el alfa y los píxeles, byte a byte; la paleta, el color en sus 256
     * entradas; y la imagen se abre con ese color (GD es solo de la prueba: el producto no lo usa).
     */
    public function test_tinting_rewrites_only_the_palette_and_the_image_opens_with_that_colour(): void
    {
        $mascara = (string) file_get_contents(resource_path(MailIcons::CARPETA.'/map-pin.png'));
        $tenida = MailIcons::tenir($mascara, '#0E8FC4');

        $antes = $this->trozos($mascara);
        $despues = $this->trozos($tenida);
        $this->assertSame(array_column($antes, 0), array_column($despues, 0));
        foreach ($antes as $i => [$tipo, $datos]) {
            $this->assertSame($tipo === 'PLTE' ? str_repeat("\x0E\x8F\xC4", 256) : $datos, $despues[$i][1], "el trozo {$tipo}");
        }

        $im = imagecreatefromstring($tenida);
        $this->assertNotFalse($im, 'la imagen teñida no se abre');
        imagepalettetotruecolor($im);
        $opacos = $transparentes = 0;
        for ($y = 0; $y < 72; $y++) {
            for ($x = 0; $x < 72; $x++) {
                $c = imagecolorsforindex($im, imagecolorat($im, $x, $y));
                if ($c['alpha'] === 127) {
                    $transparentes++;
                } elseif ($c['alpha'] === 0) {
                    $opacos++;
                    $this->assertSame([14, 143, 196], [$c['red'], $c['green'], $c['blue']]);
                }
            }
        }
        $this->assertGreaterThan(300, $opacos, 'CONTROL: el icono tiene tinta');
        $this->assertGreaterThan(2500, $transparentes, 'CONTROL: y aire alrededor');
    }

    /**
     * La ruta: el icono teñido, SIN cookie (aislada como el píxel), y 404 fuera del manifiesto o del formato. Una versión
     * vieja se sirve igual: un correo ya enviado no se rompe.
     *
     * ⚠️ Con `no-store`: lo pone `NoStoreWebResponses`, global por `RGPD-04`, y no se le abre excepción (el criterio de las
     * fotos de las reseñas, `#524`; el coste, con `PERF-02`). Se asevera la PROPIEDAD, no la cadena, como su propia guarda.
     */
    public function test_the_route_serves_the_tinted_icon_without_cookies(): void
    {
        $url = (string) MailIcons::url('clock', '#737B83');
        $this->assertStringContainsString('/correo/i/'.MailIcons::manifiesto()['version'].'/737b83/clock.png', $url);

        $respuesta = $this->get($url);
        $respuesta->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(MailIcons::png('clock', '737b83'), $respuesta->getContent());
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertSame([], $respuesta->headers->getCookies(), 'un icono no deja nada en quien abre el correo');

        $this->get('/correo/i/000000000000/737b83/clock.png')->assertOk();
        $this->get('/correo/i/1/737b83/no-existe.png')->assertNotFound();
        $this->get('/correo/i/1/737b8/clock.png')->assertNotFound();
        $this->get('/correo/i/1/zzzzzz/clock.png')->assertNotFound();
        $this->assertNull(MailIcons::url('no-existe', '#737B83'));
        $this->assertNull(MailIcons::url('clock', 'rojo'));
    }

    /** Cada icono que pinta la plantilla está en el manifiesto: uno que no, dejaría su hueco sin que nada fallara. */
    public function test_every_icon_the_template_paints_has_its_mask(): void
    {
        $usados = [];
        foreach (glob(resource_path('views/correo/html/*.blade.php')) ?: [] as $f) {
            preg_match_all("/->icono\\(\\s*'([a-z0-9-]+)'/", (string) file_get_contents($f), $m);
            array_push($usados, ...$m[1]);
            preg_match_all("/\\['([a-z0-9-]+)',\\s*\\\$p->/", (string) file_get_contents($f), $m);
            array_push($usados, ...$m[1]);
        }
        $usados = array_values(array_unique($usados));

        $this->assertContains('map-pin', $usados, 'CONTROL: el escaneo no ve los iconos del pie');
        $this->assertContains('phone', $usados, 'CONTROL: ni los de sus enlaces');
        $this->assertSame([], array_values(array_filter($usados, static fn (string $n): bool => ! MailIcons::existe($n))),
            'iconos que la plantilla pinta y no tienen máscara: `scripts/correo-mascaras.mjs`');
    }

    /**
     * El pie pinta sus iconos del rol `icono` —decorativos, `alt=""`—, y solo los de los datos que existen.
     */
    public function test_the_footer_paints_its_icons_in_the_icon_role(): void
    {
        foreach (['contact.phone' => '600 00 00 00', 'contact.email' => 'hola@demo.test'] as $k => $v) {
            Setting::updateOrCreate(['key' => $k], ['value' => $v, 'group' => 'contact']);
        }
        Setting::flushMemo();

        $html = (string) (new BrandedMailMessage)->hero('emails.order_declined', 'info')->render();
        $pie = substr($html, (int) strpos($html, 'data-bloque="pie"'));

        foreach (['map-pin', 'phone', 'mail'] as $icono) {
            $this->assertMatchesRegularExpression("#<img src=\"[^\"]*/correo/i/[a-z0-9]+/737b83/{$icono}\\.png\" width=\"\\d+\" height=\"\\d+\" alt=\"\"#", $pie, $icono);
        }
        $this->assertStringNotContainsString('/message-circle.png', $pie, 'sin WhatsApp, sin su icono');
    }

    /**
     * ❗ **UNA IMAGEN NO CAMBIA CON EL OSCURO**: el rol `icono` tiene que verse (≥ 3:1, WCAG 1.4.11) sobre el fondo y el sutil
     * en claro Y en oscuro. El apagado del producto no llegaba (2,84 sobre el sutil oscuro, medido el 29-09).
     */
    public function test_the_icon_role_reads_on_both_surfaces_in_both_modes(): void
    {
        $t = MailTheme::current();
        $lum = static function (string $hex): float {
            $c = array_map(static fn (string $p): float => ($v = hexdec($p) / 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, str_split(ltrim($hex, '#'), 2));

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        $ratio = static fn (string $a, string $b): float => round((max($lum($a), $lum($b)) + 0.05) / (min($lum($a), $lum($b)) + 0.05), 2);

        $this->assertSame('#737B83', $t->claro('icono'));
        foreach ([$t->claro('fondo'), $t->claro('sutil'), $t->oscuro('fondo'), $t->oscuro('sutil')] as $fondo) {
            $this->assertGreaterThanOrEqual(3.0, $r = $ratio($t->claro('icono'), $fondo), "el icono sobre {$fondo} da {$r}");
        }
    }
}
