/**
 * SONDA DEL CARTEL DEL PARQUE (TA de `docs/specs/analitica-para-decidir.md` §4.15; `#876`, la fila 8 de la lista del owner: «altas
 * en casa y en el parque»). El enlace del QR, de punta a punta en la LOCAL, con un visitante nuevo que NO consiente las cookies
 * —el caso que importa: la campaña viaja sin consentimiento y la visita no—:
 *   1. `/?utm_source=parque&utm_medium=qr&utm_campaign=registro#mi-cuenta` abre Mi cuenta en la isla («Entra o crea tu cuenta»);
 *   2. la visita se guarda con su campaña (`analytics_sessions`) cuando llega el primer lote de la medición (a los 5 s como
 *      mucho, `FLUSH_MS` de `cajon/track.js`): se mide cuánto tarda, porque un alta ANTES de ese lote saldría «sin dato»;
 *   3. el correo en «Entra» → «Continuar» → «Crea tu cuenta» (con el correo puesto) → nombre y el descargo (modo interno) →
 *      «Crear mi cuenta»: nace la cuenta;
 *   4. su `user_registered` lleva `parque/qr/registro` y NO lleva la visita (`session_id` y `visitor_id` vacíos);
 *   5. «Clientes» la cuenta en «De dónde llegan las altas» (`CustomersReport`, hoy), en la fila del QR.
 * La cuenta de la sonda (`sonda-cartel-<hora>@jumpweb.test`) se ANONIMIZA al final (su descargo es una prueba y no se borra,
 * `RGPD-01`); su alta sigue contando en la fila del QR, que es lo que se ve en el panel. Sin errores de JavaScript.
 *
 *   docker compose exec -u sail -T laravel.test node scripts/sonda-cartel.mjs [http://localhost]
 */
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const [base = 'http://localhost'] = process.argv.slice(2);
const ENLACE = '/?utm_source=parque&utm_medium=qr&utm_campaign=registro#mi-cuenta';
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();
const json = (php) => JSON.parse(tinker(php).split('\n').pop());

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }
if (tinker('echo App\\Domain\\Content\\Services\\ShellSettings::shell();') !== 'isla') { console.error('✗ la carcasa no es la isla'); process.exit(1); }

const checks = [];
const check = (nombre, ok, detalle = '') => checks.push({ nombre, ok: Boolean(ok), detalle });
const correo = `sonda-cartel-${Date.now()}@jumpweb.test`;
const desde = tinker("echo now()->utc()->format('Y-m-d H:i:s');");

const navegador = await chromium.launch();
const contexto = await navegador.newContext({ viewport: { width: 390, height: 844 }, locale: 'es-ES' });
const pagina = await contexto.newPage();
const errores = [];
const malas = [];
const faltan = new Set();
pagina.on('pageerror', (e) => errores.push(e.message));
// «Failed to load resource» es un FICHERO que no está (se apunta abajo, con su dirección), no un error de JavaScript.
pagina.on('console', (m) => { if (m.type() === 'error' && ! /401|Failed to load resource/.test(m.text())) errores.push(m.text()); });
pagina.on('response', (r) => {
    const ruta = new URL(r.url()).pathname;
    if (r.status() === 404 && ! ruta.startsWith('/api/')) faltan.add(ruta);
    // Sin sesión, el motor pregunta quién es al abrir: el 401 de `/me` es de esperar (como en `sonda-cuenta.mjs`).
    if (r.status() >= 400 && ruta.startsWith('/api/') && ! (r.status() === 401 && /^\/api\/v1\/me(\/|$)/.test(ruta))) malas.push(`${r.status()} ${r.request().method()} ${ruta}`);
});
const capa = () => pagina.locator('[data-isla] [role=dialog][aria-labelledby="isla-compra-paso"]');
const titular = async () => (await capa().locator('h1').first().textContent({ timeout: 4000 }).catch(() => '')).trim();

try {
    // ── 1 · El enlace del cartel abre Mi cuenta ───────────────────────────────────────────────────────
    const t0 = Date.now();
    await pagina.goto(`${base}${ENLACE}`, { waitUntil: 'load' });
    await pagina.waitForTimeout(500);
    // Sin decidir nada sobre las cookies: el visitante NO consiente (lo comprueba la foto de la visita, en el paso 2).
    await capa().waitFor({ timeout: 8000 }).catch(() => {});
    check('el enlace abre Mi cuenta en la isla («Entra o crea tu cuenta», sin sesión)', (await titular()).startsWith('Entra') && await pagina.locator('#pjc-ent').isVisible(), `titular «${await titular()}»`);

    // ── 2 · La visita, con su campaña, cuando llega el primer lote ────────────────────────────────────
    let visita = null;
    for (let i = 0; i < 40 && visita === null; i += 1) {
        visita = json(`$s = App\\Domain\\Platform\\Models\\AnalyticsSession::query()->where('started_at', '>=', '${desde}')->where('utm_campaign', 'registro')->latest('id')->first(); echo json_encode($s ? ['id' => $s->id, 'source' => $s->utm_source, 'medium' => $s->utm_medium, 'campaign' => $s->utm_campaign, 'consent' => $s->consent] : null);`);
        if (visita === null) await pagina.waitForTimeout(500);
    }
    const tarda = ((Date.now() - t0) / 1000).toFixed(1);
    check('la visita se guarda con la campaña del cartel (parque · qr · registro)', visita?.source === 'parque' && visita?.medium === 'qr' && visita?.campaign === 'registro', `${JSON.stringify(visita)} · llegó a los ${tarda} s`);
    check('y sin «análisis» consentido', visita !== null && ! visita.consent?.analytics, JSON.stringify(visita?.consent ?? null));

    // ── 3 · El alta, con el formulario de la isla: el correo en «Entra», «Continuar» y, nuevo, «Crea tu cuenta» ───────
    await pagina.fill('#pjc-ent', correo);
    await capa().getByRole('button', { name: 'Continuar' }).click();
    for (let i = 0; i < 20 && (await titular()) !== 'Crea tu cuenta'; i += 1) await pagina.waitForTimeout(300);
    check('un correo nuevo lleva a «Crea tu cuenta»', (await titular()) === 'Crea tu cuenta', `titular «${await titular()}» · el correo, en la capa: ${(await capa().innerText().catch(() => '')).includes(correo)}`);
    await pagina.fill('#pjc-nombre', 'Sonda Cartel');
    if (await pagina.locator('#pjc-descargo').count()) {
        await pagina.getByText('He leído y acepto el descargo de responsabilidad.').click();
    }
    await capa().getByRole('button', { name: 'Crear mi cuenta' }).click();
    let id = '';
    for (let i = 0; i < 30 && id === ''; i += 1) {
        id = tinker(`echo App\\Domain\\Identity\\Models\\User::query()->where('email', '${correo}')->value('id') ?? '';`);
        if (id === '') await pagina.waitForTimeout(500);
    }
    check('nace la cuenta', id !== '', correo);

    // ── 4 · El hecho del alta: la campaña sí, la visita no ────────────────────────────────────────────
    if (id !== '') {
        const alta = json(`$e = App\\Domain\\Platform\\Models\\AnalyticsEvent::query()->where('name', 'user_registered')->where('user_id', ${id})->first(); echo json_encode($e ? ['props' => $e->props, 'session_id' => $e->session_id, 'visitor_id' => $e->visitor_id] : null);`);
        check('su `user_registered` lleva la campaña del cartel', alta?.props?.source === 'parque' && alta?.props?.medium === 'qr' && alta?.props?.campaign === 'registro', JSON.stringify(alta?.props ?? null));
        check('y no lleva la visita (sin «análisis»)', alta !== null && alta.session_id === null && alta.visitor_id === null, JSON.stringify({ session_id: alta?.session_id, visitor_id: alta?.visitor_id }));

        // ── 5 · El informe: la fila del QR en «De dónde llegan las altas» ──────────────────────────────
        const fila = json("$w = (new App\\Filament\\Analytics\\CustomersReport)->compute(App\\Domain\\Platform\\Enums\\ReportPeriod::Today->window())['registrations']['by_origin']['web']; echo json_encode(collect($w)->first(fn ($r) => $r['source'] === 'parque' && $r['medium'] === 'qr' && $r['campaign'] === 'registro'));");
        check('«Clientes» la cuenta en la fila del QR (hoy)', (fila?.n ?? 0) >= 1, JSON.stringify(fila));
    }
    await pagina.screenshot({ path: 'storage/app/audit/sonda-cartel-390.png' });
} finally {
    check('sin errores de JavaScript', errores.length === 0, errores.join(' | '));
    if (faltan.size) console.log(`ℹ ficheros que la local no tiene (404): ${[...faltan].join(' · ')}`);
    check('ninguna petición a la API en error (salvo el 401 de `/me` sin sesión)', malas.length === 0, malas.join(' | '));
    await navegador.close();
    // La cuenta de la sonda, anonimizada (su descargo se conserva: es una prueba). Su alta sigue contando.
    tinker(`$u = App\\Domain\\Identity\\Models\\User::query()->where('email', '${correo}')->first(); if ($u) { $u->anonymize(); }`);
}

for (const c of checks) console.log(`${c.ok ? '✓' : '✗'} ${c.nombre}${c.detalle ? ` — ${c.detalle}` : ''}`);
const fallan = checks.filter((c) => ! c.ok).length;
console.log(`\n${checks.length - fallan}/${checks.length}`);
process.exit(fallan === 0 ? 0 : 1);
