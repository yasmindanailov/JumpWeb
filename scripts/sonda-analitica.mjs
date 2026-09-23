/**
 * SONDA DE LA ANALÍTICA — el navegador real recorriendo la landing y el cajón mientras `track.js` emite
 * (`docs/specs/analitica.md` §6, T1b). Es la mitad que ni la suite ni `node --test` pueden ver: que el trozo
 * DIFERIDO llegue de verdad tras `load`, que `IntersectionObserver` cuente secciones, que la cookie del
 * visitante que acuña el primer `202` viaje en el segundo lote, que `sendBeacon` salga al cambiar de página y
 * que la segunda vista NO repita la entrada.
 *
 * ⚠️ Versionada a propósito (`#475`): un instrumento que no viaja en el repo se reescribe, y con él sus trampas.
 *
 * ── QUÉ HACE ─────────────────────────────────────────────────────────────────────────────────────
 *   1. Entra en la portada con una campaña en la URL, espera el primer lote (5 s) y recorre la página para que
 *      las secciones cuenten.
 *   2. Abre el cajón por su abridor declarativo, espera al motor y lo cierra.
 *   3. Se va a otra página (el `pagehide` manda lo pendiente por `sendBeacon`) y espera su lote.
 *   Todo lo que entra y sale por `POST /api/v1/events` queda en el JSON: eventos con sus `props`, `meta`, el
 *   estado y lo aceptado/rechazado. Al final, una tabla de comprobaciones — y el código de salida las resume.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   Chromium en el contenedor (receta de la skill `sonda`), y después:
 *     docker compose exec -u sail -T laravel.test node scripts/sonda-analitica.mjs [etiqueta]
 *   Base: `SONDA_BASE` o `http://localhost:8081` (el puente `socat`). ⚠️ Esta sonda no necesita el puente: con
 *   `SONDA_BASE=http://localhost` pega al `80` del contenedor, porque `/events` es stateless y no depende del
 *   dominio de Sanctum.
 *   Salida: `storage/app/audit/analitica-<etiqueta>.json` (gitignorado).
 *
 * ⚠️ Playwright arranca con `navigator.webdriver = true`: los lotes tienen que decirlo en `meta.webdriver`, y
 * el servidor guarda la sesión como bot. Es lo que permite pasar esta sonda sin ensuciar el cuadro de mando.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost:8081';
const ETIQUETA = process.argv[2] ?? 'antes';
const SALIDA = 'storage/app/audit';
const LOTE_MS = 5000;

const lotes = [];
/** Qué respuesta de DOCUMENTO acuñó la cookie del visitante (`ResolveVisitor::MINT`, grupo `web`). */
const acunan = [];

/**
 * ⚠️ Dos trampas de Playwright, pagadas aquí: `request.headers()` NO trae la `Cookie` que el navegador añade
 * después (hace falta `allHeaders()`, que es asíncrona), y el cuerpo de una respuesta a un `fetch` con
 * `keepalive` puede no estar disponible. Por eso el registro se abre en el momento y se completa después, y lo
 * ACEPTADO se comprueba en el libro (tinker), no en esta salida.
 */
function registra(page) {
    page.on('request', (req) => {
        if (! req.url().includes('/api/v1/events')) return;

        let body = null;

        try {
            body = JSON.parse(req.postData() ?? 'null');
        } catch {
            body = { crudo: req.postData() };
        }

        const lote = { pagina: page.url(), tipo: req.resourceType(), cookie: null, body, respuesta: null };

        lotes.push(lote);
        req.allHeaders().then((h) => { lote.cookie = /visitor_id=/.test(h.cookie ?? ''); }, () => {});
    });
    page.on('response', (res) => {
        // ⚠️ `headers()` NO devuelve las cabeceras de cookies (lo dice su doc); `allHeaders()` sí, y es asíncrona.
        if (res.request().resourceType() === 'document') {
            res.allHeaders().then((h) => { if (/visitor_id=/.test(h['set-cookie'] ?? '')) acunan.push(res.url().replace(BASE, '')); }, () => {});
        }

        if (! res.url().includes('/api/v1/events')) return;

        const lote = lotes.findLast((l) => l.respuesta === null);

        if (! lote) return;

        lote.respuesta = { status: res.status(), acuna: null, cuerpo: null };
        res.allHeaders().then((h) => { lote.respuesta.acuna = /visitor_id=/.test(h['set-cookie'] ?? ''); }, () => {});
        res.text().then((t) => { lote.respuesta.cuerpo = t; }, () => { lote.respuesta.cuerpo = '(no disponible: keepalive)'; });
    });
}

const espera = (ms) => new Promise((r) => setTimeout(r, ms));

async function recorre(page) {
    const alto = await page.evaluate(() => document.documentElement.scrollHeight);

    for (let y = 0; y < alto; y += 500) {
        await page.evaluate((py) => window.scrollTo(0, py), y);
        await espera(650);
    }
}

async function abreYCierra(page) {
    // Primero los abridores declarativos del paquete; la landing del producto abre EN un producto desde las
    // tarjetas del raíl de tarifas (`$store.purchase.openWith(...)`), que es lo que `drawer_opened` lleva.
    const abridor = await page.$('[data-jw-open-product], [data-jw-open-zone], [data-jw-open], [\\@click*="openWith"], [\\@click*="purchase.open"]');

    if (! abridor) return 'sin abridor';

    await abridor.scrollIntoViewIfNeeded();
    await abridor.click();
    await page.waitForSelector('#sidecart-spa .purchase, #sidecart-spa [class*="catalog"], #sidecart-spa .sidebar', { timeout: 15000 }).catch(() => null);
    await espera(800);
    const cerrar = await page.$('.sidecart__close');

    if (cerrar) await cerrar.click();
    await espera(400);

    return 'abierto y cerrado';
}

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
const page = await context.newPage();
const consola = [];
const excepciones = [];

// Los errores de consola se APUNTAN, no juzgan: un `GET /me` → 401 al abrir el cajón como invitado es la
// comprobación de identidad normal y el navegador la imprime como error. Lo que sí juzga son las excepciones.
page.on('console', (m) => { if (m.type() === 'error') consola.push(m.text()); });
page.on('pageerror', (e) => excepciones.push(String(e)));
registra(page);

await page.goto(`${BASE}/?utm_source=sonda&utm_medium=navegador&utm_campaign=t1b&gclid=g-sonda-1`, { waitUntil: 'load' });
await espera(LOTE_MS + 1500);
await recorre(page);
const cajon = await abreYCierra(page);
await espera(LOTE_MS + 1500);

await page.goto(`${BASE}/entradas`, { waitUntil: 'load' });
await espera(LOTE_MS + 1500);

await browser.close();

const eventos = lotes.flatMap((l) => l.body?.events ?? []);
const nombres = eventos.map((e) => e.name);
const primera = eventos.find((e) => e.name === 'page_viewed');
const vistas = eventos.filter((e) => e.name === 'page_viewed');
const cuerpos = lotes.map((l) => l.respuesta?.cuerpo ?? '').filter((c) => c.startsWith('{'));
const rechazados = cuerpos.flatMap((c) => JSON.parse(c).rejected ?? []);

const comprobaciones = {
    'todos los lotes vuelven 202': lotes.length > 0 && lotes.every((l) => l.respuesta?.status === 202),
    'ningún evento rechazado (en los cuerpos legibles)': rechazados.length === 0,
    'la primera vista lleva entrada, campaña y click id': !! primera && primera.props.entry === '/' && primera.props.utm_source === 'sonda' && primera.props.gclid === 'g-sonda-1',
    'la página acuña la cookie del visitante y TODOS los lotes la llevan': acunan.length > 0 && lotes.every((l) => l.cookie === true),
    'alguna sección contó': nombres.includes('section_viewed'),
    'el cajón se abrió y se cerró': cajon === 'abierto y cerrado' && nombres.includes('drawer_opened') && nombres.includes('drawer_closed'),
    'la segunda página cuenta su vista SIN entrada, y el cajón que nace abierto dice por qué': vistas.length >= 2 && vistas.at(-1).route === '/entradas' && vistas.at(-1).props.entry === undefined && eventos.some((e) => e.name === 'drawer_opened' && e.props.reason === 'deeplink'),
    'los lotes dicen que es un navegador automatizado': lotes.every((l) => l.body?.meta?.webdriver === true),
    'sin excepciones de JS': excepciones.length === 0,
};

await mkdir(SALIDA, { recursive: true });
await writeFile(`${SALIDA}/analitica-${ETIQUETA}.json`, JSON.stringify({ base: BASE, etiqueta: ETIQUETA, comprobaciones, nombres, acunan, lotes, consola, excepciones }, null, 2));

console.log(`\nSONDA DE LA ANALÍTICA · ${ETIQUETA} · ${lotes.length} lotes · ${eventos.length} eventos · cookie acuñada por ${acunan.join(', ') || 'nadie'}`);
console.log('eventos:', nombres.join(', '));
for (const [nombre, ok] of Object.entries(comprobaciones)) console.log(`  ${ok ? '✔' : '✖'} ${nombre}`);
if (rechazados.length) console.log('rechazados:', JSON.stringify(rechazados));
if (consola.length) console.log('consola (se apunta, no juzga):', consola.join('\n  '));
if (excepciones.length) console.log('excepciones:', excepciones.join('\n  '));
console.log(`→ ${SALIDA}/analitica-${ETIQUETA}.json · lo ACEPTADO se lee en el libro (tinker), no aquí`);

process.exit(Object.values(comprobaciones).every(Boolean) ? 0 : 1);
