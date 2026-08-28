<?php

namespace Tests\Feature\Platform;

use App\Domain\Platform\Services\QrLogo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

/**
 * **Quién decide qué icono va dentro del QR** (`specs/identidad-qr-puerta.md` §9.7 C·3).
 *
 * Lo que se mide aquí es la **cadena de degradación**, porque ninguno de sus eslabones está en las
 * dos máquinas: Imagick lee SVG en staging y NO en local; `rsvg-convert` está en local y NO en
 * staging. Un fichero de tests que solo probase «el camino que hay en esta máquina» dejaría la mitad
 * del mecanismo sin red **justo en el entorno donde no se puede reproducir**.
 *
 * Por eso los eslabones son métodos protegidos y aquí se doblan uno a uno. El camino REAL (con el
 * rasterizador que tenga esta máquina) también se ejercita, en su propio caso.
 */
#[Group('platform')]
class QrLogoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nada de este fichero puede escribir en el `storage/` de verdad.
        Storage::fake('local');
    }

    // ─── El suelo: el icono del producto ──────────────────────────────────────────────────────

    /**
     * Sin icono de instalación se sirve el `apple-touch-icon.png` **del producto, tal cual**: ya es
     * PNG, así que este camino no depende de que haya rasterizador ninguno. Es el que van a recorrer
     * de hecho todas las instalaciones que aún no han entregado su marca.
     */
    public function test_with_no_installation_icon_it_serves_the_product_png_untouched(): void
    {
        $logo = $this->fake(['svg' => '/no/existe/client-favicon.svg']);

        $this->assertSame(file_get_contents(public_path('apple-touch-icon.png')), $logo->png());
        $this->assertSame(0, $logo->calls['imagick'] + $logo->calls['rsvg'], 'un PNG no se rasteriza');
    }

    /** Si ni el icono del producto está (instalación mutilada), se avisa y se sigue sin icono. */
    public function test_with_the_product_icon_missing_it_warns_and_gives_no_icon(): void
    {
        Log::spy();

        $logo = $this->fake(['svg' => '/no/existe.svg', 'product' => '/no/existe.png']);

        $this->assertNull($logo->png());
        Log::shouldHaveReceived('warning')->once();
    }

    // ─── La cadena de degradación ─────────────────────────────────────────────────────────────

    /** Con Imagick capaz de SVG (staging) manda Imagick y `rsvg-convert` ni se busca. */
    public function test_imagick_wins_when_it_can_read_svg(): void
    {
        $logo = $this->fake([
            'svg' => $this->svgFile(),
            'imagick' => true,
            'imagickPng' => $this->pngBytes(),
            'rsvg' => true,
            'rsvgPng' => $this->pngBytes(),
        ]);

        $this->assertSame($this->pngBytes(), $logo->png());
        $this->assertSame(1, $logo->calls['imagick']);
        $this->assertSame(0, $logo->calls['rsvg'], 'con Imagick no se abre un proceso');
    }

    /**
     * ⚠️ **Imagick cargado NO es Imagick capaz de SVG** (es el caso del contenedor local: le falta
     * `libmagickcore-6.q16-7-extra`). Cuando no puede, se baja a `rsvg-convert`.
     */
    public function test_it_falls_down_to_rsvg_when_imagick_cannot_read_svg(): void
    {
        $logo = $this->fake([
            'svg' => $this->svgFile(),
            'imagick' => false,
            'rsvg' => true,
            'rsvgPng' => $this->pngBytes(),
        ]);

        $this->assertSame($this->pngBytes(), $logo->png());
        $this->assertSame(0, $logo->calls['imagick']);
        $this->assertSame(1, $logo->calls['rsvg']);
    }

    /** Y si Imagick lo intenta y falla, el fallo tampoco corta la cadena: sigue `rsvg-convert`. */
    public function test_a_failing_imagick_still_hands_over_to_rsvg(): void
    {
        $logo = $this->fake([
            'svg' => $this->svgFile(),
            'imagick' => true,
            'imagickPng' => null,
            'rsvg' => true,
            'rsvgPng' => $this->pngBytes(),
        ]);

        $this->assertSame($this->pngBytes(), $logo->png());
        $this->assertSame(1, $logo->calls['imagick']);
        $this->assertSame(1, $logo->calls['rsvg']);
    }

    /**
     * ⚠️⚠️ **Sin ningún rasterizador la respuesta es `null`, NO el icono del producto.** Es la regla
     * de white-label: una instalación que entregó su marca no puede ver la «J» de JumpWeb impresa en
     * el correo de su cliente. Se degrada la calidad (QR liso), nunca la marca.
     */
    public function test_with_no_rasterizer_it_gives_no_icon_and_never_the_product_one(): void
    {
        Log::spy();

        $logo = $this->fake(['svg' => $this->svgFile(), 'imagick' => false, 'rsvg' => false]);

        $png = $logo->png();

        $this->assertNull($png);
        $this->assertNotSame(file_get_contents(public_path('apple-touch-icon.png')), $png, 'jamás el icono del producto');
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * Cualquier `Throwable` se traga: esta clase la llama el correo de confirmación de un pedido ya
     * cobrado y no puede costar ni un 500 ni un correo sin enviar.
     */
    public function test_any_throwable_becomes_no_icon_plus_one_warning(): void
    {
        Log::spy();

        $logo = $this->fake(['throw' => true]);

        $this->assertNull($logo->png());
        Log::shouldHaveReceived('warning')->once();
    }

    /** El aviso está deduplicado: mil correos no son mil líneas iguales en el log. */
    public function test_the_warning_is_logged_once_per_cause_and_hour(): void
    {
        Log::spy();

        $logo = $this->fake(['svg' => $this->svgFile(), 'imagick' => false, 'rsvg' => false]);

        $logo->png();
        $logo->forget();
        $logo->png();
        $logo->forget();
        $logo->png();

        Log::shouldHaveReceived('warning')->once();
    }

    // ─── Memo y caché ─────────────────────────────────────────────────────────────────────────

    /**
     * ▶ El memo guarda también el `null`. Sin la bandera de «ya lo miré», cada llamada reintentaría
     * un rasterizado que ya se sabe que falla — y hay una llamada por correo.
     */
    public function test_the_memo_remembers_the_null_too(): void
    {
        $logo = $this->fake(['svg' => $this->svgFile(), 'imagick' => true, 'imagickPng' => null, 'rsvg' => false]);

        $this->assertNull($logo->png());
        $this->assertNull($logo->png());
        $this->assertNull($logo->png());
        $this->assertSame(1, $logo->calls['imagick'], 'un solo intento por proceso');
    }

    /**
     * ▶ **La caché va por CONTENIDO del SVG**, no por ruta ni por `mtime`: dos procesos distintos con
     * el mismo icono rasterizan UNA vez. La clave lleva el `sha1` de los bytes, así que sustituir el
     * fichero invalida solo y volver al anterior reaprovecha lo ya hecho.
     */
    public function test_the_cache_is_keyed_by_content_so_a_second_process_does_not_rasterize(): void
    {
        $file = $this->svgFile();
        $svg = file_get_contents($file);

        $first = $this->fake(['svg' => $file, 'imagick' => false, 'rsvg' => true, 'rsvgPng' => $this->pngBytes()]);
        $this->assertSame($this->pngBytes(), $first->png());
        $this->assertSame(1, $first->calls['rsvg']);

        Storage::disk('local')->assertExists('qr-logo/'.sha1($svg).'-256.png');

        // Otro proceso (otra instancia: el memo no le sirve de nada), mismo icono.
        $second = $this->fake(['svg' => $file, 'imagick' => false, 'rsvg' => true, 'rsvgPng' => $this->pngBytes()]);
        $this->assertSame($this->pngBytes(), $second->png());
        $this->assertSame(0, $second->calls['rsvg'], 'la segunda vez sale de la caché');
    }

    /** Y un icono DISTINTO no reaprovecha el anterior: la clave cambia con los bytes. */
    public function test_a_different_icon_gets_a_different_key_and_is_rasterized_again(): void
    {
        $one = $this->svgFile('<circle cx="128" cy="128" r="100" fill="#D56319"/>');
        $two = $this->svgFile('<rect x="28" y="28" width="200" height="200" fill="#0B7285"/>');

        $this->fake(['svg' => $one, 'rsvg' => true, 'rsvgPng' => $this->pngBytes()])->png();
        $second = $this->fake(['svg' => $two, 'rsvg' => true, 'rsvgPng' => $this->pngBytes()]);
        $second->png();

        $this->assertSame(1, $second->calls['rsvg'], 'otro icono, otra rasterización');
        $this->assertCount(2, Storage::disk('local')->files('qr-logo'));
    }

    // ─── El camino de verdad ──────────────────────────────────────────────────────────────────

    /**
     * El rasterizador REAL de esta máquina, sin doblar nada: un SVG de contornos entra y sale un PNG
     * de 256×256. Es el único caso que puede cazar un fallo de la receta (la resolución antes de
     * leer, el formato, el proceso, el tope de tiempo) y por eso existe aunque dependa del entorno.
     *
     * ⚠️ Se salta si esta máquina no tiene NINGUNO de los dos, que es un estado legítimo del sistema
     * (entonces el QR sale liso) y no un fallo de la suite.
     */
    public function test_the_real_rasterizer_of_this_machine_turns_an_svg_into_a_256px_png(): void
    {
        $logo = $this->fake(['svg' => $this->svgFile(), 'real' => true]);

        if (! $logo->canImagick() && $logo->binary() === null) {
            $this->markTestSkipped('esta máquina no tiene ni Imagick con SVG ni rsvg-convert: el QR saldría liso, que es la conducta correcta.');
        }

        $png = $logo->png();

        $this->assertIsString($png);
        $this->assertStringStartsWith("\x89PNG", $png);

        $size = getimagesizefromstring($png);
        $this->assertSame([QrLogo::SIDE, QrLogo::SIDE], [$size[0], $size[1]]);
    }

    // ─── Utillaje ─────────────────────────────────────────────────────────────────────────────

    /**
     * Doble con los eslabones sustituibles y un contador por eslabón: sin el contador, «devuelve el
     * PNG correcto» no distingue «lo hizo Imagick» de «lo hizo rsvg», que es justo lo que este
     * fichero existe para medir.
     */
    private function fake(array $options): QrLogo
    {
        return new class($options) extends QrLogo
        {
            /** @var array{imagick: int, rsvg: int} */
            public array $calls = ['imagick' => 0, 'rsvg' => 0];

            public function __construct(private array $o) {}

            public function canImagick(): bool
            {
                return $this->imagickReadsSvg();
            }

            public function binary(): ?string
            {
                return $this->rsvgBinary();
            }

            protected function clientSvgPath(): string
            {
                if ($this->o['throw'] ?? false) {
                    throw new RuntimeException('el disco dijo que no');
                }

                return $this->o['svg'] ?? '/no/existe.svg';
            }

            protected function productIconPath(): string
            {
                return $this->o['product'] ?? parent::productIconPath();
            }

            protected function imagickReadsSvg(): bool
            {
                return ($this->o['real'] ?? false)
                    ? parent::imagickReadsSvg()
                    : (bool) ($this->o['imagick'] ?? false);
            }

            protected function rasterizeWithImagick(string $svg): ?string
            {
                $this->calls['imagick']++;

                return ($this->o['real'] ?? false)
                    ? parent::rasterizeWithImagick($svg)
                    : ($this->o['imagickPng'] ?? null);
            }

            protected function rsvgBinary(): ?string
            {
                if ($this->o['real'] ?? false) {
                    return parent::rsvgBinary();
                }

                return ($this->o['rsvg'] ?? false) ? '/usr/bin/rsvg-convert' : null;
            }

            protected function rasterizeWithRsvg(string $binary, string $svg): ?string
            {
                $this->calls['rsvg']++;

                return ($this->o['real'] ?? false)
                    ? parent::rasterizeWithRsvg($binary, $svg)
                    : ($this->o['rsvgPng'] ?? null);
            }
        };
    }

    /** Un SVG de CONTORNOS (nunca `<text>`: los dos rasterizadores lo pintan descentrado). */
    private function svgFile(string $shape = '<circle cx="128" cy="128" r="110" fill="#D56319"/>'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'qr-logo-test-');
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256">'.$shape.'</svg>');

        return $path;
    }

    /** Bytes PNG cualesquiera, pero PNG de verdad: la clase comprueba la firma. */
    private function pngBytes(): string
    {
        $im = imagecreatetruecolor(16, 16);
        imagefilledrectangle($im, 0, 0, 15, 15, imagecolorallocate($im, 213, 99, 25));

        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }
}
