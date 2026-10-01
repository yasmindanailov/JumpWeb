<?php

namespace Tests\Feature\Fiesta;

use App\Http\Fiesta\ImagenInvitacion;
use App\Http\Instancia\InstanceViews;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * **LA IMAGEN DE LA INVITACIÓN AL COMPARTIR** (`#815`, la B; `fiesta-sistema-nuevo.md` §4.19), la I1: el dibujo con GD.
 * Se prueba con las fuentes DejaVu de la Sail —las del cliente no entran en el producto— y colores puestos a mano; el kit
 * de la instancia ({@see ImagenInvitacion::estilo}), con un paquete de prueba fuera del árbol (`SEC-12`).
 *
 * ⚠️ Los colores se leen del JPEG con margen (la compresión mueve un canal unas unidades en lo liso).
 */
class ImagenInvitacionTest extends TestCase
{
    private const NEGRITA = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    private const NORMAL = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

    private const COLORES = ['banda' => '#17c8f5', 'chip' => '#ffc400', 'chipLetra' => '#0b2e4a', 'acento' => '#065b7e',
        'tinta' => '#0b2e4a', 'nieve' => '#ffffff', 'bits' => ['#ffc400', '#ff3d8b', '#b6e80f', '#ffffff']];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertFileExists(self::NEGRITA, 'Las pruebas dibujan con DejaVu de la Sail: si la imagen de Docker cambia, se ve aquí.');
        $this->assertFileExists(self::NORMAL);
    }

    /** @return array{colores: array{banda: string, chip: string, chipLetra: string, acento: string, tinta: string, nieve: string, bits: list<string>}, decor: string, fuentes: array<string, string>, logo: ?string} */
    private function estilo(string $decor = 'confeti', ?string $logo = null): array
    {
        return ['colores' => self::COLORES, 'decor' => $decor, 'fuentes' => ['titular' => self::NEGRITA, 'texto' => self::NORMAL, 'etiqueta' => self::NEGRITA], 'logo' => $logo];
    }

    /** @return array{nombre: string, edad: ?string, frase: string, cuando: string, unidad: string} */
    private function datos(string $nombre = 'Vera', ?string $edad = '7'): array
    {
        return ['nombre' => $nombre, 'edad' => $edad, 'frase' => $edad === null ? 'te invita a saltar' : "cumple {$edad} años y te invita a saltar",
            'cuando' => 'Sábado 26 de septiembre · 17:00', 'unidad' => 'años'];
    }

    private function cerca(string $jpeg, int $x, int $y, string $hex, int $margen = 10): bool
    {
        $im = imagecreatefromstring($jpeg);
        $c = imagecolorat($im, $x, $y);
        $h = ltrim($hex, '#');
        foreach ([[16, 0], [8, 2], [0, 4]] as [$desplazar, $desde]) {
            if (abs((($c >> $desplazar) & 255) - (int) hexdec(substr($h, $desde, 2))) > $margen) {
                return false;
            }
        }

        return true;
    }

    public function test_it_draws_a_1200_by_630_jpeg_under_300_kb_and_the_same_input_gives_the_same_bytes(): void
    {
        foreach (['confeti', 'fiesta', 'burbujas'] as $decor) {
            $jpeg = ImagenInvitacion::dibujar($this->datos(), $this->estilo($decor));
            $medidas = getimagesizefromstring($jpeg);

            $this->assertSame([ImagenInvitacion::ANCHO, ImagenInvitacion::ALTO], [$medidas[0], $medidas[1]], $decor);
            $this->assertSame('image/jpeg', $medidas['mime'], $decor);
            $this->assertLessThan(300 * 1024, strlen($jpeg), "WhatsApp la descarta por encima: {$decor}");
            $this->assertSame($jpeg, ImagenInvitacion::dibujar($this->datos(), $this->estilo($decor)), 'Determinista: la caché de la I2 depende de ello.');
        }
    }

    public function test_the_band_the_card_the_chip_and_the_name_take_the_colours_of_the_theme(): void
    {
        $jpeg = ImagenInvitacion::dibujar($this->datos(), $this->estilo());

        $this->assertTrue($this->cerca($jpeg, 40, 600, self::COLORES['banda']), 'La banda, a sangre.');
        $this->assertTrue($this->cerca($jpeg, 600, 530, self::COLORES['nieve']), 'La tarjeta blanca encima.');
        $this->assertTrue($this->cerca($jpeg, 170, 150, self::COLORES['chip']), 'La chapa de la edad, en el borde de la tarjeta.');
        $this->assertTrue($this->cerca($jpeg, 220, 100, self::COLORES['chip']), 'La chapa asoma por encima de la tarjeta.');

        // El nombre, en el color de ACENTO del tema: medido como tinta de ese color en su caja.
        $im = imagecreatefromstring($jpeg);
        $acento = 0;
        for ($y = 270; $y < 360; $y += 2) {
            for ($x = 160; $x < 700; $x += 2) {
                $c = imagecolorat($im, $x, $y);
                if (abs((($c >> 16) & 255) - 0x06) < 24 && abs((($c >> 8) & 255) - 0x5B) < 24 && abs(($c & 255) - 0x7E) < 24) {
                    $acento++;
                }
            }
        }
        $this->assertGreaterThan(500, $acento, 'El nombre se escribe en el acento del tema.');
    }

    public function test_without_an_age_there_is_no_chip(): void
    {
        $jpeg = ImagenInvitacion::dibujar($this->datos('Hugo', null), $this->estilo());

        $this->assertTrue($this->cerca($jpeg, 220, 100, self::COLORES['banda']), 'Sin edad, donde iría la chapa se ve la banda.');
        $this->assertTrue($this->cerca($jpeg, 170, 160, self::COLORES['nieve']), 'Y la tarjeta, entera.');
    }

    public function test_the_logo_goes_top_right_inside_the_card(): void
    {
        $logo = sys_get_temp_dir().'/logo-prueba-'.getmypid().'.png';
        $rojo = imagecreatetruecolor(400, 147);
        imagefilledrectangle($rojo, 0, 0, 399, 146, (int) imagecolorallocate($rojo, 220, 30, 30));
        imagepng($rojo, $logo);

        $jpeg = ImagenInvitacion::dibujar($this->datos(), $this->estilo('confeti', $logo));
        @unlink($logo);

        $this->assertTrue($this->cerca($jpeg, 940, 220, '#dc1e1e'), 'El logotipo, arriba a la derecha dentro de la tarjeta.');
        $this->assertTrue($this->cerca($jpeg, 600, 530, self::COLORES['nieve']));
    }

    public function test_a_short_name_takes_one_big_line_a_long_one_two_balanced_lines_and_one_without_spaces_shrinks(): void
    {
        $corto = ImagenInvitacion::ajustarNombre('Vera', self::NEGRITA, 880);
        $this->assertSame(['px' => 104, 'lineas' => ['Vera'], 'bases' => [355.0]], $corto);

        $largo = ImagenInvitacion::ajustarNombre('Valentina Martínez-Ortega', self::NEGRITA, 880);
        $this->assertSame(['Valentina', 'Martínez-Ortega'], $largo['lineas'], 'Partido por el espacio que deja las dos líneas más parejas.');
        $this->assertGreaterThanOrEqual(48, $largo['px']);
        $this->assertLessThanOrEqual(72, $largo['px']);
        $this->assertSame(380.0, $largo['bases'][1], 'La segunda línea, siempre en el mismo sitio.');
        $this->assertGreaterThanOrEqual(259.0, $largo['bases'][0] - 0.72 * $largo['px'], 'La primera, por debajo del logotipo.');

        $junto = ImagenInvitacion::ajustarNombre('Xiadaniitzayanavalentinamaria', self::NEGRITA, 880);
        $this->assertCount(1, $junto['lineas'], 'Sin espacio por donde partir, una línea: nunca se corta.');
        $this->assertLessThan(72, $junto['px']);
    }

    public function test_what_the_font_cannot_write_is_taken_out_before_drawing(): void
    {
        $this->assertSame('Noa', ImagenInvitacion::limpiar('Noa 🎈', self::NEGRITA), 'Un emoji: GD lo pinta como basura («ð» y cajas).');
        $this->assertSame('Noa', ImagenInvitacion::limpiar('Noa 一', self::NEGRITA), 'Un carácter que la fuente no tiene.');
        $this->assertSame('Ána', ImagenInvitacion::limpiar("A\u{0301}na", self::NEGRITA), 'Compuesto: «A» + tilde suelta es «Á».');
        $this->assertSame('Ana María', ImagenInvitacion::limpiar("  Ana \t  María\n", self::NEGRITA), 'Los espacios, en uno.');

        $this->expectException(\InvalidArgumentException::class);
        ImagenInvitacion::dibujar($this->datos('🎈'), $this->estilo());
    }

    public function test_the_style_comes_from_the_instance_kit_with_the_product_neutrals_underneath(): void
    {
        $paquete = sys_get_temp_dir().'/instancia-imagen-'.getmypid();
        $carpeta = 'prueba-imagen-'.getmypid();
        $publicoExistia = is_dir(public_path(InstanceViews::PUBLICO));
        File::ensureDirectoryExists($paquete.'/'.InstanceViews::SUBCARPETA);
        File::ensureDirectoryExists(public_path(InstanceViews::PUBLICO."/{$carpeta}/fuentes"));
        File::ensureDirectoryExists(public_path(InstanceViews::PUBLICO."/{$carpeta}/css"));
        File::copy(self::NEGRITA, public_path(InstanceViews::PUBLICO."/{$carpeta}/fuentes/negrita.ttf"));
        File::copy(self::NORMAL, public_path(InstanceViews::PUBLICO."/{$carpeta}/fuentes/normal.ttf"));
        File::put(public_path(InstanceViews::PUBLICO."/{$carpeta}/css/marca.css"), ':root { --azul: #17C8F5; }');
        File::put(public_path(InstanceViews::PUBLICO."/{$carpeta}/css/fiesta.css"), ':root { --fiesta-agua-500: var(--azul); }');
        File::put(public_path(InstanceViews::PUBLICO."/{$carpeta}/css/mala.css"), ':root { --fiesta-agua-500: rgba(0, 0, 0, .5); }');
        config(['instancia.ruta' => $paquete]);
        $manifiesto = fn (array $m) => File::put($paquete.'/'.InstanceViews::MANIFIESTO, (string) json_encode($m));
        $kit = ['titular' => "{$carpeta}/fuentes/negrita.ttf", 'texto' => "{$carpeta}/fuentes/normal.ttf", 'etiqueta' => "{$carpeta}/fuentes/negrita.ttf"];

        try {
            $manifiesto(['contrato' => InstanceViews::CONTRATO]);
            $this->assertNull(ImagenInvitacion::estilo('confeti'), 'Sin kit, ninguna imagen: la `og:image` de antes.');

            $manifiesto(['fuentes' => ['imagen' => array_diff_key($kit, ['etiqueta' => true])]]);
            $this->assertNull(ImagenInvitacion::estilo('confeti'), 'Con el kit a medias, tampoco.');

            $manifiesto(['fuentes' => ['imagen' => $kit]]);
            $neutro = ImagenInvitacion::estilo('confeti');
            $this->assertSame('#1aa6c9', $neutro['colores']['banda'] ?? null, 'Sin hojas de la instancia, los neutros del producto.');
            $this->assertSame('#ffffff', $neutro['colores']['nieve']);
            $this->assertSame('confeti', $neutro['decor']);
            $this->assertSame(realpath(public_path(InstanceViews::PUBLICO."/{$carpeta}/fuentes/negrita.ttf")), $neutro['fuentes']['titular']);
            $logo = public_path('img/client-logo@4x.png');
            $this->assertSame(is_file($logo) ? $logo : null, $neutro['logo'], 'El logotipo de la instalación, si lo hay.');

            $manifiesto(['fuentes' => ['imagen' => $kit], 'hojas' => ['fiesta' => ["{$carpeta}/css/marca.css", "{$carpeta}/css/fiesta.css"]]]);
            $this->assertSame('#17c8f5', ImagenInvitacion::estilo('confeti')['colores']['banda'] ?? null, 'Los roles de la instancia, con su cadena resuelta.');
            $this->assertSame('#c2327a', ImagenInvitacion::estilo('fiesta')['colores']['banda'] ?? null, 'Cada tema con su banda.');

            $manifiesto(['fuentes' => ['imagen' => $kit], 'hojas' => ['fiesta' => ["{$carpeta}/css/mala.css"]]]);
            $this->assertNull(ImagenInvitacion::estilo('confeti'), 'Un color que no llega a un hex: ninguna imagen, nunca una a medias.');
        } finally {
            File::deleteDirectory($paquete);
            File::deleteDirectory(public_path(InstanceViews::PUBLICO."/{$carpeta}"));
            if (! $publicoExistia) {
                File::deleteDirectory(public_path(InstanceViews::PUBLICO));
            }
        }
    }
}
