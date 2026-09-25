<?php

/**
 * EL MODELO DE LAS PÁGINAS DE ENTRADAS CONTRA EL BRIEF — el `$z` que saca `web/entradas/modelo.php` de la instancia,
 * comparado con `PJ_ENTRADAS[zona]` de `contenido.js` del diseño (`isla-y-landing-nueva.md` §4.12, T4c·8; `#762`,
 * `#763`). El banco (`banco-entradas.php`) juzga lo que se VE con los datos del diseño; esto juzga que los datos salen
 * de los HECHOS: con los hechos del brief, el modelo tiene que escribir el brief.
 *
 *   docker compose exec -u sail -T laravel.test php scripts/modelo-entradas.php \
 *       /var/www/instancias/playjump/diseno/playjump-design-system
 *
 * Dos pruebas, las dos en español y con el reloj en `2026-09-23 16:05` (miércoles), el de los bancos:
 *  1. **Igual al brief**: los hechos REALES de la instalación (los mismos que recibe la página) con las cifras del brief
 *     encima —precios sin la promo, la nota 4,9 con 155 reseñas—. Cada campo que el diseño escribe se compara; lo que
 *     difiere por DECISIÓN (`DECIDIDAS`) se enseña aparte y no cuenta.
 *  2. **Ninguna cifra tecleada**: se mueve cada hecho (una edad, una altura, un precio, el plazo, el cierre…) y se
 *     exige que su texto se mueva con él. Un texto que no cambia al cambiar su hecho es una cifra escrita a mano.
 * Sale con 1 si una de las dos falla.
 */

use App\Http\Instancia\PageFacts;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$diseno = $argv[1] ?? '';
if (! is_file($diseno.'/paginas/entradas/contenido.js')) {
    fwrite(STDERR, "uso: php scripts/modelo-entradas.php <diseño>\n");
    exit(1);
}
app()->setLocale('es');
$leer = 'globalThis.window = globalThis; require(process.argv[1]); process.stdout.write(JSON.stringify(window.PJ_ENTRADAS));';
$brief = json_decode((string) shell_exec('node -e '.escapeshellarg($leer).' '.escapeshellarg(realpath($diseno.'/paginas/entradas/contenido.js'))), true, 512, JSON_THROW_ON_ERROR);
$modelo = require app('view')->getFinder()->find('instancia::entradas.modelo');
$reloj = new DateTimeImmutable('2026-09-23T16:05:00+02:00');

// ── Los hechos del brief: los reales, con las cifras del brief encima ────────────────────────────────────────────
$reales = app(PageFacts::class)->resolver(['site', 'schedule', 'prices', 'zones', 'products', 'product_details', 'attractions', 'social_proof']);
$precioBrief = ['Kids · 1 hora' => [800, 1000], 'Kids · 2 horas' => [1200, 1500], 'Kids · Ilimitada' => [1800, null], 'Jump · 1 hora' => [1200, 1400], 'Jump · 2 horas' => [1800, 2200]];
$conBrief = function (array $h) use ($precioBrief): array {
    foreach ($h['prices']['products'] as $i => $p) {
        if (isset($precioBrief[$p['name']])) {
            [$n, $e] = $precioBrief[$p['name']];
            $h['prices']['products'][$i]['prices'] = array_values(array_filter([['rate' => 'normal', 'cents' => $n], $e === null ? null : ['rate' => 'special', 'cents' => $e]]));
        }
    }
    foreach ($h['products']['data'] as $i => $p) {
        if (isset($precioBrief[$p['name']])) {
            $h['products']['data'][$i]['from_price_cents'] = min(array_filter($precioBrief[$p['name']]));
        }
    }
    $h['social_proof'] = ['rating' => ['value' => 4.9, 'count' => 155, 'source' => 'google', 'url' => 'https://www.google.com/maps']];
    // Los días de cada tarifa son su etiqueta del panel (`#763`); la normal, como la escribe el brief («De lunes a
    // jueves», lo que se le propone al owner). La especial se queda la del panel: su frase corta es la diferencia decidida.
    foreach ($h['prices']['rates'] as $i => $r) {
        if (empty($r['special'])) {
            $h['prices']['rates'][$i]['label'] = 'De lunes a jueves';
        }
    }

    return $h;
};

/**
 * Lo que difiere del brief por DECISIÓN, con su porqué: se enseña y no cuenta. Las rutas usan `*` por posición.
 *
 * @var array<string, string>
 */
const DECIDIDAS = [
    'precio.filas.1.dias' => '`#763`: los días de la tarifa especial, con la etiqueta del panel (con «vísperas»), no la frase corta del diseño',
    'p4.texto' => 'sin vídeos (material que falta) el brief diría «Toca un vídeo y míralo»: dice «Toca una y mírala en grande»',
    'p4.atracciones' => 'las atracciones, sus líneas y su orden son del PANEL (el diseño trae las del brief)',
    'p6.hoy.live' => '«Quedan huecos esta tarde» es de la T4e: la disponibilidad del día la dará la isla',
    'media.foto' => 'la foto de la cabecera es la de la zona en el panel (el diseño trae una provisional)',
];

/** Las claves que el diseño escribe y el modelo no (material, notas de trabajo, lo de la T4d) no se comparan. */
const FUERA = ['hoy', 'oferta', 'precio.filas.*.oferta', 'media.hueco', 'media.nota', 'p4.pendiente', 'p4.pendienteFoto', 'p4.nota', 'p5.foto', 'p5.prueba.huecos',
    'p3.inicio', 'p3.persona', 'p3.oferta', 'p3.ofertaLinea', 'p3.preguntas', 'p3.cuantos', 'p3.calcetines', 'p3.grupo', 'p3.compartirNota',
    'p3.puntos', 'p3.nota', 'p3.boton', 'p3.compartir', 'p3.detalles', 'p3.ejemplo', 'p3.filas.*.oferta', 'p3.filas.*.id',
    'p8.plazo', 'p7.*.a.*.href', 'p5.cuidados.*.link.href', 'p7.*.a.*.door'];

$aplanar = function (mixed $valor, string $ruta = '') use (&$aplanar): array {
    if (! is_array($valor)) {
        return [$ruta => is_bool($valor) ? ($valor ? '1' : '') : (is_null($valor) ? null : (string) $valor)];
    }
    $plano = [];
    foreach ($valor as $k => $v) {
        $plano += $aplanar($v, $ruta === '' ? (string) $k : $ruta.'.'.$k);
    }

    return $plano;
};
$casa = fn (string $ruta, array $patrones): ?string => collect($patrones)->first(
    fn (string $p): bool => (bool) preg_match('#^'.str_replace(['.', '*'], ['\.', '[^.]+'], $p).'(\.|$)#', $ruta)
);

$fallos = 0;
echo "── 1. Igual al brief (hechos reales con las cifras del brief) ──\n";
foreach (['kids', 'jump'] as $zona) {
    $z = $modelo($conBrief($reales), $zona, $reloj)['z'];
    $a = $aplanar($brief[$zona]);
    $b = $aplanar($z);
    $iguales = 0;
    $decididas = [];
    foreach ($a as $ruta => $valor) {
        if ($casa($ruta, FUERA)) {
            continue;
        }
        $nuestro = array_key_exists($ruta, $b) ? $b[$ruta] : '(falta)';
        if ($nuestro === $valor) {
            $iguales++;

            continue;
        }
        if ($motivo = $casa($ruta, array_keys(DECIDIDAS))) {
            $decididas[$motivo] = DECIDIDAS[$motivo];

            continue;
        }
        $fallos++;
        echo "✗ {$zona} · {$ruta}\n    brief  «{$valor}»\n    modelo «{$nuestro}»\n";
    }
    echo "✓ {$zona}: {$iguales} campos iguales al brief".($decididas ? '; distintos por decisión: '.implode(' · ', array_map(fn ($k, $v) => "{$k} ({$v})", array_keys($decididas), $decididas)) : '')."\n";
}

echo "\n── 2. Ninguna cifra tecleada: cada hecho movido mueve su texto ──\n";
// Cada movimiento: [zona, qué cambia en los hechos, qué rutas de `$z` tienen que cambiar con él].
$mover = [
    'la edad mínima de Kids (4 → 5)' => ['kids', function (array $h): array {
        foreach ($h['products']['data'] as $i => $p) {
            if (($p['zone']['slug'] ?? '') === 'kids') {
                $h['products']['data'][$i]['guest_age_min'] = 5;
            }
        }

        return $h;
    }, ['texto', 'p5.cuidados.0.text', 'p7.0.q', 'p7.1.hint']],
    'la edad mínima de Jump (8 → 9), en la página de KIDS' => ['kids', function (array $h): array {
        foreach ($h['products']['data'] as $i => $p) {
            if (($p['zone']['slug'] ?? '') === 'jump') {
                $h['products']['data'][$i]['guest_age_min'] = 9;
            }
        }

        return $h;
    }, ['p7.2.q']],
    'la altura con adulto de Kids (90 → 95 cm)' => ['kids', function (array $h): array {
        foreach ($h['zones']['data'] as $i => $zn) {
            if ($zn['slug'] === 'kids') {
                $h['zones']['data'][$i]['escort']['under_age_from_cm'] = 95;
            }
        }

        return $h;
    }, ['texto', 'p5.cuidados.2.text', 'p7.0.a']],
    'la altura con adulto de Jump (1,30 → 1,40 m)' => ['jump', function (array $h): array {
        foreach ($h['zones']['data'] as $i => $zn) {
            if ($zn['slug'] === 'jump') {
                $h['zones']['data'][$i]['escort']['below_cm'] = 140;
            }
        }

        return $h;
    }, ['p5.cuidados.1.text', 'p7.1.a']],
    'el plazo de cambio (24 h → 72 h)' => ['kids', function (array $h): array {
        foreach ($h['products']['data'] as $i => $p) {
            if (isset($p['cancellation'])) {
                $h['products']['data'][$i]['cancellation'] = ['cutoff_hours' => 72, 'written' => 'hasta 3 días antes'];
            }
        }

        return $h;
    }, ['garantias.0.text', 'p3.junto', 'p7.8.hint', 'p7.8.a']],
    'el precio de 1 hora de Kids (8 → 9 €)' => ['kids', function (array $h): array {
        foreach ($h['prices']['products'] as $i => $p) {
            if ($p['name'] === 'Kids · 1 hora') {
                $h['prices']['products'][$i]['prices'][0]['cents'] = 900;
            }
        }

        return $h;
    }, ['precio.filas.0.precio', 'p3.filas.0.precios.0', 'p3.filas.1.nota']],
    'el precio de los calcetines (2 → 2,50 €)' => ['kids', function (array $h): array {
        foreach ($h['product_details'] as $i => $f) {
            foreach ($f['addons'] as $j => $a) {
                if (str_starts_with($a['name'], 'Calcetines')) {
                    $h['product_details'][$i]['addons'][$j]['price_cents'] = 250;
                }
            }
        }

        return $h;
    }, ['p7.4.a']],
    'el pack de cumpleaños de Kids (14,95 → 16 €)' => ['kids', function (array $h): array {
        foreach ($h['products']['data'] as $i => $p) {
            if ($p['type'] === 'pack' && ($p['guest_age_min'] ?? null) === 4) {
                $h['products']['data'][$i]['from_price_cents'] = 1600;
            }
        }

        return $h;
    }, ['p7.10.hint']],
    'el cierre de todos los días (21:30 → 22:00)' => ['jump', function (array $h): array {
        foreach ($h['schedule']['weekly'] as $i => $d) {
            $h['schedule']['weekly'][$i]['closes_at'] = '22:00';
        }

        return $h;
    }, ['p6.titular', 'p6.horario.0.hours']],
    'la apertura del fin de semana (11:00 → 10:00)' => ['kids', function (array $h): array {
        foreach ($h['schedule']['weekly'] as $i => $d) {
            if (in_array($d['weekday'], [0, 6], true)) {
                $h['schedule']['weekly'][$i]['opens_at'] = '10:00';
            }
        }

        return $h;
    }, ['p6.titular']],
    'la nota de Google (4,9 → 4,7)' => ['kids', function (array $h): array {
        $h['social_proof']['rating']['value'] = 4.7;

        return $h;
    }, ['prueba.nota']],
    'la etiqueta de la tarifa normal en el panel (`#763`)' => ['kids', function (array $h): array {
        foreach ($h['prices']['rates'] as $i => $r) {
            if (empty($r['special'])) {
                $h['prices']['rates'][$i]['label'] = 'De lunes a miércoles';
            }
        }

        return $h;
    }, ['precio.filas.0.dias', 'p3.filas.2.soloLJ', 'p3.pasos.0.title', 'p3.col_normal']],
    'la dirección del parque' => ['kids', function (array $h): array {
        $h['site']['address']['line1'] = 'Calle Mayor, 1';

        return $h;
    }, ['p6.direccion']],
];
foreach ($mover as $que => [$zona, $cambiar, $rutas]) {
    $antes = $aplanar($modelo($conBrief($reales), $zona, $reloj)['z']);
    $despues = $aplanar($modelo($cambiar($conBrief($reales)), $zona, $reloj)['z']);
    $quietas = array_filter($rutas, fn (string $r): bool => ($antes[$r] ?? null) === ($despues[$r] ?? null));
    $fallos += count($quietas);
    echo ($quietas === [] ? '✓' : '✗')." {$que}: ".($quietas === [] ? 'se mueven '.count($rutas).' textos' : 'NO se mueven '.implode(', ', $quietas))."\n";
}

echo "\n".($fallos === 0 ? '✓ el modelo escribe el brief y ninguna cifra está tecleada' : "✗ {$fallos} fallos")."\n";
exit($fallos === 0 ? 0 : 1);
