<?php

/**
 * BANCO DE PIEZAS — las piezas del sistema de diseño que usa la compra, las del producto contra las del diseño,
 * estado a estado (`isla-y-landing-nueva.md` §4.10, T3b).
 *
 * Por cada pieza escribe DOS páginas con el mismo marco (una columna clara y otra sobre tinta, cada una con todos
 * los casos de la pieza):
 *   · `a-<pieza>.html`: los componentes del diseño (su React y su compilado `_ds_bundle.js`) con su `styles.css`;
 *   · `b-<pieza>.html`: los del producto (`resources/js/isla/ui/`, compilados por
 *     `scripts/banco-piezas/vite.config.mjs`), con la hoja de Saltia y los roles de la isla de la instancia.
 * Y deja `lote.json` para `scripts/pixel.mjs`, a página completa. Los casos viven en la instancia
 * (`tema/piezas-compra.json`): son los de las pantallas de la compra del diseño, con sus textos.
 *
 *   docker compose exec -u sail -T laravel.test npx vite build --config scripts/banco-piezas/vite.config.mjs
 *   docker compose exec -u sail -T laravel.test php scripts/banco-piezas.php \
 *       /var/www/instancias/playjump/diseno/playjump-design-system \
 *       /var/www/instancias/playjump/tema/piezas-compra.json storage/app/pixel/banco-piezas http://127.0.0.1:8129
 *
 * El lenguaje de los casos es el de las props de React del diseño; el lado B lo traduce a las del producto
 * (`scripts/banco-piezas/entrada.js`). Los textos del lado B son los de `lang/es/isla.php`.
 * Se juzga con `scripts/pixel.mjs --lote …/lote.json --reloj 2026-09-23T16:05:00+02:00 --rehacer`: A lo monta
 * React y B Vue (trampa 8 del juez).
 */

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$diseno, $fichero, $salida, $base] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? '', $argv[4] ?? ''];
if (! is_file($diseno.'/_ds_bundle.js') || ! is_file($fichero) || $salida === '' || $base === '') {
    fwrite(STDERR, "uso: php scripts/banco-piezas.php <diseño> <piezas.json> <salida> <url base>\n");
    exit(1);
}
if (! is_file($salida.'/b/piezas.js')) {
    fwrite(STDERR, "falta {$salida}/b/piezas.js: compila antes el lado B (scripts/banco-piezas/vite.config.mjs)\n");
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
<style>body{margin:0;background:#ffffff}.banco{display:flex;gap:24px;align-items:flex-start;padding:24px}.col{width:440px;box-sizing:border-box;display:grid;gap:20px;align-content:start;padding:20px}.tinta{background:var(--ink-surface)}</style>
</head><body><main class="banco" data-isla=""><section class="col" id="claro"></section><section class="col tinta" id="tinta" data-surface="ink"></section></main>
{$scripts}
</body></html>
HTML;

// El lado A: el React del diseño sin JSX (no hace falta Babel), con el mismo lenguaje de casos que el lado B.
$pintarA = <<<'JS'
const DS = window.SaltiaDesignSystem_33397c;
const esElemento = (x) => x !== null && typeof x === 'object' && !Array.isArray(x) && typeof x.$ === 'string';
const valor = (x) => {
  if (x === '@fn') return () => {};
  if (x && typeof x === 'object' && Array.isArray(x['@unidad'])) { const [uno, varios] = x['@unidad']; return (v) => v + ' ' + (v === 1 ? uno : varios); }
  if (Array.isArray(x)) return x.map((y, i) => (esElemento(y) ? nodo(y, i) : valor(y)));
  if (esElemento(x)) return nodo(x);
  if (x && typeof x === 'object') return Object.fromEntries(Object.entries(x).map(([k, v]) => [k, valor(v)]));
  return x;
};
function nodo(x, clave) {
  if (!esElemento(x)) return x;
  const { $, hijos = [], ...crudas } = x;
  const props = Object.fromEntries(Object.entries(crudas).map(([k, v]) => [k, valor(v)]));
  if (clave != null) props.key = clave;
  return React.createElement(DS[$] || $, props, ...hijos.map((y, i) => nodo(y, i)));
}
for (const id of ['claro', 'tinta']) {
  ReactDOM.createRoot(document.getElementById(id)).render(React.createElement(React.Fragment, null, ...window.BANCO[id].map((x, i) => nodo(x, i))));
}
JS;

$lote = [];
foreach ($datos['piezas'] as $pieza) {
    $banco = json_encode(['claro' => $pieza['casos'], 'tinta' => $pieza['casos'], 'textos' => $textos], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    file_put_contents($salida."/a-{$pieza['nombre']}.html", $marco(
        '<link rel="stylesheet" href="diseno/styles.css">',
        <<<HTML
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="diseno/_ds_bundle.js"></script>
<script>window.BANCO = {$banco};</script>
<script>{$pintarA}</script>
HTML
    ));

    file_put_contents($salida."/b-{$pieza['nombre']}.html", $marco(
        '<link rel="stylesheet" href="instancia/css/saltia.css"><link rel="stylesheet" href="instancia/css/isla.css"><link rel="stylesheet" href="b/piezas.css">',
        "<script>window.BANCO = {$banco};</script>\n<script src=\"b/piezas.js\"></script>"
    ));

    foreach (array_merge([['nombre' => '', 'clics' => []]], $pieza['variantes'] ?? []) as $variante) {
        $lote[] = [
            'nombre' => $pieza['nombre'].($variante['nombre'] !== '' ? '-'.$variante['nombre'] : ''),
            'a' => "{$base}/a-{$pieza['nombre']}.html",
            'b' => "{$base}/b-{$pieza['nombre']}.html",
            'viewport' => '1024x800',
            'completa' => true,
            'clics' => $variante['clics'] ?? [],
            // El puntero encima justo antes de la foto (el `hover` de un día del calendario, T4d).
            'pasar' => $variante['pasar'] ?? null,
        ];
    }
}

file_put_contents($salida.'/lote.json', json_encode($lote, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo '✓ '.count($datos['piezas']).' piezas → '.count($lote)." pares en {$salida}/lote.json\n";
