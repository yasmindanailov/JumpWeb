/**
 * SONDA DE LA CONVERSIÓN — las mejoras de la compra del zip del 26-09 en un navegador de verdad
 * (`docs/specs/isla-y-landing-nueva.md` §4.16, `DECISIONES #822`). Recorre, sin sesión y con la cuenta de pruebas:
 *   1. LA HORA SE LLENA AL CONTINUAR de la pantalla 0 (la sonda llena esa franja en la BD justo cuando la compra pregunta
 *      por ella, en `POST /cart/validate-line`): «Paso 1 de 2 · Tus datos», «Esa hora ya no está libre.», «Estas sí:»
 *      —SIN «No se ha cobrado nada»: no se ha pedido nada—, las horas cercanas, «Elegir esta hora» apagado hasta elegir,
 *      la flecha a la pantalla 0 y, debajo, lo que se estaba eligiendo; al elegir, a «Tus datos» con la hora nueva;
 *   2. «¿QUERÍAS DECIR…?»: un correo mal escrito propone el bueno al salir del campo, y un toque lo escribe;
 *   3. EL INTRO: el teclado dice «Siguiente» e «Ir» (`enterkeyhint`), Intro pasa al campo siguiente y en el último hace la
 *      acción del paso;
 *   4. EL TECLADO DEL MÓVIL (solo por debajo de 900px): con `visualViewport` encogido —la sonda lo sustituye antes de que
 *      cargue nada, porque un Chromium sin cabeza no abre teclado—, la capa mide lo que se ve, la acción queda encima y el
 *      pie se queda en ella; al cerrarlo, vuelve el resumen;
 *   5. «PAGAR», COMPACTO (`#823`, el owner): sin la política de cambios (la dicen la página y sus dudas) y con las formas
 *      de pago DENTRO de la frase de la pasarela, más pequeñas.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-conversion.mjs [390|1280]
 *
 * Solo en LOCAL, con la isla como carcasa y la cuenta de pruebas de `sonda-isla.mjs`. Monta su propia página
 * (`public/_sonda-conversion.html`) y la BORRA al acabar (guarda 9 del despliegue). La franja que llena la devuelve a su
 * cupo exacto, también si la sonda se corta. No paga: no deja pedidos. Sale con 1 si falla algo.
 */
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const SALIDA = 'storage/app/audit';
const PAGINA = 'public/_sonda-conversion.html';
const ANCHO = Number(process.argv[2] ?? 390);
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' };
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }
if (tinker('echo App\\Domain\\Content\\Services\\ShellSettings::shell();') !== 'isla') { console.error('✗ la carcasa no es la isla'); process.exit(1); }
if (tinker(`echo App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->exists() ? 1 : 0;`) !== '1') {
    console.error(`✗ falta la cuenta de pruebas ${CLIENTE.email}: su receta, en scripts/sonda-isla.mjs`);
    process.exit(1);
}
// Los limitadores que dos corridas seguidas agotan (los mismos que `sonda-isla.mjs`, con los de pedir el código).
tinker(`$id = App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->value('id'); $h = App\\Domain\\Identity\\Services\\SelfSignup::emailHash('${CLIENTE.email}'); foreach ([md5('api'.'user:'.$id), md5('api'.'ip:127.0.0.1'), Illuminate\\Support\\Str::transliterate('${CLIENTE.email}|127.0.0.1'), 'login-ip|127.0.0.1', 'login-code-ip|127.0.0.1', 'login-code-email|'.$h, 'login-code-email-hour|'.$h] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }`);

/** El código para entrar recién llegado al buzón (Mailpit, su asunto), después de `desde`: el de `sonda-isla.mjs`. */
const MAILPIT = process.env.SONDA_MAILPIT ?? 'http://mailpit:8025';
async function codigoDelBuzon(desde) {
    const url = `${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${CLIENTE.email}"`)}&limit=1`;

    for (let i = 0; i < 60; i += 1) {
        const ultimo = (await fetch(url).then((r) => r.json()).catch(() => null))?.messages?.[0];
        const cifras = /(\d{3})-(\d{3}) /.exec(ultimo?.Subject ?? '');
        if (cifras && Date.parse(ultimo.Created) >= desde - 2000) return `${cifras[1]}${cifras[2]}`;
        await new Promise((listo) => setTimeout(listo, 250));
    }

    return null;
}

const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${ANCHO} · ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);

await mkdir(SALIDA, { recursive: true });
await writeFile(PAGINA, `<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sonda de la conversión (temporal)</title>
<!-- La monta scripts/sonda-conversion.mjs y la BORRA al acabar (guarda 9). -->
<link rel="stylesheet" href="/instancia/css/fuentes.css"><link rel="stylesheet" href="/instancia/css/saltia.css">
<link rel="stylesheet" href="/instancia/css/isla.css"><link rel="stylesheet" href="/css/cajon.css">
<style>body{margin:0;min-height:1600px}main{max-width:720px;margin:0 auto;padding:24px 16px}</style>
</head><body><main><h1>Sonda de la conversión</h1>
<button id="abrir-kids" type="button" data-jw-open-zone="kids">Reservar Kids</button>
</main><script type="module">
const manifiesto = await (await fetch('/build/manifest.json')).json();
await import('/build/' + manifiesto['resources/js/cajon/paquete.js'].file);
</script></body></html>
`);

const navegador = await chromium.launch();
const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 900 ? 844 : 900 } });
// El teclado, de mentira: un `visualViewport` que la sonda encoge (`__teclado(alto)`) y devuelve (`__teclado()`).
await ctx.addInitScript(() => {
    const vv = new EventTarget();
    let alto = null;
    Object.defineProperty(vv, 'height', { get: () => alto ?? window.innerHeight });
    Object.defineProperty(vv, 'offsetTop', { get: () => 0 });
    Object.defineProperty(window, 'visualViewport', { configurable: true, get: () => vv });
    window.__teclado = (h) => { alto = h ?? null; vv.dispatchEvent(new Event('resize')); };
});
const page = await ctx.newPage();
const errores = [];
page.on('pageerror', (e) => errores.push(e.message));
page.on('console', (m) => { if (m.type() === 'error' && ! /status of 4(01|09|22)/.test(m.text())) errores.push(m.text()); });

const paso = () => page.locator('#isla-compra-paso').innerText().catch(() => '');
const cuerpo = () => page.locator('[data-isla-scroll]').innerText().catch(() => '');
const debajo = () => page.locator('[data-isla] [aria-live="polite"]').last().innerText().catch(() => '');
const accion = (nombre) => page.locator('[data-isla] button', { hasText: nombre }).last();
const horaLibre = () => page.locator('[data-isla-scroll] button:not([disabled])', { hasText: /^\d{2}:\d{2}/ }).first();
const quieta = async () => { await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(450); };
const espera = (fn, arg, ms = 20000) => page.waitForFunction(fn, arg, { timeout: ms });
const enElCuerpo = (re) => espera((r) => new RegExp(r).test(document.querySelector('[data-isla-scroll]')?.textContent ?? ''), re.source);
const hastaPaso = (t) => espera((x) => (document.querySelector('#isla-compra-paso')?.textContent ?? '').includes(x), t);
const foto = (n) => page.screenshot({ path: `${SALIDA}/conversion-${ANCHO}-${n}.png` });

// La franja que se llena: la de la PRIMERA línea que la compra valida (su zona, ese día, esa hora).
let llena = null;
let porLlenar = true;
const devolverFranja = () => {
    if (llena) tinker(`$s = App\\Domain\\Booking\\Models\\Slot::find(${llena.id}); $s->online_capacity = ${llena.cupo}; $s->saveQuietly();`);
    llena = null;
};
await page.route('**/api/v1/cart/validate-line', async (ruta) => {
    if (porLlenar) {
        porLlenar = false;
        const l = JSON.parse(ruta.request().postData() ?? '{}').line ?? {};
        const hora = String(l.time ?? '').slice(0, 5);
        const f = JSON.parse(tinker(`$tt = App\\Domain\\Booking\\Models\\TicketType::find(${Number(l.product_id)}); $s = $tt ? App\\Domain\\Booking\\Models\\Slot::where('zone_id', $tt->zone_id)->whereDate('date', '${l.date}')->where('start_time', '${hora}:00')->first() : null; echo json_encode($s ? ['id' => $s->id, 'cupo' => $s->online_capacity] : null);`));
        if (f) {
            llena = { ...f, hora };
            tinker(`$s = App\\Domain\\Booking\\Models\\Slot::find(${f.id}); $s->online_capacity = $s->seats_taken; $s->saveQuietly();`);
        }
    }
    await ruta.continue();
});

try {
    // ── 1. La hora se llena AL CONTINUAR ────────────────────────────────────────────────────────────────
    await page.goto(`${BASE}/_sonda-conversion.html`, { waitUntil: 'networkidle' });
    await page.click('#abrir-kids');
    await page.waitForSelector('#isla-compra-paso', { timeout: 15000 });
    await enElCuerpo(/€/);
    await quieta();
    await horaLibre().click();
    await espera(() => /€/.test([...document.querySelectorAll('[data-isla] [aria-live="polite"]')].pop()?.textContent ?? ''));
    await quieta();
    await accion(/^Continuar$/).click();
    await enElCuerpo(/Esa hora ya no está libre/);
    await quieta();
    ok('la franja de la hora elegida, llenada al preguntar por ella', llena !== null, JSON.stringify(llena));
    const texto = await cuerpo();
    const cercanas = await page.locator('[data-isla-scroll] button', { hasText: /^\d{2}:\d{2}/ }).allInnerTexts();
    ok('al continuar: «Paso 1 de 2 · Tus datos», no «Pagar»', /Paso 1 de 2/.test(await paso()) && /Tus datos/.test(await paso()), await paso());
    ok('«Esa hora ya no está libre.» y «Estas sí:», SIN «No se ha cobrado nada»', /Estas sí:/.test(texto) && ! /No se ha cobrado nada/.test(texto), texto.slice(0, 120));
    ok('las horas cercanas del día (hasta cuatro, sin la llena)', cercanas.length > 0 && cercanas.length <= 4 && ! cercanas.some((h) => h.startsWith(llena?.hora ?? '--')), `${llena?.hora} → ${cercanas.map((h) => h.slice(0, 5)).join(', ')}`);
    // M2 de `#880` (`#881`): sin hora elegida el botón NO se apaga; el pie dice qué falta (y, al pulsar, se marca).
    ok('«Elegir esta hora» sin hora: no se apaga y el pie dice qué falta', ! await accion(/^Elegir esta hora$/).isDisabled() && /Elige una de estas horas/.test(await page.locator('[data-isla] button[aria-live="polite"]').innerText().catch(() => '')));
    ok('con su flecha a la pantalla 0', (await page.locator('[data-isla] button[aria-label="Volver"]').count()) === 1);
    ok('debajo, lo que se estaba eligiendo', /entrada/.test(await debajo()), await debajo());
    await foto('1-hora-llena-al-continuar');
    devolverFranja();

    const nueva = (await page.locator('[data-isla-scroll] button', { hasText: /^\d{2}:\d{2}/ }).first().innerText()).slice(0, 5);
    await page.locator('[data-isla-scroll] button', { hasText: /^\d{2}:\d{2}/ }).first().click();
    await accion(/^Elegir esta hora$/).click();
    await espera(() => (document.querySelector('[data-isla-scroll] h1')?.textContent ?? '') !== 'Esa hora ya no está libre.' && (document.querySelector('#isla-compra-paso')?.textContent ?? '').includes('Tus datos'));
    await espera((h) => ([...document.querySelectorAll('[data-isla] [aria-live="polite"]')].pop()?.textContent ?? '').includes(h), nueva);
    await quieta();
    ok('«Elegir esta hora» → «Tus datos», con la línea a la hora nueva', (await debajo()).includes(nueva), `${nueva} · ${await debajo()}`);

    // ── 2. «¿Querías decir…?» ───────────────────────────────────────────────────────────────────────────
    await page.fill('#pjc-correo', 'sonda@gmial.com');
    await page.locator('#pjc-correo').blur();
    const sugerencia = page.locator('[data-isla-scroll] button', { hasText: '¿Querías decir' });
    await sugerencia.waitFor({ timeout: 5000 }).catch(() => {});
    ok('un correo mal escrito, al salir del campo: «¿Querías decir sonda@gmail.com?»', (await sugerencia.innerText().catch(() => '')).includes('sonda@gmail.com'), await sugerencia.innerText().catch(() => '(no sale)'));
    await sugerencia.click().catch(() => {});
    await page.waitForTimeout(200);
    ok('un toque lo escribe, y la propuesta se va', (await page.inputValue('#pjc-correo')) === 'sonda@gmail.com' && (await sugerencia.count()) === 0, await page.inputValue('#pjc-correo'));
    await foto('2-sugerencia');

    // ── 3. El Intro ─────────────────────────────────────────────────────────────────────────────────────
    const pistas = await page.$$eval('[data-isla-scroll] input:not([type=hidden]):not([type=checkbox]):not([type=radio])', (els) => els.map((e) => [e.id, e.getAttribute('enterkeyhint')]));
    ok('el teclado dice «Siguiente» entre campos e «Ir» en el último', pistas.length > 1 && pistas.slice(0, -1).every(([, p]) => p === 'next') && pistas.at(-1)[1] === 'go', JSON.stringify(pistas));
    await page.focus(`#${pistas[0][0]}`);
    await page.keyboard.press('Enter');
    ok('Intro en un campo pasa al siguiente', await page.evaluate(() => document.activeElement?.id) === pistas[1][0], await page.evaluate(() => document.activeElement?.id));
    await page.fill(`#${pistas[0][0]}`, '');
    await page.focus(`#${pistas.at(-1)[0]}`);
    await page.keyboard.press('Enter');
    await enElCuerpo(/Revisa/).catch(() => {});
    ok('Intro en el último hace la acción del paso («Continuar al pago»: lo que falta, arriba)', /Revisa/.test(await cuerpo()), (await cuerpo()).slice(0, 90));

    // ── 4. El teclado del móvil ─────────────────────────────────────────────────────────────────────────
    if (ANCHO < 900) {
        await page.evaluate(() => window.__teclado(400));
        await page.waitForTimeout(400);
        const conTeclado = await page.evaluate(() => {
            const capa = document.querySelector('#isla-compra-paso')?.parentElement?.parentElement;
            const accionPie = [...document.querySelectorAll('[data-isla] button')].find((b) => /Continuar al pago/.test(b.textContent));
            return {
                capa: Math.round(capa?.getBoundingClientRect().height ?? 0),
                accion: Math.round(accionPie?.getBoundingClientRect().bottom ?? 9999),
                resumen: [...document.querySelectorAll('[data-isla] [aria-live="polite"]')].filter((n) => n.textContent.includes('€')).length,
            };
        });
        ok('con el teclado (400px visibles), la capa mide lo que se ve y la acción queda encima de él', conTeclado.capa === 384 && conTeclado.accion <= 400, JSON.stringify(conTeclado));
        ok('y el pie se queda en la acción: sin el resumen', conTeclado.resumen === 0, JSON.stringify(conTeclado));
        await foto('4-teclado');
        await page.evaluate(() => window.__teclado());
        await page.waitForTimeout(400);
        ok('al cerrarlo, vuelve el resumen', /€/.test(await debajo()), await debajo());
    }

    // ── 5. La política al final del recibo de «Pagar» ───────────────────────────────────────────────────
    await page.locator('[data-isla-scroll] a, [data-isla-scroll] button', { hasText: /^Entra$/ }).first().click();
    await page.fill('#pjc-ent', CLIENTE.email);
    // Con un código al correo (A3 del acceso con código, `#849`).
    const desde = Date.now();
    await accion(/^Continuar$/).click();
    await page.waitForSelector('#pjc-ent-codigo', { timeout: 15000 });
    await page.fill('#pjc-ent-codigo', (await codigoDelBuzon(desde)) ?? '');
    await accion(/^Entrar$/).click();
    await espera(() => /^Hola/.test(document.querySelector('[data-isla-scroll] h1')?.textContent ?? '') || (document.querySelector('#isla-compra-paso')?.textContent ?? '').trim().endsWith('Pagar'));
    if (/Tus datos/.test(await paso())) {
        if (await page.locator('#pjc-tel').count()) await page.fill('#pjc-tel', '600000000');
        if (await page.locator('#pjc-descargo').count()) await page.check('#pjc-descargo');
        await accion(/^Continuar al pago$/).click();
    }
    await hastaPaso('Pagar');
    await quieta();
    // El owner (`#823`): sin la política de cambios (la dicen la página y sus dudas; «Al pagar aceptas las condiciones»,
    // con su enlace, la cubre) y las formas de pago DENTRO de la frase de la pasarela, más pequeñas: un bloque menos.
    const pie = await page.evaluate(() => {
        const isla = document.querySelector('[data-isla]')?.textContent ?? '';
        const lista = document.querySelector('[data-isla] ul[aria-label="Formas de pago"]');
        const linea = lista?.parentElement;
        return {
            politica: /puedes cambiar o cancelar/.test(isla),
            condiciones: /Al pagar aceptas las condiciones de reserva/.test(isla),
            enLaPasarela: Boolean(linea && /Tu tarjeta no se guarda/.test(linea.textContent)),
            altos: [...(lista?.querySelectorAll('img') ?? [])].map((i) => Math.round(i.getBoundingClientRect().height)),
        };
    });
    ok('«Pagar», sin la política de cambios y con «Al pagar aceptas las condiciones» (#823)', ! pie.politica && pie.condiciones, JSON.stringify(pie));
    ok('las formas de pago, en la frase de la pasarela y más pequeñas (#823)', pie.enLaPasarela && pie.altos.length > 0 && pie.altos.every((h) => h <= 16), JSON.stringify(pie));
    await foto('5-pagar');

    ok('la consola, limpia', errores.length === 0, errores.join(' | '));
} finally {
    devolverFranja();
    await navegador.close();
    await rm(PAGINA, { force: true });
}

const bien = filas.filter((f) => f.startsWith('✓')).length;
filas.forEach((f) => console.log(f));
console.log(`\nsonda-conversion ${ANCHO}: ${bien}/${filas.length}`);
process.exit(bien === filas.length ? 0 : 1);
