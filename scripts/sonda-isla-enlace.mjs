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
    + ` $sv->personalize($i, ['honoree_name' => 'Noa', 'honoree_age' => 6, 'theme' => 'confeti', 'host_line' => 'Te invita la familia de Noa']); echo $i->fresh()->token;`).split('\n').pop();

const desmontarCerrada = () => tinker(`$o = App\\Domain\\Booking\\Models\\Order::where('code', '${CODIGO_CERRADA}')->first(); if ($o) { foreach ($o->items as $it) { App\\Domain\\Booking\\Models\\PartyInvitation::where('order_item_id', $it->id)->each(fn ($i) => $i->replies()->delete() && $i->delete()); } $o->items()->delete(); $o->delete(); } echo 'ok';`);

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

async function recorrer(navegador, ventana, informe, cerrada) {
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
        await foto(page, '2-recibo');
        const enlaces = await page.evaluate(() => document.querySelector('[data-receipt] .inv-enlaces')?.getBoundingClientRect().bottom ?? 0);
        await page.evaluate((y) => window.scrollTo(0, y + 40), enlaces);
        await page.waitForTimeout(1200);
        check('al bajar, «Añadir al calendario · Sáb 3 oct · 17:00», en secundaria',
            (await islaEn(page))?.sale === '0' && (await texto(calendario.locator('[data-isla-label]'))) === 'Añadir al calendario'
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
        await foto(page, '3-cerrada');
        await contexto.close();
    }

    check('la consola, limpia', errores.length === 0, errores.slice(0, 5).join(' | '));
    check('ninguna respuesta en rojo', malas.length === 0, malas.slice(0, 5).join(' | '));
}

await mkdir(SALIDA, { recursive: true });
borrarLasDeLaSonda();
const cerrada = montarCerrada();

const informe = { cuando: new Date().toISOString(), anchos: [] };
const navegador = await chromium.launch();

try {
    for (const ventana of VENTANAS) {
        const ancho = { ancho: ventana.width, checks: [] };
        informe.anchos.push(ancho);
        await recorrer(navegador, ventana, ancho, cerrada).catch((e) => ancho.checks.push({ nombre: 'el recorrido no se cortó', ok: false, detalle: e.message.split('\n')[0] }));
    }
} finally {
    await navegador.close();
    informe.borradas = borrarLasDeLaSonda();
    desmontarCerrada();
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
