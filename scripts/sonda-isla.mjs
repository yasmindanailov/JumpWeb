/**
 * SONDA DE LA ISLA — la compra en la isla, de punta a punta, en un navegador de verdad (T3e·6 de
 * `docs/specs/isla-y-landing-nueva.md` §4.10), desde las PÁGINAS de verdad (T4f, §4.12: hasta entonces, desde un andamio
 * con dos botones). Recorre:
 *   1. una ENTRADA desde `/kids`: su calculadora (el primer día y la primera hora libres), «Reservar y pagar», «Tus datos»
 *      entrando con la cuenta de pruebas, «Pagar»… y la HORA SE LLENA justo antes de pagar (la sonda llena esa franja en la
 *      BD): «Esa hora ya no está libre», las horas cercanas, «Elegir esta hora» y de vuelta a «Pagar» con la línea rehecha;
 *      la pasarela con su formulario firmado;
 *   2. los DESENLACES por la vuelta real del banco: sin datos («verificando»), el rechazo («El pago no se ha
 *      completado», con su motivo) y reintentar, y el sí («¡Reservado!» con el QR del carné);
 *   3. un CUMPLEAÑOS desde `/cumpleanos`: su calculadora (la edad elige el pack, el primer día con hueco y su primera hora),
 *      «Reservar y pagar la señal», la SEÑAL a la pasarela (5000 céntimos) y «¡Fiesta reservada!».
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-isla.mjs [390|1280]
 *
 * Solo en LOCAL, con la isla como carcasa (`sidebar.shell = isla`, en el panel), el paquete de la instancia (sus páginas
 * `/kids` y `/cumpleanos`) y la cuenta de pruebas (`probe-card@jumpweb.test`, que no viaja en el repo: si falta, la sonda
 * da su receta). La pasarela se intercepta: nada sale a Redsys; el «sí» y el «no» del banco se escriben como los
 * escribiría su notificación firmada.
 * ⚠️ La franja que llena la devuelve a su cupo exacto, también si la sonda se corta. Deja pedidos PAGADOS en la BD
 * local, como una compra de verdad; los pendientes, caducados. Sale con 1 si falla algo, y deja las fotos en
 * `storage/app/audit/isla-<ancho>-*.png`.
 */
import { chromium } from 'playwright-core';
import { Buffer } from 'node:buffer';
import { execFileSync } from 'node:child_process';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const SALIDA = 'storage/app/audit';
const POLITICA = '2026-09-24'; // CookieConsent::POLICY_VERSION: con el aviso ya contestado, la isla no lo antepone
const ANCHO = Number(process.argv[2] ?? 1280);
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' };
const MAILPIT = process.env.SONDA_MAILPIT ?? 'http://mailpit:8025';
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
if (tinker('$p = app(App\\Http\\Instancia\\InstancePages::class); echo $p->queOcupa("cumpleanos")?->slug !== null && Illuminate\\Support\\Facades\\Route::has("instancia.kids") ? 1 : 0;') !== '1') {
    console.error('✗ el paquete de la instancia no declara `/kids` ni una página que ocupe `/cumpleanos`: la compra se prueba desde ellas');
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
// Y los de pedir el código (A3 del acceso con código): con el del minuto vivo, la segunda corrida no recibiría otro y
// leería del buzón el de la primera, ya gastado.
const limitadoresACero = () => tinker(`$id = App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->value('id'); $h = App\\Domain\\Identity\\Services\\SelfSignup::emailHash('${CLIENTE.email}'); foreach ([md5('api'.'user:'.$id), md5('api'.'ip:127.0.0.1'), Illuminate\\Support\\Str::transliterate('${CLIENTE.email}|127.0.0.1'), 'login-ip|127.0.0.1', 'login-code-ip|127.0.0.1', 'login-code-email|'.$h, 'login-code-email-hour|'.$h, 'reservation-confirm:'.$id, sha1((string) $id)] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }`);

/**
 * El código para entrar que acaba de llegar al buzón de la cuenta de pruebas (A3 del acceso con código, `#849`): lo lee
 * de Mailpit —el correo de verdad, enviado tras la respuesta—, de su asunto («123 456 es tu código para entrar»), y solo
 * uno llegado DESPUÉS de `desde` (el de una corrida anterior ya se gastó).
 */
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

/** La notificación firmada del banco, sobre el último cobro de la cuenta: `0000` es el sí. */
const bancoDice = (respuesta) => tinker(`$o = ${ULTIMO}; $p = $o->payments()->latest('id')->first(); $r = app(App\\Domain\\Payments\\Services\\Redsys::class); $params = strtr(base64_encode(json_encode(['Ds_Order' => $p->gateway_order, 'Ds_Response' => '${respuesta}', 'Ds_Amount' => (string) $p->amount, 'Ds_Currency' => '978', 'Ds_AuthorisationCode' => '123456'])), '+/', '-_'); app(App\\Domain\\Payments\\Services\\RedsysReturnHandler::class)->process(['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $params, 'Ds_Signature' => $r->createMerchantSignatureNotif($r->config()['secret_key'], $params)], 'notification');`);

const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);

await mkdir(SALIDA, { recursive: true });
limitadoresACero();
const navegador = await chromium.launch();
const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 600 ? 844 : 900 }, locale: 'es-ES' });
await ctx.addCookies([{ name: 'cookie_consent', value: Buffer.from(JSON.stringify({ v: POLITICA, cats: {} })).toString('base64'), url: BASE }]);
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
const foto = (n) => page.screenshot({ path: `${SALIDA}/isla-${ANCHO}-${n}.png` });
const quieta = async () => { await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(450); };
const espera = (fn, arg, ms = 15000) => page.waitForFunction(fn, arg, { timeout: ms });
const enElCuerpo = (re) => espera((r) => new RegExp(r).test(document.querySelector('[data-isla-scroll]')?.textContent ?? ''), re.source, 20000);
const hastaPaso = (t) => espera((x) => (document.querySelector('#isla-compra-paso')?.textContent ?? '').includes(x), t, 20000);
const volverDelBanco = async (ruta) => {
    await page.goto(`${BASE}${ruta}`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#isla-compra-paso', { state: 'attached', timeout: 20000 });
};

/**
 * Hasta «Pagar», con la sesión que haya. Sin sesión, «Tus datos» y su «Entra» con la cuenta de pruebas; con ella y nada
 * que pedir, «Pagar» directamente (`#785`), y si falta algo (el teléfono, el descargo), se da en «Tus datos». Devuelve si
 * entró con el código.
 */
async function hastaPagar() {
    const enDatos = () => (document.querySelector('#isla-compra-paso')?.textContent ?? '').includes('Tus datos');
    let entro = false;

    await espera(() => /Tus datos|Pagar$/.test((document.querySelector('#isla-compra-paso')?.textContent ?? '').trim()), null, 20000);
    await quieta();
    if (await page.evaluate(enDatos) && ! /^Hola/.test(await page.locator('[data-isla-scroll] h1').innerText().catch(() => ''))) {
        entro = true;
        await page.locator('[data-isla-scroll] a, [data-isla-scroll] button', { hasText: /^Entra$/ }).first().click();
        await page.fill('#pjc-ent', CLIENTE.email);
        // Entra con un código al correo (A3, `#849`): el correo pide el código, y el del buzón entra.
        const desde = Date.now();
        await accion(/^Continuar$/).click();
        await page.waitForSelector('#pjc-ent-codigo', { timeout: 15000 });
        const codigo = await codigoDelBuzon(desde);
        ok('«Entra»: el correo con cuenta pide el código, y llega al buzón', codigo !== null, codigo ?? 'sin código en Mailpit');
        await page.fill('#pjc-ent-codigo', codigo ?? '');
        // Sin marcar «Mantener la sesión iniciada» (`#858`): la sesión de siempre, SIN cookie de recuerdo (abajo).
        await accion(/^Entrar$/).click();
        await espera(() => /^Hola/.test(document.querySelector('[data-isla-scroll] h1')?.textContent ?? '') || (document.querySelector('#isla-compra-paso')?.textContent ?? '').trim().endsWith('Pagar'), null, 20000);
    }
    // Lo que la cuenta aún deba (el teléfono, el descargo) se da aquí mismo.
    if (await page.evaluate(enDatos)) {
        if (await page.locator('#pjc-tel').count()) await page.fill('#pjc-tel', '600000000');
        if (await page.locator('#pjc-descargo').count()) await page.check('#pjc-descargo');
        await accion(/^Continuar al pago$/).click();
    }
    await hastaPaso('Pagar');
    await quieta();

    return entro;
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

/**
 * El teléfono de la cuenta de pruebas (`#787`): la FIESTA se prueba con la cuenta SIN él —la fiesta lo pide en «Tus
 * datos»; una entrada no (`sonda-compra-directa`)—, y al acabar vuelve el suyo, pase lo que pase.
 */
let telefonoDeLaSonda = null;
const quitarTelefono = () => {
    telefonoDeLaSonda = tinker(`echo App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->value('phone');`);
    tinker(`App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->update(['phone' => null]);`);
};
const devolverTelefono = () => {
    if (telefonoDeLaSonda !== null) tinker(`App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->update(['phone' => ${telefonoDeLaSonda === '' ? 'null' : JSON.stringify(telefonoDeLaSonda)}]);`);
    telefonoDeLaSonda = null;
};

let llena = null;
const devolverFranja = () => {
    if (llena) tinker(`$s = App\\Domain\\Booking\\Models\\Slot::find(${llena.id}); $s->online_capacity = ${llena.cupo}; $s->saveQuietly();`);
    llena = null;
};

try {
    // ── 1. Una ENTRADA desde `/kids`, y la hora que se llena justo antes de pagar ────────────────────────
    await page.goto(`${BASE}/kids`, { waitUntil: 'networkidle' });
    await page.locator('#precio').scrollIntoViewIfNeeded();
    await page.waitForSelector('[data-jw-calculadora] button[aria-label*=", libre"]:not([disabled])', { timeout: 20000 });
    await page.locator('[data-jw-calculadora] button[aria-label*=", libre"]:not([disabled])').first().click();
    await page.waitForSelector('[data-jw-calculadora] [role=group] button:not([disabled])', { timeout: 15000 });
    await page.locator('[data-jw-calculadora] [role=group] button:not([disabled])').first().click();
    await espera(() => { const b = [...document.querySelectorAll('[data-jw-calculadora-lado] button')].find((x) => x.textContent.includes('Reservar y pagar')); return b && ! b.disabled; }, null, 15000);
    await quieta();
    const calculado = await page.locator('[data-jw-calculadora-lado]').innerText();
    ok('la calculadora de /kids: un día y una hora elegidos, con su total', /\d\s?€/.test(calculado), calculado.slice(0, 90));
    await page.locator('[data-jw-calculadora-lado] button', { hasText: 'Reservar y pagar' }).click();
    const entro = await hastaPagar();
    ok('entrar SIN marcar «Mantener la sesión iniciada» no deja cookie de recuerdo (`#858`: solo si se pide)',
        entro && ! (await ctx.cookies()).some((c) => c.name.startsWith('remember_web_')), (await ctx.cookies()).map((c) => c.name).join(', '));
    // Entrar con el código en «Tus datos» y sin nada más que pedir (`#857`, el owner 30-09): «Tus datos» SALE DEL CAMINO.
    // «Pagar» queda como la de quien llega con sesión —sin «Paso 2 de 2», con «Reservas como …»— y su flecha vuelve a la
    // reserva, no a una pantalla vacía; y «Continuar» lleva otra vez a «Pagar», directo.
    ok('«Tus datos» con la cuenta → «Pagar» como quien llega con sesión: sin «Paso 2 de 2» y «Reservas como …»',
        entro && (await paso()).trim() === 'Pagar' && /Reservas como Sonda/.test(await cuerpo()), `${await paso()} · ${(await cuerpo()).slice(0, 70)}`);
    await page.locator('[data-isla] button[aria-label="Volver"]').click();
    await espera(() => ! /Tus datos|Pagar/.test(document.querySelector('#isla-compra-paso')?.textContent ?? ''), null, 15000).catch(() => {});
    await quieta();
    const trasVolver = await paso();
    ok('su flecha vuelve a la RESERVA, no a «Tus datos»', ! /Tus datos|Pagar/.test(trasVolver) && (await accion(/^Continuar$/).count()) > 0, trasVolver);
    await accion(/^Continuar$/).click();
    await hastaPaso('Pagar');
    await quieta();
    ok('y «Continuar» vuelve a «Pagar» directo, con la sesión', (await paso()).trim() === 'Pagar', await paso());
    // Las formas de pago (`#784`, `#786`): las del arranque, BAJO el botón de pagar (fuera del recibo), en su versión para
    // fondo oscuro (`urls.mark_<id>_ink`: la isla es tinta), en su orden y CARGADAS.
    const marcas = await page.evaluate(async () => {
        const urls = (await (await fetch('/api/v1/sidebar/boot?lang=es')).json()).urls;
        const imgs = [...document.querySelectorAll('[data-isla] ul[aria-label="Formas de pago"] img')];
        const boton = [...document.querySelectorAll('[data-isla] button')].find((b) => /^Pagar .* con tarjeta$/.test(b.textContent.trim()));

        await Promise.all(imgs.map((i) => i.decode().catch(() => null)));

        return {
            boot: Object.keys(urls).filter((k) => /^mark_[a-z]+$/.test(k)).map((k) => urls[`${k}_ink`] ?? urls[k]),
            vistas: imgs.map((i) => (i.naturalWidth > 0 ? i.src : `ROTA ${i.src}`)),
            debajo: imgs.length > 0 && imgs.every((i) => i.getBoundingClientRect().top >= boton.getBoundingClientRect().bottom),
            enElRecibo: document.querySelectorAll('[data-isla-scroll] ul[aria-label="Formas de pago"]').length,
        };
    });
    ok('las formas de pago del arranque, BAJO el botón y en su versión oscura, en su orden y cargadas', JSON.stringify(marcas.boot) === JSON.stringify(marcas.vistas) && marcas.debajo && marcas.enElRecibo === 0, JSON.stringify(marcas));
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

    // ── 3. Un CUMPLEAÑOS con señal desde `/cumpleanos` (y la cuenta SIN teléfono: la fiesta lo pide, `#787`) ──
    limitadoresACero();
    quitarTelefono();
    await page.goto(`${BASE}/cumpleanos`, { waitUntil: 'networkidle' });
    await page.waitForSelector('#p6-edad', { timeout: 20000 });
    const fiesta = await page.evaluate(() => JSON.parse(document.querySelector('[data-jw-calculadora-fiesta]')?.dataset.jwCalculadoraFiesta ?? 'null'));
    await page.evaluate(() => document.querySelector('#calcula')?.scrollIntoView());
    await quieta();
    // Cinco años: el pack de los pequeños. El día, el primero con hueco que la página ofrece (sin ninguno en ocho semanas,
    // la fiesta no se puede comprar aquí: se dice en vez de probar otra cosa).
    await page.locator('#p6-edad button', { hasText: /^5$/ }).click();
    await quieta();
    if (await page.locator('[data-jw-calculadora-dia]').count() === 0) throw new Error('sin días de fiesta con hueco en ocho semanas: la compra de la fiesta no se puede probar');
    await page.locator('[data-jw-calculadora-dia]').first().click();
    await page.waitForSelector('#p6-hora [data-hora]:not([disabled])', { timeout: 15000 });
    await page.locator('#p6-hora [data-hora]:not([disabled])').first().click();
    await espera(() => /Hoy pagas la señal/.test(document.querySelector('[data-jw-calculadora-lado]')?.textContent ?? '') && /Total/.test(document.querySelector('[data-jw-calculadora-lado]')?.textContent ?? ''), null, 15000);
    await quieta();
    const eco = await page.locator('#p6-edad').innerText();
    const packFiesta = (fiesta?.packs ?? []).find((p) => eco.includes(p.name));
    const ladoFiesta = await page.locator('[data-jw-calculadora-lado]').innerText();
    ok('la fiesta: la edad elige el pack de su tramo y «Hoy pagas la señal 50 €»', packFiesta?.guest_age_min <= 5 && /Hoy pagas la señal\s*50\s€/.test(ladoFiesta), `${packFiesta?.name ?? eco.slice(-40)} · ${ladoFiesta.match(/Hoy pagas[^\n]*\n?[^\n]*/)?.[0] ?? ''}`);
    await page.locator('[data-jw-calculadora-lado] [data-isla-cta]').click();
    await hastaPaso('Tus datos');
    ok('la compra abre con los niños en el mínimo del pack', new RegExp(`${packFiesta?.min_quantity} niños`).test(await debajo()), await debajo());
    await quieta();
    ok('una fiesta con la cuenta SIN teléfono: «Tus datos» se lo pide, y solo eso (`#787`)', await page.locator('#pjc-tel').count() === 1 && /^Hola/.test(await page.locator('[data-isla-scroll] h1').innerText()), (await cuerpo()).slice(0, 90));
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
    devolverTelefono();
    tinker(`$o = ${ULTIMO}; if ($o && $o->status === 'pending') { $o->expires_at = now()->subMinute(); $o->save(); }`);
    execFileSync('php', ['artisan', 'orders:expire'], { encoding: 'utf8' });
    await navegador.close();
}

ok('sin errores en la consola', errores.length === 0, errores.join(' | '));
console.log(filas.join('\n'));
const bien = filas.filter((f) => f.startsWith('✓')).length;
const todas = filas.filter((f) => /^[✓✗]/.test(f)).length;
console.log(`\n${bien}/${todas}`);
process.exit(bien === todas ? 0 : 1);
