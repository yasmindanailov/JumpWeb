/**
 * SONDA DE LA PRIMERA PANTALLA — la cabecera y la isla en una sola retícula (`docs/specs/isla-y-landing-nueva.md`
 * §4.15, `DECISIONES #789`). Es el comprobador del diseño, `guidelines/primera-pantalla.html` del zip (4), PORTADO tal
 * cual: sus 15 pantallas (6 móviles con las barras del navegador a la vista, la tableta, el móvil en horizontal y 7
 * escritorios), sus dos visitas (primera, con el aviso de cookies; vuelta, sin él), sus dos momentos (la llegada y tras
 * el primer scroll: bajar 2px, esperar el fundido y volver) y sus reglas, con los mismos nombres y la misma holgura
 * (0,5px). Solo cambia CÓMO encuentra las piezas, porque nuestro DOM no es el del mockup: la cabecera es
 * `section[data-layout]` o `section.pj-vh` (su forma la da `@container`, así que se lee de la maqueta), el envoltorio
 * `.pj-wrap` y la sección `section.pj-sec`; y los envoltorios `display: contents` de la cabecera se abren en sus hijos
 * (en el mockup esos bloques son hijos directos).
 *
 * ▶ CONTROL: el lado `diseno` mide el mockup con el mismo código; lo que el mockup no cumple no se le exige a la web.
 * Medido el 27-09: la guía original en el navegador y este port dan las MISMAS cifras en el mockup de Kids (114/114 y
 * 45/45 en la vuelta; 102/111 con los mismos nueve fallos en la primera visita: el aviso de cookies se abre encima).
 * ▶ `comparar` mide los dos lados y sale con 1 si UNA regla, en una pantalla, visita y momento, no da el mismo veredicto
 * que en el mockup: es la guarda («idéntico» = el mismo veredicto, regla a regla).
 *
 *   docker compose exec -u sail -d laravel.test php -S 127.0.0.1:8129 -t /var/www/instancias/playjump/diseno/playjump-design-system
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-primera-pantalla.mjs [comparar|producto|diseno] [kids,jump] [0,1]
 *
 * La visita: `1` = primera (sin decidir las cookies), `0` = vuelta (la cookie `cookie_consent` de la política vigente, sin
 * nada aceptado; en el mockup, `?cookies=0`). El bote de «hay más abajo» se da por hecho, como en la guía (si bota a mitad
 * de una medida, la mueve). Solo lectura. Escribe `storage/app/audit/sonda-primera-pantalla-<modo>.json` y sale con 1 si
 * alguna regla falla. ⚠️ Playwright oculta las barras de desplazamiento (`--hide-scrollbars`); la guía solo las quita por
 * debajo de 1024px: en escritorio, `clientWidth` difiere en su ancho, y las reglas comparan aires, no anchos.
 */
import { chromium } from 'playwright-core';
import { mkdirSync, writeFileSync } from 'node:fs';

const MODO = process.argv[2] ?? 'comparar';
const LADOS = MODO === 'comparar' ? ['diseno', 'producto'] : [MODO];
const PAGINAS = (process.argv[3] ?? 'kids,jump').split(',');
const VISITAS = (process.argv[4] ?? '0,1').split(',');
const BASES = { diseno: process.env.DISENO_BASE ?? 'http://127.0.0.1:8129', producto: process.env.SONDA_BASE ?? 'http://localhost' };
/** Las siete páginas del diseño (`PAGINAS` de la guía): su ficha y su ruta en la web. Una página nueva de la T6, aquí. */
const PAGINA = {
    kids: ['kids', '/kids'], jump: ['jump', '/jump'], portada: ['portada', '/'], cumpleanos: ['cumpleanos', '/cumpleanos'],
    colegios: ['colegios', '/colegios'], visitanos: ['visitanos', '/visitanos'], normas: ['normas', '/normas'],
};
const url = (lado, p, v) => {
    const [ficha, ruta] = PAGINA[p] ?? [p, `/${p}`];

    return lado === 'diseno' ? `${BASES.diseno}/paginas/${ficha}.card.html?cookies=${v}` : `${BASES.producto}${ruta}`;
};
const POLITICA = '2026-09-24'; // CookieConsent::POLICY_VERSION
const PARALELO = Number(process.env.PARALELO ?? 3);

/** Las de la guía, en su orden: [grupo, nombre, ancho, alto visible]. */
const PANTALLAS = [
    ['movil', 'Android pequeño', 360, 560], ['movil', 'iPhone SE', 375, 553], ['movil', 'iPhone 12–14', 390, 664], ['movil', 'iPhone 15–16', 393, 659], ['movil', 'iPhone Pro Max', 430, 746], ['movil', 'Pixel · Chrome', 412, 835],
    ['ancho', 'iPad Air vertical', 820, 1106], ['ancho', 'iPhone en horizontal', 844, 340], ['ancho', 'Ventana baja', 1280, 560], ['ancho', 'Portátil 1366', 1366, 657], ['ancho', 'Portátil 1280', 1280, 689], ['ancho', 'Windows 1536 (125 %)', 1536, 730], ['ancho', 'MacBook Air', 1440, 789], ['ancho', 'Full HD', 1920, 960], ['ancho', 'QHD', 2560, 1300],
];
const BOTES = ['jump', 'kids', 'cumpleanos', 'portada', 'colegios', 'visitanos', 'normas'];

/** `medir()` de la guía, en la página. Devuelve lo que `reglas()` necesita, o `null` si no hay isla o cabecera. */
function medir({ Hv }) {
    const d = document;
    const r1 = (n) => Math.round(n * 2) / 2;
    const caja = (el) => { if (!el) return null; const r = el.getBoundingClientRect(); return { x: r1(r.left), y: r1(r.top), w: r1(r.width), h: r1(r.height), r: r1(r.right), b: r1(r.bottom) }; };
    const naranja = (el) => { const c = getComputedStyle(el).backgroundColor.match(/\d+(\.\d+)?/g); if (!c) return false; const [r, g, b, a] = c.map(Number); return (a === undefined || a > 0.5) && r > 220 && g > 70 && g < 175 && b < 100; };
    const nombre = (el) => { const t = (el.textContent || '').replace(/\s+/g, ' ').trim(); return el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.split(' ')[0] : '') + (t ? ' «' + t.slice(0, 28) + (t.length > 28 ? '…' : '') + '»' : ''); };
    /* Los envoltorios `display: contents` no tienen caja: cuentan sus hijos (en el mockup son hijos directos). */
    const abrir = (els) => els.flatMap((el) => (getComputedStyle(el).display === 'contents' ? abrir(Array.from(el.children)) : [el]));
    const banda = d.querySelector('[data-situation]');
    const isla = banda && banda.querySelector('[data-surface="ink"]');
    const heroe = d.querySelector('section[data-layout]') || d.querySelector('section.pj-vh');
    if (!isla || !heroe) return null;
    const medio = heroe.querySelector(':scope > .pj-vh__medio');
    const layout = heroe.dataset.layout || (medio && getComputedStyle(medio).position === 'absolute' ? 'overlay' : 'stack');
    const I = caja(isla), H = caja(heroe);
    const vw = d.documentElement.clientWidth, vh = Hv;
    const arriba = I.y < vh / 2;
    const envolt = heroe.closest('.wrap, .q1-wrap, .pj-container, .pj-wrap');
    const gutter = envolt ? parseFloat(getComputedStyle(envolt).paddingLeft) : null;
    const islaH = parseFloat(getComputedStyle(d.documentElement).getPropertyValue('--island-h')) || I.h;
    const tope = arriba ? I.b : 0, pliegue = arriba ? vh : r1(I.b - islaH);
    const oculto = (el) => !!(el && el.closest('[data-llegada="oculto"]'));
    const botones = (raiz) => Array.from(raiz.querySelectorAll('a, button')).filter((b) => naranja(b) && b.getBoundingClientRect().height > 0);
    const ctaH = botones(heroe)[0] || null, C = caja(ctaH);
    const ctaHVisible = !!C && !oculto(ctaH) && C.y >= tope && C.b <= pliegue;
    const ctaI = botones(isla)[0] || null;
    const texto = heroe.lastElementChild;
    const bloques = abrir(Array.from(texto ? texto.children : [])).filter((el) => el.getBoundingClientRect().height > 0);
    const sec = heroe.closest('section.sec, section.pj-sec') || heroe.parentElement;
    const siguientes = [];
    let n = heroe; while (n && n.parentElement && n.parentElement !== sec) { let s = n.nextElementSibling; while (s) { siguientes.push(s); s = s.nextElementSibling; } n = n.parentElement; }
    const secSig = sec && sec.nextElementSibling ? [sec.nextElementSibling] : [];
    const cortes = [];
    const mira = (el, lado) => { const b = caja(el); if (!b || b.h === 0 || oculto(el)) return; if (b.y < pliegue - 0.5 && b.b > pliegue + 0.5) cortes.push({ que: nombre(el), b, lado }); };
    bloques.forEach((el) => mira(el, 'heroe'));
    if (arriba) siguientes.concat(secSig).forEach((el) => mira(el, 'debajo'));
    const h1 = heroe.querySelector('h1'), T1 = caja(h1);
    const tituloEntero = !!T1 && !oculto(h1) && T1.y >= tope - 0.5 && T1.b <= (arriba ? vh : pliegue - 16) + 0.5;
    const ultimo = bloques.filter((el) => !oculto(el) && el.getBoundingClientRect().bottom <= pliegue + 0.5).pop();
    const meta = d.querySelector('meta[name="viewport"]');
    return {
        arriba, layout, vw, vh, I, H, C, gutter,
        ctaHVisible, ctaIsla: !!ctaI, cortes,
        huecoPliegue: ultimo ? r1(pliegue - ultimo.getBoundingClientRect().bottom) : null,
        ultimo: ultimo ? nombre(ultimo) : null,
        meta: meta ? meta.content : null,
        Iy: arriba ? I.y : pliegue, tituloEntero,
        ocultos: d.querySelectorAll('[data-llegada="oculto"]').length,
        asoma: arriba ? [] : bloques.concat(siguientes, secSig).filter((el) => { if (oculto(el)) return false; const b = el.getBoundingClientRect(); return b.height > 0 && b.top < vh && b.bottom > pliegue - 16 + 0.5; }).map(nombre),
        sigTop: siguientes.concat(secSig).map((el) => el.getBoundingClientRect()).filter((b) => b.height > 0).reduce((a, b) => Math.min(a, r1(b.top)), Infinity),
        islaH, cookies: !!banda.querySelector('button') && /cookie/i.test(banda.textContent || ''),
    };
}

/** `reglas()` de la guía, sin tocar: [cumple, regla, detalle]. */
function reglas(m, momento) {
    const r1 = (n) => Math.round(n * 2) / 2;
    const eq = (a, b) => Math.abs(a - b) <= 0.5;
    const R = [];
    R.push([!!m.meta && /width=device-width/.test(m.meta), 'Escala real en el móvil', m.meta ? '' : 'sin <meta viewport>: el móvil la pinta a 980px']);
    const gapSig = m.sigTop === null || m.sigTop === Infinity ? null : r1(m.sigTop - m.H.b);
    const sinHueco = [gapSig == null || gapSig <= 48.5, 'Sin hueco tras la cabecera', gapSig == null ? '' : 'cabecera → lo siguiente: ' + gapSig + 'px'];
    if (momento === 'scroll') {
        R.push([m.ocultos === 0, 'Todo a la vista tras el primer scroll', m.ocultos ? m.ocultos + ' bloque(s) siguen ocultos' : '']);
        R.push(sinHueco);
        return R;
    }
    const cx = r1((m.I.x + m.I.r) / 2 - (m.H.x + m.H.r) / 2);
    if (m.arriba) {
        const a = m.I.y, b = r1(m.H.y - m.I.b), c = r1(m.vh - m.H.b);
        R.push([eq(cx, 0), 'Isla en el eje del héroe', 'desvío ' + cx + 'px']);
        if (m.H.b <= m.vh + 0.5) R.push([eq(a, b) && eq(b, c), 'Aire sobre la isla = isla–héroe = bajo el héroe', a + ' · ' + b + ' · ' + c]);
        else R.push([m.tituloEntero, 'No cabe: titular entero, el resto al bajar', 'la tarjeta acaba en ' + m.H.b + ' de ' + m.vh]);
    } else {
        const pildora = m.I.w < m.H.w - 1;
        if (pildora) {
            R.push([eq(cx, 0), 'Isla en el eje del héroe', 'desvío ' + cx + 'px']);
            R.push([eq(m.vh - m.I.b, m.H.x), 'Isla: aire abajo = aire del héroe a los lados', r1(m.vh - m.I.b) + ' · ' + m.H.x]);
        } else {
            R.push([eq(m.I.x, m.H.x) && eq(m.I.r, m.H.r), 'Isla y héroe, mismos bordes', 'isla ' + m.I.x + '/' + r1(m.vw - m.I.r) + ' · héroe ' + m.H.x + '/' + r1(m.vw - m.H.r)]);
            R.push([eq(m.vh - m.I.b, m.I.x), 'Isla: aire abajo = aire a los lados', r1(m.vh - m.I.b) + ' · ' + m.I.x]);
        }
        R.push([eq(m.H.y, m.H.x), 'Héroe: aire arriba = aire a los lados', m.H.y + ' · ' + m.H.x]);
        if (m.layout === 'overlay') R.push(m.H.b <= m.Iy + 0.5 ? [eq(m.Iy - m.H.b, m.H.x), 'Héroe–isla = aire a los lados', r1(m.Iy - m.H.b) + ' · ' + m.H.x] : [m.tituloEntero, 'No cabe: titular entero, el resto al bajar', 'la tarjeta acaba en ' + m.H.b + ' de ' + m.vh]);
        else if (m.H.b <= m.Iy + 0.5) R.push([eq(m.Iy - m.H.b, m.H.x), 'Cabe entera: tarjeta–isla = aire a los lados', r1(m.Iy - m.H.b) + ' · ' + m.H.x]);
        else R.push([m.huecoPliegue != null && eq(m.huecoPliegue, 16), 'Último bloque entero a 16px de la isla', (m.ultimo || '—') + (m.huecoPliegue != null ? ' · ' + m.huecoPliegue + 'px' : '')]);
    }
    const acciones = (m.ctaHVisible ? 1 : 0) + (m.ctaIsla ? 1 : 0);
    R.push([acciones === 1, 'Una sola acción a la vista', acciones === 0 ? 'ninguna' : acciones === 2 ? 'dos: héroe e isla' : m.ctaHVisible ? 'la del héroe' : 'la de la isla']);
    R.push([m.cortes.length === 0, 'Nada partido por el pliegue', m.cortes.length ? m.cortes.map((c) => c.que).join(' · ') : '']);
    if (!m.arriba) R.push([m.asoma.length === 0, 'Nada asoma bajo la isla (ni a 16px de ella)', m.asoma.length ? m.asoma.join(' · ') : m.ocultos ? m.ocultos + ' oculto(s) hasta el primer scroll' : '']);
    R.push(sinHueco);
    return R;
}

/** Lo externo del mockup (React, Babel, fuentes, Lucide), una vez por proceso: `pixel.mjs`, trampa 7. */
const EXTERNOS = /^https:\/\/(fonts\.googleapis\.com|fonts\.gstatic\.com|unpkg\.com|cdn\.jsdelivr\.net)\//;
const memoria = new Map();
async function servirExternos(context) {
    await context.route(EXTERNOS, async (route) => {
        try {
            const u = route.request().url();
            if (!memoria.has(u)) {
                const r = await route.fetch();
                const headers = Object.fromEntries(Object.entries(r.headers()).filter(([k]) => !['content-encoding', 'content-length'].includes(k)));
                memoria.set(u, { status: r.status(), headers, body: await r.body() });
            }
            const c = memoria.get(u);
            await route.fulfill({ status: c.status, headers: c.headers, body: c.body });
        } catch (e) {
            if (!/closed/i.test(String(e && e.message))) throw e;
        }
    });
}

const espera = (ms) => new Promise((r) => setTimeout(r, ms));
async function estable(page, W, Hv) {
    let prev = '', n = 0, m = null;
    for (let i = 0; i < 30; i++) {
        await espera(200);
        try {
            await page.evaluate(() => { if (window.scrollY !== 0) window.scrollTo(0, 0); });
            m = await page.evaluate(medir, { W, Hv });
        } catch { m = null; }
        const f = m ? JSON.stringify([m.I, m.H, m.C, m.cortes.length, m.ocultos, m.ctaIsla, m.ctaHVisible, m.huecoPliegue, m.asoma.length]) : '';
        n = m && f === prev ? n + 1 : 0; prev = f;
        if (n >= 3) return m;
    }
    return m;
}

async function pantalla(b, lado, p, v, [, nombre, W, Hv]) {
    const context = await b.newContext({ viewport: { width: W, height: Hv }, locale: 'es-ES' });
    if (lado === 'diseno') await servirExternos(context);
    if (lado === 'producto' && v === '0') {
        const valor = Buffer.from(JSON.stringify({ v: POLITICA, cats: {} })).toString('base64');
        await context.addCookies([{ name: 'cookie_consent', value: valor, url: BASES.producto }]);
    }
    await context.addInitScript(({ botes, estrecha }) => {
        try { botes.concat([location.pathname]).forEach((k) => sessionStorage.setItem('pj-pista:' + k, '1')); } catch { /* sin almacenamiento */ }
        if (estrecha) document.addEventListener('DOMContentLoaded', () => { const st = document.createElement('style'); st.textContent = 'html{scrollbar-width:none}html::-webkit-scrollbar{display:none}'; document.head.appendChild(st); });
    }, { botes: BOTES, estrecha: W < 1024 });
    const page = await context.newPage();
    try {
        const res = await page.goto(url(lado, p, v), { waitUntil: 'load', timeout: 60000 });
        if (!res || !res.ok()) throw new Error(`no carga (${res ? res.status() : 'sin respuesta'})`);
        await page.waitForSelector('[data-situation] [data-surface="ink"]', { timeout: 30000 });
        await Promise.race([page.evaluate(() => document.fonts.ready.then(() => true)), espera(2500)]);
        const m1 = await estable(page, W, Hv);
        await page.evaluate(() => window.scrollTo(0, 2)); await espera(900);
        await page.evaluate(() => window.scrollTo(0, 0)); await espera(400);
        const m2 = await estable(page, W, Hv);
        return { nombre, W, Hv, llegada: m1, scroll: m2 };
    } catch (e) {
        return { nombre, W, Hv, error: String(e && e.message).slice(0, 160) };
    } finally {
        await context.unrouteAll({ behavior: 'ignoreErrors' });
        await context.close();
    }
}

const b = await chromium.launch();
const informe = [];
/** El veredicto de cada regla, por su llave: lado → «página|visita|momento|pantalla|regla» → cumple. */
const veredictos = { diseno: new Map(), producto: new Map() };
let fallidas = 0;
for (const lado of LADOS) {
    for (const p of PAGINAS) {
        for (const v of VISITAS) {
            const cola = PANTALLAS.slice();
            const hechas = [];
            await Promise.all(Array.from({ length: PARALELO }, async () => { while (cola.length) { const s = cola.shift(); hechas.push(await pantalla(b, lado, p, v, s)); } }));
            hechas.sort((x, y) => PANTALLAS.findIndex((s) => s[1] === x.nombre) - PANTALLAS.findIndex((s) => s[1] === y.nombre));
            for (const mo of ['llegada', 'scroll']) {
                let ok = 0, total = 0;
                const ko = [];
                for (const h of hechas) {
                    const m = h[mo];
                    const base = `${p}|${v}|${mo}|${h.W}x${h.Hv}`;
                    if (!m) { ko.push(`${h.W}x${h.Hv} ${h.error ?? 'sin medida'}`); total++; veredictos[lado].set(`${base}|sin medida`, false); continue; }
                    const R = reglas(m, mo);
                    R.forEach((x) => { total++; if (x[0]) ok++; veredictos[lado].set(`${base}|${x[1]}`, x[0]); });
                    const k = R.filter((x) => !x[0]);
                    if (k.length) ko.push(`${h.W}x${h.Hv} ` + k.map((x) => `${x[1]} (${x[2]})`).join(' / '));
                    informe.push({ lado, pagina: p, visita: v, momento: mo, pantalla: h.nombre, W: h.W, Hv: h.Hv, medida: m, reglas: R });
                }
                if (MODO !== 'comparar') fallidas += ko.length;
                console.log(`${ko.length ? '✗' : '✓'} ${lado} · ${p} · visita ${v === '1' ? 'primera' : 'vuelta'} · ${mo} · ${ok}/${total}`);
                ko.forEach((l) => console.log('    ' + l));
            }
        }
    }
}
await b.close();
if (MODO === 'comparar') {
    // La guarda: la web no INCUMPLE ninguna regla que el mockup cumple en esa misma pantalla, visita y momento. Una regla
    // que solo existe en un lado es OTRA RAMA del contrato («cabe entera» o «último bloque a 16px»; isla en píldora o a lo
    // ancho), y la decide el CONTENIDO: medido el 27-09, la frase de la isla del mockup es de demostración («Hoy abrimos a
    // las 16:30…», dos líneas) y la de la web, la real; y sus precios y ofertas, también. Esas se cuentan, no fallan.
    const llaves = new Set([...veredictos.diseno.keys(), ...veredictos.producto.keys()]);
    const peores = [...veredictos.producto].filter(([k, ok]) => ! ok && veredictos.diseno.get(k) !== false).map(([k]) => k);
    const mejores = [...veredictos.producto].filter(([k, ok]) => ok && veredictos.diseno.get(k) === false).map(([k]) => k);
    const ramas = [...llaves].filter((k) => veredictos.diseno.has(k) !== veredictos.producto.has(k)).length;
    console.log(`\n${peores.length ? '✗' : '✓'} comparar · ${peores.length} reglas que la web incumple y el mockup no · ${mejores.length} que la web cumple y el mockup no · ${ramas} de otra rama (el contenido)`);
    peores.forEach((k) => console.log(`    ✗ ${k} — mockup: ${veredictos.diseno.get(k) ?? 'otra rama'}`));
    mejores.forEach((k) => console.log(`    + ${k}`));
    fallidas = peores.length;
}
mkdirSync('storage/app/audit', { recursive: true });
writeFileSync(`storage/app/audit/sonda-primera-pantalla-${MODO}.json`, JSON.stringify(informe, null, 1));
process.exit(fallidas ? 1 : 0);
