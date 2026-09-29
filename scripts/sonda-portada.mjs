/**
 * **LA PORTADA, EN VIVO** (T6a de `docs/specs/isla-y-landing-nueva.md` §4.17; `DECISIONES #826` y `#827`): la portada que
 * declara el paquete de la instancia (`'portada' => true`), servida por `/`, en un navegador. Lo que se comprueba:
 *   1. `/` la pinta: sus ocho piezas y el pie, `index`; y las tres salidas «Reservar» de la página (cabecera, visor y
 *      cierre) llevan `data-isla-planes` y bajan al reparto sin JavaScript (`href="#reparto"`, que existe).
 *   2. EL SELECTOR DE PLANES (T6a·1): cada «Reservar» —el de la cabecera, el de la isla, el del visor (que se cierra) y el
 *      del cierre— lo abre, sin saltar al ancla; sus opciones son las de la configuración de la página (`#jw-isla-pagina`:
 *      «Para hoy» solo con huecos) y cada plan abre la COMPRA con su intención: Kids y Jump, su entrada; el cumpleaños, la
 *      fiesta.
 *   3. LAS PUERTAS (`#827`): `/login` pinta la portada con `noindex` y abre «Entra» en la isla.
 *   4. MI CUENTA: la bienvenida de una cuenta sin reservas (la API servida vacía), en la portada, abre el selector con
 *      «Reserva tu primera visita» (en una página sin selector abre la compra: eso lo prueba `sonda-cuenta.mjs`).
 *   5. LO DEMÁS VIVO: las pestañas de «¿Qué hay en cada zona?», las garantías plegables del cierre (`<details>`) y el
 *      vídeo de la cabecera, que corre en bucle y, con «reducir movimiento», se queda en su póster.
 * Sin pagar: no deja pedidos. Sale con 1 si algo falla; las fotos, en `storage/app/audit/portada-<ancho>-*.png`.
 * ⚠️ «Para hoy» solo se puede ver con huecos HOY (la hora del parque): fuera de ese rato, la sonda lo dice y comprueba
 * que no está. Y en ese rato la acción de la isla es «Reservar para hoy», no «Reservar»: la sonda la espera según `today`.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-portada.mjs [390|1280]
 */
/* global console, document, window, location, fetch -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { Buffer } from 'node:buffer';
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const SALIDA = 'storage/app/audit';
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' };
const POLITICA = '2026-09-24'; // CookieConsent::POLICY_VERSION
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }
if (tinker('echo app(App\\Http\\Instancia\\InstancePages::class)->portada()?->slug ?? "";') === '') { console.error('✗ el paquete no declara portada'); process.exit(1); }
if (tinker(`echo App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->exists() ? 1 : 0;`) !== '1') { console.error(`✗ falta la cuenta de pruebas ${CLIENTE.email} (receta en sonda-isla.mjs)`); process.exit(1); }
tinker(`$id = App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->value('id'); foreach ([md5('api'.'user:'.$id), md5('api'.'ip:127.0.0.1'), Illuminate\\Support\\Str::transliterate('${CLIENTE.email}|127.0.0.1'), 'login-ip|127.0.0.1'] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }`);

const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);

await mkdir(SALIDA, { recursive: true });
const navegador = await chromium.launch();
const errores = [];

/** Un navegador nuevo, con las cookies ya decididas (la vuelta: nada aceptado), para que el aviso no tape la isla. */
async function contexto(opciones = {}) {
    const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 600 ? 844 : 900 }, locale: 'es-ES', ...opciones });
    await ctx.addCookies([{ name: 'cookie_consent', value: Buffer.from(JSON.stringify({ v: POLITICA, cats: {} })).toString('base64'), url: BASE }]);
    const page = await ctx.newPage();

    page.on('pageerror', (e) => errores.push(e.message));
    // Los «no» esperados (401 sin sesión) no son errores de la página.
    page.on('console', (m) => { if (m.type() === 'error' && ! /status of 401/.test(m.text())) errores.push(m.text()); });

    return { ctx, page };
}

const helpers = (page) => ({
    isla: async () => (await page.locator('[data-isla]').innerText().catch(() => '')).replace(/\s+/g, ' '),
    selector: () => page.locator('[data-isla]').getByText('¿Qué quieres reservar?').isVisible().catch(() => false),
    hastaSelector: () => page.locator('[data-isla]').getByText('¿Qué quieres reservar?').waitFor({ timeout: 10000 }).then(() => true, () => false),
    paso: async () => (await page.locator('#isla-compra-paso').innerText().catch(() => '')).trim(),
    cuerpo: async () => (await page.locator('[data-isla-scroll]').innerText().catch(() => '')).replace(/\s+/g, ' '),
    hash: () => page.evaluate(() => location.hash),
    foto: (n) => page.screenshot({ path: `${SALIDA}/portada-${ANCHO}-${n}.png` }),
    cargar: async (ruta = '/') => { await page.goto('about:blank'); await page.goto(`${BASE}${ruta}`, { waitUntil: 'networkidle' }); await page.waitForTimeout(500); },
});

/** Entra con la cuenta de pruebas por la API, con la cookie CSRF del sitio. */
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
    const a = await contexto();
    const h = helpers(a.page);

    // ── 1 · La página ─────────────────────────────────────────────────────────────────────────────────────────────
    await h.cargar('/');
    const pagina = await a.page.evaluate(() => ({
        piezas: ['.pj-q1', '#reparto', '#miralo', '#fiesta', '#para-ti', '#tranquilidad', '#donde', '#cierre', '.pj-pie'].filter((s) => ! document.querySelector(s)),
        robots: document.querySelector('meta[name=robots]')?.content,
        planes: [...document.querySelectorAll('main [data-isla-planes]')].map((b) => b.getAttribute('href') ?? b.dataset.pjVisorAccion),
        reparto: Boolean(document.getElementById('reparto')),
        config: JSON.parse(document.getElementById('jw-isla-pagina')?.textContent ?? '{}').config ?? {},
        ancho: document.documentElement.scrollWidth,
    }));
    ok('`/` pinta la portada: las ocho piezas y el pie', pagina.piezas.length === 0, pagina.piezas.join(', '));
    ok('y se indexa', pagina.robots === 'index, follow', pagina.robots);
    ok('sin desbordar a lo ancho', pagina.ancho <= ANCHO, pagina.ancho);
    ok('tres «Reservar» de la página abren el selector y, sin JavaScript, bajan al reparto', pagina.planes.length === 3 && pagina.planes.every((x) => x === '#reparto') && pagina.reparto, JSON.stringify(pagina.planes));
    const opciones = (pagina.config.plans?.options ?? []).map((o) => o.title);
    const conHuecos = Boolean(pagina.config.today?.slots);
    ok(`las opciones del selector: tres planes${conHuecos ? ' y «Para hoy» (hay huecos hoy)' : ', sin «Para hoy» (hoy no quedan huecos)'}`,
        JSON.stringify(opciones) === JSON.stringify(['Un cumpleaños', 'Entrada Kids', 'Entrada Jump', ...(conHuecos ? ['Para hoy'] : [])]), JSON.stringify(opciones));
    await h.foto('1-llegada');

    // ── 2 · El selector, desde cada «Reservar», y cada plan a su compra ──────────────────────────────────────────────
    await a.page.locator('.pj-vh [data-isla-planes]').click();
    ok('«Reservar» de la cabecera abre el selector, sin saltar al ancla', await h.hastaSelector() && await h.hash() === '', await h.hash());
    await h.foto('2-selector');
    const planes = [
        ['Entrada Kids', (paso, cuerpo) => paso === 'Cuándo y cuántos' && /Entrada KIDS/i.test(cuerpo)],
        ['Entrada Jump', (paso, cuerpo) => paso === 'Cuándo y cuántos' && /Entrada JUMP/i.test(cuerpo)],
        ['Un cumpleaños', (paso, cuerpo) => paso === 'Cuándo y cuántos' && cuerpo.includes('¿Cuántos años cumple?')],
    ];
    for (const [plan, esperado] of planes) {
        if (! await h.selector()) {
            await h.cargar('/');
            await a.page.locator('.pj-vh [data-isla-planes]').click();
            await h.hastaSelector();
        }
        await a.page.locator('[data-isla] button', { hasText: plan }).first().click();
        await a.page.locator('#isla-compra-paso').waitFor({ timeout: 15000 }).catch(() => {});
        await a.page.waitForTimeout(1800);
        const paso = await h.paso();
        const cuerpo = await h.cuerpo();

        ok(`«${plan}» abre la compra con su intención`, esperado(paso, cuerpo), `${paso} · ${cuerpo.slice(0, 120)}`);
        await h.foto(`3-${plan.split(' ').pop().toLowerCase()}`);
        // `#831`: nacida del selector, su flecha lo reabre, sin cerrar la isla (`compra.jsx` del diseño).
        const volver = a.page.locator('[data-isla] button[aria-label="Volver"]');
        const conFlecha = await volver.count() === 1;
        if (conFlecha) await volver.click();
        const reabierto = conFlecha && await h.hastaSelector();
        ok(`y su flecha cierra la compra y vuelve a abrir el selector`, reabierto && await a.page.locator('#isla-compra-paso').count() === 0, `flecha ${conFlecha} · selector ${reabierto}`);
    }

    await h.cargar('/');
    await a.page.evaluate(() => window.scrollTo(0, document.getElementById('miralo').offsetTop));
    await a.page.waitForTimeout(900);
    // Con huecos hoy y el parque por abrir o abierto, la acción de la isla es «Reservar para hoy» (`situacion.js::leerHoy`);
    // buscando «Reservar» a secas, la sonda fallaba a esas horas (29-09: 13/14, también con el `HEAD` de control).
    const hoy = pagina.config.today ?? {};
    const accionIsla = hoy.slots && ['antes', 'abierto'].includes(hoy.state) ? 'Reservar para hoy' : 'Reservar';
    await a.page.locator('[data-isla]').getByRole('button', { name: accionIsla, exact: true }).first().click();
    ok(`«${accionIsla}» de la isla abre el selector`, await h.hastaSelector(), hoy.state ?? 'sin hoy');

    await h.cargar('/');
    await a.page.locator('.pj-q3__planos [data-pj-visor-abrir]').first().scrollIntoViewIfNeeded();
    await a.page.locator('.pj-q3__planos [data-pj-visor-abrir]').first().click();
    await a.page.waitForTimeout(600);
    const visorAbierto = await a.page.locator('[data-pj-visor]').isVisible();
    await a.page.locator('[data-pj-visor] [data-pj-visor-accion]').click();
    const trasVisor = await h.hastaSelector();
    await a.page.waitForTimeout(300);
    ok('«Reservar» del visor lo cierra y abre el selector, sin saltar al ancla', visorAbierto && trasVisor && ! await a.page.locator('[data-pj-visor]').isVisible() && await h.hash() === '', `visor ${visorAbierto} · hash «${await h.hash()}»`);

    await h.cargar('/');
    await a.page.locator('#cierre [data-isla-planes]').scrollIntoViewIfNeeded();
    await a.page.locator('#cierre [data-isla-planes]').click();
    ok('«Reservar» del cierre abre el selector, sin saltar al ancla', await h.hastaSelector() && await h.hash() === '', await h.hash());

    // ── 5 · Lo demás vivo ─────────────────────────────────────────────────────────────────────────────────────────────
    await h.cargar('/');
    await a.page.locator('.pj-ze [role=tab]', { hasText: 'Jump' }).scrollIntoViewIfNeeded();
    await a.page.locator('.pj-ze [role=tab]', { hasText: 'Jump' }).click();
    await a.page.waitForTimeout(300);
    const pestanas = await a.page.evaluate(() => [...document.querySelectorAll('.pj-ze [role=tabpanel]')].map((p) => `${p.id.split('-').pop()}:${p.hidden ? 'oculta' : 'vista'}`));
    ok('«¿Qué hay en cada zona?»: la pestaña Jump enseña su panel y esconde el de Kids', JSON.stringify(pestanas) === '["kids:oculta","jump:vista"]', JSON.stringify(pestanas));
    const garantia = a.page.locator('#cierre details').first();
    await garantia.scrollIntoViewIfNeeded();
    const antes = await garantia.evaluate((d) => d.open);
    await garantia.locator('summary').click();
    ok('las garantías del cierre, plegadas; se abren al tocar', antes === false && await garantia.evaluate((d) => d.open) === true);
    await a.page.evaluate(() => window.scrollTo(0, 0));
    await a.page.waitForTimeout(1200);
    // ⚠️ El Chromium de Playwright no trae H.264 (medido: `canPlayType` vacío y `error` 4), y el vídeo lo es, como el de
    // la portada de siempre: aquí solo se puede ver que está bien pedido; que corre, en un navegador de verdad.
    const video = await a.page.evaluate(() => {
        const v = document.querySelector('[data-pj-vh-video]');

        return v ? { h264: v.canPlayType('video/mp4; codecs="avc1.640028"') !== '', corre: ! v.paused, t: v.currentTime, pedido: v.autoplay && v.muted && v.loop && v.playsInline && Boolean(v.poster) } : null;
    });
    ok(video?.h264 ? 'el vídeo de la cabecera corre en bucle' : 'el vídeo de la cabecera, bien pedido (en bucle, mudo, con póster) — que corre, NO verificable aquí: este Chromium no trae H.264',
        video !== null && video.pedido && (! video.h264 || (video.corre && video.t > 0)), JSON.stringify(video));
    await a.ctx.close();

    const quieto = await contexto({ reducedMotion: 'reduce' });
    await helpers(quieto.page).cargar('/');
    await quieto.page.waitForTimeout(1200);
    const video2 = await quieto.page.evaluate(() => { const v = document.querySelector('[data-pj-vh-video]'); return v ? { corre: ! v.paused, auto: v.autoplay, poster: Boolean(v.poster) } : null; });
    ok('con «reducir movimiento», el vídeo se queda en su póster', video2?.corre === false && video2.auto === false && video2.poster, JSON.stringify(video2));
    await quieto.ctx.close();

    // ── 3 · Las puertas ──────────────────────────────────────────────────────────────────────────────────────────
    const b = await contexto();
    const hb = helpers(b.page);
    await hb.cargar('/login');
    const puerta = await b.page.evaluate(() => ({ robots: document.querySelector('meta[name=robots]')?.content, reparto: Boolean(document.getElementById('reparto')) }));
    await b.page.waitForTimeout(800);
    ok('`/login` pinta la portada con `noindex` y abre «Entra» en la isla', puerta.reparto && puerta.robots === 'noindex, nofollow' && (await hb.isla()).includes('Entra'), `${JSON.stringify(puerta)} · ${(await hb.isla()).slice(0, 60)}`);
    await hb.foto('4-login');

    // ── 4 · Mi cuenta: la bienvenida, en la portada, abre el selector ─────────────────────────────────────────────────
    ok('se entra por la API con la cuenta de pruebas', await entrarPorLaApi(b.page) === 200);
    const vacia = (ruta) => ruta.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }) });
    await b.page.route('**/api/v1/me/reservations/upcoming**', vacia);
    await b.page.route('**/api/v1/me/reservations/past**', vacia);
    await hb.cargar('/#mi-cuenta');
    const primera = b.page.locator('section[aria-labelledby="bienvenida-t"]').getByRole('button', { name: 'Reserva tu primera visita' });
    await primera.waitFor({ timeout: 15000 }).catch(() => {});
    const hayBienvenida = await primera.isVisible().catch(() => false);
    await primera.click().catch(() => {});
    ok('la bienvenida de Mi cuenta, en la portada: «Reserva tu primera visita» abre el selector de planes', hayBienvenida && await hb.hastaSelector(), (await hb.isla()).slice(0, 120));
    await hb.foto('5-primera-visita');
    await b.ctx.close();
} catch (e) {
    ok('la sonda terminó sin excepciones', false, e.message);
} finally {
    await navegador.close();
}

ok('sin errores en la consola de la página', errores.length === 0, errores.slice(0, 3).join(' | '));
console.log(`\nSONDA DE LA PORTADA · ${ANCHO}px\n${filas.join('\n')}`);
const fallos = filas.filter((f) => f.startsWith('✗')).length;
console.log(`\n${filas.length - fallos}/${filas.length}`);
process.exit(fallos ? 1 : 0);
