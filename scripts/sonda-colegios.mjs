/**
 * **COLEGIOS, EN VIVO** (T6c de `docs/specs/isla-y-landing-nueva.md` §4.19; `DECISIONES #837`→`#841`): la página de las
 * excursiones que declara el paquete de la instancia, su calculadora, la compra de una excursión en la isla, el cálculo que
 * se retoma y la hoja para dirección, en un navegador. Funde las cuatro sondas de un solo uso de la T6c (la compra, la
 * calculadora, el cálculo retomado y la hoja). Cada cifra se compara con OTRA fuente: los tramos, con `/prices`; el total,
 * la señal y el resto, con la línea del SERVIDOR para esa misma selección (`PAY-12`); los datos del centro, con la ficha.
 *   1. LA PÁGINA: monta la calculadora con sus packs, una sola `h1`, la miniatura de la hoja y los días con hueco con su
 *      fecha; la nota es el regalo de la ficha; en el móvil no se sale de ancho.
 *   2. LA CALCULADORA, al llegar: el «desde» por persona es el del tramo de esa gente en `/prices`, cada duración con el
 *      suyo, y el tramo siguiente, si está cerca, a un toque («Calcular con…» pone su cifra). LA ESCALERA (T6c·6, el
 *      `RateTable` con `active` del diseño): la de la duración elegida, sola, con los precios de `/prices` y el tramo de
 *      esa gente en gris.
 *   3. LA CIFRA SE ESCRIBE: otra gente, su tramo, también en la escalera; lejos del siguiente, sin aviso. Otra duración:
 *      su escalera, con ella en la cabecera y sus precios.
 *   4. UN DÍA de «Próximos días con hueco», pulsado, trae sus horas; con la primera libre, el total, la señal y el resto son
 *      los del servidor, y la isla lo dice; la escalera marca la celda de la tarifa de ese día con el precio del servidor.
 *   5. «RESERVAR Y PAGAR LA SEÑAL» valida la línea (pack, gente, día, hora) y abre la compra de la isla, que baja al primer
 *      dato obligatorio del centro y lo enfoca; «Continuar» sin estar lista lleva al siguiente; con todo, a «Tus datos».
 *   6. CON LA CUENTA DE PRUEBAS, hasta «Pagar» (NUNCA paga): el precio por persona, la gente, el total y la señal «el día
 *      de la visita», sin «niños» ni «fiesta».
 *   7. EL CÁLCULO QUE SE RETOMA: del enlace (`?c=`), del dispositivo, y con su hora ya no libre (soltada y dicha).
 *   8. LA HOJA PARA DIRECCIÓN: con cálculo, las cifras del grupo son las del servidor, el QR, el A4 y el PDF en una página;
 *      sin él, las generales y `noindex`; la calculadora la descarga con su cálculo.
 * No deja pedidos. Sale con 1 si algo falla; las fotos, en `storage/app/audit/colegios-<ancho>-*.png`.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-colegios.mjs [390|1280]
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
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' }; // TESTING.md §2.octies
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }
for (const slug of ['colegios', 'colegios-propuesta']) {
    if (tinker(`echo app(App\\Http\\Instancia\\InstancePages::class)->una("${slug}")?->slug ?? "";`) === '') { console.error(`✗ el paquete no declara la página «${slug}»`); process.exit(1); }
}
tinker('foreach ([md5("api"."ip:127.0.0.1")] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }');

const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);
/** «975 €», «13,50 €», «1.040 €» → céntimos; sin cifra, `NaN`. */
const centimos = (texto) => (texto == null ? Number.NaN : Math.round(Number(String(texto).replace(/[^\d,]/g, '').replace(',', '.')) * 100));
/** El tramo de `n` personas en `/prices` (el de mayor «desde» que no pasa de `n`) y el siguiente. */
const tramoDe = (producto, n) => [...producto.tiers].sort((a, b) => b.from_quantity - a.from_quantity).find((t) => t.from_quantity <= n);
const tramoSiguiente = (producto, n) => [...producto.tiers].sort((a, b) => a.from_quantity - b.from_quantity).find((t) => t.from_quantity > n);
/** Sin día, el precio de un tramo es el más barato de sus tarifas. */
const masBarato = (tramo) => Math.min(...tramo.prices.map((p) => p.cents));
const escapar = (s) => String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const sinHora = (h) => String(h ?? '').slice(0, 5);

await mkdir(SALIDA, { recursive: true });
const navegador = await chromium.launch();
const errores = [];

async function contexto(ancho = ANCHO) {
    const ctx = await navegador.newContext({ viewport: { width: ancho, height: ancho < 600 ? 844 : 900 }, locale: 'es-ES', deviceScaleFactor: 1 });
    await ctx.addCookies([{ name: 'cookie_consent', value: Buffer.from(JSON.stringify({ v: POLITICA, cats: {} })).toString('base64'), url: BASE }]);
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errores.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error' && ! /status of 401/.test(m.text())) errores.push(m.text()); });

    return { ctx, page };
}
async function colegios(page, consulta = '') {
    await page.goto(`${BASE}/colegios${consulta}`, { waitUntil: 'networkidle' });
    await page.locator('[data-jw-calculadora] input[inputmode="numeric"]').waitFor({ timeout: 15000 });
    await page.waitForTimeout(1500);
}
const texto = (page, sel) => page.evaluate((s) => (document.querySelector(s)?.innerText ?? '').replace(/\s+/g, ' ').trim(), sel);
const foto = (page, nombre) => page.screenshot({ path: `${SALIDA}/colegios-${ANCHO}-${nombre}.png` });
const cifraDe = (page) => page.locator('[data-jw-calculadora] input[inputmode="numeric"]');
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
async function escribir(page, n) {
    const cifra = cifraDe(page);
    await cifra.click();
    await cifra.fill(String(n));
    await cifra.press('Enter');
    await page.waitForTimeout(1500);
}
/** La línea que el servidor da a esa selección: la misma petición que la calculadora. */
const lineaDelServidor = (page, q) => page.evaluate(async ({ id, cuerpo }) => {
    const r = await fetch(`/api/v1/catalog/products/${id}/addons`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(cuerpo) });

    return (await r.json()).line ?? null;
}, { id: q.id, cuerpo: { quantity: q.n, date: q.dia, time: `${sinHora(q.hora)}:00`, addons: [] } });
/** El total, lo de hoy y lo de después, tal y como los escribe el lado de la calculadora. */
async function recibo(page, textos) {
    const lado = await texto(page, '[data-jw-calculadora-lado]');

    return {
        lado,
        total: lado.match(/Total ([\d.,]+\s?€)/)?.[1],
        hoy: lado.match(new RegExp(`${escapar(textos.ahora)} ([\\d.,]+\\s?€)`))?.[1],
        resto: lado.match(new RegExp(`${escapar(textos.luego)} ([\\d.,]+\\s?€)`))?.[1],
    };
}
/**
 * La ESCALERA a la vista (T6c·6, el `RateTable` con `active` del diseño): cuáles se ven, su cabecera, sus precios por tramo y
 * tarifa, el tramo marcado (y si se ve en gris) y la celda marcada (su tarifa, `aria-current` y si lleva la caja).
 */
const escaleraVista = (page) => page.evaluate(() => {
    const todas = [...document.querySelectorAll('[data-jw-escalera]')];
    const visibles = todas.filter((x) => x.getBoundingClientRect().height > 0);
    const e = visibles[0];
    const activa = e?.querySelector('[data-jw-tramo][data-jw-activo]');
    const otra = e?.querySelector('[data-jw-tramo]:not([data-jw-activo])');
    const celda = e?.querySelector('[data-jw-tarifa][data-jw-activo]');

    return {
        visibles: visibles.map((x) => x.dataset.jwEscalera), total: todas.length, unidad: e?.querySelector('thead th')?.innerText.trim() ?? '',
        precios: e ? [...e.querySelectorAll('[data-jw-tramo]')].map((f) => [Number(f.dataset.jwTramo), [...f.querySelectorAll('[data-jw-tarifa]')].map((c) => [c.dataset.jwTarifa, c.innerText.trim()])]) : [],
        tramo: activa ? Number(activa.dataset.jwTramo) : null,
        gris: Boolean(activa && otra) && window.getComputedStyle(activa).backgroundColor !== window.getComputedStyle(otra).backgroundColor,
        tarifa: celda?.dataset.jwTarifa ?? null, actual: celda?.getAttribute('aria-current') ?? null, precioMarcado: celda?.innerText.trim() ?? null,
        caja: Boolean(celda?.firstElementChild) && window.getComputedStyle(celda.firstElementChild).boxShadow !== 'none',
    };
});

try {
    // ── 1 · La página ─────────────────────────────────────────────────────────────────────────────────────────────
    const a = await contexto();
    await colegios(a.page);
    const calc = await a.page.evaluate(() => JSON.parse(document.querySelector('[data-jw-calculadora]')?.dataset.jwCalculadora ?? 'null'));
    const packs = (calc?.filas ?? []).filter((f) => f.tipo === 'pack');
    ok('la página monta la calculadora con sus packs, por filas', packs.length > 0 && packs.length === calc.filas.length, packs.map((f) => `${f.id} ${f.label}`).join(', '));
    const { textos } = calc;
    const persona = textos.persona?.[0] ?? '';
    const precios = await a.page.evaluate(async () => (await (await fetch('/api/v1/prices?lang=es', { headers: { Accept: 'application/json' } })).json()).products);
    const fichas = await a.page.evaluate(async (ids) => Promise.all(ids.map(async (id) => (await fetch(`/api/v1/catalog/products/${id}`, { headers: { Accept: 'application/json' } })).json())), packs.map((f) => f.id));
    const precioDe = Object.fromEntries(packs.map((f) => [f.id, precios.find((p) => p.id === f.id)]));
    ok('cada pack tiene sus tramos en `/prices`', packs.every((f) => precioDe[f.id]?.tiers?.length > 0));
    ok('una sola h1', await a.page.locator('h1').count() === 1);
    const dias = await a.page.$$eval('[data-jw-calculadora-dia]', (as) => as.map((x) => x.dataset.jwCalculadoraDia));
    const hoy = new Date().toISOString().slice(0, 10);
    ok('los días con hueco llevan su fecha, desde hoy (6 como mucho)', dias.length > 0 && dias.length <= 6 && dias.every((d) => /^\d{4}-\d{2}-\d{2}$/.test(d) && d >= hoy), dias.join(', ') || 'ninguno');
    const cuerpo = await texto(a.page, 'main');
    const regalo = fichas[0]?.gifts?.[0];
    ok('la nota de la calculadora es el regalo de la ficha', ! regalo || cuerpo.includes(regalo), regalo ?? 'sin regalo');
    const mini = a.page.locator('.pj-c5__mini');
    await mini.scrollIntoViewIfNeeded();
    await a.page.waitForTimeout(700);
    const caja = await mini.boundingBox();
    ok('la miniatura de la hoja, en la pieza 5', caja && caja.width > 100 && caja.height > 150, `${Math.round(caja?.width)}×${Math.round(caja?.height)}`);
    ok('no se sale de ancho', await a.page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));

    // ── 2 · La calculadora, al llegar ─────────────────────────────────────────────────────────────────────────────
    const pieza = a.page.locator('#calcula');
    await pieza.scrollIntoViewIfNeeded();
    await a.page.waitForTimeout(600);
    const fila = packs[0];
    const n0 = Number(await cifraDe(a.page).inputValue());
    ok('nace con la gente que pide la página, en una cifra que se escribe', n0 === (textos.inicio?.personas ?? fila.min), String(n0));
    let t = await pieza.innerText();
    const DESDE = new RegExp(`desde ([\\d.,]+)\\s?€ por ${escapar(persona)}`);
    const desde = () => DESDE;
    ok(`el «desde» por ${persona} es el de su tramo en \`/prices\``, centimos(t.match(desde())?.[1]) === masBarato(tramoDe(precioDe[fila.id], n0)), `${t.match(desde())?.[0]} · /prices ${masBarato(tramoDe(precioDe[fila.id], n0))}`);
    const duraciones = packs.map((f) => ({ f, visto: centimos(t.match(new RegExp(`${escapar(f.label)}[\\s\\S]{0,40}?desde ([\\d.,]+)\\s?€`))?.[1]), api: masBarato(tramoDe(precioDe[f.id], n0)) }));
    ok('cada duración, con el «desde» de su tramo', duraciones.every((d) => d.visto === d.api), duraciones.map((d) => `${d.f.label} ${d.visto}/${d.api}`).join(' · '));
    // La escalera (T6c·6): la de esta duración, con SUS precios de `/prices`, y el tramo de esa gente marcado.
    const precioEnPrices = (id, desde, tarifa) => precioDe[id].tiers.find((x) => x.from_quantity === desde)?.prices.find((x) => x.rate === tarifa)?.cents;
    const preciosDe = (esc, id) => esc.precios.length > 0 && esc.precios.every(([desde, celdas]) => celdas.every(([tarifa, txt]) => centimos(txt) === precioEnPrices(id, desde, tarifa)));
    let esc = await escaleraVista(a.page);
    ok('la escalera de la duración elegida, sola (las demás, ocultas)', esc.visibles.join() === String(fila.id) && esc.total === packs.length, `${esc.visibles.join()} de ${esc.total}`);
    ok('sus precios son los de `/prices` de ese pack, tramo a tramo y tarifa a tarifa', preciosDe(esc, fila.id), JSON.stringify(esc.precios).slice(0, 120));
    ok('marca el tramo de esa gente, en gris; sin día, ninguna celda', esc.tramo === tramoDe(precioDe[fila.id], n0).from_quantity && esc.gris && esc.tarifa === null, `tramo ${esc.tramo} · gris ${esc.gris}`);
    const { lado: ladoAlLlegar, hoy: senalAlLlegar } = await recibo(a.page, textos);
    ok('antes de elegir, sin total y con la señal', Boolean(senalAlLlegar) && ! /Total \d/.test(ladoAlLlegar), ladoAlLlegar.slice(0, 120));
    const siguiente = tramoSiguiente(precioDe[fila.id], n0);
    const cerca = siguiente && siguiente.from_quantity - n0 <= 10;
    const avisoCerca = siguiente && textos.calculadora.tramo_cerca.replace(':n', siguiente.from_quantity);
    const antesDelPrecio = avisoCerca?.split(':precio')[0] ?? '';
    ok('el tramo siguiente se avisa SOLO si está a 10 o menos, con su precio', cerca ? centimos(t.match(new RegExp(`${escapar(antesDelPrecio)}([\\d.,]+)\\s?€`))?.[1]) === masBarato(siguiente) : ! t.includes(textos.calculadora.tramo_calcular.split(':n')[0]), cerca ? `${antesDelPrecio}… · /prices ${masBarato(siguiente)}` : 'lejos');
    await foto(a.page, '1-calculadora');
    const calcularCon = pieza.getByText(textos.calculadora.tramo_calcular.replace(':n', siguiente?.from_quantity));
    if (cerca && await calcularCon.count() === 1) {
        await calcularCon.click();
        await a.page.waitForTimeout(1500);
        t = await pieza.innerText();
        ok('«Calcular con…» pone la cifra de ese tramo y su precio, y la escalera lo marca', Number(await cifraDe(a.page).inputValue()) === siguiente.from_quantity && centimos(t.match(desde())?.[1]) === masBarato(siguiente) && (await escaleraVista(a.page)).tramo === siguiente.from_quantity);
    } else if (cerca) {
        ok('«Calcular con…» pone la cifra de ese tramo y su precio', false, 'no está');
    }

    // ── 3 · La cifra se escribe ───────────────────────────────────────────────────────────────────────────────────
    // Otra gente, en un tramo mayor y lejos del siguiente (75 con los de hoy): su precio, y sin aviso.
    const tramos = [...precioDe[fila.id].tiers].sort((x, y) => x.from_quantity - y.from_quantity);
    const n1 = tramos.map((tr) => Math.min(tr.from_quantity + 5, fila.max)).find((n) => n > n0 && (tramoSiguiente(precioDe[fila.id], n)?.from_quantity ?? Infinity) - n > 10) ?? fila.max;
    await escribir(a.page, n1);
    t = await pieza.innerText();
    ok(`se escribe ${n1}: el «desde» de su tramo`, Number(await cifraDe(a.page).inputValue()) === n1 && centimos(t.match(desde())?.[1]) === masBarato(tramoDe(precioDe[fila.id], n1)), t.match(desde())?.[0]);
    ok('lejos del tramo siguiente, sin aviso', ! t.includes(textos.calculadora.tramo_calcular.split(':n')[0]));
    ok('la escalera marca el tramo nuevo', (await escaleraVista(a.page)).tramo === tramoDe(precioDe[fila.id], n1).from_quantity);
    // Otra duración: SU escalera —su duración en la cabecera, sus precios— con el mismo tramo marcado; y se vuelve.
    if (packs.length > 1) {
        const otra = packs[1];
        await a.page.locator(`#calcula input[type="radio"][value="${otra.id}"]`).check({ force: true });
        await a.page.waitForTimeout(1500);
        esc = await escaleraVista(a.page);
        ok('otra duración: su escalera, con ella en la cabecera, sus precios y el tramo de esa gente', esc.visibles.join() === String(otra.id) && esc.unidad.includes(otra.label) && preciosDe(esc, otra.id) && esc.tramo === tramoDe(precioDe[otra.id], n1).from_quantity, `${esc.unidad} · tramo ${esc.tramo}`);
        await a.page.locator('[data-jw-escalera]:not([hidden])').first().scrollIntoViewIfNeeded();
        await a.page.waitForTimeout(400);
        await foto(a.page, '1b-escalera-otra-duracion');
        await a.page.locator(`#calcula input[type="radio"][value="${fila.id}"]`).check({ force: true });
        await a.page.waitForTimeout(1500);
    }

    // ── 4 · Un día con hueco y su primera hora libre: lo que se ve es lo del servidor ────────────────────────────
    const dia = dias[0];
    await a.page.click(`[data-jw-calculadora-dia="${dia}"]`);
    await a.page.waitForSelector('#p3-hora [data-hora]:not([disabled])', { timeout: 15000 });
    await pieza.scrollIntoViewIfNeeded();
    const elegido = await a.page.$$eval('#p3-dia button[aria-pressed="true"]', (bs) => bs.length);
    const horas = await a.page.$$eval('#p3-hora [data-hora]', (bs) => bs.map((b) => ({ hora: b.dataset.hora, libre: ! b.disabled })));
    ok(`el día ${dia}, pulsado en «Próximos días con hueco», queda elegido y trae sus horas`, elegido === 1 && horas.some((h) => h.libre), `${horas.filter((h) => h.libre).length} libres de ${horas.length}`);
    const hora = horas.find((h) => h.libre).hora;
    await a.page.click(`#p3-hora [data-hora="${hora}"]`);
    await a.page.waitForTimeout(2500);
    const linea = await lineaDelServidor(a.page, { id: fila.id, n: n1, dia, hora });
    const r = await recibo(a.page, textos);
    ok('el TOTAL que se ve es el del servidor para esa selección (`PAY-12`)', centimos(r.total) === linea?.total_cents, `${r.total} · servidor ${linea?.total_cents} · ${n1} el ${dia} a las ${sinHora(hora)}`);
    ok('la señal y el resto, también', centimos(r.hoy) === linea?.deposit_cents && centimos(r.resto) === linea?.gate_remainder_cents, `${r.hoy} / ${r.resto}`);
    ok('la señal de antes de elegir era la del servidor', centimos(senalAlLlegar) === linea?.deposit_cents, senalAlLlegar);
    // Z6b (opción C «Da la razón», zip (6)): con el botón de la calculadora a la vista, la isla no lo repite: dice la RAZÓN de
    // la pieza (la que la página declara para `calcula`), sin frase. Apartado el botón, dice lo elegido con su total.
    await a.page.locator('[data-jw-calculadora-lado] [data-isla-cta]').scrollIntoViewIfNeeded();
    await a.page.waitForTimeout(1600);
    const razonCalcula = await a.page.evaluate(() => JSON.parse(document.getElementById('jw-isla-pagina').textContent).config.razones?.calcula?.text ?? null);
    const conBoton = await islaAhora(a.page);
    ok('con el botón de la calculadora a la vista, la isla dice la razón de la pieza y no lo repite', conBoton.cta && conBoton.banner === razonCalcula && conBoton.frase === null, JSON.stringify(conBoton));
    const apartado = await sinBotonALaVista(a.page);
    const isla = (await islaAhora(a.page)).frase ?? '';
    ok('sin el botón a la vista, la isla de la página dice lo elegido con su total', apartado && isla.includes(`${n1} `) && Boolean(r.total) && isla.includes(r.total), isla.slice(0, 120));
    // Con día, la escalera marca la CELDA de su tarifa (la del día en la API), y su precio es el por persona del servidor.
    const rateDelDia = await a.page.evaluate(async (q) => (await (await fetch(`/api/v1/availability/${q.id}/dates`, { headers: { Accept: 'application/json' } })).json()).data.find((x) => x.date === q.dia)?.rate_key, { id: fila.id, dia });
    esc = await escaleraVista(a.page);
    ok('con día, la celda de su tarifa, marcada también para el lector, con el precio por persona del servidor', esc.tramo === tramoDe(precioDe[fila.id], n1).from_quantity && esc.tarifa === (rateDelDia === 'special' ? 'special' : 'normal') && esc.actual === 'true' && esc.caja && centimos(esc.precioMarcado) === linea?.unit_price_cents, `${esc.tarifa} (el día: ${rateDelDia}) · ${esc.precioMarcado} · caja ${esc.caja}`);
    await a.page.locator('[data-jw-escalera]:not([hidden])').first().scrollIntoViewIfNeeded();
    await a.page.waitForTimeout(400);
    await foto(a.page, '2b-escalera');
    await a.page.locator('[data-jw-calculadora-lado]').scrollIntoViewIfNeeded();
    await a.page.waitForTimeout(500);
    await foto(a.page, '2-total');

    // ── 5 · «Reservar y pagar la señal» → la compra de la isla, que va a lo que falta ─────────────────────────────
    const validadas = [];
    a.page.on('request', (q) => { if (q.url().includes('/cart/validate-line')) validadas.push(JSON.parse(q.postData() ?? '{}').line); });
    await a.page.locator('[data-jw-calculadora-lado] [data-isla-cta]').click();
    await a.page.waitForSelector('#pjc-q-datos', { timeout: 20000 });
    await a.page.waitForTimeout(2500);
    const dialogo = () => a.page.locator('[role="dialog"][data-isla-velo]').innerText();
    let c = (await dialogo()).replace(/\s+/g, ' ');
    ok('la compra se abre con esa gente y esa hora', c.includes(`${n1} personas`) && c.includes(sinHora(hora)), c.slice(0, 160));
    const obligatorios = (fichas[0].event_fields ?? []).filter((f) => f.required).map((f) => f.key);
    ok('pide los datos de la reserva de la ficha, con sus etiquetas', c.includes('Datos de la reserva') && (fichas[0].event_fields ?? []).every((f) => c.includes(f.label)), obligatorios.join(', '));
    ok('ni «niños» ni «fiesta» en una excursión', ! /niñ|fiesta/i.test(c));
    const aLaVista = (id) => a.page.evaluate((i) => {
        const marco = document.querySelector('[data-isla-scroll]').getBoundingClientRect();
        const el = document.getElementById(i)?.getBoundingClientRect();

        return { foco: document.activeElement?.id ?? '', dentro: Boolean(el) && el.top >= marco.top && el.bottom <= marco.bottom };
    }, id);
    let vista = await aLaVista(`pjc-dato-${obligatorios[0]}`);
    ok('la capa baja al primer dato obligatorio y lo enfoca (`#840`)', vista.foco === `pjc-dato-${obligatorios[0]}` && vista.dentro, JSON.stringify(vista));
    await foto(a.page, '3-compra');
    await a.page.fill(`#pjc-dato-${obligatorios[0]}`, 'CEIP de la sonda');
    await a.page.locator('[data-isla-scroll]').evaluate((m) => { m.scrollTop = 0; });
    const continuar = (page) => page.getByRole('button', { name: 'Continuar', exact: true });
    await continuar(a.page).click();
    await a.page.waitForTimeout(1200);
    vista = await aLaVista(`pjc-dato-${obligatorios[1]}`);
    ok('«Continuar» sin estar lista lleva al siguiente que falta', ! obligatorios[1] || (vista.foco === `pjc-dato-${obligatorios[1]}` && vista.dentro), JSON.stringify(vista));
    const rellenar = async (page) => { for (const k of obligatorios) { if (! await page.inputValue(`#pjc-dato-${k}`)) await page.fill(`#pjc-dato-${k}`, /phone|telefono/i.test(k) ? '600000000' : 'Dato de la sonda'); } await page.waitForTimeout(500); };
    await rellenar(a.page);
    c = (await dialogo()).replace(/\s+/g, ' ');
    ok('con los datos, «Hoy pagas» la señal del servidor', centimos(c.match(/Hoy pagas ([\d.,]+\s?€)/)?.[1]) === linea?.deposit_cents, c.match(/Hoy pagas [^.]*/)?.[0]);
    await continuar(a.page).click();
    await a.page.waitForTimeout(3500);
    c = (await dialogo()).replace(/\s+/g, ' ');
    ok('sin sesión, a «Tus datos», que pide el teléfono (un pack)', c.includes('Tus datos') && /[Tt]eléfono/.test(c), c.slice(0, 120));
    // La línea se valida al continuar, con los datos del centro dentro.
    const v = validadas.at(-1);
    ok('la línea validada lleva el pack, la gente, el día y la hora', v?.product_id === fila.id && v?.quantity === n1 && v?.date === dia && sinHora(v?.time) === sinHora(hora), JSON.stringify(v ?? {}).slice(0, 170));
    ok('…y los datos obligatorios del centro', obligatorios.every((k) => String(v?.event_data?.[k] ?? '') !== ''), JSON.stringify(v?.event_data ?? {}).slice(0, 170));
    await foto(a.page, '4-tus-datos');
    await a.ctx.close();

    // ── 6 · Con la cuenta de pruebas, hasta «Pagar» (NUNCA paga) ───────────────────────────────────────────────────
    const b = await contexto();
    await colegios(b.page);
    const entrada = await b.page.evaluate(async ({ email, password }) => {
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
        const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? '');
        const res = await fetch('/api/v1/auth/login', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf }, body: JSON.stringify({ email, password }) });

        return res.status;
    }, CLIENTE);
    ok('entra la cuenta de pruebas', entrada === 200 || entrada === 204, String(entrada));
    await b.page.evaluate((q) => window.JumpWeb.cajon.openWith({ type: 'linea', id: q.id, date: q.dia, time: `${q.hora}:00`, quantity: q.n, continuar: true }), { id: fila.id, dia, hora: sinHora(hora), n: n1 });
    await b.page.waitForSelector(`#pjc-dato-${obligatorios[0]}`, { timeout: 20000 });
    await b.page.waitForTimeout(1500);
    await rellenar(b.page);
    await continuar(b.page).click();
    await b.page.waitForTimeout(4000);
    const p = (await b.page.locator('[role="dialog"][data-isla-velo]').innerText()).replace(/\s+/g, ' ');
    ok('con sesión, a «Pagar»', /Repasa y paga/.test(p), p.slice(0, 100));
    ok('el recibo: el precio por persona del servidor y la gente', centimos(p.match(/([\d.,]+\s?€) por persona/)?.[1]) === linea?.unit_price_cents && p.includes(`${n1} personas`), p.match(/[\d.,]+\s?€ por persona/)?.[0]);
    const senal = p.match(/Hoy pagas ([\d.,]+\s?€) de señal; el resto, ([\d.,]+\s?€), el día de la visita\./);
    ok('el total, y la señal y el resto «el día de la visita», del servidor', Boolean(r.total) && p.includes(r.total) && centimos(senal?.[1]) === linea?.deposit_cents && centimos(senal?.[2]) === linea?.gate_remainder_cents, senal?.[0] ?? p.slice(0, 160));
    ok('ni «niños» ni «fiesta» en «Pagar»', ! /niñ|fiesta/i.test(p));
    await foto(b.page, '5-pagar');
    await b.ctx.close();

    // ── 7 · El cálculo que se retoma ──────────────────────────────────────────────────────────────────────────────
    const d = await contexto();
    const codigo = (n, h) => `${n}_${fila.id}_${dia}_${sinHora(h)}`;
    await colegios(d.page, `?c=${codigo(n1, hora)}#calcula`);
    await d.page.waitForTimeout(2000);
    const rd = await recibo(d.page, textos);
    ok('del enlace: la gente y el total del servidor', Number(await cifraDe(d.page).inputValue()) === n1 && centimos(rd.total) === linea?.total_cents, rd.total);
    // Sin el botón de la calculadora a la vista (con él, la razón de la pieza: Z6b).
    await sinBotonALaVista(d.page);
    const islaD = (await islaAhora(d.page)).frase ?? '';
    ok('la isla lo dice como suyo', islaD.includes(textos.tuya.trim()) && Boolean(rd.total) && islaD.includes(rd.total), islaD.slice(0, 90));
    const wa = decodeURIComponent(await d.page.locator('[data-jw-calculadora-lado] a[href*="wa.me"]').first().getAttribute('href') ?? '');
    ok('el WhatsApp comparte el enlace con el cálculo dentro', wa.includes(`/colegios?c=${encodeURIComponent(codigo(n1, hora))}#${calc.ancla}`), wa.slice(-60));
    const descarga = await d.page.locator('[data-jw-calculadora-lado] a', { hasText: textos.descargar_calculo }).getAttribute('href');
    ok('y se descarga la propuesta con ese cálculo', descarga?.endsWith(`?c=${encodeURIComponent(codigo(n1, hora))}&imprimir=1`) && descarga.startsWith(calc.hoja), descarga ?? '');
    // Del dispositivo: otra cifra y de vuelta sin enlace.
    const n2 = n1 + 1 <= fila.max ? n1 + 1 : n1 - 1;
    await escribir(d.page, n2);
    await d.page.waitForTimeout(1500);
    await colegios(d.page);
    await d.page.waitForTimeout(2000);
    const linea2 = await lineaDelServidor(d.page, { id: fila.id, n: n2, dia, hora });
    ok('sin enlace, lo guardado en el dispositivo: la gente y su total del servidor', Number(await cifraDe(d.page).inputValue()) === n2 && centimos((await recibo(d.page, textos)).total) === linea2?.total_cents, `${n2} · ${linea2?.total_cents}`);
    // Una hora que ya no está libre: la primera apagada del día (no cabe) o, si ninguna, una fuera de horario.
    const perdida = horas.find((h) => ! h.libre)?.hora ?? '23:30';
    await colegios(d.page, `?c=${codigo(fila.min, perdida)}#calcula`);
    await d.page.waitForTimeout(2000);
    t = await d.page.locator('#calcula').innerText();
    ok('la hora que ya no está libre se suelta y se dice', t.includes(textos.hora_perdida) && ! /Total \d/.test(await texto(d.page, '[data-jw-calculadora-lado]')), sinHora(perdida));
    await foto(d.page, '6-hora-perdida');
    await d.page.click(`#p3-hora [data-hora="${hora}"]`);
    await d.page.waitForTimeout(2000);
    ok('al elegir otra, el aviso se va', ! (await d.page.locator('#calcula').innerText()).includes(textos.hora_perdida));
    await d.ctx.close();

    // ── 8 · La hoja para dirección ────────────────────────────────────────────────────────────────────────────────
    // Es un A4 (794 × 1123 px) que se imprime: se mide en una ventana donde cabe; el móvil ya vio su miniatura (1).
    const e = await contexto(1280);
    await e.page.goto(`${calc.hoja}?c=${codigo(n1, hora)}`.replace(/^https?:\/\/[^/]+/, BASE), { waitUntil: 'networkidle' });
    await e.page.waitForTimeout(1500);
    const hoja = e.page.locator('article.pj-hoja').first();
    const cifras = await e.page.evaluate(() => Object.fromEntries([...document.querySelectorAll('[data-jw-hoja-cifra]')].map((x) => [x.dataset.jwHojaCifra, x.innerText.trim()])));
    ok('con cálculo: la gente, el precio por persona y el total, del servidor', Number(cifras.alumnos) === n1 && centimos(cifras.porAlumno) === linea?.unit_price_cents && centimos(cifras.total) === linea?.total_cents, JSON.stringify(cifras));
    const cuando = await texto(e.page, '[data-jw-hoja-cuando]');
    ok('la línea del grupo, con su hora', cuando.includes(sinHora(hora)), cuando);
    ok('las cifras generales, fuera', await e.page.locator('[data-jw-hoja-general]').first().isHidden());
    ok('el QR', await hoja.locator('[role="img"] svg').count() === 1);
    const a4 = await hoja.boundingBox();
    const pie = await hoja.locator('.pj-hoja__letra').boundingBox();
    ok('un A4 de 794 × 1123 px, con el pie dentro', Math.round(a4.width) === 794 && Math.round(a4.height) === 1123 && pie && pie.y + pie.height <= a4.y + a4.height - 20, `${Math.round(a4.width)}×${Math.round(a4.height)}`);
    const pdf = await e.page.pdf({ format: 'A4', printBackground: true, preferCSSPageSize: true });
    const paginas = (Buffer.from(pdf).toString('latin1').match(/\/Type\s*\/Page[^s]/g) ?? []).length;
    ok('el PDF sale en UNA sola página', paginas === 1, `${paginas} páginas`);
    await e.page.screenshot({ path: `${SALIDA}/colegios-${ANCHO}-7-hoja.png` });
    await e.page.goto(calc.hoja.replace(/^https?:\/\/[^/]+/, BASE), { waitUntil: 'networkidle' });
    await e.page.waitForTimeout(800);
    ok('sin cálculo: las generales y sin grupo', await e.page.locator('[data-jw-hoja-general]').first().isVisible() && await e.page.locator('[data-jw-hoja-grupo]').first().isHidden());
    ok('fuera de los buscadores (`noindex`)', /noindex/.test(await e.page.locator('meta[name="robots"]').getAttribute('content') ?? ''));
    await e.ctx.close();
} catch (err) {
    ok('la sonda terminó sin excepciones', false, err.message.split('\n')[0]);
} finally {
    await navegador.close();
}

ok('sin errores en la consola de la página', errores.length === 0, errores.slice(0, 3).join(' | '));
console.log(`\nSONDA DE COLEGIOS · ${ANCHO}px\n${filas.join('\n')}`);
const fallos = filas.filter((f) => f.startsWith('✗')).length;
console.log(`\n${filas.length - fallos}/${filas.length}`);
process.exit(fallos ? 1 : 0);
