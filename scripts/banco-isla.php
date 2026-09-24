<?php

/**
 * BANCO DE LA ISLA — la isla del producto contra la del diseño, situación a situación (`isla-y-landing-nueva.md`
 * §4.9, T2c).
 *
 * Por cada situación escribe DOS páginas con el mismo marco (una columna flexible, la isla primera en el código
 * y última en móvil, sobre una foto real del parque para que el cristal desenfoque algo):
 *   · `a-<nombre>.html`: `ParkIsland` del diseño, con su React, su compilado y su `styles.css`;
 *   · `b-<nombre>.html`: `IslaFlotante.vue` del producto (compilada por `scripts/banco-isla/vite.config.mjs`), con la
 *     hoja de Saltia y los roles de la isla de la instancia.
 * Y deja `lote.json` para `scripts/pixel.mjs`: cada situación abajo (390×844) y arriba (1280×800).
 *
 *   docker compose exec -u sail -T laravel.test npx vite build --config scripts/banco-isla/vite.config.mjs
 *   docker compose exec -u sail -T laravel.test php scripts/banco-isla.php \
 *       /var/www/instancias/playjump/diseno/playjump-design-system \
 *       /var/www/instancias/playjump/tema/isla-situaciones.json storage/app/pixel/banco-isla http://127.0.0.1:8128
 *
 * Los textos del lado B son los de `lang/es/isla.php`: si una coma no es la del diseño, el juez lo ve.
 * Se juzga con `scripts/pixel.mjs --lote …/lote.json --reloj 2026-09-23T16:05:00+02:00 --rehacer`: A lo monta
 * React y B Vue (trampa 8 del juez).
 */

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$diseno, $fichero, $salida, $base] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? '', $argv[4] ?? ''];
if (! is_file($diseno.'/_ds_bundle.js') || ! is_file($fichero) || $salida === '' || $base === '') {
    fwrite(STDERR, "uso: php scripts/banco-isla.php <diseño> <situaciones.json> <salida> <url base>\n");
    exit(1);
}
if (! is_file($salida.'/b/isla.js')) {
    fwrite(STDERR, "falta {$salida}/b/isla.js: compila antes el lado B (scripts/banco-isla/vite.config.mjs)\n");
    exit(1);
}

app()->setLocale('es');
$textos = __('isla');
$datos = json_decode((string) file_get_contents($fichero), true, 512, JSON_THROW_ON_ERROR);

foreach (['diseno' => realpath($diseno), 'instancia' => public_path('instancia')] as $nombre => $destino) {
    if (! file_exists($salida.'/'.$nombre)) {
        symlink($destino, $salida.'/'.$nombre);
    }
}

$marco = static fn (string $hojas, string $scripts): string => <<<HTML
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
{$hojas}
<style>body{margin:0}.pagina{display:flex;flex-direction:column;min-height:100vh}.fondo{flex:1 0 auto;height:1600px;background:#0b2e4a url(diseno/assets/media/foto-127.png) center top / cover no-repeat}@media (max-width: 899px){[data-situation]{order:99}}</style>
</head><body><div class="pagina"><div id="isla" style="display:contents"></div><main class="fondo"></main></div>
{$scripts}
</body></html>
HTML;

$lote = [];
foreach ($datos['situaciones'] as $sit) {
    $props = array_merge($datos['comun'], ['page' => $datos['paginas'][$sit['pagina']]], $sit['props']);
    $json = json_encode($props, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    file_put_contents($salida."/a-{$sit['nombre']}.html", $marco(
        '<link rel="stylesheet" href="diseno/styles.css">',
        <<<HTML
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script src="diseno/_ds_bundle.js"></script>
<script type="text/babel">
const { ParkIsland } = window.SaltiaDesignSystem_33397c;
const conFunciones = (v) => (v === '@fn' ? () => {} : Array.isArray(v) ? v.map(conFunciones) : v && typeof v === 'object' ? Object.fromEntries(Object.entries(v).map(([k, x]) => [k, conFunciones(x)])) : v);
ReactDOM.createRoot(document.getElementById('isla')).render(<ParkIsland {...conFunciones({$json})} />);
</script>
HTML
    ));

    $banco = json_encode(['props' => $props, 'textos' => $textos], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    file_put_contents($salida."/b-{$sit['nombre']}.html", $marco(
        '<link rel="stylesheet" href="instancia/css/saltia.css"><link rel="stylesheet" href="instancia/css/isla.css"><link rel="stylesheet" href="b/isla.css">',
        "<script>window.BANCO = {$banco};</script>\n<script src=\"b/isla.js\"></script>"
    ));

    foreach (['390x844', '1280x800'] as $ventana) {
        $lote[] = [
            'nombre' => $sit['nombre'].'@'.$ventana,
            'a' => "{$base}/a-{$sit['nombre']}.html",
            'b' => "{$base}/b-{$sit['nombre']}.html",
            'viewport' => $ventana,
            'completa' => false,
            'clics' => $sit['clics'] ?? [],
        ];
    }
}

file_put_contents($salida.'/lote.json', json_encode($lote, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo '✓ '.count($datos['situaciones']).' situaciones → '.count($lote)." pares en {$salida}/lote.json\n";
