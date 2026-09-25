/**
 * SONDA DE LA PUERTA — el navegador real en el kiosco, con la ENCUESTA INTERNA delante
 * (`docs/specs/encuestas.md` §4.2 y §6, T2; `DECISIONES #740`, `#741`; el kiosco: `#232`, `#234`).
 *
 * Es el guion que va ANTES del ojo del owner: que la ficha abra, que la tarjeta de la encuesta salga DEBAJO de
 * «Hoy» y no encima del lector, que el campo del lector siga con el cursor (antes y después de la encuesta),
 * que cada botón de respuesta dé al dedo sus 44 px, que el formulario se pinte con las preguntas de la
 * encuesta viva, que la consola quede limpia; y deja las capturas de tablet y móvil para mirarlas.
 *
 * ── CÓMO SE CORRE ─────────────────────────────────────────────────────────────────────────────────────────
 *   Chromium en el contenedor (receta de la skill `sonda`), un admin local con `registrations.validate` y
 *   `puerta.profile`, y un cliente con la VISITA DE HOY acreditada (el escaneo la acredita, `#741`; con una
 *   búsqueda tecleada hay que acreditarla antes por tinker):
 *     docker compose exec -u sail -T -e SONDA_PANEL_EMAIL=… -e SONDA_PANEL_PASSWORD=… laravel.test \
 *         node scripts/sonda-puerta.mjs [etiqueta] [correo-o-carné-del-cliente]
 *   Base: `SONDA_BASE` o `http://localhost` (el `80` del contenedor). Cliente por defecto: la cuenta de sonda
 *   `probe-card@jumpweb.test`. Salida: `storage/app/audit/puerta-<etiqueta>.json` y las capturas al lado.
 *
 * ⚠️ Las credenciales van SOLO por entorno: no hay defecto y no viajan en el repo.
 * ⚠️ El login del panel tiene limitador: UNA sesión y se cambia el viewport dentro de ella. Y la búsqueda
 *    TECLEADA que abre la ficha tiene el suyo por hora: esta sonda la usa dos veces (tablet y móvil).
 * ⚠️ Capturas de VENTANA con el ratón apartado y tras esperar a que Livewire asiente; nunca `fullPage`.
 * ⚠️ No deja respuesta: abre el formulario, mide y lo cierra con «Ahora no». Si la encuesta ya estaba
 *    contestada por ese cliente, la tarjeta sale en «contestada» y la sonda lo dice en vez de medir el formulario.
 * ⚠️ CONTROL (`#435`): una línea de texto pequeña (`.gate-expires`) tiene que medir MENOS de 44 px, para
 *    demostrar que la medida discrimina y no aprueba todo.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const EMAIL = process.env.SONDA_PANEL_EMAIL ?? '';
const PASSWORD = process.env.SONDA_PANEL_PASSWORD ?? '';
const ETIQUETA = process.argv[2] ?? 'ojo';
const CLIENTE = process.argv[3] ?? 'probe-card@jumpweb.test';
const SALIDA = 'storage/app/audit';
const MIN_TACTIL = 44;

if (EMAIL === '' || PASSWORD === '') {
    console.error('SONDA_PANEL_EMAIL y SONDA_PANEL_PASSWORD son obligatorias (solo por entorno).');
    process.exit(2);
}

await mkdir(SALIDA, { recursive: true });

const informe = { base: BASE, etiqueta: ETIQUETA, cliente: CLIENTE, login: null, filas: [], capturas: [], consola: [] };
let fallos = 0;

function fila(check, ok, detalle = '') {
    informe.filas.push({ check, ok, detalle });
    if (! ok) fallos++;
    console.log(`${ok ? '✓' : '✗'} ${check}${detalle ? ' — ' + detalle : ''}`);
}

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1080, height: 810 }, locale: 'es-ES' });
const page = await context.newPage();
page.on('console', (m) => { if (m.type() === 'error') informe.consola.push(m.text()); });
page.on('pageerror', (e) => informe.consola.push(`pageerror: ${e.message}`));

async function asentar() {
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.mouse.move(2, 2);
    await page.waitForTimeout(450);
}

async function captura(nombre) {
    const ruta = `${SALIDA}/puerta-${ETIQUETA}-${nombre}.png`;
    await page.screenshot({ path: ruta });
    informe.capturas.push(ruta);
}

async function rect(selector) {
    return page.evaluate((s) => {
        const el = document.querySelector(s);
        if (! el) return null;
        const r = el.getBoundingClientRect();
        return { top: Math.round(r.top), bottom: Math.round(r.bottom), left: Math.round(r.left), width: Math.round(r.width), height: Math.round(r.height) };
    }, selector);
}

/** Todos los objetivos táctiles del formulario: las etiquetas-botón y los dos botones. */
async function objetivos() {
    return page.evaluate((min) => {
        const nodos = [...document.querySelectorAll('[data-gate-survey-form] .gate-q__btn, [data-gate-survey-save], [data-gate-survey-cancel]')];
        const filas = nodos.map((el) => {
            const r = el.getBoundingClientRect();
            return { texto: (el.textContent || '').trim().slice(0, 24), w: Math.round(r.width), h: Math.round(r.height) };
        });
        return { total: filas.length, cortos: filas.filter((f) => f.w < min || f.h < min), minH: Math.min(...filas.map((f) => f.h)), minW: Math.min(...filas.map((f) => f.w)) };
    }, MIN_TACTIL);
}

async function lectorLibre(momento) {
    const estado = await page.evaluate(() => {
        const input = document.getElementById('input');
        if (! input) return { existe: false };
        const r = input.getBoundingClientRect();
        const encima = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
        return {
            existe: true,
            enfocado: document.activeElement === input,
            visible: r.top >= 0 && r.bottom <= window.innerHeight,
            libre: encima === input,
            encima: encima ? `${encima.tagName.toLowerCase()}${encima.className ? '.' + String(encima.className).split(' ')[0] : ''}` : 'nada',
        };
    });
    fila(`lector: el campo existe (${momento})`, estado.existe === true);
    fila(`lector: nada lo tapa (${momento})`, estado.libre === true, estado.encima);
    fila(`lector: tiene el cursor (${momento})`, estado.enfocado === true);
    return estado;
}

async function buscar() {
    await page.fill('#input', CLIENTE);
    await page.press('#input', 'Enter');
    await page.waitForSelector('[data-gate-profile]', { timeout: 20000 });
    await asentar();
}

// ── Login: UNA vez ─────────────────────────────────────────────────────────────────────────────────────
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', EMAIL);
await page.fill('input[type="password"]', PASSWORD);
await Promise.all([
    page.waitForURL((u) => ! u.pathname.endsWith('/login'), { timeout: 20000 }),
    page.click('button[type="submit"]'),
]);
informe.login = page.url();
fila('login', ! page.url().endsWith('/login'), page.url());

// ── Tablet horizontal (el kiosco, `#232`): la ficha, la tarjeta y el lector ──────────────────────────
await page.goto(`${BASE}/admin/puerta/validar`, { waitUntil: 'networkidle' });
await asentar();
await lectorLibre('al abrir, tablet');

await buscar();
fila('la ficha abre', (await rect('[data-gate-holder]')) !== null);
fila('la visita de hoy está acreditada', (await rect('[data-gate-visit-badge]')) !== null, 'sin visita no hay oferta: acredítala antes (escaneo o tinker)');
await lectorLibre('con la ficha abierta, tablet');

const estadoEncuesta = await page.evaluate(() => document.querySelector('[data-gate-survey]')?.getAttribute('data-gate-survey') ?? null);
fila('la tarjeta de la encuesta está', estadoEncuesta !== null, `estado: ${estadoEncuesta ?? 'ninguna (¿hay una interna viva?)'}`);

// DEBAJO de «Hoy» (la primera sección de la columna principal) y nunca sobre el buscador.
const hoy = await rect('[data-gate-col="main"] > section:first-child, [data-gate-col="main"] > div:first-child');
const tarjeta = await rect('[data-gate-survey]');
const buscador = await rect('.gate-search');
if (tarjeta && hoy) fila('la tarjeta va DEBAJO de «Hoy»', tarjeta.top >= hoy.bottom - 1, `hoy.bottom=${hoy.bottom} tarjeta.top=${tarjeta.top}`);
if (tarjeta && buscador) fila('la tarjeta no pisa el buscador', tarjeta.top >= buscador.bottom, `buscador.bottom=${buscador.bottom} tarjeta.top=${tarjeta.top}`);
await captura('tablet-oferta');

// CONTROL: una línea pequeña mide MENOS de 44 px (la medida discrimina).
const expira = await rect('.gate-expires');
fila('CONTROL: `.gate-expires` mide < 44 px', expira !== null && expira.height < MIN_TACTIL, `alto=${expira?.height}`);
const btnBuscar = await rect('.gate-search__btn');
fila('CONTROL: el botón de buscar da ≥ 44 px (lo garantiza `GateKioskTest`)', btnBuscar !== null && btnBuscar.height >= MIN_TACTIL, `alto=${btnBuscar?.height}`);

// ── El formulario: cada respuesta al alcance del dedo ────────────────────────────────────────────────
if (estadoEncuesta === 'offer') {
    const abrir = await rect('[data-gate-survey-open]');
    const noPreguntar = await rect('[data-gate-survey-decline]');
    fila('«Preguntar» y «No preguntar» dan ≥ 44 px', abrir && noPreguntar && abrir.height >= MIN_TACTIL && noPreguntar.height >= MIN_TACTIL, `${abrir?.height} / ${noPreguntar?.height}`);

    await page.click('[data-gate-survey-open]');
    await page.waitForSelector('[data-gate-survey-form]', { timeout: 15000 });
    await asentar();
    const preguntas = await page.evaluate(() => [...document.querySelectorAll('[data-gate-question]')].map((q) => `${q.getAttribute('data-gate-question')}:${q.getAttribute('data-gate-question-type')}`));
    fila('el formulario pinta las preguntas de la encuesta viva', preguntas.length > 0, preguntas.join(' · '));
    const tactil = await objetivos();
    fila(`todos los objetivos del formulario dan ≥ ${MIN_TACTIL} px (tablet)`, tactil.total > 0 && tactil.cortos.length === 0, `${tactil.total} objetivos · mínimo ${tactil.minW}×${tactil.minH}${tactil.cortos.length ? ' · cortos: ' + JSON.stringify(tactil.cortos) : ''}`);
    await captura('tablet-formulario');

    // Marcar una escala y una opción para VER el estado «elegido» en la captura (no se guarda nada).
    await page.evaluate(() => {
        const primera = document.querySelector('[data-gate-survey-form] .gate-q__input');
        if (primera) { primera.click(); }
    });
    await asentar();
    await captura('tablet-marcado');

    // ── Móvil (390 px), en la MISMA sesión ─────────────────────────────────────────────────────────
    await page.setViewportSize({ width: 390, height: 844 });
    await asentar();
    const tactilMovil = await objetivos();
    fila(`todos los objetivos del formulario dan ≥ ${MIN_TACTIL} px (móvil)`, tactilMovil.total > 0 && tactilMovil.cortos.length === 0, `${tactilMovil.total} objetivos · mínimo ${tactilMovil.minW}×${tactilMovil.minH}`);
    const desbordaX = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    fila('sin desplazamiento horizontal en móvil', ! desbordaX);
    await page.evaluate(() => document.querySelector('[data-gate-survey-form]')?.scrollIntoView({ block: 'start' }));
    await asentar();
    await captura('movil-formulario');

    // Cerrar sin guardar: «Ahora no» devuelve la tarjeta a la oferta y el cursor al lector.
    await page.setViewportSize({ width: 1080, height: 810 });
    await page.click('[data-gate-survey-cancel]');
    await page.waitForSelector('[data-gate-survey="offer"]', { timeout: 15000 });
    await asentar();
    fila('«Ahora no» vuelve a la oferta sin guardar', (await page.evaluate(() => document.querySelector('[data-gate-survey]')?.getAttribute('data-gate-survey'))) === 'offer');
    await lectorLibre('tras cerrar la encuesta, tablet');
} else {
    fila('el formulario se mide', false, `la tarjeta no está en «offer» (${estadoEncuesta}): ese cliente ya contestó o declinó hoy`);
}

// «Nueva búsqueda»: la ficha se va de la pantalla (privacidad) y el cursor vuelve.
await page.click('.gate-foot__btn');
await asentar();
fila('«Nueva búsqueda» quita la ficha', (await rect('[data-gate-profile]')) === null);
await lectorLibre('tras «Nueva búsqueda», tablet');

fila('consola limpia', informe.consola.length === 0, informe.consola.slice(0, 3).join(' | '));

await browser.close();
await writeFile(`${SALIDA}/puerta-${ETIQUETA}.json`, JSON.stringify(informe, null, 2));
console.log(`▶ ${informe.filas.length - fallos}/${informe.filas.length} en verde · ${informe.capturas.length} capturas · ${SALIDA}/puerta-${ETIQUETA}.json`);
process.exit(fallos === 0 ? 0 : 1);
