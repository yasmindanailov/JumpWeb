/**
 * SONDA DEL DRIVER DE ANÁLISIS — el navegador real con la herramienta configurada (`docs/specs/analitica.md`
 * §4.3 y §6, T3a·2). Lo que la suite no puede ver: que SIN la categoría «análisis» no salga ni una petición a
 * un tercero (el registro de red entero), que CON ella el script se pida al host del driver, arranque con la
 * configuración de la spec y no dispare ningún `securitypolicyviolation` (la CSP se abrió), que los eventos
 * del libro se reenvíen con la ruta enmascarada, que con sesión se identifique a la persona OPACA, y que
 * retirar la categoría lo apague.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   El driver tiene que estar configurado en la BD local (tinker; se deja como estaba al acabar):
 *     Setting::updateOrCreate(['key'=>'analytics.driver'],['value'=>'posthog','group'=>'analytics']);
 *     Setting::updateOrCreate(['key'=>'analytics.posthog_project'],['value'=>'phc_sondaSondaSondaSonda0001','group'=>'analytics']);
 *   Chromium en el contenedor (receta de la skill `sonda`), y después:
 *     docker compose exec -u sail -T -e SONDA_PANEL_EMAIL=… -e SONDA_PANEL_PASSWORD=… laravel.test \
 *         node scripts/sonda-driver.mjs [etiqueta]
 *   (las credenciales son de un CLIENTE de prueba con sesión web; sin ellas se salta la identificación).
 *   Base: `SONDA_BASE` o `http://localhost`. Salida: `storage/app/audit/driver-<etiqueta>.json`.
 *
 * ⚠️ **No se habla con PostHog de verdad**: las peticiones a `*.posthog.com` se interceptan y se sirve un
 *    DOBLE del script que apunta lo que la página le pide (`init`, `capture`, `identify`, `opt_out`). La CSP
 *    se comprueba igual: el navegador la evalúa ANTES de que la intercepción vea la petición, y una
 *    violación dispara `securitypolicyviolation` en el documento.
 * ⚠️ Playwright arranca con `navigator.webdriver = true`: los lotes van marcados y la sesión queda como bot.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const EMAIL = process.env.SONDA_PANEL_EMAIL ?? '';
const PASSWORD = process.env.SONDA_PANEL_PASSWORD ?? '';
const ETIQUETA = process.argv[2] ?? 'ojo';
const SALIDA = 'storage/app/audit';
const LOTE_MS = 5000;
/** Terceros EXENTOS y sin cookies que la web carga siempre (`COOKIES.md` §1): no son «el driver». */
const EXENTOS = /fonts\.bunny\.net|challenges\.cloudflare\.com/;

const DOBLE = `
window.__ph = [];
window.posthog = {
    init: (key, opts) => window.__ph.push(['init', key, Object.keys(opts).sort(), { person_profiles: opts.person_profiles, capture_pageview: opts.capture_pageview, autocapture: opts.autocapture, ip: opts.ip, api_host: opts.api_host, sanitized: typeof opts.sanitize_properties === 'function' ? opts.sanitize_properties({ $current_url: location.href + '?signature=x', $pathname: '/reservas/7' }) : null }]),
    capture: (name, props) => window.__ph.push(['capture', name, props]),
    identify: (id) => window.__ph.push(['identify', id]),
    opt_out_capturing: () => window.__ph.push(['opt_out']),
    opt_in_capturing: () => window.__ph.push(['opt_in']),
};
`;

await mkdir(SALIDA, { recursive: true });
const browser = await chromium.launch();
const comprobaciones = [];
const ok = (nombre, cond, detalle = '') => comprobaciones.push({ nombre, ok: Boolean(cond), detalle: String(detalle).slice(0, 200) });
const espera = (ms) => new Promise((r) => setTimeout(r, ms));

async function contexto() {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 }, locale: 'es-ES' });
    const page = await ctx.newPage();
    const red = { terceros: [], eventos: [], excepciones: [] };

    await page.addInitScript(() => {
        window.__csp = [];
        document.addEventListener('securitypolicyviolation', (e) => window.__csp.push(`${e.violatedDirective} ${e.blockedURI}`));
    });
    await page.route(/https:\/\/[^/]*posthog\.com\//, (route) => {
        red.terceros.push(route.request().url());
        route.fulfill({ status: 200, contentType: 'application/javascript', body: DOBLE });
    });
    page.on('request', (req) => {
        const url = req.url();
        if (! url.startsWith(BASE) && ! /posthog\.com/.test(url) && ! url.startsWith('data:') && ! EXENTOS.test(url)) red.terceros.push(url);
        if (url.includes('/api/v1/events')) {
            try { red.eventos.push(...(JSON.parse(req.postData() ?? '{}').events ?? []).map((e) => e.name)); } catch { /* keepalive */ }
        }
    });
    page.on('pageerror', (e) => red.excepciones.push(String(e)));

    return { ctx, page, red };
}

const csp = (page) => page.evaluate(() => window.__csp ?? []);
const ph = (page) => page.evaluate(() => window.__ph ?? []);
const consentir = (page, prefs) => page.evaluate((p) => window.Alpine.store('cookies').savePanel(p), prefs);

// ── 0. La cabecera: la CSP abre los orígenes del driver ─────────────────────────────────────────────
{
    const { ctx, page } = await contexto();
    const res = await page.goto(`${BASE}/`, { waitUntil: 'load' });
    const cabecera = res?.headers()['content-security-policy'] ?? '';
    ok('el driver está configurado (el body lo dice)', await page.evaluate(() => document.body.dataset.analyticsDriver) === 'posthog', await page.evaluate(() => JSON.stringify({ d: document.body.dataset.analyticsDriver, k: document.body.dataset.analyticsKey })));
    ok('la CSP abre *.posthog.com en script, connect e img', /script-src [^;]*https:\/\/\*\.posthog\.com/.test(cabecera) && /connect-src [^;]*https:\/\/\*\.posthog\.com/.test(cabecera) && /img-src [^;]*https:\/\/\*\.posthog\.com/.test(cabecera), cabecera.slice(0, 160));
    await ctx.close();
}

// ── 1. Sin la categoría: ni una petición a un tercero, y nada del driver ─────────────────────────────
{
    const { ctx, page, red } = await contexto();
    await page.goto(`${BASE}/`, { waitUntil: 'load' });
    await espera(LOTE_MS + 1500);
    ok('sin consentir, ninguna petición a un tercero (registro de red entero)', red.terceros.length === 0, red.terceros.join(' | '));
    ok('sin consentir, el doble de PostHog no existe', (await ph(page)).length === 0);
    ok('sin consentir, ninguna violación de CSP', (await csp(page)).length === 0, (await csp(page)).join(' | '));

    // Rechazar todo: sigue sin nada.
    await consentir(page, { maps: false, social: false, analytics: false, marketing: false });
    await espera(1500);
    ok('tras rechazar, sigue sin peticiones a terceros', red.terceros.length === 0, red.terceros.join(' | '));
    await ctx.close();
}

// ── 2. Con la categoría concedida EN la página: el script llega, arranca como dice la spec, reenvía ──
{
    const { ctx, page, red } = await contexto();
    await page.goto(`${BASE}/`, { waitUntil: 'load' });
    await espera(LOTE_MS + 1500);   // el tracker ya está instalado
    await consentir(page, { maps: false, social: false, analytics: true, marketing: false });
    await espera(2500);

    const pedido = red.terceros.find((u) => /posthog\.com\/static\/array\.js/.test(u));
    ok('al conceder «análisis» se pide el script al host EU', !! pedido, red.terceros.join(' | '));
    ok('solo ese tercero, y ninguna violación de CSP', red.terceros.every((u) => /posthog\.com/.test(u)) && (await csp(page)).length === 0, (await csp(page)).join(' | '));

    const llamadas = await ph(page);
    const init = llamadas.find((c) => c[0] === 'init');
    ok('arranca con la configuración de la spec', init && init[1].startsWith('phc_') && init[3].person_profiles === 'identified_only' && init[3].capture_pageview === false && init[3].autocapture === false && init[3].ip === false && init[3].api_host === 'https://eu.i.posthog.com', JSON.stringify(init?.[3]).slice(0, 180));
    ok('sanitize_properties enmascara la URL y el path', init?.[3]?.sanitized && ! /signature/.test(init[3].sanitized.$current_url) && init[3].sanitized.$pathname === '/reservas/{n}', JSON.stringify(init?.[3]?.sanitized));
    ok('sin sesión, ninguna identify', ! llamadas.some((c) => c[0] === 'identify'));

    // Un evento del libro después de cargar: se reenvía con la ruta enmascarada.
    await page.evaluate(() => window.JumpWeb.track('section_viewed', { section: 'sonda' }));
    await espera(300);
    const reenviado = (await ph(page)).find((c) => c[0] === 'capture' && c[1] === 'section_viewed');
    ok('los eventos del libro se reenvían con la ruta', reenviado && reenviado[2].section === 'sonda' && reenviado[2].route === '/', JSON.stringify(reenviado));

    // Retirar la categoría: opt_out y nada más se captura.
    await consentir(page, { maps: false, social: false, analytics: false, marketing: false });
    await espera(800);
    await page.evaluate(() => window.JumpWeb.track('section_viewed', { section: 'despues' }));
    await espera(300);
    const despues = await ph(page);
    ok('retirar la categoría apaga la captura', despues.some((c) => c[0] === 'opt_out') && ! despues.some((c) => c[0] === 'capture' && c[2]?.section === 'despues'));
    ok('sin excepciones de JS', red.excepciones.length === 0, red.excepciones.join(' | '));
    await ctx.close();
}

// ── 3. Recargar con la cookie: el driver carga solo, y con sesión identifica a la persona opaca ─────
{
    const { ctx, page, red } = await contexto();
    await page.goto(`${BASE}/`, { waitUntil: 'load' });
    await espera(1500);
    await consentir(page, { maps: false, social: false, analytics: true, marketing: false });
    await espera(1000);
    await page.reload({ waitUntil: 'load' });
    await espera(LOTE_MS + 2500);
    ok('con la cookie ya decidida, el driver carga solo al entrar', red.terceros.some((u) => /array\.js/.test(u)) && (await ph(page)).some((c) => c[0] === 'init'));
    ok('anónimo: sin persona en el body y sin identify', (await page.evaluate(() => document.body.dataset.analyticsPerson ?? null)) === null && ! (await ph(page)).some((c) => c[0] === 'identify'));

    if (EMAIL !== '' && PASSWORD !== '') {
        await page.goto(`${BASE}/login`, { waitUntil: 'load' });
        await page.fill('input[type="email"], input[name="email"]', EMAIL);
        await page.fill('input[type="password"], input[name="password"]', PASSWORD);
        await Promise.all([page.waitForURL((u) => ! u.pathname.endsWith('/login'), { timeout: 20000 }).catch(() => null), page.click('button[type="submit"]')]);
        await page.goto(`${BASE}/`, { waitUntil: 'load' });
        await espera(LOTE_MS + 2500);
        const persona = await page.evaluate(() => document.body.dataset.analyticsPerson ?? null);
        ok('con sesión y categoría, el body lleva la persona OPACA (64 hex)', /^[0-9a-f]{64}$/.test(persona ?? ''), persona ?? 'sin persona');
        ok('y el driver la identifica', (await ph(page)).some((c) => c[0] === 'identify' && c[1] === persona));
    } else {
        ok('identificación con sesión (saltada: sin credenciales de cliente)', true, 'SONDA_PANEL_EMAIL/PASSWORD vacías');
    }
    await ctx.close();
}

// ── 4. En una URL con credenciales no carga aunque la categoría esté ────────────────────────────────
{
    const { ctx, page, red } = await contexto();
    await page.goto(`${BASE}/`, { waitUntil: 'load' });
    await espera(1500);
    await consentir(page, { maps: false, social: false, analytics: true, marketing: false });
    await espera(LOTE_MS + 1500);
    // El driver YA cargó en la portada al conceder: se vacía el registro y se mira solo lo de la invitación.
    red.terceros.length = 0;
    await page.goto(`${BASE}/invitacion/01HZX8K4N2P7Q9R3S5T6V8W0YA`, { waitUntil: 'load' }).catch(() => null);
    await espera(LOTE_MS + 1500);
    ok('en /invitacion/{token} no se pide el driver (la URL lleva una credencial)', ! red.terceros.some((u) => /array\.js/.test(u)), red.terceros.join(' | '));
    await ctx.close();
}

await browser.close();
await writeFile(`${SALIDA}/driver-${ETIQUETA}.json`, JSON.stringify({ base: BASE, etiqueta: ETIQUETA, comprobaciones }, null, 2));
console.table(comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 90) })));
process.exit(comprobaciones.every((c) => c.ok) ? 0 : 1);
