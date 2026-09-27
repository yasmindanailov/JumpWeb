/**
 * SONDA DEL CUADRO DE MANDO — el navegador real entrando al panel y mirando «Analítica»
 * (`docs/specs/analitica.md` §4.5; la forma, la T3a de `analitica-para-decidir.md` §4.13, `DECISIONES #759`). Es el guion
 * que va ANTES del ojo del owner: que la página abra con permiso; que al abrir SOLO pida lo de «Resumen» (medido el 28-09:
 * pedía los widgets de las seis pestañas); que cada una de las SIETE pestañas lleve su pregunta en la cabecera, como mucho
 * seis tarjetas arriba y tres gráficos, lo demás plegado —y plegado de verdad— y su CSV al pie; que no quede jerga fuera de
 * «Calidad del dato»; que en el móvil manden el selector nativo y la píldora del filtro y la primera cifra caiga en la
 * primera pantalla (también en la tableta del panel); que el filtro cambie el periodo y la comparación, y que la consola
 * quede limpia. Deja las capturas de escritorio, tableta y móvil para mirarlas.
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
 * ⚠️ Los widgets cargan al entrar en pantalla (Livewire perezoso), y desde la T3a solo existen los de la pestaña abierta:
 *    la sonda abre cada una y la recorre hasta el final antes de medir.
 * ⚠️ Tras entrar, se espera a que la página de después del login («Hoy») termine sus peticiones: si no, las suyas se
 *    cuentan como del cuadro (el instrumento lo hizo el 28-09: 12 en vez de 10).
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const EMAIL = process.env.SONDA_PANEL_EMAIL ?? '';
const PASSWORD = process.env.SONDA_PANEL_PASSWORD ?? '';
const ETIQUETA = process.argv[2] ?? 'ojo';
const SALIDA = 'storage/app/audit';
const ESPERA_WIDGETS_MS = 30000;

/**
 * Lo que se espera de cada pestaña (T3a, §4.13): su nombre, su pregunta, cuántas tarjetas arriba, cuántos grupos plegados de
 * tarjetas, cuántos gráficos (`null`: uno o ninguno, según haya datos), el informe de su CSV y el último texto que carga.
 */
const PESTANAS = {
    summary: { nombre: 'Resumen', pregunta: '¿Cómo vamos?', arriba: 7, plegados: 0, graficos: 0, csv: null, ultimo: 'Ver en Satisfacción' },
    money: { nombre: 'Dinero', pregunta: '¿Cuánto ganamos y de qué?', arriba: 6, plegados: 1, graficos: 3, csv: 'money', ultimo: 'El desglose' },
    occupancy: { nombre: 'Ocupación', pregunta: '¿Cómo de lleno está el parque, y cuándo?', arriba: 6, plegados: 0, graficos: null, csv: 'occupancy', ultimo: 'La ocupación, al detalle' },
    customers: { nombre: 'Clientes', pregunta: '¿Quién viene y quién vuelve?', arriba: 6, plegados: 3, graficos: 3, csv: 'customers', ultimo: 'Registros y puerta, al detalle' },
    marketing: { nombre: 'Marketing', pregunta: '¿Qué trae visitas y ventas?', arriba: 2, plegados: 2, graficos: 3, csv: 'funnel', ultimo: 'Calidad del dato' },
    parties: { nombre: 'Fiestas', pregunta: '¿Cómo van los cumpleaños?', arriba: 6, plegados: 1, graficos: 3, csv: 'parties', ultimo: 'La fiesta, al detalle' },
    satisfaction: { nombre: 'Satisfacción', pregunta: '¿Están contentos?', arriba: 2, plegados: 1, graficos: null, csv: 'surveys', ultimo: 'Las encuestas, al detalle' },
};

/** Las tablas (plegadas, en el DOM) que cada pestaña tiene que llevar. */
const TABLAS = {
    money: ['Por día', 'Por canal', 'Por método de cobro', 'Por producto', 'La señal', 'Perdido'],
    customers: ['Cómo se registran', 'Por día'],
    marketing: ['Paso a paso', 'Dónde se quedan', 'Cómo llegaron la primera vez', 'Páginas de entrada', 'Visitas por hora del parque', 'Dispositivo', 'Contacto'],
    parties: ['El dinero de después de reservar', 'Por complemento', 'La invitación y el justificante', 'Tiempos', 'Los invitados: dispositivo e idioma'],
    satisfaction: ['Por encuesta', 'Notas bajas frente al resto'],
};

/** La jerga de §4.11: fuera de «Calidad del dato» no puede salir (la misma lista que `AnalyticsJargonTest`). */
const JERGA = [/\bsesi(?:ón|ones)\b/iu, /\bprimer toque\b/iu, /\búltimo toque\b/iu, /\banterior a la medición\b/iu, /\bsistema\b/iu, /\bfuera del recuento\b/iu, /\bbots?\b/iu, /\binternas?\b/iu, /\bembudo\b/iu];

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
    consola.push(`[${paso}] ${partes.join(' ') || m.text()} @ ${donde?.url?.split('/').pop() ?? '?'}:${donde?.lineNumber ?? '?'}`);
});
page.on('pageerror', (e) => consola.push(`[${paso}] pageerror ${e.name}: ${e.message} · ${(e.stack ?? '').split('\n').slice(0, 4).map((l) => l.trim()).join(' | ').slice(0, 400)}`));
// …y como «Object» no dice nada (27-09, T0b: siete de golpe), la página misma vuelca QUÉ se lanzó.
await page.addInitScript(() => {
    const vuelca = (etiqueta, valor) => {
        let texto;
        try { texto = JSON.stringify(valor, Object.getOwnPropertyNames(valor ?? {})).slice(0, 400); } catch { texto = String(valor); }
        console.error(`${etiqueta} ${valor?.constructor?.name ?? typeof valor} ${texto}`);
    };
    window.addEventListener('unhandledrejection', (ev) => vuelca('rechazo sin atender:', ev.reason));
    window.addEventListener('error', (ev) => { if (! (ev.error instanceof Error)) vuelca('lanzado:', ev.error); });
});
page.on('request', (req) => { if (req.method() === 'POST' && /livewire/.test(req.url())) peticionesLivewire++; });
// ⚠️ Un widget que revienta al cargar no rompe la página: Livewire responde 5xx y el hueco se queda vacío.
page.on('response', async (res) => {
    if (res.status() < 500) return;
    let cuerpo = '';
    try { cuerpo = (await res.text()).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 400); } catch { /* sin cuerpo */ }
    consola.push(`HTTP ${res.status()} ${res.url()} — ${cuerpo}`);
});

const informe = { base: BASE, etiqueta: ETIQUETA, login: null, status: null, titulo: null, abrir: null, pestanas: {}, tamanos: {}, periodo: {}, capturas: [], consola, comprobaciones: [] };
const ok = (nombre, cond, detalle = '') => informe.comprobaciones.push({ nombre, ok: Boolean(cond), detalle: String(detalle) });

/** Espera a un texto VISIBLE y, si no llega, lo apunta en rojo en vez de matar la sonda. */
async function llega(texto) {
    try {
        // ⚠️ Solo una coincidencia VISIBLE (T0b): `getByText` busca por subcadena, y un «¿Cómo se calcula?» plegado casaba.
        await page.getByText(texto).and(page.locator(':visible')).first().waitFor({ timeout: ESPERA_WIDGETS_MS });

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

/** Recorre la página por PANTALLAS hasta que llega su último widget (un salto al final deja sin cargar los del medio). */
async function recorrer(ultimoTexto) {
    for (let pasada = 0; pasada < 3; pasada++) {
        for (let y = 0; y < await page.evaluate(() => document.body.scrollHeight); y += 600) {
            await page.evaluate((top) => window.scrollTo(0, top), y);
            await page.waitForTimeout(350);
        }
        await page.waitForTimeout(800);
        if (await page.getByText(ultimoTexto).count() > 0) break;
    }
}

/** Lo que hay en la pestaña abierta (desde la T3a es la ÚNICA pintada). */
async function leerPestana() {
    return page.evaluate(() => {
        const tabs = document.querySelector('.fi-sc-tabs');
        const arriba = tabs?.querySelector('[data-analytics-group="top"]');
        const plegados = [...(tabs?.querySelectorAll('[data-analytics-group="folded"]') ?? [])];
        const calidad = [...(tabs?.querySelectorAll('[data-analytics-group="folded"]') ?? [])].find((g) => g.textContent.includes('Calidad del dato'));
        const texto = (tabs?.innerText ?? '').replace(calidad?.innerText ?? '\u0000', '');

        return {
            url: location.search,
            pregunta: document.querySelector('.fi-header-subheading')?.textContent?.trim() ?? '',
            arriba: arriba ? [...arriba.querySelectorAll('.fi-wi-stats-overview-stat')].map((s) => ({
                label: s.querySelector('.fi-wi-stats-overview-stat-label')?.textContent?.trim() ?? '',
                value: s.querySelector('.fi-wi-stats-overview-stat-value')?.textContent?.trim() ?? '',
            })) : [],
            plegados: plegados.length,
            plegadosCerrados: plegados.filter((g) => g.querySelector('.fi-section.fi-collapsed')).length,
            graficos: tabs ? tabs.querySelectorAll('canvas').length : 0,
            tablas: [...(tabs?.querySelectorAll('h3') ?? [])].map((h) => h.textContent?.trim() ?? ''),
            csv: [...(tabs?.querySelectorAll('a') ?? [])].filter((a) => /analitica\/csv/.test(a.href)).map((a) => new URL(a.href).searchParams.get('report')),
            segmento: [...(tabs?.querySelectorAll('button') ?? [])].some((b) => b.textContent.includes('Exportar segmento')),
            texto,
        };
    });
}

// 1. Entrar, una sola vez, y esperar a que «Hoy» termine.
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', EMAIL);
await page.fill('input[type="password"]', PASSWORD);
await Promise.all([
    page.waitForURL((u) => ! u.pathname.endsWith('/login'), { timeout: 20000 }),
    page.click('button[type="submit"]'),
]);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(3000);
informe.login = page.url();
ok('login', ! page.url().endsWith('/login'), page.url());

// 2. Abrir «Analítica»: siete pestañas, «Resumen» abierta, y SOLO lo suyo pedido.
peticionesLivewire = 0;
paso = 'abrir';
const respuesta = await page.goto(`${BASE}/admin/analitica`, { waitUntil: 'networkidle' });
informe.status = respuesta?.status() ?? null;
informe.titulo = await page.title();
ok('status 200', informe.status === 200, informe.status);
ok('siete pestañas', await page.getByRole('tab').count() === 7, await page.getByRole('tab').count());
await page.waitForTimeout(4000);
informe.abrir = peticionesLivewire;
// Antes de la T3a, 10 (los widgets de las seis pestañas). Ahora, el de «Resumen» (y ninguno de otra).
ok('al abrir solo pide lo de «Resumen» (≤ 2 peticiones)', peticionesLivewire <= 2, peticionesLivewire);

// 3. Cada pestaña, en escritorio.
for (const [clave, p] of Object.entries(PESTANAS)) {
    paso = `pestaña ${clave}`;
    peticionesLivewire = 0;
    await page.getByRole('tab', { name: p.nombre }).first().click();
    await page.waitForTimeout(700);
    await recorrer(p.ultimo);
    await page.evaluate(() => window.scrollTo(0, 0));
    await llega(p.ultimo);
    await asentar(1200);
    const leido = await leerPestana();
    const jerga = JERGA.map((re) => leido.texto.match(re)?.[0]).filter(Boolean);
    delete leido.texto;
    informe.pestanas[clave] = { ...leido, peticiones: peticionesLivewire, jerga };

    // La de por defecto no se escribe (Livewire omite el valor inicial): `/admin/analitica` a secas ya abre «Resumen».
    ok(`${clave}: en la URL`, clave === 'summary' ? ! leido.url.includes('pestana=') || leido.url.includes('pestana=summary') : leido.url.includes(`pestana=${clave}`), leido.url);
    ok(`${clave}: su pregunta`, leido.pregunta === p.pregunta, leido.pregunta);
    ok(`${clave}: ${p.arriba} tarjetas arriba`, leido.arriba.length === p.arriba, leido.arriba.map((s) => s.label).join(' · '));
    ok(`${clave}: ninguna tarjeta vacía`, leido.arriba.every((s) => s.label !== '' && s.value !== ''));
    ok(`${clave}: ${p.plegados} grupos plegados, y nacen plegados`, leido.plegados === p.plegados && leido.plegadosCerrados === p.plegados, `${leido.plegados} · cerrados ${leido.plegadosCerrados}`);
    ok(`${clave}: ${p.graficos ?? 'uno o ningún'} gráfico(s), tres como mucho`, p.graficos === null ? leido.graficos <= 1 : leido.graficos === p.graficos, leido.graficos);
    ok(`${clave}: su CSV al pie`, p.csv === null ? leido.csv.length === 0 : JSON.stringify(leido.csv) === JSON.stringify([p.csv]), leido.csv.join(','));
    ok(`${clave}: sin jerga fuera de «Calidad del dato»`, jerga.length === 0, jerga.join(', '));
    for (const tabla of TABLAS[clave] ?? []) {
        ok(`${clave}: la tabla «${tabla}»`, leido.tablas.includes(tabla), leido.tablas.join(' · ').slice(0, 200));
    }
    await captura(`escritorio-${clave}`);
}
ok('«Exportar segmento», solo en Clientes', informe.pestanas.customers.segmento && Object.entries(informe.pestanas).every(([k, v]) => k === 'customers' || ! v.segmento));

// 3 bis. La ocupación: el mapa de calor escribe el % en cada celda, con su tooltip.
paso = 'mapa';
await page.getByRole('tab', { name: 'Ocupación' }).first().click();
await page.waitForTimeout(700);
await recorrer('La ocupación, al detalle');
const celdas = await page.$$eval('[data-occupancy-heatmap] td', (tds) => tds.map((td) => td.textContent?.trim() ?? ''));
ok('ocupación: el mapa escribe el % en cada celda (o «—» sin franjas)', celdas.length > 0 && celdas.every((t) => /^\d{1,3} %$/.test(t) || t === '—'), `${celdas.length} celdas`);
ok('ocupación: cada celda lleva su tooltip', await page.$$eval('[data-occupancy-heatmap] td', (tds) => tds.every((td) => (td.getAttribute('title') ?? '') !== '')));

// 3 ter. Un grupo plegado se abre al tocarlo.
paso = 'plegado';
await page.getByRole('tab', { name: 'Dinero' }).first().click();
await page.waitForTimeout(700);
await recorrer('El desglose');
await page.getByText('Más del dinero').first().click();
await page.waitForTimeout(600);
ok('un grupo plegado se abre al tocarlo', await page.getByText('Gestiones posteriores').and(page.locator(':visible')).count() > 0);
await page.getByText('Más del dinero').first().scrollIntoViewIfNeeded();
await captura('escritorio-money-plegado-abierto');

// 4. Los CSV, con la sesión del navegador: estado, tipo, BOM y la línea de comparación.
for (const informeCsv of ['money', 'occupancy', 'customers', 'funnel', 'parties', 'surveys']) {
    const csv = await page.request.get(`${BASE}/admin/analitica/csv?report=${informeCsv}&period=this_month&compare=year_ago`);
    const cuerpo = await csv.text();
    ok(`CSV «${informeCsv}»`, csv.status() === 200 && (csv.headers()['content-type'] ?? '').startsWith('text/csv') && cuerpo.startsWith('﻿') && cuerpo.includes('Resumen') && cuerpo.includes('Comparado con'), `${csv.status()} ${cuerpo.length} B`);
}

// 5. Tableta (1080×810, el aparato del panel) y móvil (390×844), en la MISMA sesión.
async function tamano(w, h) {
    await page.setViewportSize({ width: w, height: h });
    await page.waitForTimeout(800);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(1500);

    return page.evaluate(() => {
        const vw = window.innerWidth;
        const tabs = [...document.querySelectorAll('[role="tab"]')];
        const primera = document.querySelector('.fi-sc-tabs .fi-wi-stats-overview-stat-value');
        const selector = document.querySelector('[data-analytics-tab-select]');
        const pildora = document.querySelector('[data-analytics-filter-pill]');
        const filtros = document.getElementById('analitica-filtros');

        return {
            pestanasALaVista: tabs.filter((t) => { const b = t.getBoundingClientRect(); return b.width > 0 && b.left >= 0 && b.right <= vw; }).length,
            selector: selector ? getComputedStyle(selector).display !== 'none' : false,
            pildora: pildora ? getComputedStyle(pildora).display !== 'none' : false,
            filtros: filtros ? getComputedStyle(filtros).display !== 'none' : false,
            yPrimeraCifra: primera ? Math.round(primera.getBoundingClientRect().top + window.scrollY) : null,
            primeraEnLaPrimeraPantalla: primera ? primera.getBoundingClientRect().bottom <= window.innerHeight : false,
            anchoDoc: document.documentElement.scrollWidth,
        };
    });
}

paso = 'tableta';
await page.getByRole('tab', { name: 'Resumen' }).first().click();
await page.waitForTimeout(1200);
informe.tamanos.tableta = await tamano(1080, 810);
ok('tableta: las siete pestañas a la vista', informe.tamanos.tableta.pestanasALaVista === 7, informe.tamanos.tableta.pestanasALaVista);
ok('tableta: la primera cifra en la primera pantalla', informe.tamanos.tableta.primeraEnLaPrimeraPantalla, informe.tamanos.tableta.yPrimeraCifra);
await captura('tableta-resumen');

paso = 'móvil';
// «Resumen» se abre desde el selector: en el móvil la fila de pestañas no se ve.
await page.setViewportSize({ width: 390, height: 844 });
await page.locator('[data-analytics-tab-select] select').selectOption('summary');
await page.waitForTimeout(1200);
informe.tamanos.movil = await tamano(390, 844);
const m = informe.tamanos.movil;
ok('móvil: el selector nativo y ninguna pestaña cortada', m.selector && m.pestanasALaVista === 0, JSON.stringify(m));
ok('móvil: la píldora a la vista y el filtro plegado', m.pildora && ! m.filtros, JSON.stringify(m));
ok('móvil: la primera cifra en la primera pantalla', m.primeraEnLaPrimeraPantalla, m.yPrimeraCifra);
ok('móvil: sin desplazamiento horizontal', m.anchoDoc <= 390, m.anchoDoc);
await captura('movil-resumen');
await page.locator('[data-analytics-filter-pill] button').click();
await page.waitForTimeout(600);
ok('móvil: la píldora abre el filtro', await page.evaluate(() => getComputedStyle(document.getElementById('analitica-filtros')).display !== 'none'));
await captura('movil-filtro-abierto');
await page.locator('[data-analytics-filter-pill] button').click();
await page.locator('[data-analytics-tab-select] select').selectOption('money');
await page.waitForTimeout(1500);
ok('móvil: el selector cambia de pestaña', page.url().includes('pestana=money') && (await page.locator('.fi-header-subheading').textContent())?.trim() === '¿Cuánto ganamos y de qué?', page.url());
await captura('movil-dinero');
await page.locator('[data-analytics-tab-select] select').selectOption('occupancy');
await page.waitForTimeout(1500);
await recorrer('La ocupación, al detalle');
await page.getByText('Cuándo se llena: día de la semana y hora').first().scrollIntoViewIfNeeded();
await captura('movil-ocupacion-mapa');
const mapaDesborda = await page.evaluate(() => {
    const caja = document.querySelector('[data-occupancy-heatmap]');
    return caja ? { propio: caja.scrollWidth > caja.clientWidth, pagina: document.documentElement.scrollWidth } : null;
});
ok('móvil: el mapa se desplaza en su caja y no en la página', mapaDesborda !== null && mapaDesborda.pagina <= 390, JSON.stringify(mapaDesborda));

// 6. El filtro: a 90 días el desglose pasa a semanas; a «este año», a meses; y la comparación con el año pasado.
paso = 'filtro';
await page.setViewportSize({ width: 1440, height: 900 });
await page.getByRole('tab', { name: 'Dinero' }).first().click();
await page.waitForTimeout(700);
await page.evaluate(() => window.scrollTo(0, 0));
const periodo = page.locator('#analitica-filtros select').nth(0);
const comparar = page.locator('#analitica-filtros select').nth(1);
await periodo.selectOption('last_90');
await recorrer('El desglose');
ok('90 días agrupa por semana', await llega('Por semana'));
await page.evaluate(() => window.scrollTo(0, 0));
await captura('escritorio-90-dias');

await periodo.selectOption('this_year');
await recorrer('El desglose');
ok('el año agrupa por mes', await llega('Por mes'));

await page.evaluate(() => window.scrollTo(0, 0));
await comparar.selectOption('year_ago');
let vsAnio = true;
try {
    await page.locator('.fi-sc-tabs .fi-wi-stats-overview-stat', { hasText: 'año pasado' }).first().waitFor({ timeout: ESPERA_WIDGETS_MS });
} catch {
    vsAnio = false;
}
await asentar(1200);
informe.periodo = { periodo: await periodo.inputValue(), comparar: await comparar.inputValue() };
ok('comparar con el año pasado', vsAnio);
ok('el CSV del pie lleva el filtro', (await page.locator('.fi-sc-tabs a[href*="analitica/csv"]').first().getAttribute('href') ?? '').includes('period=this_year&compare=year_ago'));
await captura('escritorio-anio-vs-anterior');

await periodo.selectOption('custom');
ok('a medida enseña las dos fechas', await llega('Desde') && await page.getByText('Hasta').count() > 0);
// Se deja el filtro como estaba, para el ojo del owner (vive en la sesión).
await periodo.selectOption('this_month');
await comparar.selectOption('previous');
await page.waitForTimeout(1500);

ok('consola limpia', consola.length === 0, consola.join(' | '));
if (consola.length > 0) console.log('consola:\n  ' + consola.join('\n  '));

await browser.close();

await writeFile(`${SALIDA}/analitica-panel-${ETIQUETA}.json`, JSON.stringify(informe, null, 2));

console.table(informe.comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 80) })));
console.log(`capturas: ${informe.capturas.join(', ')}`);
process.exit(informe.comprobaciones.every((c) => c.ok) ? 0 : 1);
