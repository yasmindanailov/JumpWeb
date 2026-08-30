<?php

/**
 * Corrige las DOS SOMBRAS que se traducen mal al exportar un lockup CSS a SVG (`#275`).
 *
 * Son dos defectos independientes y los dos los vio el owner: la sombra de FUERA —la extrusión, que
 * sale engordada— y la de DENTRO —el velo del borde inferior de las letras, que sale ~3,5× más
 * fuerte y les quita color vivo—.
 *
 * ══ DEFECTO 1 · LA PROFUNDIDAD, Y POR QUÉ NADIE LO VIO EN CUATRO TANDAS ═════════════════════════
 *
 * El lockup del mockup construye cada palabra apilando cuatro `-webkit-text-stroke` decrecientes y
 * dando la profundidad con un **`text-shadow` de 6 pasos**. La exportación a SVG lo tradujo a
 * `<use>` con `stroke-width`: las capas de color, bien; **y los 26 `<use>` de la extrusión con el
 * MISMO `stroke-width` que la capa de color**.
 *
 * ⚠️⚠️ **Y ahí está el fallo: `text-shadow` NO arrastra el `-webkit-text-stroke`.** Su sombra son
 * copias del **glifo desnudo**; las nuestras eran copias del glifo **engordado media anchura de
 * trazo por lado**. Medido en navegador con control (mismo glifo con y sin trazo):
 *
 *     glifo con `-webkit-text-stroke: 6.5px` .......... 20,63 px de ancho
 *     su `text-shadow` .................................. 14,13 px
 *     el mismo glifo SIN trazo, su sombra ............... 14,13 px   ← el control
 *
 * ▶ Consecuencia a la talla de la web (70 px): el faldón azul que asoma por debajo de cada letra
 * medía **7,1 px** contra los **4,0 px** del mockup, y **todas** las columnas del contorno tenían
 * faldón (1121 de 1121) frente a 852 de 1137 en el suyo. Es lo que el owner describió como «un
 * borde exterior de unos 5 px» contra «2 o 3» en su landing.
 *
 * ⚠️ **`#274` midió las BANDAS del contorno, que son idénticas** (cian 1,00 · tinta 1,50 ·
 * blanco 0,875 px en los dos), concluyó que el ojo veía algo que la aritmética no, y adelgazó las
 * bandas al 65 %. Eso no tocó la causa —la sombra— y además rompió una invariante propia del
 * dibujo: al encoger la capa de color y no la extrusión, el azul marino pasó a asomar **1,14 px
 * por fuera del cian** en todo el contorno. *Se midió lo que se veía, no lo que sobresalía.*
 *
 * ══ QUÉ TOCA Y QUÉ NO ══════════════════════════════════════════════════════════════════════════
 *
 * Retira `stroke` y `stroke-width` **solo** de los `<use>` de extrusión de las PALABRAS — los que
 * referencian `#u1`/`#u2` **y llevan `transform`**. No toca:
 *   · las capas de COLOR (los `<use>` sin `transform`): sus tres bandas ya son las del mockup;
 *   · la SILUETA (`#fig`) — y esto NO es una omisión, es la diferencia entre los dos mecanismos:
 *     en el mockup la figura es un `<img>` con `filter: drop-shadow(…)`, y **un `drop-shadow` SÍ
 *     sigue el alfa completo de lo que dibuja, contorno incluido**. Ahí la extrusión con trazo es
 *     justamente lo correcto;
 *   · los degradados, el `viewBox` ni ningún `id` — de los que dependen `InlineSvg` y la coreografía.
 *
 * ⚠️⚠️ **Esto MODIFICA el asset del cliente, y por eso es un guion y no una edición a mano.**
 * `#211` dejó escrito que el logotipo lo exporta el owner; cuando lo vuelva a exportar llegará otra
 * vez con la sombra engordada y **hay que volver a pasar este guion**. Que sea reproducible es lo
 * único que hace sostenible tocar un fichero que no es nuestro.
 *
 * ⚠️ **Es IDEMPOTENTE**: pasarlo dos veces deja el mismo fichero (a diferencia del guion de `#274`,
 * que multiplicaba y al segundo pase dejaba los trazos al 42 %). Se puede correr sin miedo.
 *
 * ══ DEFECTO 2 · EL VELO DE DENTRO SALE ~3,5× MÁS FUERTE ════════════════════════════════════════
 *
 * El lockup pinta un degradado oscuro en el borde INFERIOR de cada palabra con
 * `background-clip: text`, así que **el degradado se mide sobre la CAJA DE LÍNEA**. La exportación
 * lo tradujo a un `<linearGradient>` con `objectBoundingBox`, que se mide sobre la **TINTA**.
 *
 * ⚠️⚠️ **No son la misma caja**: la de línea baja hasta el hueco de los descendentes, así que su
 * degradado ya ha decaído cuando llega al pie de las letras. Copiar sus paradas al pie de la tinta
 * pone ahí el valor de arranque. Medido en el borde inferior de la «J» (α del velo):
 *
 *     distancia:      0,25   0,50   1,00   1,50   2,00   3,00   4,00   6,00 px
 *     su lockup:     0,112  0,107  0,096  0,086  0,076  0,051  0,025  0,000
 *     el export:     0,377  0,360  0,326  0,293  0,259  0,192  0,155  0,092   ← 3,4× y el doble de largo
 *     corregido:     0,117  0,107  0,096  0,086  0,071  0,051  0,025  0,000   ← 6 de 8 idénticas
 *
 * ▶ Por eso las paradas pasan de `.5 / .18 / 0` en `0 / 15 % / 33 %` a **`.14 / 0` en `0 / 20 %`**.
 * Son dos paradas porque el tramo que queda del suyo es RECTO.
 *
 * ⚠️⚠️ **Y hay una segunda mitad: el TONO.** El lockup usa un velo distinto por palabra —frío bajo
 * la palabra fría, cálido bajo la cálida— y la exportación dejó **uno solo, el frío, para las dos**.
 * Un velo azul sobre letras amarillas y naranjas no las oscurece: las **desatura**. Es lo que el
 * owner describió como «pierde color vivo».
 * ▶ El color cálido **se pasa por argumento y no vive aquí**: es un dato de la marca del cliente
 * (`DECISIONES #1`). Sin él, el guion aplica solo la corrección de fuerza, que sí es general.
 * ⚠️ El BRILLO superior se midió también y **NO diverge** (0,278 el suyo contra 0,263 el nuestro,
 * y los dos se apagan): no se toca.
 *
 * ⚠️ **Es IDEMPOTENTE**: pasarlo dos veces deja el mismo fichero (a diferencia del guion de `#274`,
 * que multiplicaba y al segundo pase dejaba los trazos al 42 %). Se puede correr sin miedo.
 *
 * ⚠️ **El logotipo NO viaja en el despliegue** (`deploy.sh` excluye los ficheros de marca): hay que
 * subirlo aparte, o pasar el guion en el servidor —`scripts/` sí viaja—.
 *
 * Uso:  php scripts/logo-sombra.php public/img/client-logo.svg [#RRGGBB del velo cálido]
 */
const VELO_ALFA = '.14';        // arranque del velo al pie de la TINTA (medido: el suyo da .112 a 0,25 px)
const VELO_FIN_FRIA = '0.200';  // dónde se apaga, en fracción de la caja de la palabra fría
const VELO_FIN_CALIDA = '0.188';

$ruta = $argv[1] ?? 'public/img/client-logo.svg';
$veloCalido = $argv[2] ?? null;

if ($veloCalido !== null && ! preg_match('/^#[0-9A-Fa-f]{6}$/', $veloCalido)) {
    fwrite(STDERR, "el velo cálido tiene que ser un color en formato #RRGGBB, y ha llegado «{$veloCalido}».\n");
    exit(1);
}

if (! is_file($ruta)) {
    fwrite(STDERR, "no existe: {$ruta}\n");
    exit(1);
}

$svg = (string) file_get_contents($ruta);
$tocadas = 0;

$salida = preg_replace_callback(
    '/<use href="#u[12]"[^>]*\btransform="[^"]*"[^>]*>/',
    function (array $m) use (&$tocadas): string {
        $limpio = preg_replace('/\s(?:stroke|stroke-width)="[^"]*"/', '', $m[0]);
        if ($limpio !== $m[0]) {
            $tocadas++;
        }

        return $limpio;
    },
    $svg,
);

if ($salida === null) {
    fwrite(STDERR, "ABORTADO: el reemplazo ha fallado.\n");
    exit(1);
}

// ⚠️ Guardas del propio guion, comprobadas ANTES de escribir. La del guion de `#274` contaba
// ocurrencias de `#fig` y `transform=` — o sea, comprobaba que NO había tocado la extrusión, que
// era justo el defecto. Éstas comprueban lo contrario: que la extrusión ha quedado sin trazo y que
// nada MÁS ha cambiado.
$capasColor = fn (string $s) => preg_match_all('/<use href="#u[12]"(?![^>]*\btransform=)[^>]*\bstroke-width="[\d.]+"/', $s);
$fig = fn (string $s) => substr_count($s, '#fig');

foreach ([
    'las capas de COLOR de las palabras' => $capasColor,
    'la SILUETA' => $fig,
] as $queEs => $cuenta) {
    if ($cuenta($svg) !== $cuenta($salida)) {
        fwrite(STDERR, "ABORTADO: el guion ha tocado {$queEs}, que no le corresponde.\n");
        exit(1);
    }
}

if (preg_match('/<use href="#u[12]"[^>]*\btransform="[^"]*"[^>]*\bstroke-width=/', $salida) === 1) {
    fwrite(STDERR, "ABORTADO: ha quedado extrusión con trazo.\n");
    exit(1);
}

// ══ DEFECTO 2 · EL VELO DE DENTRO ══════════════════════════════════════════════════════════════
$velo = 0;

if (preg_match('/<linearGradient id="sombraTexto"[^>]*>(.*?)<\/linearGradient>/s', $salida, $g)) {
    if (! preg_match('/stop-color="(#[0-9A-Fa-f]{6})"/', $g[1], $c)) {
        fwrite(STDERR, "ABORTADO: el velo no declara un `stop-color` reconocible.\n");
        exit(1);
    }

    $frio = $c[1];
    $paradas = fn (string $color, string $fin) => '<stop offset="0" stop-color="'.$color.'" stop-opacity="'.VELO_ALFA.'"></stop>'
        .'<stop offset="'.$fin.'" stop-color="'.$color.'" stop-opacity="0"></stop>';

    $bloque = '<linearGradient id="sombraTexto" x1="0" y1="1" x2="0" y2="0">'.$paradas($frio, VELO_FIN_FRIA).'</linearGradient>';

    // ⚠️ La idempotencia se decide AQUÍ: si el velo cálido ya está declarado no se vuelve a añadir,
    // o el segundo pase dejaría dos declaraciones con el mismo `id` y ganaría una en silencio.
    $yaCalido = str_contains($salida, 'id="sombraTextoCalido"');

    if ($veloCalido !== null && ! $yaCalido) {
        $bloque .= "\n  ".'<linearGradient id="sombraTextoCalido" x1="0" y1="1" x2="0" y2="0">'
            .$paradas($veloCalido, VELO_FIN_CALIDA).'</linearGradient>';
    }

    $nuevo = str_replace($g[0], $bloque, $salida);

    // La palabra CÁLIDA es `#u2`. Solo se repunta si se ha dado color: si no, se queda con el frío,
    // que es lo que trae el export — a medias, pero no inventado.
    if ($veloCalido !== null) {
        $nuevo = str_replace(
            '<use href="#u2" fill="url(#sombraTexto)">',
            '<use href="#u2" fill="url(#sombraTextoCalido)">',
            $nuevo,
        );

        if (substr_count($nuevo, 'id="sombraTextoCalido"') !== 1
            || substr_count($nuevo, 'fill="url(#sombraTextoCalido)"') !== 1) {
            fwrite(STDERR, "ABORTADO: el velo cálido tiene que quedar declarado UNA vez y usado UNA vez.\n");
            exit(1);
        }
    }

    if ($nuevo !== $salida) {
        $salida = $nuevo;
        $velo = 1;
    }
}

if ($tocadas === 0 && $velo === 0) {
    echo "nada que hacer: las dos sombras ya están corregidas (el guion es idempotente).\n";
    exit(0);
}

file_put_contents($ruta, $salida);

printf("✔ %d capas de extrusión de palabra sin trazo\n", $tocadas);
if ($velo === 1) {
    printf("✔ velo inferior a %s y apagándose al %s / %s%s\n", VELO_ALFA, VELO_FIN_FRIA, VELO_FIN_CALIDA,
        $veloCalido !== null ? ", cálido {$veloCalido} bajo la palabra cálida" : ' (sin velo cálido: no se ha dado color)');
}
printf("  capas de color: %d, INTACTAS · referencias a #fig: %d, INTACTAS\n", $capasColor($salida), $fig($salida));
