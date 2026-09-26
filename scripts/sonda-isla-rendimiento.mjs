/**
 * SONDA DEL RENDIMIENTO DE LA ISLA (`docs/specs/isla-y-landing-nueva.md` §4.14, `#783`): lo que cuesta en el hilo
 * principal, con la CPU ×4 (un móvil medio), en cuatro escenas de /kids —desplazarse por la página, abrir y cerrar el menú,
 * abrir y cerrar la compra, y quieta arriba—. Por escena: fotogramas de más de 20ms y de más de 50ms, el tiempo de script,
 * de estilos y de maquetación (`Performance.getMetrics`), cuántas veces se recalcula la maquetación y cuántas veces
 * cambia el estilo de la isla (sus repintados de Vue). No juzga: imprime, para comparar antes y después de optimizar.
 * ⚠️ Sin GPU el pintado cuesta más que en un móvil; lo que se compara es el mismo escenario en la misma máquina.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-isla-rendimiento.mjs [390|1280] [cpu=4]
 */
import { chromium } from 'playwright-core';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const CPU = Number(process.argv[3] ?? 4);
const b = await chromium.launch();
const ctx = await b.newContext({ viewport: { width: ANCHO, height: ANCHO >= 900 ? 800 : 844 }, locale: 'es-ES' });
const p = await ctx.newPage();
const cdp = await ctx.newCDPSession(p);
await p.goto(`${BASE}/kids`, { waitUntil: 'load' });
// Experimentos (`EXP=a,b`), para medir cuánto pesa cada cosa ANTES de decidir, sin tocar el código.
const EXPERIMENTOS = {
    'sin-cristal': '[data-isla] [data-surface="ink"]{backdrop-filter:none!important;-webkit-backdrop-filter:none!important}',
    'sin-velo': '[data-isla] > div:not([data-surface]){backdrop-filter:none!important;-webkit-backdrop-filter:none!important}',
    contener: '[data-isla] [data-surface="ink"]{contain:layout paint!important}',
    'velo-quieto': '[data-isla] > div:not([data-surface]){animation:none!important}',
    'velo-4px': '[data-isla] > div:not([data-surface]){backdrop-filter:saturate(120%) blur(4px)!important;-webkit-backdrop-filter:saturate(120%) blur(4px)!important}',
};
for (const e of (process.env.EXP ?? '').split(',').filter(Boolean)) await p.addStyleTag({ content: EXPERIMENTOS[e] });
await p.waitForTimeout(800);
const r = p.getByRole('button', { name: 'Rechazar' }).first();
if (await r.isVisible().catch(() => false)) { await r.click(); await p.waitForTimeout(600); }
await cdp.send('Performance.enable');
await cdp.send('Emulation.setCPUThrottlingRate', { rate: CPU });
await p.evaluate(() => {
    window.__cambiosIsla = 0;
    const vigilar = () => {
        const i = document.querySelector('[data-jw-isla-pagina] [data-surface="ink"]');
        if (i && ! i.__vigilada) { i.__vigilada = true; new MutationObserver((ms) => { window.__cambiosIsla += ms.length; }).observe(i, { attributes: true, attributeFilter: ['style'] }); }
    };
    vigilar();
    setInterval(vigilar, 200);
});

const metricas = async () => Object.fromEntries((await cdp.send('Performance.getMetrics')).metrics.map((m) => [m.name, m.value]));
async function escena(nombre, hacer, ms) {
    await p.evaluate(() => {
        window.__frames = [];
        window.__cambiosIsla = 0;
        let ultimo = performance.now();
        window.__midiendo = true;
        const paso = (t) => { window.__frames.push(t - ultimo); ultimo = t; if (window.__midiendo) requestAnimationFrame(paso); };
        requestAnimationFrame(paso);
    });
    const antes = await metricas();
    await hacer();
    await p.waitForTimeout(ms);
    const despues = await metricas();
    const f = await p.evaluate(() => { window.__midiendo = false; return { frames: window.__frames, isla: window.__cambiosIsla }; });
    const d = (k) => Math.round((despues[k] - antes[k]) * (k.endsWith('Duration') ? 1000 : 1));
    const fr = f.frames.slice(1);
    console.log(`${nombre.padEnd(22)} fotogramas ${String(fr.length).padStart(4)} · >20ms ${String(fr.filter((x) => x > 20).length).padStart(3)} · >50ms ${String(fr.filter((x) => x > 50).length).padStart(3)} · peor ${Math.round(Math.max(...fr))}ms · script ${d('ScriptDuration')}ms · estilos ${d('RecalcStyleDuration')}ms (${d('RecalcStyleCount')}) · maquetación ${d('LayoutDuration')}ms (${d('LayoutCount')}) · isla repintada ${f.isla}`);
}

console.log(`/kids a ${ANCHO}, CPU ×${CPU}${process.env.EXP ? ` · experimento: ${process.env.EXP}` : ''}`);
await escena('quieta arriba', async () => {}, 2000);
await escena('desplazarse', () => p.evaluate(() => new Promise((ok) => {
    let y = 0;
    const paso = () => { y += 24; window.scrollTo(0, y); if (y < 6000) requestAnimationFrame(paso); else ok(); };
    requestAnimationFrame(paso);
})), 300);
await p.evaluate(() => window.scrollTo(0, 0));
await p.waitForTimeout(600);
const menu = p.locator('[data-jw-isla-pagina] button[aria-label]').first();
await escena('abrir el menú', () => menu.click(), 900);
await escena('cerrar el menú', () => p.keyboard.press('Escape'), 900);
await escena('abrir la compra', () => p.evaluate(() => window.JumpWeb.cajon.open()), 1200);
await escena('cerrar la compra', () => p.keyboard.press('Escape'), 900);
await b.close();
