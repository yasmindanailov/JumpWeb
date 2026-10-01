/**
 * SONDA DE LA ISLA DE LAS PÁGINAS DE ENLACE (`fiesta-sistema-nuevo.md` §4.18, `#814`; `LinkIsland` del zip (6)). Lo que la
 * suite no ve: la isla FIJA abajo y su hueco al final del contenido, que llegue tras el confeti, que se quede al escribir
 * en su campo, que se aparte con el vídeo abierto, que sin JavaScript vaya en el flujo, y en el recibo «Añadir al
 * calendario» solo cuando la tarjeta ya no lo enseña. En local, a 390 y a 1280, con fotos.
 *
 * ── L1 · LA INVITACIÓN ───────────────────────────────────────────────────────────────────────────────────────────
 *   1. Abierta (`JW-OJO-F8`): sale por abajo nada más cargar y llega tras el confeti; fija abajo (a 1280, centrada a 560);
 *      la única que flota; al final del scroll, el pie queda ENCIMA de ella (el hueco); con el foco en su campo, se queda;
 *      con «Ver el parque» abierto, se aparta y vuelve al cerrarlo. Sin JavaScript, en el flujo y sin hueco.
 *   2. El recibo de un «sí»: con la tarjeta enseñando «Añadir al calendario», la isla no lo repite; al bajar, «Añadir al
 *      calendario · Sáb 3 oct · 17:00»; tocada, se va.
 *   3. Pasado el plazo: la línea, sin respuesta (una fiesta de HOY que la sonda monta y quita, `JW-SONDA-ISLA-C`).
 *   4. La consola, limpia, y ninguna respuesta en rojo.
 *
 * ── L2 · LA LISTA ── el único Guardar (naranja con algo que guardar, con lo exacto), el aviso, la línea al pie y «Enviar
 *   por WhatsApp» (con su propia lista sin enviar, `JW-SONDA-ISLA-E`); F8, solo para mirar.
 *
 * ── L3 · LA AUTORIZACIÓN ── la de `JW-SONDA-ISLA-E`: el único «Firmar», con lo que falta por su nombre hasta «Todo listo»;
 *   tocada con algo pendiente, el servidor lo marca y el foco va al primero (sin escribir nada); firmando, ocupada y sin
 *   doble envío (el envío se RETIENE y se aborta: una firma es una prueba encadenada que no se borra); sin JavaScript, en
 *   el flujo justo tras el formulario.
 *
 * ⚠️ SOLO EN LOCAL. Las respuestas que crea («Sonda Isla …», en `JW-OJO-F8`) se BORRAN al terminar (y al empezar, las de una
 *    corrida cortada). Fotos: `storage/app/audit/sonda-isla-enlace-<ancho>-<paso>.png`.
 *
 *   docker compose exec -u sail -T laravel.test node scripts/sonda-isla-enlace.mjs
 *
 * Sale con 1 si algún punto falla; el informe, en `storage/app/audit/sonda-isla-enlace.json`.
 */
/* global document, window, getComputedStyle -- dentro de `evaluate`, el navegador */
import process from 'node:process';
import { execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const SALIDA = 'storage/app/audit';
const ABIERTA = process.env.SONDA_INV ?? 'bKOAn3L9HPQT';
const CODIGO_CERRADA = 'JW-SONDA-ISLA-C';
const VENTANAS = [{ width: 390, height: 844, hasTouch: true, isMobile: true }, { width: 1280, height: 900 }];

const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL: contesta invitaciones'); process.exit(1); }

/** Las respuestas de la sonda, fuera (con sus autorizaciones, si las hubiera). Devuelve cuántas. */
const borrarLasDeLaSonda = () => Number(tinker('$r = App\\Domain\\Booking\\Models\\InvitationReply::where(\'child_name\', \'like\', \'Sonda Isla%\')->get(); $r->each(fn ($x) => $x->delete()); echo $r->count();'));

/**
 * Una fiesta de HOY a las 20:00 de la cuenta de sondas: su plazo (la víspera) ya pasó y aún no se ha celebrado, que es
 * cuando la invitación enseña la LÍNEA. Ninguna de las montadas para el ojo está ahora en ese punto. Devuelve su token.
 */
const montarCerrada = () => tinker(`$h = App\\Domain\\Identity\\Models\\User::where('email', 'probe-card@jumpweb.test')->firstOrFail(); $p = App\\Domain\\Booking\\Models\\TicketType::findOrFail(105);`
    + ` $o = App\\Domain\\Booking\\Models\\Order::where('code', '${CODIGO_CERRADA}')->first();`
    + ` if ($o === null) { $s = App\\Domain\\Booking\\Models\\Slot::firstOrCreate(['zone_id' => $p->zone_id, 'date' => App\\Domain\\Platform\\Services\\DisplayTime::today()->toDateString(), 'start_time' => '20:00:00'], ['end_time' => '22:00:00', 'capacity' => 200, 'online_capacity' => 200]);`
    + ` $o = App\\Domain\\Booking\\Models\\Order::create(['user_id' => $h->id, 'code' => '${CODIGO_CERRADA}', 'status' => App\\Domain\\Booking\\Models\\Order::STATUS_PAID, 'subtotal' => 6 * 1695, 'tax' => 0, 'total' => 6 * 1695, 'currency' => 'EUR', 'paid_at' => now()]);`
    + ` $o->items()->create(['ticket_type_id' => $p->id, 'slot_id' => $s->id, 'quantity' => 6, 'unit_price' => 1695, 'seats' => 6, 'event_data' => ['celebrant' => 'Noa', 'age' => 6]]); }`
    + ` $r = $o->items()->whereNull('parent_item_id')->firstOrFail(); $sv = app(App\\Domain\\Booking\\Services\\PartyInvitations::class); $i = $sv->forReservation($r);`
    + ` $sv->personalize($i, ['honoree_name' => 'Noa', 'honoree_age' => 6, 'theme' => 'confeti', 'host_line' => 'Te invita la familia de Noa', 'show_host_phone' => true]); echo $i->fresh()->token;`).split('\n').pop();

const desmontar = (codigo) => tinker(`$o = App\\Domain\\Booking\\Models\\Order::where('code', '${codigo}')->first(); if ($o) { foreach ($o->items as $it) { Illuminate\\Support\\Facades\\DB::table('analytics_events')->where('order_id', $o->id)->delete(); App\\Domain\\Booking\\Models\\PartyInvitation::where('order_item_id', $it->id)->each(fn ($i) => $i->replies()->delete() && $i->delete()); } $o->items()->delete(); $o->delete(); } echo 'ok';`);

/**
 * L2: una fiesta de dentro de 5 días con la invitación PERSONALIZADA y SIN ENVIAR (la isla ofrece enviarla) y tres niños en
 * la lista. Devuelve el enlace firmado de su lista, con el host de dentro del contenedor.
 */
const CODIGO_LISTA = 'JW-SONDA-ISLA-E';
const montarLista = () => tinker(`URL::forceRootUrl('${BASE}'); $h = App\\Domain\\Identity\\Models\\User::where('email', 'probe-card@jumpweb.test')->firstOrFail(); $p = App\\Domain\\Booking\\Models\\TicketType::findOrFail(105);`
    + ` $o = App\\Domain\\Booking\\Models\\Order::where('code', '${CODIGO_LISTA}')->first();`
    + ` if ($o === null) { $s = App\\Domain\\Booking\\Models\\Slot::firstOrCreate(['zone_id' => $p->zone_id, 'date' => App\\Domain\\Platform\\Services\\DisplayTime::today()->addDays(5)->toDateString(), 'start_time' => '18:00:00'], ['end_time' => '20:00:00', 'capacity' => 200, 'online_capacity' => 200]);`
    + ` $o = App\\Domain\\Booking\\Models\\Order::create(['user_id' => $h->id, 'code' => '${CODIGO_LISTA}', 'status' => App\\Domain\\Booking\\Models\\Order::STATUS_PAID, 'subtotal' => 8 * 1695, 'tax' => 0, 'total' => 8 * 1695, 'currency' => 'EUR', 'paid_at' => now()]);`
    + ` $o->items()->create(['ticket_type_id' => $p->id, 'slot_id' => $s->id, 'quantity' => 8, 'unit_price' => 1695, 'seats' => 8, 'event_data' => ['celebrant' => 'Noa', 'age' => 6], 'guest_data' => [['name' => 'Mateo'], ['name' => 'Ana'], ['name' => 'Hugo']]]); }`
    + ` $r = $o->items()->whereNull('parent_item_id')->firstOrFail(); $sv = app(App\\Domain\\Booking\\Services\\PartyInvitations::class); $i = $sv->forReservation($r);`
    + ` $sv->personalize($i, ['honoree_name' => 'Noa', 'honoree_age' => 6, 'theme' => 'confeti', 'host_line' => 'Te invita la familia de Noa']); echo $r->fresh()->guestFormSignedUrl();`).split('\n').pop();
const compartidaLista = () => tinker(`$o = App\\Domain\\Booking\\Models\\Order::where('code', '${CODIGO_LISTA}')->first(); $r = $o?->items()->whereNull('parent_item_id')->first(); echo $r && App\\Domain\\Booking\\Models\\PartyInvitation::where('order_item_id', $r->id)->whereNotNull('shared_at')->exists() ? '1' : '0';`);
/** L3: la autorización de la fiesta de la sonda (la de la lista), con el host de dentro del contenedor. */
const autorizacion = () => tinker(`URL::forceRootUrl('${BASE}'); echo App\\Domain\\Booking\\Models\\Order::where('code', '${CODIGO_LISTA}')->firstOrFail()->items()->whereNull('parent_item_id')->firstOrFail()->guardianAuthorizationSignedUrl();`).split('\n').pop();
/** La lista de `JW-OJO-F8` (enviada, con una respuesta por repasar), solo para MIRAR: la sonda no guarda en ella. */
const listaF8 = () => tinker(`URL::forceRootUrl('${BASE}'); echo App\\Domain\\Booking\\Models\\Order::where('code', 'JW-OJO-F8')->firstOrFail()->items()->whereNull('parent_item_id')->firstOrFail()->guestFormSignedUrl();`).split('\n').pop();

const texto = async (loc) => ((await loc.innerText({ timeout: 4000 }).catch(() => '')) ?? '').replace(/\s+/g, ' ').trim();

async function cookies(page) {
    const boton = page.locator('.cookie-consent-root button:visible', { hasText: /^(acept|rechaz)/i }).first();
    if (await boton.isVisible({ timeout: 1500 }).catch(() => false)) await boton.click({ timeout: 3000 }).catch(() => {});
}

/** Dónde está la isla: su caja en la ventana, si está fija, si ha salido y el alto de su hueco. */
const islaEn = (page) => page.evaluate(() => {
    const isla = document.querySelector('[data-isla-enlace]');
    const hueco = document.querySelector('[data-isla-hueco]');
    if (!isla) return null;
    const r = isla.getBoundingClientRect();

    return { top: r.top, bottom: r.bottom, left: r.left, width: r.width, pos: getComputedStyle(isla).position, sale: isla.dataset.sale ?? '', vw: window.innerWidth, vh: window.innerHeight, hueco: hueco ? hueco.getBoundingClientRect().height : -1 };
});

async function recorrer(navegador, ventana, informe, cerrada, lista) {
    const { width, height, ...tacto } = ventana;
    const check = (nombre, ok, detalle = '') => informe.checks.push({ nombre, ok: Boolean(ok), detalle: String(detalle) });
    const errores = [];
    const malas = [];

    const nueva = async (opciones = {}) => {
        const contexto = await navegador.newContext({ viewport: { width, height }, ...tacto, locale: 'es-ES', acceptDownloads: true, ...opciones });
        const page = await contexto.newPage();
        page.setDefaultTimeout(12000);
        page.on('pageerror', (e) => errores.push(e.message.split('\n')[0]));
        page.on('console', (m) => { if (m.type() === 'error' && ! /status of \d{3}|turnstile|challenges\.cloudflare/i.test(m.text())) errores.push(m.text().split('\n')[0]); });
        page.on('response', (r) => {
            const url = new URL(r.url());
            if (url.origin === new URL(BASE).origin && r.status() >= 400 && ! /\.(png|jpe?g|webp|mp4|ico)$/.test(url.pathname)) malas.push(`${r.status()} ${url.pathname}`);
        });

        return { contexto, page };
    };
    const foto = async (page, paso) => {
        await page.mouse.move(0, 0).catch(() => {});
        await page.waitForTimeout(450);
        await page.screenshot({ path: `${SALIDA}/sonda-isla-enlace-${width}-${paso}.png` });
    };

    // ── 1 · La invitación abierta ──────────────────────────────────────────────────────────────────
    {
        const { contexto, page } = await nueva();
        await page.goto(`${BASE}/invitacion/${ABIERTA}`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('html.js', { timeout: 10000 }).catch(() => {});
        const recien = await islaEn(page);
        check('nada más cargar, la isla espera abajo (llega tras el confeti)', recien?.sale === '1', JSON.stringify(recien));
        await page.waitForTimeout(1900);
        await cookies(page);
        const llegada = await islaEn(page);
        check('tras el confeti, llega: fija abajo, a su margen del borde', llegada?.sale === '0' && llegada.pos === 'fixed' && Math.abs(llegada.vh - llegada.bottom - 16) <= 24, JSON.stringify(llegada));
        if (width >= 900) {
            check('en escritorio, centrada y a 560 px', Math.abs(llegada.width - 560) <= 1 && Math.abs(llegada.left + llegada.width / 2 - llegada.vw / 2) <= 1, `${llegada.width} px · izquierda ${llegada.left}`);
        } else {
            check('en el móvil, de borde a borde con su margen', Math.abs(llegada.left - 16) <= 1 && Math.abs(llegada.width - (llegada.vw - 32)) <= 1, `${llegada.width} px`);
        }
        check('la respuesta está DENTRO de la isla, y es la única pieza que flota',
            (await page.locator('[data-isla-enlace] form[data-rsvp] [data-rsvp-si]').count()) === 1 && (await page.locator('form[data-rsvp]').count()) === 1);
        await foto(page, '1-invitacion');

        await page.evaluate(() => window.scrollTo(0, document.scrollingElement.scrollHeight));
        await page.waitForTimeout(700);
        const abajo = await islaEn(page);
        const pie = await page.evaluate(() => { const els = document.querySelectorAll('.inv > *:not([data-isla-hueco]):not([data-isla-enlace])'); const ult = els[els.length - 1]; return ult ? ult.getBoundingClientRect().bottom : null; });
        check('al final del scroll, el hueco deja el pie ENCIMA de la isla', abajo.hueco > 0 && pie !== null && pie <= abajo.top + 1, `pie ${pie} · isla ${abajo.top} · hueco ${abajo.hueco}`);
        await foto(page, '1b-final');

        await page.locator('#rsvp-nino').focus();
        await page.waitForTimeout(500);
        check('con el foco en SU campo, la isla se queda', (await islaEn(page))?.sale === '0');
        await page.locator('#rsvp-nino').fill('Sonda');
        await foto(page, '1c-escribiendo');
        await page.locator('#rsvp-nino').fill('');
        await page.locator('#rsvp-nino').blur();

        const verParque = page.locator('[data-visor-abrir]');
        if (await verParque.count()) {
            await page.evaluate(() => window.scrollTo(0, 0));
            await verParque.click();
            await page.waitForTimeout(700);
            check('con «Ver el parque» abierto, la isla se aparta', (await islaEn(page))?.sale === '1');
            await page.keyboard.press('Escape');
            await page.waitForTimeout(800);
            check('al cerrarlo, vuelve', (await islaEn(page))?.sale === '0');
        }
        await contexto.close();
    }

    // ── 1 · Sin JavaScript ──────────────────────────────────────────────────────────────────────────
    {
        const { contexto, page } = await nueva({ javaScriptEnabled: false });
        await page.goto(`${BASE}/invitacion/${ABIERTA}`, { waitUntil: 'domcontentloaded' });
        const sin = await islaEn(page);
        check('sin JavaScript, la isla va EN EL FLUJO (y contesta igual) y su hueco no ocupa nada',
            sin !== null && sin.pos !== 'fixed' && sin.hueco === 0 && (await page.locator('form[data-rsvp] button[name="attending"]').count()) === 2, JSON.stringify(sin));
        await contexto.close();
    }

    // ── 2 · El recibo de un «sí»: «Añadir al calendario» ───────────────────────────────────────────
    {
        const { contexto, page } = await nueva();
        await page.goto(`${BASE}/invitacion/${ABIERTA}`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1900);
        await cookies(page);
        await page.locator('#rsvp-nino').fill(`Sonda Isla ${width} ${Date.now()}`);
        await page.locator('[data-rsvp-si]').click();
        await page.waitForSelector('[data-receipt="si"]', { timeout: 15000 });
        await page.waitForTimeout(900);
        const calendario = page.locator('[data-receipt-island-calendar]');
        check('en el recibo, con la tarjeta enseñando el calendario, la isla NO lo repite', (await calendario.count()) === 1 && (await islaEn(page))?.sale === '1');
        check('el recibo CONSERVA su «Firmar» (L3: allí la isla lleva el calendario)', (await page.locator('form[data-receipt-firma] [data-firma-boton]').count()) === 1);
        const huecoArriba = (await islaEn(page))?.hueco;
        check('…y sin cara, su hueco es 0 (como el diseño: la página no se alarga por debajo)', huecoArriba === 0, `hueco ${huecoArriba}`);
        await foto(page, '2-recibo');
        const enlaces = await page.evaluate(() => document.querySelector('[data-receipt] .inv-enlaces')?.getBoundingClientRect().bottom ?? 0);
        await page.evaluate((y) => window.scrollTo(0, y + 40), enlaces);
        await page.waitForTimeout(1200);
        check('al bajar, «Añadir al calendario · Sáb 3 oct · 17:00», en secundaria, y su hueco vuelve',
            (await islaEn(page))?.sale === '0' && (await islaEn(page))?.hueco > 0 && (await texto(calendario.locator('[data-isla-label]'))) === 'Añadir al calendario'
                && (await texto(calendario.locator('[data-isla-sub-texto]'))) === 'Sáb 3 oct · 17:00' && ! (await calendario.evaluate((el) => el.classList.contains('fi-isla-barra--primary'))),
            `${await texto(calendario)}`);
        await foto(page, '2b-calendario');
        const [descarga] = await Promise.all([page.waitForEvent('download', { timeout: 8000 }).catch(() => null), calendario.click()]);
        await page.waitForTimeout(800);
        check('tocada, se descarga el .ics y la isla se va', descarga !== null && /\.ics$/.test(descarga.suggestedFilename()) && (await islaEn(page))?.sale === '1', descarga?.suggestedFilename() ?? 'sin descarga');
        await contexto.close();
    }

    // ── 3 · Pasado el plazo: la línea ──────────────────────────────────────────────────────────────
    {
        const { contexto, page } = await nueva();
        await page.goto(`${BASE}/invitacion/${cerrada}`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1900);
        await cookies(page);
        const linea = page.locator('[data-isla-enlace] [data-isla-cara="linea"] [role="status"]');
        check('pasado el plazo, la isla es la LÍNEA y no ofrece responder', (await linea.count()) === 1 && ! (await page.locator('[data-rsvp-si]').count()), await texto(linea));
        // Como el `Line` del diseño: UNA fila; el texto se parte y «Llamar» no baja (medido en la pasada `#768`: con
        // `flex-wrap`, caía debajo del texto).
        const fila = await page.evaluate(() => {
            const l = document.querySelector('[data-isla-enlace] [data-isla-cara="linea"] [role="status"]');
            const b = l?.querySelector('[data-invitation-call]');
            const t = b?.previousElementSibling;
            if (!b || !t) return null;
            const rb = b.getBoundingClientRect(); const rt = t.getBoundingClientRect(); const rl = l.getBoundingClientRect();
            return { misma: rb.top + rb.height / 2 > rt.top && rb.top + rb.height / 2 < rt.bottom, dentro: rb.right <= rl.right + 0.5, alto: rl.height };
        });
        check('…en UNA fila: «Llamar» a la altura del texto, dentro de la isla', fila?.misma && fila.dentro, JSON.stringify(fila));
        await foto(page, '3-cerrada');
        await contexto.close();
    }

    // ── L2 · La lista: el único Guardar, el aviso, la línea de lo guardado y «Enviar por WhatsApp» ─────────
    const cara = (page) => page.evaluate(() => document.querySelector('[data-lista-isla] [data-isla-cara]')?.dataset.islaCara ?? '');
    const subIsla = (page) => texto(page.locator('[data-lista-isla] [data-isla-cara] [data-isla-sub-texto]'));
    {
        const { contexto, page } = await nueva();
        await page.route('https://wa.me/**', (r) => r.abort());
        await page.goto(lista.e, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('html.js', { timeout: 10000 }).catch(() => {});
        await cookies(page);
        await page.waitForTimeout(900);
        check('lista sin enviar, arriba (la zona 1 ya ofrece enviar): la isla NO lo repite', (await islaEn(page))?.sale === '1', await cara(page));
        await foto(page, '4-lista-arriba');
        await page.evaluate(() => window.scrollTo(0, document.scrollingElement.scrollHeight));
        await page.waitForTimeout(1300);
        const enviar = page.locator('[data-lista-isla] [data-isla-cara="enviar"] a[data-envio="whatsapp"]');
        check('al bajar, sin nada que guardar: «Enviar por WhatsApp · La invitación de Noa», en secundaria',
            (await islaEn(page))?.sale === '0' && (await enviar.count()) === 1 && (await subIsla(page)) === 'La invitación de Noa'
                && ! (await enviar.evaluate((a) => a.classList.contains('fi-isla-barra--primary'))), `${await cara(page)} · ${await subIsla(page)}`);
        await foto(page, '4b-lista-enviar');

        // Un cambio: la edad de la ficha abierta. Escribiendo, la isla se aparta; al salir, el Guardar en naranja con lo exacto.
        const edad = page.locator('input[id$="-edad"]:visible').first();
        await edad.fill('7');
        await page.waitForTimeout(500);
        check('escribiendo en un campo de la página, la isla se aparta', (await islaEn(page))?.sale === '1');
        await edad.blur();
        await page.waitForTimeout(800);
        const guardar = page.locator('[data-lista-isla] [data-isla-cara="guardar"] button[type="submit"][form="fiesta-form"]');
        check('al salir del campo: el ÚNICO Guardar, en naranja, con «1 cambio · borrador en este móvil»',
            (await islaEn(page))?.sale === '0' && (await guardar.count()) === 1 && (await guardar.evaluate((b) => b.classList.contains('fi-isla-barra--primary')))
                && (await subIsla(page)) === '1 cambio · borrador en este móvil', `${await cara(page)} · ${await subIsla(page)}`);
        await foto(page, '4c-lista-guardar');

        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => {}), guardar.click()]);
        await page.waitForSelector('html.js', { timeout: 10000 }).catch(() => {});
        await page.waitForTimeout(500);
        const aviso = await texto(page.locator('[data-lista-isla] [data-isla-cara="aviso"] [data-isla-label]'));
        check('guardado: el aviso «Guardado hoy a las…» en la isla, un momento', /^Guardado hoy a las \d{2}:\d{2}$/.test(aviso), aviso);
        check('…y NO también arriba: «Formulario guardado» queda solo para sin JavaScript (regla 3)',
            (await page.locator('[data-aviso-sin-js]').count()) === 1 && ! (await page.locator('[data-aviso-sin-js]').isVisible()));
        await foto(page, '4d-lista-guardado');
        await page.waitForTimeout(3200);
        check('y después, el aviso se va (sin nada que guardar ni nada que ofrecer a la vista, la isla se aparta)', (await cara(page)) !== 'aviso', await cara(page));
        check('al pie, la línea de lo guardado', /^Guardado hoy a las \d{2}:\d{2}$/.test(await texto(page.locator('[data-guardado-linea]'))), await texto(page.locator('[data-guardado-linea]')));
        // En la BASE (el primer campo de edad de la página recargada no tiene por qué ser el de la ficha editada).
        const edades = tinker(`$o = App\\Domain\\Booking\\Models\\Order::where('code', '${CODIGO_LISTA}')->first(); echo json_encode(array_column((array) $o?->items()->whereNull('parent_item_id')->first()?->guest_data, 'age'));`);
        check('y lo guardado es de verdad: la edad está en la lista guardada', /"7"|\b7\b/.test(edades), edades);

        await page.evaluate(() => window.scrollTo(0, document.scrollingElement.scrollHeight));
        await page.waitForTimeout(1300);
        await enviar.click().catch(() => {});
        await page.waitForTimeout(900);
        check('«Enviar por WhatsApp» desde la isla: la isla se va y la invitación queda ENVIADA', (await islaEn(page))?.sale === '1' && compartidaLista() === '1', `compartida ${compartidaLista()}`);
        await contexto.close();
    }
    {
        const { contexto, page } = await nueva({ javaScriptEnabled: false });
        await page.goto(lista.e, { waitUntil: 'domcontentloaded' });
        const sin = await islaEn(page);
        check('lista sin JavaScript: la isla en el flujo, con su Guardar de enviar, y sin hueco',
            sin !== null && sin.pos !== 'fixed' && sin.hueco === 0 && (await page.locator('[data-lista-isla] button[type="submit"][form="fiesta-form"]').count()) === 1, JSON.stringify(sin));
        await contexto.close();
    }
    {
        const { contexto, page } = await nueva();
        await page.goto(lista.f8, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('html.js', { timeout: 10000 }).catch(() => {});
        await cookies(page);
        await page.waitForTimeout(900);
        const repasar = Number(await texto(page.locator('[data-repasar-n]')) || 0);
        const esperado = repasar === 1 ? '1 respuesta por repasar' : `${repasar} respuestas por repasar`;
        check('con respuestas por repasar (F8, enviada): el Guardar en naranja lo dice, sin tocar nada', repasar > 0 && (await cara(page)) === 'guardar' && (await subIsla(page)) === esperado, `${repasar} · ${await subIsla(page)}`);
        await foto(page, '4e-lista-repasar');
        await contexto.close();
    }

    // ── L3 · La autorización: el único «Firmar», con lo que falta por su nombre ─────────────────────────────────
    // ⚠️ La sonda NO firma: una firma es una prueba encadenada (`waiver_signatures`) que no se borra. El envío que falla por
    //    un campo no escribe nada (se mira en la base); el último envío se RETIENE para ver «Firmando» y se aborta.
    const subFirma = (page) => texto(page.locator('[data-aut-isla] [data-isla-sub-texto]'));
    {
        const { contexto, page } = await nueva();
        await page.goto(lista.aut, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('html.js', { timeout: 10000 }).catch(() => {});
        await cookies(page);
        await page.waitForTimeout(700);
        const firmar = page.locator('[data-aut-isla] button[type="submit"][form="aut-form"]');
        const llegada = await islaEn(page);
        check('autorización: la isla es el ÚNICO «Firmar» —fija abajo, en naranja— y el formulario no pinta el suyo',
            llegada?.sale === '0' && llegada.pos === 'fixed' && (await firmar.count()) === 1 && (await firmar.evaluate((b) => b.classList.contains('fi-isla-barra--primary')))
                && (await page.locator('#aut-form button[type="submit"]').count()) === 0 && (await page.locator('button[type="submit"]:visible').count()) === 1, JSON.stringify(llegada));
        check('vacía: «Faltan 6 datos y la casilla»', (await subFirma(page)) === 'Faltan 6 datos y la casilla', await subFirma(page));
        await foto(page, '5-autorizacion');

        const sufijo = `${width} ${Date.now()}`;
        await page.locator('#aut-ninoNombre').fill('Sonda Isla');
        await page.waitForTimeout(400);
        check('escribiendo en la firma, la isla se aparta', (await islaEn(page))?.sale === '1');
        await page.locator('#aut-ninoApellidos').fill(sufijo);
        await page.locator('#aut-nacimiento').fill('2019-05-04');
        await page.locator('#aut-nombre').fill('Ana Sonda');
        await page.evaluate(() => document.activeElement?.blur());
        await page.waitForTimeout(700);
        check('con lo del niño y tu nombre: «Faltan 2 datos y la casilla» (la relación y el teléfono)', (await islaEn(page))?.sale === '0' && (await subFirma(page)) === 'Faltan 2 datos y la casilla', await subFirma(page));
        await foto(page, '5b-faltan');

        // Tocarla con algo pendiente: el servidor lo marca con palabras, el foco va al primero y no se escribe nada.
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => {}), firmar.click()]);
        await page.waitForSelector('html.js', { timeout: 10000 }).catch(() => {});
        await page.waitForTimeout(700);
        const marcado = await page.evaluate(() => ({ relacion: Boolean(document.querySelector('.pz-selector--error #aut-relacion')), telefono: Boolean(document.querySelector('.pz-campo--error #aut-telefono')), foco: document.activeElement?.id ?? '', nino: document.querySelector('#aut-ninoNombre')?.value ?? '' }));
        const escritas = tinker(`echo App\\Domain\\Identity\\Models\\GuardianAuthorization::where('minor_surname', '${sufijo}')->count();`);
        check('tocada con lo pendiente: lo marca, el foco va al primero (la relación), lo escrito sigue y NO se firma nada',
            marcado.relacion && marcado.telefono && marcado.foco === 'aut-relacion' && marcado.nino === 'Sonda Isla' && escritas === '0', `${JSON.stringify(marcado)} · firmas ${escritas}`);
        await foto(page, '5c-marcado');
        const relacion = await page.locator('#aut-relacion option').nth(1).getAttribute('value');
        await page.locator('#aut-relacion').selectOption(relacion);
        await page.evaluate(() => document.activeElement?.blur());
        await page.waitForTimeout(700);
        const selector = await page.evaluate(() => { const s = document.querySelector('#aut-relacion'); const caja = s.closest('.pz-selector'); // ⚠️ El borde de un campo de texto es el de su CAJA (`.pz-campo__caja`): el `input` no lleva (medido).
            const borde = (el) => getComputedStyle(el).borderTopColor;
            return { rojo: caja.classList.contains('pz-selector--error'), texto: Boolean(caja.querySelector('[data-pz-error]')), borde: borde(s), sano: borde(document.querySelector('#aut-correo').closest('.pz-campo__caja')), enRojo: borde(document.querySelector('#aut-telefono').closest('.pz-campo__caja')) }; });
        check('elegida la relación, su error se va (su borde, el de un campo sano) y la isla: «Faltan tu teléfono y la casilla»',
            ! selector.rojo && ! selector.texto && selector.borde === selector.sano && selector.borde !== selector.enRojo && (await subFirma(page)) === 'Faltan tu teléfono y la casilla', `${await subFirma(page)} · ${JSON.stringify(selector)}`);
        await foto(page, '5c2-faltan-dos');

        await page.locator('#aut-telefono').fill('611204118');
        await page.locator('#aut-telefono').blur();
        await page.waitForTimeout(500);
        const errorDe = (sel) => page.evaluate((s) => { const el = document.querySelector(s); const caja = el?.closest('.pz-campo, .pz-casilla'); return { rojo: Boolean(caja?.matches('.pz-campo--error, .pz-casilla--error')), texto: Boolean(caja?.querySelector('.pz-campo__error, .pz-casilla__error')), invalido: el?.getAttribute('aria-invalid') ?? '' }; }, sel);
        const casillaAntes = await errorDe('#aut-casilla');
        const telefono = await errorDe('#aut-telefono');
        check('con el teléfono: «Falta la casilla», y el error del teléfono se va (el de la casilla, aún no)',
            (await subFirma(page)) === 'Falta la casilla' && ! telefono.rojo && ! telefono.texto && telefono.invalido === '' && casillaAntes.rojo && casillaAntes.texto, `${await subFirma(page)} · ${JSON.stringify({ telefono, casillaAntes })}`);
        await page.locator('#aut-casilla').check({ force: true });
        await page.waitForTimeout(500);
        const casilla = await errorDe('#aut-casilla');
        check('marcada la casilla, su error se va', ! casilla.rojo && ! casilla.texto, JSON.stringify(casilla));
        const punto = await page.evaluate(() => { const p = document.querySelector('[data-aut-isla] [data-isla-punto]'); return p && ! p.hidden ? getComputedStyle(p).backgroundColor : ''; });
        check('todo puesto: «Todo listo», con el punto lima', (await subFirma(page)) === 'Todo listo' && punto !== '' && punto !== 'rgba(0, 0, 0, 0)', `${await subFirma(page)} · ${punto}`);
        await foto(page, '5d-todo-listo');

        // El envío, CANCELADO desde la página DESPUÉS de que actúe la firma (un `submit` en `window` corre tras los del
        // formulario): cuenta los que la página dejó salir. ⚠️ Retener el POST con `page.route` no sirve: con una navegación
        // pendiente, Playwright no evalúa nada en la página (medido: se cuelga hasta el tiempo límite).
        await page.evaluate(() => {
            window.sondaEnvios = 0;
            window.addEventListener('submit', (e) => { if (! e.defaultPrevented) window.sondaEnvios += 1; e.preventDefault(); });
        });
        await firmar.click();
        await page.waitForTimeout(300);
        await firmar.click({ force: true });
        await page.waitForTimeout(400);
        const ocupada = await firmar.evaluate((b) => ({ ocupada: b.classList.contains('fi-isla-barra--ocupada'), busy: b.getAttribute('aria-busy'), envios: window.sondaEnvios }));
        check('firmando: «Firmando», ocupada, y un segundo toque no envía otra vez',
            (await texto(firmar.locator('[data-isla-label]'))) === 'Firmando' && ocupada.ocupada && ocupada.busy === 'true' && ocupada.envios === 1, `${await texto(firmar)} · ${JSON.stringify(ocupada)}`);
        await foto(page, '5e-firmando');
        await contexto.close();
    }
    {
        const { contexto, page } = await nueva({ javaScriptEnabled: false });
        await page.goto(lista.aut, { waitUntil: 'domcontentloaded' });
        const sin = await islaEn(page);
        const tras = await page.evaluate(() => document.querySelector('#aut-form')?.nextElementSibling?.hasAttribute('data-isla-enlace') ?? false);
        check('autorización sin JavaScript: la isla en el flujo, JUSTO tras el formulario, con su «Firmar» que lo envía, y sin hueco',
            sin !== null && sin.pos !== 'fixed' && sin.hueco === 0 && tras && (await page.locator('[data-aut-isla] button[type="submit"][form="aut-form"]').count()) === 1, JSON.stringify(sin));
        await page.locator('[data-aut-isla]').scrollIntoViewIfNeeded();
        await foto(page, '5f-sin-js');
        await contexto.close();
    }

    check('la consola, limpia', errores.length === 0, errores.slice(0, 5).join(' | '));
    check('ninguna respuesta en rojo', malas.length === 0, malas.slice(0, 5).join(' | '));
}

await mkdir(SALIDA, { recursive: true });
borrarLasDeLaSonda();
const cerrada = montarCerrada();
const lista = { e: montarLista(), f8: listaF8() };
lista.aut = autorizacion();

const informe = { cuando: new Date().toISOString(), anchos: [] };
const navegador = await chromium.launch();

try {
    for (const ventana of VENTANAS) {
        const ancho = { ancho: ventana.width, checks: [] };
        informe.anchos.push(ancho);
        await recorrer(navegador, ventana, ancho, cerrada, lista).catch((e) => ancho.checks.push({ nombre: 'el recorrido no se cortó', ok: false, detalle: e.message.split('\n')[0] }));
        // La lista de la sonda vuelve a estar SIN ENVIAR para el ancho siguiente (el paso de enviar la marca enviada).
        desmontar(CODIGO_LISTA);
        lista.e = montarLista();
        lista.aut = autorizacion();
    }
} finally {
    await navegador.close();
    informe.borradas = borrarLasDeLaSonda();
    desmontar(CODIGO_CERRADA);
    desmontar(CODIGO_LISTA);
}

await writeFile(`${SALIDA}/sonda-isla-enlace.json`, `${JSON.stringify(informe, null, 2)}\n`);

let fallos = 0;
for (const { ancho, checks } of informe.anchos) {
    console.log(`── ${ancho} px`);
    for (const { nombre, ok, detalle } of checks) {
        if (! ok) fallos += 1;
        console.log(`  ${ok ? '✓' : '✗'} ${nombre}${! ok && detalle ? `  → ${detalle}` : ''}`);
    }
}
console.log(`\n${fallos === 0 ? '✓' : '✗'} ${fallos} fallos · ${informe.borradas} respuestas de la sonda borradas · ${SALIDA}/sonda-isla-enlace.json`);
process.exit(fallos === 0 ? 0 : 1);
