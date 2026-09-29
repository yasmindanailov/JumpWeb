/**
 * SONDA DE LOS SEGMENTOS — la sección «Segmentos de clientes» de «Analítica → Clientes» (`docs/specs/analitica.md`
 * §4.6, T4b), en el navegador real: que la pestaña la pinta con sus cinco filas y, desde la TP·3b
 * (`specs/analitica-para-decidir.md` §4.14, `#793`: el público es ANÓNIMO), que ya NO hay «Exportar segmento» ni su
 * descarga, que ninguna cifra de 1 a 4 se escribe tal cual y que la sección no lleva ni un correo. Una captura para el
 * ojo del owner.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *     docker compose exec -u sail -T -e SONDA_PANEL_EMAIL=… -e SONDA_PANEL_PASSWORD=… laravel.test \
 *         node scripts/sonda-segmentos.mjs [etiqueta]
 *   Base: `SONDA_BASE` o `http://localhost`. Salida: `storage/app/audit/segmentos-<etiqueta>*.{json,png}`.
 * ⚠️ El login del panel tiene limitador: UNA sesión por pasada.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const EMAIL = process.env.SONDA_PANEL_EMAIL ?? '';
const PASSWORD = process.env.SONDA_PANEL_PASSWORD ?? '';
const ETIQUETA = process.argv[2] ?? 'ojo';
const SALIDA = 'storage/app/audit';

if (EMAIL === '' || PASSWORD === '') {
    console.error('SONDA_PANEL_EMAIL y SONDA_PANEL_PASSWORD son obligatorias (solo por entorno).');
    process.exit(2);
}

await mkdir(SALIDA, { recursive: true });
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-ES', reducedMotion: 'reduce' });
const page = await ctx.newPage();
const comprobaciones = [];
const ok = (nombre, cond, detalle = '') => comprobaciones.push({ nombre, ok: Boolean(cond), detalle: String(detalle).slice(0, 200) });
const asentar = async (ms = 900) => { await page.mouse.move(0, 0); await page.waitForTimeout(ms); };

await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', EMAIL);
await page.fill('input[type="password"]', PASSWORD);
await Promise.all([page.waitForURL((u) => ! u.pathname.endsWith('/login'), { timeout: 20000 }), page.click('button[type="submit"]')]);
ok('login', ! page.url().endsWith('/login'), page.url());

await page.goto(`${BASE}/admin/analitica`, { waitUntil: 'networkidle' });
await page.getByRole('tab', { name: 'Clientes' }).first().click();
await page.waitForTimeout(800);
// ⚠️ Los widgets de Filament son PEREZOSOS: cargan al entrar en pantalla. Se recorre la pestaña por pantallas
// (la receta de `sonda-analitica-panel.mjs`) para que el de los segmentos, al final, llegue a pedirse.
for (let pasada = 0; pasada < 3; pasada++) {
    for (let y = 0; y < 8000; y += 600) {
        await page.evaluate((top) => window.scrollTo(0, top), y);
        await page.waitForTimeout(250);
    }
    await page.waitForTimeout(800);
}
const cabecera = page.getByText('Segmentos de clientes').first();
await cabecera.waitFor({ timeout: 30000 }).catch(() => null);
ok('la pestaña «Clientes» pinta la sección de segmentos', await cabecera.count() > 0);
await cabecera.scrollIntoViewIfNeeded();
await cabecera.click();   // la sección nace plegada
await page.waitForTimeout(600);
// Cinco desde la T3 de la fiesta (`specs/analitica-fiesta.md` §4.4): «vino invitado y luego compró».
const filas = ['Compró una vez y no volvió', 'Fiesta hace un año', 'Invitado que no ha comprado', 'Vino invitado y luego compró', 'Escribió y no tiene pedido'];
const presentes = [];
for (const f of filas) presentes.push(await page.getByText(f, { exact: false }).count() > 0);
ok('las cinco filas, con sus dos cifras', presentes.every(Boolean), JSON.stringify(presentes));
await asentar();
await page.screenshot({ path: `${SALIDA}/segmentos-${ETIQUETA}-tabla.png` });

// TP·3b: solo recuentos. Las cifras de la tabla, leídas del DOM: ninguna entre 1 y 4, y ni un correo en la sección.
const seccion = page.locator('section, .fi-section').filter({ hasText: 'Segmentos de clientes' }).last();
const celdas = await seccion.locator('td').allInnerTexts();
const cifras = celdas.map((t) => t.trim()).filter((t) => /^\d+$/.test(t)).map(Number);
ok('ninguna cifra de 1 a 4 escrita tal cual', cifras.every((n) => n === 0 || n >= 5), JSON.stringify(celdas));
ok('la sección no lleva ni un correo', ! (await seccion.innerText()).includes('@'));
ok('ya no hay botón «Exportar segmento»', await page.getByRole('button', { name: 'Exportar segmento' }).count() === 0);
const retirada = await page.evaluate(async (base) => (await fetch(`${base}/admin/analitica/segmentos/csv?segment=once_never_back`, { credentials: 'include' })).status, BASE);
ok('y su descarga ya no existe (404)', retirada === 404, String(retirada));

await browser.close();
await writeFile(`${SALIDA}/segmentos-${ETIQUETA}.json`, JSON.stringify({ base: BASE, etiqueta: ETIQUETA, comprobaciones }, null, 2));
console.table(comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 80) })));
process.exit(comprobaciones.every((c) => c.ok) ? 0 : 1);
