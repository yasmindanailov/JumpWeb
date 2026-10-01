/**
 * SONDA DE LA A4b — Mi cuenta del cajón confirma con un CÓDIGO al correo (`docs/specs/acceso-con-codigo.md` §4.11, `#813`).
 * Lo que la suite no ve: el CABLEADO de las tres zonas y de su pieza (`account/ConfirmCode.vue`: qué hace cada botón y la
 * sexta cifra), que las acciones se hagan DE VERDAD en el servidor con el código leído del buzón, y que la contraseña ya no
 * aparezca por ningún sitio. En local, a 390 y a 1280, con los códigos leídos de Mailpit (`scripts/entrar-con-codigo.mjs`).
 *
 * ── EL RECORRIDO, EN CADA ANCHO ──────────────────────────────────────────────────────────────────
 *   1. Entrar con la cuenta de pruebas → el índice, sin «Cambiar contraseña».
 *   2. «Sesiones» (con OTRO dispositivo dentro, abierto antes): ningún campo de contraseña; «Para confirmarlo, te enviaremos
 *      un código a …» y «Enviarme el código» → el código de confirmar en el buzón, las seis casillas con el foco y «Te hemos
 *      enviado un código de 6 cifras a …», el botón ya con su acción y apagado sin las seis. «Pedir otro código» enseguida →
 *      «Espera N segundos…», sin borrar lo escrito. Un código que no es → su «no» bajo las casillas, vacío. El bueno, con la
 *      sexta → «Sesiones ✓», y el OTRO dispositivo, fuera (`/me` 401); éste, dentro.
 *   3. «Desvincular» (con una identidad de Google sembrada) → pide el suyo, las casillas bajo la lista; el bueno → la fila se va.
 *   4. «Privacidad y datos» → borrar: «Enviarme el código» → el código → con la sexta, la PREGUNTA (no borra) → «Cancelar»
 *      vuelve al formulario con el código escrito → la cuenta sigue ahí.
 *   5. «Tus datos» → cambiar el correo: ningún campo de contraseña; bajo el correo, «Para confirmarlo, te enviaremos un código
 *      a <el de ahora>»; «Enviarme el código» → el código al de AHORA → con la sexta, el cambio queda pendiente: el aviso con
 *      las casillas del código del NUEVO («… a <el nuevo>»), «Confirmar el correo» y «Pedir otro código» (enseguida → la
 *      espera); el código del buzón nuevo → el correo cambiado. Se devuelve con tinker.
 *   6. Borrar DE VERDAD, con una cuenta desechable: el código → «Sí, eliminar» → la portada, y la cuenta anonimizada.
 *   7. La consola, limpia, y ninguna respuesta de la API en rojo salvo las buscadas.
 *
 * ⚠️ SOLO EN LOCAL y con el CAJÓN (sin fila `sidebar.shell`): lo comprueba. Toca la cuenta de pruebas (`probe-card@jumpweb.test`):
 *    cierra sus otras sesiones, le siembra y quita una identidad de Google y le cambia el correo, que DEVUELVE al terminar (y al
 *    empezar, si una corrida se cortó). Las cuentas desechables (`sonda-a4b-*@jumpweb.test`) se ANONIMIZAN con la purga del
 *    producto (`User::anonymize()`, `RGPD-01`).
 * ⚠️ Captura de VENTANA (`#303`), con el ratón apartado: `storage/app/audit/sonda-cajon-a4b-<ancho>-<paso>.png`.
 *
 *   docker compose exec -u sail -T laravel.test node scripts/sonda-cajon-a4b.mjs
 *
 * Sale con 1 si algún punto falla; el informe, en `storage/app/audit/sonda-cajon-a4b.json`.
 */
/* global document -- dentro de `evaluate`, el navegador */
import process from 'node:process';
import { execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';
import { codigoDelBuzon, entrarConCodigo, limitadoresACero } from './entrar-con-codigo.mjs';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const SALIDA = 'storage/app/audit';
const CLIENTE = 'probe-card@jumpweb.test';
const VENTANAS = [{ width: 390, height: 844, hasTouch: true, isMobile: true }, { width: 1280, height: 900 }];
const USER = 'App\\Domain\\Identity\\Models\\User';

const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL: toca la cuenta de pruebas'); process.exit(1); }
if (tinker('echo App\\Domain\\Content\\Services\\ShellSettings::shell();') !== 'cajon') { console.error('✗ la carcasa no es el cajón (hay fila `sidebar.shell`)'); process.exit(1); }

/** La cuenta de pruebas, como estaba: su correo (si una corrida lo dejó cambiado), sin cambio pendiente y sin la identidad sembrada. */
function dejarComoEstaba() {
    tinker(`$u = ${USER}::where('email', 'like', 'sonda-a4b-nuevo-%')->first(); if ($u && ! ${USER}::where('email', '${CLIENTE}')->exists()) { $u->forceFill(['email' => '${CLIENTE}'])->save(); }`
        + ` ${USER}::where('email', '${CLIENTE}')->update(['pending_email' => null, 'pending_email_sent_at' => null]);`
        + ' App\\Domain\\Identity\\Models\\UserIdentity::where(\'provider_id\', \'like\', \'sonda-a4b-%\')->delete();');
}

/** Las cuentas desechables, fuera, con la purga del producto. Devuelve cuántas. */
const anonimizarLasDeLaSonda = () => Number(tinker(`echo ${USER}::where('email', 'like', 'sonda-a4b-borrar-%@jumpweb.test')->get()->each(fn ($u) => $u->anonymize())->count();`));

/** El limitador de la API por IP (60 por minuto): la sonda hace muchas peticiones seguidas, y no puede decidir un 429. */
const apiACero = () => tinker(`Illuminate\\Support\\Facades\\RateLimiter::clear(md5('api'.'ip:127.0.0.1'));`);

/** Los topes de pedir un código de confirmar (por cuenta) y de reenviar el del correo nuevo: cada paso empieza sin ellos. */
const confirmarACero = (correo) => tinker(`$h = App\\Domain\\Identity\\Services\\SelfSignup::emailHash('${correo}'); $id = ${USER}::where('email', '${correo}')->value('id'); foreach (['confirm-code|'.$h, 'confirm-code-hour|'.$h, 'pending-email-resend:'.$id, 'pending-email-resend-hour:'.$id] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }`);

/** Siembra una identidad de Google en la cuenta (en local Google está apagado: la sonda no puede vincularla de verdad). */
const sembrarGoogle = () => tinker(`App\\Domain\\Identity\\Models\\UserIdentity::create(['user_id' => ${USER}::where('email', '${CLIENTE}')->value('id'), 'provider' => 'google', 'provider_id' => 'sonda-a4b-'.uniqid(), 'email_at_link' => '${CLIENTE}', 'linked_via' => 'account']);`);

const texto = async (loc) => ((await loc.innerText({ timeout: 4000 }).catch(() => '')) ?? '').replace(/\s+/g, ' ').trim();

async function sinVelo(page) {
    await page.waitForSelector('.jj-loading', { state: 'hidden' }).catch(() => {});
    await page.waitForFunction(() => ! document.querySelector('.sidecart__panel .jj-spinner')).catch(() => {});
}

async function cookies(page) {
    const boton = page.locator('.cookie-consent-root button:visible', { hasText: /^(acept|rechaz)/i }).first();

    if (await boton.isVisible({ timeout: 3000 }).catch(() => false)) await boton.click({ timeout: 3000 }).catch(() => {});
}

/** Entra en Mi cuenta por `/login` con el código del buzón y espera al índice. */
async function entrarEnLaCuenta(page, correo) {
    apiACero();
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    await cookies(page);
    await page.locator('.sidecart__panel #login-email').waitFor({ timeout: 20000 });
    await sinVelo(page);
    await entrarConCodigo(page, correo);
    await page.waitForSelector('.sidecart.is-open .acc-tiles', { timeout: 20000 });
    await sinVelo(page);
}

/** Del índice a una zona por su tarjeta. */
async function aLaZona(page, rotulo) {
    await page.locator('.acc-tile', { hasText: rotulo }).first().click();
    await page.locator('.sidecart.is-open .wiz__title', { hasText: rotulo }).first().waitFor({ timeout: 10000 }).catch(() => {});
    await sinVelo(page);
}

/** Vuelve al índice con «Volver». */
async function alIndice(page) {
    await page.locator('.account__back').click();
    await page.waitForSelector('.sidecart.is-open .acc-tiles', { timeout: 10000 });
}

async function recorrer(navegador, ventana, informe) {
    const { width, height, ...tacto } = ventana;
    const check = (nombre, ok, detalle = '') => informe.checks.push({ nombre, ok: Boolean(ok), detalle: String(detalle) });
    const errores = [];
    const malas = [];
    // Los «no» BUSCADOS, por ruta: pedir otro antes del minuto (429) y un código que no es (422).
    const buscadas = new Map();
    const buscar = (clave) => buscadas.set(clave, (buscadas.get(clave) ?? 0) + 1);

    const nueva = async ({ vigilar = true } = {}) => {
        const contexto = await navegador.newContext({ viewport: { width, height }, ...tacto, locale: 'es-ES' });
        const page = await contexto.newPage();
        page.setDefaultTimeout(12000);
        if (! vigilar) return { contexto, page };
        page.on('pageerror', (e) => errores.push(e.message.split('\n')[0]));
        page.on('console', (m) => { if (m.type() === 'error' && ! /status of \d{3}/.test(m.text())) errores.push(m.text().split('\n')[0]); });
        page.on('response', (r) => {
            const url = new URL(r.url());
            if (url.origin !== new URL(BASE).origin || ! url.pathname.startsWith('/api/') || r.status() < 400) return;
            if (r.status() === 401 && /^\/api\/v1\/me(\/[a-z-]+)?$/.test(url.pathname)) return;
            const clave = `${r.status()} ${r.request().method()} ${url.pathname}`;
            if ((buscadas.get(clave) ?? 0) > 0) { buscadas.set(clave, buscadas.get(clave) - 1); return; }
            malas.push(clave);
        });

        return { contexto, page };
    };
    const captura = async (page, paso) => {
        await page.mouse.move(0, 0).catch(() => {});
        await page.waitForTimeout(350);
        await page.screenshot({ path: `${SALIDA}/sonda-cajon-a4b-${width}-${paso}.png` });
    };
    /** Espera a que la API haya dado el «no» buscado (o 8 s). */
    const hastaQue = async (page, clave) => { for (let i = 0; i < 32 && (buscadas.get(clave) ?? 0) > 0; i += 1) await page.waitForTimeout(250); return (buscadas.get(clave) ?? 0) === 0; };

    dejarComoEstaba();
    // ⚠️ ANTES de entrar: la zona pide las vinculadas UNA vez (`ensureIdentities`), y sembrarla a mitad no se vería. En la
    // vida real vincular es una ida a Google, que vuelve con la página recargada.
    sembrarGoogle();

    // El OTRO dispositivo: dentro ANTES de cerrar las otras sesiones (no se vigila: su 401 es lo que se busca).
    const otro = await nueva({ vigilar: false });
    await entrarEnLaCuenta(otro.page, CLIENTE).catch((e) => check('el otro dispositivo entra', false, e.message));
    const meDelOtro = () => otro.page.evaluate(async () => (await fetch('/api/v1/me', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).status);
    check('el otro dispositivo está dentro antes de empezar', (await meDelOtro()) === 200);

    const { contexto, page } = await nueva();
    const panel = page.locator('.sidecart__panel');

    // ── 1 · El índice, sin «Cambiar contraseña» ─────────────────────────────────────────────────────
    await entrarEnLaCuenta(page, CLIENTE).catch((e) => check('entrar en Mi cuenta', false, e.message));
    const tarjetas = await page.locator('.acc-tile').allInnerTexts();
    check('el índice ya no ofrece «Cambiar contraseña», y sí «Sesiones» y «Privacidad y datos»',
        ! tarjetas.some((t) => /contraseña/i.test(t)) && tarjetas.some((t) => t.includes('Sesiones')) && tarjetas.some((t) => t.includes('Privacidad')),
        tarjetas.map((t) => t.split('\n')[0]).join(' · '));
    await captura(page, '1-indice');

    // ── 2 · Sesiones: cerrar las otras con su código ────────────────────────────────────────────────
    await aLaZona(page, 'Sesiones');
    check('«Sesiones» no pide la contraseña en ningún sitio', ! (await panel.locator('input[type="password"]').count()));
    const frase = await texto(panel.locator('.auth__form .form__hint').first());
    check('antes de pedirlo, solo a dónde irá: «Para confirmarlo, te enviaremos un código a …»',
        frase === `Para confirmarlo, te enviaremos un código a ${CLIENTE}.` && ! (await panel.locator('.code-input').count()), `«${frase}»`);
    const cerrar = panel.locator('.auth__form .auth__submit');
    check('el botón dice «Enviarme el código»', (await texto(cerrar)) === 'Enviarme el código', await texto(cerrar));
    await captura(page, '2-sesiones');

    confirmarACero(CLIENTE);
    let desde = Date.now();
    await cerrar.click();
    await panel.locator('#acct-sessions-code').waitFor({ timeout: 10000 }).catch(() => {});
    const codigoSesiones = await codigoDelBuzon(CLIENTE, desde);
    const pista = await texto(panel.locator('.code-input .form__hint'));
    check('pedido: el código en el buzón y las seis casillas con «Te hemos enviado un código de 6 cifras a …»',
        codigoSesiones !== null && (await panel.locator('.code-input__box').count()) === 6 && pista === `Te hemos enviado un código de 6 cifras a ${CLIENTE}.`,
        `${codigoSesiones ?? 'sin código'} · «${pista}»`);
    check('el foco va a las casillas', await page.evaluate(() => document.activeElement?.id === 'acct-sessions-code'));
    check('el botón ya dice la acción y va apagado sin las seis',
        (await texto(cerrar)) === 'Cerrar las otras sesiones' && await cerrar.isDisabled(), await texto(cerrar));
    // ⚠️ El texto largo de antes («Cerrar sesión en los demás dispositivos») se salía del botón a 390 (lo vio esta sonda).
    const cabe = await cerrar.evaluate((b) => b.scrollWidth <= b.clientWidth);
    check('y su texto CABE en el botón (sin cortarse)', cabe);

    await panel.locator('#acct-sessions-code').fill('4829');
    buscar('429 POST /api/v1/me/confirm-code');
    await panel.locator('.code-input .auth__switch button').click();
    await hastaQue(page, '429 POST /api/v1/me/confirm-code');
    const espera = await texto(panel.locator('.code-input .form__error'));
    const segundos = Number(/^Espera (\d+) segundos para pedir otro código\.$/.exec(espera)?.[1] ?? Number.NaN);
    check('«Pedir otro código» enseguida: «Espera N segundos…» bajo las casillas, sin «otro» y SIN borrar lo escrito (`#812`)',
        segundos > 0 && segundos <= 60 && (await texto(panel.locator('.code-input .form__hint, .code-input .form__error').first())) === espera
            && (await panel.locator('#acct-sessions-code').inputValue()) === '4829', `«${espera}»`);
    await captura(page, '2b-espera');

    const malo = codigoSesiones === '000000' ? '111111' : '000000';
    buscar('422 POST /api/v1/me/sessions/revoke-others');
    await panel.locator('#acct-sessions-code').fill(malo);
    const llego = await hastaQue(page, '422 POST /api/v1/me/sessions/revoke-others');
    const noes = await texto(panel.locator('.code-input .form__error'));
    check('un código que no es se prueba SOLO con la sexta: su «no» bajo las casillas, y se VACÍAN',
        llego && noes === 'El código no es correcto o ha caducado. Pide otro.' && (await panel.locator('#acct-sessions-code').inputValue()) === '', `«${noes}»`);
    await captura(page, '2c-no');

    await panel.locator('#acct-sessions-code').fill(codigoSesiones ?? '');
    await panel.locator('[role="status"]', { hasText: 'Sesiones ✓' }).waitFor({ timeout: 10000 }).catch(() => {});
    check('el bueno, con la sexta: «Sesiones ✓» y las casillas se van', await panel.locator('[role="status"]', { hasText: 'Sesiones ✓' }).isVisible().catch(() => false)
        && ! (await panel.locator('#acct-sessions-code').count()));
    check('y DE VERDAD: el otro dispositivo, fuera (`/me` 401)', (await meDelOtro()) === 401);
    check('este dispositivo sigue dentro', (await page.evaluate(async () => (await fetch('/api/v1/me', { headers: { Accept: 'application/json' } })).status)) === 200);
    await captura(page, '2d-hecho');
    await otro.contexto.close();

    // ── 3 · Desvincular Google con su código (en la misma zona) ────────────────────────────────────
    const desvincular = panel.locator('.account__consents button', { hasText: 'Desvincular' });
    await desvincular.waitFor({ timeout: 10000 }).catch(() => {});
    check('con una cuenta de Google vinculada, su fila con «Desvincular»', await desvincular.isVisible().catch(() => false));
    confirmarACero(CLIENTE);
    desde = Date.now();
    await desvincular.click();
    await panel.locator('#acct-unlink-code').waitFor({ timeout: 10000 }).catch(() => {});
    const codigoGoogle = await codigoDelBuzon(CLIENTE, desde);
    check('«Desvincular» pide SU código, y las casillas salen bajo la lista (no en el formulario de cerrar)',
        codigoGoogle !== null && await panel.locator('.account__linked #acct-unlink-code').count() === 1 && ! (await panel.locator('#acct-sessions-code').count()));
    check('mientras, la fila no deja desvincular sin las seis', await desvincular.isDisabled());
    await captura(page, '3-desvincular');
    await panel.locator('#acct-unlink-code').fill(codigoGoogle ?? '');
    await desvincular.waitFor({ state: 'detached', timeout: 10000 }).catch(() => {});
    const quedan = Number(tinker(`echo App\\Domain\\Identity\\Models\\UserIdentity::where('user_id', ${USER}::where('email', '${CLIENTE}')->value('id'))->count();`));
    check('con la sexta, la fila se va y DE VERDAD no queda ninguna vinculada', ! (await desvincular.count()) && quedan === 0, `${quedan} en la BD`);

    // ── 4 · Privacidad: el código y después la pregunta, sin borrar ─────────────────────────────────
    await alIndice(page);
    await aLaZona(page, 'Privacidad');
    const peligro = panel.locator('.account__card--danger');
    check('borrar la cuenta no pide la contraseña', ! (await peligro.locator('input[type="password"]').count()));
    const borrar = peligro.locator('.account__delete-btn');
    check('antes de pedirlo, la frase y «Enviarme el código»',
        (await texto(peligro.locator('.form__hint').first())) === `Para confirmarlo, te enviaremos un código a ${CLIENTE}.` && (await texto(borrar)) === 'Enviarme el código');
    confirmarACero(CLIENTE);
    desde = Date.now();
    await borrar.click();
    await peligro.locator('#acct-delete-code').waitFor({ timeout: 10000 }).catch(() => {});
    const codigoBorrar = await codigoDelBuzon(CLIENTE, desde);
    check('pedido: las casillas y «Eliminar mi cuenta», apagado sin las seis',
        codigoBorrar !== null && (await texto(borrar)) === 'Eliminar mi cuenta' && await borrar.isDisabled());
    await peligro.locator('#acct-delete-code').fill(codigoBorrar ?? '');
    const pregunta = peligro.locator('.purchase__confirm');
    await pregunta.waitFor({ timeout: 8000 }).catch(() => {});
    check('la sexta NO borra: abre la pregunta de siempre', await pregunta.isVisible().catch(() => false)
        && (await texto(pregunta.locator('p'))) === '¿Seguro que quieres eliminar tu cuenta? Esta acción es permanente.');
    await captura(page, '4-pregunta');
    await pregunta.locator('.btn--ghost').click();
    await peligro.locator('#acct-delete-code').waitFor({ timeout: 8000 }).catch(() => {});
    check('«Cancelar» vuelve al formulario, con el código escrito', (await peligro.locator('#acct-delete-code').inputValue().catch(() => '')) === codigoBorrar);
    check('y la cuenta sigue ahí', (await page.evaluate(async () => (await fetch('/api/v1/me', { headers: { Accept: 'application/json' } })).status)) === 200);

    // ── 5 · Tus datos: el correo nuevo en tres tiempos ─────────────────────────────────────────────
    await alIndice(page);
    await aLaZona(page, 'Tus datos');
    const nuevo = `sonda-a4b-nuevo-${width}-${Date.now()}@jumpweb.test`;
    check('«Tus datos» no pide la contraseña, ni con el correo cambiado', ! (await panel.locator('input[type="password"]').count()));
    await panel.locator('#acct-email').fill(nuevo);
    const guardar = panel.locator('.auth__form .auth__submit');
    const bajoElCorreo = await texto(panel.locator('.auth__form .form__hint', { hasText: 'Para confirmarlo' }));
    check('con el correo cambiado: bajo él, el código irá al de AHORA, y el botón dice «Enviarme el código»',
        bajoElCorreo === `Para confirmarlo, te enviaremos un código a ${CLIENTE}.` && (await texto(guardar)) === 'Enviarme el código' && ! (await panel.locator('input[type="password"]').count()),
        `«${bajoElCorreo}» · «${await texto(guardar)}»`);
    await captura(page, '6-correo');
    confirmarACero(CLIENTE);
    desde = Date.now();
    await guardar.click();
    await panel.locator('#acct-email-code').waitFor({ timeout: 10000 }).catch(() => {});
    const codigoAhora = await codigoDelBuzon(CLIENTE, desde);
    check('pedido al de AHORA: las casillas bajo el correo y «Guardar cambios»', codigoAhora !== null && (await texto(guardar)) === 'Guardar cambios');
    desde = Date.now();
    await panel.locator('#acct-email-code').fill(codigoAhora ?? '');
    const aviso = panel.locator('.auth__errors[role="status"]');
    await aviso.locator('#acct-pending-code').waitFor({ timeout: 10000 }).catch(() => {});
    const avisoTexto = await texto(aviso);
    const pistaNuevo = await texto(aviso.locator('.code-input .form__hint'));
    check('con la sexta, el cambio queda PENDIENTE: el aviso dice a cuál, y las casillas del código del NUEVO',
        avisoTexto.includes(`Falta confirmar ${nuevo}`) && avisoTexto.includes(`sigues entrando con ${CLIENTE}`) && pistaNuevo === `Te hemos enviado un código de 6 cifras a ${nuevo}.`,
        `«${avisoTexto.slice(0, 160)}»`);
    const primeroNuevo = await codigoDelBuzon(nuevo, desde);
    check('el código del nuevo llega a SU buzón', primeroNuevo !== null);
    const confirmarCorreo = aviso.locator('button', { hasText: 'Confirmar el correo' });
    check('«Confirmar el correo», apagado sin las seis', await confirmarCorreo.isDisabled().catch(() => false));
    await captura(page, '6b-pendiente');

    // ⚠️ El servidor cuenta los REENVÍOS (uno por minuto), no el código que salió con el cambio: el primer «otro» SALE —y
    // anula el anterior—; el segundo, enseguida, espera. Medido por esta sonda en su primera corrida (el 422 de más).
    const otroNuevo = aviso.locator('.code-input .auth__switch button');
    desde = Date.now();
    await otroNuevo.click();
    await aviso.locator('.code-input .form__hint', { hasText: 'otro código' }).waitFor({ timeout: 10000 }).catch(() => {});
    // ⚠️ El lector acepta lo llegado hasta 2 s ANTES de pedirlo (el reloj de Mailpit), y el del cambio salió hace menos: sin
    // esperar a uno DISTINTO leía el anterior —ya anulado— cuando el nuevo aún no había llegado (medido: a 1280, un 422).
    let codigoNuevo = await codigoDelBuzon(nuevo, desde);
    for (let i = 0; i < 40 && codigoNuevo === primeroNuevo; i += 1) { await page.waitForTimeout(250); codigoNuevo = await codigoDelBuzon(nuevo, desde); }
    check('el primer «Pedir otro código» del nuevo SALE: «Te hemos enviado otro código a <el nuevo>», y llega',
        (await texto(aviso.locator('.code-input .form__hint'))) === `Te hemos enviado otro código a ${nuevo}.` && codigoNuevo !== null,
        await texto(aviso.locator('.code-input .form__hint')));
    buscar('429 POST /api/v1/me/pending-email/resend');
    await otroNuevo.click();
    await hastaQue(page, '429 POST /api/v1/me/pending-email/resend');
    const esperaNuevo = await texto(aviso.locator('.code-input .form__error'));
    check('y el segundo, enseguida: la espera bajo sus casillas', /^Espera \d+ segundos para pedir otro código\.$/.test(esperaNuevo), `«${esperaNuevo}»`);

    const maloNuevo = codigoNuevo === '000000' ? '111111' : '000000';
    buscar('422 POST /api/v1/me/pending-email/confirm');
    await aviso.locator('#acct-pending-code').fill(maloNuevo);
    await hastaQue(page, '422 POST /api/v1/me/pending-email/confirm');
    check('un código del nuevo que no es: su «no» bajo las casillas, vacías',
        (await texto(aviso.locator('.code-input .form__error'))) === 'El código no es correcto o ha caducado. Pide otro.' && (await aviso.locator('#acct-pending-code').inputValue()) === '');
    await aviso.locator('#acct-pending-code').fill(codigoNuevo ?? '');
    await aviso.waitFor({ state: 'detached', timeout: 10000 }).catch(() => {});
    check('el bueno: el aviso se va y el correo es el NUEVO, en la pantalla y en la BD',
        ! (await aviso.count()) && (await panel.locator('#acct-email').inputValue()) === nuevo
            && tinker(`echo ${USER}::where('email', '${nuevo}')->exists() ? '1' : '0';`) === '1');
    await captura(page, '6c-cambiado');
    dejarComoEstaba();
    await contexto.close();

    // ── 6 · Borrar DE VERDAD, con una cuenta desechable ──────────────────────────────────────────────
    {
        const desechable = `sonda-a4b-borrar-${width}-${Date.now()}@jumpweb.test`;
        tinker(`(new ${USER})->forceFill(['name' => 'Sonda A4b', 'email' => '${desechable}', 'email_verified_at' => now(), 'password' => null])->save();`);
        limitadoresACero(desechable);
        const { contexto: c2, page: p2 } = await nueva();
        await entrarEnLaCuenta(p2, desechable).catch((e) => check('la desechable entra', false, e.message));
        await aLaZona(p2, 'Privacidad');
        const peligro2 = p2.locator('.sidecart__panel .account__card--danger');
        confirmarACero(desechable);
        desde = Date.now();
        await peligro2.locator('.account__delete-btn').click();
        await peligro2.locator('#acct-delete-code').waitFor({ timeout: 10000 }).catch(() => {});
        await peligro2.locator('#acct-delete-code').fill((await codigoDelBuzon(desechable, desde)) ?? '');
        await peligro2.locator('.purchase__confirm .account__delete-btn').click();
        await p2.waitForURL(`${BASE}/`, { timeout: 15000 }).catch(() => {});
        const queda = tinker(`echo ${USER}::where('email', '${desechable}')->exists() ? '1' : '0';`);
        check('borrar DE VERDAD: el código, «Sí, eliminar» → la portada, y la cuenta anonimizada', new URL(p2.url()).pathname === '/' && queda === '0', `${p2.url()} · ${queda === '1' ? 'sigue' : 'anonimizada'}`);
        await c2.close();
    }

    check('la consola, limpia', errores.length === 0, errores.slice(0, 5).join(' | '));
    check('ninguna respuesta de la API en rojo salvo las buscadas', malas.length === 0, malas.slice(0, 5).join(' | '));
}

await mkdir(SALIDA, { recursive: true });
anonimizarLasDeLaSonda();

const informe = { cuando: new Date().toISOString(), anchos: [] };
const navegador = await chromium.launch();

try {
    for (const ventana of VENTANAS) {
        const ancho = { ancho: ventana.width, checks: [] };
        informe.anchos.push(ancho);
        await recorrer(navegador, ventana, ancho).catch((e) => ancho.checks.push({ nombre: 'el recorrido no se cortó', ok: false, detalle: e.message.split('\n')[0] }));
    }
} finally {
    await navegador.close();
    dejarComoEstaba();
    informe.anonimizadas = anonimizarLasDeLaSonda();
}

await writeFile(`${SALIDA}/sonda-cajon-a4b.json`, `${JSON.stringify(informe, null, 2)}\n`);

let fallos = 0;
for (const { ancho, checks } of informe.anchos) {
    console.log(`── ${ancho} px`);
    for (const { nombre, ok, detalle } of checks) {
        if (! ok) fallos += 1;
        console.log(`  ${ok ? '✓' : '✗'} ${nombre}${! ok && detalle ? `  → ${detalle}` : ''}`);
    }
}
console.log(`\n${fallos === 0 ? '✓' : '✗'} ${fallos} fallos · ${informe.anonimizadas} cuentas desechables anonimizadas · ${SALIDA}/sonda-cajon-a4b.json`);
process.exit(fallos === 0 ? 0 : 1);
