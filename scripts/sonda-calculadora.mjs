/**
 * **LA SONDA DE LA CALCULADORA EN VIVO** (T4d·4 y ·5 de `specs/isla-y-landing-nueva.md` §4.12): la página de verdad
 * (`/kids`, `/jump`), el motor de verdad y la API de verdad, en un navegador, a 1280 y a 390. Comprueba:
 *   1. que al llegar NO se pide nada de la calculadora (se monta al acercarse la pieza) y que al bajar se monta;
 *   2. que los días del calendario son los que la API vende y que, al elegir día y hora, el total que se PINTA es el
 *      `total_cents` de la línea que respondió el servidor (el mismo formato: nunca una cuenta del cliente);
 *   3. que «Un par para cada» añade los calcetines y el total vuelve a ser el del servidor;
 *   4. que «Reservar y pagar» abre la compra de la isla en «Tus datos» con ESA línea en la cesta (`jw.cart.v1`);
 *   5. que la consola queda limpia.
 * Sale con 1 si algo falla. Dentro del contenedor, contra su puerto 80 (la sonda no pasa por el puente):
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-calculadora.mjs http://localhost kids
 */
/* global URL, console, setTimeout, document, localStorage -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { chromium } from 'playwright-core';

const [base = 'http://localhost', zona = 'kids'] = process.argv.slice(2);
const euros = (cents) => new Intl.NumberFormat('es', { style: 'currency', currency: 'EUR', minimumFractionDigits: cents % 100 === 0 ? 0 : 2, maximumFractionDigits: 2 }).format(cents / 100);

async function recorrer(navegador, ventana) {
    const [ancho, alto] = ventana.split('x').map(Number);
    const contexto = await navegador.newContext({ viewport: { width: ancho, height: alto }, locale: 'es-ES' });
    const pagina = await contexto.newPage();
    const errores = [];
    const api = [];
    const lineas = [];
    const informe = { ventana, checks: [] };
    const check = (nombre, ok, detalle = '') => informe.checks.push({ nombre, ok: Boolean(ok), detalle });

    // ⚠️ Los dos 401 de la ADMISIÓN de un invitado son de esperar: al seguir a «Tus datos», el motor pregunta a la vez por
    // la sesión y por si puede reservar (`sidebar/admission.js::runCheckout`), y sin sesión los dos dicen 401. El navegador
    // los apunta en la consola sin su URL, así que se descuentan por cuenta: tantos como respuestas 401 a esas dos rutas.
    const esperados401 = [];
    pagina.on('pageerror', (e) => errores.push(e.message));
    pagina.on('console', (m) => { if (m.type() === 'error') errores.push(m.text()); });
    pagina.on('request', (r) => { if (r.url().includes('/api/v1/')) api.push(`${r.method()} ${new URL(r.url()).pathname.replace('/api/v1', '')}`); });
    const malas = [];
    pagina.on('response', (r) => {
        const ruta = new URL(r.url()).pathname;
        if (r.status() === 401 && /\/api\/v1\/me(\/reservation-eligibility)?$/.test(ruta)) esperados401.push(ruta);
        else if (r.status() >= 400 && ruta.startsWith('/api/')) malas.push(`${r.status()} ${r.request().method()} ${ruta}`);
    });
    pagina.on('response', async (r) => {
        if (/\/catalog\/products\/\d+\/addons$/.test(new URL(r.url()).pathname) && r.request().method() === 'POST') {
            try { const j = await r.json(); lineas.push({ cuerpo: JSON.parse(r.request().postData() ?? '{}'), line: j.line ?? null, singles: j.singles ?? [] }); } catch { /* sin cuerpo */ }
        }
    });

    await pagina.goto(`${base}/${zona}`, { waitUntil: 'load' });
    await pagina.waitForTimeout(600);
    const alLlegar = api.filter((p) => /\/availability\/|\/catalog\/products\/\d+/.test(p));
    const pintada = await pagina.evaluate(() => ({
        preguntas: document.querySelectorAll('[data-jw-calculadora] > div').length,
        total: Boolean(document.querySelector('[data-jw-calculadora-lado] button')),
    }));
    check('al llegar ya está PINTADA (las preguntas y el total) sin haber pedido nada', pintada.preguntas >= 4 && pintada.total && alLlegar.length === 0,
        `${pintada.preguntas} preguntas${alLlegar.length ? `; pidió ${alLlegar.join(', ')}` : ''}`);

    await pagina.locator('#precio').scrollIntoViewIfNeeded();
    await pagina.waitForSelector('[data-jw-calculadora] > div', { timeout: 15000 });
    try {
        await pagina.waitForSelector('[data-jw-calculadora] button[aria-label*=", libre"]', { timeout: 15000 });
    } catch {
        check('al bajar, el calendario enseña días libres', false, `respuestas malas de la API: ${malas.join(', ') || 'ninguna'}; pidió ${api.join(', ')}`);
        await contexto.close();

        return informe;
    }
    check('al bajar se monta y pide los días de sus filas', api.some((p) => /\/availability\/\d+\/dates/.test(p)), api.filter((p) => p.includes('/availability/')).join(', '));

    // El primer día libre, y su primera hora libre.
    const dia = pagina.locator('[data-jw-calculadora] button[aria-label*=", libre"]:not([disabled])').first();
    const diaNombre = await dia.getAttribute('aria-label');
    await dia.click();
    await pagina.waitForSelector('[data-jw-calculadora] [role=group] button:not([disabled])', { timeout: 15000 });
    const hora = pagina.locator('[data-jw-calculadora] [role=group] button:not([disabled])').first();
    const horaTexto = (await hora.innerText()).split('\n')[0].trim();
    await hora.click();
    const boton = pagina.locator('[data-jw-calculadora-lado] button:has-text("Reservar y pagar")');
    await boton.waitFor({ state: 'visible', timeout: 15000 });
    await pagina.waitForFunction(() => { const b = [...document.querySelectorAll('[data-jw-calculadora-lado] button')].find((x) => x.textContent.includes('Reservar y pagar')); return b && ! b.disabled; }, null, { timeout: 15000 });
    await pagina.waitForTimeout(300);
    const lado = async () => pagina.locator('[data-jw-calculadora-lado]').innerText();
    const ultima = () => [...lineas].reverse().find((l) => l.line);
    const primera = ultima();
    check(`día (${diaNombre}) y hora (${horaTexto}): el total que se pinta es el del servidor`, primera && (await lado()).includes(euros(primera.line.total_cents)), primera ? `servidor ${euros(primera.line.total_cents)}` : 'sin línea');

    // Un par para cada uno: el cargo de los calcetines y el total, del servidor.
    const cadaUno = pagina.locator('[data-jw-calculadora] button:has-text("Un par para cada")');
    if (await cadaUno.count()) {
        const antes = lineas.length;
        await cadaUno.click();
        // Hasta que responda el servidor (la línea con los calcetines), y un momento para pintarla.
        for (const hasta = Date.now() + 10000; lineas.length <= antes && Date.now() < hasta;) await pagina.waitForTimeout(100);
        await pagina.waitForTimeout(400);
        const conCalcetines = ultima();
        const texto = await lado();
        check('con calcetines: su línea y el total del servidor', lineas.length > antes && conCalcetines.line.addons?.length === 1 && texto.includes(euros(conCalcetines.line.total_cents)) && texto.includes(euros(conCalcetines.line.addons[0].subtotal_cents)),
            conCalcetines ? `total ${euros(conCalcetines.line.total_cents)}, calcetines ${euros(conCalcetines.line.addons?.[0]?.subtotal_cents ?? 0)}` : '');
    } else {
        check('con calcetines: la zona los vende', false, 'no hay «Un par para cada»');
    }

    // «Reservar y pagar»: la compra de la isla en «Tus datos», con esa línea en la cesta.
    const pedida = ultima()?.cuerpo ?? {};
    await boton.click();
    let enDatos = true;
    try { await pagina.waitForSelector('text=Tus datos', { timeout: 20000 }); } catch { enDatos = false; }
    await pagina.waitForTimeout(500);
    const cesta = await pagina.evaluate(() => { try { return JSON.parse(localStorage.getItem('jw.cart.v1') ?? 'null'); } catch { return null; } });
    const linea = cesta?.lines?.[0] ?? null;
    check('«Reservar y pagar» abre la compra en «Tus datos»', enDatos);
    check('con ESA línea en la cesta', linea && linea.date === pedida.date && linea.time === pedida.time && Number(linea.quantity) === Number(pedida.quantity),
        linea ? `${linea.product_id} ${linea.date} ${linea.time} ×${linea.quantity}` : 'cesta vacía');

    let descontar = esperados401.length;
    const inesperados = errores.filter((m) => ! (/status of 401/.test(m) && descontar-- > 0));
    check(`consola limpia (${esperados401.length} × 401 de la admisión, esperados)`, inesperados.length === 0, inesperados.slice(0, 3).join(' | '));
    check('ninguna otra respuesta de error de la API', malas.length === 0, malas.join(', '));
    // Lo que cuesta el recorrido entero contra el suelo de la API (60 por minuto y por IP sin sesión, `config/api.php`).
    check(`${api.length} peticiones a la API, de llegar a «Tus datos» (el suelo: 60 por minuto y por IP)`, api.length <= 40, api.join(', '));
    await contexto.close();

    return informe;
}

// ⚠️ Un minuto de pausa ANTES de cada recorrido: todos salen de la misma IP, y el suelo de la API (60 por minuto sin
// sesión) lo agotaban dos recorridos seguidos —medido: 429 en los días y la ficha, y el calendario entero cerrado—.
const navegador = await chromium.launch();
const informes = [];
for (const ventana of ['1280x900', '390x844']) {
    await new Promise((listo) => { setTimeout(listo, Number(process.env.SONDA_PAUSA ?? 61000)); });
    informes.push(await recorrer(navegador, ventana));
}
await navegador.close();

let fallos = 0;
for (const { ventana, checks } of informes) {
    for (const c of checks) {
        if (! c.ok) fallos += 1;
        console.log(`${c.ok ? '✓' : '✗'} ${zona} ${ventana} · ${c.nombre}${c.detalle ? ` — ${c.detalle}` : ''}`);
    }
}
console.log(fallos === 0 ? `\n✓ la calculadora de ${zona}, en vivo: todo en su sitio` : `\n✗ ${fallos} fallos`);
process.exit(fallos === 0 ? 0 : 1);
