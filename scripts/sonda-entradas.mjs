/**
 * **KIDS Y JUMP, EN VIVO** (T4f de `docs/specs/isla-y-landing-nueva.md` §4.12): las dos páginas del molde de entradas del
 * paquete de la instancia, en un navegador. Cada dato que pintan se compara con SU hecho de la API, no con una cifra escrita
 * aquí (el molde de `sonda-visitanos.mjs`):
 *   1. LA PÁGINA: una `h1`, sin salirse de ancho; el título para Google con la ciudad y las edades, la descripción con el
 *      «desde» y la foto para compartir, la de la zona.
 *   2. LA CABECERA: «1 hora, por niño» y sus tarifas (`/prices`), «Hoy» en la de hoy (`/schedule`), la nota de Google
 *      (`/social-proof`), el botón («Reservar para hoy» solo si hoy abre y quedan huecos, `/availability`), el plazo de las
 *      garantías (`/catalog/products`) y las ofertas (`/promotions`).
 *   3. EL PRECIO: una fila por entrada, en el orden del catálogo, con sus precios; el ahorro de la de 2 horas, CALCULADO; la
 *      unidad, las etiquetas y los días de la tarifa especial, los del panel; la calculadora con sus filas y los calcetines
 *      de su ficha; y las dudas del precio, con sus edades y alturas (`/catalog/zones`).
 *   4. LA ZONA: las atracciones de `/attractions`, en su orden, y el «play» solo en las que tienen vídeo.
 *   5. TRANQUILIDAD: los cuidados con sus edades y alturas, y las voces: las tres primeras de `/reviews` con su etiqueta.
 *   6. DÓNDE Y CUÁNDO: el titular, la dirección, «Cómo llegar», las franjas y [Hoy] de `/schedule` y `/site`, y el botón.
 *   7. LAS DUDAS: edades, alturas, calcetines, plazo, el cumpleaños de su pack y los días especiales; y sus puertas.
 *   8. EL CIERRE Y EL PIE: el teléfono y WhatsApp de `/site`, el plazo, sin la promesa de Bizum; el pie con la zona actual.
 *   9. LA ISLA: página de producto de su zona, con su acción, su «desde» y el menú con la zona activa.
 *  10. EN Y FR: la página entera, sin marcadores sin rellenar ni claves sueltas.
 * Solo lectura. Sale con 1 si algo falla; las fotos, en `storage/app/audit/entradas-<zona>-<ancho>-*.png`.
 * ⚠️ Los hechos se piden UNA vez para las dos páginas: el suelo de la API sin sesión es de 60 por minuto y por IP.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-entradas.mjs [390|1280] [kids,jump]
 */
/* global console, document, window, fetch -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { Buffer } from 'node:buffer';
import { URL } from 'node:url';
import { chromium } from 'playwright-core';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 390);
const ZONAS = (process.argv[3] ?? 'kids,jump').split(',');
const SALIDA = 'storage/app/audit';
const POLITICA = '2026-09-24'; // CookieConsent::POLICY_VERSION
const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);
const errores = [];
/** El dinero como lo escribe la página en español: «6,40 €», «8 €». */
const euros = (c) => `${(c / 100).toFixed(2).replace('.', ',').replace(/,00$/, '')} €`;
/** «90 cm» o «1,30 m», como escribe la página una altura. */
const altura = (cm) => (cm >= 100 ? `${Math.floor(cm / 100)},${String(cm % 100).padStart(2, '0')} m` : `${cm} cm`);
/** «1 hora», «2 horas» o «Ilimitada», como rotula la página una entrada. */
const duracion = (min) => (min == null ? 'Ilimitada' : `${min / 60} ${min === 60 ? 'hora' : 'horas'}`);
const minuscula = (s) => String(s ?? '').charAt(0).toLowerCase() + String(s ?? '').slice(1);
/** Los espacios, uno (`\s` ya cubre el duro y el estrecho). */
const sinEspacios = (s) => String(s ?? '').replace(/\s+/g, ' ').trim();
/** La firma de una opinión, como la escribe `components/resena.php`: «Marta R.». */
const firma = (autor) => {
    const [a = '', b] = String(autor).trim().split(/\s+/);
    const nombre = a.charAt(0).toUpperCase() + a.slice(1);

    return b ? `${nombre} ${b.charAt(0).toUpperCase()}.` : nombre;
};

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
const api = (page, ruta, cuerpo = null) => page.evaluate(async ([r, c]) => {
    const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
    const opciones = c === null ? { headers: { Accept: 'application/json' } }
        : { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf }, body: JSON.stringify(c) };

    return (await fetch(`/api/v1/${r}`, opciones)).json();
}, [ruta, cuerpo]);
const texto = (page, sel) => page.evaluate((s) => [...document.querySelectorAll(s)].map((e) => e.textContent).join(' ').replace(/\s+/g, ' ').trim(), sel);
const textos = (page, sel) => page.$$eval(sel, (es) => es.map((e) => e.textContent.replace(/\s+/g, ' ').trim()));
const ir = (page, sel) => page.evaluate((s) => document.querySelector(s)?.scrollIntoView({ block: 'start', behavior: 'instant' }), sel);

let hechos = null;

try {
    for (const zona of ZONAS) {
        const { ctx, page } = await contexto();
        const foto = (nombre) => page.screenshot({ path: `${SALIDA}/entradas-${zona}-${ANCHO}-${nombre}.png` });
        await page.goto(`${BASE}/${zona}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(1200);
        await page.mouse.move(1, 1);
        const Z = zona.toUpperCase();

        // Los hechos, una vez para las dos páginas (el suelo de la API).
        if (hechos === null) {
            const [site, schedule, zonas, precios, productos, prueba, atracciones, resenas, promociones] = await Promise.all([
                api(page, 'site?lang=es'), api(page, 'schedule?lang=es'), api(page, 'catalog/zones?lang=es'), api(page, 'prices?lang=es'),
                api(page, 'catalog/products?lang=es'), api(page, 'social-proof?lang=es'), api(page, 'attractions?lang=es'), api(page, 'reviews?lang=es'),
                api(page, 'promotions?lang=es'),
            ]);
            const catalogo = productos.data ?? productos;
            const entradas = catalogo.filter((p) => p.type === 'entry');
            const fichas = Object.fromEntries(await Promise.all(entradas.map(async (p) => { const f = await api(page, `catalog/products/${p.id}?lang=es`); return [p.id, f.data ?? f]; })));
            hechos = { site, schedule, zonas: zonas.data ?? zonas, precios, catalogo, prueba, atracciones, resenas, promociones, fichas, huecos: {} };
        }
        const { site, schedule, precios, catalogo, prueba, atracciones, resenas, promociones, fichas } = hechos;
        const zonaDe = (slug) => hechos.zonas.find((z) => z.slug === slug) ?? {};
        const entradasDe = (slug) => catalogo.filter((p) => p.type === 'entry' && p.zone?.slug === slug);
        const baseDe = (slug) => entradasDe(slug).find((p) => p.duration_min === 60) ?? entradasDe(slug)[0] ?? {};
        const esta = zonaDe(zona);
        const productos = entradasDe(zona);
        const base = baseDe(zona);
        const kids = { zona: zonaDe('kids'), base: baseDe('kids') };
        const jump = { zona: zonaDe('jump'), base: baseDe('jump') };
        const normal = precios.rates.find((r) => ! r.special);
        const especial = precios.rates.find((r) => r.special);
        const centimos = (p, tarifa) => (tarifa ? precios.products.find((x) => x.id === p.id)?.prices.find((x) => x.rate === tarifa.key)?.cents ?? null : null);
        const unidad = precios.products.find((x) => x.id === base.id)?.unit ?? '';
        const desde = Math.min(...productos.map((p) => p.from_price_cents).filter((c) => c != null));
        const plazo = base.cancellation?.written;
        // Los calcetines: el complemento que llevan TODAS las entradas de la zona (su ficha).
        const comunes = productos.map((p) => (fichas[p.id]?.addons ?? []).map((a) => a.id)).reduce((acc, ids) => acc.filter((id) => ids.includes(id)));
        const calcetin = comunes.length === 1 ? fichas[productos[0].id].addons.find((a) => a.id === comunes[0]) : null;
        // El pack de cumpleaños de la zona: el de la zona de cumpleaños con la edad mínima de sus entradas.
        const pack = catalogo.find((p) => p.type === 'pack' && p.zone?.slug === 'cumpleanos' && p.guest_age_min === base.guest_age_min);

        // Hoy, en el calendario del parque: su horario y su tarifa.
        const partes = await page.evaluate((tz) => new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23', weekday: 'short' }).formatToParts(new Date()).reduce((o, p) => ({ ...o, [p.type]: p.value }), {}), schedule.timezone ?? 'Europe/Madrid');
        const fechaHoy = `${partes.year}-${partes.month}-${partes.day}`;
        const ahoraHm = `${partes.hour}:${partes.minute}`;
        const dia = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(partes.weekday);
        const especialHoy = (schedule.special_days ?? []).find((d) => d.date === fechaHoy);
        const horarioHoy = especialHoy ?? (schedule.weekly ?? []).find((d) => d.weekday === dia);
        const abreHoy = Boolean(horarioHoy) && ! horarioHoy.closed;
        const tarifaHoyEspecial = especialHoy ? especialHoy.rate_label === especial?.label : (especial?.weekdays ?? []).includes(dia);
        // Quedan huecos hoy: alguna hora vendible y con plazas en alguna entrada de la zona (`/availability`, el mismo hecho).
        if (hechos.huecos[zona] === undefined) {
            const horas = abreHoy ? await Promise.all(productos.map((p) => api(page, `availability/${p.id}/times`, { date: fechaHoy }))) : [];
            hechos.huecos[zona] = horas.some((h) => (h.data ?? []).some((x) => x.sellable && Number(x.available ?? 0) > 0));
        }
        const huecos = abreHoy && hechos.huecos[zona];
        const accion = huecos ? 'Reservar para hoy' : `Reservar ${zona === 'kids' ? 'Kids' : 'Jump'}`;

        // ── 1 · La página ────────────────────────────────────────────────────────────────────────────────────────────
        ok(`${Z} · una sola h1`, await page.locator('h1').count() === 1);
        ok(`${Z} · no se sale de ancho`, await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));
        const titulo = await page.title();
        const edades = zona === 'kids' ? `${base.guest_age_min} a ${base.guest_age_max} años` : `desde ${base.guest_age_min} años`;
        ok(`${Z} · el título para Google: la ciudad (\`/site\`) y las edades (\`/catalog\`)`, titulo.includes(site.address?.city) && sinEspacios(titulo).includes(edades), titulo);
        const descripcion = sinEspacios(await page.getAttribute('meta[name="description"]', 'content'));
        ok(`${Z} · la descripción: el «desde» de sus entradas`, descripcion.includes(`desde ${euros(desde)}`) || descripcion.includes(`Desde ${euros(desde)}`), descripcion.slice(-90));
        const og = await page.getAttribute('meta[property="og:image"]', 'content');
        ok(`${Z} · la foto para compartir, la de la zona (\`/catalog/zones\`)`, og === esta.image_url, og);
        await foto('0-llegada');

        // ── 2 · La cabecera ──────────────────────────────────────────────────────────────────────────────────────────
        const caption = sinEspacios(await texto(page, '.pj-vh .pj-dr__titulo'));
        ok(`${Z} · el precio de la cabecera: «${duracion(base.duration_min)}, ${unidad}» (\`/prices\`)`, caption === `${duracion(base.duration_min)}, ${unidad}`, caption);
        const tarifas = await page.$$eval('.pj-vh .pj-dr__fila', (fs) => fs.map((f) => ({ cifra: f.querySelector('.pj-dr__cifra')?.textContent.replace(/\s+/g, ' ').trim(), dias: f.querySelector('.pj-dr__celda--dias')?.firstChild?.textContent.replace(/\s+/g, ' ').trim(), hoy: Boolean(f.querySelector('.pj-dr__hoy')) })));
        const esperadas = [normal, especial].filter((t) => t && centimos(base, t) !== null).map((t) => ({ cifra: euros(centimos(base, t)), dias: minuscula(t.label) }));
        ok(`${Z} · sus tarifas, con sus precios y sus días (\`/prices\`)`, JSON.stringify(tarifas.map(({ cifra, dias: d }) => ({ cifra, dias: d }))) === JSON.stringify(esperadas), tarifas.map((t) => `${t.cifra} ${t.dias}`).join(' · '));
        const filaHoy = abreHoy ? (tarifaHoyEspecial && especial ? 1 : 0) : -1;
        ok(`${Z} · «Hoy» en la tarifa de hoy (\`/schedule\` y sus días especiales)`, tarifas.findIndex((t) => t.hoy) === filaHoy, `fila ${tarifas.findIndex((t) => t.hoy)}, esperada ${filaHoy}`);
        const rating = prueba.rating ?? null;
        const nota = rating ? String(rating.value.toFixed(1)).replace('.', ',') : null;
        const cabeceraRs = await page.$$eval('.pj-vh .pj-rs', (rs) => rs.map((r) => ({ nota: r.querySelector('.pj-rs__nota')?.textContent.trim(), texto: r.textContent.replace(/\s+/g, ' '), href: r.querySelector('a')?.getAttribute('href') })));
        ok(`${Z} · la nota de Google: su cifra, sus reseñas, su enlace y su fuente (\`/social-proof\`)`, rating
            ? cabeceraRs.length > 0 && cabeceraRs.every((r) => r.nota === nota && r.texto.includes(`${rating.count} reseñas`) && r.href === rating.url && /en Google/.test(r.texto) === (rating.source === 'google'))
            : cabeceraRs.length === 0, JSON.stringify(cabeceraRs[0] ?? {}).slice(0, 160));
        const cta = sinEspacios(await texto(page, '.pj-vh [data-isla-cta]'));
        ok(`${Z} · el botón: «Reservar para hoy» solo si hoy abre y quedan huecos (\`/availability\`)`, cta === accion, `${cta} · huecos hoy: ${huecos}`);
        // La franja pinta cada garantía SIN su punto final cuando la frase entera es el titular (`reassurance-band`).
        const garantias = await textos(page, '.pj-rb > .pj-rb__item .pj-rb__titular');
        ok(`${Z} · las garantías: el plazo de sus entradas (\`/catalog/products\`)`, plazo ? garantias.includes(`Cambias o cancelas ${sinEspacios(plazo)}`) : ! garantias.some((g) => /Cambias o cancelas/.test(g)), garantias.join(' · '));
        const ofertas = (promociones.promotions ?? []).filter((p) => p.kind === 'offer' && p.ends_on && (p.target?.type === 'installation' || (p.target?.type === 'zone' && p.target?.zone === zona)));
        ok(`${Z} · las ofertas de la cabecera: las de \`/promotions\` de la instalación o de la zona (${ofertas.length})`, await page.locator('.pj-vh .pj-oferta').count() === ofertas.length);

        // ── 3 · El precio ────────────────────────────────────────────────────────────────────────────────────────────
        const rotulos = await textos(page, '#precio .pj-rt__rotulo');
        ok(`${Z} · la tabla: una fila por entrada de la zona, en el orden del catálogo`, JSON.stringify(rotulos) === JSON.stringify(productos.map((p) => duracion(p.duration_min))), rotulos.join(' · '));
        const celdas = await page.$$eval('#precio .pj-rt__fila', (fs) => fs.map((f) => [...f.querySelectorAll('td')].map((td) => td.textContent.replace(/\s+/g, ' ').trim())));
        const celdasEsperadas = productos.map((p) => [normal, especial].map((t) => (centimos(p, t) === null ? `Solo ${minuscula(normal.label)}` : euros(centimos(p, t)))));
        ok(`${Z} · cada precio, el de \`/prices\` (o «Solo ${minuscula(normal.label)}» sin tarifa especial)`, JSON.stringify(celdas) === JSON.stringify(celdasEsperadas), celdas.map((c) => c.join('/')).join(' · '));
        const dos = productos.find((p) => p.duration_min === 120);
        if (dos) {
            const ahorros = [...new Set([normal, especial].map((t) => (centimos(base, t) === null || centimos(dos, t) === null ? null : 2 * centimos(base, t) - centimos(dos, t))).filter((a) => a !== null && a > 0))];
            const esperado = ahorros.length === 1 ? euros(ahorros[0]) : `${euros(ahorros[0]).replace(' €', '')} o ${euros(ahorros[1])}`;
            const notaDos = sinEspacios(await texto(page, `#precio .pj-rt__fila:nth-child(${productos.indexOf(dos) + 1}) .pj-rt__nota`));
            ok(`${Z} · el ahorro de la de 2 horas, CALCULADO de sus precios`, notaDos === `${esperado} menos que dos de ${duracion(base.duration_min)}`, notaDos);
        }
        const cabezas = await textos(page, '#precio .pj-rt__cabeza');
        ok(`${Z} · la unidad y la columna normal, las del panel`, cabezas[0] === `${unidad.charAt(0).toUpperCase()}${unidad.slice(1)}, IVA incluido` && cabezas[1]?.startsWith(normal.label), cabezas.slice(0, 2).join(' · '));
        const precio = sinEspacios(await texto(page, '#precio'));
        ok(`${Z} · la nota de la tarifa especial: sus días, los del panel`, precio.includes(`Tarifa especial: ${minuscula(especial.label)}.`));
        const calculadora = await page.evaluate(() => JSON.parse(document.querySelector('[data-jw-calculadora]')?.dataset.jwCalculadora ?? 'null'));
        ok(`${Z} · la calculadora recibe sus filas (\`/catalog\`) y los calcetines de su ficha`, JSON.stringify(calculadora?.filas?.map((f) => f.id)) === JSON.stringify(productos.map((p) => p.id))
            && (calcetin ? calculadora?.calcetin?.id === calcetin.id && calculadora?.calcetin?.price_cents === calcetin.price_cents : calculadora?.calcetin == null), JSON.stringify(calculadora?.calcetin ?? null));
        const cortos = { 1: 'L', 2: 'M', 3: 'X', 4: 'J', 5: 'V', 6: 'S', 0: 'D' };
        const orden = [1, 2, 3, 4, 5, 6, 0];
        const pos = (especial.weekdays ?? []).map((d) => orden.indexOf(d)).sort((a, b) => a - b);
        const corto = pos.length > 1 && pos.at(-1) - pos[0] === pos.length - 1 ? `${cortos[orden[pos[0]]]}–${cortos[orden[pos.at(-1)]]}` : pos.map((p) => cortos[orden[p]]).join(', ');
        const detalles = sinEspacios(await texto(page, '#precio .pj-acc'));
        const edadesPrecio = zona === 'kids'
            ? detalles.includes(`Menores de ${base.guest_age_min} años`) && detalles.includes(`Más de ${altura(esta.escort?.under_age_from_cm)}`)
            : detalles.includes(`Desde ${base.guest_age_min} años`) && detalles.includes(`menos de ${altura(esta.escort?.below_cm)}`) && detalles.includes(`Los de ${kids.base.guest_age_min} a ${kids.base.guest_age_max} saltan en Kids`);
        ok(`${Z} · las dudas del precio: edades y alturas (\`/catalog\`) y los días especiales en corto (${corto})`, edadesPrecio && detalles.includes(`${corto} y festivos`) && detalles.includes(`${especial.label}.`), detalles.slice(0, 160));

        // ── 4 · La zona: las atracciones ─────────────────────────────────────────────────────────────────────────────
        const suyas = (atracciones.attractions ?? []).filter((a) => a.zone === zona && (a.image_url || a.video_url));
        const vistas = await page.$$eval('#zona .pj-ct, #zona .pj-cl__boton', (es) => es.map((e) => ({ nombre: e.querySelector('.pj-ct__nombre, .pj-cl__nombre')?.textContent.trim(), play: Boolean(e.querySelector('.pj-ct__play, .pj-cl__play')) })));
        ok(`${Z} · las atracciones: las de \`/attractions\` de la zona, en su orden (${suyas.length})`, JSON.stringify(vistas.map((v) => v.nombre)) === JSON.stringify(suyas.map((a) => a.name)), vistas.map((v) => v.nombre).join(', ').slice(0, 160));
        ok(`${Z} · el «play» solo en las que tienen vídeo`, vistas.length === suyas.length && vistas.every((v, i) => v.play === Boolean(suyas[i].video_url)), vistas.filter((v) => v.play).map((v) => v.nombre).join(', ') || 'ninguna con vídeo');

        // ── 5 · Tranquilidad ─────────────────────────────────────────────────────────────────────────────────────────
        const tranquilidad = sinEspacios(await texto(page, '#tranquilidad'));
        const cuidados = zona === 'kids'
            ? tranquilidad.includes(`de ${base.guest_age_min} a ${base.guest_age_max} años, hasta ${altura(esta.height?.up_to_cm)}`) && tranquilidad.includes(`más de ${altura(esta.escort?.under_age_from_cm)}`)
            : tranquilidad.includes(`Los de ${kids.base.guest_age_min} a ${kids.base.guest_age_max} años saltan en Kids`) && tranquilidad.includes(`Desde ${base.guest_age_min} años`) && tranquilidad.includes(`menos de ${altura(esta.escort?.below_cm)}`);
        ok(`${Z} · los cuidados: sus edades y alturas, de \`/catalog\``, cuidados, tranquilidad.slice(0, 160));
        const voces = await textos(page, '#tranquilidad .pj-rc__nombre');
        const vocesEsperadas = (resenas.reviews ?? []).filter((r) => (r.tags ?? []).includes(zona)).slice(0, 3).map((r) => firma(r.author));
        ok(`${Z} · las voces: las tres primeras de \`/reviews\` con la etiqueta de la zona`, JSON.stringify(voces) === JSON.stringify(vocesEsperadas), voces.join(' · '));
        ok(`${Z} · «Y tú, a gusto» solo en Kids`, tranquilidad.includes('Y tú, a gusto') === (zona === 'kids'));
        await ir(page, '#tranquilidad');
        await page.waitForTimeout(500);
        await foto('1-tranquilidad');

        // ── 6 · Dónde y cuándo ───────────────────────────────────────────────────────────────────────────────────────
        const semana = (schedule.weekly ?? []).filter((d) => ! d.closed);
        const sabado = semana.find((d) => d.weekday === 6);
        const cierres = [...new Set(semana.map((d) => d.closes_at))];
        const titular = sinEspacios(await texto(page, '#donde .pj-sh__titulo'));
        const horaCorta = (hm) => (hm.endsWith(':00') ? String(Number(hm.slice(0, 2))) : hm);
        ok(`${Z} · el titular, de \`/schedule\``, zona === 'kids' ? titular.endsWith(`desde las ${horaCorta(sabado.opens_at)}`) : cierres.length === 1 && titular.includes(`hasta las ${cierres[0]}`), titular);
        const direccion = sinEspacios(await texto(page, '#donde .pj-pl__direccion'));
        ok(`${Z} · la dirección, la de \`/site\``, direccion === `${site.address.line1}, ${site.address.city}.`, direccion);
        const comoLlegar = await page.$$eval('#donde a[target="_blank"]', (as) => as.map((a) => a.getAttribute('href')));
        ok(`${Z} · «Cómo llegar», la ruta de \`/site\``, comoLlegar.includes(site.address.maps_url), comoLlegar.join(' '));
        const franjas = await page.$$eval('#donde .pj-oh__fila .pj-oh__horas', (ds) => ds.map((d) => d.textContent.replace(/\s+/g, '')));
        const franjasEsperadas = [...new Set(orden.map((d) => semana.find((x) => x.weekday === d)).filter(Boolean).map((d) => `${d.opens_at}–${d.closes_at}`))];
        ok(`${Z} · el horario: sus franjas, las de la semana de \`/schedule\``, JSON.stringify(franjas) === JSON.stringify(franjasEsperadas), franjas.join(' · '));
        const lineaHoy = sinEspacios(await texto(page, '#donde .pj-oh__hoy'));
        const lineaEsperada = ! abreHoy ? '' : ahoraHm < horarioHoy.closes_at ? `Hoy abrimos de ${horarioHoy.opens_at} a ${horarioHoy.closes_at}` : 'Hoy ya hemos cerrado';
        ok(`${Z} · [Hoy] del horario: las horas de hoy (o que ya cerró)`, lineaEsperada === '' ? lineaHoy === '' : lineaHoy.startsWith(lineaEsperada), lineaHoy || '(sin línea)');
        const desdeTexto = huecos ? `Hoy, ${duracion(base.duration_min).toLowerCase()} cuesta ${euros(centimos(base, tarifaHoyEspecial ? especial : normal))}` : `Desde ${euros(desde)}`;
        const cajaDonde = sinEspacios(await texto(page, '#donde .pj-donde__caja .pj-bcta'));
        ok(`${Z} · el botón de «Dónde y cuándo» y su precio («${desdeTexto}»)`, cajaDonde.includes(accion) && cajaDonde.includes(desdeTexto), cajaDonde);

        // ── 7 · Las dudas ────────────────────────────────────────────────────────────────────────────────────────────
        const dudas = sinEspacios(await texto(page, '#dudas'));
        const edadesDudas = zona === 'kids'
            ? dudas.includes(`tiene ${base.guest_age_min - 1} años`) && dudas.includes(`más de ${altura(esta.escort?.under_age_from_cm)}`) && dudas.includes(`¿Y si tiene ${jump.base.guest_age_min} años?`) && dudas.includes(`menos de ${altura(jump.zona.escort?.below_cm)}`)
            : dudas.includes(`desde ${base.guest_age_min} años`) && dudas.includes(`menos de ${altura(esta.escort?.below_cm)}`) && dudas.includes(`Los de ${kids.base.guest_age_min} a ${kids.base.guest_age_max} saltan en Kids`);
        ok(`${Z} · las dudas: sus edades y alturas, de \`/catalog\``, edadesDudas, dudas.slice(0, 160));
        ok(`${Z} · los calcetines, al precio de su ficha (${calcetin ? euros(calcetin.price_cents) : 'sin precio'})`, calcetin ? dudas.includes(`${euros(calcetin.price_cents)} el par`) : ! /€ el par/.test(dudas));
        const plazoCorto = base.cancellation?.cutoff_hours == null ? null : base.cancellation.cutoff_hours < 48 ? `${base.cancellation.cutoff_hours} h antes` : `${base.cancellation.cutoff_hours / 24} días antes`;
        ok(`${Z} · el plazo, el de sus entradas (y en corto en su pista: ${plazoCorto})`, dudas.includes(`Sí, ${sinEspacios(plazo)}.`) && dudas.includes(plazoCorto), plazo);
        const letra = { 1: 'una hora', 2: 'dos horas', 3: 'tres horas' };
        ok(`${Z} · el cumpleaños: el «desde» y la duración de su pack (\`/catalog\`)`, Boolean(pack) && dudas.includes(`desde ${euros(pack.from_price_cents)} por niño`) && dudas.includes(`${letra[pack.duration_min / 60]}, merienda`), pack ? `${pack.id}: ${euros(pack.from_price_cents)}, ${pack.duration_min} min` : 'sin pack');
        ok(`${Z} · los días especiales: la etiqueta del panel`, dudas.includes(`${especial.label} tienen tarifa especial`));
        const puertas = await page.$$eval('#dudas a', (as) => as.map((a) => new URL(a.href).pathname + new URL(a.href).hash));
        ok(`${Z} · sus puertas: ${zona === 'kids' ? 'a Jump, ' : ''}al cumpleaños y «aquí» al precio`, (zona !== 'kids' || puertas.includes('/jump')) && puertas.includes('/cumpleanos') && puertas.some((p) => p.endsWith('#precio')), puertas.join(' '));

        // ── 8 · El cierre y el pie ───────────────────────────────────────────────────────────────────────────────────
        const digitos = String(site.contact?.phone ?? '').replace(/\D/g, '');
        const cierreEnlaces = await page.$$eval('#cierre a', (as) => as.map((a) => a.getAttribute('href')));
        const mensaje = `Hola, quiero reservar en la zona ${zona === 'kids' ? 'Kids' : 'Jump'}.`;
        ok(`${Z} · el cierre: el teléfono y WhatsApp (con su mensaje), del número de \`/site\``, cierreEnlaces.includes(`tel:+${digitos}`) && cierreEnlaces.includes(`https://wa.me/${digitos}?text=${encodeURIComponent(mensaje)}`), cierreEnlaces.filter((h) => /tel:|wa\.me/.test(h)).join(' · '));
        const cierre = sinEspacios(await texto(page, '#cierre'));
        ok(`${Z} · el cierre: su botón y el plazo de sus entradas`, cierre.includes(accion) && cierre.includes(`Cambias o cancelas ${sinEspacios(plazo)}.`), cierre.slice(0, 160));
        ok(`${Z} · el cierre, SIN la promesa de Bizum (apagada hasta que llegue)`, ! cierre.includes('pagas por Bizum'));
        const pie = await page.$$eval('.pj-pie a', (as) => as.map((a) => ({ t: a.textContent.replace(/\s+/g, ' ').trim(), h: a.getAttribute('href') })));
        const pieTexto = sinEspacios(await texto(page, '.pj-pie'));
        ok(`${Z} · el pie: la zona como actual, y la dirección y el teléfono de \`/site\``, pie.some((a) => a.t === (zona === 'kids' ? 'Kids' : 'Jump') && a.h === '#') && pieTexto.includes(site.address.line1) && pie.some((a) => a.h === `tel:+${digitos}`), pie.filter((a) => a.h === '#').map((a) => a.t).join(' '));
        await ir(page, '#cierre');
        await page.waitForTimeout(500);
        await foto('2-cierre');

        // ── 9 · La isla ──────────────────────────────────────────────────────────────────────────────────────────────
        const isla = await page.evaluate(() => JSON.parse(document.getElementById('jw-isla-pagina')?.textContent ?? 'null')?.config ?? null);
        const activa = (isla?.menuItems ?? []).find((m) => m.active);
        ok(`${Z} · la isla: página de producto de la zona, su acción y su «desde»`, isla?.page?.kind === 'producto' && isla?.page?.product === zona && isla?.page?.action?.label === `Reservar ${zona === 'kids' ? 'Kids' : 'Jump'}` && sinEspacios(isla?.page?.from) === `Desde ${euros(desde)}`, JSON.stringify(isla?.page ?? {}).slice(0, 160));
        ok(`${Z} · el menú de la isla, con la zona activa, y el contacto de \`/site\``, Boolean(activa) && new URL(activa.href, BASE).pathname === `/${zona}` && isla?.contact?.whatsapp === digitos, JSON.stringify(activa ?? {}));
        await ctx.close();
    }

    // ── 10 · En inglés y en francés ──────────────────────────────────────────────────────────────────────────────────
    for (const zona of ZONAS) {
        for (const lang of ['en', 'fr']) {
            const c = await contexto();
            await c.page.goto(`${BASE}/lang/${lang}`, { waitUntil: 'domcontentloaded' });
            await c.page.goto(`${BASE}/${zona}`, { waitUntil: 'networkidle' });
            const main = await c.page.evaluate(() => { const m = document.querySelector('main').cloneNode(true); m.querySelectorAll('script').forEach((s) => s.remove()); return m.textContent.replace(/\s+/g, ' '); });
            const sueltos = [...new Set(main.match(/:[a-z_]{3,}\b|\b(?:paginas|piezas)\.[a-z_.]+/g) ?? [])];
            ok(`${zona.toUpperCase()} en ${lang}: la página entera, sin marcadores ni claves sueltas`, main.length > 4000 && sueltos.length === 0 && await c.page.locator('html').getAttribute('lang') === lang, sueltos.join(' ') || `${main.length} caracteres`);
            await c.ctx.close();
        }
    }
} catch (e) {
    ok('la sonda terminó sin excepciones', false, e.message.split('\n')[0]);
} finally {
    await navegador.close();
}

ok('sin errores en la consola de las páginas', errores.length === 0, errores.slice(0, 3).join(' | '));
console.log(`\nSONDA DE KIDS Y JUMP · ${ANCHO}px\n${filas.join('\n')}`);
const fallos = filas.filter((f) => f.startsWith('✗')).length;
console.log(`\n${filas.length - fallos}/${filas.length}`);
process.exit(fallos ? 1 : 0);
