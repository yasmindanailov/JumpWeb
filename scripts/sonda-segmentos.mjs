/**
 * SONDA DE LOS SEGMENTOS — la sección «Segmentos de clientes» de «Analítica → Clientes» y su exportación
 * (`docs/specs/analitica.md` §4.6, T4b), en el navegador real: que la pestaña la pinta con sus cuatro filas, que el
 * botón «Exportar segmento» abre su modal, que la descarga responde un CSV con cabecera y `no-store`, y dos
 * capturas para el ojo del owner.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *     docker compose exec -u sail -T -e SONDA_PANEL_EMAIL=… -e SONDA_PANEL_PASSWORD=… laravel.test \
 *         node scripts/sonda-segmentos.mjs [etiqueta]
 *   Base: `SONDA_BASE` o `http://localhost`. Salida: `storage/app/audit/segmentos-<etiqueta>*.{json,png}`.
 * ⚠️ El login del panel tiene limitador: UNA sesión por pasada. ⚠️ La descarga deja una fila de auditoría en la
 *    BD local (`segments.exported`), como haría un operador.
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
const filas = ['Compró una vez y no volvió', 'Fiesta hace un año', 'Invitado que no ha comprado', 'Escribió y no tiene pedido'];
const presentes = [];
for (const f of filas) presentes.push(await page.getByText(f, { exact: false }).count() > 0);
ok('las cuatro filas, con sus dos cifras', presentes.every(Boolean), JSON.stringify(presentes));
await asentar();
await page.screenshot({ path: `${SALIDA}/segmentos-${ETIQUETA}-tabla.png` });

const boton = page.getByRole('button', { name: 'Exportar segmento' }).first();
ok('el botón «Exportar segmento» está (el admin tiene el permiso)', await boton.count() > 0);
await boton.click();
const modal = page.getByText('Exportar un segmento de clientes').first();
await modal.waitFor({ timeout: 10000 }).catch(() => null);
ok('abre su modal con el selector de segmento', await modal.count() > 0 && await page.getByText('Solo las personas con opt-in').count() > 0);
await asentar(600);
await page.screenshot({ path: `${SALIDA}/segmentos-${ETIQUETA}-modal.png` });

// La descarga, con la sesión del navegador: un CSV con su cabecera y sin caché.
const csv = await page.evaluate(async (base) => {
    const res = await fetch(`${base}/admin/analitica/segmentos/csv?segment=once_never_back`, { credentials: 'include' });
    return { status: res.status, type: res.headers.get('content-type'), cache: res.headers.get('cache-control'), head: (await res.text()).split('\n')[0] };
}, BASE);
ok('la descarga responde un CSV', csv.status === 200 && /text\/csv/.test(csv.type ?? ''), JSON.stringify(csv));
ok('con la cabecera nombre;correo;teléfono;última compra', /Nombre;Correo;Tel/.test(csv.head), csv.head);
ok('y sin caché (`no-store`)', /no-store/.test(csv.cache ?? ''), csv.cache ?? '');
const desconocido = await page.evaluate(async (base) => (await fetch(`${base}/admin/analitica/segmentos/csv?segment=todos`, { credentials: 'include' })).status, BASE);
ok('un segmento desconocido es 404', desconocido === 404, String(desconocido));

await browser.close();
await writeFile(`${SALIDA}/segmentos-${ETIQUETA}.json`, JSON.stringify({ base: BASE, etiqueta: ETIQUETA, comprobaciones }, null, 2));
console.table(comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 80) })));
process.exit(comprobaciones.every((c) => c.ok) ? 0 : 1);
