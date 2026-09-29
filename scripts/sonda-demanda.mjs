/**
 * **LA DEMANDA SIN HUECO DE LA ISLA, EN VIVO** (`DECISIONES #758`; `docs/specs/isla-y-landing-nueva.md` §4.26): los
 * `availability_missing` que llegan de verdad a `analytics_events` desde `/colegios`, cuyas excursiones (en LOCAL) no se
 * venden hasta el mes que viene. Lo que se compara es la BD con lo que la regla del cajón (`missingMonths`) espera de los
 * días que la API da en ese momento:
 *   0. CONTROL: el tracker entrega (su `page_viewed` llega); sin él, un «ninguno» no mediría nada.
 *   1. LLEGAR Y ACERCAR LA CALCULADORA (arranca sola y pide los días de sus filas): NINGUNO.
 *   2. TOCARLA (otra gente): uno por mes sin días de la fila que MIRA, y solo de ésa.
 *   3. OTRA DURACIÓN: los de la suya.
 *   4. LA COMPRA de la isla con la fila del paso 2 (`openWith`): NINGUNO más —un reportero por página—; su control, el
 *      `drawer_opened` de esa apertura.
 *   5. OTRA VISITA (recargar) y tocarla: vuelve a contar, una vez por visita como el cajón.
 * El envío se fuerza con un `pagehide` (el tracker vacía su cola con `sendBeacon`). Al terminar BORRA los
 * `availability_missing` que dejó (sesión de robot, `webdriver`): el cuadro no filtra robots en esa cifra (§4.26). No
 * deja pedidos. Sale con 1 si algo falla.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-demanda.mjs [390|1280]
 */
/* global console, document, window, fetch -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { Buffer } from 'node:buffer';
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const POLITICA = '2026-09-24'; // CookieConsent::POLICY_VERSION
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }
tinker('foreach ([md5("api"."ip:127.0.0.1")] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }');
const DESDE = Number(tinker("echo DB::table('analytics_events')->max('id') ?? 0;"));

/** Lo que la sonda ha dejado en la BD desde que arrancó: eventos de `/colegios` de una sesión de robot. */
const deLaSonda = () => JSON.parse(tinker(`echo json_encode(DB::table('analytics_events as e')->join('analytics_sessions as s', 's.id', '=', 'e.session_id')
    ->where('e.id', '>', ${DESDE})->where('s.is_bot', true)->where('e.route', '/colegios')->orderBy('e.id')->get(['e.id', 'e.name', 'e.props'])
    ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'props' => json_decode((string) $r->props, true)])->all());`) || '[]');
const faltas = (eventos) => eventos.filter((e) => e.name === 'availability_missing').map((e) => `${e.props.product}|${e.props.month}`);
/** Espera a que la BD tenga lo que `listo` pide (el control de cada paso) y devuelve lo que hay. */
async function hasta(listo, ms = 15000) {
    for (let t = 0; t < ms; t += 500) {
        const ev = deLaSonda();

        if (listo(ev)) return ev;
        await esperar(500);
    }

    return deLaSonda();
}

const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);
const errores = [];
const navegador = await chromium.launch();

async function contexto() {
    const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 600 ? 844 : 900 }, locale: 'es-ES', deviceScaleFactor: 1 });
    await ctx.addCookies([{ name: 'cookie_consent', value: Buffer.from(JSON.stringify({ v: POLITICA, cats: {} })).toString('base64'), url: BASE }]);
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errores.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error' && ! /status of 401/.test(m.text())) errores.push(m.text()); });

    return { ctx, page };
}
async function llegar(page) {
    await page.goto(`${BASE}/colegios`, { waitUntil: 'networkidle' });
    await page.locator('#calcula').scrollIntoViewIfNeeded();
    await page.locator('[data-jw-calculadora] input[inputmode="numeric"]').waitFor({ timeout: 15000 });
    await page.waitForTimeout(2500);
}
/** Vacía la cola del tracker como al irse de la página: `sendBeacon` con lo que haya. */
const enviar = (page) => page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
async function tocar(page, n) {
    const cifra = page.locator('[data-jw-calculadora] input[inputmode="numeric"]');
    await cifra.click();
    await cifra.fill(String(n));
    await cifra.press('Enter');
    await page.waitForTimeout(2000);
}

try {
    const a = await contexto();
    await llegar(a.page);
    const calc = await a.page.evaluate(() => JSON.parse(document.querySelector('[data-jw-calculadora]')?.dataset.jwCalculadora ?? 'null'));
    const ids = (calc?.filas ?? []).map((f) => f.id);
    // Lo que la regla del cajón espera de cada fila con los días que da la API AHORA, con el reloj del navegador.
    const esperado = await a.page.evaluate(async (lista) => {
        const mes = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        const out = {};
        for (const id of lista) {
            const dias = (await (await fetch(`/api/v1/availability/${id}/dates`, { headers: { Accept: 'application/json' } })).json()).data ?? [];
            const primero = dias.map((d) => d.date.slice(0, 7)).sort()[0] ?? null;
            const meses = [];
            for (let d = new Date(); ; d = new Date(d.getFullYear(), d.getMonth() + 1, 1)) {
                if (primero !== null && mes(d) >= primero) break;
                meses.push(`${id}|${mes(d)}`);
                if (primero === null) break;
            }
            out[id] = meses;
        }
        return out;
    }, ids);
    if (ids.length < 2 || ids.some((id) => esperado[id].length === 0)) {
        console.error(`✗ sin caso: hacen falta dos filas sin días este mes (${JSON.stringify(esperado)})`);
        process.exit(1);
    }

    // ── 0 y 1 · El control y llegar ─────────────────────────────────────────────────────────────────────────────────
    await enviar(a.page);
    let ev = await hasta((e) => e.some((x) => x.name === 'page_viewed'));
    ok('control: el tracker entrega (el `page_viewed` de /colegios llega a la BD)', ev.some((x) => x.name === 'page_viewed'), `${ev.length} eventos`);
    ok('llegar y acercar la calculadora (pide los días de sus filas) NO informa', faltas(ev).length === 0, faltas(ev).join(', ') || 'ninguno');

    // ── 2 · Tocarla ──────────────────────────────────────────────────────────────────────────────────────────────────
    await tocar(a.page, Number(await a.page.locator('[data-jw-calculadora] input[inputmode="numeric"]').inputValue()) + 5);
    const mirada = Number(await a.page.locator('#calcula input[type="radio"]:checked').getAttribute('value'));
    await enviar(a.page);
    ev = await hasta((e) => faltas(e).length >= esperado[mirada].length);
    ok('tocarla informa la fila que MIRA, un evento por mes sin días', JSON.stringify(faltas(ev)) === JSON.stringify(esperado[mirada]), `${faltas(ev).join(', ')} · esperado ${esperado[mirada].join(', ')}`);

    // ── 3 · Otra duración ───────────────────────────────────────────────────────────────────────────────────────────
    const otra = ids.find((id) => id !== mirada);
    // Clic NATIVO: el radio lo lleva Vue y `check({force})` no lo cambia.
    await a.page.locator(`#calcula input[type="radio"][value="${otra}"]`).evaluate((el) => el.click());
    await a.page.waitForTimeout(2000);
    ok('control: la otra duración quedó elegida', Number(await a.page.locator('#calcula input[type="radio"]:checked').getAttribute('value')) === otra);
    await enviar(a.page);
    ev = await hasta((e) => faltas(e).length >= esperado[mirada].length + esperado[otra].length);
    ok('otra duración: los suyos, y nada repetido', JSON.stringify(faltas(ev)) === JSON.stringify([...esperado[mirada], ...esperado[otra]]), faltas(ev).join(', '));

    // ── 4 · La compra, con la fila del paso 2 ────────────────────────────────────────────────────────────────────────
    const antes = faltas(ev).length;
    await a.page.evaluate((id) => window.JumpWeb.cajon.openWith({ type: 'product', id }), mirada);
    await a.page.locator('[role="dialog"][data-isla-velo]').waitFor({ timeout: 15000 });
    await a.page.waitForTimeout(3000);
    await enviar(a.page);
    ev = await hasta((e) => e.some((x) => x.name === 'drawer_opened'));
    ok('control: la compra de la isla se abrió (su `drawer_opened` llega)', ev.some((x) => x.name === 'drawer_opened'));
    ok('la compra con la MISMA fila no cuenta otra vez (un reportero por página)', faltas(ev).length === antes, `${faltas(ev).length - antes} nuevos`);
    await a.ctx.close();

    // ── 5 · Otra visita: la compra PRIMERO (sin tocar la calculadora) cuenta; la calculadora después, con esa fila, no ──
    const b = await contexto();
    await llegar(b.page);
    await b.page.evaluate((id) => window.JumpWeb.cajon.openWith({ type: 'product', id }), mirada);
    await b.page.locator('[role="dialog"][data-isla-velo]').waitFor({ timeout: 15000 });
    await b.page.waitForTimeout(3000);
    await enviar(b.page);
    ev = await hasta((e) => faltas(e).length >= antes + esperado[mirada].length);
    ok('otra visita: la compra SOLA informa su fila (vuelve a contar, una vez por visita)', JSON.stringify(faltas(ev).slice(antes)) === JSON.stringify(esperado[mirada]), faltas(ev).slice(antes).join(', ') || 'ninguno');
    const antesB = faltas(ev).length;
    await b.page.evaluate(() => window.JumpWeb.cajon.close());
    await b.page.waitForTimeout(1500);
    ok('control: la calculadora mira la misma fila que la compra', Number(await b.page.locator('#calcula input[type="radio"]:checked').getAttribute('value')) === mirada);
    await tocar(b.page, 40);
    await enviar(b.page);
    await esperar(5000);
    ev = deLaSonda();
    ok('y tocar la calculadora después, con esa fila, no cuenta otra vez', faltas(ev).length === antesB, `${faltas(ev).length - antesB} nuevos`);
    await b.ctx.close();

    ok('sin errores en la consola', errores.length === 0, errores.join(' | '));
} catch (e) {
    ok('la sonda terminó', false, e.message);
} finally {
    await navegador.close();
    const borrados = tinker(`echo DB::table('analytics_events')->where('id', '>', ${DESDE})->where('name', 'availability_missing')
        ->whereIn('session_id', DB::table('analytics_sessions')->where('is_bot', true)->select('id'))->delete();`);
    filas.push(`· borrados ${borrados} \`availability_missing\` de la sonda`);
}

console.log(filas.join('\n'));
process.exit(filas.some((f) => f.startsWith('✗')) ? 1 : 0);
