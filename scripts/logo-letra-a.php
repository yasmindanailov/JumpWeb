<?php

/**
 * Restituye una LETRA de un logotipo exportado con la silueta RESTADA del trazado (`#275`).
 *
 * ══ EL DEFECTO ══════════════════════════════════════════════════════════════════════════════════
 *
 * En el lockup del mockup, la palabra es TEXTO y la silueta es una `<img>` **encima**: la letra
 * está entera y el dibujo la tapa. En la exportación a SVG, en cambio, **la silueta se restó del
 * trazado de la palabra**, así que la letra viene mordida siguiendo el contorno del dibujo.
 *
 * ▶ En reposo no se nota, porque la silueta ocupa exactamente el hueco. **Se ve en la ANIMACIÓN**:
 * `brand-hop` arranca en `opacity: 0` y hace volar la silueta, así que durante la espera y durante
 * todo el vuelo el logotipo enseña una letra mutilada. Lo cazó el ojo del owner: *«se queda el
 * contorno en la A de la silueta, no se va la A perfecta, original»*.
 * ⚠️ Medido en el segundo cliente: al trazado le faltaba el **16,8 %** del área de la A.
 *
 * ══ QUÉ HACE ════════════════════════════════════════════════════════════════════════════════════
 *
 * Sustituye los subtrazados de UNA letra de `#u1` por la geometría que se le pasa, **en las DOS
 * apariciones que tiene**:
 *   1. dentro de `#u1`, que alimenta el contorno de color, la tinta y el blanco vía `<use>`;
 *   2. en el `<path>` del RELLENO de color de esa letra, que es una copia literal de esos mismos
 *      subtrazados.
 * ⚠️⚠️ **Arreglar solo la primera deja la parte restituida en BLANCO**, porque el relleno no llega
 * hasta ahí. Se descubrió midiendo, después de dar el arreglo por bueno.
 *
 * ══ DE DÓNDE SALE LA GEOMETRÍA, Y POR QUÉ NO VIVE AQUÍ ══════════════════════════════════════════
 *
 * **La letra es del paquete del cliente, no del producto** (`DECISIONES #1`): igual que
 * `client-logo.svg` o `client.css`, se pasa en un fichero suyo —por defecto
 * `public/img/client-logo-a.path`— que no se versiona y que `deploy.sh` excluye del `--delete`.
 * Las mismas tres piezas del patrón de marca.
 *
 * ▶ **Cómo se deriva** (queda escrito en `docs/specs/tema-por-instalacion.md` §27 para poder
 * rehacerlo): se toma la letra de la MISMA fuente con la que el cliente compone su lockup y se
 * coloca con la transformación afín recuperada del PROPIO trazado, usando las letras que están
 * intactas (momentos de área: centroides y covarianzas). La afín mapea Béziers a Béziers de forma
 * exacta, así que la letra no se aplana: se transforman sus puntos de control.
 * ⚠️ **Con su control, que es lo que lo hace fiable**: en el segundo cliente la consistencia del
 * ajuste salió a **0,001 %** y la letra que NO se usó para ajustar cayó encima con 0,52 % de área
 * y 0,31 unidades de caja — 0,04 px a la talla de la web.
 *
 * ⚠️ Es IDEMPOTENTE: si la letra ya coincide con la geometría dada, no hace nada.
 * ⚠️ Se pasa DESPUÉS de `logo-sombra.php`; no se pisan (uno toca `<use>`, el otro `<path>`).
 *
 * Uso:  php scripts/logo-letra-a.php [svg] [fichero-con-la-letra] [índice del 1.er subtrazado]
 *       php scripts/logo-letra-a.php public/img/client-logo.svg public/img/client-logo-a.path 3
 */
$ruta = $argv[1] ?? 'public/img/client-logo.svg';
$fichero = $argv[2] ?? 'public/img/client-logo-a.path';
$desde = (int) ($argv[3] ?? 3);

foreach ([$ruta, $fichero] as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "no existe: {$f}\n");
        exit(1);
    }
}

$letra = trim((string) file_get_contents($fichero));

if (! str_starts_with($letra, 'M')) {
    fwrite(STDERR, "ABORTADO: «{$fichero}» no contiene un trazado SVG (debe empezar por «M»).\n");
    exit(1);
}

$svg = (string) file_get_contents($ruta);

if (! preg_match('/<path id="u1" d="([^"]+)"/', $svg, $m)) {
    fwrite(STDERR, "ABORTADO: no encuentro el trazado de la palabra (#u1).\n");
    exit(1);
}

$subtrazados = preg_match_all('/M[^M]*/', $m[1], $mm) ? $mm[0] : [];

if ($desde < 1 || $desde >= count($subtrazados)) {
    fwrite(STDERR, "ABORTADO: el subtrazado {$desde} no existe (#u1 tiene ".count($subtrazados).").\n");
    exit(1);
}

$actual = implode('', array_slice($subtrazados, $desde));

if ($actual === $letra) {
    echo "nada que hacer: la letra ya está entera (el guion es idempotente).\n";
    exit(0);
}

// ⚠️ El relleno de color de la letra es una copia LITERAL de esos subtrazados. Si no lo es, este
// fichero no tiene la anatomía que el guion sabe reparar y se para antes de tocar nada: media
// reparación es peor que ninguna, porque deja la letra a medio pintar sin que falle nada.
if (! preg_match('/<path d="'.preg_quote($actual, '/').'" fill="(#[0-9A-Fa-f]{6})"/', $svg, $color)) {
    fwrite(STDERR, "ABORTADO: no encuentro el `<path>` de relleno que copia esos subtrazados.\n");
    fwrite(STDERR, "  ▶ Sin él, arreglar solo `#u1` dejaría la parte restituida en BLANCO.\n");
    exit(1);
}

$salida = str_replace(
    ['<path id="u1" d="'.$m[1].'"', '<path d="'.$actual.'" fill="'.$color[1].'"'],
    [
        '<path id="u1" d="'.implode('', array_slice($subtrazados, 0, $desde)).$letra.'"',
        '<path d="'.$letra.'" fill="'.$color[1].'"',
    ],
    $svg,
);

if (substr_count($salida, $letra) !== 2) {
    fwrite(STDERR, "ABORTADO: no se han sustituido las DOS apariciones de la letra.\n");
    exit(1);
}

// Guardas del propio guion, ANTES de escribir: nada más puede haber cambiado.
foreach (['#fig', '<path id="u2"', 'viewBox'] as $ancla) {
    if (substr_count($svg, $ancla) !== substr_count($salida, $ancla)) {
        fwrite(STDERR, "ABORTADO: el guion ha tocado «{$ancla}», que no le corresponde.\n");
        exit(1);
    }
}

file_put_contents($ruta, $salida);

printf("✔ letra restituida en sus DOS apariciones (trazado de la palabra y relleno %s)\n", $color[1]);
