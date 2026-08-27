<?php

namespace App\Domain\Platform\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as ChillerlanQRCode;
use chillerlan\QRCode\QROptions;

/**
 * Generación de códigos QR como SVG inline (server-side), sin JS ni imagen binaria.
 *
 * Usa `chillerlan/php-qrcode` (v5, ya instalado como dependencia de Filament). Salida SVG =
 * puro markup (sin GD/imagick): escala sin pérdida, pesa poco, se incrusta en el Blade y
 * funciona SIN JavaScript (mejora progresiva de la landing).
 *
 * El SVG son solo trazados geométricos: el dato (URL) NO aparece como texto en el markup →
 * incrustarlo con `{!! !!}` es seguro (no hay vector de inyección por el contenido del QR).
 * El COLOR no se fija en el SVG (`svgUseFillAttributes=false`) → lo pone el CSS, en un único
 * sitio, con contraste FIJO oscuro-sobre-claro (fiabilidad de escaneo, no se tematiza).
 */
class QrCode
{
    /**
     * QR de `$data` como `<svg>` inline. `eccLevel=M` (equilibrio densidad/tolerancia, típico de
     * URLs); sin zona de silencio en el SVG (el marco de la card ya da el margen); trazados unidos
     * (SVG compacto); módulos claros NO dibujados (fondo transparente → deja ver el papel del marco).
     */
    public static function svg(string $data): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,       // SVG inline (no data-URI) → el CSS controla el color
            'eccLevel' => EccLevel::M,
            'quietzoneSize' => 0,
            'connectPaths' => true,
            'drawLightModules' => false,
            'svgUseFillAttributes' => false,
            'svgAddXmlHeader' => false,
            'cssClass' => 'qr-svg',
        ]);

        return (new ChillerlanQRCode($options))->render($data);
    }

    /**
     * Fase 6 · subsistema A — el perfil del CARNÉ (`specs/identidad-qr-puerta.md` §4.3, §4.10): PNG
     * binario para adjuntarlo a un correo (los clientes de correo no renderizan SVG inline), corrección
     * MÁXIMA (H: un carné rayado, una pantalla sucia, poca luz) y **zona de silencio de 4 módulos** —
     * `svg()` la pone a 0 porque el marco de la tarjeta hace de margen, y eso vale para un adorno que se
     * escanea con el móvil, no para un lector de mostrador—. El dato es el token pelado (§3·C), que
     * entra en modo alfanumérico: 20 caracteres caben en versión 2 (25×25), medido.
     *
     * Necesita GD (presente en el contenedor; staging sin inventariar, §4.10).
     */
    public static function png(string $data, int $scale = 8): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::H,
            'quietzoneSize' => 4,
            'scale' => $scale,
            'imageTransparent' => false,
        ]);

        return (new ChillerlanQRCode($options))->render($data);
    }
}
