/**
 * SONDA DEL CAJÓN — el OJO que la suite no tiene, dentro del panel de compra y de cuenta.
 *
 * Hermana de `scripts/sonda-geometria.mjs`, que solo llega a lo PÚBLICO: su propio docblock declara
 * que deja fuera «el paso de datos del cajón, el post-form y el justificante» (`#407`). Ésta entra.
 *
 * ⚠️ **Está versionada a propósito**, por la lección de `#475`: la sonda de la auditoría del cajón
 * (`#438`) vivía en `storage/app/`, está gitignorada y **ya no existe en esta máquina** — con ella se
 * fueron sus cien medidas y sus cinco trampas. Un instrumento que no viaja en el repo se reescribe, y
 * al reescribirlo se vuelven a pagar sus trampas.
 *
 * ── QUÉ MIDE, POR PANTALLA ───────────────────────────────────────────────────────────────────────
 *   · el TAMAÑO de letra COMPUTADO de cada nodo con texto propio (no el declarado en la hoja: el que
 *     el navegador resuelve tras la cascada), con su clase — es lo único que dice si la grieta 00
 *     está cerrada de verdad;
 *   · el REFLUJO, que es lo que puede romper subir el cuerpo: alto del contenido contra el del panel,
 *     desborde horizontal y textos RECORTADOS (`scrollWidth > clientWidth`);
 *   · los objetivos táctiles por debajo de `--tap-min`;
 *   · y una CAPTURA de ventana por pantalla.
 *
 * ⚠️ **Captura de VENTANA y no de elemento** (la trampa de `#303`, pagada dos veces): una captura de
 * elemento cose los `fixed` y enseña piezas que no están ahí.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   1. Chromium y el puente, una vez por contenedor:
 *        docker compose exec -u sail -T laravel.test node node_modules/playwright-core/cli.js install chromium
 *        docker compose exec -d -T laravel.test bash -lc 'socat TCP-LISTEN:8081,fork,reuseaddr TCP:127.0.0.1:80'
 *      ⚠️ El navegador se instala con la CLI del PROYECTO. Con `npx playwright install` cae en la caché
 *      de `npx` y el `playwright-core` de `node_modules` **no lo encuentra** (medido el 2026-09-11).
 *      ⚠️ `npm install` PODA `playwright-core` (va sin guardar): hay que reinstalarlo después.
 *   2. docker compose exec -u sail -T laravel.test node scripts/sonda-cajon.mjs antes
 *      … y tras el cambio: `node scripts/sonda-cajon.mjs despues`
 *
 * La etiqueta nombra la salida: `storage/app/audit/cajon-<etiqueta>.json` + las capturas. Esa carpeta
 * está gitignorada, así que las fotos no entran en el repo; el JSON es lo que se compara.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = 'http://localhost:8081';
const ETIQUETA = process.argv[2] ?? 'antes';
const SALIDA = 'storage/app/audit';

/** La cuenta de sonda (memoria del proyecto; no está en el repo ni en un seeder). */
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' };

const MOVIL = { width: 390, height: 844, hasTouch: true, isMobile: true };
const ESCRITORIO = { width: 1280, height: 900 };

/** El suelo del sistema: cuerpo 16 (móvil) y 17 (escritorio). Se mide contra 16. */
const SUELO = 16;

/**
 * Lo que se mide DENTRO del panel. Corre en el navegador.
 *
 * ⚠️ Solo se miran nodos con texto PROPIO (`childNodes` de tipo texto): contar el de un contenedor
 * atribuye a la caja el tamaño de sus hijos y sale ruido con cifras creíbles.
 */
const MEDIR = (suelo) => {
    const panel = document.querySelector('.sidecart__panel');
    if (! panel) return { error: 'sin panel' };

    const visible = (el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden';
    };
    const nombre = (el) => (el.className && typeof el.className === 'string'
        ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.')
        : el.tagName.toLowerCase());

    const pequenos = [];
    const recortados = [];
    let conTexto = 0;

    for (const el of panel.querySelectorAll('*')) {
        if (! visible(el)) continue;
        const propio = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim() !== '');
        if (! propio) continue;
        conTexto++;
        const px = parseFloat(getComputedStyle(el).fontSize);
        if (px < suelo) pequenos.push({ sel: nombre(el), px: Math.round(px * 10) / 10 });
        // Texto recortado por su caja: el modo de fallo de subir el cuerpo.
        if (el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).overflow !== 'visible') {
            recortados.push({ sel: nombre(el), contenido: el.scrollWidth, caja: el.clientWidth });
        }
    }

    // Objetivos táctiles: la caja del control, sin el pseudo-elemento que lo amplía (eso lo mide la
    // sonda pública, que ya paga esa trampa). Aquí basta para ver si el cambio los MUEVE.
    const tap = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--tap-min')) || 48;
    const cortos = [];
    for (const el of panel.querySelectorAll('button, a, input, select, summary, [role="button"]')) {
        if (! visible(el)) continue;
        const r = el.getBoundingClientRect();
        if (r.height + 0.5 < tap) cortos.push({ sel: nombre(el), alto: Math.round(r.height * 10) / 10 });
    }

    const scroll = panel.querySelector('.purchase__scroll') ?? panel;

    return {
        nodosConTexto: conTexto,
        pequenos,
        recortados,
        cortos,
        alto: { contenido: scroll.scrollHeight, caja: scroll.clientHeight },
        desbordeH: Math.max(0, panel.scrollWidth - panel.clientWidth),
    };
};

/**
 * Espera a que el motor SPA haya montado su árbol (`CE-6`: por CONDICIÓN, nunca por reloj).
 *
 * ⚠️ **El EMBUDO y la CUENTA no montan la misma raíz**: `.purchase[data-engine="spa"]` es la del
 * embudo, y el área de cliente es otra sección al lado (`#66`). Esperar la del embudo en
 * `/mi-cuenta` agota el tiempo con el cajón perfectamente abierto — la primera versión de esta
 * sonda perdió ahí las nueve pantallas de la cuenta, y el error solo decía «Timeout».
 */
const esperarCajon = (page, zona = 'embudo') => page.waitForSelector(
    zona === 'embudo'
        ? '.sidecart.is-open .purchase[data-engine="spa"]'
        : '.sidecart.is-open .acc-tiles, .sidecart.is-open #login-email',
    { timeout: 20000 },
);

/**
 * El aviso de cookies tapa el cajón entero en móvil (`#237`): se acepta antes de medir nada.
 *
 * ⚠️⚠️ **Su banner tiene NUEVE botones y seis están OCULTOS** (el panel de preferencias: «Rechazar
 * todo», «Guardar preferencias», «Aceptar todo»…). Un localizador por texto coge el primero que
 * case —oculto o no— y Playwright espera a que sea visible hasta agotar su tiempo: la primera
 * versión de esta sonda murió ahí, con un «Timeout 30000ms» que no decía en qué clic. Se acota al
 * banner, se filtra por VISIBLE y se le pone tiempo corto: si no hay banner, no pasa nada.
 */
async function aceptarCookies(page) {
    const banner = page.locator('.cookie-consent-root');

    try {
        await banner.waitFor({ state: 'visible', timeout: 3000 });
    } catch {
        return; // sin aviso: nada que aceptar
    }

    const boton = banner.locator('button:visible', { hasText: /^acept/i }).first();

    if (await boton.count()) {
        await boton.click({ timeout: 3000 });
        await banner.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {});
    }
}

async function recorrer(context, viewport, nombreViewport, informe) {
    const page = await context.newPage();
    await page.setViewportSize(viewport);

    // ⚠️ Tiempo CORTO y por acción: con el de por defecto (30 s) un fallo no dice dónde fue, que es
    // exactamente lo que costó la primera pasada. Con el paso anotado, el error se lee solo.
    page.setDefaultTimeout(12000);

    let paso = 'arranque';
    page.on('pageerror', (e) => console.error(`   JS en ${paso}: ${e.message.split('\n')[0]}`));

    /**
     * ⚠️⚠️ **LA ESPERA DEL VELO SUBE AQUÍ, Y NO ESTABA EN EL EMBUDO** (`#551`). La pasada de `#550` la
     * hacía solo en las zonas de CUENTA, así que las seis pantallas del embudo se medían —y se
     * fotografiaban— con el spinner puesto: la captura de «hora elegida» salía con el velo encima y
     * dentro de la medición entraban `.jj-spinner-label` (14 px) y `.jj-spinner__sr`, que está recortado
     * a 1 px **por diseño**. O sea defectos que no existen, con cifras creíbles.
     * ▶ Es la misma trampa que aquella tanda documentó para la cuenta, **pagada otra vez en el embudo**
     * por haber puesto la espera en el bucle y no en el instrumento.
     * ⚠️ Se espera por CONDICIÓN, nunca por reloj, y con `catch` porque hay pantallas que no cargan nada.
     */
    const medir = async (pantalla) => {
        paso = pantalla;
        await page.waitForSelector('.jj-loading', { state: 'hidden' }).catch(() => {});
        await page.waitForFunction(() => ! document.querySelector('.sidecart__panel .jj-spinner')).catch(() => {});
        // ⚠️⚠️ **Y el PANEL tiene que estar QUIETO** (`#552`). El cajón entra deslizándose, y esperar a
        // que exista un `.catalog__item` no es esperar a que haya terminado de abrirse: medido, en esa
        // ventana las filas salen más estrechas y aparecen «recortes» que no existen —dos
        // `.catalog__feat` de 68 en una caja de 55, cuando en reposo miden 124 de 124—.
        // ▶ Se espera **por CONDICIÓN**: que el ancho del panel sea el mismo en dos fotogramas
        // seguidos. Un reloj fijo volvería a medir antes de tiempo en la máquina que vaya más lenta.
        await page.waitForFunction(() => new Promise((listo) => {
            const panel = document.querySelector('.sidecart__panel');
            if (! panel) return listo(false);
            const antes = panel.getBoundingClientRect().width;
            requestAnimationFrame(() => requestAnimationFrame(() => {
                listo(Math.abs(panel.getBoundingClientRect().width - antes) < 0.5);
            }));
        })).catch(() => {});
        const datos = await page.evaluate(MEDIR, SUELO);
        informe.push({ pantalla, viewport: nombreViewport, ...datos });
        await page.screenshot({ path: `${SALIDA}/cajon-${ETIQUETA}/${pantalla}@${viewport.width}.png` });
        return datos;
    };

    // ── EL EMBUDO ────────────────────────────────────────────────────────────────────────────────
    // `/entradas` abre el cajón solo (`data-purchase-open="1"` con la venta online abierta).
    await page.goto(`${BASE}/entradas`, { waitUntil: 'domcontentloaded' });
    await aceptarCookies(page);
    await esperarCajon(page);
    await page.waitForSelector('.catalog__item');
    await medir('01-catalogo');

    await page.locator('.catalog__item').first().click();
    await page.waitForSelector('.daystrip__day, .purchase__empty');
    await medir('02-fecha');

    await page.locator('.daystrip__day').first().click();
    await page.waitForSelector('.purchase__chip');
    await medir('03-hora');

    await page.locator('.purchase__chip:not(.is-full)').first().click();
    await page.waitForSelector('.qtybox');
    await medir('04-hora-elegida');

    // «Añadir al carrito» es el CTA del pie; el carrito se abre desde la barra del catálogo.
    await page.locator('.bk-cta').click();
    await page.waitForSelector('.cartbar, .cart__item');
    if (await page.locator('.cartbar').count()) {
        await page.locator('.cartbar').click();
    }
    await page.waitForSelector('.cart__item, .purchase__empty');
    await medir('05-carrito');

    await page.locator('.bk-cta').click();
    await page.waitForSelector('.auth, .purchase__note');
    await medir('06-identificarse');

    // ── EL ÁREA DE CUENTA ────────────────────────────────────────────────────────────────────────
    await page.goto(`${BASE}/mi-cuenta`, { waitUntil: 'domcontentloaded' });
    await esperarCajon(page, 'cuenta');

    if (await page.locator('#login-email').count()) {
        paso = 'entrar';
        await page.fill('#login-email', CLIENTE.email);
        await page.fill('#login-password', CLIENTE.password);
        await page.click('.sidecart__panel button[type="submit"]');
    }

    await page.waitForSelector('.acc-tiles .acc-tile', { timeout: 20000 });
    await medir('10-cuenta-indice');

    const zonas = await page.locator('.acc-tile').count();
    for (let i = 0; i < zonas; i++) {
        await page.goto(`${BASE}/mi-cuenta`, { waitUntil: 'domcontentloaded' });
        await esperarCajon(page, 'cuenta');
        await page.waitForSelector('.acc-tiles .acc-tile');
        const tile = page.locator('.acc-tile').nth(i);
        const rotulo = (await tile.locator('.acc-tile__name').innerText()).trim().toLowerCase().replace(/[^a-z0-9]+/g, '-');
        await tile.click();

        // ⚠️⚠️ **Se espera a que el VELO se vaya, no a un reloj.** Con un `waitForTimeout` fijo la
        // medición cae a veces mientras la zona pide sus datos, y entonces lo que se mide es el
        // SPINNER: aparecen `.jj-spinner-label` (14 px) y un «recorte» de `.jj-spinner__sr`, que es
        // el texto para lectores de pantalla y está recortado a 1 px **por diseño**. Sale un defecto
        // que no existe, con cifras creíbles — y en la comparación antes/después parece una
        // regresión de la tanda. Medido: el índice de la cuenta daba 4 nodos pequeños y 0 recortes
        // en una pasada y 5 y 1 en la siguiente, sin tocar una línea de CSS entre medias.
        //
        // ⚠️⚠️ **Y el efecto grande no era ése: el reloj SUBESTIMABA.** Con la espera fija, media zona
        // se medía antes de pintarse —menos nodos, y los que se contaban eran los del velo—: el total
        // de las quince pantallas pasó de **181 a 207** nodos al esperar de verdad, y zonas enteras
        // cambiaron de cifra (privacidad 6 → 11, menores en escritorio 2 → 17). *Un instrumento que
        // mide antes de tiempo no falla: devuelve un número más bajo y igual de creíble.*
        // ▶ Comprobado con el reparto: los 207 están todos en 15 (Cuerpo S) y 12 (Etiqueta), cero por
        // debajo de 12 y cero recortes. Los cinco a 14 son `.jj-spinner-label`, de `spinner.css`.
        await page.waitForSelector('.jj-loading', { state: 'hidden' }).catch(() => {});
        await page.waitForFunction(() => ! document.querySelector('.sidecart__panel .jj-spinner')).catch(() => {});
        await medir(`1${i + 1}-zona-${rotulo}`);
    }

    await page.close();
}

const navegador = await chromium.launch();
const informe = [];

for (const [nombre, viewport] of [['movil', MOVIL], ['escritorio', ESCRITORIO]]) {
    const context = await navegador.newContext({ viewport, reducedMotion: 'no-preference' });
    await mkdir(`${SALIDA}/cajon-${ETIQUETA}`, { recursive: true });
    try {
        await recorrer(context, viewport, nombre, informe);
    } catch (e) {
        informe.push({ pantalla: 'ERROR', viewport: nombre, error: e.message.split('\n')[0] });
        console.error(`✗ ${nombre}: ${e.message.split('\n')[0]}`);
    }
    await context.close();
}

await navegador.close();
await writeFile(`${SALIDA}/cajon-${ETIQUETA}.json`, JSON.stringify(informe, null, 2));

// Resumen por pantalla: lo que se compara entre «antes» y «después».
console.log(`\n${'pantalla'.padEnd(26)} ${'vp'.padEnd(11)} ${'<16'.padStart(4)} ${'recortes'.padStart(8)} ${'tap<48'.padStart(7)} ${'alto'.padStart(9)}`);
for (const f of informe) {
    if (f.error) { console.log(`${f.pantalla.padEnd(26)} ${f.viewport.padEnd(11)} ${f.error}`); continue; }
    console.log(
        `${f.pantalla.padEnd(26)} ${f.viewport.padEnd(11)} ${String(f.pequenos.length).padStart(4)} ` +
        `${String(f.recortados.length).padStart(8)} ${String(f.cortos.length).padStart(7)} ` +
        `${String(f.alto.contenido + '/' + f.alto.caja).padStart(9)}`
    );
}
const total = informe.filter((f) => ! f.error).reduce((n, f) => n + f.pequenos.length, 0);
console.log(`\nnodos de texto por debajo de ${SUELO} px: ${total} · informe: ${SALIDA}/cajon-${ETIQUETA}.json`);
