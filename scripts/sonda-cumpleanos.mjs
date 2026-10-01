/**
 * **CUMPLEAÑOS, EN VIVO** (T6b de `docs/specs/isla-y-landing-nueva.md` §4.18; `DECISIONES #832`→`#836`): la página que
 * declara el paquete de la instancia y OCUPA `/cumpleanos`, en un navegador. Lo que se comprueba:
 *   1. LA PÁGINA: `/cumpleanos` la pinta con sus hechos —la duda de la hora extra y el ajuste de invitados (`#834`), los
 *      días con hueco con su fecha, y la invitación y «quien prefiere irse firma» SOLO si todos los packs los tienen
 *      (`#835`, leído de su ficha en la API)— y en el móvil no se sale de ancho.
 *   2. LA CALCULADORA DE LA FIESTA (`#836`), al llegar: sin edad no hay total, «Hoy pagas» enseña la señal y la hora extra
 *      espera a la hora.
 *   3. UN DÍA CON HUECO, pulsado, queda elegido en la calculadora; y la ISLA pide lo que falta, la edad, con su ancla.
 *   4. ELIGIENDO (la edad mayor, el día, la primera hora libre, el menú de pago y la hora extra si cabe): el total, la
 *      señal y el resto que se ven son los que el SERVIDOR da a esa misma selección (`PAY-12`), y la isla lo resume sin el
 *      botón de la calculadora a la vista; con él, no lo repite: dice la razón de la pieza (Z6b, opción C).
 *   5. «RESERVAR Y PAGAR LA SEÑAL» valida la línea con la edad, el menú y la hora extra, y abre la compra de la isla.
 * Sin pagar: no deja pedidos (sin sesión se para en «Tus datos»). Sale con 1 si algo falla; las fotos, en
 * `storage/app/audit/cumpleanos-<ancho>-*.png`.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-cumpleanos.mjs [390|1280]
 */
/* global console, document, window, fetch -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { Buffer } from 'node:buffer';
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const SALIDA = 'storage/app/audit';
const POLITICA = '2026-09-24'; // CookieConsent::POLICY_VERSION
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }
if (tinker('echo app(App\\Http\\Instancia\\InstancePages::class)->queOcupa("cumpleanos")?->slug ?? "";') === '') { console.error('✗ el paquete no declara una página que ocupe /cumpleanos'); process.exit(1); }
tinker('foreach ([md5("api"."ip:127.0.0.1")] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }');

const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);
/** «183,60 €» → 18360. */
const centimos = (texto) => Math.round(Number(String(texto ?? '').replace(/[^\d,]/g, '').replace(',', '.')) * 100);

await mkdir(SALIDA, { recursive: true });
const navegador = await chromium.launch();
const errores = [];

async function pagina() {
    const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 600 ? 844 : 900 }, locale: 'es-ES' });
    await ctx.addCookies([{ name: 'cookie_consent', value: Buffer.from(JSON.stringify({ v: POLITICA, cats: {} })).toString('base64'), url: BASE }]);
    const page = await ctx.newPage();

    page.on('pageerror', (e) => errores.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error' && ! /status of 401/.test(m.text())) errores.push(m.text()); });
    await page.goto(`${BASE}/cumpleanos`, { waitUntil: 'networkidle' });
    await page.waitForSelector('#p6-edad', { timeout: 15000 });

    return { ctx, page };
}
const texto = (page, sel) => page.evaluate((s) => (document.querySelector(s)?.innerText ?? '').replace(/\s+/g, ' ').trim(), sel);
const foto = (page, nombre) => page.screenshot({ path: `${SALIDA}/cumpleanos-${ANCHO}-${nombre}.png` });
/**
 * Lo que la isla enseña (Z6b): su banner (el título de la razón), su frase y si hay un botón de la página a la vista, con la
 * geometría de `pagina.js` (`medirVista`: fuera de la franja de la isla y sin lo que la llegada esconde).
 */
const islaAhora = (page) => page.evaluate(() => {
    const isla = document.querySelector('[data-situation]');
    const caja = isla?.getBoundingClientRect();
    const alto = window.innerHeight;
    let arriba = 0;
    let abajo = alto;
    if (caja && caja.height) { if (caja.top > alto / 2) abajo = caja.top; else arriba = caja.bottom; }
    const ve = (el) => { if (el.closest('[data-llegada="oculto"]')) return false; const r = el.getBoundingClientRect(); return r.height > 0 && r.bottom > arriba && r.top < abajo; };
    const frases = Array.from(isla?.querySelectorAll('[aria-live="polite"]') ?? []).map((n) => n.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean);

    return { cta: Array.from(document.querySelectorAll('[data-isla-cta]')).some(ve), banner: isla?.querySelector('[data-isla-razon] b')?.textContent.trim() ?? null, frase: frases[0] ?? null };
});
/** Baja desde arriba hasta un sitio sin ningún botón de la página a la vista, y espera a que la isla se asiente. */
async function sinBotonALaVista(page) {
    const total = await page.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < total; y += 250) {
        await page.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), y);
        await page.waitForTimeout(350);
        if (! (await islaAhora(page)).cta) { await page.waitForTimeout(1200); return true; }
    }

    return false;
}

try {
    // ── 1 · La página, con sus hechos ─────────────────────────────────────────────────────────────────────────────
    const a = await pagina();
    const calc = await a.page.evaluate(() => JSON.parse(document.querySelector('[data-jw-calculadora-fiesta]')?.dataset.jwCalculadoraFiesta ?? 'null'));
    ok('la página monta la calculadora de la fiesta con sus packs', Array.isArray(calc?.packs) && calc.packs.length > 0, calc?.packs?.map((p) => p.name).join(', '));
    const fichas = await a.page.evaluate(async (ids) => Promise.all(ids.map(async (id) => (await fetch(`/api/v1/catalog/products/${id}`, { headers: { Accept: 'application/json' } })).json())), calc.packs.map((p) => p.id));
    const todos = (f) => fichas.every(f);
    const cuerpo = await texto(a.page, 'main');
    ok('la invitación digital sale SOLO si todos los packs la traen (`#835`)', cuerpo.includes('O comparte la invitación') === todos((f) => f.guest_invitation === true));
    ok('«quien prefiere irse firma» sale SOLO si todos los packs admiten la firma', /firma desde el móvil/.test(cuerpo) === todos((f) => f.guardian_mode !== 'none'));
    const conHoraExtra = Object.keys(calc.extensiones ?? {}).length > 0;
    ok('la duda de la hora extra sale SOLO si algún pack se alarga (`#834`)', /¿Se puede alargar la fiesta\?/.test(cuerpo) === conHoraExtra);
    ok('«¿Cuántos niños…?» con su ajuste («ajusta hasta…»)', /ajusta hasta/.test(cuerpo));
    const dias = await a.page.$$eval('[data-jw-calculadora-dia]', (as) => as.map((x) => x.dataset.jwCalculadoraDia));
    ok('los días con hueco llevan su fecha (4 como mucho)', dias.length <= 4 && dias.every((d) => /^\d{4}-\d{2}-\d{2}$/.test(d)), dias.join(', ') || 'ninguno');
    ok('no se sale de ancho', await a.page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));

    // ── 2 · La calculadora, al llegar ─────────────────────────────────────────────────────────────────────────────
    await a.page.evaluate(() => document.querySelector('#calcula').scrollIntoView());
    await a.page.waitForTimeout(900);
    const lado = await texto(a.page, '[data-jw-calculadora-lado]');
    ok('al llegar: sin edad no hay total, y «Hoy pagas» enseña la señal', /Elige cuántos años cumple/.test(lado) && /Hoy pagas la señal/.test(lado) && ! /El resto/.test(lado), lado.slice(0, 120));
    ok('la hora extra espera a la hora', ! conHoraExtra || /Elige la hora para saber si cabe/.test(lado));
    await foto(a.page, '1-calculadora');

    // ── 3 · Un día con hueco, pulsado; y la isla pide la edad ─────────────────────────────────────────────────────
    if (dias.length) {
        await a.page.click('[data-jw-calculadora-dia]');
        await a.page.waitForTimeout(1500);
        const elegido = await a.page.$$eval('#p6-dia button[aria-pressed="true"]', (bs) => bs.map((b) => b.getAttribute('aria-label')));
        ok('un día con hueco, pulsado, queda elegido en la calculadora', elegido.length === 1, elegido.join(''));
        // Con un botón de la página a la vista (la cabecera, el de la calculadora) la isla no repite el suyo (el diseño):
        // se mira desde una pieza sin ninguno, «Para tu tranquilidad».
        await a.page.evaluate(() => [...document.querySelectorAll('h2')].find((h) => /tranquilidad/i.test(h.textContent))?.scrollIntoView({ block: 'center' }));
        await a.page.waitForTimeout(1200);
        const isla = await a.page.$$eval('[data-jw-isla] a[href]', (as) => as.map((x) => `${x.innerText.trim()} → ${x.getAttribute('href')}`));
        ok('la isla pide lo que falta, la edad, con su ancla', isla.includes('Elige la edad → #p6-edad'), `${isla.join(' | ')} · ${(await texto(a.page, '[data-jw-isla]')).slice(0, 90)}`);
    } else {
        ok('sin días con hueco: nada que pulsar (la caja no se pinta)', (await a.page.$$('.pj-nd')).length === 0);
    }
    await a.ctx.close();

    // ── 4 · Eligiendo: lo que se ve es lo del servidor ────────────────────────────────────────────────────────────
    const b = await pagina();
    const validadas = [];
    b.page.on('request', (r) => { if (r.url().includes('/cart/validate-line')) validadas.push(JSON.parse(r.postData() ?? '{}').line); });
    const edades = await b.page.$$eval('#p6-edad button', (bs) => bs.map((x) => x.innerText.trim()));
    await b.page.locator('#p6-edad button', { hasText: new RegExp(`^${edades.at(-1)}$`) }).click();
    await b.page.waitForTimeout(700);
    // El día, el primero con hueco (su fecha la dice su marca). Sin ninguno en ocho semanas, la elección no se puede medir
    // aquí: se dice, en vez de medir otra cosa.
    if (! dias.length) throw new Error('sin días con hueco en ocho semanas: la elección no se puede medir');
    await b.page.click('[data-jw-calculadora-dia]');
    await b.page.waitForSelector('#p6-hora [data-hora]:not([disabled])', { timeout: 10000 });
    const hora = await b.page.getAttribute('#p6-hora [data-hora]:not([disabled])', 'data-hora');
    await b.page.click(`#p6-hora [data-hora="${hora}"]`);
    await b.page.waitForTimeout(900);
    const menus = await b.page.$$eval('#p6-menu input[type="radio"]', (is) => is.map((x) => x.value));
    const menu = menus.at(-1);
    await b.page.locator(`#p6-menu input[value="${menu}"]`).check({ force: true });
    await b.page.waitForTimeout(900);
    const casilla = b.page.locator('[data-jw-calculadora-lado] label input[type="checkbox"]');
    const conExtra = conHoraExtra && await casilla.isEnabled().catch(() => false);
    if (conExtra) { await casilla.check({ force: true }); await b.page.waitForTimeout(1200); }

    const eco = await texto(b.page, '#p6-edad');
    const pack = calc.packs.find((p) => eco.includes(p.name));
    ok('la edad elige el pack de su tramo (su eco)', Boolean(pack), eco.slice(-60));
    const ext = calc.extensiones?.[pack?.id];
    const diaElegido = await b.page.$eval('#p6-dia button[aria-pressed="true"]', (x) => x.getAttribute('aria-label')).catch(() => '');
    const iso = dias[0];
    const linea = await b.page.evaluate(async (q) => {
        const r = await fetch(`/api/v1/catalog/products/${q.id}/addons`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(q.cuerpo) });
        return (await r.json()).line;
    }, { id: pack.id, cuerpo: { quantity: pack.min_quantity, date: iso, time: `${hora}:00`, addons: conExtra && ext ? [{ product_id: ext.id, quantity: 1 }] : [], choices: [{ group: 'menu', product_id: Number(menu) }] } });
    const ladoElegido = await texto(b.page, '[data-jw-calculadora-lado]');
    const total = ladoElegido.match(/Total ([\d.,]+\s?€)/)?.[1];
    const hoy = ladoElegido.match(/Hoy pagas la señal ([\d.,]+\s?€)/)?.[1];
    const resto = ladoElegido.match(/El resto, el día de la fiesta ([\d.,]+\s?€)/)?.[1];
    ok('el TOTAL que se ve es el del servidor para esa selección (`PAY-12`)', iso !== null && centimos(total) === linea?.total_cents, `${total} · servidor ${linea?.total_cents} · ${diaElegido} ${hora}`);
    ok('la señal y el resto, también', centimos(hoy) === linea?.deposit_cents && centimos(resto) === linea?.gate_remainder_cents, `${hoy} / ${resto}`);
    ok('la hora extra, si cabe, entra en la línea', ! conExtra || (linea?.addons ?? []).some((x) => x.product_id === ext.id && x.quantity > 0));
    // Z6b (opción C «Da la razón», zip (6)): con el botón de la calculadora a la vista, la isla no lo repite: dice la RAZÓN de
    // la pieza (la que la página declara para `calcula`), sin frase. Apartado el botón, resume lo elegido con el total.
    await b.page.evaluate(() => document.querySelector('[data-jw-calculadora-lado] [data-isla-cta]').scrollIntoView({ block: 'center' }));
    await b.page.waitForTimeout(1600);
    const razonCalcula = await b.page.evaluate(() => JSON.parse(document.getElementById('jw-isla-pagina').textContent).config.razones?.calcula?.text ?? null);
    const conBoton = await islaAhora(b.page);
    ok('con el botón de la calculadora a la vista, la isla dice la razón de la pieza y no lo repite', conBoton.cta && conBoton.banner === razonCalcula && conBoton.frase === null, JSON.stringify(conBoton));
    const apartado = await sinBotonALaVista(b.page);
    const sinBoton = await islaAhora(b.page);
    ok('sin el botón a la vista, la isla resume lo elegido con el total', apartado && total && (sinBoton.frase ?? '').includes(total.replace(/\s/g, ' ')), JSON.stringify(sinBoton));
    await b.page.evaluate(() => document.querySelector('[data-jw-calculadora-lado]').scrollIntoView({ block: 'start' }));
    await b.page.waitForTimeout(400);
    await foto(b.page, '2-elegido');

    // ── 5 · «Reservar y pagar la señal»: la selección entera, a la compra de la isla ──────────────────────────────
    await b.page.locator('[data-jw-calculadora-lado] [data-isla-cta]').click();
    await b.page.waitForTimeout(6000);
    const v = validadas.at(-1);
    const edadEnLinea = Object.values(v?.event_data ?? {})[0];
    ok('la línea validada lleva el pack, la edad, el día y la hora', v?.product_id === pack.id && String(edadEnLinea) === edades.at(-1) && v?.date === iso && v?.time === `${hora}:00`, JSON.stringify(v ?? {}).slice(0, 170));
    ok('…el menú elegido y, si cabe, la hora extra', (v?.addons ?? []).some((x) => String(x.product_id) === menu) && (! conExtra || (v?.addons ?? []).some((x) => x.product_id === ext.id)));
    const compra = await texto(b.page, 'body');
    ok('la compra de la isla se abre con ese pedido', compra.includes(pack.name) && total && compra.includes(total.replace(/\s/g, ' ')));
    await foto(b.page, '3-compra');
    await b.ctx.close();
} catch (e) {
    ok('la sonda terminó sin excepciones', false, e.message);
} finally {
    await navegador.close();
}

ok('sin errores en la consola de la página', errores.length === 0, errores.slice(0, 3).join(' | '));
console.log(`\nSONDA DE CUMPLEAÑOS · ${ANCHO}px\n${filas.join('\n')}`);
const fallos = filas.filter((f) => f.startsWith('✗')).length;
console.log(`\n${filas.length - fallos}/${filas.length}`);
process.exit(fallos ? 1 : 0);
