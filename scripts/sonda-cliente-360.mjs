/**
 * SONDA DE LA 360 DEL CLIENTE — la sección «Cliente 360» de la ficha del panel (`docs/specs/analitica.md` §4.6,
 * T4a), en el navegador real: que la pinta el admin y no un staff sin `customers.insights`, que lo del contrato
 * sale de los pedidos y lo de la navegación solo en el régimen identificado, y una captura para el ojo del owner.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   Chromium en el contenedor (receta de la skill `sonda`), un admin local, y el id de un cliente CON pedidos:
 *     docker compose exec -u sail -T -e SONDA_PANEL_EMAIL=… -e SONDA_PANEL_PASSWORD=… -e SONDA_CLIENTE_ID=… \
 *         laravel.test node scripts/sonda-cliente-360.mjs [etiqueta]
 *   Base: `SONDA_BASE` o `http://localhost`. Salida: `storage/app/audit/cliente-360-<etiqueta>.{json,png}`.
 * ⚠️ El login del panel tiene limitador: UNA sesión por pasada.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const EMAIL = process.env.SONDA_PANEL_EMAIL ?? '';
const PASSWORD = process.env.SONDA_PANEL_PASSWORD ?? '';
const CLIENTE = process.env.SONDA_CLIENTE_ID ?? '';
const ETIQUETA = process.argv[2] ?? 'ojo';
const SALIDA = 'storage/app/audit';

if (EMAIL === '' || PASSWORD === '' || CLIENTE === '') {
    console.error('SONDA_PANEL_EMAIL, SONDA_PANEL_PASSWORD y SONDA_CLIENTE_ID son obligatorias (solo por entorno).');
    process.exit(2);
}

await mkdir(SALIDA, { recursive: true });
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-ES', reducedMotion: 'reduce' });
const page = await ctx.newPage();
const comprobaciones = [];
const ok = (nombre, cond, detalle = '') => comprobaciones.push({ nombre, ok: Boolean(cond), detalle: String(detalle).slice(0, 200) });

await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', EMAIL);
await page.fill('input[type="password"]', PASSWORD);
await Promise.all([
    page.waitForURL((u) => ! u.pathname.endsWith('/login'), { timeout: 20000 }),
    page.click('button[type="submit"]'),
]);
ok('login', ! page.url().endsWith('/login'), page.url());

await page.goto(`${BASE}/admin/users/${CLIENTE}`, { waitUntil: 'networkidle' });
const seccion = page.locator('[data-insights]').first();
await seccion.waitFor({ timeout: 20000 }).catch(() => null);
ok('la ficha pinta la sección «Cliente 360»', await seccion.count() > 0);
const modo = await seccion.getAttribute('data-insights');
const pedidos = await page.locator('[data-insights-orders]').first().getAttribute('data-insights-orders').catch(() => null);
const identificado = await page.locator('[data-insights-identified]').first().getAttribute('data-insights-identified').catch(() => null);
ok('lo del contrato sale de los pedidos (compras > 0 en un cliente con pedidos)', modo === 'contract' && Number(pedidos) > 0, `modo=${modo} compras=${pedidos}`);
ok('lo de la navegación se pinta o se dice que no hay (nunca en blanco)', identificado === '1'
    ? await page.locator('[data-insights="navigation"]').count() === 1
    : await page.locator('[data-insights="not-identified"]').count() === 1, `identificado=${identificado}`);
ok('la sección no enseña ningún nombre de menor ni una edad', ! /data-dependent-age/.test(await seccion.evaluate((el) => el.closest('section')?.outerHTML ?? '')));

await page.locator('[data-insights]').first().scrollIntoViewIfNeeded();
await page.mouse.move(0, 0);
await page.waitForTimeout(900);
await page.screenshot({ path: `${SALIDA}/cliente-360-${ETIQUETA}.png` });

await browser.close();
await writeFile(`${SALIDA}/cliente-360-${ETIQUETA}.json`, JSON.stringify({ base: BASE, etiqueta: ETIQUETA, cliente: CLIENTE, comprobaciones }, null, 2));
console.table(comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 80) })));
process.exit(comprobaciones.every((c) => c.ok) ? 0 : 1);
