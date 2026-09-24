<?php

/**
 * BANCO DE LUCIDE — los iconos del producto contra los del diseño, píxel a píxel (`DECISIONES #686`).
 *
 * Escribe dos páginas con la MISMA rejilla, la misma hoja (la `styles.css` del diseño) y el mismo DOM:
 *   · `a.html`: cada icono con el `Icon` del diseño (su `_ds_bundle.js`, que lo baja de jsDelivr);
 *   · `b.html`: cada icono con `<x-lucide>` del producto, renderizado por Blade.
 * Así lo único que puede diferir es cómo se pinta el icono. La lista son TODOS los nombres que usa el diseño
 * (se leen de sus fuentes), a 16, 20 y 24 px, más las variantes: relleno, con nombre, en chip y con color.
 *
 *   docker compose exec -u sail -T laravel.test \
 *       php scripts/banco-lucide.php /var/www/instancias/playjump/diseno/playjump-design-system storage/app/pixel/banco-lucide
 *   (se sirve `storage/app/pixel/banco-lucide` con `php -S` y se juzga con `scripts/pixel.mjs`)
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Blade;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$diseno, $salida] = [$argv[1] ?? '', $argv[2] ?? ''];
if (! is_dir($diseno) || ! is_file($diseno.'/_ds_bundle.js') || $salida === '') {
    fwrite(STDERR, "uso: php scripts/banco-lucide.php <carpeta del diseño> <salida>\n");
    exit(1);
}

// Los nombres que usa el diseño: <Icon name="…">, icon: "…" e icon="…" en sus fuentes.
$nombres = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($diseno, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (! preg_match('/\.(jsx|js|html)$/', $f->getFilename()) || $f->getFilename() === '_ds_bundle.js') {
        continue;
    }
    preg_match_all('/(?:<Icon[^>]*\bname="|\bicon:\s*"|\bicon=")([a-z0-9-]+)"/', (string) file_get_contents($f->getPathname()), $m);
    array_push($nombres, ...$m[1]);
}
$nombres = array_values(array_unique($nombres));
sort($nombres);

$tallas = [16, 20, 24];
$variantes = [
    ['name' => 'star', 'size' => 20, 'fill' => true],
    ['name' => 'star', 'size' => 20, 'fill' => true, 'label' => '4,9 en Google'],
    ['name' => 'ticket', 'size' => 18, 'strokeBox' => true],
    ['name' => 'shield-check', 'size' => 22, 'color' => 'var(--aqua-600)'],
];

@mkdir($salida, 0775, true);
if (! file_exists($salida.'/diseno')) {
    symlink(realpath($diseno), $salida.'/diseno');
}

$cabeza = <<<'HTML'
<!doctype html><html lang="es"><head><meta charset="utf-8"><link rel="stylesheet" href="diseno/styles.css">
<style>body{padding:16px}.g{display:grid;grid-template-columns:repeat(12,64px);gap:8px}.c{display:flex;align-items:center;justify-content:center;height:52px;color:var(--text-strong)}</style>
HTML;

// A: el Icon del diseño. Las etiquetas de React y Babel son las de sus fichas, con su integridad.
$lista = json_encode($nombres);
$tallasJs = json_encode($tallas);
$variantesJs = json_encode($variantes);
file_put_contents($salida.'/a.html', $cabeza.<<<HTML

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script src="diseno/_ds_bundle.js"></script>
</head><body><div id="root"></div>
<script type="text/babel">
const { Icon } = window.SaltiaDesignSystem_33397c;
const N = {$lista}, T = {$tallasJs}, V = {$variantesJs};
function Banco(){return(<div className="g">{N.flatMap(n => T.map(s => <div className="c" key={n+s}><Icon name={n} size={s}/></div>))}{V.map((v,i) => <div className="c" key={'v'+i}><Icon {...v}/></div>)}</div>)}
ReactDOM.createRoot(document.getElementById('root')).render(<Banco/>);
</script></body></html>
HTML);

// B: <x-lucide> del producto, con el mismo DOM (una celda por icono, dentro de #root > .g).
$celdas = '';
foreach ($nombres as $n) {
    foreach ($tallas as $s) {
        $celdas .= Blade::render('<div class="c"><x-lucide :name="$n" :size="$s" /></div>', ['n' => $n, 's' => $s]);
    }
}
foreach ($variantes as $v) {
    $celdas .= Blade::render('<div class="c"><x-lucide :name="$v[\'name\']" :size="$v[\'size\']" :fill="$v[\'fill\'] ?? false" :label="$v[\'label\'] ?? null" :stroke-box="$v[\'strokeBox\'] ?? false" :color="$v[\'color\'] ?? \'currentColor\'" /></div>', ['v' => $v]);
}
file_put_contents($salida.'/b.html', $cabeza."\n</head><body><div id=\"root\"><div class=\"g\">".$celdas."</div></div></body></html>\n");

echo '✓ '.count($nombres).' iconos × '.count($tallas).' tallas + '.count($variantes)." variantes → {$salida}/a.html y b.html\n";
