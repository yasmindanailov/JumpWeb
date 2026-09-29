/**
 * **NORMAS Y SEGURIDAD, EN VIVO** (T6e de `docs/specs/isla-y-landing-nueva.md` §4.21; `DECISIONES #842`): la página de
 * APOYO que convierte, declarada por el paquete de la instancia (`seguridad`, que OCUPA `/normas`), en un navegador. Cada
 * dato que pinta se compara con SU hecho de la API, no con una cifra escrita aquí:
 *   1. LA PÁGINA: `/normas` la pinta la instancia (sus ocho piezas y el pie, no el tablero del producto), una `h1`, sin
 *      salirse de ancho y sin precio en la cabecera; la nota de Google, la de `/social-proof` (cifra, reseñas y enlace).
 *   2. LA ISLA (situación 15, `kind: 'apoyo'`): su línea, las horas de hoy de `/schedule` (o las de mañana si ya cerró);
 *      CEDE su «Reservar» mientras se ve el de la cabecera o el del cierre, y lo dice entre medias.
 *   3. LAS NORMAS, las del PANEL (`GET /rules`, `#842`): por sus momentos y en su orden, las sin momento a lo ancho; cada
 *      tarjeta con su nombre, su descripción y su PORQUÉ, su NIVEL (la clase y la palabra del diseño, `RuleCard.jsx`) y su
 *      ICONO (el dibujo de `resources/icons/lucide/`, el mismo fichero que `Lucide::svg`). El reparto en dos columnas es el
 *      `rgSplit` del diseño (`RuleGrid.jsx`), y la maqueta —una columna o dos, el chip encima o al lado— la de su fórmula
 *      con el ancho medido.
 *   4. QUIÉN SALTA: las edades, las de las entradas de cada zona (`/catalog/products`); las alturas, las de
 *      `/catalog/zones`; la puerta a Visítanos.
 *   5. EL DESCARGO: la hoja lleva el título, la versión y las secciones VIGENTES de `/legal/waiver`; el enlace la ABRE, Esc
 *      y «Cerrar» la cierran; sin JavaScript, el enlace lleva a `/waiver`, que responde.
 *   6. LAS DUDAS: la primera abierta; los calcetines, al precio de su ficha (el de la calculadora de Kids); la altura de
 *      Jump, la de `/catalog/zones`.
 *   7. LOS «RESERVAR»: el de la cabecera, el de la isla y el del cierre abren el SELECTOR de planes, sin saltar al ancla; el
 *      teléfono y WhatsApp del cierre, del número de `/site`.
 *   8. LA NAVEGACIÓN: el pie marca Normas como la actual; el menú de la isla no marca otra.
 *   9. EN Y FR: las normas del panel en ese idioma (`/rules?lang=`), sin marcadores sin rellenar ni claves sueltas.
 * Solo lectura. Sale con 1 si algo falla; las fotos, en `storage/app/audit/normas-<ancho>-*.png`.
 * ⚠️ Las URL absolutas de los hechos llevan el puerto de fuera (`APP_URL`, 8081): dentro del contenedor se piden al 80.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-normas.mjs [390|1280]
 */
/* global console, document, window, location, fetch, getComputedStyle -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { Buffer } from 'node:buffer';
import { URL } from 'node:url';
import { chromium } from 'playwright-core';
import { mkdir, readFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const SALIDA = 'storage/app/audit';
const POLITICA = '2026-09-24'; // CookieConsent::POLICY_VERSION
/** La palabra de cada nivel, la del diseño (`RuleCard.jsx`); sin nivel, «Seguridad» y el escudo (`#842`). */
const PALABRA = { must: 'Obligatorio', safety: 'Seguridad', forbidden: 'Prohibido', info: 'Bueno saber' };
const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);
const errores = [];
/** «90 cm» o «1,30 m», como escribe la página una altura. */
const altura = (cm) => (cm >= 100 ? `${Math.floor(cm / 100)},${String(cm % 100).padStart(2, '0')}` : String(cm)).replace(/,00$/, '') + (cm >= 100 ? ' m' : ' cm');
/** Los espacios, uno (`\s` ya cubre el duro y el estrecho). */
const sinEspacios = (s) => String(s ?? '').replace(/\s+/g, ' ').trim();
/** El reparto del diseño (`rgSplit` de `RuleGrid.jsx`, copiado de allí): el corte más parejo en número de normas. */
function rgSplit(grupos) {
    const n = grupos.map((g) => g.rules.length);
    const total = n.reduce((a, b) => a + b, 0);
    let best = 1, diff = Infinity, acc = 0;
    for (let k = 1; k < grupos.length; k += 1) {
        acc += n[k - 1];
        const d = Math.abs(acc - (total - acc)) + (acc < total - acc ? 0.5 : 0);
        if (d < diff) { diff = d; best = k; }
    }

    return [grupos.slice(0, best), grupos.slice(best)];
}
/** La maqueta del diseño para un ancho (`RuleGrid.jsx`): con grupos, dos pistas y 32 px entre ellas, sobre gris. */
function maquetaDelDiseno(w) {
    const padX = w >= 1020 ? 40 : w >= 708 ? 24 : 12;
    const pista = (w - 2 * padX - 32) / 2;

    return pista >= 450 ? 'row' : pista >= 330 ? 'stack' : 'one';
}

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
const foto = (page, nombre) => page.screenshot({ path: `${SALIDA}/normas-${ANCHO}-${nombre}.png` });
const ir = (page, sel) => page.evaluate((s) => document.querySelector(s)?.scrollIntoView({ block: 'start', behavior: 'instant' }), sel);
const cargar = async (page) => { await page.goto('about:blank'); await page.goto(`${BASE}/normas`, { waitUntil: 'networkidle' }); await page.waitForTimeout(1500); await page.mouse.move(1, 1); };
const botonIsla = (page) => page.locator('[data-isla]').getByRole('button', { name: 'Reservar', exact: true });
const hastaSelector = (page) => page.locator('[data-isla]').getByText('¿Qué quieres reservar?').waitFor({ timeout: 10000 }).then(() => true, () => false);

try {
    const { ctx, page } = await contexto();
    await cargar(page);
    const [site, schedule, zonas, productos, reglas, descargo, social] = await Promise.all([
        api(page, 'site?lang=es'), api(page, 'schedule?lang=es'), api(page, 'catalog/zones?lang=es'), api(page, 'catalog/products?lang=es'),
        api(page, 'rules?lang=es'), api(page, 'legal/waiver'), api(page, 'social-proof'),
    ]);
    const catalogo = productos.data ?? productos;
    const zona = (slug) => (zonas.data ?? zonas).find((z) => z.slug === slug);
    /** La entrada de referencia de una zona, la del modelo de la página: la de una hora, o la primera. */
    const entradaDe = (slug) => { const es = catalogo.filter((p) => p.type === 'entry' && p.zone?.slug === slug); return es.find((p) => p.duration_min === 60) ?? es[0]; };
    const [kids, jump, kidsEntrada, jumpEntrada] = [zona('kids'), zona('jump'), entradaDe('kids'), entradaDe('jump')];

    // ── 1 · La página y su cabecera ──────────────────────────────────────────────────────────────────────────────
    const faltan = await page.evaluate(() => ['.pj-vh', '#cuidados', '#normas', '#quien', '#descargo', '#si-pasa', '#dudas', '#cierre', '.pj-pie'].filter((s) => ! document.querySelector(s)));
    ok('`/normas` la pinta la instancia: sus ocho piezas y el pie', faltan.length === 0, faltan.join(', '));
    ok('una sola h1', await page.locator('h1').count() === 1);
    ok('no se sale de ancho', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
    ok('la cabecera, sin precio (el único es el de los calcetines, de una norma)', ! /€/.test(await texto(page, '.pj-vh')));
    const nota = await page.evaluate(() => { const r = document.querySelector('.pj-vh .pj-rs'); return r ? { cifra: r.querySelector('.pj-rs__nota')?.textContent.trim(), enlace: r.querySelector('a')?.getAttribute('href'), cuenta: r.querySelector('a')?.textContent.replace(/\s+/g, ' ').trim() } : null; });
    const rating = social.rating ?? null;
    ok('la nota de Google de la cabecera: la cifra, las reseñas y el enlace de `/social-proof`', rating
        ? nota?.cifra === rating.value.toLocaleString('es-ES', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) && nota.enlace === rating.url && nota.cuenta?.includes(String(rating.count))
        : nota === null, JSON.stringify(nota));
    await foto(page, '0-llegada');

    // ── 2 · La isla: [Hoy] de `/schedule`, y cede su «Reservar» al de la página ──────────────────────────────────────
    const hoyParque = await page.evaluate((tz) => new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23', weekday: 'short' }).formatToParts(new Date()).reduce((o, p) => ({ ...o, [p.type]: p.value }), {}), schedule.timezone ?? 'Europe/Madrid');
    const fechaHoy = `${hoyParque.year}-${hoyParque.month}-${hoyParque.day}`;
    const ahoraHm = `${hoyParque.hour}:${hoyParque.minute}`;
    const dia = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(hoyParque.weekday);
    const horarioDe = (fecha, d) => (schedule.special_days ?? []).find((x) => x.date === fecha) ?? (schedule.weekly ?? []).find((x) => x.weekday === d);
    const hoyH = horarioDe(fechaHoy, dia);
    const manana = new Date(`${fechaHoy}T12:00:00Z`); manana.setUTCDate(manana.getUTCDate() + 1);
    const mananaH = horarioDe(manana.toISOString().slice(0, 10), (dia + 1) % 7);
    const abiertoHoy = hoyH && ! hoyH.closed && ahoraHm < hoyH.closes_at;
    const esperadas = abiertoHoy ? [hoyH.opens_at, hoyH.closes_at] : (mananaH && ! mananaH.closed ? [mananaH.opens_at] : []);
    const islaLlegada = sinEspacios(await texto(page, '[data-isla]'));
    ok('la línea de la isla: las horas de hoy (o la de mañana si ya cerró), de `/schedule`', esperadas.length ? esperadas.every((h) => islaLlegada.includes(h)) : ! /\d{1,2}:\d{2}/.test(islaLlegada), `${esperadas.join('–') || 'sin horario'} · ${islaLlegada.slice(0, 80)}`);
    ok('al llegar, la isla CEDE su «Reservar» (se ve el de la cabecera)', ! await botonIsla(page).isVisible().catch(() => false));
    await ir(page, '#normas');
    await page.waitForTimeout(900);
    ok('entre medias, la isla dice «Reservar»', await botonIsla(page).isVisible().catch(() => false), sinEspacios(await texto(page, '[data-isla]')).slice(0, 80));
    await foto(page, '1-normas');

    // ── 3 · Las normas del panel ───────────────────────────────────────────────────────────────────────────────────
    const delPanel = reglas.rules ?? [];
    const esperados = (reglas.moments ?? []).map((m) => ({ id: `normas-${m}`, rules: delPanel.filter((r) => r.moment === m) })).filter((g) => g.rules.length);
    const sinMomento = delPanel.filter((r) => ! r.moment);
    const pintados = await page.$$eval('#normas .pj-rg__grupo', (gs) => gs.map((g) => ({
        id: g.id,
        tarjetas: [...g.querySelectorAll('.pj-rgc')].map((c) => ({
            titulo: c.querySelector('.pj-rgc__titulo')?.textContent.trim() ?? '',
            texto: (c.querySelector('.pj-rgc__texto')?.textContent ?? '').replace(/\s+/g, ' ').trim(),
            nivel: [...c.classList].find((k) => /^pj-rgc--/.test(k))?.slice(8) ?? '',
            palabra: c.querySelector('.pj-rgc__nivel')?.textContent.trim() ?? '',
            svg: c.querySelector('.pj-rgc__chip svg')?.innerHTML ?? '',
        })),
    })));
    const idsEsperados = [...esperados.map((g) => g.id), ...(sinMomento.length ? ['normas-otras'] : [])];
    ok(`las normas, por los momentos del panel y en su orden (${idsEsperados.length} grupos)`, JSON.stringify(pintados.map((g) => g.id)) === JSON.stringify(idsEsperados), pintados.map((g) => `${g.id}:${g.tarjetas.length}`).join(' · '));
    const enOrden = [...esperados.flatMap((g) => g.rules), ...sinMomento];
    const tarjetas = pintados.flatMap((g) => g.tarjetas);
    ok(`las ${enOrden.length} normas de \`/rules\`, cada una en su grupo y en su orden`, JSON.stringify(tarjetas.map((t) => t.titulo)) === JSON.stringify(enOrden.map((r) => sinEspacios(r.name))), `${tarjetas.length} en la página`);
    const sinPorque = enOrden.filter((r, i) => tarjetas[i]?.texto !== sinEspacios(`${r.description ?? ''} ${r.reason ?? ''}`));
    ok('cada una con su descripción y su PORQUÉ detrás', enOrden.length > 0 && sinPorque.length === 0, sinPorque.map((r) => r.name).join(' · '));
    const malNivel = enOrden.filter((r, i) => tarjetas[i]?.nivel !== (r.level ?? 'safety') || tarjetas[i]?.palabra !== PALABRA[r.level ?? 'safety']);
    ok('cada una con su NIVEL del panel (la clase y la palabra; sin nivel, «Seguridad»)', enOrden.length > 0 && malNivel.length === 0, malNivel.map((r) => `${r.name}: ${r.level}`).join(' · '));
    // El dibujo, el MISMO fichero que sirve `Lucide::svg`, pasado por el mismo intérprete de HTML que la página.
    const iconos = [...new Set(enOrden.map((r) => r.icon ?? 'shield-check'))];
    const ficheros = Object.fromEntries(await Promise.all(iconos.map(async (i) => [i, await readFile(`resources/icons/lucide/icons/${i}.svg`, 'utf8').catch(() => '')])));
    const dibujos = await page.evaluate((fs) => Object.fromEntries(Object.entries(fs).map(([i, svg]) => { const t = document.createElement('template'); t.innerHTML = svg; return [i, t.content.querySelector('svg')?.innerHTML ?? '']; })), ficheros);
    const malIcono = enOrden.filter((r, i) => ! dibujos[r.icon ?? 'shield-check'] || tarjetas[i]?.svg !== dibujos[r.icon ?? 'shield-check']);
    ok('cada una con su ICONO del panel (el dibujo de Lucide; sin icono, el escudo)', enOrden.length > 0 && malIcono.length === 0, malIcono.map((r) => `${r.name}: ${r.icon}`).join(' · '));
    const reparto = await page.evaluate(() => {
        const s = document.getElementById('normas');
        const cols = [...s.querySelectorAll('.pj-rg__columna')].map((c) => ({ ids: [...c.querySelectorAll(':scope > .pj-rg__grupo')].map((g) => g.id), caja: c.getBoundingClientRect().toJSON() }));
        const tarjeta = s.querySelector('.pj-rg__columna .pj-rgc');

        return { w: s.getBoundingClientRect().width, cols, ancho: [...s.querySelectorAll('.pj-rg__ancho > .pj-rg__grupo')].map((g) => g.id), chip: tarjeta ? getComputedStyle(tarjeta).flexDirection : null };
    });
    const [izq, der] = esperados.length >= 2 ? rgSplit(esperados) : [esperados, []];
    ok('el reparto en dos columnas, el `rgSplit` del diseño; las sin momento, a lo ancho', JSON.stringify(reparto.cols.map((c) => c.ids)) === JSON.stringify([izq.map((g) => g.id), der.map((g) => g.id)].filter((c) => c.length))
        && JSON.stringify(reparto.ancho) === JSON.stringify(sinMomento.length ? ['normas-otras'] : []), reparto.cols.map((c) => c.ids.join('+')).join(' | '));
    const modo = esperados.length >= 2 ? maquetaDelDiseno(reparto.w) : 'one';
    const [a, b] = reparto.cols.map((c) => c.caja);
    const lado = Boolean(a && b) && b.left >= a.right - 0.5 && Math.abs(b.top - a.top) <= 0.5;
    ok(`la maqueta de la fórmula del diseño a ${Math.round(reparto.w)} px: ${modo === 'one' ? 'una columna' : `dos columnas, el chip ${modo === 'row' ? 'al lado' : 'encima'}`}`,
        modo === 'one' ? ! lado && reparto.chip === 'row' : lado && reparto.chip === (modo === 'row' ? 'row' : 'column'), `lado a lado ${lado} · chip ${reparto.chip}`);

    // ── 4 · Quién salta, y con quién ──────────────────────────────────────────────────────────────────────────────
    const quien = sinEspacios(await texto(page, '#quien'));
    const cuidados = sinEspacios(await texto(page, '#cuidados'));
    const kidsFrase = `Kids, de ${kidsEntrada?.guest_age_min} a ${kidsEntrada?.guest_age_max} años y hasta ${altura(kids?.height?.up_to_cm)}`;
    ok('las edades, las de las entradas de cada zona; las alturas, las de `/catalog/zones` (quién salta)', quien.includes(kidsFrase) && quien.includes(`más de ${altura(kids?.escort?.under_age_from_cm)}`)
        && quien.includes(`Jump, desde ${jumpEntrada?.guest_age_min} años`) && quien.includes(`menos de ${altura(jump?.escort?.below_cm)}`), `${kidsFrase} · ${altura(kids?.escort?.under_age_from_cm)} · ${jumpEntrada?.guest_age_min} · ${altura(jump?.escort?.below_cm)}`);
    ok('y las mismas en «Tres cosas que hacemos siempre»', cuidados.includes(kidsFrase) && cuidados.includes(`Jump, desde ${jumpEntrada?.guest_age_min} años y ${altura(jump?.escort?.below_cm)}`));
    const aVisitanos = await page.$eval('#quien a.pj-se-enlace', (l) => l.getAttribute('href')).catch(() => null);
    ok('la puerta a Visítanos', aVisitanos !== null && new URL(aVisitanos, BASE).pathname === '/visitanos', aVisitanos);

    // ── 5 · El descargo y su hoja ─────────────────────────────────────────────────────────────────────────────────
    const doc = descargo.document ?? null;
    const hoja = await page.evaluate(() => {
        const d = document.querySelector('#descargo dialog.pj-ws');
        const abrir = document.querySelector('#descargo .pj-ws__abrir');

        return { href: abrir?.getAttribute('href') ?? null, abre: abrir?.dataset.pjHojaAbrir ?? null, id: d?.id ?? null, titulo: d?.querySelector('.pj-ws__titulo')?.textContent.trim() ?? null, version: (d?.querySelector('.pj-ws__version')?.textContent ?? '').replace(/\s+/g, ' ').trim(), trozos: d ? [...d.querySelectorAll('.pj-ws__h, .pj-ws__p')].map((e) => e.textContent.replace(/\s+/g, ' ').trim()) : [] };
    });
    const [anio, mes, diaPub] = (doc?.published_at ?? '').slice(0, 10).split('-').map(Number);
    const fechaPub = doc?.published_at ? `${diaPub} de ${new Intl.DateTimeFormat('es-ES', { month: 'long', timeZone: 'UTC' }).format(Date.UTC(anio, mes - 1, diaPub))} de ${anio}` : '';
    const trozos = (doc?.sections ?? []).flatMap((s) => [s.h, s.p].filter((x) => (x ?? '') !== '').map(sinEspacios));
    // La versión, como número entero y FUERA de la fecha: el «1» de «1 de septiembre» no puede pasar por la versión 1.
    const suVersion = new RegExp(`(^|\\D)${doc?.version}(\\D|$)`).test(hoja.version.replace(fechaPub, ''));
    ok('la hoja lleva el descargo VIGENTE de `/legal/waiver`: su título, su versión y su fecha, y sus secciones', doc
        ? hoja.titulo === doc.title && suVersion && hoja.version.includes(fechaPub) && JSON.stringify(hoja.trozos) === JSON.stringify(trozos)
        : hoja.id === null, `${hoja.titulo} · ${hoja.version} · ${hoja.trozos.length}/${trozos.length} trozos`);
    const salida = hoja.href ? await page.evaluate(async (h) => (await fetch(h)).status, new URL(hoja.href, BASE).pathname) : null;
    ok('sin JavaScript, el enlace lleva a la página del descargo, que responde', hoja.href !== null && new URL(hoja.href, BASE).pathname === '/waiver' && salida === 200, `${hoja.href} · ${salida}`);
    if (doc && hoja.id === null) {
        ok('el enlace ABRE la hoja sin salir de la página, y Esc la cierra', false, 'hay descargo publicado y la hoja no está montada');
    } else if (doc) {
        const abierta = () => page.evaluate(() => document.querySelector('#descargo dialog.pj-ws')?.open === true);
        await ir(page, '#descargo');
        await page.waitForTimeout(400);
        await page.locator('#descargo [data-pj-hoja-abrir]').click();
        await page.waitForTimeout(700);
        const tras = await abierta();
        const enLaPagina = await page.evaluate(() => location.pathname);
        await foto(page, '2-hoja');
        await page.keyboard.press('Escape');
        await page.waitForTimeout(500);
        ok('el enlace ABRE la hoja sin salir de la página, y Esc la cierra', tras && enLaPagina === '/normas' && ! await abierta(), `abierta ${tras} · ${enLaPagina}`);
        await page.locator('#descargo [data-pj-hoja-abrir]').click();
        await page.waitForTimeout(700);
        await page.locator('#descargo dialog.pj-ws .pj-ws__pie [data-pj-hoja-cerrar]').click();
        await page.waitForTimeout(500);
        ok('y «Cerrar» también', ! await abierta());
    }

    // ── 6 · Las dudas ──────────────────────────────────────────────────────────────────────────────────────────────
    const primera = await page.$eval('#dudas [aria-expanded]', (b) => b.getAttribute('aria-expanded')).catch(() => null);
    ok('la primera duda, abierta', primera === 'true', primera);
    const kidsHtml = await page.evaluate(async () => (await fetch('/kids')).text());
    const calcetin = JSON.parse((kidsHtml.match(/data-jw-calculadora="([^"]*)"/)?.[1] ?? 'null').replace(/&quot;/g, '"').replace(/&amp;/g, '&'))?.calcetin;
    const precio = calcetin ? `${(calcetin.price_cents / 100).toFixed(2).replace('.', ',').replace(/,00$/, '')} €` : null;
    const dudas = sinEspacios(await texto(page, '#dudas'));
    const respuestas = sinEspacios(await page.evaluate(() => [...document.querySelectorAll('#dudas .pj-acc__respuesta')].map((r) => r.textContent).join(' ')));
    ok('los calcetines, al precio de su ficha (el de la calculadora de Kids), en la pista y en la respuesta', precio ? dudas.includes(`${precio} el par`) && respuestas.includes(`por ${precio}`) : ! /€ el par/.test(dudas), precio ?? 'sin precio');
    ok('la altura de Jump en las dudas, la de `/catalog/zones`', respuestas.includes(`menos de ${altura(jump?.escort?.below_cm)}`) && dudas.includes(`${jumpEntrada?.guest_age_min} años`), altura(jump?.escort?.below_cm));

    // ── 7 · El cierre ──────────────────────────────────────────────────────────────────────────────────────────────
    const digitos = String(site.contact?.phone ?? '').replace(/\D/g, '');
    const cierre = await page.$$eval('#cierre a', (as) => as.map((a) => a.getAttribute('href')));
    ok('el teléfono y WhatsApp del cierre, del número de `/site`', cierre.includes(`tel:+${digitos}`) && cierre.includes(`https://wa.me/${digitos}`), cierre.join(' · '));
    await ir(page, '#cierre');
    await page.waitForTimeout(900);
    ok('ante el «Reservar» del cierre, la isla vuelve a ceder el suyo', ! await botonIsla(page).isVisible().catch(() => false));
    await foto(page, '3-cierre');

    // ── 8 · La navegación ──────────────────────────────────────────────────────────────────────────────────────────
    const pie = await page.$$eval('.pj-pie a', (as) => as.map((a) => ({ href: a.getAttribute('href'), texto: a.innerText.trim() })));
    const actual = pie.filter((l) => l.href === '#');
    ok('el pie marca Normas como la página actual (y ningún enlace a `/normas`)', actual.length === 1 && /normas/i.test(actual[0].texto) && ! pie.some((l) => l.href && /\/normas$/.test(new URL(l.href, BASE).pathname)), actual.map((l) => l.texto).join(' '));
    const isla = await page.evaluate(() => JSON.parse(document.getElementById('jw-isla-pagina')?.textContent ?? 'null')?.config ?? null);
    const activas = (isla?.menuItems ?? []).filter((m) => m.active);
    ok('el menú de la isla no marca otra página como actual (y la página, de apoyo)', isla !== null && activas.length === 0 && isla.page?.kind === 'apoyo', activas.map((m) => m.label).join(' ') || `kind ${isla?.page?.kind}`);
    await ctx.close();

    // ── Los «Reservar»: cabecera, isla y cierre abren el selector de planes, sin saltar al ancla ─────────────────────
    // El de la página se busca por SER el botón primario de su pieza, no por sus marcas (`data-isla-planes` lo hace abrir el
    // selector y `data-isla-cta` hace ceder a la isla): sin la primera, el clic baja a la portada y la comprobación lo dice.
    const primario = (p, pieza) => p.locator(`${pieza} .pj-btn--primary`).first();
    for (const [donde, abrir] of [
        ['de la cabecera', (p) => primario(p, '.pj-vh').click()],
        ['de la isla', async (p) => { await ir(p, '#normas'); await p.waitForTimeout(900); await botonIsla(p).click(); }],
        ['del cierre', async (p) => { await primario(p, '#cierre').scrollIntoViewIfNeeded(); await primario(p, '#cierre').click(); }],
    ]) {
        const c = await contexto();
        await cargar(c.page);
        await abrir(c.page);
        const abierto = await hastaSelector(c.page);
        const hash = await c.page.evaluate(() => location.hash);
        const ruta = await c.page.evaluate(() => location.pathname);
        ok(`«Reservar» ${donde} abre el selector de planes, sin saltar al ancla`, abierto && hash === '' && ruta === '/normas', `${ruta}${hash}`);
        if (donde === 'de la cabecera') await foto(c.page, '4-selector');
        await c.ctx.close();
    }

    // ── 9 · En inglés y en francés ────────────────────────────────────────────────────────────────────────────────
    for (const lang of ['en', 'fr']) {
        const c = await contexto();
        await c.page.goto(`${BASE}/lang/${lang}`, { waitUntil: 'domcontentloaded' });
        await c.page.goto(`${BASE}/normas`, { waitUntil: 'networkidle' });
        const suyas = (await api(c.page, `rules?lang=${lang}`)).rules ?? [];
        const titulos = await c.page.$$eval('#normas .pj-rgc__titulo', (ts) => ts.map((t) => t.textContent.trim()));
        const main = await c.page.evaluate(() => { const m = document.querySelector('main').cloneNode(true); m.querySelectorAll('script').forEach((s) => s.remove()); return `${document.title} ${m.textContent}`.replace(/\s+/g, ' '); });
        const sueltos = [...new Set(main.match(/:[a-z_]{3,}\b|\b(?:paginas|piezas)\.[a-z_.]+/g) ?? [])];
        ok(`en ${lang}: la página entera, sin marcadores ni claves sueltas`, main.length > 2000 && sueltos.length === 0 && await c.page.locator('html').getAttribute('lang') === lang, sueltos.join(' ') || `${main.length} caracteres`);
        ok(`en ${lang}: las normas, las del panel en ese idioma (\`/rules?lang=${lang}\`)`, suyas.length > 0 && JSON.stringify([...titulos].sort()) === JSON.stringify(suyas.map((r) => sinEspacios(r.name)).sort()), `${titulos.length}/${suyas.length}`);
        await c.ctx.close();
    }
} catch (e) {
    ok('la sonda terminó sin excepciones', false, e.message.split('\n')[0]);
} finally {
    await navegador.close();
}

ok('sin errores en la consola de la página', errores.length === 0, errores.slice(0, 3).join(' | '));
console.log(`\nSONDA DE NORMAS · ${ANCHO}px\n${filas.join('\n')}`);
const fallos = filas.filter((f) => f.startsWith('✗')).length;
console.log(`\n${filas.length - fallos}/${filas.length}`);
process.exit(fallos ? 1 : 0);
