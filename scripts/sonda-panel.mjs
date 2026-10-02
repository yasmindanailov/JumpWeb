/**
 * **EL PANEL, A SALVO, EN VIVO** (`docs/specs/panel-a-salvo.md`; `DECISIONES #850`): lo que las pruebas no ven del guard
 * propio del panel —las peticiones Livewire, que no pasan por la ruta de la página— en un navegador real:
 *   1. el login del panel entra, y su sesión NO abre la web (`GET /api/v1/me` → 401);
 *   2. una pantalla de Filament con Livewire (la búsqueda de Pedidos) responde con el guard del panel;
 *   3. LA PUERTA (`/admin/puerta/validar`, Livewire fuera de Filament): buscar un cliente responde 200 y pinta
 *      un resultado —si Livewire no reaplicara `auth:admin`, `authorizeAccess()` daría 403—;
 *   4. una ruta del personal fuera de Filament (el resumen del día, PDF) responde 200;
 *   5. en otro navegador, entrar por la WEB (`POST /api/v1/auth/login`) con la misma cuenta NO abre el panel;
 *   6. con dirección secreta (P2), `/admin` y los suyos dan 404;
 *   7. EL AUTHENTICATOR (P3, `#851`): el login de un administrador con él pide el código de su app (la sonda lo calcula,
 *      TOTP de 6 cifras y 30 s, y sin él no entra); uno SIN él acaba, tras su contraseña, en la página de configurarlo.
 * Monta sus administradores temporales (`sonda-panel@` con un secreto conocido, `sonda-panel-sin@` sin él; contraseñas
 * aleatorias) y los BORRA al terminar. Solo en LOCAL. Sale con 1 si algo falla.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-panel.mjs [390|1280]
 */
/* global console, document, fetch, URL -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { Buffer } from 'node:buffer';
import { createHmac, randomBytes } from 'node:crypto';
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const ANCHO = Number(process.argv[2] ?? 1280);
const EMAIL = 'sonda-panel@jumpweb.test';
const EMAIL_SIN = 'sonda-panel-sin@jumpweb.test';
const CLAVE = randomBytes(18).toString('base64url');
const CLIENTE = 'probe-card@jumpweb.test';
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

/** El código de 6 cifras de una app de autenticación (TOTP, RFC 6238: HMAC-SHA1, pasos de 30 s) para un secreto base32. */
function totp(secreto, ahora = Date.now()) {
    const alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const c of secreto.replace(/=+$/, '').toUpperCase()) bits += alfabeto.indexOf(c).toString(2).padStart(5, '0');
    const clave = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
    const paso = Buffer.alloc(8);
    paso.writeBigUInt64BE(BigInt(Math.floor(ahora / 30000)));
    const h = createHmac('sha1', clave).update(paso).digest();
    const o = h[h.length - 1] & 0xf;

    return String((h.readUInt32BE(o) & 0x7fffffff) % 1000000).padStart(6, '0');
}

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }
const PANEL = tinker("echo trim((string) (config('panel.path') ?? 'admin'), '/');") || 'admin';
const montar = (email, conSecreto) => tinker(`$u = App\\Domain\\Identity\\Models\\User::firstOrNew(['email' => '${email}']);
    $u->forceFill(['name' => 'Sonda del panel', 'password' => '${CLAVE}', 'email_verified_at' => now()])->save();
    $u->roles()->sync([App\\Domain\\Identity\\Models\\Role::where('name', 'admin')->value('id')]);
    $s = ${conSecreto ? 'app(PragmaRX\\Google2FA\\Google2FA::class)->generateSecretKey()' : 'null'};
    $u->saveAppAuthenticationSecret($s);
    foreach (['login-ip|127.0.0.1', md5('api'.'ip:127.0.0.1'), 'livewire-rate-limiter:'.sha1(Filament\\Auth\\Pages\\Login::class.'|authenticate|127.0.0.1')] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }
    echo $s ?? '';`);
// ⚠️ La última clave es el limitador del login de Filament (5 por minuto e IP): dos pasadas seguidas lo agotaban (medido: la
// segunda, a 390 justo tras la de 1280, dio 9/11).
const SECRETO = montar(EMAIL, true);
montar(EMAIL_SIN, false);
const CONFIGURAR = new URL(tinker("echo Filament\\Facades\\Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl();")).pathname;

const filas = [];
const ok = (nombre, cierto, detalle = '') => filas.push(`${cierto ? '✓' : '✗'} ${nombre}${detalle ? ` — ${String(detalle).replace(/\s+/g, ' ').slice(0, 170)}` : ''}`);
const navegador = await chromium.launch();
const errores = [];

async function contexto() {
    const ctx = await navegador.newContext({ viewport: { width: ANCHO, height: ANCHO < 600 ? 844 : 900 }, locale: 'es-ES' });
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errores.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error' && ! /status of 401/.test(m.text())) errores.push(m.text()); });

    return { ctx, page };
}
/** Las respuestas de Livewire (`/livewire…/update`) que llegan mientras corre `accion`. */
async function livewire(page, accion) {
    const estados = [];
    const oir = (r) => { if (/livewire[^/]*\/update/.test(r.url())) estados.push(r.status()); };
    page.on('response', oir);
    await accion();
    await page.waitForTimeout(2500);
    page.off('response', oir);

    return estados;
}

try {
    // ── 1 · El login del panel —con el reto del authenticator—, y su sesión no abre la web ──────────────────────────
    const a = await contexto();
    await a.page.goto(`${BASE}/${PANEL}/login`, { waitUntil: 'networkidle' });
    await a.page.fill('input[type="email"]', EMAIL);
    await a.page.fill('input[type="password"]', CLAVE);
    await a.page.click('button[type="submit"]');
    const codigo = a.page.locator('input[autocomplete="one-time-code"], input[id*="multiFactor"]').first();
    await codigo.waitFor({ timeout: 15000 });
    ok('P3: con la contraseña sola, el administrador NO entra: le pide el código de su app', new URL(a.page.url()).pathname.endsWith('/login'), a.page.url());
    // La sexta cifra envía sola (Z6g·1, `#867`, `PanelAppAuthentication`): rellenar basta, sin tocar «Entrar».
    await Promise.all([a.page.waitForURL((u) => ! u.pathname.endsWith('/login'), { timeout: 20000 }), codigo.fill(totp(SECRETO))]);
    ok('y con el código de su app, entra al escribir la última cifra', new URL(a.page.url()).pathname.startsWith(`/${PANEL}`) && ! a.page.url().endsWith('/login'), a.page.url());
    const me = await a.page.evaluate(async () => (await fetch('/api/v1/me', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })).status);
    ok('su sesión NO abre la web (`/api/v1/me`)', me === 401, `HTTP ${me}`);

    // ── 2 · Una pantalla de Filament con Livewire ─────────────────────────────────────────────────────────────────
    await a.page.goto(`${BASE}/${PANEL}/orders`, { waitUntil: 'networkidle' });
    const buscar = a.page.locator('input[type="search"]').first();
    const enPedidos = await livewire(a.page, () => buscar.fill('JJ'));
    ok('Filament (buscar en Pedidos): Livewire responde con la sesión del panel', enPedidos.length > 0 && enPedidos.every((s) => s === 200), JSON.stringify(enPedidos));

    // ── 3 · La puerta ─────────────────────────────────────────────────────────────────────────────────────────────
    await a.page.goto(`${BASE}/${PANEL}/puerta/validar`, { waitUntil: 'networkidle' });
    await a.page.fill('#input', CLIENTE);
    const enPuerta = await livewire(a.page, () => a.page.press('#input', 'Enter'));
    const estado = await a.page.evaluate(() => document.querySelector('[data-gate-status]')?.dataset.gateStatus ?? null);
    ok('la puerta: buscar responde 200 (Livewire con `auth:admin`)', enPuerta.length > 0 && enPuerta.every((s) => s === 200), JSON.stringify(enPuerta));
    ok('y pinta el resultado de la búsqueda (`data-gate-status`)', estado !== null, `estado «${estado}»`);

    // ── 4 · Una ruta del personal fuera de Filament ──────────────────────────────────────────────────────────────
    const hoy = new Date().toISOString().slice(0, 10);
    const pdf = await a.page.request.get(`${BASE}/${PANEL}/calendario/resumen-dia?date=${hoy}`);
    ok('una ruta del personal (el resumen del día) responde 200', pdf.status() === 200, `HTTP ${pdf.status()} · ${pdf.headers()['content-type'] ?? ''}`);
    await a.ctx.close();

    // ── 5 · Entrar por la WEB no abre el panel ───────────────────────────────────────────────────────────────────
    const b = await contexto();
    await b.page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
    const web = await b.page.evaluate(async ({ email, password }) => {
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
        const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) ?? [])[1] ?? '');
        const r = await fetch('/api/v1/auth/login', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf },
            body: JSON.stringify({ email, password }),
        });

        return r.status;
    }, { email: EMAIL, password: CLAVE });
    ok('control: la misma cuenta entra por la web', web === 200, `HTTP ${web}`);
    await b.page.goto(`${BASE}/${PANEL}`, { waitUntil: 'networkidle' });
    ok('y esa sesión de la web NO abre el panel (va a su login)', new URL(b.page.url()).pathname === `/${PANEL}/login`, b.page.url());

    // ── 6 · Con dirección secreta (P2), `/admin` ya no existe ─────────────────────────────────────────────────────
    if (PANEL !== 'admin') {
        const viejas = await Promise.all(['/admin', '/admin/login', '/admin/puerta/validar'].map(async (r) => [r, (await b.page.request.get(`${BASE}${r}`, { maxRedirects: 0 })).status()]));
        ok('con dirección secreta, `/admin`, su login y la puerta de siempre dan 404', viejas.every(([, s]) => s === 404), JSON.stringify(viejas));
    }
    await b.ctx.close();

    // ── 7 · Un administrador SIN authenticator: tras su contraseña, a configurarlo ─────────────────────────────────
    const c = await contexto();
    await c.page.goto(`${BASE}/${PANEL}/login`, { waitUntil: 'networkidle' });
    await c.page.fill('input[type="email"]', EMAIL_SIN);
    await c.page.fill('input[type="password"]', CLAVE);
    await Promise.all([c.page.waitForURL((u) => ! u.pathname.endsWith('/login'), { timeout: 20000 }), c.page.click('button[type="submit"]')]);
    await c.page.goto(`${BASE}/${PANEL}/orders`, { waitUntil: 'networkidle' });
    ok('P3: un administrador sin authenticator acaba en la página de configurarlo', new URL(c.page.url()).pathname === CONFIGURAR, `${c.page.url()} · esperada ${CONFIGURAR}`);
    await c.ctx.close();

    ok('sin errores en la consola', errores.length === 0, errores.slice(0, 3).join(' | '));
} catch (e) {
    ok('la sonda terminó', false, e.message);
} finally {
    await navegador.close();
    tinker(`App\\Domain\\Identity\\Models\\User::whereIn('email', ['${EMAIL}', '${EMAIL_SIN}'])->get()->each->delete();`);
    filas.push(`· borrados los administradores temporales ${EMAIL} y ${EMAIL_SIN}`);
}

console.log(`SONDA DEL PANEL · /${PANEL} · ${ANCHO}px\n${filas.join('\n')}`);
process.exit(filas.some((f) => f.startsWith('✗')) ? 1 : 0);
