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
 *   Para el paso 5 (T3b·1, los píxeles) hacen falta los tres ids en Ajustes (tinker; se quitan al acabar):
 *     Setting::updateOrCreate(['key'=>'marketing.google_ads.conversion_id'],['value'=>'AW-123456789','group'=>'marketing']);
 *     Setting::updateOrCreate(['key'=>'marketing.meta.pixel_id'],['value'=>'1234567890123456','group'=>'marketing']);
 *     Setting::updateOrCreate(['key'=>'marketing.tiktok.pixel_id'],['value'=>'C9ABCDEFGHIJKLMNOPQR','group'=>'marketing']);
 *   Para el paso 3c (T3a·4, el aviso del índice) la cuenta de prueba tiene que tener el correo marcado y el
 *   aviso sin despedir (tinker; se deja como estaba al acabar, también por tinker: la sonda solo lo despide):
 *     User::where('email', '…')->update(['analytics_notified_at' => now(), 'analytics_notice_seen_at' => null]);
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
/** Los hosts de los píxeles de anuncios (T3b·1): se interceptan y solo pueden aparecer con `marketing`. */
const PIXELES = /googletagmanager\.com|connect\.facebook\.net|analytics\.tiktok\.com|googleads\.g\.doubleclick\.net|www\.facebook\.com\/tr/;

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
    const red = { terceros: [], eventos: [], excepciones: [], pixeles: [] };

    await page.addInitScript(() => {
        window.__csp = [];
        document.addEventListener('securitypolicyviolation', (e) => window.__csp.push(`${e.violatedDirective} ${e.blockedURI}`));
    });
    await page.route(/https:\/\/[^/]*posthog\.com\//, (route) => {
        red.terceros.push(route.request().url());
        route.fulfill({ status: 200, contentType: 'application/javascript', body: DOBLE });
    });
    // Los píxeles (T3b·1): tampoco se habla con Google, Meta ni TikTok; sus scripts se sirven vacíos y se
    // apunta qué se pidió. Lo que la página les dice queda en `dataLayer`, `fbq.queue` y `ttq._q`.
    await page.route(PIXELES, (route) => {
        red.pixeles.push(route.request().url());
        route.fulfill({ status: 200, contentType: 'application/javascript', body: '/* doble del píxel */' });
    });
    page.on('request', (req) => {
        const url = req.url();
        if (! url.startsWith(BASE) && ! /posthog\.com/.test(url) && ! PIXELES.test(url) && ! url.startsWith('data:') && ! EXENTOS.test(url)) red.terceros.push(url);
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

        // ── 3b. La OPOSICIÓN desde «Mi cuenta → Privacidad» (T3a·3): el segundo interruptor ───────────────
        const me = async () => page.evaluate(async () => (await (await fetch('/api/v1/me', { credentials: 'include', headers: { Accept: 'application/json' } })).json()));
        await page.goto(`${BASE}/mi-cuenta`, { waitUntil: 'load' });
        await page.waitForSelector('#sidecart-spa .sidebar, #sidecart-spa [class*="account"]', { timeout: 15000 }).catch(() => null);
        await espera(1200);
        const tarjeta = page.getByText('Privacidad y datos').first();
        if (await tarjeta.count() > 0) await tarjeta.click();
        await page.locator('input[role="switch"]').nth(1).waitFor({ timeout: 15000 }).catch(() => null);
        const interruptores = await page.locator('input[role="switch"]').count();
        ok('«Privacidad» lleva DOS interruptores (marketing y análisis)', interruptores === 2, String(interruptores));
        const analisis = page.locator('input[role="switch"]').nth(1);
        ok('el de análisis arranca encendido (la cuenta no se opuso)', await analisis.isChecked());
        await page.mouse.move(0, 0);
        await page.screenshot({ path: `${SALIDA}/driver-${ETIQUETA}-privacidad.png` });

        await analisis.click();
        await espera(1500);
        const tras = await me();
        ok('apagarlo escribe la oposición en la cuenta (GET /me)', tras?.analytics_opt_out === true, JSON.stringify({ analytics_opt_out: tras?.analytics_opt_out }));
        ok('y la lista de consentimientos enseña el de análisis retirado', await page.locator('.account__consent--revoked').count() >= 1);
        await page.mouse.move(0, 0);
        await page.screenshot({ path: `${SALIDA}/driver-${ETIQUETA}-privacidad-opuesto.png` });

        await page.goto(`${BASE}/`, { waitUntil: 'load' });
        await espera(LOTE_MS + 2500);
        ok('con la oposición, el body ya no lleva la persona aunque la categoría siga', (await page.evaluate(() => document.body.dataset.analyticsPerson ?? null)) === null);
        ok('y el driver carga (la categoría sigue) pero no identifica a nadie', (await ph(page)).some((c) => c[0] === 'init') && ! (await ph(page)).some((c) => c[0] === 'identify'));

        // Se deja como estaba: la cuenta de prueba vuelve a permitirlo (por la API, con la sesión del navegador;
        // el camino de la UI ya quedó probado al apagarlo).
        const vuelta = await page.evaluate(async () => {
            const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? '');
            const res = await fetch('/api/v1/me/analytics', { method: 'PUT', credentials: 'include', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf }, body: JSON.stringify({ accepted: true }) });

            return res.status;
        });
        ok('volver a permitirlo (PUT /me/analytics) quita la oposición', vuelta === 204 && (await me())?.analytics_opt_out === false, `status ${vuelta}`);

        // ── 3c. El AVISO del índice a las cuentas existentes (T3a·4): viaja con su texto y se despide ─────
        const contextoCuenta = async () => page.evaluate(async () => (await (await fetch('/api/v1/me/account-context', { credentials: 'include', headers: { Accept: 'application/json' } })).json()));
        const aviso = (await contextoCuenta())?.analytics_notice ?? null;
        if (aviso !== null) {
            await page.goto(`${BASE}/mi-cuenta`, { waitUntil: 'load' });
            await page.waitForSelector('#sidecart-spa .sidebar, #sidecart-spa [class*="account"]', { timeout: 15000 }).catch(() => null);
            const texto = page.getByText(aviso.text, { exact: false }).first();
            await texto.waitFor({ timeout: 15000 }).catch(() => null);
            ok('el índice pinta el aviso con el texto que viaja en el contexto', await texto.count() > 0);
            const despedir = page.getByRole('button', { name: aviso.dismiss }).first();
            ok('con «Privacidad y datos» y el botón de despedir', await page.getByRole('button', { name: 'Privacidad y datos' }).count() >= 1 && await despedir.count() === 1);
            await page.mouse.move(0, 0);
            await page.screenshot({ path: `${SALIDA}/driver-${ETIQUETA}-aviso.png` });

            await despedir.click();
            await espera(1500);
            ok('despedirlo lo quita del índice', await page.getByText(aviso.text, { exact: false }).count() === 0);
            ok('y el servidor lo confirma: el contexto ya no lo trae', (await contextoCuenta())?.analytics_notice === null);
            await page.reload({ waitUntil: 'load' });
            await espera(1200);
            ok('tras recargar sigue despedido (la marca es del servidor)', await page.getByText(aviso.text, { exact: false }).count() === 0);
        } else {
            ok('el aviso del índice (saltado: la cuenta no lo tiene pendiente; ver la cabecera)', true, 'analytics_notice null');
        }
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

// ── 5. Los píxeles de anuncios (T3b·1): nada sin `marketing`; con ella, los tres y el Consent Mode básico ──
{
    const { ctx, page, red } = await contexto();
    await page.goto(`${BASE}/`, { waitUntil: 'load' });
    await espera(LOTE_MS + 1500);
    const ids = await page.evaluate(() => ({ g: document.body.dataset.pixelGoogleAds ?? null, m: document.body.dataset.pixelMeta ?? null, t: document.body.dataset.pixelTiktok ?? null }));
    if (ids.g && ids.m && ids.t) {
        ok('el body publica los tres píxeles configurados (ids públicos)', /^AW-\d+/.test(ids.g) && /^\d+$/.test(ids.m) && /^[A-Z0-9]+$/.test(ids.t), JSON.stringify(ids));
        ok('sin la categoría «marketing»: ni una petición a Google, Meta o TikTok', red.pixeles.length === 0, red.pixeles.join(' '));
        ok('Consent Mode BÁSICO: gtag no existe en el DOM sin marketing', await page.evaluate(() => window.dataLayer === undefined && window.fbq === undefined && window.ttq === undefined));

        await consentir(page, { maps: false, social: false, analytics: false, marketing: true });
        await espera(2500);
        const hosts = ['googletagmanager.com', 'connect.facebook.net', 'analytics.tiktok.com'];
        ok('con «marketing» concedida, los tres scripts se piden a sus hosts', hosts.every((h) => red.pixeles.some((u) => u.includes(h))), red.pixeles.join(' '));
        ok('y la CSP no protesta (sus orígenes entraron con el píxel)', (await csp(page)).length === 0, (await csp(page)).join(' '));
        const capas = await page.evaluate(() => (window.dataLayer ?? []).map((a) => Array.from(a)));
        ok('gtag: el consent default va todo DENEGADO y antes de todo', capas[0]?.[0] === 'consent' && capas[0]?.[1] === 'default' && Object.values(capas[0]?.[2] ?? {}).every((v) => v === 'denied'), JSON.stringify(capas[0]));
        ok('gtag: el update concede solo lo de anuncios y deja analytics_storage denegado', capas[1]?.[1] === 'update' && capas[1]?.[2]?.ad_storage === 'granted' && capas[1]?.[2]?.analytics_storage === 'denied', JSON.stringify(capas[1]));
        ok('Meta: init con su id y PageView; TikTok: page', await page.evaluate(() => {
            const fb = (window.fbq?.queue ?? []).map((a) => Array.from(a));
            return fb[0]?.[0] === 'init' && fb[1]?.[1] === 'PageView' && (window.ttq?._q ?? [])[0]?.[0] === 'page';
        }));
        ok('y ningún tercero fuera de los píxeles y el driver', red.terceros.every((u) => /posthog\.com/.test(u)), red.terceros.join(' '));

        await consentir(page, { maps: false, social: false, analytics: false, marketing: false });
        await espera(1000);
        const ultimo = await page.evaluate(() => Array.from((window.dataLayer ?? []).at(-1) ?? []));
        ok('retirar «marketing» deniega de nuevo en gtag y revoca en Meta', ultimo[1] === 'update' && ultimo[2]?.ad_storage === 'denied' && (await page.evaluate(() => Array.from((window.fbq?.queue ?? []).at(-1) ?? []))).join(',') === 'consent,revoke', JSON.stringify(ultimo));
    } else {
        ok('los píxeles (saltado: sin los tres ids en Ajustes; ver la cabecera)', true, JSON.stringify(ids));
    }
    await ctx.close();
}

await browser.close();
await writeFile(`${SALIDA}/driver-${ETIQUETA}.json`, JSON.stringify({ base: BASE, etiqueta: ETIQUETA, comprobaciones }, null, 2));
console.table(comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 90) })));
process.exit(comprobaciones.every((c) => c.ok) ? 0 : 1);
