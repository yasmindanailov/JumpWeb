/**
 * SONDA DE LOS HUECOS DE LA LISTA (P2 de `docs/specs/fiesta-sistema-nuevo.md` §4.20, `[DECIDIDO owner]` `#913`: «en un grupo con
 * tarjetas impares, la última a lo ancho»). Por cada rejilla de la zona 4 (`.pli-grid2`, las tarjetas de un grupo; `.pli-grupos`,
 * los grupos de «Para los niños») y cada FILA (lo que empieza a la misma altura): una pieza sola en su fila tiene que ocupar el
 * ancho entero; si no, deja un hueco al lado, y eso falla. A 1280 (dos columnas) y a 390 (una: nunca hay hueco).
 * Con `--control`, la misma medida con la regla ANULADA en la página (sin tocar el repo): tiene que encontrar los huecos, o el
 * instrumento no mide nada.
 *
 *   docker compose exec -u sail -T laravel.test node scripts/sonda-lista-huecos.mjs "<url de la lista>" [--control]
 */
import { chromium } from 'playwright-core';

const [URL_LISTA, ...resto] = process.argv.slice(2);
const control = resto.includes('--control');
const ANULA = '.pli-grid2>article.fi-complemento,.pli-grupos>.pli-fam--ancha{grid-column:auto!important}';

const browser = await chromium.launch();
const huecos = [];
const medidas = [];
for (const [ancho, alto] of [[1280, 900], [390, 844]]) {
    const page = await (await browser.newContext({ viewport: { width: ancho, height: alto }, locale: 'es-ES' })).newPage();
    const r = await page.goto(URL_LISTA, { waitUntil: 'networkidle' });
    if (control) await page.addStyleTag({ content: ANULA });
    await page.waitForTimeout(300);
    const filas = await page.evaluate(() => {
        const out = [];
        const rejillas = [...document.querySelectorAll('[data-zona="4"] .pli-grid2, [data-zona="4"] .pli-grupos')];
        for (const g of rejillas) {
            const piezas = [...g.children].filter((c) => c.matches('article.fi-complemento, .pli-fam') && c.getClientRects().length > 0);
            const caja = g.getBoundingClientRect();
            const porFila = new Map();
            for (const p of piezas) {
                const b = p.getBoundingClientRect();
                const y = Math.round(b.top);
                porFila.set(y, [...(porFila.get(y) ?? []), { nombre: p.dataset.nombre ?? p.querySelector('[data-nombre]')?.dataset.nombre ?? '?', ancho: Math.round(b.width) }]);
            }
            for (const [, fila] of porFila) out.push({ rejilla: g.className, cajaAncho: Math.round(caja.width), fila });
        }
        return out;
    });
    for (const f of filas) {
        const sola = f.fila.length === 1 ? f.fila[0] : null;
        medidas.push({ ancho, rejilla: f.rejilla, piezas: f.fila.map((p) => `${p.nombre}(${p.ancho})`).join(' + '), caja: f.cajaAncho });
        if (sola && sola.ancho < f.cajaAncho - 2) huecos.push(`${ancho} px · ${f.rejilla}: «${sola.nombre}» sola con ${sola.ancho} de ${f.cajaAncho} px`);
    }
    console.log(`${ancho} px · HTTP ${r?.status()} · ${filas.length} filas medidas`);
}
await browser.close();

for (const m of medidas) console.log(`  ${m.ancho} · ${m.rejilla.padEnd(26)} · ${m.piezas} (caja ${m.caja})`);
if (control) {
    console.log(huecos.length > 0 ? `✓ CONTROL: con la regla anulada, ${huecos.length} huecos (el instrumento los ve)` : '✗ CONTROL: sin la regla tampoco ve huecos: no mide nada');
    process.exit(huecos.length > 0 ? 0 : 1);
}
for (const h of huecos) console.log(`✗ hueco: ${h}`);
console.log(huecos.length === 0 ? '✓ ninguna pieza sola deja hueco' : `✗ ${huecos.length} huecos`);
process.exit(huecos.length === 0 ? 0 : 1);
