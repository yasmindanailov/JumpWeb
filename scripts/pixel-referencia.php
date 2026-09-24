<?php

/**
 * Enrutador de `php -S` para juzgar una HOJA contra la referencia de un sistema de diseño (`#685`).
 *
 * Sirve la referencia tal cual, con UN cambio: en cada página HTML, el enlace a su `styles.css` pasa a
 * apuntar a la hoja que se juzga. Con `scripts/pixel.mjs`, la misma página servida sin este enrutador (A) y
 * con él (B) solo difieren en la hoja; cualquier píxel distinto es de la hoja.
 *
 *   REFERENCIA=/ruta/al/diseno HOJA_DIR=/ruta/a/public/instancia HOJA=css/saltia.css \
 *       php -S 127.0.0.1:8125 -t "$REFERENCIA" scripts/pixel-referencia.php
 *
 * ⚠️ Si una página no trae exactamente UN enlace a `styles.css`, responde 500 y lo dice: una B que se sirve
 * sin la sustitución compara la referencia consigo misma y daría un «0 píxeles» que no significa nada.
 */
$referencia = realpath((string) getenv('REFERENCIA'));
$hojaDir = realpath((string) getenv('HOJA_DIR'));
$hoja = (string) (getenv('HOJA') ?: 'css/saltia.css');

if ($referencia === false || $hojaDir === false) {
    http_response_code(500);
    echo 'faltan REFERENCIA u HOJA_DIR, o no existen';

    return true;
}

$ruta = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

/** Un fichero de dentro de `$base`, o null si la ruta se sale de ella. */
$dentro = static function (string $base, string $rel): ?string {
    $f = realpath($base.'/'.ltrim($rel, '/'));

    return $f !== false && is_file($f) && str_starts_with($f, $base.DIRECTORY_SEPARATOR) ? $f : null;
};

if (str_starts_with($ruta, '/hoja/')) {
    $f = $dentro($hojaDir, substr($ruta, strlen('/hoja/')));
    if ($f === null) {
        http_response_code(404);

        return true;
    }
    $tipos = ['css' => 'text/css; charset=utf-8', 'woff2' => 'font/woff2'];
    header('Content-Type: '.($tipos[pathinfo($f, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($f);

    return true;
}

if (str_ends_with($ruta, '.html')) {
    $f = $dentro($referencia, $ruta);
    if ($f === null) {
        http_response_code(404);

        return true;
    }
    $html = preg_replace('#href="(?:\./|(?:\.\./)*)styles\.css"#', 'href="/hoja/'.$hoja.'"', (string) file_get_contents($f), -1, $n);
    if ($n !== 1) {
        http_response_code(500);
        echo "«{$ruta}» trae {$n} enlaces a styles.css y se esperaba uno: sin la sustitución, B sería A.";

        return true;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo $html;

    return true;
}

// El resto (el compilado, los tokens de la referencia, las fotos) se sirve tal cual desde la raíz.
return false;
