/**
 * SONDA DEL CUADRO DE MANDO — el navegador real entrando al panel y mirando «Analítica»
 * (`docs/specs/analitica.md` §4.5, T2a→T2f; `DECISIONES #735`, `#736`). Es el guion que va ANTES del ojo del
 * owner: que la página abra con permiso, que las tres pestañas estén y que los widgets de cada una lleguen
 * (Filament los carga en diferido), que los gráficos pinten su `canvas`, que las tablas plegadas sigan en el DOM,
 * que el filtro cambie el periodo y la comparación y que la consola quede limpia; y deja las capturas de
 * escritorio y móvil para mirarlas.
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
 * ⚠️ Los widgets cargan al entrar en pantalla (Livewire perezoso): una pestaña oculta no carga nada hasta que
 *    se abre, y dentro de ella la sonda recorre la página hasta el final antes de medir.
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
let peticionesLivewire = 0;
let paso = 'entrar';
// Un `console.error(objeto)` sale como «Object» en `text()`: se serializa cada argumento y se apunta en qué paso
// de la sonda ocurrió, o el rojo no dice nada.
page.on('console', async (m) => {
    if (m.type() !== 'error') return;
    const partes = [];
    for (const a of m.args()) {
        try {
            partes.push(await a.evaluate((o) => (o instanceof Error ? `${o.name}: ${o.message}` : typeof o === 'object' ? JSON.stringify(o).slice(0, 300) : String(o))));
        } catch {
            partes.push(String(a));
        }
    }
    const donde = m.location();
    const pila = (m.stackTrace?.() ?? []).slice(0, 3).map((f) => `${f.url?.split('/').pop()}:${f.lineNumber}`).join(' < ');
    consola.push(`[${paso}] ${partes.join(' ') || m.text()} @ ${donde?.url?.split('/').pop() ?? '?'}:${donde?.lineNumber ?? '?'} ${pila}`);
});
// Un `throw` de algo que no es `Error` llega aquí con el mensaje «Object»: se apunta también la pila.
page.on('pageerror', (e) => consola.push(`[${paso}] pageerror ${e.name}: ${e.message} · ${(e.stack ?? '').split('\n').slice(0, 4).map((l) => l.trim()).join(' | ').slice(0, 400)}`));
page.on('request', (req) => { if (req.method() === 'POST' && /livewire/.test(req.url())) peticionesLivewire++; });
// ⚠️ Un widget que revienta al cargar no rompe la página: Livewire responde 5xx a `/livewire/update` y el
// hueco se queda vacío. Sin esto, la sonda solo veía «no llega» y moría sin decir por qué.
page.on('response', async (res) => {
    if (res.status() < 500) return;
    let cuerpo = '';
    try { cuerpo = (await res.text()).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 400); } catch { /* sin cuerpo */ }
    consola.push(`HTTP ${res.status()} ${res.url()} — ${cuerpo}`);
});

const informe = { base: BASE, etiqueta: ETIQUETA, login: null, status: null, titulo: null, pestanas: {}, tablas: [], canvas: 0, periodo: {}, capturas: [], consola, comprobaciones: [] };
const ok = (nombre, cond, detalle = '') => informe.comprobaciones.push({ nombre, ok: Boolean(cond), detalle });

/** Espera a un texto y, si no llega, lo apunta como comprobación en rojo en vez de matar la sonda. */
async function llega(texto) {
    try {
        await page.getByText(texto).first().waitFor({ timeout: ESPERA_WIDGETS_MS });

        return true;
    } catch {
        ok(`llega «${texto}»`, false, `no visible en ${ESPERA_WIDGETS_MS} ms`);

        return false;
    }
}

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

/**
 * Los rótulos y valores de las tarjetas de Filament de la PESTAÑA ABIERTA. ⚠️ Las pestañas inactivas no son
 * `display: none` sino `invisible absolute h-0` (Filament), así que `offsetParent` no las distingue: se mira el
 * panel `.fi-active`.
 */
async function leerStats() {
    return page.$$eval('.fi-sc-tabs-tab.fi-active .fi-wi-stats-overview-stat', (els) => els.map((el) => ({
        label: el.querySelector('.fi-wi-stats-overview-stat-label')?.textContent?.trim() ?? '',
        value: el.querySelector('.fi-wi-stats-overview-stat-value')?.textContent?.trim() ?? '',
        description: el.querySelector('.fi-wi-stats-overview-stat-description')?.textContent?.trim() ?? '',
    })));
}

/** Baja hasta un texto si está; si no, la captura se hace donde esté la página. */
async function bajaHasta(texto) {
    const el = page.getByText(texto).first();
    if (await el.count() > 0) await el.scrollIntoViewIfNeeded();
}

/**
 * Abre una pestaña y recorre la página por PANTALLAS hasta que llega su último widget: un salto al final se
 * deja atrás los marcadores del medio, que nunca entran en el viewport y nunca cargan (medido el 24-09).
 */
async function abrirPestana(nombre, ultimoTexto) {
    paso = `pestaña ${nombre}`;
    await page.getByRole('tab', { name: nombre }).first().click();
    await page.waitForTimeout(500);
    for (let pasada = 0; pasada < 3; pasada++) {
        for (let y = 0; y < await page.evaluate(() => document.body.scrollHeight); y += 600) {
            await page.evaluate((top) => window.scrollTo(0, top), y);
            await page.waitForTimeout(350);
        }
        await page.waitForTimeout(800);
        if (await page.getByText(ultimoTexto).count() > 0) break;
    }
    await page.evaluate(() => window.scrollTo(0, 0));
    const llego = await llega(ultimoTexto);
    await asentar(1200);

    return llego;
}

/** Los `canvas` de la pestaña abierta. */
async function canvasVisibles() {
    return page.locator('.fi-sc-tabs-tab.fi-active canvas').count();
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

// 2. Abrir «Analítica»: las tres pestañas y la de «Dinero» abierta.
const respuesta = await page.goto(`${BASE}/admin/analitica`, { waitUntil: 'networkidle' });
informe.status = respuesta?.status() ?? null;
informe.titulo = await page.title();
ok('status 200', informe.status === 200, String(informe.status));
ok('tres pestañas', await page.getByRole('tab').count() === 3, String(await page.getByRole('tab').count()));
// ⚠️ Medido: las pestañas inactivas NO son `display: none` (Filament las deja `invisible absolute h-0`), así que
// el observador de intersección de Livewire da por visibles sus widgets y los pide también. Se apunta cuántas
// peticiones costó abrir la página, para que el dato esté y no se afirme lo contrario.
await page.waitForTimeout(4000);
informe.peticionesAlAbrir = peticionesLivewire;
ok('peticiones de Livewire al abrir (dato, no umbral)', true, String(peticionesLivewire));

// 3. Dinero: 12 tarjetas, 3 gráficos (la serie, por producto, por canal) y el desglose plegado.
await abrirPestana('Dinero', 'El desglose');
await llega('Cobrado online');
await llega('Vendido por producto');
informe.pestanas.dinero = { stats: await leerStats(), canvas: await canvasVisibles() };
ok('dinero: doce tarjetas', informe.pestanas.dinero.stats.length === 12, String(informe.pestanas.dinero.stats.length));
ok('dinero: tres gráficos', informe.pestanas.dinero.canvas === 3, String(informe.pestanas.dinero.canvas));
ok('dinero: el desglose nace plegado', await page.locator('section.fi-collapsed').count() >= 1);
await captura('escritorio-dinero');
await bajaHasta('Vendido por producto');
await captura('escritorio-dinero-graficos');

// 4. Clientes: 11 tarjetas (registros 3 · puerta 8), 3 gráficos (la serie, las horas, el anillo) y su desglose.
await abrirPestana('Clientes', 'Registros y puerta, al detalle');
await llega('Registros de clientes');
await llega('La puerta');
informe.pestanas.clientes = { stats: await leerStats(), canvas: await canvasVisibles() };
ok('clientes: once tarjetas', informe.pestanas.clientes.stats.length === 11, String(informe.pestanas.clientes.stats.length));
ok('clientes: tres gráficos', informe.pestanas.clientes.canvas === 3, String(informe.pestanas.clientes.canvas));
await captura('escritorio-clientes');
await bajaHasta('Búsquedas en la puerta por hora del parque');
await captura('escritorio-clientes-graficos');

// 5. Conversión: 6 tarjetas, 5 gráficos (embudo, fuentes, serie, horas, dispositivos) y tres desgloses plegados.
await abrirPestana('Conversión', 'Páginas, dispositivos, productos y contacto');
await llega('La conversión en la web');
await llega('El embudo: sesiones');
await llega('Fuentes y campañas');
informe.pestanas.conversion = { stats: await leerStats(), canvas: await canvasVisibles() };
ok('conversión: seis tarjetas', informe.pestanas.conversion.stats.length === 6, String(informe.pestanas.conversion.stats.length));
ok('conversión: cinco gráficos', informe.pestanas.conversion.canvas === 5, String(informe.pestanas.conversion.canvas));
await captura('escritorio-conversion');
await bajaHasta('Visitas por hora del parque');
await captura('escritorio-conversion-graficos');

// Las tablas siguen en el DOM, plegadas: son la vista de tabla de los gráficos y lo que lleva el CSV.
informe.tablas = await page.$$eval('h3', (els) => els.map((el) => el.textContent?.trim() ?? '').filter(Boolean));
informe.canvas = await page.locator('canvas').count();
ok('las tablas del dinero', ['Por día', 'Por canal', 'Por método de cobro', 'Por producto', 'La señal', 'Perdido'].every((t) => informe.tablas.includes(t)), informe.tablas.join(' · '));
ok('las tablas de registros y puerta', informe.tablas.includes('Cómo se registran') && informe.tablas.filter((t) => t === 'Por día').length === 2, informe.tablas.join(' · '));
ok('las tablas de la conversión', ['Paso a paso', 'Dónde se quedan', 'Por primer toque', 'Páginas de entrada', 'Contacto'].every((t) => informe.tablas.includes(t)), informe.tablas.join(' · '));
ok('once gráficos en total', informe.canvas === 11, String(informe.canvas));
const todasLasStats = [...informe.pestanas.dinero.stats, ...informe.pestanas.clientes.stats, ...informe.pestanas.conversion.stats];
ok('ninguna tarjeta vacía', todasLasStats.every((s) => s.label !== '' && s.value !== ''));
ok('la pestaña viaja en la URL', page.url().includes('pestana=traffic'), page.url());

// T2d: el botón del CSV (el admin tiene `reports.export` por `Gate::before`) y la descarga de verdad, con la
// sesión del navegador: estado, tipo, el BOM que abre bien la hoja de cálculo y la línea de comparación (T2f).
ok('el botón «Descargar CSV»', await page.getByText('Descargar CSV').count() > 0);
for (const informeCsv of ['money', 'customers', 'funnel']) {
    const csv = await page.request.get(`${BASE}/admin/analitica/csv?report=${informeCsv}&period=this_month&compare=year_ago`);
    const cuerpo = await csv.text();
    ok(`CSV «${informeCsv}»`, csv.status() === 200 && (csv.headers()['content-type'] ?? '').startsWith('text/csv') && cuerpo.startsWith('﻿') && cuerpo.includes('Resumen') && cuerpo.includes('Comparado con'), `${csv.status()} ${csv.headers()['content-type'] ?? ''} ${cuerpo.length} B`);
}

// 6. Móvil, en la MISMA sesión: la pestaña del dinero y la de la conversión.
paso = 'móvil';
await page.setViewportSize({ width: 390, height: 844 });
await page.getByRole('tab', { name: 'Dinero' }).first().click();
await page.waitForTimeout(500);
await page.evaluate(() => window.scrollTo(0, 0));
await captura('movil-dinero');
await bajaHasta('Vendido por producto');
await captura('movil-dinero-graficos');
await page.getByRole('tab', { name: 'Conversión' }).first().click();
await page.waitForTimeout(500);
await bajaHasta('El embudo: sesiones');
await captura('movil-conversion');
const anchoDoc = await page.evaluate(() => document.documentElement.scrollWidth);
ok('sin scroll horizontal en móvil', anchoDoc <= 390, String(anchoDoc));

// 7. El filtro: a 90 días el desglose pasa a semanas; a «este año», a meses; y la comparación con el año pasado.
paso = 'filtro';
await page.setViewportSize({ width: 1440, height: 900 });
await page.getByRole('tab', { name: 'Dinero' }).first().click();
await page.waitForTimeout(500);
await page.evaluate(() => window.scrollTo(0, 0));
const periodo = page.locator('select').nth(0);
const comparar = page.locator('select').nth(1);
await periodo.selectOption('last_90');
const porSemana = await llega('Por semana');
await asentar(1200);
ok('90 días agrupa por semana', porSemana);
await captura('escritorio-90-dias');

await periodo.selectOption('this_year');
const porMes = await llega('Por mes');
await asentar(1500);
ok('el año agrupa por mes', porMes);
await captura('escritorio-anio');

await comparar.selectOption('year_ago');
// Con datos del año pasado dice «frente al año pasado»; sin ellos, «Sin datos el año pasado». Las dos valen.
let vsAnio = true;
try {
    await page.locator('.fi-sc-tabs-tab.fi-active .fi-wi-stats-overview-stat-description', { hasText: 'año pasado' }).first().waitFor({ timeout: ESPERA_WIDGETS_MS });
} catch {
    vsAnio = false;
}
await asentar(1200);
informe.periodo = { periodo: await periodo.inputValue(), comparar: await comparar.inputValue(), stats: await leerStats() };
ok('comparar con el año pasado', vsAnio && informe.periodo.stats.some((s) => s.description.includes('año pasado')), informe.periodo.stats.map((s) => s.description).join(' | ').slice(0, 200));
await captura('escritorio-anio-vs-anterior');

await periodo.selectOption('custom');
const desde = await llega('Desde');
ok('a medida enseña las dos fechas', desde && await page.getByText('Hasta').count() > 0);
await captura('escritorio-a-medida');

ok('consola limpia', consola.length === 0, consola.join(' | '));
if (consola.length > 0) console.log('consola:\n  ' + consola.join('\n  '));

await browser.close();

await writeFile(`${SALIDA}/analitica-panel-${ETIQUETA}.json`, JSON.stringify(informe, null, 2));

console.table(informe.comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 80) })));
console.log(`capturas: ${informe.capturas.join(', ')}`);
process.exit(informe.comprobaciones.every((c) => c.ok) ? 0 : 1);
