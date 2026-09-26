/**
 * EL MOVIMIENTO DE LA ISLA, CONTRA EL DISEÑO (Z3 de `docs/specs/isla-y-landing-nueva.md` §4.14, `#782`): en el banco de
 * la isla (`scripts/banco-isla.php`: `a-*` es la `ParkIsland` del mockup, con su React; `b-*`, `IslaFlotante` del
 * producto) se aplican LOS MISMOS cambios a las dos —con `window.BANCO_SET`, o el mismo clic— y se muestrea cada
 * fotograma: el tamaño de la caja (cuánto tarda en asentarse y si rebota), el bote de la acción, el retraso de la frase y
 * los retrasos de las filas del panel. Lo que se compara es la FORMA del movimiento, con la holgura de un fotograma.
 *
 *   docker compose exec -u sail -T laravel.test npx vite build --config scripts/banco-isla/vite.config.mjs
 *   docker compose exec -u sail -T laravel.test php scripts/banco-isla.php /var/www/instancias/playjump/diseno/… \
 *       /var/www/instancias/playjump/tema/isla-situaciones.json storage/app/pixel/banco-isla http://127.0.0.1:8128
 *   docker compose exec -u sail -d laravel.test php -S 127.0.0.1:8128 -t storage/app/pixel/banco-isla
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-banco-movimiento.mjs [390|1280]
 *
 * El lado A trae React de unpkg: necesita red. Sale con 1 si algo no casa.
 */
import { chromium } from 'playwright-core';

const BASE = process.env.BANCO_BASE ?? 'http://127.0.0.1:8128';
const ANCHO = Number(process.argv[2] ?? 390);
const b = await chromium.launch();
let bien = 0;
const fallos = [];
const cumple = (nombre, ok, visto) => { if (ok) bien++; else fallos.push(`${nombre} — visto: ${JSON.stringify(visto)}`); };

/** Monta una situación en un lado, arranca el muestreo y aplica el cambio. Devuelve las muestras. */
async function correr(lado, situacion, cambio, ms = 1000) {
    const p = await (await b.newContext({ viewport: { width: ANCHO, height: ANCHO >= 900 ? 800 : 844 }, locale: 'es-ES' })).newPage();
    await p.goto(`${BASE}/${lado}-${situacion}.html`, { waitUntil: 'load' });
    await p.waitForFunction(() => document.querySelector('[data-situation] [data-surface="ink"]') && typeof window.BANCO_SET === 'function', null, { timeout: 30000 });
    await p.waitForTimeout(900);
    // Lo que va ANTES del cambio (abrir el menú para cerrarlo) se hace antes de empezar a muestrear.
    if (cambio.antes) { await p.locator(cambio.antes).first().click(); await p.waitForTimeout(900); }
    await p.evaluate((dur) => {
        window.__m = [];
        const t0 = performance.now();
        const paso = () => {
            const i = document.querySelector('[data-situation] [data-surface="ink"]');
            const r = i.getBoundingClientRect();
            const a = i.querySelector('[data-island-action], [data-isla-accion]');
            const ar = a ? a.getBoundingClientRect() : null;
            const l = i.querySelector('[aria-live="polite"]:not([role])');
            window.__m.push({
                t: performance.now() - t0, w: r.width, h: r.height, radio: parseFloat(getComputedStyle(i).borderTopLeftRadius),
                escala: new DOMMatrix(getComputedStyle(i).transform).a,
                accion: a ? { dy: ar.top - r.top, op: Number(getComputedStyle(a).opacity), anim: getComputedStyle(a).animationName } : null,
                linea: l ? { texto: l.textContent.trim(), retraso: getComputedStyle(l).animationDelay } : null,
                filas: [...i.querySelectorAll('[role="dialog"] *')].flatMap((el) => el.getAnimations().filter((x) => x.constructor.name === 'Animation').map((x) => x.effect.getTiming().delay)).sort((x, y) => x - y).slice(0, 4),
            });
            if (performance.now() - t0 < dur) requestAnimationFrame(paso);
        };
        requestAnimationFrame(paso);
    }, ms);
    await p.waitForTimeout(40);
    if (cambio.props) await p.evaluate((x) => window.BANCO_SET(x), cambio.props);
    if (cambio.clic) await p.locator(cambio.clic).first().click();
    if (cambio.tecla) await p.keyboard.press(cambio.tecla);
    if (cambio.despues) await p.keyboard.press(cambio.despues);
    if (cambio.hundir) {
        const c = await p.locator(cambio.hundir).first().boundingBox();
        await p.mouse.move(c.x + c.width / 2, c.y + c.height / 2);
        await p.mouse.down();
    }
    await p.waitForTimeout(ms + 100);
    const m = await p.evaluate(() => window.__m);
    await p.context().close();
    return m;
}

/** La forma de una serie: final, cuándo se asienta (a 1px) y cuánto se pasa del final (el rebote). */
function forma(m, clave) {
    const v = m.map((x) => x[clave]);
    const fin = v.at(-1);
    const ini = v[0];
    let asienta = 0;
    for (let i = v.length - 1; i >= 0; i--) { if (Math.abs(v[i] - fin) > 1) { asienta = m[Math.min(i + 1, m.length - 1)].t; break; } }
    const crece = fin >= ini;
    const pasa = crece ? Math.max(...v) - fin : fin - Math.min(...v);
    return { ini: Math.round(ini), fin: Math.round(fin), asienta: Math.round(asienta), pasa: Math.round(pasa * 10) / 10 };
}
const casan = (fa, fb, holgura = 70) => Math.abs(fa.fin - fb.fin) <= 2 && Math.abs(fa.asienta - fb.asienta) <= holgura && (fa.pasa > 1.5) === (fb.pasa > 1.5);

const ESCENAS = [
    // La frase cambia con la misma acción: en calma, y la nueva entra 120ms después. Con un DATO DURO (€), para que en
    // móvil vaya en su renglón y no dentro del botón (ahí no hay línea que medir).
    { nombre: 'frase', situacion: 'miedo', cambio: { props: { reassurance: 'Solo pagas 50 € al reservar; el resto, en el parque' } }, caja: 'w', linea: true },
    // Llega la acción (la página deja de tener su botón a la vista): rebote y bote.
    { nombre: 'llega', situacion: 'hoy-cedida', cambio: { props: { ctaVisible: false } }, caja: 'w', bote: true },
    // Se va la acción: en calma.
    { nombre: 'sale', situacion: 'hoy-antes-huecos', cambio: { props: { ctaVisible: true } }, caja: 'w' },
    // Abrir el menú: en calma, y sus filas +140ms, 30ms entre ellas.
    { nombre: 'abrir menú', situacion: 'menu-invitado', cambio: { clic: '[aria-label="Menú, cuenta y Mi QR"]' }, caja: 'h', filas: true },
    // Cerrarlo: el contenido primero, la caja en `--dur-close`.
    { nombre: 'cerrar menú', situacion: 'menu-invitado', cambio: { antes: '[aria-label="Menú, cuenta y Mi QR"]', despues: 'Escape' }, caja: 'h', holgura: 90 },
    // Hundirse al tocar.
    { nombre: 'hundirse', situacion: 'hoy-antes-huecos', cambio: { hundir: '[aria-label="Menú, cuenta y Mi QR"]' }, escala: true, ms: 400 },
];

for (const e of ESCENAS) {
    const [ma, mb] = await Promise.all([correr('a', e.situacion, e.cambio, e.ms), correr('b', e.situacion, e.cambio, e.ms)]);
    if (e.caja) {
        const fa = forma(ma, e.caja);
        const fb = forma(mb, e.caja);
        cumple(`${e.nombre}: la caja (${e.caja}) se mueve como en el diseño`, casan(fa, fb, e.holgura), { diseño: fa, producto: fb });
    }
    if (e.bote) {
        const boteDe = (m) => { const xs = m.filter((x) => x.accion); return { anim: xs.at(-1)?.accion.anim, opMin: Math.min(...xs.map((x) => x.accion.op)), sube: Math.round(Math.min(...xs.map((x) => x.accion.dy)) - xs.at(-1).accion.dy) }; };
        const [ba, bb] = [boteDe(ma), boteDe(mb)];
        cumple(`${e.nombre}: la acción entra con el bote del diseño`, ba.opMin < 0.2 && bb.opMin < 0.2 && Math.abs(ba.sube - bb.sube) <= 2, { diseño: ba, producto: bb });
    }
    if (e.linea) {
        const ret = (m) => m.filter((x) => x.linea && x.linea.texto.startsWith('Solo pagas')).map((x) => x.linea.retraso)[0];
        cumple(`${e.nombre}: la frase nueva entra con el retraso del diseño`, ret(ma) === ret(mb) && ret(ma) === '0.12s', { diseño: ret(ma), producto: ret(mb) });
    }
    if (e.filas) {
        const fil = (m) => (m.find((x) => x.filas.length >= 3) || { filas: [] }).filas.slice(0, 3);
        cumple(`${e.nombre}: las filas entran con los retrasos del diseño`, JSON.stringify(fil(ma)) === JSON.stringify(fil(mb)) && fil(ma).length === 3, { diseño: fil(ma), producto: fil(mb) });
    }
    if (e.escala) {
        const esc = (m) => Math.round(Math.min(...m.map((x) => x.escala)) * 1000) / 1000;
        cumple(`${e.nombre}: la isla se hunde como en el diseño`, esc(ma) === esc(mb) && esc(ma) < 1, { diseño: esc(ma), producto: esc(mb) });
    }
}

await b.close();
console.log(`sonda-banco-movimiento ${ANCHO}: ${bien}/${bien + fallos.length}`);
fallos.forEach((f) => console.log(`  ✗ ${f}`));
process.exit(fallos.length ? 1 : 0);
