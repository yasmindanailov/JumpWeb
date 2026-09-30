/**
 * SONDA DEL INVENTARIO DE COOKIES — lo que la web pone DE VERDAD en el navegador, para que la política de `/cookies` diga
 * eso y no lo que se creía (`docs/sistemas/COOKIES.md` §1; el owner, 30-09: «con rigor y profesionalidad»). Recorre cada
 * página que declara la instancia en cinco escenarios, cada uno en un navegador limpio:
 *   1. `sin-decidir`: sin tocar el aviso;
 *   2. `rechazar`: «Rechazar» en el aviso;
 *   3. `aceptar`: «Aceptar» (todas las categorías);
 *   4. `entrar`: rechazando, y entrando con el código al correo (leído de Mailpit), como la isla, SIN marcar «Mantener
 *      la sesión iniciada»; y `entrar-recordando`, marcándola (`#858`: la cookie de recuerdo, solo entonces);
 *   5. `alta`: rechazando, y abriendo «Crea tu cuenta» (el anti-bot, si la instalación lo tiene).
 * Apunta cada cookie (nombre, dominio, ruta, caducidad en días, `HttpOnly`, `Secure`, `SameSite`) y cada ORIGEN de tercero
 * al que el navegador pide algo, por escenario. No paga: el banco es una redirección a SU dominio, y sus cookies son suyas.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-inventario-cookies.mjs
 *
 * Solo en LOCAL. Mide lo que la configuración LOCAL enciende (mapa, redes, anti-bot, análisis, píxeles): lo que en
 * producción esté configurado distinto se contrasta aparte. Salida: `storage/app/audit/inventario-cookies.json`, una
 * tabla y las capturas de `/cookies` a 390 y 1280. ⚠️ Sale con 1 si una página no carga o si una cookie PROPIA medida no
 * está en el listado que publica la política (`GET /legal/documents/cookies`, `inventory`): la guarda de que no miente.
 */
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const MAILPIT = process.env.SONDA_MAILPIT ?? 'http://mailpit:8025';
const SALIDA = 'storage/app/audit';
const CLIENTE = { email: 'probe-card@jumpweb.test' };
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }

// Las páginas que declara la instancia (las mismas que recorre `sonda-web.mjs`) y las legales del producto.
const declaradas = JSON.parse(tinker(`$p = app(App\\Http\\Instancia\\InstancePages::class); $r = []; foreach ($p->todas() as $pg) { $n = $pg->ocupa ?? $pg->ruta(); if (Illuminate\\Support\\Facades\\Route::has($n)) { $r[] = route($n, [], false); } } echo json_encode(array_values(array_unique($r)));`).split('\n').pop());
const PAGINAS = [...new Set(['/', ...declaradas, '/cookies', '/privacidad', '/aviso-legal', '/condiciones'])];

// Lo que la configuración local tiene encendido: sin esto, «no apareció» no dice nada.
const configuracion = JSON.parse(tinker(`$s = fn ($k) => (string) App\\Domain\\Platform\\Models\\Setting::value($k, ''); echo json_encode([
    'banner' => App\\Domain\\Identity\\Services\\CookieConsent::bannerEnabled(),
    'mapa' => $s('address.maps_embed_url') !== '',
    'redes' => $s('social.feed_embed_url') !== '',
    'turnstile' => App\\Domain\\Platform\\Services\\Turnstile::enabled(),
    'sesion' => ['cookie' => config('session.cookie'), 'minutos' => config('session.lifetime'), 'al_cerrar' => config('session.expire_on_close')],
]);`).split('\n').pop());

const LIMPIAR = `$h = App\\Domain\\Identity\\Services\\SelfSignup::emailHash('${CLIENTE.email}'); foreach (['login-code-ip|127.0.0.1', 'login-code-email|'.$h, 'login-code-email-hour|'.$h, 'login-ip|127.0.0.1', Illuminate\\Support\\Str::transliterate('${CLIENTE.email}|127.0.0.1'), md5('api'.'ip:127.0.0.1')] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }`;

async function codigoDelBuzon(desde) {
    const url = `${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${CLIENTE.email}"`)}&limit=1`;
    for (let i = 0; i < 60; i += 1) {
        const ultimo = (await fetch(url).then((r) => r.json()).catch(() => null))?.messages?.[0];
        const cifras = /(\d{3}) (\d{3}) /.exec(ultimo?.Subject ?? '');
        if (cifras && Date.parse(ultimo.Created) >= desde - 2000) return `${cifras[1]}${cifras[2]}`;
        await new Promise((listo) => setTimeout(listo, 250));
    }

    return null;
}

const propio = new URL(BASE).hostname;
const navegador = await chromium.launch();
const resultado = { configuracion, paginas: PAGINAS, escenarios: {} };
const fallos = [];

/** Un escenario: un navegador limpio, lo que haga `antes` y todas las páginas. */
async function escenario(nombre, antes = async () => {}) {
    const ctx = await navegador.newContext({ viewport: { width: 1280, height: 900 }, locale: 'es-ES' });
    const page = await ctx.newPage();
    const terceros = new Map();

    page.on('request', (req) => {
        const u = new URL(req.url());
        if (! ['http:', 'https:'].includes(u.protocol) || u.hostname === propio) return;
        terceros.set(u.hostname, (terceros.get(u.hostname) ?? new Set()).add(req.resourceType()));
    });

    await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
    await antes(page, ctx);

    for (const ruta of PAGINAS) {
        const r = await page.goto(`${BASE}${ruta}`, { waitUntil: 'networkidle' }).catch((e) => ({ status: () => `error ${e.message}` }));
        if (typeof r?.status() !== 'number' || r.status() >= 400) fallos.push(`${nombre} ${ruta} → ${r?.status()}`);
    }

    const ahora = Date.now() / 1000;
    resultado.escenarios[nombre] = {
        cookies: (await ctx.cookies()).map((c) => ({
            nombre: c.name, dominio: c.domain, ruta: c.path, httpOnly: c.httpOnly, secure: c.secure, sameSite: c.sameSite,
            dias: c.expires > 0 ? Math.round((c.expires - ahora) / 86400 * 10) / 10 : 'sesión',
        })).sort((a, b) => a.nombre.localeCompare(b.nombre)),
        terceros: [...terceros].map(([host, tipos]) => ({ host, tipos: [...tipos].sort() })).sort((a, b) => a.host.localeCompare(b.host)),
    };
    await ctx.close();
}

const pulsarAviso = (texto) => async (page) => {
    const b = page.getByRole('button', { name: texto, exact: true }).first();
    await b.waitFor({ timeout: 8000 }).catch(() => {});
    await b.click().catch(() => fallos.push(`no se encontró «${texto}» en el aviso`));
    await page.waitForLoadState('networkidle');
};

await escenario('sin-decidir');
await escenario('rechazar', pulsarAviso('Rechazar'));
await escenario('aceptar', pulsarAviso('Aceptar'));
/** Rechazando, entra con el código leído de Mailpit; `recordar`, la casilla «Mantener la sesión iniciada» (`#858`). */
const entrarConCodigo = (recordar) => async (page) => {
    await pulsarAviso('Rechazar')(page);
    tinker(LIMPIAR);
    const desde = Date.now();
    const pedir = (ruta, cuerpo) => page.evaluate(async ([ruta, cuerpo]) => {
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
        const xsrf = decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
        return (await fetch(`/api/v1${ruta}`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf }, body: JSON.stringify(cuerpo) })).status;
    }, [ruta, cuerpo]);
    await pedir('/auth/code', { email: CLIENTE.email });
    const codigo = await codigoDelBuzon(desde);
    if ((await pedir('/auth/login', { email: CLIENTE.email, code: codigo ?? '', remember: recordar })) !== 200) fallos.push('entrar con el código no dio 200');
};

await escenario('entrar', entrarConCodigo(false));
await escenario('entrar-recordando', entrarConCodigo(true));
await escenario('alta', async (page) => {
    await pulsarAviso('Rechazar')(page);
    await page.goto(`${BASE}/kids#mi-cuenta`, { waitUntil: 'networkidle' });
    await page.getByText('Crea tu cuenta').first().click().catch(() => fallos.push('no se abrió «Crea tu cuenta»'));
    await page.waitForTimeout(2500);
});

// LA GUARDA: cada cookie PROPIA medida está en el listado que la política publica (`GET /legal/documents/cookies`), con su
// nombre exacto. Una cookie nueva que nadie declaró hace fallar la sonda (`specs/politica-de-cookies.md` §3).
const declarado = await (await fetch(`${BASE}/api/v1/legal/documents/cookies?lang=es`, { headers: { Accept: 'application/json' } })).json();
const nombres = new Set((declarado.inventory?.cookies ?? []).map((c) => c.name));
const medidas = new Set(Object.values(resultado.escenarios).flatMap((e) => e.cookies.filter((c) => c.dominio === propio).map((c) => c.nombre)));
for (const nombre of medidas) {
    if (! nombres.has(nombre)) fallos.push(`la cookie «${nombre}» se pone y la política no la declara`);
}
resultado.declaradas = [...nombres];

// Y la página, para el ojo: el listado a 390 y a 1280.
for (const ancho of [390, 1280]) {
    const ctx = await navegador.newContext({ viewport: { width: ancho, height: ancho < 600 ? 844 : 900 }, locale: 'es-ES' });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/cookies`, { waitUntil: 'networkidle' });
    const listado = page.locator('[data-cookie-inventory]');
    if (! await listado.count()) fallos.push(`/cookies a ${ancho}: sin el listado`);
    await listado.scrollIntoViewIfNeeded().catch(() => {});
    await page.screenshot({ path: `${SALIDA}/inventario-cookies-${ancho}.png` });
    await ctx.close();
}

await navegador.close();
await mkdir(SALIDA, { recursive: true });
await writeFile(`${SALIDA}/inventario-cookies.json`, JSON.stringify(resultado, null, 2));

console.log('Configuración local:', JSON.stringify(configuracion));
for (const [nombre, e] of Object.entries(resultado.escenarios)) {
    console.log(`\n── ${nombre}`);
    for (const c of e.cookies) console.log(`  cookie  ${c.nombre.padEnd(52)} ${String(c.dias).padStart(7)} d  ${c.dominio}${c.ruta}  ${c.httpOnly ? 'HttpOnly ' : ''}${c.secure ? 'Secure ' : ''}${c.sameSite}`);
    for (const t of e.terceros) console.log(`  tercero ${t.host.padEnd(52)} ${t.tipos.join(',')}`);
}
if (fallos.length) {
    console.log(`\n✗ ${fallos.join('\n✗ ')}`);
    process.exit(1);
}
console.log(`\n✓ ${PAGINAS.length} páginas en ${Object.keys(resultado.escenarios).length} escenarios; las ${medidas.size} cookies propias medidas, declaradas en la política`);
