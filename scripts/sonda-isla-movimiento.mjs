/**
 * SONDA DEL MOVIMIENTO DE LA ISLA — Z3 de `docs/specs/isla-y-landing-nueva.md` §4.14 (`DECISIONES #782`): lo que el diseño
 * del 26-09 mueve en la isla, medido en un navegador de verdad sobre /kids:
 *   1. la isla CEDE su acción con un primario de la página a la vista y, al recuperarla, la acción LLEGA con bote y la
 *      caja cambia con rebote (`--t-island`); al cederla otra vez, en calma (`--dur-slow`);
 *   2. si solo cambia la frase, la caja va en calma (`--dur-base`) y la frase nueva entra 120ms después;
 *   3. tocarla la HUNDE (`--scale-press`) y al soltar vuelve;
 *   4. el MENÚ entra fila a fila (+140ms, 30ms entre filas) y al cerrar su contenido se va en 100ms, la caja vuelve en
 *      `--dur-close` y el velo se funde;
 *   5. el RELEVO con la compra: crece desde la píldora y vuelve a ella, sin un fotograma sin isla;
 *   6. en la compra, la hora elegida es una PÍLDORA que se desliza, y el total rueda;
 *   7. con «reducir movimiento», nada de eso se mueve (y todo sigue funcionando);
 *   8. la calculadora de la página: la píldora de sus horas, su total que rueda y su «Reservar y pagar», que LLEGA
 *      con bote y un brillo al desbloquearse a la vista;
 *   9. entre páginas, la isla SE QUEDA (/kids → /jump: su grupo de la transición, con la vieja y la nueva).
 * La forma del movimiento contra el diseño la mide `scripts/sonda-banco-movimiento.mjs`.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-isla-movimiento.mjs [390|1280]
 *
 * Solo en LOCAL, con la isla como carcasa (`sidebar.shell = isla`). No escribe nada: abre la compra y la cierra sin
 * pagar. Sale con 1 si falla algo.
 */
import { chromium } from 'playwright-core';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const b = await chromium.launch();
let bien = 0;
const fallos = [];
const cumple = (nombre, ok, visto) => { if (ok) bien++; else fallos.push(`${nombre} — visto: ${JSON.stringify(visto)}`); };
const nueva = async (extra = {}) => {
    const p = await (await b.newContext({ viewport: { width: ANCHO, height: ANCHO >= 900 ? 800 : 844 }, locale: 'es-ES', ...extra })).newPage();
    await p.goto(`${BASE}/kids`, { waitUntil: 'load' });
    await p.waitForTimeout(700);
    const r = p.getByRole('button', { name: 'Rechazar' }).first();
    if (await r.isVisible().catch(() => false)) { await r.click(); await p.waitForTimeout(500); }
    return p;
};
const baja = (p, y) => p.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), y);
// Sin GPU, a 1280 el navegador de la sonda pinta un fotograma cada 35–50ms: se espera a la CONDICIÓN, con tope, nunca
// a un reloj fijo (medido: a 80ms la isla aún no había recibido el fotograma que la cambia).
const hasta = (p, fn, arg) => p.waitForFunction(fn, arg, { timeout: 2000, polling: 'raf' }).then(() => true, () => false);
// La isla de la PÁGINA (la primera) y su caja; lo que se mide de ella.
const estado = (p) => p.evaluate(() => {
    const w = document.querySelector('[data-jw-isla-pagina] > [data-isla]');
    if (! w) return null;
    const i = w.querySelector('[data-surface="ink"]');
    const a = w.querySelector('[data-isla-accion]');
    const l = w.querySelector('[aria-live="polite"]:not([role])');
    return {
        id: w.dataset.situation, size: w.dataset.size, transicion: i.style.transition, transform: i.style.transform,
        accion: a ? a.textContent.trim() : null, bote: a ? getComputedStyle(a).animationName : null, boteRetraso: a ? getComputedStyle(a).animationDelay : null,
        brillo: a ? [...a.children].map((c) => getComputedStyle(c)).filter((c) => c.animationName === 'isla-sheen').map((c) => c.animationDelay)[0] ?? null : null,
        linea: l ? l.textContent.trim() : null, lineaRetraso: l ? getComputedStyle(l).animationDelay : null,
    };
});

// Una altura sin ningún primario de la página a la vista (fuera de la franja de la isla): ahí la isla tiene su acción.
const sinBotones = (p) => p.evaluate(() => {
    const isla = document.querySelector('[data-jw-isla-pagina] [data-surface="ink"]').getBoundingClientRect();
    const abajo = isla.top > innerHeight / 2;
    const ctas = [...document.querySelectorAll('[data-isla-cta]')].map((el) => { const r = el.getBoundingClientRect(); return [r.top + scrollY, r.bottom + scrollY]; }).filter(([t, b]) => b > t);
    // La franja que se mira, 40px MÁS ancha por cada lado que la que mide la isla: sin ningún botón en ella, tampoco lo hay
    // para la isla.
    for (let y = 600; y < document.documentElement.scrollHeight - innerHeight; y += 50) {
        const [de, a] = abajo ? [y - 40, y + isla.top + 40] : [y + isla.bottom - 40, y + innerHeight + 40];
        if (! ctas.some(([t, b]) => b > de && t < a)) return y;
    }
    return null;
});

// 1–3 · Ceder, llegar con bote, irse en calma; la frase; hundirse.
{
    const p = await nueva();
    const arriba = await estado(p);
    cumple('arriba, con el botón de la cabecera a la vista, la isla cede su acción (también tras irse el aviso de cookies)', arriba && arriba.accion === null, arriba);
    const y = await sinBotones(p);
    cumple('hay una altura de la página sin botones a la vista', y !== null, y);
    await baja(p, y ?? 0);
    await hasta(p, () => Boolean(document.querySelector('[data-jw-isla-pagina] [data-isla-accion]')));
    const llega = await estado(p);
    cumple('al recuperar la acción, llega con bote (120ms después)', llega?.accion && llega.bote === 'isla-bote' && llega.boteRetraso === '0.12s', llega);
    cumple('…y tras el salto, un brillo que la cruza (lo pidió el owner, `#783`)', llega?.brillo === '0.64s', llega?.brillo);
    cumple('…y la caja cambia con rebote (`--t-island`)', /^var\(--t-island\)/.test(llega?.transicion ?? ''), llega?.transicion);
    await baja(p, 0);
    await hasta(p, () => ! document.querySelector('[data-jw-isla-pagina] [data-isla-accion]'));
    const sale = await estado(p);
    cumple('al cederla otra vez, la caja va en calma (`--dur-slow`)', sale?.accion === null && /^width var\(--dur-slow\)/.test(sale.transicion), sale);

    // La frase: un barrido por la página, y cada cambio de frase con la MISMA acción, en calma y con su retraso.
    const cambios = [];
    let antes = await estado(p);
    for (let y = 300; y < 9000; y += 350) {
        await baja(p, y);
        await p.waitForTimeout(60);
        const e = await estado(p);
        if (e && antes && (e.id !== antes.id || e.linea !== antes.linea) && e.accion === antes.accion && e.linea) cambios.push(e);
        antes = e;
    }
    // Informativo: en Kids, casi todo cambio de frase llega con un cambio de acción. Lo que hace la frase lo mide el banco
    // contra el diseño; aquí, que la que haya cumpla.
    console.log(`  (cambios de frase con la misma acción en el barrido: ${cambios.length})`);
    const malos = cambios.filter((c) => ! (/^width var\(--dur-base\)/.test(c.transicion) && c.lineaRetraso === '0.12s'));
    cumple('cada cambio de frase: caja en calma (`--dur-base`) y la frase 120ms después', malos.length === 0, malos.slice(0, 2));

    // Hundirse: el dedo en un botón de la isla cerrada, y soltar.
    const menu = p.locator('[data-jw-isla-pagina] button[aria-label]').first();
    const caja = await menu.boundingBox();
    await p.mouse.move(caja.x + caja.width / 2, caja.y + caja.height / 2);
    await p.mouse.down();
    await p.waitForTimeout(40);
    const hundida = (await estado(p)).transform;
    await p.mouse.move(caja.x + caja.width / 2, caja.y - 200);
    await p.mouse.up();
    await p.waitForTimeout(40);
    const suelta = (await estado(p)).transform;
    cumple('tocarla la hunde (`--scale-press`) y al soltar vuelve', hundida === 'scale(var(--scale-press))' && suelta === 'none', [hundida, suelta]);
    await p.context().close();
}

// 4 · El menú: fila a fila al abrir; al cerrar, el contenido en 100ms, la caja en `--dur-close` y el velo fundido.
{
    const p = await nueva();
    await p.locator('[data-jw-isla-pagina] button[aria-label]').first().click();
    await p.waitForTimeout(30);
    const filas = await p.evaluate(() => {
        const pn = document.querySelector('[data-jw-isla-pagina] [role="dialog"]');
        // Solo las de la API (las filas); no las de CSS, como el punto de [Hoy] que late.
        return pn ? [...pn.querySelectorAll('*')].flatMap((el) => el.getAnimations().filter((a) => a.constructor.name === 'Animation').map((a) => a.effect.getTiming().delay)).sort((x, y) => x - y) : null;
    });
    cumple('el menú entra fila a fila: +140ms y 30ms entre filas', filas && filas.length >= 4 && filas[0] === 140 && filas[1] === 170 && filas[2] === 200, filas?.slice(0, 5));
    await p.waitForTimeout(700);
    await p.keyboard.press('Escape');
    const fundiendo = await hasta(p, () => {
        const pn = document.querySelector('[data-jw-isla-pagina] [role="dialog"]');
        return pn && Number(getComputedStyle(pn).opacity) < 0.9;
    });
    cumple('al cerrar, el contenido se va fundiéndose (sigue ahí, a medio fundir)', fundiendo, null);
    await hasta(p, () => ! document.querySelector('[data-jw-isla-pagina] [role="dialog"]'));
    const cerrada = await p.evaluate(() => ({
        panel: Boolean(document.querySelector('[data-jw-isla-pagina] [role="dialog"]')),
        transicion: document.querySelector('[data-jw-isla-pagina] [data-surface="ink"]').style.transition,
        velo: Boolean(document.querySelector('[data-jw-isla-pagina] .isla-velo-sale')),
    }));
    cumple('fundido el contenido, el panel se va, la caja vuelve en `--dur-close` y el velo se funde', ! cerrada.panel && /^width var\(--dur-close\)/.test(cerrada.transicion) && cerrada.velo, cerrada);
    await p.waitForTimeout(500);
    cumple('el velo que se funde se quita solo', ! await p.evaluate(() => Boolean(document.querySelector('.isla-velo-sale'))), null);
    await p.context().close();
}

// 5–6 · El relevo con la compra, y dentro, las horas y el total.
{
    const p = await nueva();
    await p.evaluate(() => {
        window.__f = [];
        const mira = () => {
            const islas = [...document.querySelectorAll('[data-isla]')].map((w) => {
                const r = w.querySelector('[data-surface="ink"]').getBoundingClientRect();
                return { size: w.dataset.size, x: r.left, y: r.top, w: r.width, h: r.height };
            });
            window.__f.push(islas);
            requestAnimationFrame(mira);
        };
        requestAnimationFrame(mira);
    });
    const pildora = await p.evaluate(() => { const r = document.querySelector('[data-jw-isla-pagina] [data-surface="ink"]').getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width, h: r.height }; });
    await p.evaluate(() => { window.__f = []; window.JumpWeb.cajon.open(); });
    await p.waitForTimeout(900);
    const abre = await p.evaluate(() => window.__f);
    const sinIsla = abre.filter((f) => f.length === 0).length;
    const primera = abre.map((f) => f.find((x) => x.size === 'compra')).find(Boolean);
    const ultima = abre.at(-1).find((x) => x.size === 'compra');
    const arriba = ANCHO >= 900;
    const casa = (a, bb) => Math.abs((a.x + a.w / 2) - (bb.x + bb.w / 2)) < 6 && (arriba ? Math.abs(a.y - bb.y) < 6 : Math.abs((a.y + a.h) - (bb.y + bb.h)) < 8);
    cumple('abrir la compra: ningún fotograma sin isla', sinIsla === 0, sinIsla);
    cumple('la compra nace de la píldora (su centro y su borde anclado)', primera && casa(primera, pildora) && primera.h < pildora.h + 40, { primera, pildora });
    cumple('…y acaba en su sitio, grande', ultima && ultima.h > 300, ultima);

    // Dentro: la zona (abierta sin producto, la compra pregunta cuál), el día si lo pide, una hora, otra hora (la
    // píldora se desliza) y el total.
    await p.locator('[data-isla] [data-surface="ink"]').getByText(/^kids$/i).first().click();
    await p.waitForTimeout(600);
    const dia = p.locator('[data-isla] [role="radiogroup"] [role="radio"]:not([disabled])');
    if (await dia.count()) await dia.first().click();
    await p.waitForSelector('[data-isla] [data-hora]:not([disabled])', { timeout: 8000 });
    await p.waitForTimeout(600);
    const horas = p.locator('[data-isla] [data-hora]:not([disabled])');
    const n = await horas.count();
    await horas.nth(0).click();
    await p.waitForTimeout(500);
    const pil = () => p.evaluate(() => {
        const g = document.querySelector('[data-isla] [data-hora]').parentElement;
        const pl = g.firstElementChild;
        const elegida = g.querySelector('[data-hora][aria-pressed="true"]');
        const [x, y] = (pl.style.transform.match(/-?[\d.]+/g) || []).map(Number);
        return { x, y, opacidad: pl.style.opacity, destino: elegida ? [elegida.offsetLeft, elegida.offsetTop] : null, transicion: pl.style.transition };
    });
    const enSuSitio = (e) => e.destino && e.x === e.destino[0] && e.y === e.destino[1];
    const una = await pil();
    cumple('la hora elegida lleva la píldora debajo', una.opacidad === '1' && enSuSitio(una), una);
    if (n > 2) {
        // Se muestrea la píldora fotograma a fotograma mientras va de una hora a otra: tiene que pasar POR MEDIO.
        await p.evaluate(() => {
            const pl = document.querySelector('[data-isla] [data-hora]').parentElement.firstElementChild;
            window.__px = [];
            const t0 = performance.now();
            const mira = () => { window.__px.push(new DOMMatrix(getComputedStyle(pl).transform).m41); if (performance.now() - t0 < 900) requestAnimationFrame(mira); };
            requestAnimationFrame(mira);
        });
        await horas.nth(2).click();
        await p.waitForTimeout(950);
        const otra = await pil();
        const xs = await p.evaluate(() => window.__px);
        const medios = xs.filter((x) => x > una.x + 2 && x < otra.x - 2).length;
        cumple('al elegir otra, la píldora SE DESLIZA (pasa por medio y acaba en su sitio)', /transform var\(--dur-slow\)/.test(otra.transicion) && enSuSitio(otra) && medios > 0, { medios, una: una.x, otra });
    }
    const total = await p.evaluate(() => {
        const col = [...document.querySelectorAll('[data-isla] [aria-hidden="true"] > span > span')].find((s) => /translateY\(-?[\d.]+em\)/.test(s.style.transform));
        return col ? { rueda: true, transicion: col.style.transition } : null;
    });
    cumple('el total es un número que rueda', total?.rueda && /transform var\(--dur-slow\) var\(--ease-spring\)/.test(total.transicion), total);

    await p.evaluate(() => { window.__f = []; });
    await p.keyboard.press('Escape');
    await p.waitForTimeout(700);
    const cierra = await p.evaluate(() => window.__f);
    cumple('cerrar la compra: ningún fotograma sin isla', cierra.filter((f) => f.length === 0).length === 0, cierra.filter((f) => f.length === 0).length);
    const vuelta = cierra.at(-1).find((x) => x.size !== 'compra');
    cumple('…y la píldora vuelve a su sitio', vuelta && casa(vuelta, pildora) && Math.abs(vuelta.h - pildora.h) < 8, { vuelta, pildora });
    await p.context().close();
}

// 8 · La calculadora de la página (fuera de la isla): la píldora de sus horas, el total que rueda y su «Reservar y
// pagar», que al desbloquearse LLEGA con bote y un brillo la primera vez que se ve (dentro de la isla, nunca).
{
    const p = await nueva();
    await p.evaluate(() => document.getElementById('precio')?.scrollIntoView({ behavior: 'instant' }));
    await p.waitForSelector('#p3-dia button[aria-pressed]:not([disabled])', { timeout: 10000 });
    await p.locator('#p3-dia button[aria-pressed]:not([disabled])').nth(2).click();
    await p.waitForSelector('#p3-hora [data-hora]:not([disabled])', { timeout: 10000 });
    await p.locator('#p3-hora [data-hora]:not([disabled])').first().click();
    await hasta(p, () => { const g = document.querySelector('#p3-hora [data-hora]')?.parentElement; return g && g.firstElementChild.style.opacity === '1'; });
    const pildora = await p.evaluate(() => document.querySelector('#p3-hora [data-hora]').parentElement.firstElementChild.style.opacity);
    cumple('las horas de la calculadora llevan su píldora', pildora === '1', pildora);
    const boton = p.locator('[data-jw-calculadora-lado] [data-isla-cta]').first();
    await hasta(p, () => { const x = document.querySelector('[data-jw-calculadora-lado] [data-isla-cta]'); return x && ! x.disabled; });
    await p.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await p.waitForTimeout(300);
    await boton.evaluate((x) => x.scrollIntoView({ block: 'center', behavior: 'instant' }));
    const llego = await hasta(p, () => getComputedStyle(document.querySelector('[data-jw-calculadora-lado] [data-isla-cta]')).animationName === 'isla-bote');
    const brillo = await p.evaluate(() => [...document.querySelector('[data-jw-calculadora-lado] [data-isla-cta]').children].some((c) => getComputedStyle(c).animationName === 'isla-sheen'));
    cumple('su «Reservar y pagar», desbloqueado y a la vista, llega con bote y un brillo', llego && brillo, { llego, brillo });
    const total = await p.evaluate(() => {
        const col = [...document.querySelectorAll('[data-jw-calculadora-lado] span')].find((s) => /translateY\(-?[\d.]+em\)/.test(s.style.transform));
        return col ? col.style.transition : null;
    });
    cumple('su total es un número que rueda', /transform var\(--dur-slow\) var\(--ease-spring\)/.test(total ?? ''), total);
    await p.context().close();
}

// 9 · Entre páginas, la isla SE QUEDA: /kids → /jump, y la nueva ya la trae al revelarse (su script bloquea el primer
// pintado cuando se llega desde el sitio), así que viajan como una (`::view-transition-group(isla)`, vieja y nueva).
{
    const ctx = await b.newContext({ viewport: { width: ANCHO, height: 844 }, locale: 'es-ES' });
    await ctx.addInitScript(() => {
        if (window !== top) return;
        addEventListener('pagereveal', (e) => {
            window.__isla = Boolean(document.querySelector('[data-jw-isla-pagina] [data-isla]'));
            if (e.viewTransition) e.viewTransition.ready.then(() => { window.__grupos = document.getAnimations().map((a) => a.effect?.pseudoElement).filter(Boolean); }, () => { window.__grupos = []; });
        });
    });
    const p = await ctx.newPage();
    await p.goto(`${BASE}/kids`, { waitUntil: 'load' });
    await p.waitForTimeout(500);
    await p.evaluate(() => { const a = document.createElement('a'); a.href = '/jump'; a.id = 'sonda-ir'; a.textContent = 'ir'; a.style.cssText = 'position:fixed;top:40%;left:0;z-index:99999;background:#fff'; document.body.append(a); });
    await p.click('#sonda-ir');
    await p.waitForURL('**/jump');
    await p.waitForTimeout(900);
    const vt = await p.evaluate(() => ({ isla: window.__isla, grupos: window.__grupos || [] }));
    cumple('entre páginas la isla se queda: la nueva la trae al revelarse y las dos forman su grupo', vt.isla && vt.grupos.includes('::view-transition-group(isla)') && vt.grupos.includes('::view-transition-new(isla)') && vt.grupos.includes('::view-transition-old(isla)'), vt);
    await ctx.close();
}

// 7 · Con «reducir movimiento»: ni bote, ni filas, ni relevo animado.
{
    const p = await nueva({ reducedMotion: 'reduce' });
    await p.locator('[data-jw-isla-pagina] button[aria-label]').first().click();
    await p.waitForTimeout(30);
    const filas = await p.evaluate(() => [...document.querySelectorAll('[data-jw-isla-pagina] [role="dialog"] *')].filter((el) => el.getAnimations().some((a) => a.effect.getTiming().duration > 1)).length);
    await p.keyboard.press('Escape');
    await p.waitForTimeout(100);
    await p.evaluate(() => window.JumpWeb.cajon.open());
    await p.waitForTimeout(400);
    const relevo = await p.evaluate(() => [...document.querySelectorAll('[data-isla] [data-surface="ink"]')].flatMap((i) => i.getAnimations().map((a) => a.effect.getTiming().duration)).filter((d) => d > 1).length);
    cumple('reducir movimiento: el menú y el relevo no se animan', filas === 0 && relevo === 0, { filas, relevo });
    await p.context().close();
}

await b.close();
console.log(`sonda-isla-movimiento ${ANCHO}: ${bien}/${bien + fallos.length}`);
fallos.forEach((f) => console.log(`  ✗ ${f}`));
process.exit(fallos.length ? 1 : 0);
