/**
 * SONDA DEL BANNER DE COOKIES — el navegador real ante la tarjeta con las CUATRO finalidades de la T3a
 * (`docs/specs/analitica.md` §4.3; `docs/sistemas/COOKIES.md`). Es lo que ni la suite ni `node --test` ven: que
 * el panel pinte un toggle por categoría, que «Guardar» mande las cuatro y el servidor las escriba, que
 * `consent_shown` y `consent_updated` lleguen al libro, que con el cajón de compra ABIERTO la tarjeta espere,
 * que reabrir desde el pie lleve el foco al título, y que tras recargar el `<body>` diga lo decidido.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   Chromium en el contenedor (receta de la skill `sonda`), y después:
 *     docker compose exec -u sail -T laravel.test node scripts/sonda-cookies.mjs [etiqueta]
 *   Base: `SONDA_BASE` o `http://localhost` (el `80` del contenedor).
 *   Salida: `storage/app/audit/cookies-<etiqueta>.json` y las capturas al lado (gitignorado).
 *
 * ⚠️ Playwright arranca con `navigator.webdriver = true`: los lotes van marcados y la sesión queda como bot.
 * ⚠️ Capturas de VENTANA con el ratón apartado; nunca `fullPage`.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ETIQUETA = process.argv[2] ?? 'ojo';
const SALIDA = 'storage/app/audit';
const LOTE_MS = 5000;

await mkdir(SALIDA, { recursive: true });

const browser = await chromium.launch();
const comprobaciones = [];
const capturas = [];
const ok = (nombre, cond, detalle = '') => comprobaciones.push({ nombre, ok: Boolean(cond), detalle: String(detalle).slice(0, 160) });
const espera = (ms) => new Promise((r) => setTimeout(r, ms));

/** Un contexto limpio (sin cookies) con lo que sale por `/api/v1/events` y por `/cookies/consentimiento` apuntado. */
async function contexto(viewport) {
    const ctx = await browser.newContext({ viewport, locale: 'es-ES', reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    const registro = { eventos: [], consentimientos: [], excepciones: [], consola: [] };

    page.on('request', (req) => {
        const url = req.url();
        if (url.includes('/api/v1/events')) {
            try { registro.eventos.push(...(JSON.parse(req.postData() ?? '{}').events ?? []).map((e) => ({ name: e.name, props: e.props }))); } catch { /* keepalive */ }
        }
        if (url.includes('/cookies/consentimiento')) {
            try { registro.consentimientos.push({ body: JSON.parse(req.postData() ?? 'null'), status: null }); } catch { registro.consentimientos.push({ body: req.postData(), status: null }); }
        }
    });
    page.on('response', (res) => {
        if (res.url().includes('/cookies/consentimiento')) {
            const c = registro.consentimientos.findLast((x) => x.status === null);
            if (c) c.status = res.status();
        }
    });
    page.on('pageerror', (e) => registro.excepciones.push(String(e)));
    page.on('console', (m) => { if (m.type() === 'error') registro.consola.push(m.text()); });

    return { ctx, page, registro };
}

async function captura(page, nombre) {
    const ruta = `${SALIDA}/cookies-${ETIQUETA}-${nombre}.png`;
    await page.mouse.move(0, 0);
    await espera(600);
    await page.screenshot({ path: ruta });
    capturas.push(ruta);
}

// ── 1. Escritorio: la tarjeta, el panel con las cuatro finalidades, guardar y recargar ─────────────────────
{
    const { ctx, page, registro } = await contexto({ width: 1440, height: 900 });
    await page.goto(`${BASE}/`, { waitUntil: 'load' });
    const tarjeta = page.locator('aside.cookie');
    await tarjeta.waitFor({ state: 'visible', timeout: 15000 }).catch(() => null);
    ok('la tarjeta se enseña a un visitante nuevo', await tarjeta.isVisible());
    ok('tres acciones en igualdad en la primera capa', await page.locator('.cookie__compact .cookie-btn').count() === 2 && await page.locator('.cookie__config').count() === 1);
    await captura(page, 'escritorio-capa1');

    await page.locator('.cookie__config').click();
    await espera(400);
    const toggles = await page.locator('[data-consent-category]').evaluateAll((els) => els.map((el) => el.dataset.consentCategory));
    ok('el panel lleva UN toggle por categoría, en el orden del servidor', JSON.stringify(toggles) === JSON.stringify(['maps', 'social', 'analytics', 'marketing']), toggles.join(','));
    ok('ninguno viene premarcado', await page.locator('[data-consent-category] .ck-tgl.is-on').count() === 0);
    // Medido el 24-09 antes del tope de alto: con cuatro finalidades el título quedaba por encima del borde.
    const caja = await tarjeta.boundingBox();
    ok('el panel abierto CABE en la ventana (título y «Necesarias» alcanzables)', caja && caja.y >= 0 && caja.y + caja.height <= 900, JSON.stringify(caja));
    await tarjeta.evaluate((el) => el.scrollTo(0, 0));
    await captura(page, 'escritorio-panel');

    // Solo «análisis»: el toggle y guardar.
    await page.locator('[data-consent-category="analytics"] .ck-tgl').click();
    await page.locator('.cookie__prefs-actions .cookie-btn').nth(1).click();   // Guardar preferencias
    await espera(1500);
    const guardado = registro.consentimientos.at(-1);
    ok('«Guardar» manda las CUATRO categorías y el servidor responde 200', guardado?.status === 200 && JSON.stringify(guardado.body) === JSON.stringify({ maps: false, social: false, analytics: true, marketing: false }), JSON.stringify(guardado));
    ok('la tarjeta se esconde tras la confirmación del servidor', ! (await tarjeta.isVisible()));

    await espera(LOTE_MS + 1500);
    const nombres = registro.eventos.map((e) => e.name);
    ok('`consent_shown` llega al libro', nombres.includes('consent_shown'), nombres.join(','));
    const actualizado = registro.eventos.find((e) => e.name === 'consent_updated');
    ok('`consent_updated` dice qué se aceptó', actualizado?.props?.categories === 'analytics', JSON.stringify(actualizado));

    await page.reload({ waitUntil: 'load' });
    await espera(800);
    const dataset = await page.evaluate(() => ({ ...document.body.dataset }));
    ok('tras recargar, el body dice lo decidido', dataset.cookieDecided === '1' && dataset.cookieAnalytics === '1' && dataset.cookieMarketing === '' && dataset.consentCategories === 'maps,social,analytics,marketing', JSON.stringify(dataset).slice(0, 150));
    ok('y la tarjeta no vuelve', ! (await tarjeta.isVisible()));

    // Reabrir desde el pie: el panel, con el foco en el título.
    const pie = page.locator('footer button, footer a').filter({ hasText: 'Configuración de cookies' }).first();
    if (await pie.count() > 0) {
        await pie.scrollIntoViewIfNeeded();
        await pie.click();
        await espera(500);
        ok('el enlace del pie reabre el panel con lo guardado', await tarjeta.isVisible() && await page.locator('[data-consent-category="analytics"] .ck-tgl.is-on').count() === 1);
        ok('al reabrir, el foco va al título', await page.evaluate(() => document.activeElement?.classList.contains('cookie__title')));
        await captura(page, 'escritorio-reabierto');
    } else {
        ok('el enlace del pie existe', false, 'sin «Configuración de cookies» en el pie');
    }

    ok('sin excepciones de JS (escritorio)', registro.excepciones.length === 0, registro.excepciones.join(' | '));
    await ctx.close();
}

// ── 2. Móvil: la tarjeta y el panel caben ────────────────────────────────────────────────────────────────
{
    const { ctx, page, registro } = await contexto({ width: 390, height: 844 });
    await page.goto(`${BASE}/`, { waitUntil: 'load' });
    await page.locator('aside.cookie').waitFor({ state: 'visible', timeout: 15000 }).catch(() => null);
    await captura(page, 'movil-capa1');
    await page.locator('.cookie__config').click();
    await espera(400);
    const cajaMovil = await page.locator('aside.cookie').boundingBox();
    ok('en móvil el panel abierto cabe en la pantalla', cajaMovil && cajaMovil.y >= 0 && cajaMovil.y + cajaMovil.height <= 844, JSON.stringify(cajaMovil));
    await page.locator('aside.cookie').evaluate((el) => el.scrollTo(0, 0));
    await captura(page, 'movil-panel');
    ok('en móvil no hay scroll lateral con el panel abierto', await page.evaluate(() => document.documentElement.scrollWidth) <= 390);
    ok('sin excepciones de JS (móvil)', registro.excepciones.length === 0, registro.excepciones.join(' | '));
    await ctx.close();
}

// ── 3. Con el cajón de compra ABIERTO la tarjeta espera; al cerrarlo, aparece ───────────────────────────
{
    const { ctx, page, registro } = await contexto({ width: 1440, height: 900 });
    await page.goto(`${BASE}/entradas`, { waitUntil: 'load' });
    await page.waitForSelector('#sidecart-spa .purchase, #sidecart-spa [class*="catalog"], #sidecart-spa .sidebar', { timeout: 15000 }).catch(() => null);
    await espera(1200);
    const abierto = await page.evaluate(() => document.body.dataset.purchaseOpen === '1' || !! window.JumpWeb?.cajon?.isOpen);
    const tarjeta = page.locator('aside.cookie');
    ok('/entradas nace con el cajón abierto', abierto);
    ok('con el cajón delante la tarjeta NO se enseña', abierto && ! (await tarjeta.isVisible()));
    await captura(page, 'escritorio-cajon-abierto');

    const cerrar = page.locator('.sidecart__close').first();
    if (await cerrar.count() > 0) {
        await cerrar.click();
        await tarjeta.waitFor({ state: 'visible', timeout: 5000 }).catch(() => null);
        ok('al cerrar el cajón, la tarjeta aparece', await tarjeta.isVisible());
        await espera(LOTE_MS + 1500);
        ok('y `consent_shown` se cuenta entonces, una vez', registro.eventos.filter((e) => e.name === 'consent_shown').length === 1, registro.eventos.map((e) => e.name).join(','));
    } else {
        ok('el cajón tiene botón de cerrar', false);
    }
    ok('sin excepciones de JS (cajón)', registro.excepciones.length === 0, registro.excepciones.join(' | '));
    await ctx.close();
}

await browser.close();
await writeFile(`${SALIDA}/cookies-${ETIQUETA}.json`, JSON.stringify({ base: BASE, etiqueta: ETIQUETA, comprobaciones, capturas }, null, 2));

console.table(comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 90) })));
console.log(`capturas: ${capturas.join(', ')}`);
process.exit(comprobaciones.every((c) => c.ok) ? 0 : 1);
