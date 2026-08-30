<?php

/**
 * Afina el CONTORNO de un logotipo de instalación (`#274`, `[DECIDIDO owner]`).
 *
 * ⚠️⚠️ **Esto MODIFICA el asset del cliente, y por eso es un guion y no una edición a mano.**
 * `#211` dejó escrito que el logotipo lo exporta el owner; cuando lo vuelva a exportar, el fichero
 * llegará otra vez con el trazo grueso y habrá que **volver a pasar este guion**. Que sea
 * reproducible es lo único que hace sostenible tocar un fichero que no es nuestro.
 *
 * ▶ **Qué toca y qué NO**: solo el `stroke-width` de las **capas de color** de las palabras — los
 * `<use href="#u1|#u2">` SIN `transform`—. **No toca**:
 *   · la EXTRUSIÓN (los `<use>` con `transform`), que es la profundidad y el owner la quiere igual;
 *   · la SILUETA (`#fig`), que es un dibujo y no una letra;
 *   · los degradados, el `viewBox` ni ningún `id` — de los que dependen `InlineSvg` y la coreografía.
 *
 * Uso:  php scripts/logo-contorno.php public/img/client-logo.svg 0.65
 */
$ruta = $argv[1] ?? 'public/img/client-logo.svg';
$factor = (float) ($argv[2] ?? 0.65);

if (! is_file($ruta)) {
    fwrite(STDERR, "no existe: {$ruta}\n");
    exit(1);
}

$svg = (string) file_get_contents($ruta);
$antes = [];
$despues = [];

$salida = preg_replace_callback(
    '/(<use href="#u[12]"(?![^>]*\btransform=)[^>]*?)stroke-width="([\d.]+)"/',
    function (array $m) use ($factor, &$antes, &$despues): string {
        $w = (float) $m[2];
        $n = round($w * $factor, 2);
        $antes[] = $w;
        $despues[] = $n;

        return $m[1].'stroke-width="'.$n.'"';
    },
    $svg,
);

if ($salida === null || $salida === $svg) {
    fwrite(STDERR, "no se ha cambiado nada: ¿el fichero tiene la anatomía esperada?\n");
    exit(1);
}

// ⚠️ Guarda del propio guion: si tocara la extrusión o la silueta, el número de `<use>` con
// `transform` o los de `#fig` cambiaría. Se comprueba ANTES de escribir.
foreach (['#fig' => null, 'transform=' => null] as $ancla => $_) {
    if (substr_count($svg, $ancla) !== substr_count($salida, $ancla)) {
        fwrite(STDERR, "ABORTADO: el guion ha tocado «{$ancla}», que no le corresponde.\n");
        exit(1);
    }
}

file_put_contents($ruta, $salida);

printf("✔ %d capas de color afinadas al %d %%\n", count($antes), (int) round($factor * 100));
printf("  antes:   %s\n", implode(' · ', array_unique($antes)));
printf("  después: %s\n", implode(' · ', array_unique($despues)));
printf("  extrusión y silueta: INTACTAS (%d `<use>` con transform, %d de #fig)\n",
    substr_count($salida, 'transform='), substr_count($salida, '#fig'));
