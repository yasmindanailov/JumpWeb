/**
 * **VISÍTANOS, EN VIVO** (T6d de `docs/specs/isla-y-landing-nueva.md` §4.20): la página de APOYO que declara el paquete de la
 * instancia, en un navegador. Cada dato que pinta se compara con SU hecho de la API, no con una cifra escrita aquí:
 *   1. LA PÁGINA: una `h1`, sin salirse de ancho, sin primario en la cabecera (la acción es de la isla) y «Cómo llegar» a la
 *      ruta del sitio (`/site`), en otra pestaña.
 *   2. [HOY]: la línea de la cabecera dice las horas de HOY (o las de mañana si ya cerró) de `/schedule`, y la isla lo dice
 *      TAMBIÉN desde la llegada (Z6a, zip (6): «Visítanos conserva su [Hoy] grande… y su isla lo dice también: sin él, se
 *      quedaría sin frase»; con el zip del 27-09 la isla callaba mientras se veía, por la marca `[data-hoy-linea]`).
 *   3. EL HORARIO: sus franjas son las de la semana de `/schedule`; las FECHAS ESPECIALES son las próximas cerradas o con
 *      otro horario que la fila de los festivos (tres como mucho).
 *   4. QUÉ TRAER y QUIÉN NECESITA UN ADULTO: el precio de los calcetines es el de la calculadora de Kids (su ficha), y las
 *      alturas, las de `/catalog/zones`.
 *   5. LA CAFETERÍA: su foto y su `alt` son los de `/bar`, y «sin entrada» solo si `free_entry`.
 *   6. LAS DUDAS: el plazo de las entradas y el de los cumpleaños, los de sus productos; el tamaño de los grupos, el de las
 *      fichas de las excursiones; y «aquí» ABRE el selector de planes de la isla.
 *   7. EL CIERRE: el teléfono, WhatsApp, el correo y las redes, los de `/site`; el pie y la isla, con Visítanos como actual.
 *   8. EN Y FR: la página entera, sin marcadores sin rellenar ni claves sueltas.
 * Solo lectura. Sale con 1 si algo falla; las fotos, en `storage/app/audit/visitanos-<ancho>-*.png`.
 * ⚠️ Las URL absolutas de los hechos llevan el puerto de fuera (`APP_URL`, 8081): dentro del contenedor se piden al 80.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-visitanos.mjs [390|1280]
 */
/* global console, document, window, fetch -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { Buffer } from 'node:buffer';
import { URL } from 'node:url';
import { chromium } from 'playwright-core';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const SALIDA = 'storage/app/audit';
const POLITICA = '2026-09-24'; // CookieConsent::POLICY_VERSION
const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);
const errores = [];
/** «90 cm» o «1,30 m», como escribe la página una altura. */
const altura = (cm) => (cm >= 100 ? `${Math.floor(cm / 100)},${String(cm % 100).padStart(2, '0')}` : String(cm)).replace(/,00$/, '') + (cm >= 100 ? ' m' : ' cm');
/** Los espacios, uno (`\s` ya cubre el duro y el estrecho). */
const sinEspacios = (s) => String(s ?? '').replace(/\s+/g, ' ').trim();

await mkdir(SALIDA, { recursive: true });
const navegador = await chromium.launch();

async function contexto() {
    const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 600 ? 844 : 900 }, locale: 'es-ES', deviceScaleFactor: 1 });
    await ctx.addCookies([{ name: 'cookie_consent', value: Buffer.from(JSON.stringify({ v: POLITICA, cats: {} })).toString('base64'), url: BASE }]);
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errores.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error' && ! /status of 401/.test(m.text())) errores.push(m.text()); });
    await page.route('http://localhost:8081/**', (r) => r.continue({ url: r.request().url().replace('localhost:8081', new URL(BASE).host) }));

    return { ctx, page };
}
const api = (page, ruta) => page.evaluate(async (r) => (await fetch(`/api/v1/${r}`, { headers: { Accept: 'application/json' } })).json(), ruta);
const texto = (page, sel) => page.evaluate((s) => (document.querySelector(s)?.innerText ?? '').replace(/\s+/g, ' ').trim(), sel);
const foto = (page, nombre) => page.screenshot({ path: `${SALIDA}/visitanos-${ANCHO}-${nombre}.png` });
const ir = (page, sel) => page.evaluate((s) => document.querySelector(s)?.scrollIntoView({ block: 'start', behavior: 'instant' }), sel);

try {
    const { ctx, page } = await contexto();
    await page.goto(`${BASE}/visitanos`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1500);
    await page.mouse.move(1, 1);
    const [site, schedule, zonas, bar, productos] = await Promise.all([
        api(page, 'site?lang=es'), api(page, 'schedule?lang=es'), api(page, 'catalog/zones?lang=es'), api(page, 'bar?lang=es'), api(page, 'catalog/products?lang=es'),
    ]);
    const catalogo = productos.data ?? productos;

    // ── 1 · La página ────────────────────────────────────────────────────────────────────────────────────────────
    ok('una sola h1', await page.locator('h1').count() === 1);
    ok('no se sale de ancho', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
    ok('la cabecera sin primario: la acción es la de la isla', await page.locator('.pj-vh [data-isla-cta]').count() === 0);
    const ruta = await page.locator('.pj-vh a[target="_blank"]').first().getAttribute('href').catch(() => null);
    ok('«Cómo llegar» abre la ruta del sitio, en otra pestaña', ruta === site.address?.maps_url, ruta);
    await foto(page, '0-llegada');

    // ── 2 · [Hoy]: el horario de hoy de `/schedule`, y la isla que lo dice también (Z6a) ────────────────────────────
    const hoyParque = await page.evaluate((tz) => new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23', weekday: 'short' }).formatToParts(new Date()).reduce((o, p) => ({ ...o, [p.type]: p.value }), {}), schedule.timezone ?? 'Europe/Madrid');
    const fechaHoy = `${hoyParque.year}-${hoyParque.month}-${hoyParque.day}`;
    const ahoraHm = `${hoyParque.hour}:${hoyParque.minute}`;
    const dia = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(hoyParque.weekday);
    const horarioDe = (fecha, d) => (schedule.special_days ?? []).find((x) => x.date === fecha) ?? (schedule.weekly ?? []).find((x) => x.weekday === d);
    const hoyH = horarioDe(fechaHoy, dia);
    // La línea se lee por SÍ MISMA (la de la cabecera), no por la marca.
    const lineaCabecera = sinEspacios(await texto(page, '.pj-vh .pj-oh__hoy'));
    ok('la línea de la cabecera NO lleva la marca que callaría a la isla (`[data-hoy-linea] > p`, Z6a)', ! lineaCabecera || await page.evaluate(() => document.querySelector('[data-hoy-linea] > p') !== document.querySelector('.pj-vh .pj-oh__hoy')));
    const abiertoHoy = hoyH && ! hoyH.closed && ahoraHm < hoyH.closes_at;
    const manana = new Date(`${fechaHoy}T12:00:00Z`); manana.setUTCDate(manana.getUTCDate() + 1);
    const mananaH = horarioDe(manana.toISOString().slice(0, 10), (dia + 1) % 7);
    ok('[Hoy] de la cabecera: las horas de hoy (o la de mañana si ya cerró), de `/schedule`', abiertoHoy
        ? lineaCabecera.includes(hoyH.opens_at) && lineaCabecera.includes(hoyH.closes_at)
        : (! mananaH || mananaH.closed ? lineaCabecera === '' : lineaCabecera.includes(mananaH.opens_at)), lineaCabecera || '(sin línea)');
    const islaLlegada = await texto(page, '[data-jw-isla]');
    ok('al llegar, la isla DICE [Hoy] también (Z6a: sin él se quedaría sin frase)', ! lineaCabecera || /\d{1,2}:\d{2}/.test(islaLlegada), islaLlegada.slice(0, 80));
    await ir(page, '#traer');
    await page.waitForTimeout(900);
    const islaAbajo = await texto(page, '[data-jw-isla]');
    ok('al bajar, la isla dice [Hoy]', ! lineaCabecera || /\d{1,2}:\d{2}/.test(islaAbajo), islaAbajo.slice(0, 80));

    // ── 3 · El horario y las fechas especiales ────────────────────────────────────────────────────────────────────
    const franjas = await page.$$eval('#horario .pj-oh__fila .pj-oh__horas', (ds) => ds.map((d) => d.innerText.replace(/\s+/g, '')));
    const semanales = new Set((schedule.weekly ?? []).filter((d) => ! d.closed).map((d) => `${d.opens_at}–${d.closes_at}`));
    const especiales = (schedule.special_days ?? []);
    const festivos = [...new Set(especiales.filter((d) => ! d.closed).map((d) => `${d.opens_at}–${d.closes_at}`))];
    const esperadas = especiales.filter((d) => d.date >= fechaHoy && ! (! d.closed && festivos.length === 1 && festivos[0] === `${d.opens_at}–${d.closes_at}`)).sort((a, b) => a.date.localeCompare(b.date)).slice(0, 3);
    const deLaSemana = franjas.slice(0, semanales.size);
    ok('las franjas de la semana son las de `/schedule`', deLaSemana.length === semanales.size && deLaSemana.every((f) => semanales.has(f)), deLaSemana.join(' · '));
    const fechas = await page.$$eval('#horario .pj-oh__fechas .pj-oh__fila', (fs) => fs.length);
    ok(`las fechas especiales: las próximas cerradas o con otro horario (${esperadas.length})`, fechas === esperadas.length, `${fechas} en la página · ${esperadas.map((d) => d.date).join(', ') || 'ninguna'}`);

    // ── 4 · Qué traer y quién necesita un adulto ──────────────────────────────────────────────────────────────────
    const kidsHtml = await page.evaluate(async () => (await fetch('/kids')).text());
    const calcetin = JSON.parse((kidsHtml.match(/data-jw-calculadora="([^"]*)"/)?.[1] ?? 'null').replace(/&quot;/g, '"').replace(/&amp;/g, '&'))?.calcetin;
    const traer = sinEspacios(await texto(page, '#traer'));
    ok('tres tarjetas de norma', await page.locator('#traer .pj-rgc').count() === 3);
    const precioCalcetin = calcetin ? `${(calcetin.price_cents / 100).toFixed(2).replace('.', ',').replace(/,00$/, '')} €` : null;
    ok('los calcetines, al precio de su ficha (el de la calculadora de Kids)', precioCalcetin ? traer.includes(`${precioCalcetin} el par`) : ! /€ el par/.test(traer), precioCalcetin ?? 'sin precio');
    const kids = zonas.data?.find((z) => z.slug === 'kids') ?? (zonas.find?.((z) => z.slug === 'kids'));
    const jump = zonas.data?.find((z) => z.slug === 'jump') ?? (zonas.find?.((z) => z.slug === 'jump'));
    const adulto = sinEspacios(await texto(page, '#adulto'));
    ok('las alturas de «quién necesita un adulto», las de `/catalog/zones`', adulto.includes(`más de ${altura(kids?.escort?.under_age_from_cm)}`) && adulto.includes(`menos de ${altura(jump?.escort?.below_cm)}`), `${altura(kids?.escort?.under_age_from_cm)} / ${altura(jump?.escort?.below_cm)}`);

    // ── 5 · La cafetería ──────────────────────────────────────────────────────────────────────────────────────────
    const img = await page.$eval('#cafeteria img', (i) => ({ src: i.getAttribute('src'), alt: i.getAttribute('alt') })).catch(() => null);
    const venue = bar.bar?.venue ?? null;
    ok('la foto de la cafetería y su `alt`, los de `/bar`', venue ? img?.src === venue.url && img?.alt === venue.alt : img === null, img ? `${img.src?.split('/').pop()} · ${img.alt}` : 'sin foto');
    const tituloCafe = sinEspacios(await texto(page, '#cafeteria h2'));
    ok('«sin entrada» solo si el panel lo dice (`free_entry`)', /sin entrada/i.test(tituloCafe) === (bar.bar?.free_entry === true), tituloCafe);
    await ir(page, '#cafeteria');
    await page.waitForTimeout(500);
    await foto(page, '1-cafeteria');

    // ── 6 · Las dudas ──────────────────────────────────────────────────────────────────────────────────────────────
    const entrada = catalogo.find((p) => p.type === 'entry' && p.zone?.slug === 'kids' && p.cancellation?.written);
    const pack = catalogo.filter((p) => p.type === 'pack' && p.zone?.slug === 'cumpleanos' && p.from_price_cents != null).sort((a, b) => a.from_price_cents - b.from_price_cents)[0];
    const dudas = sinEspacios(await page.evaluate(() => [...document.querySelectorAll('#dudas .pj-acc__respuesta')].map((r) => r.textContent).join(' ')));
    ok('el plazo de las entradas y el de los cumpleaños, los de sus productos', dudas.includes(sinEspacios(entrada?.cancellation?.written)) && dudas.includes(sinEspacios(pack?.cancellation?.written)), `${entrada?.cancellation?.written} · ${pack?.cancellation?.written}`);
    const excursiones = await Promise.all(catalogo.filter((p) => p.type === 'pack' && p.zone?.slug === 'excursiones').map((p) => api(page, `catalog/products/${p.id}?lang=es`)));
    const minimo = Math.min(...excursiones.map((f) => (f.data ?? f).min_quantity));
    const maximo = Math.max(...excursiones.map((f) => (f.data ?? f).max_quantity));
    ok('los grupos de colegio, de las fichas de las excursiones', ! excursiones.length || dudas.includes(`de ${minimo} a ${maximo} alumnos`), `${minimo}–${maximo}`);
    await ir(page, '#dudas');
    await page.waitForTimeout(400);
    const aqui = page.locator('#dudas a[data-isla-planes]');
    const conMarca = await aqui.count() === 1;
    ok('«aquí» lleva la marca que abre el selector de planes', conMarca);
    if (conMarca) {
        await page.locator('#dudas .pj-acc__boton', { hasText: '¿Puedo comprar la entrada para hoy?' }).click();
        await page.waitForTimeout(500);
        await aqui.click();
        await page.waitForTimeout(2500);
        const tras = sinEspacios(await page.evaluate(() => document.querySelector('[data-jw-isla]')?.innerText ?? ''));
        ok('y lo ABRE: el selector de planes de la isla', /Un cumpleaños|Entrada Kids|Entrada Jump/.test(tras), tras.slice(0, 120));
        await foto(page, '2-selector');
        await page.keyboard.press('Escape');
        await page.waitForTimeout(600);
    }

    // ── 7 · El cierre, el pie y la isla ───────────────────────────────────────────────────────────────────────────
    const digitos = String(site.contact?.phone ?? '').replace(/\D/g, '');
    const cierre = await page.$$eval('#contacto a', (as) => as.map((a) => a.getAttribute('href')));
    ok('el teléfono y WhatsApp, del número de `/site`', cierre.includes(`tel:+${digitos}`) && cierre.includes(`https://wa.me/${digitos}`), cierre.slice(0, 2).join(' · '));
    ok('el correo y las redes, los de `/site`', cierre.includes(`mailto:${site.contact?.email}`) && Object.entries(site.social ?? {}).filter(([k]) => ['instagram', 'tiktok'].includes(k)).every(([, u]) => cierre.includes(u)), cierre.slice(2).join(' · '));
    const actualPie = await page.$$eval('footer a, .pj-pie a', (as) => as.filter((a) => a.innerText.trim() === 'Visítanos').map((a) => a.getAttribute('href')));
    ok('el pie marca Visítanos como la página actual', actualPie.includes('#'), actualPie.join(' '));
    // La configuración de la isla que da la página (`<x-pagina isla>`): su menú, con la actual marcada.
    const isla = await page.evaluate(() => JSON.parse(document.getElementById('jw-isla-pagina')?.textContent ?? 'null')?.config ?? null);
    const visitanosMenu = (isla?.menuItems ?? []).find((m) => /visitanos/.test(m.href));
    ok('el menú de la isla, con Visítanos activa (y la página, de apoyo)', visitanosMenu?.active === true && isla?.page?.kind === 'apoyo', JSON.stringify(visitanosMenu ?? {}));
    await ir(page, '#contacto');
    await page.waitForTimeout(500);
    await foto(page, '3-cierre');
    await ctx.close();

    // ── 8 · En inglés y en francés ────────────────────────────────────────────────────────────────────────────────
    for (const lang of ['en', 'fr']) {
        const c = await contexto();
        await c.page.goto(`${BASE}/lang/${lang}`, { waitUntil: 'domcontentloaded' });
        await c.page.goto(`${BASE}/visitanos`, { waitUntil: 'networkidle' });
        const main = await c.page.evaluate(() => { const m = document.querySelector('main').cloneNode(true); m.querySelectorAll('script').forEach((s) => s.remove()); return m.textContent.replace(/\s+/g, ' '); });
        const sueltos = [...new Set(main.match(/:[a-z_]{3,}\b|\b(?:paginas|piezas)\.[a-z_.]+/g) ?? [])];
        ok(`en ${lang}: la página entera, sin marcadores ni claves sueltas`, main.length > 2000 && sueltos.length === 0 && await c.page.locator('html').getAttribute('lang') === lang, sueltos.join(' ') || `${main.length} caracteres`);
        await c.ctx.close();
    }
} catch (e) {
    ok('la sonda terminó sin excepciones', false, e.message.split('\n')[0]);
} finally {
    await navegador.close();
}

ok('sin errores en la consola de la página', errores.length === 0, errores.slice(0, 3).join(' | '));
console.log(`\nSONDA DE VISÍTANOS · ${ANCHO}px\n${filas.join('\n')}`);
const fallos = filas.filter((f) => f.startsWith('✗')).length;
console.log(`\n${filas.length - fallos}/${filas.length}`);
process.exit(fallos ? 1 : 0);
