/**
 * SONDA DE LA ISLA — la compra en la isla, de punta a punta, en un navegador de verdad (T3e·6 de
 * `docs/specs/isla-y-landing-nueva.md` §4.10). Es la versionada; las de cada sub-tanda (`storage/app/sonda-isla-t3e*.mjs`)
 * eran desechables. Recorre:
 *   1. una ENTRADA: la pantalla 0, «Tus datos» entrando con la cuenta de pruebas, «Pagar»… y la HORA SE LLENA justo antes
 *      de pagar (la sonda llena esa franja en la BD): «Esa hora ya no está libre», las horas cercanas, «Elegir esta hora»
 *      y de vuelta a «Pagar» con la línea rehecha; la pasarela con su formulario firmado;
 *   2. los DESENLACES por la vuelta real del banco: sin datos («verificando»), el rechazo («El pago no se ha
 *      completado», con su motivo) y reintentar, y el sí («¡Reservado!» con el QR del carné);
 *   3. un CUMPLEAÑOS: la pantalla 0 de la fiesta, la SEÑAL a la pasarela (5000 céntimos) y «¡Fiesta reservada!».
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-isla.mjs [390|1280]
 *
 * Solo en LOCAL, con la isla como carcasa (`sidebar.shell = isla`, en el panel) y la cuenta de pruebas
 * (`probe-card@jumpweb.test`, que no viaja en el repo: si falta, la sonda da su receta). La pasarela se intercepta: nada
 * sale a Redsys; el «sí» y el «no» del banco se escriben como los escribiría su notificación firmada.
 * ⚠️ Monta su propia página (`public/_sonda-isla.html`, las hojas de Saltia y el paquete del cajón, como lo hará una
 * página nueva) y la BORRA al acabar: un andamio olvidado en `public/` lo para la guarda 9 del despliegue.
 * ⚠️ La franja que llena la devuelve a su cupo exacto, también si la sonda se corta. Deja pedidos PAGADOS en la BD
 * local, como una compra de verdad; los pendientes, caducados. Sale con 1 si falla algo, y deja las fotos en
 * `storage/app/audit/isla-<ancho>-*.png`.
 */
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const SALIDA = 'storage/app/audit';
const PAGINA = 'public/_sonda-isla.html';
const ANCHO = Number(process.argv[2] ?? 1280);
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' };
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const SUS_PEDIDOS = `App\\Domain\\Booking\\Models\\Order::whereHas('user', fn ($q) => $q->where('email', '${CLIENTE.email}'))`;
const ULTIMO = `${SUS_PEDIDOS}->latest('id')->first()`;
const cuantosPedidos = () => Number(tinker(`echo ${SUS_PEDIDOS}->count();`));
const RECETA = [
    'docker compose exec -u sail -T laravel.test php artisan tinker --execute=\'$u = new App\\Domain\\Identity\\Models\\User();',
    '  $u->name = "Sonda"; $u->email = "probe-card@jumpweb.test"; $u->password = Illuminate\\Support\\Facades\\Hash::make("Probe-card-2026!");',
    '  $u->email_verified_at = now(); $u->save();\'',
].join('\n');

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }
if (tinker('echo App\\Domain\\Content\\Services\\ShellSettings::shell();') !== 'isla') {
    console.error('✗ la carcasa de esta instalación no es la isla: ponla en el panel (Ajustes · el cajón, «isla») y vuelve a correrla');
    process.exit(1);
}
if (tinker(`echo App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->exists() ? 1 : 0;`) !== '1') {
    console.error(`✗ falta la cuenta de pruebas ${CLIENTE.email}. Receta:\n${RECETA}`);
    process.exit(1);
}

/**
 * Los limitadores que dos corridas seguidas agotan: la API (60/min), la entrada, crear pedidos y el `throttle:6,1` del
 * REINTENTO, cuya clave sin nombre es `sha1(id del titular)` y la comparten los demás `throttle` numéricos (medido: la
 * segunda corrida a 390 daba 429 al reintentar).
 */
const limitadoresACero = () => tinker(`$id = App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->value('id'); foreach ([md5('api'.'user:'.$id), md5('api'.'ip:127.0.0.1'), Illuminate\\Support\\Str::transliterate('${CLIENTE.email}|127.0.0.1'), 'login-ip|127.0.0.1', 'reservation-confirm:'.$id, sha1((string) $id)] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }`);

/** La notificación firmada del banco, sobre el último cobro de la cuenta: `0000` es el sí. */
const bancoDice = (respuesta) => tinker(`$o = ${ULTIMO}; $p = $o->payments()->latest('id')->first(); $r = app(App\\Domain\\Payments\\Services\\Redsys::class); $params = strtr(base64_encode(json_encode(['Ds_Order' => $p->gateway_order, 'Ds_Response' => '${respuesta}', 'Ds_Amount' => (string) $p->amount, 'Ds_Currency' => '978', 'Ds_AuthorisationCode' => '123456'])), '+/', '-_'); app(App\\Domain\\Payments\\Services\\RedsysReturnHandler::class)->process(['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $params, 'Ds_Signature' => $r->createMerchantSignatureNotif($r->config()['secret_key'], $params)], 'notification');`);

const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);

await mkdir(SALIDA, { recursive: true });
await writeFile(PAGINA, `<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sonda de la isla (temporal)</title>
<!-- La monta scripts/sonda-isla.mjs y la BORRA al acabar (guarda 9). -->
<link rel="stylesheet" href="/instancia/css/fuentes.css"><link rel="stylesheet" href="/instancia/css/saltia.css">
<link rel="stylesheet" href="/instancia/css/isla.css"><link rel="stylesheet" href="/css/cajon.css">
<style>body{margin:0;min-height:1600px}main{max-width:720px;margin:0 auto;padding:24px 16px}</style>
</head><body><main><h1>Sonda de la isla</h1>
<button id="abrir-kids" type="button" data-jw-open-zone="kids">Reservar Kids</button>
<button id="abrir-packs" type="button" data-jw-open="packs">Reservar un cumpleaños</button>
</main><script type="module">
const manifiesto = await (await fetch('/build/manifest.json')).json();
await import('/build/' + manifiesto['resources/js/cajon/paquete.js'].file);
</script></body></html>
`);

limitadoresACero();
const navegador = await chromium.launch();
const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 600 ? 844 : 900 } });
const pasarela = [];
await ctx.route(/redsys\.es/, (ruta) => {
    pasarela.push(ruta.request().postData() ?? '');

    return ruta.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>pasarela</title><p>La pasarela de la sonda.</p>' });
});
const page = await ctx.newPage();
const errores = [];
page.on('pageerror', (e) => errores.push(e.message));
// Los «no» que la compra espera (401 sin sesión, 409/422 de la hora llena) no son errores de la página.
page.on('console', (m) => { if (m.type() === 'error' && ! /status of 4(01|09|22)/.test(m.text())) errores.push(m.text()); });

const paso = () => page.locator('#isla-compra-paso').innerText().catch(() => '');
const cuerpo = () => page.locator('[data-isla-scroll]').innerText().catch(() => '');
const debajo = () => page.locator('[data-isla] [aria-live="polite"]').last().innerText().catch(() => '');
const accion = (nombre) => page.locator('[data-isla] button', { hasText: nombre }).last();
const boton = (texto) => page.locator('[data-isla-scroll] button', { hasText: texto });
const horaLibre = () => page.locator('[data-isla-scroll] button:not([disabled])', { hasText: /^\d{2}:\d{2}/ }).first();
const foto = (n) => page.screenshot({ path: `${SALIDA}/isla-${ANCHO}-${n}.png` });
const quieta = async () => { await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(450); };
const espera = (fn, arg, ms = 15000) => page.waitForFunction(fn, arg, { timeout: ms });
const enElCuerpo = (re) => espera((r) => new RegExp(r).test(document.querySelector('[data-isla-scroll]')?.textContent ?? ''), re.source, 20000);
const hastaPaso = (t) => espera((x) => (document.querySelector('#isla-compra-paso')?.textContent ?? '').includes(x), t, 20000);
const volverDelBanco = async (ruta) => {
    await page.goto(`${BASE}${ruta}`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#isla-compra-paso', { state: 'attached', timeout: 20000 });
};

/** «Tus datos» → «Pagar», con la sesión que haya: «Hola», o «Entra» con la cuenta de pruebas. */
async function hastaPagar() {
    await hastaPaso('Tus datos');
    await quieta();
    if (! /^Hola/.test(await page.locator('[data-isla-scroll] h1').innerText().catch(() => ''))) {
        await page.locator('[data-isla-scroll] a, [data-isla-scroll] button', { hasText: /^Entra$/ }).first().click();
        await page.fill('#pjc-ent', CLIENTE.email);
        await page.fill('#pjc-ent-clave', CLIENTE.password);
        await accion(/^Continuar$/).click();
        await espera(() => /^Hola/.test(document.querySelector('[data-isla-scroll] h1')?.textContent ?? ''), null, 20000);
    }
    // Lo que la cuenta aún deba (el teléfono, el descargo) se da aquí mismo.
    if (await page.locator('#pjc-tel').count()) await page.fill('#pjc-tel', '600000000');
    if (await page.locator('#pjc-descargo').count()) await page.check('#pjc-descargo');
    await accion(/^Continuar al pago$/).click();
    await hastaPaso('Paso 2 de 2');
    await quieta();
}

/** La franja de la línea de la cesta (la que persiste el motor): la de su zona, ese día y a esa hora. */
async function franjaDeLaCesta() {
    const linea = await page.evaluate(() => {
        for (const valor of Object.values(localStorage)) {
            try { const j = JSON.parse(valor); if (Array.isArray(j?.lines) && j.lines.length) return j.lines[0]; } catch { /* otra clave */ }
        }

        return null;
    });
    if (! linea) return null;
    const hora = String(linea.time).slice(0, 5);
    const datos = JSON.parse(tinker(`$tt = App\\Domain\\Booking\\Models\\TicketType::find(${Number(linea.product_id)}); $s = App\\Domain\\Booking\\Models\\Slot::where('zone_id', $tt->zone_id)->whereDate('date', '${linea.date}')->where('start_time', '${hora}:00')->first(); echo json_encode($s ? ['id' => $s->id, 'cupo' => $s->online_capacity] : null);`));

    return datos ? { ...datos, hora } : null;
}

let llena = null;
const devolverFranja = () => {
    if (llena) tinker(`$s = App\\Domain\\Booking\\Models\\Slot::find(${llena.id}); $s->online_capacity = ${llena.cupo}; $s->saveQuietly();`);
    llena = null;
};

try {
    // ── 1. Una ENTRADA, y la hora que se llena justo antes de pagar ──────────────────────────────────────
    await page.goto(`${BASE}/_sonda-isla.html`, { waitUntil: 'networkidle' });
    await page.click('#abrir-kids');
    await page.waitForSelector('#isla-compra-paso', { timeout: 15000 });
    await enElCuerpo(/€/);
    await quieta();
    await horaLibre().click();
    await espera(() => /€/.test([...document.querySelectorAll('[data-isla] [aria-live="polite"]')].pop()?.textContent ?? ''), null, 10000);
    await quieta();
    ok('la pantalla 0: una hora elegida, con su línea y su total debajo', /entrada/.test(await debajo()), await debajo());
    await accion(/^Continuar$/).click();
    await hastaPagar();
    ok('«Tus datos» con la cuenta → «Paso 2 de 2 · Pagar»', (await paso()).includes('Pagar'), await paso());
    await foto('1-pagar');

    llena = await franjaDeLaCesta();
    ok('la franja de la línea, encontrada en la BD', llena !== null, JSON.stringify(llena));
    tinker(`$s = App\\Domain\\Booking\\Models\\Slot::find(${llena.id}); $s->online_capacity = $s->seats_taken; $s->saveQuietly();`);
    const pedidosAntes = cuantosPedidos();
    await accion(/^Pagar .* con tarjeta$/).click();
    await enElCuerpo(/Esa hora ya no está libre/);
    await quieta();
    const perdida = await cuerpo();
    const cercanas = await page.locator('[data-isla-scroll] button', { hasText: /^\d{2}:\d{2}/ }).allInnerTexts();
    ok('la hora se llena al pagar → «Esa hora ya no está libre. No se ha cobrado nada.»', /No se ha cobrado nada/.test(perdida), perdida.slice(0, 120));
    ok('con las horas cercanas del día (hasta cuatro, sin la perdida)', cercanas.length > 0 && cercanas.length <= 4 && ! cercanas.some((h) => h.startsWith(llena.hora)), `${llena.hora} → ${cercanas.map((h) => h.slice(0, 5)).join(', ')}`);
    ok('en la banda de «Pagar», y «Elegir esta hora» apagado hasta elegir', (await paso()).includes('Pagar') && await accion(/^Elegir esta hora$/).isDisabled());
    ok('debajo sigue la línea que se estaba pagando, como en el diseño', /entrada/.test(await debajo()) && (await debajo()).includes(llena.hora), await debajo());
    ok('no nació ningún pedido: de verdad no se cobró nada', cuantosPedidos() === pedidosAntes, `${pedidosAntes} → ${cuantosPedidos()}`);
    await foto('2-hora-llena');
    devolverFranja();

    const nueva = (await boton(/^\d{2}:\d{2}/).first().innerText()).slice(0, 5);
    await boton(/^\d{2}:\d{2}/).first().click();
    await accion(/^Elegir esta hora$/).click();
    await hastaPaso('Pagar');
    await espera((h) => (document.querySelector('[data-isla-scroll] h1')?.textContent ?? '') !== 'Esa hora ya no está libre.' && ([...document.querySelectorAll('[data-isla] [aria-live="polite"]')].pop()?.textContent ?? '').includes(h), nueva, 15000);
    await quieta();
    ok('«Elegir esta hora» → de vuelta a «Pagar», con la línea a la hora nueva', (await debajo()).includes(nueva), await debajo());
    await accion(/^Pagar .* con tarjeta$/).click();
    await page.waitForURL(/redsys\.es/, { timeout: 20000 });
    ok('pagar sale a la pasarela con el formulario firmado', pasarela.length === 1 && /Ds_Signature=/.test(pasarela[0]) && /Ds_MerchantParameters=/.test(pasarela[0]));

    // ── 2. Los desenlaces, por la vuelta REAL del banco ──────────────────────────────────────────────────
    await volverDelBanco('/pago/redsys/retorno-ok');
    await enElCuerpo(/confirmando con la pasarela/);
    await quieta();
    ok('volver sin datos → la isla abierta en «verificando»', /Paso 2 de 2/.test(await paso()));
    await foto('3-verificando');

    tinker(`$o = ${ULTIMO}; $p = $o->payments()->latest('id')->first(); $p->status = 'failed'; $p->raw_response = ['Ds_Response' => '0190']; $p->save();`);
    await volverDelBanco('/pago/redsys/retorno-ko');
    await enElCuerpo(/no se ha completado/);
    await quieta();
    ok('rechazo → «El pago no se ha completado», su motivo y la hora guardada', /Motivo: .+/.test(await cuerpo()) && /hasta las \d{1,2}:\d{2}/.test(await cuerpo()), await cuerpo());
    await foto('4-fallido');
    limitadoresACero();
    await accion(/^Volver a intentar con tarjeta$/).click();
    await page.waitForURL(/redsys\.es/, { timeout: 20000 });
    ok('reintentar → la pasarela otra vez', pasarela.length === 2);

    bancoDice('0000');
    await volverDelBanco('/pago/redsys/retorno-ok');
    await enElCuerpo(/Reservado/);
    await quieta();
    ok('el sí → «¡Reservado!» con su Nº de pedido y el QR del carné', /Nº de pedido/.test(await cuerpo()) && await page.locator('[data-isla-scroll] img[src*="/me/card/png"]').count() === 1);
    await foto('5-reservado');

    // ── 3. Un CUMPLEAÑOS con señal ───────────────────────────────────────────────────────────────────────
    limitadoresACero();
    await page.goto(`${BASE}/_sonda-isla.html`, { waitUntil: 'networkidle' });
    await page.click('#abrir-packs');
    await espera(() => (document.querySelector('[data-isla-scroll] h1')?.textContent ?? '') === 'Un cumpleaños', null, 20000);
    await enElCuerpo(/Menú 1/);
    await quieta();
    await boton(/^5$/).click();
    await quieta();
    await page.locator('[data-isla-scroll] button', { hasText: /^(lun|mar|mié|jue|vie|sáb|dom|hoy)/ }).first().click();
    await enElCuerpo(/¿A qué hora\?/);
    await quieta();
    await horaLibre().click();
    await espera(() => /Hoy pagas/.test([...document.querySelectorAll('[data-isla]')].pop()?.textContent ?? ''), null, 15000);
    await quieta();
    ok('la fiesta: la edad elige el pack, los niños en su mínimo y «Hoy pagas 50 €»', /KIDS, de 4 a 7 años/.test(await cuerpo()) && /8 niños/.test(await debajo()) && /Hoy pagas 50\s€/.test(await page.locator('[data-isla]').innerText()));
    await accion(/^Continuar$/).click();
    await hastaPagar();
    ok('«Pagar» cobra la señal: «Pagar 50 € con tarjeta»', /Pagar 50\s€ con tarjeta/.test(await accion(/con tarjeta$/).innerText()));
    await foto('6-fiesta-pagar');
    await accion(/con tarjeta$/).click();
    await page.waitForURL(/redsys\.es/, { timeout: 20000 });
    ok('a la pasarela sale la SEÑAL (5000 céntimos)', tinker(`echo ${ULTIMO}->payments()->latest('id')->first()->amount;`) === '5000');
    bancoDice('0000');
    await volverDelBanco('/pago/redsys/retorno-ok');
    await enElCuerpo(/Fiesta reservada/i);
    await quieta();
    ok('pagada → «¡Fiesta reservada!» con el formulario de invitados y su plazo', /Rellena el formulario de invitados/.test(await cuerpo()), (await cuerpo()).match(/Rellena[^\n]*/)?.[0]);
    await foto('7-fiesta-reservada');
} catch (e) {
    ok('SE CORTA', false, e.message.split('\n')[0]);
    await foto('corte').catch(() => {});
} finally {
    devolverFranja();
    tinker(`$o = ${ULTIMO}; if ($o && $o->status === 'pending') { $o->expires_at = now()->subMinute(); $o->save(); }`);
    execFileSync('php', ['artisan', 'orders:expire'], { encoding: 'utf8' });
    await navegador.close();
    await rm(PAGINA, { force: true });
}

ok('sin errores en la consola', errores.length === 0, errores.join(' | '));
console.log(filas.join('\n'));
const bien = filas.filter((f) => f.startsWith('✓')).length;
const todas = filas.filter((f) => /^[✓✗]/.test(f)).length;
console.log(`\n${bien}/${todas}`);
process.exit(bien === todas ? 0 : 1);
