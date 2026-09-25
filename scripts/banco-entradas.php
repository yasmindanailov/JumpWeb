<?php

/**
 * BANCO DE LAS PÁGINAS DE ENTRADAS — las piezas de Kids y Jump de la instancia contra las del diseño, zona a zona
 * (`isla-y-landing-nueva.md` §4.12, T4c; `DECISIONES #762`).
 *
 * Por cada pieza y zona escribe DOS páginas con el mismo marco, el de la página montada del diseño:
 *   · `a/<zona>-<pieza>.html`: la pieza React del diseño (su `.jsx` de `paginas/entradas/`, con Babel y el compilado
 *     `_ds_bundle.js`) con los datos de `contenido.js` y su `styles.css`, dentro de su `<section>`, y con el `<style>`
 *     de `paginas/kids.card.html` copiado TAL CUAL del diseño;
 *   · `b/<zona>-<pieza>.html`: la pieza Blade de la INSTANCIA (`web/entradas/`, con sus `web/components/`) con el
 *     MISMO `$z` —el JSON de `contenido.js`— y las hojas que carga la página de verdad (las de Saltia, la de la isla y
 *     `entradas.css`).
 * Y deja `lote.json` para `scripts/pixel.mjs`: cada par a 390 y a 1280, a página completa.
 *
 * ⚠️ Los dos lados con los corchetes COMO EN NUESTRA PÁGINA: sin oferta ni precio de antes (`#699`), sin JumpPoints,
 * [Jump Club] ni [Bono], y sin la isla (la isla viva en la página es la T4e). El A no es la ficha de `sections/`,
 * que la lleva dentro y enseña los interruptores (`#762`·5).
 * ⚠️ El día: el diseño marca la fila de hoy por el día de la semana (`PJ_FILA_HOY`), y el juez congela el reloj del
 * navegador; el lado B recibe la misma fila, calculada aquí con la MISMA fecha que se le pasa al juez (`RELOJ`).
 *
 *   cp -r ../instancias/playjump/publico/instancia public/        # las hojas y el logotipo de la instancia
 *   docker compose exec -u sail -T laravel.test php scripts/banco-entradas.php \
 *       /var/www/instancias/playjump/diseno/playjump-design-system storage/app/pixel/banco-entradas http://127.0.0.1:8131
 *   docker compose exec -u sail -T laravel.test php -S 127.0.0.1:8131 -t storage/app/pixel/banco-entradas   # aparte
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test node scripts/pixel.mjs \
 *       --lote storage/app/pixel/banco-entradas/lote.json --reloj 2026-09-23T16:05:00+02:00 --rehacer --reintentos 2 \
 *       --salida storage/app/pixel/banco-entradas/juicio
 *
 * ⚠️ `--reintentos 2`, medido (24-09): con el puntero sobre una puerta con flecha, el DISEÑO contra sí mismo da un
 * píxel distinto en el primer intento (la flecha que se desplaza, trampa 6 del juez) y su captura estable es idéntica
 * a la nuestra. Reintentar solo demuestra identidad: un 0 exige que coincidan todos los píxeles.
 *
 * Con nombres de pieza al final, solo esas (`… http://127.0.0.1:8131 cabecera`).
 */

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** La fecha que se le pasa al juez con `--reloj`: miércoles, así que la fila de hoy es la de lunes a jueves. */
const RELOJ = '2026-09-23T16:05:00+02:00';

[$diseno, $salida, $base] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
$solo = array_slice($argv, 4);
if (! is_file($diseno.'/_ds_bundle.js') || $salida === '' || $base === '') {
    fwrite(STDERR, "uso: php scripts/banco-entradas.php <diseño> <salida> <url base> [pieza…]\n");
    exit(1);
}
foreach (['css/entradas.css', 'css/saltia.css', 'img/logo-playjump-sm.png'] as $fichero) {
    if (! is_file(public_path('instancia/'.$fichero))) {
        fwrite(STDERR, "falta public/instancia/{$fichero}: cp -r ../instancias/playjump/publico/instancia public/\n");
        exit(1);
    }
}

app()->setLocale('es');

// Los datos del diseño, tal cual: `contenido.js` es JavaScript (claves sin comillas, ` `), así que lo lee Node.
$leer = 'globalThis.window = globalThis; require(process.argv[1]); process.stdout.write(JSON.stringify(window.PJ_ENTRADAS));';
$contenido = json_decode((string) shell_exec('node -e '.escapeshellarg($leer).' '.escapeshellarg(realpath($diseno.'/paginas/entradas/contenido.js'))), true, 512, JSON_THROW_ON_ERROR);

// El marco: el `<style>` de la página montada del diseño, copiado byte a byte.
preg_match('#<style>(.*?)</style>#s', (string) file_get_contents($diseno.'/paginas/kids.card.html'), $m) || exit("sin <style> en paginas/kids.card.html\n");
$marco = $m[1];

// La fila de hoy, con la regla de las fichas del diseño (`PJ_FILA_HOY`: de lunes a jueves, la primera).
$diaSemana = (int) (new DateTimeImmutable(RELOJ))->format('w');
$filaHoy = $diaSemana >= 1 && $diaSemana <= 4 ? 0 : 1;

/**
 * Lo que el diseño deja escrito en sus `.jsx` y no en `contenido.js` (el teléfono, las URL, el pie entero de
 * `piezas-7-9.jsx`): el lado B lo recibe con los mismos valores, como se lo dará el modelo del molde.
 */
$contacto = ['telefono' => '641 99 57 14', 'tel' => 'tel:+34641995714', 'whatsapp' => 'https://wa.me/34641995714'];
$pie = static function (string $zona): array {
    $actual = $zona === 'jump' ? 'Jump' : 'Kids';
    $paginas = [['Cumpleaños', '#cumpleanos'], ['Kids', '#kids'], ['Jump', '#jump'], ['Colegios', '#colegios'], ['Visítanos', '#visitanos'], ['Normas y seguridad', '#normas'], ['Mi cuenta', '#cuenta']];

    return [
        'brand' => ['src' => '../instancia/img/logo-playjump-sm.png', 'alt' => 'Play Jump Park', 'href' => '#portada', 'height' => 46],
        'address' => ['lines' => ['Pol. Ind. Los Peñones', 'Ctra. de Granada, km 163', 'Lorca, Murcia'], 'note' => 'Parking gratis', 'mapsHref' => 'https://maps.google.com'],
        'hours' => [
            ['label' => 'De lunes a viernes', 'hours' => '16:30–21:30', 'days' => [1, 2, 3, 4, 5]],
            ['label' => 'Sábados, domingos y festivos', 'hours' => '11:00–21:30', 'days' => [0, 6]],
        ],
        'contact' => ['phone' => '641 99 57 14', 'whatsappHref' => 'https://wa.me/34641995714'],
        'nav' => array_map(fn (array $p): array => ['label' => $p[0], 'href' => $p[0] === $actual ? '#' : $p[1]], $paginas),
        'legal' => [['label' => 'Aviso legal', 'href' => '#legal'], ['label' => 'Privacidad', 'href' => '#privacidad'], ['label' => 'Accesibilidad', 'href' => '#accesibilidad'], ['label' => 'Configurar cookies']],
        'payment' => 'Pago con tarjeta o Bizum',
        'social' => [['label' => 'Instagram', 'href' => 'https://instagram.com'], ['label' => 'TikTok', 'href' => 'https://tiktok.com']],
        'language' => ['current' => 'Español'],
        'copyright' => '© 2026 Play Jump Park',
    ];
};

/**
 * Un estado con el puntero encima de un botón o enlace, para juzgar su `hover`. Con `movimiento`, SIN reducirlo: solo
 * en los BOTONES, porque su levantamiento es un token que el movimiento reducido anula (`--lift-hover: 0px`). Un enlace
 * se juzga con él reducido: sus duraciones pasan a 1 ms y el `hover` (color, subrayado, la flecha) sale entero y quieto
 * —medido: con movimiento, la flecha que se desplaza dejaba al propio DISEÑO distinto de sí mismo en el 1.er intento—.
 */
$pasar = static fn (string $selector, array $clics = [], bool $movimiento = false): array => ['pasar' => $selector, 'clics' => $clics, 'movimiento' => $movimiento];
$pasarBoton = static fn (string $selector): array => $pasar($selector, [], true);

/**
 * Las piezas: su `.jsx` (bajo `paginas/`), cómo la monta la página del diseño (`pagina.jsx`, con nuestros corchetes),
 * la vista de la instancia que la pinta con lo que recibe además de `$z`, y sus ESTADOS además del de reposo: lo que se
 * pulsa antes de la foto (`clics`, abrir una duda) y dónde se deja el puntero (`pasar`). Los selectores valen en los dos
 * lados: el A no tiene nuestras clases, así que van por texto, por rol o por atributo.
 */
$piezas = [
    'cabecera' => [
        'jsx' => ['entradas/piezas-1-2.jsx'],
        'react' => '<section className="sec sec--top"><div className="wrap"><CabeceraEntradas z={z} ofertas={false} /></div></section>',
        'vista' => 'instancia::entradas.piezas-1-2',
        'datos' => fn (string $zona): array => ['hoy' => $filaHoy, 'huecos' => true, 'logo' => '../instancia/img/logo-playjump-sm.png', 'logoAlt' => 'Play Jump Park', 'google' => 'https://www.google.com/maps'],
        'estados' => ['boton' => $pasarBoton('text=Reservar para hoy'), 'enlace' => $pasar('text=Ver precios'), 'resenas' => $pasar('text=155 reseñas')],
    ],
    'dudas' => [
        'jsx' => ['entradas/pieza-7.jsx'],
        'react' => '<section className="sec"><div className="wrap"><DudasEntradas z={z} /></div></section>',
        'vista' => 'instancia::entradas.pieza-7',
        'datos' => fn (string $zona): array => [],
        'estados' => [
            'otra' => ['clics' => ['button[aria-expanded] >> nth=1']],
            'cerrada' => ['clics' => ['button[aria-expanded] >> nth=0']],
            // ⚠️ `a:has-text`, no `a:text-is`: éste casa con el elemento MÁS PEQUEÑO que tiene el texto (el `<span>` de
            // dentro del enlace), y el puntero esperaba 30 s a un `<a>` que no llegaba.
            'aqui' => $pasar('a:has-text("aquí")', ['button:has-text("¿Puedo comprar la entrada para hoy?")']),
            'puerta' => $pasar('a:has-text("Ver el cumpleaños")', ['button:has-text("¿Hacéis cumpleaños")']),
        ],
    ],
    // La pieza 5 COMO IRÁ HOY: sin las tres reseñas (esperan la T2·9 del SPA) y sin la foto que cuida (material que
    // falta), las dos sin hueco (`#761`·3). El A es el diseño con esos datos vaciados antes de montar.
    'tranquilidad' => [
        'jsx' => ['entradas/pieza-5.jsx'],
        'antes' => 'for (const k of ["kids", "jump"]) window.PJ_ENTRADAS[k].p5.prueba.huecos = []; const DS5 = window.SaltiaDesignSystem_33397c; const PF5 = DS5.ProofList; DS5.ProofList = (p) => React.createElement(PF5, Object.assign({}, p, { media: null }));',
        'react' => '<section className="sec"><div className="wrap"><TranquilidadEntradas z={z} /></div></section>',
        'vista' => 'instancia::entradas.pieza-5',
        'datos' => fn (string $zona): array => ['google' => 'https://www.google.com/maps', 'foto' => null],
        'estados' => ['normas' => $pasar('text=Ver normas y seguridad'), 'resenas' => $pasar('text=Leer las reseñas en Google')],
    ],
    // Y su forma CON foto, para el día que llegue el material: la misma imagen a los dos lados.
    'tranquilidad-foto' => [
        'jsx' => ['entradas/pieza-5.jsx'],
        'antes' => 'for (const k of ["kids", "jump"]) window.PJ_ENTRADAS[k].p5.prueba.huecos = []; const DS5 = window.SaltiaDesignSystem_33397c; const PF5 = DS5.ProofList; DS5.ProofList = (p) => React.createElement(PF5, Object.assign({}, p, { media: { src: "../assets/media/foto-120.png", alt: "Un monitor con los niños" } }));',
        'react' => '<section className="sec"><div className="wrap"><TranquilidadEntradas z={z} /></div></section>',
        'vista' => 'instancia::entradas.pieza-5',
        'datos' => fn (string $zona): array => ['google' => 'https://www.google.com/maps', 'foto' => ['src' => '../assets/media/foto-120.png', 'alt' => 'Un monitor con los niños']],
    ],
    'donde' => [
        'jsx' => ['entradas/pieza-6.jsx'],
        // ⚠️ El mapa es material que NO EXISTE (el diseño pinta su hueco de «pendiente», y la web nunca: `#761`·3). Para
        // juzgar la forma CON mapa, el `ParkLocation` del propio diseño recibe una imagen que hace de mapa, envuelto en
        // su espacio antes de montar (la pieza lo lee al pintarse); el lado B, la misma. Su código no se toca.
        'antes' => 'const DS6 = window.SaltiaDesignSystem_33397c; const PL6 = DS6.ParkLocation; DS6.ParkLocation = (p) => React.createElement(PL6, Object.assign({}, p, { src: "../assets/media/foto-113.png", alt: "Mapa del parque" }));',
        'react' => '<section className="sec"><div className="wrap"><DondeEntradas z={z} ofertas={false} /></div></section>',
        'vista' => 'instancia::entradas.pieza-6',
        'datos' => fn (string $zona): array => ['hoy' => $filaHoy, 'huecos' => true, 'diaSemana' => $diaSemana, 'mapsHref' => 'https://maps.google.com', 'mapa' => ['src' => '../assets/media/foto-113.png', 'alt' => 'Mapa del parque']],
        'estados' => ['llegar' => $pasarBoton('text=Cómo llegar'), 'boton' => $pasarBoton('text=Reservar para hoy')],
    ],
    'cierre' => [
        'jsx' => ['entradas/pieza-8.jsx'],
        'react' => '<section className="sec sec--cierre"><div className="wrap"><CierreEntradas z={z} ofertas={false} /></div></section>',
        'vista' => 'instancia::entradas.pieza-8',
        'datos' => fn (string $zona): array => ['hoy' => $filaHoy, 'huecos' => true, 'bizum' => true, 'google' => 'https://www.google.com/maps', 'contacto' => $contacto],
        'estados' => ['boton' => $pasarBoton('text=Reservar para hoy'), 'resenas' => $pasar('text=155 reseñas'), 'whatsapp' => $pasar('text=escríbenos por WhatsApp'), 'telefono' => $pasar('a >> text=641 99 57 14')],
    ],
    'pie' => [
        'jsx' => ['piezas-7-9.jsx'],
        'react' => '<section className="sec sec--pie"><div className="wrap"><Pie actual={z.zona === "jump" ? "Jump" : "Kids"} /></div></section>',
        'vista' => 'instancia::entradas.pie',
        'datos' => fn (string $zona): array => ['hoy' => $diaSemana, 'pie' => $pie($zona)],
        'estados' => ['llegar' => $pasar('text=Cómo llegar'), 'pagina' => $pasar('text=Cumpleaños'), 'cookies' => $pasar('text=Configurar cookies'), 'telefono' => $pasar('text=641 99 57 14'), 'idioma' => $pasar('text=Español'), 'red' => $pasar('text=Instagram')],
    ],
];

is_dir($salida) || mkdir($salida, 0775, true);
foreach (['diseno' => realpath($diseno), 'assets' => realpath($diseno.'/assets'), 'instancia' => public_path('instancia')] as $nombre => $destino) {
    if (! file_exists($salida.'/'.$nombre)) {
        symlink($destino, $salida.'/'.$nombre);
    }
}
foreach (['a', 'b'] as $lado) {
    is_dir($salida.'/'.$lado) || mkdir($salida.'/'.$lado, 0775, true);
}

$lote = [];
foreach ($piezas as $nombre => $pieza) {
    if ($solo !== [] && ! in_array($nombre, $solo, true)) {
        continue;
    }
    foreach (['kids', 'jump'] as $zona) {
        $scripts = implode("\n", array_map(fn (string $jsx): string => '<script type="text/babel" src="../diseno/paginas/'.$jsx.'"></script>', $pieza['jsx']));
        $antes = $pieza['antes'] ?? '';
        file_put_contents($salida."/a/{$zona}-{$nombre}.html", <<<HTML
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../diseno/styles.css">
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script src="../diseno/_ds_bundle.js"></script>
<style>{$marco}</style>
</head><body><div id="root"></div>
<script src="../diseno/paginas/entradas/contenido.js"></script>
{$scripts}
<script type="text/babel">{$antes}const z = window.PJ_ENTRADAS["{$zona}"]; ReactDOM.createRoot(document.getElementById("root")).render({$pieza['react']});</script>
</body></html>
HTML);

        $cuerpo = view($pieza['vista'], ['z' => $contenido[$zona], ...$pieza['datos']($zona)])->render();
        file_put_contents($salida."/b/{$zona}-{$nombre}.html", <<<HTML
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../instancia/css/fuentes.css"><link rel="stylesheet" href="../instancia/css/saltia.css"><link rel="stylesheet" href="../instancia/css/isla.css"><link rel="stylesheet" href="../instancia/css/entradas.css">
</head><body><main class="pj-pagina">
{$cuerpo}
</main>
<script src="../instancia/js/entradas.js"></script>
</body></html>
HTML);

        // En reposo y en cada estado (lo que se pulsa, dónde se deja el puntero y si se reduce el movimiento).
        foreach (['390x844', '1280x900'] as $ventana) {
            foreach (['' => [], ...($pieza['estados'] ?? [])] as $estado => $pasos) {
                $lote[] = [
                    'nombre' => "{$zona}-{$nombre}-".strtok($ventana, 'x').($estado !== '' ? "-{$estado}" : ''),
                    'a' => "{$base}/a/{$zona}-{$nombre}.html",
                    'b' => "{$base}/b/{$zona}-{$nombre}.html",
                    'viewport' => $ventana,
                    'completa' => true,
                    'clics' => $pasos['clics'] ?? [],
                    ...(isset($pasos['pasar']) ? ['pasar' => $pasos['pasar'], 'movimiento' => $pasos['movimiento']] : []),
                ];
            }
        }
    }
}

file_put_contents($salida.'/lote.json', json_encode($lote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo '✓ '.count($lote).' pares en '.$salida."/lote.json\n";
