/**
 * **LA COMPRA SIN PANTALLAS DE MÁS, EN VIVO** (`DECISIONES #785`, `specs/isla-y-landing-nueva.md` §4.14): la página de
 * verdad (`/kids`), su calculadora, la isla y la API, en un navegador. Cuatro recorridos:
 *   1. INVITADO: «Reservar y pagar» de la calculadora → la isla NUNCA enseña la pantalla 0 (se vigila por fotograma: ni
 *      una pregunta `pjc-q-*`) y llega a «Tus datos», que sí tiene algo que pedir.
 *   2. LA VUELTA DE GOOGLE CON CUENTA (simulada): «Continuar con Google» sale; la ida se intercepta, se entra por la API
 *      con la cuenta de pruebas y se vuelve a su `next` (`?compra=reanudar`). La compra se reabre SOLA —la página nueva
 *      lleva sus marcas del `<body>`— y, sin nada que pedir, llega a «Pagar» sin «Tus datos»: «Pagar» a secas y
 *      «Reservas como …».
 *   3. CON SESIÓN: la calculadora → «Pagar» directo, y su flecha vuelve a la pantalla 0 (lo que se eligió).
 *   4. LA VUELTA DE GOOGLE SIN CUENTA (simulada): igual, pero lo que espera en la sesión del SERVIDOR es un perfil de
 *      Google (tinker, SOLO EN LOCAL: el mismo `GoogleAuthSession::rememberProfile` que el retorno de Google). La compra
 *      lo encuentra y completa el alta en «Tus datos» —«Con tu cuenta de Google: …», su nombre, la casilla y, en una
 *      entrada, SIN teléfono (`#787`)—; al seguir, la cuenta nace y la compra sigue a «Pagar». La cuenta se ANONIMIZA
 *      al acabar (y al empezar, la de una corrida anterior).
 * Y antes, EL PIE de la página (`#786`): las formas de pago del hecho del sitio, en vez de la frase.
 * Sin pagar: no deja pedidos. Sale con 1 si algo falla; las fotos, en `storage/app/audit/directa-<ancho>-*.png`.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-compra-directa.mjs [390|1280]
 */
/* global URL, console, document, window, requestAnimationFrame, fetch -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const SALIDA = 'storage/app/audit';
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' };
const NUEVA = { email: 'sonda-google-785@jumpweb.test', sub: 'sonda-google-785', nombre: 'Sonda Google' };
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }
if (tinker('echo App\\Domain\\Content\\Services\\ShellSettings::shell();') !== 'isla') { console.error('✗ la carcasa no es la isla'); process.exit(1); }
if (tinker('echo App\\Domain\\Identity\\Services\\GoogleAuth::enabled() ? 1 : 0;') !== '1') { console.error('✗ Google no está configurado en local: sin él no hay botón que probar'); process.exit(1); }
if (tinker(`echo App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->exists() ? 1 : 0;`) !== '1') { console.error(`✗ falta la cuenta de pruebas ${CLIENTE.email} (receta en sonda-isla.mjs)`); process.exit(1); }

/** La cuenta que nace en el recorrido 4, anonimizada (libera su `sub` de Google y su correo): la vía del producto. */
const anonimizarNueva = () => tinker(`App\\Domain\\Identity\\Models\\User::where('email', '${NUEVA.email}')->get()->each->anonymize();`);
/** Los limitadores que agotan corridas seguidas: la API (60/min), la entrada. */
const limitadoresACero = () => tinker(`$id = App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->value('id'); foreach ([md5('api'.'user:'.$id), md5('api'.'ip:127.0.0.1'), Illuminate\\Support\\Str::transliterate('${CLIENTE.email}|127.0.0.1'), 'login-ip|127.0.0.1'] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }`);
/** Deja un perfil de Google esperando en la sesión del SERVIDOR de ese navegador, como lo deja su retorno. */
const perfilDeGoogleEnLaSesion = (cookie) => tinker(`$id = Illuminate\\Cookie\\CookieValuePrefix::remove(Illuminate\\Support\\Facades\\Crypt::decrypt(urldecode('${cookie}'), false)); $s = app('session')->driver(); $s->setId($id); $s->start(); App\\Http\\Auth\\GoogleAuthSession::rememberProfile(App\\Domain\\Identity\\Contracts\\SocialProfile::google('${NUEVA.sub}', '${NUEVA.email}', true, '${NUEVA.nombre}')); $s->save(); echo $s->get('auth.google.profile')['email'] ?? '';`);

const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);

await mkdir(SALIDA, { recursive: true });
anonimizarNueva();
limitadoresACero();
const navegador = await chromium.launch();
const errores = [];
let actual = null;

/** Un navegador nuevo (sin sesión), con la vigía de la pantalla 0 en cada documento y la ida a Google interceptada. */
async function contexto(alSalirAGoogle) {
    const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 600 ? 844 : 900 }, locale: 'es-ES' });
    await ctx.addInitScript(() => {
        window.__cero = false;
        const mira = () => {
            if (document.querySelector('[data-isla-scroll] [id^="pjc-q-"]')) window.__cero = true;
            requestAnimationFrame(mira);
        };
        requestAnimationFrame(mira);
    });
    // La ida a Google (`/auth/google`, `auth.google.redirect`) NO sale: se hace lo que haría su vuelta y se vuelve a
    // `next`, como hace `GoogleAuthController`.
    await ctx.route(/\/auth\/google(\?|$)/, async (ruta) => {
        const next = new URL(ruta.request().url()).searchParams.get('next') ?? '/';

        await alSalirAGoogle?.();

        return ruta.fulfill({ status: 302, headers: { location: `${BASE}${next}` } });
    });
    const page = await ctx.newPage();

    actual = page;
    page.on('pageerror', (e) => errores.push(e.message));
    // Los «no» esperados (401 sin sesión, el 404 de «no hay alta de Google esperando») no son errores de la página.
    page.on('console', (m) => { if (m.type() === 'error' && ! /status of 40(1|4)/.test(m.text())) errores.push(m.text()); });

    return { ctx, page };
}

const helpers = (page) => {
    const espera = (fn, arg, ms = 20000) => page.waitForFunction(fn, arg, { timeout: ms });

    return {
        paso: () => page.locator('#isla-compra-paso').innerText().catch(() => ''),
        cuerpo: () => page.locator('[data-isla-scroll]').innerText().catch(() => ''),
        hastaPaso: (t) => espera((x) => (document.querySelector('#isla-compra-paso')?.textContent ?? '').trim().endsWith(x), t),
        accion: (nombre) => page.locator('[data-isla] button', { hasText: nombre }).last(),
        foto: (n) => page.screenshot({ path: `${SALIDA}/directa-${ANCHO}-${n}.png` }),
        quieta: async () => { await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(450); },
        cero: () => page.evaluate(() => window.__cero === true),
    };
};

/** La calculadora de Kids: el primer día libre, su primera hora, y «Reservar y pagar». */
async function reservarDesdeLaCalculadora(page) {
    await page.goto(`${BASE}/kids`, { waitUntil: 'load' });
    await page.locator('#precio').scrollIntoViewIfNeeded();
    const dia = page.locator('[data-jw-calculadora] button[aria-label*=", libre"]:not([disabled])').first();

    await dia.waitFor({ timeout: 20000 });
    await dia.click();
    const hora = page.locator('[data-jw-calculadora] [role=group] button:not([disabled])').first();

    await hora.waitFor({ timeout: 15000 });
    await hora.click();
    await page.waitForFunction(() => {
        const b = [...document.querySelectorAll('[data-jw-calculadora-lado] button')].find((x) => x.textContent.includes('Reservar y pagar'));

        return b && ! b.disabled;
    }, null, { timeout: 15000 });
    await page.evaluate(() => { window.__cero = false; });
    await page.locator('[data-jw-calculadora-lado] button', { hasText: 'Reservar y pagar' }).click();
}

/** Entra con la cuenta de pruebas por la API, con la cookie CSRF del sitio: la sesión que dejaría la vuelta de Google. */
const entrarPorLaApi = (page) => page.evaluate(async ({ email, password }) => {
    await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
    const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? '');
    const r = await fetch('/api/v1/auth/login', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf },
        body: JSON.stringify({ email, password }),
    });

    return r.status;
}, CLIENTE);

try {
    // ── 1 y 2. Invitado → «Tus datos»; y la vuelta de Google CON cuenta → «Pagar» ─────────────────────────────────────
    const a = await contexto();
    const h = helpers(a.page);

    // El pie de la página (`#786`): las formas de pago del hecho del sitio, en su versión CLARA, en su orden, cargadas y en
    // vez de la frase.
    await a.page.goto(`${BASE}/kids`, { waitUntil: 'load' });
    const pie = await a.page.evaluate(async () => {
        const hechos = (await (await fetch('/api/v1/site')).json()).payment_marks ?? [];
        const imgs = [...document.querySelectorAll('.pj-pie ul.pj-pie__marcas img')];

        imgs.forEach((i) => { i.loading = 'eager'; });
        await Promise.all(imgs.map((i) => i.decode().catch(() => null)));

        return {
            hechos: hechos.map((m) => m.src),
            vistas: imgs.map((i) => (i.naturalWidth > 0 ? i.src : `ROTA ${i.src}`)),
            frase: document.querySelectorAll('.pj-pie .pj-pie__pago').length,
        };
    });
    ok('el pie: las formas de pago del sitio, en su orden, cargadas y en lugar de la frase', pie.hechos.length > 0 && JSON.stringify(pie.hechos) === JSON.stringify(pie.vistas) && pie.frase === 0, JSON.stringify(pie));
    await a.page.locator('.pj-pie__marcas').scrollIntoViewIfNeeded();
    await a.page.waitForTimeout(600);
    await a.page.locator('.pj-pie__letra').screenshot({ path: `${SALIDA}/directa-${ANCHO}-0-pie.png` }).catch(() => {});

    await reservarDesdeLaCalculadora(a.page);
    await h.hastaPaso('Tus datos');
    await h.quieta();
    ok('invitado: «Reservar y pagar» lleva a «Tus datos» sin enseñar la pantalla 0 por el camino', ! await h.cero(), await h.paso());
    await h.foto('1-datos');

    // La sesión que dejaría Google, abierta ANTES de salir: el documento que se va no puede pedir nada (medido: se colgaba).
    // La compra de esta página sigue creyéndose sin sesión, como la que sale a Google de verdad.
    ok('se entra por la API (la sesión que dejaría Google)', await entrarPorLaApi(a.page) === 200);
    await a.page.locator('[data-isla-scroll] button', { hasText: /Continuar con Google/ }).first().click();
    await a.page.waitForURL(/\/kids(\?|$)/, { timeout: 20000 });
    await h.hastaPaso('Pagar');
    await h.quieta();
    const trasGoogle = await h.cuerpo();
    ok('la vuelta de Google con cuenta REABRE la compra y, sin nada que pedir, llega a «Pagar»', (await h.paso()).trim() === 'Pagar', await h.paso());
    ok('sin «Tus datos» delante: «Reservas como …» bajo el titular', /Reservas como \S+/.test(trasGoogle), trasGoogle.slice(0, 90));
    ok('y la pantalla 0 no se pintó al volver', ! await h.cero());
    await h.foto('2-google-pagar');

    // ── 3. Con sesión: la calculadora → «Pagar» directo; su flecha, a la pantalla 0 ───────────────────────────────────
    await reservarDesdeLaCalculadora(a.page);
    await h.hastaPaso('Pagar');
    await h.quieta();
    ok('con sesión: «Reservar y pagar» → «Pagar» directo, sin la pantalla 0 ni «Tus datos»', (await h.paso()).trim() === 'Pagar' && ! await h.cero(), await h.paso());
    await h.foto('3-sesion-pagar');
    await a.page.locator('[data-isla] button[aria-label="Volver"]').first().click();
    await a.page.waitForSelector('[data-isla-scroll] [id^="pjc-q-"]', { timeout: 15000 });
    ok('la flecha de «Pagar» vuelve a la pantalla 0, a lo elegido', await a.page.locator('[data-isla-scroll] [id^="pjc-q-"]').count() > 0);
    await a.ctx.close();

    // ── 4. La vuelta de Google SIN cuenta → el alta en «Tus datos», sin salir de la compra ────────────────────────────
    limitadoresACero();
    const b = await contexto(async () => {
        const cookie = (await b.ctx.cookies()).find((c) => /-session$/.test(c.name))?.value ?? '';

        ok('el perfil de Google espera en la sesión del servidor', perfilDeGoogleEnLaSesion(cookie).split('\n').pop() === NUEVA.email);
    });
    const hb = helpers(b.page);

    await reservarDesdeLaCalculadora(b.page);
    await hb.hastaPaso('Tus datos');
    await hb.quieta();
    await b.page.locator('[data-isla-scroll] button', { hasText: /Continuar con Google/ }).first().click();
    await b.page.waitForURL(/\/kids(\?|$)/, { timeout: 20000 });
    await b.page.waitForFunction((correo) => (document.querySelector('[data-isla-scroll]')?.textContent ?? '').includes(correo), NUEVA.email, { timeout: 20000 });
    await hb.quieta();
    const alta = await hb.cuerpo();
    ok('sin cuenta: la compra se reabre en «Tus datos» con «Con tu cuenta de Google: …»', /Con tu cuenta de Google/.test(alta) && (await hb.paso()).includes('Tus datos'), alta.slice(0, 120));
    ok('con el nombre que da Google, y sin correo ni contraseña que teclear', await b.page.inputValue('#pjc-nombre') === NUEVA.nombre && await b.page.locator('#pjc-correo, #pjc-clave').count() === 0);
    ok('sin «¿Ya has venido? Entra» ni el botón de Google', ! /Ya has venido|Continuar con Google/.test(alta));
    ok('y sin teléfono: es una entrada (`#787`: solo las fiestas lo piden)', await b.page.locator('#pjc-tel').count() === 0);
    await hb.foto('4-alta-google');
    if (await b.page.locator('#pjc-descargo').count()) await b.page.check('#pjc-descargo');
    await hb.accion(/^Continuar al pago$/).click();
    await hb.hastaPaso('Pagar');
    await hb.quieta();
    ok('la cuenta NACE al seguir (con su vínculo de Google y sin teléfono)', tinker(`echo App\\Domain\\Identity\\Models\\User::where('email', '${NUEVA.email}')->whereHas('identities')->whereNull('phone')->exists() ? 1 : 0;`) === '1');
    ok('y la compra sigue a «Pagar» sin pedir nada más, sin haber salido a Mi cuenta', (await hb.paso()).includes('Pagar') && ! /Mi cuenta/.test(await hb.cuerpo()), await hb.paso());
    await hb.foto('5-alta-pagar');
    await b.ctx.close();

    ok('sin errores en la consola', errores.length === 0, errores.slice(0, 3).join(' | '));
} catch (e) {
    // Dónde se quedó: la URL y una foto, para no adivinar.
    await actual?.screenshot({ path: `${SALIDA}/directa-${ANCHO}-fallo.png` }).catch(() => {});
    ok('el recorrido terminó', false, `${e.message.split('\n')[0]} · en ${actual?.url() ?? '?'}`);
} finally {
    await navegador.close();
    anonimizarNueva();
}

for (const f of filas) console.log(f);
const fallos = filas.filter((f) => f.startsWith('✗')).length;

console.log(`\n${filas.length - fallos}/${filas.length}`);
process.exit(fallos === 0 ? 0 : 1);
