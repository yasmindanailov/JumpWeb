<?php

/**
 * Construye el ARTEFACTO de la forma B (`docs/specs/guion-de-la-portada.md` §6.7, `DECISIONES #432`)
 * a partir de `index.src.html`: inyecta las fotos reales de la instalación como `data:` (recortadas
 * y comprimidas con GD), y el kit de fachada y el logotipo del cliente en línea.
 *
 * Instrumento del carril de diseño (`DECISIONES #433`). Se corre DENTRO del contenedor porque las
 * fotos y GD están ahí, y deja la copia SERVIDA (gitignorada) en `storage/app/public/prototipo-b/`,
 * que la web local sirve en `http://localhost:8081/storage/prototipo-b/index.html`:
 *   docker compose exec -T -u sail laravel.test php /var/www/html/scripts/prototipo-b/build.php
 * El artefacto publicado es esa misma salida (`Artifact` sobre una copia del fichero).
 *
 * Marcadores admitidos en el fuente:
 *   {{img:<ruta relativa a public/>|<ancho>x<alto>|<calidad>}}   recorte centrado a esa proporción
 *   {{img:<ruta>|<ancho>|<calidad>}}                              solo reescala al ancho
 *   {{svg:<ruta relativa a public/>}}                             contenido del fichero tal cual
 *   {{svgbody:<ruta>}}                                            el interior del <svg> (sin la raíz)
 */
$root = '/var/www/html';
$src = __DIR__.'/index.src.html';
$outServed = $root.'/storage/app/public/prototipo-b/index.html';

$html = file_get_contents($src);
if ($html === false) {
    fwrite(STDERR, "No se puede leer $src\n");
    exit(1);
}

$stats = [];

$img = function (string $rel, string $size, int $q) use ($root, &$stats): string {
    $path = $root.'/public/'.$rel;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $im = match ($ext) {
        'webp' => imagecreatefromwebp($path),
        'jpg', 'jpeg' => imagecreatefromjpeg($path),
        'png' => imagecreatefrompng($path),
        default => false,
    };
    if ($im === false) {
        fwrite(STDERR, "No se puede abrir $path\n");
        exit(1);
    }
    $sw = imagesx($im);
    $sh = imagesy($im);

    if (str_contains($size, 'x')) {
        [$tw, $th] = array_map('intval', explode('x', $size));
        // Recorte CENTRADO a la proporción pedida, después reescalado.
        $ratio = $tw / $th;
        if ($sw / $sh > $ratio) {
            $cw = (int) round($sh * $ratio);
            $ch = $sh;
        } else {
            $cw = $sw;
            $ch = (int) round($sw / $ratio);
        }
        $cx = (int) (($sw - $cw) / 2);
        $cy = (int) (($sh - $ch) / 2);
        $dst = imagecreatetruecolor($tw, $th);
        imagecopyresampled($dst, $im, 0, 0, $cx, $cy, $tw, $th, $cw, $ch);
    } else {
        $tw = (int) $size;
        $th = (int) round($sh * $tw / $sw);
        $dst = imagecreatetruecolor($tw, $th);
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $tw, $th, $sw, $sh);
    }
    ob_start();
    imagewebp($dst, null, $q);
    $bin = ob_get_clean();
    imagedestroy($im);
    imagedestroy($dst);
    $stats[] = sprintf('%-40s %4dx%-4d %5.0f KB', $rel, $tw, $th, strlen($bin) / 1024);

    return 'data:image/webp;base64,'.base64_encode($bin);
};

$html = preg_replace_callback('/\{\{img:([^|}]+)\|([^|}]+)\|(\d+)\}\}/', fn ($m) => $img(trim($m[1]), trim($m[2]), (int) $m[3]), $html);

$html = preg_replace_callback('/\{\{svg:([^}]+)\}\}/', function ($m) use ($root, &$stats) {
    $path = $root.'/public/'.trim($m[1]);
    $s = file_get_contents($path);
    if ($s === false) {
        fwrite(STDERR, "No se puede leer $path\n");
        exit(1);
    }
    $s = preg_replace('/<\?xml[^>]*\?>\s*/', '', $s);
    $stats[] = sprintf('%-40s %14s %5.0f KB', trim($m[1]), 'svg', strlen($s) / 1024);

    return $s;
}, $html);

$html = preg_replace_callback('/\{\{svgbody:([^}]+)\}\}/', function ($m) use ($root, &$stats) {
    $path = $root.'/public/'.trim($m[1]);
    $s = file_get_contents($path);
    if ($s === false) {
        fwrite(STDERR, "No se puede leer $path\n");
        exit(1);
    }
    $s = preg_replace('/<\?xml[^>]*\?>\s*/', '', $s);
    $s = preg_replace('/^\s*<svg[^>]*>/', '', $s);
    $s = preg_replace('/<\/svg>\s*$/', '', $s);
    $stats[] = sprintf('%-40s %14s %5.0f KB', trim($m[1]), 'svg body', strlen($s) / 1024);

    return $s;
}, $html);

if (preg_match('/\{\{(img|svg|svgbody):/', $html)) {
    fwrite(STDERR, "Quedan marcadores sin resolver\n");
    exit(1);
}

@mkdir(dirname($outServed), 0775, true);
file_put_contents($outServed, $html);
echo implode("\n", $stats), "\n";
printf("→ %s (%.0f KB)\n", $outServed, strlen($html) / 1024);
