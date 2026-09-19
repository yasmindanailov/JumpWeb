/**
 * HUELLA DE MAQUETACIÓN — el juez de un cambio que promete «no mover un píxel».
 *
 * ⚠️⚠️ **Se escribe ahora porque el original NO existe, y eso es la lección de `#475`.** `DECISIONES #437`
 * convirtió 662 literales a token y dejó escrito que «cero reflujo» se midió con «la huella de maquetación de
 * las doce vistas a 1280 y 390, idéntica en geometría en las 24». Ese instrumento no viajaba en el repo: se
 * perdió, y con él la capacidad de repetir la medida. Éste viaja.
 *
 * ── QUÉ ES UNA HUELLA ────────────────────────────────────────────────────────────────────────────
 * Por cada vista y cada ancho, un renglón por ELEMENTO del documento con:
 *   · una CLAVE estable — el camino desde la raíz (`html>body>div:3>p:0`), que no cambia mientras no cambie
 *     el DOM. Un cambio de CSS no lo cambia, y por eso sirve de juez para la T4;
 *   · su RECTÁNGULO redondeado a entero (x, y, ancho, alto);
 *   · su FIRMA de estilo computado: familia, tamaño, peso, interlineado, tracking, color, fondo y borde.
 *
 * Dos huellas se comparan clave a clave. Un cambio que promete no mover nada tiene que dar CERO diferencias;
 * una diferencia es un renglón con lo que había y lo que hay.
 *
 * ⚠️ **Cubre también el CAJÓN**, al revés que la huella de `#437`: la T4 le parte la hoja de estilos, así que
 * mirar solo lo público mediría justo lo que no cambia.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   docker compose exec -d -T laravel.test bash -lc 'socat TCP-LISTEN:8081,fork,reuseaddr TCP:127.0.0.1:80'
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/huella-maquetacion.mjs antes
 *   …se hace el cambio…
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/huella-maquetacion.mjs despues
 *   docker compose exec -u sail -T laravel.test node scripts/huella-maquetacion.mjs --comparar antes despues
 *
 * Sale con código 1 si la comparación encuentra diferencias, o si una pasada no midió nada.
 *
 * ── LAS TRAMPAS, TODAS PAGADAS EN ESTE REPO ─────────────────────────────────────────────────────
 *  1. **Las fuentes** (`#323`): medir antes de `document.fonts.ready` mide la de respaldo y devuelve otros
 *     altos. Se espera.
 *  2. **El puntero virtual arranca en (0,0)** (`#478`) y deja la primera pieza en `:hover`. Se aparta.
 *  3. **Y apartarlo no basta: la transición TERMINA** (`#554`, `#565`). Un color a medio camino es tan falso
 *     como el del estado equivocado.
 *  4. **El banner de cookies es `fixed`**: no cambia el alto del documento pero sí tapa y se pinta. Se DECIDE
 *     y se dice: aquí se acepta con consentimiento dado, para medir la web que ve un visitante que ya decidió.
 *  5. **El movimiento se congela** (`reducedMotion`), o dos pasadas de la misma página no coinciden.
 *  6. **El limitador de la API** (`throttle:api`, 60/min por IP) **agota una pasada entera**: medido el
 *     2026-09-18, la primera versión de este guion recibió 12 respuestas 429 y el control de estabilidad dio
 *     611 nodos distintos entre DOS pasadas idénticas — el catálogo llegaba vacío. No se toca el producto para
 *     medirlo: el guion se pone RITMO (cuenta sus propias peticiones a `/api/v1` y espera antes de pasar del
 *     umbral). Aun así se cuentan los 429, y una pasada con alguno NO vale.
 */
import { chromium } from 'playwright-core';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const BASE = 'http://localhost:8081';
const SALIDA = 'storage/app/audit';

/** Las vistas públicas, las mismas que recorre `sonda-geometria.mjs`. */
const VISTAS = [
    '/', '/entradas', '/precios', '/cumpleanos', '/servicios', '/normas', '/contacto', '/bar',
    '/cookies', '/privacidad', '/aviso-legal', '/condiciones', '/login', '/registro',
];

/** Y el CAJÓN, que es lo que la T4 toca. Cada una abre por su vía y espera a que el motor pinte. */
const CAJON = [
    { nombre: 'cajon:catalogo', ruta: '/entradas' },
    { nombre: 'cajon:cuenta', ruta: '/mi-cuenta' },
    { nombre: 'cajon:alta', ruta: '/registro' },
];

const MOVIL = { width: 390, height: 844 };
const ESCRITORIO = { width: 1280, height: 900 };

/** Lo que se mira de cada elemento. Rectángulo y lo que un cambio de hoja puede mover sin romper nada. */
const FIRMA = [
    'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing',
    'color', 'background-color', 'border-top-width', 'border-radius', 'display',
    'padding-top', 'padding-left', 'margin-top', 'gap', 'visibility',
];

const huellaDeLaPagina = (props) => (page) => page.evaluate((campos) => {
    const clave = (el) => {
        const partes = [];
        for (let n = el; n && n.nodeType === 1 && n !== document.documentElement; n = n.parentElement) {
            const i = [...n.parentElement.children].indexOf(n);
            partes.unshift(`${n.tagName.toLowerCase()}:${i}`);
        }

        return partes.join('>');
    };

    const filas = {};
    for (const el of document.querySelectorAll('*')) {
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        const caja = [r.x, r.y, r.width, r.height].map(Math.round).join(',');
        filas[clave(el)] = `${caja}|${campos.map((c) => cs.getPropertyValue(c)).join('|')}`;
    }

    return filas;
}, props);

/**
 * **El RITMO** (trampa 6): el limitador de la API deja 60 peticiones por minuto y por IP, y una pasada entera
 * se las come. Se apuntan las propias y, antes de abrir una página, se espera a que la ventana de 60 s baje del
 * umbral. Lento a propósito: una huella que mide un catálogo vacío no mide nada.
 */
const UMBRAL = 40;
const pedidas = [];

const apuntar = (url) => { if (url.includes('/api/v1/')) pedidas.push(Date.now()); };

async function conRitmo() {
    for (;;) {
        const corte = Date.now() - 60_000;
        while (pedidas.length && pedidas[0] < corte) pedidas.shift();
        if (pedidas.length < UMBRAL) return;

        await new Promise((r) => setTimeout(r, 2_000));
    }
}

/** Asentar la página antes de medir: fuentes, ratón fuera y transiciones terminadas (ver las trampas). */
async function asentar(page) {
    await page.evaluate(() => document.fonts.ready);
    await page.mouse.move(2, 2);
    await page.waitForTimeout(500);
}

async function medirVista(context, ruta, viewport, nombre, huella, limitadas) {
    await conRitmo();
    const page = await context.newPage();
    page.on('request', (r) => apuntar(r.url()));
    page.on('response', (r) => { if (r.status() === 429) limitadas.n += 1; });
    await page.setViewportSize(viewport);
    // Consentimiento dado: se mide la web de quien ya decidió, no la del banner (trampa 4).
    await page.context().addCookies([{ name: 'cookie_consent', value: '1', url: BASE }]);
    await page.goto(`${BASE}${ruta}`, { waitUntil: 'load' });
    await asentar(page);
    huella[`${nombre}@${viewport.width}`] = await huellaDeLaPagina(FIRMA)(page);
    await page.close();
}

async function medirCajon(context, caso, viewport, huella, limitadas) {
    await conRitmo();
    const page = await context.newPage();
    page.on('request', (r) => apuntar(r.url()));
    page.on('response', (r) => { if (r.status() === 429) limitadas.n += 1; });
    await page.setViewportSize(viewport);
    await page.context().addCookies([{ name: 'cookie_consent', value: '1', url: BASE }]);
    await page.goto(`${BASE}${caso.ruta}`, { waitUntil: 'load' });
    await page.waitForFunction(() => !! window.JumpWeb?.cajon, null, { timeout: 10000 });
    await page.evaluate(() => window.JumpWeb.cajon.open());
    await page.waitForFunction(() => document.querySelector('.sidecart')?.classList.contains('is-open'), null, { timeout: 8000 });
    // El motor monta con `import()` y luego pide lo suyo: se espera a que haya algo con que operar.
    await page.waitForFunction(() => document.querySelectorAll('#sidecart-spa button, #sidecart-spa a').length > 0, null, { timeout: 20000 });
    await asentar(page);
    huella[`${caso.nombre}@${viewport.width}`] = await huellaDeLaPagina(FIRMA)(page);
    await page.close();
}

async function comparar(a, b) {
    const antes = JSON.parse(await readFile(`${SALIDA}/huella-${a}.json`, 'utf8'));
    const despues = JSON.parse(await readFile(`${SALIDA}/huella-${b}.json`, 'utf8'));
    const pantallas = [...new Set([...Object.keys(antes), ...Object.keys(despues)])].sort();
    let difs = 0;

    for (const p of pantallas) {
        const x = antes[p] ?? {};
        const y = despues[p] ?? {};
        const claves = [...new Set([...Object.keys(x), ...Object.keys(y)])];
        const malas = claves.filter((k) => x[k] !== y[k]);

        console.log(`${malas.length === 0 ? '✓' : '✗'} ${p.padEnd(26)} ${String(claves.length).padStart(5)} nodos · ${malas.length} distintos`);
        for (const k of malas.slice(0, 6)) {
            console.log(`      ${k}\n        antes:  ${x[k] ?? '(no estaba)'}\n        ahora:  ${y[k] ?? '(ya no está)'}`);
        }
        if (malas.length > 6) console.log(`      … y ${malas.length - 6} más`);
        difs += malas.length;
    }

    console.log(`\n${difs === 0 ? '✓ IDÉNTICAS' : `✗ ${difs} nodos distintos`} · ${pantallas.length} pantallas`);

    return difs === 0 && pantallas.length > 0 ? 0 : 1;
}

/**
 * **EL JUEZ DE LA T4**: el cajón con `site.css` contra el cajón con `cajon.css`, en la MISMA página.
 *
 * `cajon.css` se genera desde `site.css` (`scripts/hoja-del-cajon.py`) para que una landing ajena no cargue las
 * 1.352 reglas de la landing. Lo que hay que demostrar no es que la hoja se generó, sino que **el cajón se ve
 * igual con ella**: se mide su subárbol servido como siempre, se cambia la hoja en caliente y se vuelve a
 * medir. Cero diferencias o la extracción se dejó algo.
 *
 * ⚠️ Se mide SOLO el subárbol `.sidecart`: al quitar `site.css` la landing de detrás se queda sin estilo, que
 * es justamente lo que pasa en una página ajena y no es lo que se juzga aquí.
 */
/** Las hojas del PRODUCTO que una página ajena no carga. `client.css` y las fuentes sí: están en el contrato. */
const HOJAS_DEL_PRODUCTO = ['site.css', 'landing.css', 'spinner.css'];

async function juzgarHojaDelCajon(navegador) {
    const subarbol = (page) => page.evaluate((campos) => {
        const raiz = document.querySelector('.sidecart');
        const clave = (el) => {
            const partes = [];
            for (let n = el; n && n !== raiz; n = n.parentElement) {
                partes.unshift(`${n.tagName.toLowerCase()}:${[...n.parentElement.children].indexOf(n)}`);
            }

            return partes.join('>');
        };
        const filas = {};
        for (const el of [raiz, ...raiz.querySelectorAll('*')]) {
            const r = el.getBoundingClientRect();
            const cs = getComputedStyle(el);
            filas[clave(el)] = `${[r.x, r.y, r.width, r.height].map(Math.round).join(',')}|${campos.map((c) => cs.getPropertyValue(c)).join('|')}`;
        }

        return filas;
    }, FIRMA);

    let difs = 0;

    for (const viewport of [MOVIL, ESCRITORIO]) {
        for (const caso of CAJON) {
            await conRitmo();
            const context = await navegador.newContext({ viewport, reducedMotion: 'reduce' });
            const page = await context.newPage();
            page.on('request', (r) => apuntar(r.url()));
            await page.goto(`${BASE}${caso.ruta}`, { waitUntil: 'load' });
            await page.waitForFunction(() => !! window.JumpWeb?.cajon, null, { timeout: 10000 });
            await page.evaluate(() => window.JumpWeb.cajon.open());
            await page.waitForFunction(() => document.querySelectorAll('#sidecart-spa button, #sidecart-spa a').length > 0, null, { timeout: 20000 });
            await asentar(page);

            const conSite = await subarbol(page);

            // Se cambian las hojas EN CALIENTE y se espera a que el navegador las aplique.
            // ⚠️⚠️ **Se retiran LAS TRES hojas del producto, no solo `site.css`.** Mientras el juez quitaba
            // únicamente ésa, el `:root` de `landing.css` seguía en la página sirviendo los 116 tokens del
            // sistema: la hoja del paquete parecía autosuficiente y no se estaba comprobando. Lo que queda es
            // exactamente lo que una landing ajena carga —fuentes, `client.css` y el paquete—, que es la
            // promesa de §4.1: dos líneas.
            const retiradas = await page.evaluate(async (hojas) => {
                const nueva = document.createElement('link');
                nueva.rel = 'stylesheet';
                nueva.href = `/css/cajon.css?v=${document.querySelectorAll('link').length}`;
                await new Promise((ok, mal) => { nueva.onload = ok; nueva.onerror = mal; document.head.append(nueva); });

                const viejas = [...document.querySelectorAll('link[rel=stylesheet]')]
                    .filter((l) => hojas.some((h) => l.href.includes(`/css/${h}`)));
                viejas.forEach((l) => l.remove());

                return viejas.length;
            }, HOJAS_DEL_PRODUCTO);

            // Un juez que no retira nada aprueba siempre. Aquí ya pasó con `site.css` sola.
            if (retiradas !== HOJAS_DEL_PRODUCTO.length) {
                console.log(`✗ ${caso.nombre}@${viewport.width}: se retiraron ${retiradas} hojas de ${HOJAS_DEL_PRODUCTO.length}; el juez no está juzgando.`);
                difs += 1;
            }
            await asentar(page);

            const conCajon = await subarbol(page);
            const claves = [...new Set([...Object.keys(conSite), ...Object.keys(conCajon)])];
            const malas = claves.filter((k) => conSite[k] !== conCajon[k]);

            console.log(`${malas.length === 0 ? '✓' : '✗'} ${caso.nombre}@${viewport.width}`.padEnd(30) + `${claves.length} nodos · ${malas.length} distintos`);
            for (const k of malas.slice(0, 5)) {
                console.log(`      ${k}\n        site.css :  ${conSite[k]}\n        cajon.css:  ${conCajon[k]}`);
            }
            if (malas.length > 5) console.log(`      … y ${malas.length - 5} más`);
            difs += malas.length;
            await context.close();
        }
    }

    console.log(`\n${difs === 0 ? '✓ el cajón se ve IGUAL con las dos hojas' : `✗ ${difs} nodos distintos`}`);

    return difs === 0 ? 0 : 1;
}

const [modo, ...resto] = process.argv.slice(2);

if (modo === '--comparar') {
    process.exit(await comparar(resto[0], resto[1]));
}

if (modo === '--cajon') {
    const nav = await chromium.launch();
    try {
        process.exit(await juzgarHojaDelCajon(nav));
    } finally {
        await nav.close();
    }
}

const ETIQUETA = modo || 'huella';
const navegador = await chromium.launch();
const huella = {};
const limitadas = { n: 0 };

try {
    for (const viewport of [MOVIL, ESCRITORIO]) {
        const context = await navegador.newContext({ viewport, reducedMotion: 'reduce' });

        for (const ruta of VISTAS) await medirVista(context, ruta, viewport, ruta, huella, limitadas);
        for (const caso of CAJON) await medirCajon(context, caso, viewport, huella, limitadas);

        await context.close();
    }
} finally {
    await navegador.close();
}

await mkdir(SALIDA, { recursive: true });
await writeFile(`${SALIDA}/huella-${ETIQUETA}.json`, JSON.stringify(huella));

const pantallas = Object.keys(huella).length;
const nodos = Object.values(huella).reduce((n, p) => n + Object.keys(p).length, 0);
console.log(`${pantallas} pantallas · ${nodos} nodos · ${SALIDA}/huella-${ETIQUETA}.json`);
if (limitadas.n) console.log(`⚠️ ${limitadas.n} respuestas 429: la pasada NO vale, espera un minuto y repite.`);

process.exit(pantallas === (VISTAS.length + CAJON.length) * 2 && limitadas.n === 0 ? 0 : 1);
