/**
 * SONDA DEL CUADRO DE MANDO — el navegador real entrando al panel y mirando «Analítica»
 * (`docs/specs/analitica.md` §4.5, T2a; `DECISIONES #735`). Es el guion que va ANTES del ojo del owner: que la
 * página abra con permiso, que los cuatro widgets lleguen (Filament los carga en diferido), que el gráfico
 * pinte un `canvas`, que el desglose traiga sus seis tablas, que el filtro cambie el periodo y que la consola
 * quede limpia; y deja las capturas de escritorio y móvil para mirarlas.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   Chromium en el contenedor (receta de la skill `sonda`), un admin local, y después:
 *     docker compose exec -u sail -T -e SONDA_PANEL_EMAIL=… -e SONDA_PANEL_PASSWORD=… laravel.test \
 *         node scripts/sonda-analitica-panel.mjs [etiqueta]
 *   Base: `SONDA_BASE` o `http://localhost` (el `80` del contenedor; el panel no necesita el puente).
 *   Salida: `storage/app/audit/analitica-panel-<etiqueta>.json` y las capturas al lado (gitignorado).
 *
 * ⚠️ Las credenciales van SOLO por entorno: no hay defecto y no viajan en el repo.
 * ⚠️ El login del panel tiene limitador: UNA sesión y se cambia el viewport dentro de ella, nunca una entrada
 *    por tamaño. Si mide la pantalla de LOGIN, es el limitador: `php artisan cache:clear`.
 * ⚠️ Capturas de VENTANA con el ratón apartado y tras esperar a que Livewire asiente; nunca `fullPage`.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const EMAIL = process.env.SONDA_PANEL_EMAIL ?? '';
const PASSWORD = process.env.SONDA_PANEL_PASSWORD ?? '';
const ETIQUETA = process.argv[2] ?? 'ojo';
const SALIDA = 'storage/app/audit';
const ESPERA_WIDGETS_MS = 30000;

if (EMAIL === '' || PASSWORD === '') {
    console.error('SONDA_PANEL_EMAIL y SONDA_PANEL_PASSWORD son obligatorias (solo por entorno).');
    process.exit(2);
}

await mkdir(SALIDA, { recursive: true });

const browser = await chromium.launch();
const contexto = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-ES', reducedMotion: 'reduce' });
const page = await contexto.newPage();

const consola = [];
page.on('console', (m) => { if (m.type() === 'error') consola.push(m.text()); });
page.on('pageerror', (e) => consola.push(String(e)));

const informe = { base: BASE, etiqueta: ETIQUETA, login: null, status: null, titulo: null, stats: [], tablas: [], canvas: 0, periodo: {}, capturas: [], consola, comprobaciones: [] };
const ok = (nombre, cond, detalle = '') => informe.comprobaciones.push({ nombre, ok: Boolean(cond), detalle });

async function asentar(ms = 900) {
    await page.mouse.move(0, 0);
    await page.waitForTimeout(ms);
}

async function captura(nombre) {
    const ruta = `${SALIDA}/analitica-panel-${ETIQUETA}-${nombre}.png`;
    await asentar();
    await page.screenshot({ path: ruta });
    informe.capturas.push(ruta);
}

/** Los rótulos y valores de las tarjetas de Filament, tal y como los ve el owner. */
async function leerStats() {
    return page.$$eval('.fi-wi-stats-overview-stat', (els) => els.map((el) => ({
        label: el.querySelector('.fi-wi-stats-overview-stat-label')?.textContent?.trim() ?? '',
        value: el.querySelector('.fi-wi-stats-overview-stat-value')?.textContent?.trim() ?? '',
        description: el.querySelector('.fi-wi-stats-overview-stat-description')?.textContent?.trim() ?? '',
    })));
}

// 1. Entrar, una sola vez.
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', EMAIL);
await page.fill('input[type="password"]', PASSWORD);
await Promise.all([
    page.waitForURL((u) => ! u.pathname.endsWith('/login'), { timeout: 20000 }),
    page.click('button[type="submit"]'),
]);
informe.login = page.url();
ok('login', ! page.url().endsWith('/login'), page.url());

// 2. Abrir «Analítica» y esperar a los widgets diferidos.
const respuesta = await page.goto(`${BASE}/admin/analitica`, { waitUntil: 'networkidle' });
informe.status = respuesta?.status() ?? null;
informe.titulo = await page.title();
ok('status 200', informe.status === 200, String(informe.status));

await page.getByText('Cobrado online').first().waitFor({ timeout: ESPERA_WIDGETS_MS });
await page.locator('canvas').first().waitFor({ timeout: ESPERA_WIDGETS_MS });
await page.getByText('El desglose').first().waitFor({ timeout: ESPERA_WIDGETS_MS });
await asentar(1200);

informe.stats = await leerStats();
informe.canvas = await page.locator('canvas').count();
informe.tablas = await page.$$eval('h3', (els) => els.map((el) => el.textContent?.trim() ?? '').filter(Boolean));
ok('doce tarjetas (dinero + clientes)', informe.stats.length === 12, String(informe.stats.length));
ok('un gráfico', informe.canvas >= 1, String(informe.canvas));
ok('seis tablas del desglose', ['Por día', 'Por canal', 'Por método de cobro', 'Por producto', 'La señal', 'Perdido'].every((t) => informe.tablas.includes(t)), informe.tablas.join(' · '));
ok('ninguna tarjeta vacía', informe.stats.every((s) => s.label !== '' && s.value !== ''));

// 3. Capturas de escritorio: arriba, y bajando hasta el desglose.
await captura('escritorio-arriba');
await page.getByText('El desglose').first().scrollIntoViewIfNeeded();
await captura('escritorio-desglose');

// 4. Móvil, en la MISMA sesión.
await page.setViewportSize({ width: 390, height: 844 });
await page.evaluate(() => window.scrollTo(0, 0));
await captura('movil-arriba');
await page.getByText('Por canal').first().scrollIntoViewIfNeeded();
await captura('movil-desglose');
const anchoDoc = await page.evaluate(() => document.documentElement.scrollWidth);
ok('sin scroll horizontal en móvil', anchoDoc <= 390, String(anchoDoc));

// 5. El filtro: a 90 días, el desglose pasa a semanas.
await page.setViewportSize({ width: 1440, height: 900 });
await page.evaluate(() => window.scrollTo(0, 0));
const select = page.locator('select').first();
await select.selectOption('last_90');
await page.getByText('Por semana').first().waitFor({ timeout: ESPERA_WIDGETS_MS });
await asentar(1200);
informe.periodo = { valor: await select.inputValue(), stats: await leerStats(), tablas: await page.$$eval('h3', (els) => els.map((el) => el.textContent?.trim() ?? '')) };
ok('90 días agrupa por semana', informe.periodo.tablas.includes('Por semana'));
await captura('escritorio-90-dias');

ok('consola limpia', consola.length === 0, consola.join(' | '));

await browser.close();

await writeFile(`${SALIDA}/analitica-panel-${ETIQUETA}.json`, JSON.stringify(informe, null, 2));

console.table(informe.comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 80) })));
console.log(`capturas: ${informe.capturas.join(', ')}`);
process.exit(informe.comprobaciones.every((c) => c.ok) ? 0 : 1);
