<?php

/**
 * BANCO DE LA COMPRA — la isla en su tamaño «Compra», con las pantallas del producto contra las del diseño,
 * estado a estado (`isla-y-landing-nueva.md` §4.10, T3c).
 *
 * Por cada estado escribe DOS páginas con el mismo marco (una foto del parque detrás, como el banco de la isla):
 *   · `a-<estado>.html`: `ParkIsland` del diseño con su `checkout` y su pantalla (`paginas/compra/*.jsx`), armados
 *     por la vista de su hook, transcrita en `scripts/banco-compra/vista-diseno.jsx`;
 *   · `b-<estado>.html`: `IslaFlotante.vue` con las pantallas de `resources/js/isla/compra/`, armadas por el
 *     adaptador de `scripts/banco-compra/entrada.js` (compilado por su `vite.config.mjs`).
 * Los dos lados parten del mismo estado, normalizado por `scripts/banco-compra/estado.js` con las funciones del
 * propio diseño. Y deja `lote.json` para `scripts/pixel.mjs`: cada estado abajo (390×844) y arriba (1280×800).
 *
 *   docker compose exec -u sail -T laravel.test npx vite build --config scripts/banco-compra/vite.config.mjs
 *   docker compose exec -u sail -T laravel.test php scripts/banco-compra.php \
 *       /var/www/instancias/playjump/diseno/playjump-design-system \
 *       /var/www/instancias/playjump/tema/compra-estados.json storage/app/pixel/banco-compra http://127.0.0.1:8130
 *   (se sirve `storage/app/pixel/banco-compra` con `php -S 127.0.0.1:8130` y se juzga con
 *    `scripts/pixel.mjs --lote …/lote.json --reloj 2026-09-23T16:05:00+02:00 --rehacer`)
 *
 * ⚠️ **Con `--rehacer`** (trampa 8 del juez): A la monta React y B Vue, y sin rehacer las cajas antes de la foto
 * Chromium pinta de 4 a 1.007 píxeles de antialias distintos en bordes redondeados con el MISMO DOM y estilo.
 */

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$diseno, $fichero, $salida, $base] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? '', $argv[4] ?? ''];
if (! is_file($diseno.'/_ds_bundle.js') || ! is_file($fichero) || $salida === '' || $base === '') {
    fwrite(STDERR, "uso: php scripts/banco-compra.php <diseño> <estados.json> <salida> <url base>\n");
    exit(1);
}
if (! is_file($salida.'/b/compra.js')) {
    fwrite(STDERR, "falta {$salida}/b/compra.js: compila antes el lado B (scripts/banco-compra/vite.config.mjs)\n");
    exit(1);
}

app()->setLocale('es');
$textos = __('isla');
$datos = json_decode((string) file_get_contents($fichero), true, 512, JSON_THROW_ON_ERROR);
$estado = (string) file_get_contents(__DIR__.'/banco-compra/estado.js');
$vista = (string) file_get_contents(__DIR__.'/banco-compra/vista-diseno.jsx');

foreach (['diseno' => realpath($diseno), 'instancia' => public_path('instancia')] as $nombre => $destino) {
    if (! file_exists($salida.'/'.$nombre)) {
        symlink($destino, $salida.'/'.$nombre);
    }
}

$marco = static fn (string $hojas, string $scripts): string => <<<HTML
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
{$hojas}
<style>body{margin:0}.pagina{display:flex;flex-direction:column;min-height:100vh}.fondo{flex:1 0 auto;height:1600px;background:#0b2e4a url(diseno/assets/media/foto-127.png) center top / cover no-repeat}@media (max-width: 899px){[data-situation]{order:99}}</style>
</head><body><div class="pagina"><div id="isla" style="display:contents"></div><main class="fondo"></main></div>
{$scripts}
</body></html>
HTML;

// Los datos del diseño que leen los dos lados: las entradas de Kids y Jump y los de la compra, como en su banco.
$datosDiseno = '<script src="diseno/paginas/entradas/contenido.js"></script><script src="diseno/paginas/compra/datos.js"></script>';

$lote = [];
foreach ($datos['estados'] as $e) {
    $banco = json_encode(['estado' => $e['estado'], 'isla' => $datos['isla'], 'textos' => $textos], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    file_put_contents($salida."/a-{$e['nombre']}.html", $marco(
        '<link rel="stylesheet" href="diseno/styles.css">',
        <<<HTML
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script src="diseno/_ds_bundle.js"></script>
{$datosDiseno}
<script>window.BANCO = {$banco};</script>
<script>{$estado}</script>
<script type="text/babel" src="diseno/paginas/compra/ui.jsx"></script>
<script type="text/babel" src="diseno/paginas/compra/pasos-1-2.jsx"></script>
<script type="text/babel" src="diseno/paginas/compra/pantalla-0.jsx"></script>
<script type="text/babel" src="diseno/paginas/compra/listo.jsx"></script>
<script type="text/babel" src="diseno/paginas/compra/entrar.jsx"></script>
<script type="text/babel">
{$vista}
const { ParkIsland } = window.SaltiaDesignSystem_33397c;
const { checkout, body } = bancoCompraVista(window.bancoCompraEstado(window.BANCO.estado));
ReactDOM.createRoot(document.getElementById('isla')).render(<ParkIsland {...window.BANCO.isla} checkout={checkout}>{body}</ParkIsland>);
</script>
HTML
    ));

    file_put_contents($salida."/b-{$e['nombre']}.html", $marco(
        '<link rel="stylesheet" href="instancia/css/saltia.css"><link rel="stylesheet" href="instancia/css/isla.css"><link rel="stylesheet" href="b/compra.css">',
        "{$datosDiseno}\n<script>window.BANCO = {$banco};</script>\n<script>{$estado}</script>\n<script src=\"b/compra.js\"></script>"
    ));

    foreach (['390x844', '1280x800'] as $ventana) {
        $lote[] = [
            'nombre' => $e['nombre'].'@'.$ventana,
            'a' => "{$base}/a-{$e['nombre']}.html",
            'b' => "{$base}/b-{$e['nombre']}.html",
            'viewport' => $ventana,
            'completa' => false,
            'clics' => $e['clics'] ?? [],
        ];
    }
}

file_put_contents($salida.'/lote.json', json_encode($lote, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo '✓ '.count($datos['estados']).' estados → '.count($lote)." pares en {$salida}/lote.json\n";
