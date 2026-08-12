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
}
