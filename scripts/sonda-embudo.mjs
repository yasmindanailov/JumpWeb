/**
 * SONDA DEL EMBUDO — la TRAZA de una compra en el cajón: cada pantalla y cada petición a `/api/v1`, en
 * orden, para comparar dos builds (`DECISIONES #691`).
 *
 * Nace con la T3d de la landing nueva: la secuencia de compra salió de `sections/PurchaseSection.vue` a
 * `usePurchaseFlow.js` «sin cambiar una conducta», y eso se DEMUESTRA así —misma traza antes y después—,
 * no leyendo el diff. Sus hermanas miden otras cosas: `sonda-cajon.mjs` la letra y el reflujo de cada
 * pantalla, `sonda-geometria.mjs` lo público.
 *
 * ── EL RECORRIDO ─────────────────────────────────────────────────────────────────────────────────
 *   `/entradas` (el cajón nace abierto) → producto → día → hora → a la cesta → RECARGA (la cesta se
 *   restaura y el cajón abre en ella) → «ir a pagar» sin sesión (paso 5) → entrar con la cuenta de sonda
 *   → pagar → la salida al banco → la vuelta SIN datos (paso 11, con un sondeo) → el banco dice que no
 *   → la vuelta de rechazo (paso 10, con su motivo y su hora) → reintentar → la salida al banco.
 *
 * ⚠️⚠️ **SOLO EN LOCAL, y lo comprueba**: crea un pedido de verdad, marca su pago como DENEGADO (como lo
 * haría la notificación de Redsys: `status = failed` y `Ds_Response` en `raw_response`) y al terminar lo
 * caduca con `orders:expire`, que devuelve la plaza. La pasarela no se toca: toda petición a Redsys se
 * contesta con una página vacía y queda anotada.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   Chromium, una vez por contenedor (con la CLI del PROYECTO; ver `sonda-cajon.mjs`):
 *     docker compose exec -u sail -T laravel.test node node_modules/playwright-core/cli.js install chromium
 *   Y la traza, contra el puerto 80 del propio contenedor (sin puente):
 *     docker compose exec -u sail -T laravel.test node scripts/sonda-embudo.mjs antes
 *     … el cambio y `npm run build` …
 *     docker compose exec -u sail -T laravel.test node scripts/sonda-embudo.mjs despues
 *     diff storage/app/audit/embudo-antes.txt storage/app/audit/embudo-despues.txt
 *
 * ⚠️ El código del pedido cambia en cada corrida y se escribe `{pedido}`; lo demás —productos, días,
 * horas— sale de la BD local y es el mismo en dos corridas seguidas. Un diff vacío es la prueba.
 * ⚠️ Un 500 al abrir con «Deadlock found» en `storage/logs/laravel.log` NO es del cajón: es la caché
 * en BD de local (`CACHE_STORE=database`), donde el limitador escribe con tres peticiones en paralelo.
 * Se vuelve a correr (medido el 2026-09-24: una corrida de cuatro).
 */
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ETIQUETA = process.argv[2] ?? 'antes';
const SALIDA = 'storage/app/audit';

/** La cuenta de sonda (memoria del proyecto; no está en el repo ni en un seeder). */
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' };

const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') {
    console.error('✗ la sonda del embudo solo corre en LOCAL: crea un pedido y marca su pago como denegado');
    process.exit(1);
}

/** El último pedido de la cuenta de sonda, en PHP (se reutiliza en las dos escrituras). */
const ULTIMO = `App\\Domain\\Booking\\Models\\Order::whereHas('user', fn ($q) => $q->where('email', '${CLIENTE.email}'))->latest('id')->first()`;

// ⚠️ Reservar y reintentar comparten limitador: 3 por minuto y titular (`ReservationAdmissionPolicy`).
// Cada corrida gasta dos, así que dos corridas seguidas se pisaban y la segunda acababa en un 429 que
// no es del código que se compara. Se parte de cero, y es la ÚNICA escritura previa.
tinker(`Illuminate\\Support\\Facades\\RateLimiter::clear('reservation-confirm:'.App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->value('id'));`);

const traza = [];
const anota = (linea) => traza.push(linea);

/** La ruta de la API sin lo que cambia de una corrida a otra: el código del pedido (`quote` no lo es). */
const normaliza = (url) => url.pathname.replace(/\/orders\/(?!quote$)[^/]+/, '/orders/{pedido}') + url.search;

const navegador = await chromium.launch();
const contexto = await navegador.newContext({ viewport: { width: 1280, height: 900 } });

await contexto.route(/redsys\.es/, (ruta) => ruta.fulfill({
    status: 200,
    contentType: 'text/html',
    body: '<!doctype html><title>pasarela</title><p>La pasarela de la sonda.</p>',
}));

const page = await contexto.newPage();

page.on('request', (peticion) => {
    const url = new URL(peticion.url());
    if (url.pathname.startsWith('/api/v1/')) anota(`    → ${peticion.method()} ${normaliza(url)}`);
    else if (url.hostname.endsWith('redsys.es')) anota(`    → ${peticion.method()} ${url.hostname} (la pasarela)`);
});
page.on('pageerror', (error) => anota(`    ✗ pageerror: ${error.message.split('\n')[0]}`));
page.on('console', (mensaje) => {
    if (mensaje.type() === 'error' || mensaje.type() === 'warning') anota(`    ✗ console.${mensaje.type()}: ${mensaje.text().split('\n')[0]}`);
});

/** Espera a que la pantalla se asiente —sin velo y sin red— y anota su título. */
async function pantalla() {
    await page.waitForSelector('.jj-loading', { state: 'hidden' }).catch(() => {});
    await page.waitForLoadState('networkidle').catch(() => {});
    const titulo = await page.locator('.sidecart.is-open .wiz__title').first().innerText({ timeout: 2000 }).catch(() => '—');
    anota(`  ■ «${titulo.trim()}»`);
}

async function paso(nombre, accion) {
    anota(`▶ ${nombre}`);
    await accion();
    await pantalla();
}

async function aceptarCookies() {
    const boton = page.locator('.cookie-consent-root button:visible', { hasText: /^acept/i }).first();
    if (await boton.count()) await boton.click({ timeout: 3000 }).catch(() => {});
}

try {
    await paso('abrir /entradas', async () => {
        await page.goto(`${BASE}/entradas`, { waitUntil: 'domcontentloaded' });
        await aceptarCookies();
        const cabecera = page.locator('.catalog-acc__head').first();
        await cabecera.waitFor();
        if (await cabecera.getAttribute('aria-expanded') !== 'true') await cabecera.click();
        await page.waitForSelector('.catalog__item');
    });

    await paso('elegir el primer producto', async () => {
        await page.locator('.catalog__item').first().click();
        await page.waitForSelector('.daystrip__day');
    });

    await paso('elegir el primer día', async () => {
        await page.locator('.daystrip__day').first().click();
        await page.waitForSelector('.purchase__chip');
    });

    await paso('elegir la primera hora libre', async () => {
        await page.locator('.purchase__chip:not(.is-full)').first().click();
        await page.waitForSelector('.qtybox');
    });

    await paso('añadir a la cesta', async () => {
        await page.locator('.bk-cta').click();
        await page.waitForSelector('.cartbar, .cart__item');
        if (await page.locator('.cartbar').count()) await page.locator('.cartbar').click();
        await page.waitForSelector('.cart__item');
    });

    await paso('recargar (la cesta se restaura)', async () => {
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.cart__item');
    });

    await paso('ir a pagar sin sesión', async () => {
        await page.locator('.bk-cta').click();
        await page.waitForSelector('.auth');
    });

    await paso('entrar con la cuenta de sonda', async () => {
        await page.fill('#login-email', CLIENTE.email);
        await page.fill('#login-password', CLIENTE.password);
        await page.locator('.auth__submit').click();
        await page.waitForSelector('.cart--summary', { timeout: 20000 });
    });

    await paso('pagar con tarjeta', async () => {
        const casilla = page.locator('.paydue input[type="checkbox"]');
        if (await casilla.count() && ! await casilla.isChecked()) await casilla.check();
        const telefono = page.locator('.paydue input[type="tel"]');
        if (await telefono.count()) await telefono.fill('600000000');
        await page.locator('.bk-cta').click();
        await page.waitForURL(/redsys\.es/, { timeout: 20000 });
    });

    await paso('volver del banco sin datos (paso 11)', async () => {
        await page.goto(`${BASE}/pago/redsys/retorno-ok`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.purchase__verifying');
    });

    anota('▶ esperar un sondeo (5 s)');
    await page.waitForTimeout(6000);
    await pantalla();

    await paso('el banco dice que no → volver (paso 10)', async () => {
        tinker(`$o = ${ULTIMO}; $p = $o->payments()->latest('id')->first(); $p->status = 'failed'; $p->raw_response = ['Ds_Response' => '0190']; $p->save();`);
        await page.goto(`${BASE}/pago/redsys/retorno-ko`, { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('.purchase__failed');
    });
    // La hora de retención depende del reloj: se anota su FORMA, que es lo que un idioma cambia.
    for (const nota of await page.locator('.purchase__failed .purchase__note').allInnerTexts()) {
        anota(`    · ${nota.trim().replace(/\d{1,2}:\d{2}(\s?[AP]M)?/g, (hora, meridiano) => (meridiano ? 'hh:mm AM' : 'HH:MM'))}`);
    }

    await paso('reintentar el pago', async () => {
        await page.locator('.purchase__failed .purchase__cta').click();
        await page.waitForURL(/redsys\.es/, { timeout: 20000 });
    });
} catch (error) {
    anota(`✗ SE CORTA: ${error.message.split('\n')[0]}`);
} finally {
    // La plaza vuelve al inventario por el camino del dominio: se vence la retención y barre el comando.
    tinker(`$o = ${ULTIMO}; if ($o && $o->status === 'pending') { $o->expires_at = now()->subMinute(); $o->save(); }`);
    execFileSync('php', ['artisan', 'orders:expire'], { encoding: 'utf8' });
    await navegador.close();
}

await mkdir(SALIDA, { recursive: true });
await writeFile(`${SALIDA}/embudo-${ETIQUETA}.txt`, traza.join('\n') + '\n');
console.log(traza.join('\n'));
console.log(`\n${traza.filter((l) => l.startsWith('▶')).length} pasos · ${traza.filter((l) => l.includes('→ ')).length} peticiones · ${traza.filter((l) => l.includes('✗')).length} fallos · ${SALIDA}/embudo-${ETIQUETA}.txt`);
