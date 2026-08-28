<?php

namespace App\Domain\Platform\Services\Qr;

use App\Domain\Platform\Services\QrLogo;
use chillerlan\QRCode\Common\Mode;
use chillerlan\QRCode\Data\QRDataModeInterface;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode as ChillerlanQRCode;
use chillerlan\Settings\SettingsContainerInterface;
use RuntimeException;

/**
 * Fase 6 · subsistema A — **el icono de la instalación DENTRO del QR** (`specs/identidad-qr-puerta.md`
 * §9.7 C·3).
 *
 * ⚠️ **La librería (`chillerlan/php-qrcode` 5.0.5) NO sabe dibujar logos.** Lo único que ofrece es
 * `addLogoSpace`, que **borra módulos de la matriz** para dejar hueco: eso EMPEORA la lectura (gasta
 * corrección de errores para nada) y además no pinta nada. Por eso el icono se superpone DESPUÉS,
 * sobre el lienzo ya pintado, sin tocar la matriz: los módulos tapados los recupera la corrección de
 * errores `H` (30 %), que es para lo que está.
 *
 * ▶ **El tamaño se expresa en MÓDULOS, no en píxeles** ({@see self::LOGO_MODULES}). Un icono de
 * «56 px» significa cosas distintas según la escala; «7 módulos» tapa siempre la misma fracción del
 * dato, que es lo que decide si el QR se lee. Medido con **dos decodificadores independientes** —el
 * lector de la propia librería (puerto de ZXing) y **jsQR** en Node— sobre el token de 20 caracteres
 * (versión 2, 33 módulos con zona de silencio), a **264 px y a 132 px** (la mitad: un adjunto de
 * correo abierto en un móvil):
 *
 * ⚠️⚠️ **La primera versión de esta tabla decía que 9 «también lee», y era FALSO** (corregido el
 * 2026-08-28 por la revisión de `#217`): se midió con **un solo token**, y encima con una cadena que
 * no era un carné posible. Re-medido con **40 carnés de `CardToken::generate()`**:
 *
 * | módulos tapados | 264 px | 132 px |
 * |---|---|---|
 * | 7 (**el elegido**, 21 % del lado) | 0 fallos de 40 | 0 de 40 |
 * | 9 | **10 fallos de 40** | **10 de 40** |
 * | 11 | no lee | no lee |
 *
 * O sea: **9 no está por debajo del precipicio, 9 ES el precipicio** —uno de cada cuatro carnés deja
 * de escanearse— y 7 es el último valor limpio. `QrCodePngLogoTest` lo fija con ocho carnés reales
 * que leen con 7 y NO con 9, y con su guarda de la guarda: con 11 el decodificador tiene que FALLAR,
 * porque uno que dijera «lee» pase lo que pase dejaría este fichero verde para siempre.
 *
 * ▶ **Y el suelo cuando un QR no lee es TECLEAR**: la puerta acepta los 20 caracteres por el mismo
 * campo (§4.6) y el alfabeto excluye las parejas que se confunden al dictar. Un fallo de lectura es
 * una molestia, no una puerta cerrada — pero no es excusa para gastarse el margen.
 * **deja de leerse** —si el decodificador del test no supiera fallar, el caso verde no valdría nada—.
 */
class PngWithLogo extends QRGdImagePNG
{
    /**
     * Lado del CUADRO que ocupa el icono, en MÓDULOS de la matriz (ver la tabla del docblock).
     *
     * ⚠️ Es **lo que se tapa**, no el tamaño del dibujo: dentro van {@see self::LOGO_PAD_MODULES} de
     * margen por cada lado y el icono en el hueco. Se expresa así a propósito, porque lo que decide
     * si el QR se lee es la superficie de dato cubierta, no lo grande que se vea el logotipo.
     *
     * A escala 8 son 56 px sobre 264 (21 % del lado, 4,5 % del área). Impar a propósito: con un
     * número par el icono no puede quedar centrado sobre la retícula y muerde media fila de más.
     */
    public const LOGO_MODULES = 7;

    /**
     * **El MARGEN entre el icono y los módulos**, en módulos, por cada lado (2026-08-28, a petición
     * del owner: «falta un poco de padding entre el icono de la web y el QR»).
     *
     * ⚠️⚠️ **El margen se come de DENTRO, no crece hacia fuera**, y esa es la decisión: el cuadro
     * tapado sigue siendo de 7 módulos —**el último valor que lee limpio**: 0 fallos sobre 40 carnés
     * reales, mientras que 9 falla 10 de 40— y lo
     * que encoge es el dibujo, de 7 a **5 módulos** (40 px sobre 264, el 15 % del lado: la proporción
     * que recomiendan los lectores para un logo). Hacerlo al revés —icono de 7 más un anillo— habría
     * tapado 9, y eso no es «un escalón menos de margen»: es **un carné de cada cuatro que no
     * escanea**, en un parque que además los imprime.
     *
     * ▶ Y el anillo se rellena con el color de FONDO leído del propio lienzo (la zona de silencio),
     * no con blanco quemado: así un tema con el QR sobre otro color sigue cuadrando.
     */
    public const LOGO_PAD_MODULES = 1;

    /** Bytes PNG del icono, o `null` = esta clase se comporta EXACTAMENTE como su padre. */
    private ?string $logoPng;

    public function __construct(SettingsContainerInterface $options, QRMatrix $matrix, ?string $logoPng = null)
    {
        parent::__construct($options, $matrix);

        $this->logoPng = $logoPng;
    }

    /**
     * Pinta el QR de `$data` con `$logoPng` encima y devuelve los bytes PNG.
     *
     * ⚠️⚠️ **Réplica deliberada de `ChillerlanQRCode::render()`**, y no por gusto: `render()` elige
     * él mismo la clase de salida a partir de `outputType`, así que no hay forma de que instancie una
     * subclase nuestra sin pasar por `outputType = CUSTOM` —que cambiaría de camino el `renderImage()`
     * y el `setTransparencyColor()` del padre—. Se replican las tres líneas que hacen falta y las
     * opciones se quedan **idénticas a las de hoy**: así los bytes sin icono son los de siempre.
     *
     * ⚠️ **La trampa medida está en el `foreach`**: `getQRMatrix()` sin ningún `addSegment()` previo
     * NO falla — codifica **la cadena vacía** y devuelve un QR perfectamente válido que no dice nada.
     * Un carné así se imprimiría, se enviaría por correo y solo se descubriría en la puerta. De ahí
     * la guarda: si ningún modo acepta el dato, esto **lanza**.
     */
    public static function render(SettingsContainerInterface $options, string $data, string $logoPng): string
    {
        $qr = new ChillerlanQRCode($options);
        $mode = null;

        /** @var class-string<QRDataModeInterface> $dataInterface */
        foreach (Mode::INTERFACES as $dataInterface) {
            if ($dataInterface::validateString($data)) {
                $qr->addSegment(new $dataInterface($data));
                $mode = $dataInterface;

                break;
            }
        }

        if ($mode === null) {
            throw new RuntimeException('QR sin segmento de datos: la matriz codificaría la cadena VACÍA.');
        }

        return (new self($options, $qr->getQRMatrix(), $logoPng))->dump(null);
    }

    /**
     * ▶ **Sin icono no hay ninguna diferencia con el padre**, y eso es una garantía POR
     * CONSTRUCCIÓN, no por parecido: se llama a `parent::dump()` tal cual. Es lo que sostiene que
     * el adjunto del correo y `GET /me/card.png` sigan siendo byte a byte lo mismo.
     *
     * Con icono, el truco es `returnResource`: el padre pinta el QR entero y, en vez de serializar,
     * devuelve el lienzo GD. Se estampa el icono encima y se serializa aquí — con la MISMA cola que
     * el padre (`dumpImage()` + `saveToFile()` + base64 opcional) para no perder ninguna opción.
     */
    public function dump(?string $file = null): string
    {
        if ($this->logoPng === null) {
            $this->options->returnResource = false;

            return parent::dump($file);
        }

        $this->options->returnResource = true;

        try {
            parent::dump(null);
        } finally {
            // Se restaura pase lo que pase: el contenedor de opciones puede ser compartido.
            $this->options->returnResource = false;
        }

        $this->stampLogo();

        $imageData = $this->dumpImage();

        $this->saveToFile($imageData, $file);

        if ($this->options->outputBase64) {
            $imageData = $this->toBase64DataURI($imageData, 'image/'.$this->options->outputType);
        }

        return $imageData;
    }

    /**
     * Estampa el icono centrado sobre el lienzo ya pintado.
     *
     * `imagealphablending(true)` en el DESTINO es lo que hace que un icono con transparencia deje
     * ver los módulos por debajo en vez de recortar un cuadrado opaco: un icono medio transparente
     * degrada suave (tapa menos dato), que es la dirección correcta del fallo.
     *
     * ⚠️ **Si los bytes no son una imagen que GD entienda, el QR sale LISO y no pasa nada más.**
     * Esta clase se usa para un correo de confirmación y para la descarga del carné: reventar aquí
     * dejaría al cliente sin correo por un icono mal subido. Quien decide qué icono hay —y quien
     * deja constancia en el log cuando algo no cuadra— es {@see QrLogo}.
     */
    private function stampLogo(): void
    {
        $box = self::LOGO_MODULES * $this->scale;
        $pad = self::LOGO_PAD_MODULES * $this->scale;
        $side = $box - (2 * $pad);

        // Un icono que no cabe (escalas absurdas) taparía el QR entero: mejor liso.
        if ($side <= 0 || $box >= $this->length) {
            return;
        }

        $logo = @imagecreatefromstring((string) $this->logoPng);

        if ($logo === false) {
            return;
        }

        // `length` incluye la zona de silencio, y la matriz está centrada en ella: el centro del
        // lienzo ES el centro del QR. Con 33 módulos y 7 de cuadro el offset cae exacto (104 px).
        $offset = intdiv($this->length - $box, 2);

        // ⚠️ El MARGEN: se rellena el cuadro entero con el color de fondo del propio lienzo antes de
        // estampar. Se lee del píxel (0,0) —zona de silencio, que por definición es fondo— en vez de
        // quemar blanco: si algún día el QR se pinta sobre otro color, el anillo lo sigue solo.
        // `alphablending` a `false` para ESCRIBIR el color tal cual (con `true` se mezclaría con lo
        // que hay debajo y el anillo saldría grisáceo sobre los módulos oscuros).
        imagealphablending($this->image, false);
        imagefilledrectangle($this->image, $offset, $offset, $offset + $box - 1, $offset + $box - 1, imagecolorat($this->image, 0, 0));

        // Y a `true` para ESTAMPAR: un icono con transparencia se funde con el anillo de fondo en vez
        // de recortar un cuadrado negro (GD copiaría el canal alfa e `imagepng()` lo aplanaría).
        imagealphablending($this->image, true);
        imagecopyresampled($this->image, $logo, $offset + $pad, $offset + $pad, 0, 0, $side, $side, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
    }
}
