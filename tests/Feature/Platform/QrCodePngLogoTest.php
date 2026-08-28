<?php

namespace Tests\Feature\Platform;

use App\Domain\Platform\Services\Qr\PngWithLogo;
use App\Domain\Platform\Services\QrCode;
use App\Domain\Platform\Services\QrLogo;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as ChillerlanQRCode;
use chillerlan\QRCode\QROptions;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * **El icono de la instalación DENTRO del QR del carné** (`specs/identidad-qr-puerta.md` §9.7 C·3).
 *
 * Aquí se mide lo único que importa de verdad de un carné: **que se siga leyendo**. Por eso los
 * casos no comparan píxeles con una imagen de referencia —eso rompería con cualquier versión de GD—
 * sino que **decodifican el PNG** y comprueban que sale el token.
 *
 * ⚠️ **Y por eso hay una guarda de la guarda**: un decodificador que dijera «lee» pase lo que pase
 * dejaría este fichero en verde para siempre. {@see self::test_the_decoder_used_by_these_guards_can_actually_fail}
 * tapa 11 módulos y exige que el decodificador FALLE.
 *
 * ⚠️⚠️ **El umbral se mide con CARNÉS REALES, no con un token de muestra** — la primera versión de
 * este fichero lo midió con una cadena que ni siquiera era un carné posible y dio un número falso.
 * Medido con 40 tokens de `CardToken::generate()`: **7 módulos tapados, 0 fallos; 9 módulos, 10 de
 * 40**, igual a 264 que a 132 px. Lo fija {@see self::test_the_module_budget_holds_for_real_cards_and_nine_modules_does_not}.
 */
#[Group('platform')]
class QrCodePngLogoTest extends TestCase
{
    /**
     * Un carné REAL, no una cadena de veinte letras.
     *
     * ⚠️⚠️ **Aquí había `ABCDEFGHJKMNPQRSTUVW`, que NO es un carné posible** (2026-08-28, revisión de
     * `#217`): no empieza por `JW`, lleva una `U` —que `CardToken::ALPHABET` excluye a propósito, por
     * confundirse al dictar— y su último carácter no es el control que `CardToken::checksum()` daría.
     * Y no era un detalle cosmético: **ese token concreto era de los que sobreviven** a tapar 9
     * módulos, así que la medición del margen se hizo con el token afortunado y el número que salió
     * —«9 también lee»— resultó falso para uno de cada cuatro carnés de verdad. El de abajo sale de
     * `CardToken::generate()` y tiene su control correcto.
     */
    private const TOKEN = 'JWWBEZGSFCQ5ED9AHSSJ';

    /** Lado del PNG con `scale = 8`: 33 módulos (25 + 4 + 4 de zona de silencio) × 8. */
    private const SIDE_PX = 264;

    // ─── Los bytes ────────────────────────────────────────────────────────────────────────────

    /**
     * ▶ **Sin icono, los bytes son los de SIEMPRE.** No «parecidos»: se reconstruye aquí la llamada
     * literal que hacía `QrCode::png()` antes de existir esta unidad y se exige igualdad byte a byte.
     * Es lo que sostiene que el adjunto del correo ya enviado a un cliente y el que se genere mañana
     * sean el mismo dibujo, y lo que protege a `MeCardTest` de cambiar de significado sin avisar.
     */
    public function test_without_an_icon_the_bytes_are_exactly_the_ones_from_before(): void
    {
        $this->fixLogo(null);

        $legacy = (new ChillerlanQRCode($this->qrOptions()))->render(self::TOKEN);

        $this->assertSame($legacy, QrCode::png(self::TOKEN), 'sin icono no puede cambiar ni un byte');
    }

    /**
     * Con icono cambian los bytes y **no cambia nada más**: mismo lienzo, mismo formato. Un icono que
     * agrandase el PNG rompería la maqueta del correo y la caja del cajón.
     */
    public function test_with_an_icon_the_bytes_change_and_the_canvas_does_not(): void
    {
        $this->fixLogo(null);
        $plain = QrCode::png(self::TOKEN);

        $this->fixLogo($this->sampleIcon());
        $stamped = QrCode::png(self::TOKEN);

        $this->assertNotSame($plain, $stamped, 'el icono tiene que verse en los bytes');
        $this->assertStringStartsWith("\x89PNG", $stamped);
        $this->assertSame([self::SIDE_PX, self::SIDE_PX], $this->sizeOf($stamped));
        $this->assertSame($this->sizeOf($plain), $this->sizeOf($stamped), 'el icono NO agranda el lienzo');
    }

    /**
     * El icono ocupa **7 módulos centrados** y ni uno más: el centro cambia, y el patrón de
     * localización de la esquina —lo primero que busca cualquier lector— queda intacto.
     *
     * Se comprueba con píxeles concretos porque es la única forma de distinguir «se estampó donde
     * toca» de «se estampó en algún sitio»: un icono descentrado también cambiaría los bytes y
     * también leería a veces.
     */
    public function test_the_icon_covers_seven_modules_in_the_middle_and_leaves_the_finder_alone(): void
    {
        $this->fixLogo(null);
        $plain = imagecreatefromstring(QrCode::png(self::TOKEN));

        $this->fixLogo($this->sampleIcon());
        $stamped = imagecreatefromstring(QrCode::png(self::TOKEN));

        $scale = 8;
        $side = PngWithLogo::LOGO_MODULES * $scale;      // 56 px
        $offset = intdiv(self::SIDE_PX - $side, 2);      // 104 px

        $centre = intdiv(self::SIDE_PX, 2);
        $this->assertNotSame(
            imagecolorat($plain, $centre, $centre),
            imagecolorat($stamped, $centre, $centre),
            'el centro lo tapa el icono'
        );

        // Justo FUERA del cuadro del icono (un píxel antes de su borde) nada puede haber cambiado.
        $justOutside = $offset - 1;
        $this->assertSame(
            imagecolorat($plain, $justOutside, $centre),
            imagecolorat($stamped, $justOutside, $centre),
            'el icono no se sale de sus 7 módulos'
        );

        // El patrón de localización superior izquierdo empieza tras la zona de silencio (4 módulos).
        $finder = (4 * $scale) + 4;
        $this->assertSame(
            imagecolorat($plain, $finder, $finder),
            imagecolorat($stamped, $finder, $finder),
            'el patrón de localización queda intacto'
        );
    }

    /**
     * ⚠️⚠️ **Un icono con transparencia se funde con el fondo del cuadro; NUNCA estampa negro.**
     *
     * Sin `imagealphablending(true)` en el destino, un favicon de fondo transparente —que es como se
     * entrega casi cualquier logotipo— dejaría un **cuadrado negro** en mitad del QR: GD copiaría el
     * canal alfa e `imagepng()`, que aquí no lo guarda, lo aplanaría a negro. El fallo saldría solo
     * con el icono de un cliente, nunca con el del producto (opaco), o sea **en producción y en una
     * instalación sola**.
     *
     * ▶ **Este caso cambió de forma al añadir el margen** (2026-08-28): antes se aseveraba que por
     * las zonas transparentes «se veían los módulos», y ahora lo que se ve por ahí es el **anillo de
     * fondo**, que es justo el punto del padding. Lo que se sostiene sigue siendo lo mismo y es lo
     * único que importa: donde el icono no pinta, el resultado es el color de FONDO, no negro.
     */
    public function test_a_transparent_icon_blends_with_the_background_instead_of_stamping_black(): void
    {
        $this->fixLogo($this->transparentIcon());
        $stamped = imagecreatefromstring(QrCode::png(self::TOKEN));

        $background = imagecolorat($stamped, 0, 0);   // zona de silencio: el fondo, por definición
        $box = PngWithLogo::LOGO_MODULES * 8;
        $pad = PngWithLogo::LOGO_PAD_MODULES * 8;
        $offset = intdiv(self::SIDE_PX - $box, 2);
        $centre = intdiv(self::SIDE_PX, 2);

        // ⚠️⚠️ **Se muestrea DENTRO del dibujo, no en el anillo, y esa distinción es el test entero**
        // (corregido el 2026-08-28 por la revisión de `#217`). Al añadir el margen, este punto pasó a
        // caer en el anillo de padding —que es fondo POR CONSTRUCCIÓN, se pinte lo que se pinte
        // encima—, así que el caso seguía verde **incluso con el cuadrado negro estampado**: medía el
        // relleno del propio test. La esquina del ICONO (tras el margen) sí es del icono, y ahí el
        // disco de `transparentIcon()` no llega: es transparente, y por tanto tiene que verse el fondo.
        $this->assertSame(
            $background,
            imagecolorat($stamped, $offset + $pad + 2, $offset + $pad + 2),
            'donde el icono es transparente queda el FONDO, no un cuadro negro'
        );

        $this->assertNotSame(
            $background,
            imagecolorat($stamped, $centre, $centre),
            'y donde sí pinta, se ve el icono'
        );
    }

    /**
     * ⚠️ **EL MARGEN entre el icono y los módulos** (`LOGO_PAD_MODULES`, a petición del owner el
     * 2026-08-28). Se comprueba con píxeles porque es lo único que distingue «hay un anillo de fondo»
     * de «el icono llega hasta el borde»: las dos versiones cambian los bytes y las dos leen.
     *
     * ▶ Y se asevera además lo que el margen NO puede hacer: **el cuadro tapado sigue siendo de 7
     * módulos**. El margen se come de dentro (el icono baja a 5), porque crecer hacia fuera habría
     * dejado 9 módulos tapados — que leen, pero a un solo escalón del precipicio medido.
     */
    public function test_the_icon_keeps_a_ring_of_background_around_it(): void
    {
        // ⚠️ Icono SÓLIDO, no el de los otros casos: aquél es blanco en los bordes (un anillo naranja
        // sobre blanco), así que «hay fondo junto al borde» sería cierto CON margen y SIN él — la
        // aserción no distinguiría nada. Con un cuadrado de color, el único blanco posible es el anillo.
        $this->fixLogo($this->solidIcon());
        $stamped = imagecreatefromstring(QrCode::png(self::TOKEN));

        $background = imagecolorat($stamped, 0, 0);
        $scale = 8;
        $box = PngWithLogo::LOGO_MODULES * $scale;          // 56 px
        $pad = PngWithLogo::LOGO_PAD_MODULES * $scale;      // 8 px
        $offset = intdiv(self::SIDE_PX - $box, 2);
        $centre = intdiv(self::SIDE_PX, 2);

        $this->assertSame(5, PngWithLogo::LOGO_MODULES - (2 * PngWithLogo::LOGO_PAD_MODULES), 'el dibujo son 5 módulos');

        // Dentro del cuadro pero fuera del icono: el anillo, en color de fondo.
        $this->assertSame(
            $background,
            imagecolorat($stamped, $offset + intdiv($pad, 2), $centre),
            'entre el borde del cuadro y el icono tiene que haber fondo'
        );

        // Y el icono empieza justo después del margen.
        $this->assertNotSame(
            $background,
            imagecolorat($stamped, $offset + $pad + 2, $centre),
            'pasado el margen empieza el dibujo'
        );
    }

    // ─── Lo que importa: que se lea ───────────────────────────────────────────────────────────

    /**
     * ▶ **El carné con icono se sigue leyendo**, a tamaño completo y **a la mitad** — que es el caso
     * real: el adjunto del correo abierto en un móvil, o el carné en pantalla a media distancia.
     */
    public function test_the_card_with_the_icon_is_still_decodable_at_full_and_half_size(): void
    {
        $this->fixLogo($this->sampleIcon());
        $png = QrCode::png(self::TOKEN);

        $this->assertSame(self::TOKEN, $this->decode($png), 'a 264 px');
        $this->assertSame(self::TOKEN, $this->decode($this->halve($png)), 'a 132 px');
    }

    /**
     * Y con el icono **REAL del producto** (el `apple-touch-icon.png` que es el suelo cuando la
     * instalación no trae el suyo), que es lo que de hecho va a salir en cada correo: 180 px opacos
     * y a todo color, el peor caso de los tres iconos que se prueban aquí.
     */
    public function test_the_product_icon_that_ships_by_default_also_decodes(): void
    {
        $icon = file_get_contents(public_path('apple-touch-icon.png'));
        $this->assertIsString($icon);

        $this->fixLogo($icon);
        $png = QrCode::png(self::TOKEN);

        $this->assertSame(self::TOKEN, $this->decode($png), 'a 264 px');
        $this->assertSame(self::TOKEN, $this->decode($this->halve($png)), 'a 132 px');
    }

    /**
     * ⚠️⚠️ **EL PRESUPUESTO DE MÓDULOS, MEDIDO CON CARNÉS REALES** (2026-08-28, revisión de `#217`).
     *
     * Este caso nace de un número FALSO que estuvo a punto de gobernar el siguiente cambio. El
     * docblock de `PngWithLogo` afirmaba que tapar **9** módulos «también lee», y de ahí salía la
     * justificación de comerse el margen de dentro. Se había medido con **un solo token**, y encima
     * con uno imposible (ver {@see self::TOKEN}) que resultó ser de los afortunados.
     *
     * ▶ **Medido de verdad, con 40 carnés de `CardToken::generate()`**: con 7 módulos tapados, **0
     * fallos**; con 9, **10 de 40 (25 %)**, igual a 264 px que a 132. O sea: 9 no está «un escalón por
     * debajo del precipicio», **9 ES el precipicio**, y el diseño que envía 7 es el correcto por un
     * motivo distinto —y mejor— del que estaba escrito.
     *
     * Los ocho carnés de abajo son REALES (con su control válido) y salen de esa medición: los ocho
     * leen con 7 y **ninguno** lee con 9. Fijarlos hace el caso determinista —lo aleatorio aquí sería
     * un test que pasa «casi siempre», que es justo lo que este proyecto no admite— y además deja el
     * precipicio ejercitado en las dos direcciones.
     */
    public function test_the_module_budget_holds_for_real_cards_and_nine_modules_does_not(): void
    {
        $reales = [
            'JWWBEZGSFCQ5ED9AHSSJ', 'JW3BAJPNSAM4TAM3WM2H', 'JW2MDPBFX2MRAEEGMBHM', 'JWJ9SBWS8PXV7Z2RC9DE',
            'JWEAKF2MB0MJ2T0QW019', 'JWFHYVFBFV079S16P9EK', 'JWEVQAJEB17WF5N5ZKRD', 'JW2GB015M05NQX5QZ887',
        ];

        $ciegosCon7 = [];
        $leenCon9 = [];

        foreach ($reales as $token) {
            $png = (new ChillerlanQRCode($this->qrOptions()))->render($token);

            $con7 = $this->stampSquare($png, PngWithLogo::LOGO_MODULES);
            if ($this->decode($con7) !== $token || $this->decode($this->halve($con7)) !== $token) {
                $ciegosCon7[] = $token;
            }

            $con9 = $this->stampSquare($png, 9);
            if ($this->decode($con9) === $token) {
                $leenCon9[] = $token;
            }
        }

        $this->assertSame(
            [], $ciegosCon7,
            "Con los 7 módulos que se envían hay carnés que YA NO SE LEEN:\n  ".implode("\n  ", $ciegosCon7)."\n\n".
            '⚠️ Es el presupuesto del icono: si esto cae, el carné de un cliente real no escanea en la puerta.'
        );

        $this->assertSame(
            [], $leenCon9,
            "Estos carnés SÍ leen con 9 módulos tapados, y el conjunto se eligió porque no debían:\n  ".
            implode("\n  ", $leenCon9)."\n\n".
            '⚠️ Si el precipicio se ha movido (otra versión de la librería, otro GD), vuelve a medirlo '.
            'con 40 carnés generados ANTES de tocar `LOGO_MODULES` o `LOGO_PAD_MODULES`.'
        );
    }

    /**
     * ⚠️⚠️ **La guarda de la guarda.** Los dos casos de arriba solo valen si el decodificador sabe
     * decir que NO. Se tapan 11 módulos —el primer tamaño que los dos decodificadores medidos
     * rechazan— y se exige el fallo en los dos tamaños. Si este caso se pusiera verde, los otros dos
     * dejarían de significar nada.
     */
    public function test_the_decoder_used_by_these_guards_can_actually_fail(): void
    {
        $this->fixLogo(null);
        $blinded = $this->stampSquare(QrCode::png(self::TOKEN), 11);

        $this->assertNull($this->decode($blinded), 'con 11 módulos tapados NO se lee a 264 px');
        $this->assertNull($this->decode($this->halve($blinded)), 'ni a 132 px');
    }

    /**
     * El mismo dibujo, leído por un decodificador **de otra estirpe** (jsQR, en Node): el de la
     * librería comparte código con el que escribe la matriz, así que por sí solo no descarta un
     * error simétrico.
     *
     * ⚠️ Se salta —diciendo por qué— si jsQR no está a mano: en este contenedor vive en
     * `/root/e2e/node_modules`, y `/root` es `drwx------`, así que **el usuario `sail` que corre la
     * suite no puede entrar**. Medido a mano como `root`, jsQR coincide exactamente con el
     * decodificador de PHP (lee con 7 y con 9 módulos tapados a 264 y a 132 px; no lee con 11).
     */
    public function test_an_independent_decoder_recovers_the_token(): void
    {
        $modules = $this->jsqrModulesPath();

        if ($modules === null) {
            $this->markTestSkipped('jsQR no es legible por el usuario de la suite: vive en /root/e2e/node_modules y /root es drwx------. Medido A MANO como root el 2026-08-28: jsQR coincide con el decodificador de PHP (lee con 7 y 9 módulos tapados a 264 y 132 px; no lee con 11). Para que este caso corra: copiar jsqr y pngjs a una carpeta legible y apuntar QR_JSQR_MODULES a ella.');
        }

        $this->fixLogo($this->sampleIcon());
        $png = QrCode::png(self::TOKEN);

        $this->assertSame(self::TOKEN, $this->decodeWithJsqr($modules, $png), 'jsQR a 264 px');
        $this->assertSame(self::TOKEN, $this->decodeWithJsqr($modules, $this->halve($png)), 'jsQR a 132 px');
    }

    /**
     * ▶ **Sin doblar NADA**: el resolvedor de verdad, la instalación tal cual está en el repo. Es lo
     * que va a salir en el correo de confirmación de mañana — el icono del producto, porque este
     * repo no trae `client-favicon.svg`— y lo que hace que este fichero no sea un teatro de dobles:
     * el cableado `QrCode::png()` → `QrLogo` → `PngWithLogo` se recorre entero.
     */
    public function test_the_real_resolver_stamps_an_icon_and_the_card_still_reads(): void
    {
        $real = QrCode::png(self::TOKEN);

        $this->fixLogo(null);
        $plain = QrCode::png(self::TOKEN);

        $this->assertNotSame($plain, $real, 'por defecto el QR SÍ lleva icono (el del producto)');
        $this->assertSame(self::TOKEN, $this->decode($real), 'y se lee');
        $this->assertSame(self::TOKEN, $this->decode($this->halve($real)), 'también a 132 px');
    }

    // ─── La trampa del `render()` replicado ───────────────────────────────────────────────────

    /**
     * ⚠️ **`getQRMatrix()` sin `addSegment()` NO falla: codifica la cadena VACÍA** y devuelve un QR
     * perfectamente válido que no dice nada. Es la trampa que hace peligroso replicar `render()`, y
     * el motivo de que `PngWithLogo::render()` lleve una guarda explícita.
     *
     * Se comprueba de las dos maneras: que la trampa EXISTE (la librería, sin guarda, da un QR que
     * decodifica a la cadena vacía) y que nuestra guarda la corta.
     */
    public function test_a_qr_with_no_data_segment_throws_instead_of_encoding_the_empty_string(): void
    {
        // La trampa, medida sobre la librería tal cual: sin segmento no revienta, dibuja el vacío.
        $matrix = (new ChillerlanQRCode($this->qrOptions()))->getQRMatrix();
        $empty = (new PngWithLogo($this->qrOptions(), $matrix))->dump(null);
        $this->assertSame('', $this->decode($empty), 'la librería SÍ dibuja un QR de la cadena vacía');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cadena VAC/');

        PngWithLogo::render($this->qrOptions(), '', $this->sampleIcon());
    }

    /**
     * `PngWithLogo` **sin icono es su padre**: mismo camino, mismos bytes. Es la propiedad que hace
     * que meter la clase en medio no pueda cambiar nada cuando no hay nada que estampar.
     */
    public function test_the_subclass_without_an_icon_is_byte_identical_to_its_parent(): void
    {
        $qr = new ChillerlanQRCode($this->qrOptions());
        $qr->addAlphaNumSegment(self::TOKEN);
        $matrix = $qr->getQRMatrix();

        $this->assertSame(
            (new ChillerlanQRCode($this->qrOptions()))->render(self::TOKEN),
            (new PngWithLogo($this->qrOptions(), $matrix))->dump(null),
        );
    }

    // ─── Utillaje ─────────────────────────────────────────────────────────────────────────────

    /** Sustituye el resolvedor de icono por uno que siempre contesta lo mismo. */
    private function fixLogo(?string $png): void
    {
        $this->app->instance(QrLogo::class, new class($png) extends QrLogo
        {
            public function __construct(private ?string $fixed) {}

            public function png(): ?string
            {
                return $this->fixed;
            }
        });
    }

    /** Las MISMAS opciones que `QrCode::png()`: si divergen, la comparación de bytes no dice nada. */
    private function qrOptions(): QROptions
    {
        return new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::H,
            'quietzoneSize' => 4,
            'scale' => 8,
            'imageTransparent' => false,
        ]);
    }

    /**
     * Icono de prueba **determinista** (se dibuja, no se lee de disco): fondo blanco con un anillo
     * de color, que es la forma de un favicon real. No se usa el del producto para que el caso no
     * cambie de significado el día que se rebrandee el producto.
     */
    private function sampleIcon(): string
    {
        $im = imagecreatetruecolor(180, 180);
        imagefilledrectangle($im, 0, 0, 179, 179, imagecolorallocate($im, 255, 255, 255));
        imagefilledellipse($im, 90, 90, 130, 130, imagecolorallocate($im, 213, 99, 25));
        imagefilledellipse($im, 90, 90, 60, 60, imagecolorallocate($im, 255, 255, 255));

        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /**
     * Icono SÓLIDO de lado a lado. Solo lo usa el caso del margen, y por eso existe: con un icono que
     * tenga blanco en sus bordes, «junto al borde del cuadro hay fondo» se cumple aunque no haya
     * ningún margen, y el caso pasaría en verde sin medir nada.
     */
    private function solidIcon(): string
    {
        $im = imagecreatetruecolor(180, 180);
        imagefilledrectangle($im, 0, 0, 179, 179, imagecolorallocate($im, 213, 99, 25));

        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /** Icono con ALFA: fondo transparente y un disco de color, la forma de un logotipo entregado. */
    private function transparentIcon(): string
    {
        $im = imagecreatetruecolor(180, 180);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        imagefilledrectangle($im, 0, 0, 179, 179, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
        imagefilledellipse($im, 90, 90, 120, 120, imagecolorallocate($im, 213, 99, 25));

        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /** Tapa `$modules` módulos en el centro con un cuadro macizo: la mutación de la guarda. */
    private function stampSquare(string $png, int $modules): string
    {
        $im = imagecreatefromstring($png);
        $side = $modules * 8;
        $offset = intdiv(self::SIDE_PX - $side, 2);
        imagefilledrectangle($im, $offset, $offset, $offset + $side, $offset + $side, imagecolorallocate($im, 213, 99, 25));

        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /** @return array{0: int, 1: int} */
    private function sizeOf(string $png): array
    {
        $size = getimagesizefromstring($png);

        return [(int) $size[0], (int) $size[1]];
    }

    /** El mismo PNG a la mitad de lado (132 px): el carné visto en un móvil. */
    private function halve(string $png): string
    {
        $half = imagescale(imagecreatefromstring($png), intdiv(self::SIDE_PX, 2), intdiv(self::SIDE_PX, 2));

        ob_start();
        imagepng($half);

        return (string) ob_get_clean();
    }

    /** Decodifica con el lector de la librería (puerto de ZXing). `null` = no se pudo leer. */
    private function decode(string $png): ?string
    {
        try {
            return (string) (new ChillerlanQRCode($this->qrOptions()))->readFromBlob($png);
        } catch (Throwable) {
            return null;
        }
    }

    /** Carpeta de módulos de Node donde vive jsQR, si la suite puede leerla. */
    private function jsqrModulesPath(): ?string
    {
        $candidates = array_filter([
            env('QR_JSQR_MODULES'),
            '/root/e2e/node_modules',
            base_path('node_modules'),
        ]);

        foreach ($candidates as $dir) {
            if (@is_readable($dir.'/jsqr/package.json') && @is_readable($dir.'/pngjs/package.json')) {
                return $dir;
            }
        }

        return null;
    }

    private function decodeWithJsqr(string $modules, string $png): ?string
    {
        $image = tempnam(sys_get_temp_dir(), 'qr-') ?: null;
        $script = tempnam(sys_get_temp_dir(), 'qr-js-') ?: null;

        if ($image === null || $script === null) {
            return null;
        }

        file_put_contents($image, $png);
        file_put_contents($script, <<<JS
            const jsQR = require({$this->jsLiteral($modules.'/jsqr')}).default || require({$this->jsLiteral($modules.'/jsqr')});
            const { PNG } = require({$this->jsLiteral($modules.'/pngjs')});
            const png = PNG.sync.read(require('fs').readFileSync(process.argv[2]));
            const r = jsQR(new Uint8ClampedArray(png.data), png.width, png.height);
            process.stdout.write(r ? r.data : '');
            JS);

        $pipes = [];
        $process = proc_open(['node', $script, $image], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        @unlink($image);
        @unlink($script);

        $this->assertSame(0, $code, 'jsQR no llegó a correr: '.$err);

        return $out === '' ? null : $out;
    }

    private function jsLiteral(string $value): string
    {
        return (string) json_encode($value);
    }
}
