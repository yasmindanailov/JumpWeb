/**
 * **LA RAZÓN Y LA FRASE DE CADA PIEZA, EN VIVO** (Z6b·1 de `docs/specs/isla-y-landing-nueva.md` §4.27: la opción C «Da la
 * razón» del zip (6) y la frase de cada pieza, `#866`). La isla de cada página que vende, recorrida como la recorre una
 * persona —bajando a pasos y parando—, contra lo que la PÁGINA le declara (`#jw-isla-pagina`: `razones` y `frases` por
 * zona), no contra un texto escrito aquí:
 *   1. NADA AL LLEGAR: arriba del todo, con el botón de la cabecera a la vista, la isla no pone banner (manda la cabecera).
 *   2. LA RAZÓN: un banner solo con un botón de la página a la vista (`data-isla-cta`, fuera de la franja de la isla), y dice
 *      la razón de la pieza que cruza la línea media —o la de la llegada—, con su tipo y, la de tipo `razon`, con su icono
 *      (el dibujo que manda el servidor); la isla, en secundaria y SIN frase (una sola voz).
 *   3. SIN SATURAR: la de una pieza de DECISIÓN sale cada vez que su botón está a la vista; de las demás, una por visita.
 *   4. LA FRASE: sin botón a la vista, la frase que la isla dice es la de la pieza que se lee (o la de siempre); la de una
 *      pieza de decisión, siempre.
 *   5. ABRIRLA: tocar el banner abre la razón —su sobretítulo, el dato y el detalle— con la acción de la página en
 *      naranja; la X la cierra.
 *   6. Visítanos y Normas no dicen razones (no las declaran).
 * Solo lectura. Sale con 1 si algo falla; la foto de la razón abierta, en `storage/app/audit/razon-<ancho>-<página>.png`.
 * ⚠️ Cada página, en una visita NUEVA (el presupuesto de razones es por visita) y con el consentimiento ya decidido: el
 * aviso de cookies también ocupa la isla.
 *
 *   docker compose exec -u sail -T laravel.test node scripts/sonda-razon.mjs [390|1280]
 *   (con `-e SONDA_PAGINAS=/kids,/cumpleanos -e SONDA_APOYO=0`, solo esas: lo usa su arnés, `mutar-sonda-razon.sh`)
 */
/* global console, document, window -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { Buffer } from 'node:buffer';
import { URL } from 'node:url';
import { chromium } from 'playwright-core';
import { mkdir } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';

// Corridas seguidas (el arnés) agotan el suelo de la API de esta IP (`TESTING.md` §2.octies): se pone a cero al empezar.
execFileSync('php', ['artisan', 'tinker', '--execute', 'Illuminate\\Support\\Facades\\RateLimiter::clear(md5("api"."ip:127.0.0.1"));'], { encoding: 'utf8' });

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const SALIDA = 'storage/app/audit';
const POLITICA = '2026-09-24'; // CookieConsent::POLICY_VERSION
// Paso y pausa del recorrido: la pausa cubre lo que tarda una razón de decisión (1,2 s) más su asentado (0,25 s).
const PASO = 300;
const PAUSA = 1800;
// `SONDA_PAGINAS=/kids,/cumpleanos` y `SONDA_APOYO=0`: menos páginas, para el arnés (`mutar-sonda-razon.sh`).
const PAGINAS = (process.env.SONDA_PAGINAS ?? '/kids,/jump,/cumpleanos,/colegios,/').split(',');
const APOYO = process.env.SONDA_APOYO !== '0';
const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 190)}` : ''}`);
/** Los espacios, uno: «3 días» lleva espacio duro en los textos y la isla lo pinta tal cual (`\s` cubre el duro). */
const limpio = (s) => String(s ?? '').replace(/\s+/g, ' ').trim();
const errores = [];
const navegador = await chromium.launch();
await mkdir(SALIDA, { recursive: true });

async function visita(ruta) {
    const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 600 ? 844 : 900 }, locale: 'es-ES', deviceScaleFactor: 1 });
    await ctx.addCookies([{ name: 'cookie_consent', value: Buffer.from(JSON.stringify({ v: POLITICA, cats: {} })).toString('base64'), url: BASE }]);
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errores.push(`${ruta}: ${e.message}`));
    await page.route('http://localhost:8081/**', (r) => r.continue({ url: r.request().url().replace('localhost:8081', new URL(BASE).host) }));
    await page.goto(`${BASE}${ruta}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2500);
    await page.mouse.move(1, 1);

    return { ctx, page };
}

/** Lo que la isla enseña y lo que se ve de la página, con la geometría de `pagina.js` (`medirVista`, `zonaEnMedio`). */
const leer = (page) => page.evaluate(() => {
    const isla = document.querySelector('[data-situation]');
    const caja = isla?.getBoundingClientRect();
    const alto = window.innerHeight;
    let arriba = 0;
    let abajo = alto;
    if (caja && caja.height) { if (caja.top > alto / 2) abajo = caja.top; else arriba = caja.bottom; }
    const ve = (el) => { if (el.closest('[data-llegada="oculto"]')) return false; const r = el.getBoundingClientRect(); return r.height > 0 && r.bottom > arriba && r.top < abajo; };
    const media = alto / 2;
    const zona = Array.from(document.querySelectorAll('[data-zona]')).find((el) => { const r = el.getBoundingClientRect(); return r.top <= media && r.bottom > media; })?.dataset.zona ?? '';
    const bn = isla?.querySelector('[data-isla-razon]');
    const frases = Array.from(isla?.querySelectorAll('[aria-live="polite"]') ?? []).map((n) => n.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean);

    return {
        zona, cta: Array.from(document.querySelectorAll('[data-isla-cta]')).some(ve), tono: isla?.dataset.tono, abierta: isla?.dataset.size === 'abierta',
        banner: bn ? { tipo: bn.dataset.tipo, titulo: bn.querySelector('b')?.textContent.trim() ?? '', icono: Boolean(bn.querySelector('svg')) } : null, frase: frases[0] ?? null,
    };
});

for (const ruta of PAGINAS) {
    const { ctx, page } = await visita(ruta);
    try {
        await recorrer(ruta, page);
    } catch (e) {
        ok(`${ruta}: la sonda terminó sin excepciones`, false, e.message);
    } finally {
        await ctx.close();
    }
}

/** El recorrido de una página que vende: lo de arriba, del 1 al 5. */
async function recorrer(ruta, page) {
    const config = await page.evaluate(() => JSON.parse(document.getElementById('jw-isla-pagina')?.textContent ?? 'null')?.config ?? null);
    const razones = config?.razones ?? {};
    const frases = config?.frases ?? {};
    const razonDe = (zona) => razones[zona] ?? razones.llegada ?? null;
    ok(`${ruta}: la página declara sus razones`, Object.keys(razones).length > 0, Object.keys(razones).join(', '));

    // 1 · Nada al llegar.
    const llegada = await leer(page);
    ok(`${ruta}: al llegar, sin banner aunque se vea un botón`, llegada.banner === null, JSON.stringify(llegada));

    // 2–4 · El recorrido.
    const total = await page.evaluate(() => document.documentElement.scrollHeight);
    const vistas = new Set();
    let malos = 0;
    let decisiones = 0;
    let frasesBien = 0;
    let ocasiones = 0;
    let primeraAbierta = null;
    for (let y = PASO; y <= total; y += PASO) {
        await page.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), y);
        await page.waitForTimeout(PAUSA);
        const v = await leer(page);
        const esperada = razonDe(v.zona);
        if (v.banner) {
            // La razón, con su icono (el dibujo que manda el servidor); la viva, con su punto.
            const bien = v.cta && esperada && v.banner.titulo === esperada.text && v.tono === 'secundaria' && v.frase === null
                && v.banner.tipo === (esperada.type ?? 'razon') && (v.banner.tipo !== 'razon' || v.banner.icono);
            if (! bien) { malos += 1; ok(`${ruta} y=${y}: el banner dice la razón de su pieza, en secundaria y sin frase`, false, JSON.stringify({ v, esperada: esperada?.text })); }
            if (esperada && ! esperada.decision) vistas.add(esperada.text);
            if (bien && esperada.decision) decisiones += 1;
            // Para abrirla después se vuelve a esta altura: una de DECISIÓN, que sale cada vez (la otra, una por visita).
            if (! primeraAbierta && bien && esperada.decision) primeraAbierta = { y, esperada };
        } else {
            // Sin botón a la vista, ningún banner (y la acción, en principal); con él, la razón de una pieza de DECISIÓN sale siempre.
            if (v.cta && esperada?.decision) { malos += 1; ok(`${ruta} y=${y}: la razón de una pieza de decisión sale siempre`, false, JSON.stringify({ v, esperada: esperada.text })); }
            const frase = frases[v.zona];
            if (! v.cta && frase && frase.decision) {
                ocasiones += 1;
                if (limpio(v.frase) === limpio(frase.text)) frasesBien += 1;
                else { malos += 1; ok(`${ruta} y=${y}: la frase de una pieza de decisión sale siempre`, false, JSON.stringify({ v, esperada: frase.text })); }
            }
            // Una frase de OTRA pieza (dos piezas pueden decir la misma, como las dudas y el cierre: entonces es la suya).
            const deOtra = limpio(frases[v.zona]?.text) !== limpio(v.frase) && Object.entries(frases).find(([z, f]) => limpio(f.text) === limpio(v.frase) && z !== v.zona);
            if (deOtra) { malos += 1; ok(`${ruta} y=${y}: la frase es la de la pieza que se lee`, false, JSON.stringify({ v, de: deOtra[0] })); }
        }
    }
    ok(`${ruta}: cada paso del recorrido, en regla (el banner de su pieza, la frase de su pieza)`, malos === 0, `${malos} fuera de regla`);
    ok(`${ruta}: de las que no son de decisión, una por visita`, vistas.size <= 1, [...vistas].join(' | '));
    ok(`${ruta}: alguna razón de decisión dicha`, decisiones > 0, `${decisiones} pasos`);
    // Una frase de decisión, en cada paso en que su pieza se lee SIN botón a la vista (en escritorio, la calculadora de
    // Colegios tiene el suyo siempre a la vista: no hay ocasión, y no se cuenta como fallo).
    if (Object.values(frases).some((f) => f.decision)) ok(`${ruta}: las frases de decisión, dichas en cada ocasión`, frasesBien === ocasiones, `${frasesBien} de ${ocasiones} pasos`);

    // 5 · Abrir la razón.
    const hayBanner = primeraAbierta && await (async () => {
        await page.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), primeraAbierta.y);
        return page.waitForSelector('[data-isla-razon]', { timeout: 5000 }).then(() => true, () => false);
    })();
    if (primeraAbierta && ! hayBanner) ok(`${ruta}: la razón de decisión vuelve a salir con su botón`, false, `y=${primeraAbierta.y}`);
    if (hayBanner) {
        await page.waitForTimeout(400);
        await page.click('[data-isla-razon]');
        await page.waitForTimeout(1000);
        await page.mouse.move(1, 1);
        const abierta = await page.evaluate(() => {
            const isla = document.querySelector('[data-situation]');
            const dlg = isla?.querySelector('[role="dialog"]');
            const accion = isla?.querySelector('[data-isla-accion]');

            return { tamano: isla?.dataset.size, tono: isla?.dataset.tono, etiqueta: dlg?.getAttribute('aria-label') ?? '', texto: dlg?.textContent.replace(/\s+/g, ' ').trim() ?? '', accion: accion?.textContent.trim() ?? null, fondo: accion ? window.getComputedStyle(accion).backgroundColor : null, quieta: window.getComputedStyle(document.documentElement).getPropertyValue('--action-bg').trim() };
        });
        const r = primeraAbierta.esperada;
        const limpio = (s) => String(s ?? '').replace(/\s+/g, ' ').trim();
        ok(`${ruta}: tocarla abre la razón, con su sobretítulo, su dato y su detalle`, abierta.tamano === 'abierta' && abierta.texto.includes(limpio(r.text)) && (! r.title || abierta.texto.startsWith(limpio(r.title))) && (! r.detail || abierta.texto.includes(limpio(r.detail))), abierta.texto);
        ok(`${ruta}: abierta, la acción de la página en principal (naranja)`, abierta.tono === 'principal' && abierta.accion === config.page.action.label, `${abierta.accion} · ${abierta.fondo}`);
        await page.screenshot({ path: `${SALIDA}/razon-${ANCHO}${ruta === '/' ? '-portada' : ruta.replace('/', '-')}.png` });
        await page.click('[data-situation] button[aria-label="Cerrar"]');
        await page.waitForTimeout(1200);
        const cerrada = await leer(page);
        ok(`${ruta}: la X la cierra`, ! cerrada.abierta, JSON.stringify(cerrada));
    } else if (! primeraAbierta) {
        ok(`${ruta}: alguna razón de decisión se pudo abrir`, false, 'el recorrido no vio ninguna');
    }
}

// 6 · Las de apoyo no dicen razones.
for (const ruta of APOYO ? ['/visitanos', '/normas'] : []) {
    const { ctx, page } = await visita(ruta);
    let banners = 0;
    const total = await page.evaluate(() => document.documentElement.scrollHeight);
    for (let y = PASO * 2; y <= total; y += PASO * 2) {
        await page.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), y);
        await page.waitForTimeout(PAUSA);
        if ((await leer(page)).banner) banners += 1;
    }
    ok(`${ruta}: una página de apoyo no dice razones`, banners === 0, `${banners} banners`);
    await ctx.close();
}

ok('sin errores en la consola de las páginas', errores.length === 0, errores.join(' | '));
await navegador.close();
console.log(`sonda-razon · ${ANCHO}px`);
filas.forEach((f) => console.log(f));
const fallos = filas.filter((f) => f.startsWith('✗')).length;
console.log(`${filas.length - fallos}/${filas.length}`);
process.exit(fallos ? 1 : 0);
