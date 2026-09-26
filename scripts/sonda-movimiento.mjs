/**
 * SONDA DEL MOVIMIENTO — lo que el sistema Saltia mueve en las páginas de Kids y Jump, en un navegador de verdad (Z2 de
 * `docs/specs/isla-y-landing-nueva.md` §4.14, `DECISIONES #781`). Lo pinta `movimiento.js` de la instancia sobre el
 * HTML del servidor; aquí se mide cada pieza contra la regla del diseño:
 *   1. el BOTÓN primario llega (bote y un brillo) al verse un 25 %, y NO antes; a los 1,6 s ya no lleva la clase;
 *   2. la NOTA cuenta desde el entero (4,0 → 4,9) y acaba en lo que escribió el servidor;
 *   3. el ACORDEÓN abierto apaga su respuesta corta y trae el texto con 60ms de retraso;
 *   4. la CABECERA tiene fondo al bajar (la foto se mueve) y, con el texto sobre la foto (≥ 720px), el texto se funde;
 *   5. «HAY MÁS ABAJO»: sin tocar nada, la ventana bota una vez `--nudge-distance` y vuelve; al recargar, no;
 *   6. con «reducir movimiento», nada de lo anterior;
 *   7. ENTRE PÁGINAS, /kids → /jump con View Transition (la regla en línea de `<x-pagina transiciones>`);
 *   8. el CAMINO de pasos ligado al scroll —hoy ninguna página lo pinta (las dos llevan calculadora), así que se
 *      inyecta su HTML, el mismo que `step-list.blade.php`—: vacío arriba, a medias en medio, lleno abajo.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-movimiento.mjs [1280|390]
 *
 * Solo lectura: no escribe en la BD ni en `public/`. Sale con 1 si falla algo.
 * ⚠️ La página lleva `scroll-behavior: smooth`: la sonda baja con `behavior: 'instant'`, o mide a medio camino.
 */
import { chromium } from 'playwright-core';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 1280);
const b = await chromium.launch();
const nueva = async (extra = {}) => (await b.newContext({ viewport: { width: ANCHO, height: 900 }, locale: 'es-ES', ...extra })).newPage();
const baja = (p, y) => p.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), y);
const vigilaY = () => { window.__maxY = 0; const mira = () => { window.__maxY = Math.max(window.__maxY, window.scrollY); requestAnimationFrame(mira); }; requestAnimationFrame(mira); };
let bien = 0;
const fallos = [];
const cumple = (nombre, ok, visto) => { if (ok) bien++; else fallos.push(`${nombre} — visto: ${JSON.stringify(visto)}`); };

// 1–4 · El botón, la nota, el acordeón y la cabecera.
{
    const p = await nueva();
    await p.addInitScript(() => {
        window.__notas = [];
        new MutationObserver((ms) => ms.forEach((m) => {
            const n = m.target.nodeType === 3 ? m.target.parentElement : m.target;
            if (n?.classList?.contains('pj-rs__nota')) window.__notas.push(n.textContent);
        })).observe(document, { subtree: true, childList: true, characterData: true });
    });
    await p.goto(`${BASE}/kids`, { waitUntil: 'load' });
    await p.waitForTimeout(250);
    const boton = () => p.evaluate(() => {
        const bt = document.querySelector('.pj-vh .pj-btn--primary');
        const r = bt.getBoundingClientRect();
        return { visible: Math.max(0, Math.min(innerHeight, r.bottom) - Math.max(0, r.top)) / r.height, llega: bt.classList.contains('pj-btn--llega'), bote: getComputedStyle(bt).animationName, brillo: getComputedStyle(bt, '::after').animationName };
    });
    const alCargar = await boton();
    cumple('el botón llega al cargar SOLO si se ve un 25 %', alCargar.llega === alCargar.visible >= 0.25, alCargar);
    await baja(p, 300);
    await p.waitForTimeout(200);
    const alBajar = await boton();
    cumple('el botón, a la vista, llega con bote y brillo', alBajar.llega && alBajar.bote === 'pj-bote' && alBajar.brillo === 'pj-sheen', alBajar);
    await p.waitForTimeout(1600);
    cumple('a los 1,6 s ya no lleva la clase', !(await boton()).llega, null);

    const notas = await p.evaluate(() => ({ lista: window.__notas, final: document.querySelector('.pj-rs__nota')?.textContent }));
    const final = notas.final ?? '';
    const entero = final.split(/[.,]/)[0];
    cumple('la nota cuenta desde el entero', notas.lista.includes(`${entero},0`) || notas.lista.includes(`${entero}.0`) || /[.,]0$/.test(final), notas.lista.slice(0, 6));
    cumple('la nota acaba en la del servidor', notas.lista.at(-1) === final && /^\d[.,]\d$/.test(final), notas);

    await baja(p, 620);
    await p.waitForTimeout(200);
    const cab = await p.evaluate(() => ({ foto: document.querySelector('.pj-vh__foto').style.transform, texto: document.querySelector('.pj-vh__cuerpo').style.opacity, caja: document.querySelector('.pj-vh-caja').clientWidth }));
    cumple('la foto de la cabecera se mueve al bajar', /translate3d\(0px, [\d.]+px, 0px\) scale\(1\.0\d+\)/.test(cab.foto), cab);
    cumple('el texto se funde solo sobre la foto (≥ 720px)', cab.caja >= 720 ? Number(cab.texto) < 1 : cab.texto === '', cab);

    const fila = p.locator('.pj-acc__fila').first();
    await fila.scrollIntoViewIfNeeded();
    await fila.locator('button').first().click();
    await p.waitForTimeout(700);
    const acc = await p.evaluate(() => {
        const f = document.querySelector('.pj-acc__fila');
        const pista = f.querySelector('.pj-acc__pista');
        const resp = f.querySelector('.pj-acc__respuesta');
        return { abierta: f.hasAttribute('data-abierta'), pista: pista ? getComputedStyle(pista).opacity : '0', respuesta: getComputedStyle(resp).opacity, retraso: getComputedStyle(resp).transitionDelay };
    });
    cumple('el acordeón abierto: pista apagada, texto entero, 60ms después', acc.abierta && acc.pista === '0' && acc.respuesta === '1' && acc.retraso === '0.06s', acc);
    await p.context().close();
}

// 5 · «Hay más abajo», una vez por visita y página.
{
    const p = await nueva();
    await p.addInitScript(vigilaY);
    await p.goto(`${BASE}/kids`, { waitUntil: 'load' });
    await p.waitForTimeout(6000);
    const primera = await p.evaluate(() => ({ max: window.__maxY, y: window.scrollY, marca: sessionStorage.getItem(`pj-pista:${location.pathname}`) }));
    cumple('la pista bota su distancia y vuelve arriba', primera.max >= 10 && primera.max <= 20 && primera.y === 0 && primera.marca === '1', primera);
    await p.reload({ waitUntil: 'load' });
    await p.waitForTimeout(6000);
    const segunda = await p.evaluate(() => window.__maxY);
    cumple('al recargar, no vuelve a botar', segunda === 0, segunda);
    await p.context().close();
}

// 6 · Con «reducir movimiento», quieto.
{
    const p = await nueva({ reducedMotion: 'reduce' });
    await p.addInitScript(vigilaY);
    await p.goto(`${BASE}/kids`, { waitUntil: 'load' });
    await p.waitForTimeout(5500);
    const max = await p.evaluate(() => window.__maxY);
    await baja(p, 500);
    await p.waitForTimeout(200);
    const q = await p.evaluate(() => ({ llega: document.querySelectorAll('.pj-btn--llega').length, foto: document.querySelector('.pj-vh__foto').style.transform }));
    cumple('reducir movimiento: ni pista, ni llegada, ni fondo', max === 0 && q.llega === 0 && q.foto === '', { max, ...q });
    await p.context().close();
}

// 7 · Entre páginas.
{
    const p = await nueva();
    await p.addInitScript(() => { if (window === top) addEventListener('pagereveal', (e) => { window.__vt = e.viewTransition ? 'sí' : 'no'; e.viewTransition?.finished.then(() => { window.__fin = 'ok'; }, (err) => { window.__fin = String(err); }); }); });
    await p.goto(`${BASE}/kids`, { waitUntil: 'load' });
    const nombre = await p.evaluate(() => getComputedStyle(document.querySelector('.pj-vh__foto')).viewTransitionName);
    await p.waitForTimeout(300);
    await p.evaluate(() => { const a = document.createElement('a'); a.href = '/jump'; a.id = 'sonda-ir'; a.textContent = 'ir'; a.style.cssText = 'position:fixed;top:0;left:0;z-index:99999;background:#fff'; document.body.append(a); });
    await p.click('#sonda-ir');
    await p.waitForURL('**/jump');
    await p.waitForTimeout(900);
    const vt = await p.evaluate(() => [window.__vt, window.__fin]);
    cumple('la foto se llama pj-kids para la transición', nombre === 'pj-kids', nombre);
    cumple('/kids → /jump con View Transition, y termina', vt[0] === 'sí' && vt[1] === 'ok', vt);
    await p.context().close();
}

// 8 · El camino ligado, con su HTML inyectado tras un hueco que obliga a bajar.
{
    const p = await nueva();
    await p.route('**/kids', async (ruta) => {
        const res = await ruta.fetch();
        const li = (i, ultimo) => `<li class="pj-sl__paso${ultimo ? ' pj-sl__paso--ultimo' : ''}">${ultimo ? '' : '<span aria-hidden="true" class="pj-sl__via"><span class="pj-sl__relleno"></span></span>'}<span class="pj-sl__nodo"><span class="pj-sl__num">${i + 1}</span></span><div class="pj-sl__texto"><h4 class="pj-sl__titulo">Paso ${i + 1}</h4><p class="pj-sl__detalle">Texto del paso.</p></div></li>`;
        const ol = `<div style="height:1400px"></div><section class="pj-sec"><div class="pj-wrap" style="max-width:420px"><ol class="pj-sl" id="sonda-sl">${[0, 1, 2, 3].map((i) => li(i, i === 3)).join('')}</ol></div></section>`;
        await ruta.fulfill({ response: res, body: (await res.text()).replace(/(<main[^>]*>)/, `$1${ol}`) });
    });
    await p.goto(`${BASE}/kids`, { waitUntil: 'load' });
    await p.waitForTimeout(300);
    const estado = () => p.evaluate(() => {
        const ol = document.getElementById('sonda-sl');
        return { on: [...ol.children].map((li) => (li.hasAttribute('data-on') ? 1 : 0)).join(''), relleno: [...ol.querySelectorAll('.pj-sl__relleno')].map((x) => Number((/scaleY\(([\d.]+)\)/.exec(x.style.transform) ?? [0, 0])[1])) };
    });
    const arriba = await estado();
    // La línea (55 % de la ventana) a la MITAD del primer raíl, medido: ni a ojo ni por la altura de un paso.
    await p.evaluate(() => { const via = document.querySelector('#sonda-sl .pj-sl__via').getBoundingClientRect(); window.scrollTo({ top: via.top + via.height / 2 + scrollY - innerHeight * 0.55, behavior: 'instant' }); });
    await p.waitForTimeout(400);
    const medio = await estado();
    await baja(p, 1e6);
    await p.waitForTimeout(400);
    const abajo = await estado();
    cumple('camino: vacío arriba', arriba.on === '0000' && arriba.relleno.every((x) => x === 0), arriba);
    cumple('camino: el primero encendido y su raíl a medias', medio.on.startsWith('1') && medio.relleno[0] > 0 && medio.relleno[0] < 1, medio);
    cumple('camino: lleno abajo', abajo.on === '1111' && abajo.relleno.every((x) => x === 1), abajo);
    await p.context().close();
}

await b.close();
console.log(`sonda-movimiento ${ANCHO}: ${bien}/${bien + fallos.length}`);
fallos.forEach((f) => console.log(`  ✗ ${f}`));
process.exit(fallos.length ? 1 : 0);
