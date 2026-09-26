/**
 * **LA SONDA DE MI CUENTA EN LA ISLA, en vivo** (T5a de `specs/isla-y-landing-nueva.md` §4.13, `DECISIONES #773`): la
 * página de verdad (`/kids`), el cajón, el motor y la API de verdad, en un navegador, a 1280 y a 390. Comprueba:
 *   1. sin sesión, «Entrar o crear cuenta» del menú abre ENTRA en la capa de la isla —no el lateral—, con la flecha que
 *      vuelve al menú; «Crea tu cuenta» es un paso de la capa y su flecha vuelve a Entra; y la puerta de completar el
 *      alta de Google (`/registro/google`) abre su paso en la isla (sin perfil pendiente, su desenlace «caducado»);
 *   2. entrar recarga la MISMA página en Mi cuenta (`#mi-cuenta`): «Hola, …» y Tu QR compacto con la PNG del servidor;
 *   3. «Enseñar mi QR» abre Tu QR (el QR grande, el código en grupos de cuatro, «Guardar en el móvil» con su descarga) y
 *      su flecha vuelve a Mi cuenta; «Renovar mi QR» pregunta, renueva y lo confirma arriba con el código nuevo;
 *   4. la X cierra la capa y deja la dirección sin `#mi-cuenta`; «Mi QR» del menú abre Tu QR directamente;
 *   5. las puertas: `/mi-cuenta` abre Mi cuenta en la isla, y `#mi-cuenta/qr`, Tu QR;
 *   6. la consola queda limpia y ninguna respuesta de la API falla.
 * Sale con 1 si algo falla. Dentro del contenedor, contra su puerto 80, con la cuenta de pruebas de `sonda-isla.mjs`:
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-cuenta.mjs http://localhost
 *
 * ⚠️ Renueva el carné de la cuenta de pruebas (es lo que se prueba): no la uses para otra cosa que dependa de su QR.
 */
/* global URL, console, document -- Node y, dentro de `evaluate`, el navegador */
import process from 'node:process';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const [base = 'http://localhost'] = process.argv.slice(2);
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!', nombre: 'Sonda' };
const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL'); process.exit(1); }
if (tinker('echo App\\Domain\\Content\\Services\\ShellSettings::shell();') !== 'isla') { console.error('✗ la carcasa no es la isla'); process.exit(1); }

// Los limitadores (entrar, 5 por minuto; la API, 60 por minuto y por IP o titular) no pueden decidir el resultado de una
// sonda que entra dos veces y, repetida, agota el suelo de la API (la trampa de `TESTING.md` §2.octies). Las claves, las
// de `sonda-isla.mjs`.
const limitadoresACero = () => tinker(`$id = App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->value('id'); foreach ([md5('api'.'user:'.$id), md5('api'.'ip:127.0.0.1'), 'login-ip|127.0.0.1', Illuminate\\Support\\Str::transliterate('${CLIENTE.email}|127.0.0.1')] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }`);

async function recorrer(navegador, ventana) {
    const [ancho, alto] = ventana.split('x').map(Number);
    const contexto = await navegador.newContext({ viewport: { width: ancho, height: alto }, locale: 'es-ES', acceptDownloads: true });
    const pagina = await contexto.newPage();
    const errores = [];
    const malas = [];
    const informe = { ventana, checks: [] };
    const check = (nombre, ok, detalle = '') => informe.checks.push({ nombre, ok: Boolean(ok), detalle });

    // ⚠️ Dos «no» del servidor son de esperar, y el navegador los apunta en la consola sin su URL (se descuentan por
    // cuenta): los 401 de `/me` sin sesión (el motor pregunta quién es al abrir) y el 404 de `/auth/google/pending` en su
    // puerta, que ES el desenlace «no hay ningún alta esperando» (`account/google.js`, el mismo del cajón).
    const esperados = { 401: 0, 404: 0 };
    pagina.on('pageerror', (e) => errores.push(e.message));
    pagina.on('console', (m) => { if (m.type() === 'error') errores.push(m.text()); });
    pagina.on('response', (r) => {
        const ruta = new URL(r.url()).pathname;
        if (r.status() === 401 && /\/api\/v1\/me(\/[a-z-]+)?$/.test(ruta)) esperados[401] += 1;
        else if (r.status() === 404 && ruta === '/api/v1/auth/google/pending') esperados[404] += 1;
        else if (r.status() >= 400 && ruta.startsWith('/api/')) malas.push(`${r.status()} ${r.request().method()} ${ruta}`);
    });

    // La capa GRANDE (Mi cuenta, la compra): el diálogo que se nombra con su banda. ⚠️ El panel del menú de la isla
    // también es un diálogo dentro de `[data-isla]`: sin el nombre, la sonda los confundía.
    const capa = () => pagina.locator('[data-isla] [role=dialog][aria-labelledby="isla-compra-paso"]');
    const banda = async () => (await pagina.locator('#isla-compra-paso').textContent({ timeout: 4000 }).catch(() => '')).trim();
    const titular = async () => (await capa().locator('h1').first().textContent({ timeout: 4000 }).catch(() => '')).trim();
    const lateralAbierto = () => pagina.evaluate(() => Boolean(document.querySelector('.sidecart.is-open, .sidecart[aria-hidden="false"]')));
    const captura = (paso) => pagina.screenshot({ path: `storage/app/audit/sonda-cuenta-${ancho}-${paso}.png` });
    const abrirMenu = async () => { await pagina.getByRole('button', { name: 'Menú, cuenta y Mi QR' }).first().click(); await pagina.waitForTimeout(300); };

    // ── 1 · Sin sesión ────────────────────────────────────────────────────────────────────────────────
    limitadoresACero();
    await pagina.goto(`${base}/kids`, { waitUntil: 'load' });
    await pagina.waitForTimeout(500);
    const rechazar = pagina.getByRole('button', { name: 'Rechazar' }).first();
    if (await rechazar.isVisible().catch(() => false)) { await rechazar.click(); await pagina.waitForTimeout(400); }

    await abrirMenu();
    await pagina.getByText('Entrar o crear cuenta').first().click();
    await capa().waitFor({ timeout: 8000 }).catch(() => {});
    check('sin sesión, el menú abre «Entra» en la capa de la isla, no el lateral', (await titular()) === 'Entra' && ! (await lateralAbierto()), `titular «${await titular()}»`);
    check('abierta desde el menú, lleva la flecha', await capa().getByRole('button', { name: 'Volver' }).isVisible());
    await captura('1-entra');

    await capa().getByText('Crea tu cuenta').click();
    await pagina.waitForTimeout(400);
    await captura('2-crea');
    check('«Crea tu cuenta» es un paso de la capa, con su acción', (await titular()) === 'Crea tu cuenta' && await capa().getByRole('button', { name: 'Crear mi cuenta' }).isVisible(), `titular «${await titular()}»`);
    await capa().getByRole('button', { name: 'Volver' }).click();
    await pagina.waitForTimeout(400);
    check('su flecha vuelve a «Entra»', (await titular()) === 'Entra');

    await capa().getByRole('button', { name: 'Volver' }).click();
    await pagina.waitForTimeout(500);
    check('la flecha de «Entra» cierra la capa y abre el menú de la isla', ! (await capa().isVisible().catch(() => false)) && await pagina.getByText('Entrar o crear cuenta').first().isVisible());

    // La puerta de completar el alta de Google (`/registro/google`): en la isla, con las piezas del sistema (`#773`·d). Sin
    // un viaje de verdad a Google no hay perfil esperando: sale su desenlace «ya no hay nada que completar».
    await pagina.goto(`${base}/registro/google`, { waitUntil: 'load' });
    await capa().waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(600);
    check('la puerta `/registro/google` abre «Completa tu registro» en la isla y, sin nada pendiente, lo dice',
        (await banda()) === 'Completa tu registro' && await capa().getByText('Ha pasado demasiado tiempo').isVisible() && ! (await lateralAbierto()), `banda «${await banda()}»`);
    await captura('0-alta-google');
    await pagina.goto(`${base}/kids`, { waitUntil: 'load' });
    await pagina.waitForTimeout(500);
    await abrirMenu();

    // ── 2 · Entrar ────────────────────────────────────────────────────────────────────────────────────
    await pagina.getByText('Entrar o crear cuenta').first().click();
    await capa().waitFor({ timeout: 8000 });
    limitadoresACero();
    await pagina.fill('#pjc-ent', CLIENTE.email);
    await pagina.fill('#pjc-ent-clave', CLIENTE.password);
    await Promise.all([
        pagina.waitForURL(/#mi-cuenta$/, { timeout: 15000 }).catch(() => {}),
        capa().getByRole('button', { name: 'Continuar' }).click(),
    ]);
    await pagina.waitForLoadState('load');
    await capa().waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(800);
    check('entrar recarga la MISMA página en Mi cuenta (`#mi-cuenta`)', new URL(pagina.url()).pathname === '/kids' && (await banda()) === 'Mi cuenta', `${pagina.url()} · banda «${await banda()}»`);
    check('«Hola, …» con su nombre de pila', (await titular()) === `Hola, ${CLIENTE.nombre}`, `titular «${await titular()}»`);
    const mini = await capa().locator('#mi-qr img').first().evaluate((img) => ({ ok: img.complete && img.naturalWidth > 0, src: img.getAttribute('src') })).catch(() => ({ ok: false, src: '' }));
    check('Tu QR compacto enseña la PNG del SERVIDOR (cargada)', mini.ok && /\/me\/card\/png/.test(mini.src), mini.src);
    check('abierta desde un enlace, solo la X (sin flecha)', ! (await capa().getByRole('button', { name: 'Volver' }).isVisible().catch(() => false)));
    await captura('3-mi-cuenta');

    // ── 3 · Tu QR ─────────────────────────────────────────────────────────────────────────────────────
    await capa().getByRole('button', { name: 'Enseñar mi QR' }).last().click();
    await pagina.waitForTimeout(600);
    const codigo = async () => (await capa().locator('#mi-qr b').first().textContent().catch(() => '')).trim();
    const antes = await codigo();
    const guardar = capa().getByRole('link', { name: 'Guardar en el móvil' });
    const descarga = await guardar.getAttribute('download').catch(() => null);
    check('Tu QR: la banda, el QR grande y el código en grupos de cuatro', (await banda()) === 'Tu QR' && /^[A-Z0-9]{4}( [A-Z0-9]{4}){4}$/.test(antes), `«${await banda()}» · «${antes}»`);
    check('«Guardar en el móvil» descarga la MISMA PNG', descarga === 'mi-qr.png' && /\/me\/card\/png/.test(await guardar.getAttribute('href') ?? ''));

    await capa().getByRole('button', { name: 'Renovar mi QR' }).click();
    await capa().getByRole('button', { name: 'Sí, renovar' }).click();
    await pagina.waitForTimeout(1200);
    const despues = await codigo();
    check('«Renovar mi QR» pregunta, renueva y lo confirma arriba', despues !== '' && despues !== antes && await capa().getByText('Tu QR se ha renovado').isVisible(), `${antes} → ${despues}`);
    await captura('4-tu-qr-renovado');
    await capa().getByRole('button', { name: 'Volver' }).click();
    await pagina.waitForTimeout(500);
    check('desde Mi cuenta, la flecha de Tu QR vuelve a Mi cuenta', (await banda()) === 'Mi cuenta');

    // ── 4 · La X y Mi QR del menú ────────────────────────────────────────────────────────────────────
    await capa().getByRole('button', { name: 'Cerrar' }).click();
    await pagina.waitForTimeout(500);
    check('la X cierra la capa y la dirección pierde `#mi-cuenta`', ! (await capa().isVisible().catch(() => false)) && ! pagina.url().includes('#'), pagina.url());
    await abrirMenu();
    await pagina.getByText('Mi QR', { exact: true }).first().click();
    await capa().waitFor({ timeout: 8000 }).catch(() => {});
    await pagina.waitForTimeout(500);
    check('«Mi QR» del menú abre Tu QR, con la flecha que vuelve al menú', (await banda()) === 'Tu QR' && await capa().getByRole('button', { name: 'Volver' }).isVisible(), `banda «${await banda()}»`);
    check('llegando de fuera, Tu QR ofrece «Ir a mi cuenta»', await capa().getByText('Ir a mi cuenta').isVisible());

    // ── 5 · Las puertas ──────────────────────────────────────────────────────────────────────────────
    await pagina.goto(`${base}/mi-cuenta`, { waitUntil: 'load' });
    await capa().waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(600);
    check('la puerta `/mi-cuenta` abre Mi cuenta en la isla, no el lateral', (await banda()) === 'Mi cuenta' && ! (await lateralAbierto()), `banda «${await banda()}»`);
    await pagina.goto(`${base}/kids#mi-cuenta/qr`, { waitUntil: 'load' });
    await capa().waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(600);
    check('el enlace `#mi-cuenta/qr` abre Tu QR', (await banda()) === 'Tu QR', `banda «${await banda()}»`);

    // ── 6 · Limpio ───────────────────────────────────────────────────────────────────────────────────
    const deCodigo = (codigo) => errores.filter((e) => e.includes(`status of ${codigo}`));
    const inesperados = errores.filter((e) => ! /status of (401|404)/.test(e))
        .concat(deCodigo(401).slice(esperados[401]), deCodigo(404).slice(esperados[404]));
    check('consola limpia', inesperados.length === 0, inesperados.join(' | '));
    check('ninguna respuesta de la API falla', malas.length === 0, malas.join(', '));

    await captura('5-enlace-qr');
    await contexto.close();

    return informe;
}

const navegador = await chromium.launch();
let fallos = 0;

try {
    for (const ventana of ['1280x860', '390x844']) {
        // Cada recorrido empieza sin sesión: el anterior entró.
        const informe = await recorrer(navegador, ventana);

        for (const c of informe.checks) {
            if (! c.ok) fallos += 1;
            console.log(`${c.ok ? '✓' : '✗'} ${ventana} · ${c.nombre}${c.detalle ? ` — ${c.detalle}` : ''}`);
        }
    }
} finally {
    await navegador.close();
}

console.log(fallos === 0 ? '\n✓ Mi cuenta en la isla, en vivo: todo en su sitio' : `\n✗ ${fallos} fallos`);
process.exit(fallos === 0 ? 0 : 1);
