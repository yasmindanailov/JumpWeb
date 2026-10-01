/**
 * SONDA DE LA A4a — el cajón entra y crea cuenta con un CÓDIGO al correo (`docs/specs/acceso-con-codigo.md` §4.11,
 * `#848`/`#849`/`#858`). Lo que la suite no ve: el CABLEADO de la compra y de Mi cuenta (qué hace cada botón de la
 * puerta), el reloj de «Pedir otro código» corriendo en el navegador, «¿Querías decir…?» bajando al salir del campo
 * (`import()`), la cookie de recordar SOLO con la casilla y el alta que no espera a ningún correo. En local, a 390 y a
 * 1280, con los códigos leídos de Mailpit (`scripts/entrar-con-codigo.mjs`).
 *
 * ── EL RECORRIDO, EN CADA ANCHO ──────────────────────────────────────────────────────────────────
 *   1. `/login` sin sesión → la puerta en su primera cara: «Entra», el correo y «Continuar»; ni pestañas ni contraseña.
 *      Una errata («gmial.com») propone el correo bueno al salir del campo, y aceptarla lo escribe.
 *   2. «Continuar» con la cuenta de pruebas → la cara del código, con el `CodeInput` del diseño (`#861`): «Te hemos enviado
 *      un código de 6 cifras a …» con «Cambiar el correo» al lado, el foco en el campo, seis casillas con su guion y sin decir
 *      cuánto dura, «Mantener la sesión iniciada…» SIN marcar, «Pedir otro código» a mano desde el primer momento —como la
 *      isla, `#812`: pulsado enseguida, «Espera N segundos…» bajo el código y el aviso sin «otro»— y «Entrar» apagado con
 *      cinco cifras.
 *   3. Un código que no es se comprueba SOLO con la sexta cifra → su «no» bajo las casillas, y el código se vacía. El bueno →
 *      Mi cuenta. Sin la casilla, NINGUNA cookie de recuerdo.
 *   4. Con esa sesión, «Cambiar contraseña» → el aviso de quien no tiene una → «Recupera tu contraseña», con sus textos
 *      (desde la A4a viajan solo con sesión).
 *   5. `/recuperar-contrasena` y `/registro`, sin sesión, abren la PUERTA.
 *   6. La compra: `/entradas` → producto, día, hora y a la cesta → «ir a pagar» → la puerta en el paso 5; con la casilla
 *      MARCADA → la compra sigue (la cuenta de pruebas tiene menores: vuelve al carrito a asignarlos, `#202`), y queda la
 *      cookie de recuerdo, de ~90 días.
 *   7. El alta en la compra con un correo NUEVO: la cara del alta (el correo a la vista, sin contraseña ni teléfono) y
 *      ningún código en su buzón; «Cambiar el correo» vuelve a la puerta con el correo escrito; el alta → «Pagar».
 *   8. El alta en Mi cuenta (`/registro`) con otro correo nuevo → la cuenta al momento, con «Debes verificar tu correo».
 *   9. La consola, limpia, y ninguna respuesta de la API en rojo salvo las buscadas: el 401 de `/me` sin sesión (el motor
 *      pregunta quién es al abrir) y el del código que no es.
 *
 * ⚠️ SOLO EN LOCAL y con el CAJÓN (sin fila `sidebar.shell`): lo comprueba. Las cuentas que crea (`sonda-a4a-*@jumpweb.test`)
 *    se ANONIMIZAN al terminar con la purga del producto (`User::anonymize()`, `RGPD-01`), y las de una corrida cortada, al
 *    empezar. La cesta de la cuenta de pruebas se queda con su línea (las demás sondas ya cuentan con eso).
 * ⚠️ Captura de VENTANA, no de elemento (`#303`), con el ratón apartado: `storage/app/audit/sonda-cajon-a4a-<ancho>-<paso>.png`.
 *
 *   docker compose exec -u sail -T laravel.test node scripts/sonda-cajon-a4a.mjs
 *
 * Sale con 1 si algún punto falla; el informe, en `storage/app/audit/sonda-cajon-a4a.json`.
 */
/* global document -- dentro de `evaluate`, el navegador */
import process from 'node:process';
import { execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright-core';
import { MAILPIT, codigoDelBuzon, entrarConCodigo, limitadoresACero } from './entrar-con-codigo.mjs';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const SALIDA = 'storage/app/audit';
const CLIENTE = 'probe-card@jumpweb.test';
const VENTANAS = [{ width: 390, height: 844, hasTouch: true, isMobile: true }, { width: 1280, height: 900 }];

const tinker = (php) => execFileSync('php', ['artisan', 'tinker', '--execute', php], { encoding: 'utf8' }).trim();

if (tinker('echo app()->environment();') !== 'local') { console.error('✗ solo en LOCAL: crea cuentas'); process.exit(1); }
if (tinker('echo App\\Domain\\Content\\Services\\ShellSettings::shell();') !== 'cajon') { console.error('✗ la carcasa no es el cajón (hay fila `sidebar.shell`)'); process.exit(1); }

/** Las cuentas de la sonda, fuera, con la purga del producto (no con un borrado a mano). Devuelve cuántas. */
const anonimizarLasDeLaSonda = () => Number(tinker(`echo App\\Domain\\Identity\\Models\\User::where('email', 'like', 'sonda-a4a-%@jumpweb.test')->get()->each(fn ($u) => $u->anonymize())->count();`));

/** El limitador de la API por IP (60 por minuto): la sonda hace muchas peticiones seguidas, y no puede decidir un 429. */
const apiACero = () => tinker(`Illuminate\\Support\\Facades\\RateLimiter::clear(md5('api'.'ip:127.0.0.1'));`);

/** Y el del alta por IP: la sonda da cuatro altas por corrida. */
const altaACero = () => tinker(`Illuminate\\Support\\Facades\\RateLimiter::clear('register:127.0.0.1');`);

/**
 * ¿El alta de esta instalación exige aceptar el descargo? La misma regla que el servidor (`AuthRegistrationController`).
 * ⚠️ La casilla aparece cuando llega `GET /legal/waiver`, DESPUÉS de pintarse el alta: mirar si existe al rellenar es una
 * carrera (medida: a 390 perdió una de tres corridas y el alta dio su 422). Sabiéndolo por el servidor, se ESPERA.
 */
const DESCARGO = tinker(`echo App\\Domain\\Identity\\Services\\WaiverSettings::isInternal() && App\\Domain\\Identity\\Services\\LegalDocuments::current('waiver', 'es') !== null ? '1' : '0';`) === '1';

/** Los asuntos de lo que ha llegado a `correo` (para ver que al alta NO le llega ningún código). */
const asuntosDe = async (correo) => ((await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(`to:"${correo}"`)}&limit=20`)
    .then((r) => r.json()).catch(() => null))?.messages ?? []).map((m) => m.Subject);

/** El texto de un trozo, en una línea (`\s` incluye los espacios duros). */
const texto = async (loc) => ((await loc.innerText({ timeout: 4000 }).catch(() => '')) ?? '').replace(/\s+/g, ' ').trim();

/** Espera a que se vaya el velo de carga (por CONDICIÓN, nunca por reloj). */
async function sinVelo(page) {
    await page.waitForSelector('.jj-loading', { state: 'hidden' }).catch(() => {});
    await page.waitForFunction(() => ! document.querySelector('.sidecart__panel .jj-spinner')).catch(() => {});
}

/** El aviso de cookies tapa el cajón en móvil: se cierra con cualquiera de sus dos botones visibles, si está. */
async function cookies(page) {
    const boton = page.locator('.cookie-consent-root button:visible', { hasText: /^(acept|rechaz)/i }).first();

    if (await boton.isVisible({ timeout: 3000 }).catch(() => false)) await boton.click({ timeout: 3000 }).catch(() => {});
}

/** `/entradas` → producto, día, hora y a la cesta → «ir a pagar»: la puerta del paso 5, sin sesión. */
async function aLaPuertaDeLaCompra(page) {
    apiACero();
    await page.goto(`${BASE}/entradas`, { waitUntil: 'domcontentloaded' });
    await cookies(page);
    await page.waitForSelector('.sidecart.is-open .purchase[data-engine="spa"]', { timeout: 20000 });
    await page.waitForSelector('.catalog__item');
    const cabecera = page.locator('.catalog-acc__head').first();
    if (await cabecera.count() && await cabecera.getAttribute('aria-expanded') === 'false') await cabecera.click();
    await page.locator('.catalog__item').first().click();
    await page.locator('.daystrip__day').first().click();
    await page.locator('.purchase__chip:not(.is-full)').first().click();
    await page.waitForSelector('.qtybox');
    await page.locator('.bk-cta').click();
    await page.waitForSelector('.cartbar, .cart__item');
    if (await page.locator('.cartbar').count()) await page.locator('.cartbar').click();
    await page.waitForSelector('.cart__item');
    await page.locator('.bk-cta').click();
    await page.waitForSelector('.sidecart__panel .auth', { timeout: 15000 });
    await sinVelo(page);
}

/** Rellena y envía la cara del alta (el nombre y, si la instalación lo pide, el descargo). */
async function darseDeAlta(puerta) {
    await puerta.locator('#reg-name').fill('Sonda A4a');
    if (DESCARGO) {
        const descargo = puerta.locator('.form__checks .check input[type="checkbox"]');
        await descargo.waitFor({ timeout: 8000 });
        await descargo.check();
    }
    altaACero();
    await puerta.locator('.auth__submit').click();
}

async function recorrer(navegador, ventana, informe) {
    const { width, height, ...tacto } = ventana;
    const check = (nombre, ok, detalle = '') => informe.checks.push({ nombre, ok: Boolean(ok), detalle: String(detalle) });
    const errores = [];
    const malas = [];
    // Los «no» BUSCADOS: el del código que no es (paso 3) y el del servidor al pedir otro antes del minuto (paso 2).
    let codigoMalo = 0;
    let limitado = 0;

    const nueva = async () => {
        const contexto = await navegador.newContext({ viewport: { width, height }, ...tacto, locale: 'es-ES' });
        const page = await contexto.newPage();
        page.setDefaultTimeout(12000);
        page.on('pageerror', (e) => errores.push(e.message.split('\n')[0]));
        // Las respuestas en rojo se juzgan en `response`, con su ruta: la consola solo las repite sin ella.
        page.on('console', (m) => { if (m.type() === 'error' && ! /status of \d{3}/.test(m.text())) errores.push(m.text().split('\n')[0]); });
        // Se juzga la API, como en `sonda-cuenta.mjs`: las imágenes de las atracciones de la landing son del CLIENTE (no
        // viajan en el producto) y en un clon sin su paquete dan 404 sin que la A4a tenga nada que ver.
        page.on('response', (r) => {
            const url = new URL(r.url());
            if (url.origin !== new URL(BASE).origin || ! url.pathname.startsWith('/api/') || r.status() < 400) return;
            if (r.status() === 401 && /^\/api\/v1\/me(\/[a-z-]+)?$/.test(url.pathname)) return;
            if ([401, 422].includes(r.status()) && url.pathname === '/api/v1/auth/login' && codigoMalo > 0) { codigoMalo -= 1; return; }
            if (r.status() === 429 && url.pathname === '/api/v1/auth/code' && limitado > 0) { limitado -= 1; return; }
            malas.push(`${r.status()} ${r.request().method()} ${url.pathname}`);
        });

        return { contexto, page };
    };
    const captura = async (page, paso) => {
        await page.mouse.move(0, 0).catch(() => {});
        await page.waitForTimeout(350);
        await page.screenshot({ path: `${SALIDA}/sonda-cajon-a4a-${width}-${paso}.png` });
    };

    // ── 1-4 · Mi cuenta: la puerta, el código, entrar y recuperar con sesión ───────────────────────────
    {
        const { contexto, page } = await nueva();
        apiACero();
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        await cookies(page);
        const puerta = page.locator('.sidecart__panel');
        await puerta.locator('#login-email').waitFor({ timeout: 20000 });
        await sinVelo(page);
        check('`/login` abre la puerta: «Entra», el correo y «Continuar»; ni pestañas ni contraseña',
            (await texto(puerta.locator('.auth__title').first())) === 'Entra' && (await texto(puerta.locator('.auth__submit'))) === 'Continuar'
                && ! (await puerta.locator('.tabset, input[type="password"]').count()),
            await texto(puerta.locator('.auth').first()));
        await captura(page, '1-puerta');

        await puerta.locator('#login-email').fill('ana@gmial.com');
        await puerta.locator('#login-email').blur();
        const sugerencia = puerta.locator('.auth__link', { hasText: '¿Querías decir ana@gmail.com?' });
        await sugerencia.waitFor({ timeout: 5000 }).catch(() => {});
        check('una errata propone el correo bueno al salir del campo (la regla baja entonces, con `import()`)', await sugerencia.isVisible().catch(() => false));
        await captura(page, '1b-sugerencia');
        await sugerencia.click().catch(() => {});
        check('aceptarla escribe el correo bueno', (await puerta.locator('#login-email').inputValue()) === 'ana@gmail.com', await puerta.locator('#login-email').inputValue());

        limitadoresACero(CLIENTE);
        await puerta.locator('#login-email').fill(CLIENTE);
        const desde = Date.now();
        await puerta.locator('.auth__submit').click();
        await puerta.locator('#login-code').waitFor({ timeout: 15000 }).catch(() => {});
        const codigo = await codigoDelBuzon(CLIENTE, desde);
        const enviado = await texto(puerta.locator('.auth__sent [role="status"]'));
        check('con cuenta, la cara del código: el aviso con su correo, y el código en el buzón',
            enviado === `Te hemos enviado un código de 6 cifras a ${CLIENTE}.` && codigo !== null, `«${enviado}» · ${codigo ?? 'sin código en Mailpit'}`);
        check('el foco va al campo del código', await page.evaluate(() => document.activeElement?.id === 'login-code'));
        check('el `CodeInput` del diseño: seis casillas con su guion, y sin decir cuánto dura el código (como la isla, `#812`)',
            (await puerta.locator('.code-input__box').count()) === 6 && (await puerta.locator('.code-input__dash').count()) === 1
                && ! (await puerta.locator('.code-input .form__hint').count()));
        check('«Mantener la sesión iniciada en este dispositivo» nace SIN marcar (`#858`)',
            (await texto(puerta.locator('.auth__row'))) === 'Mantener la sesión iniciada en este dispositivo' && ! (await puerta.locator('.auth__row input[type="checkbox"]').isChecked()));
        check('y «Cambiar el correo» está junto al aviso', (await texto(puerta.locator('.auth__sent .auth__link'))) === 'Cambiar el correo');
        // «Pedir otro código», siempre a mano como la isla: pulsado nada más llegar, el servidor dice que aún no (uno por minuto
        // y correo), y el cajón lo dice bajo el código SIN dar por enviado otro.
        const otro = puerta.locator('.code-input .auth__switch button');
        check('«Pedir otro código» está a mano desde el primer momento', (await texto(otro)) === 'Pedir otro código' && await otro.isEnabled());
        limitado += 1;
        await otro.click();
        const espera = puerta.locator('.code-input .form__error');
        await espera.waitFor({ timeout: 8000 }).catch(() => {});
        const segundos = Number(/^Espera (\d+) segundos para pedir otro código\.$/.exec(await texto(espera))?.[1] ?? Number.NaN);
        check('pedido antes del minuto: «Espera N segundos…» bajo el código, y el aviso NO dice «otro»',
            limitado === 0 && segundos > 0 && segundos <= 60
                && (await texto(puerta.locator('.auth__sent [role="status"]'))) === `Te hemos enviado un código de 6 cifras a ${CLIENTE}.`, await texto(espera));
        await puerta.locator('#login-code').fill('48291');
        check('«Entrar» sigue apagado con cinco cifras', await puerta.locator('.auth__submit').isDisabled());
        await captura(page, '2-codigo');

        // Con la sexta cifra se comprueba SOLO: sin pulsar nada.
        const malo = codigo === '000000' ? '111111' : '000000';
        const esperarNoes = async () => { for (let i = 0; i < 32 && codigoMalo > 0; i += 1) await page.waitForTimeout(250); return codigoMalo === 0; };
        codigoMalo += 1;
        await puerta.locator('#login-code').fill(malo);
        const error = puerta.locator('.code-input .form__error');
        await error.waitFor({ timeout: 8000 }).catch(() => {});
        check('un código que no es se comprueba solo con la sexta cifra: su «no» bajo las casillas, y el código se VACÍA',
            await esperarNoes() && (await texto(error)) === 'El código no es correcto o ha caducado. Pide otro.'
                && (await puerta.locator('#login-code').inputValue()) === '', await texto(error));
        await captura(page, '3-codigo-malo');
        // ⚠️ Y el MISMO código, escrito otra vez, vuelve a comprobarse solo: el `CodeInput` olvida su aviso cuando el código se
        // vacía. Es conducta del componente, que ninguna prueba de Node ve (gasta otro de los cinco intentos: quedan tres).
        codigoMalo += 1;
        await puerta.locator('#login-code').fill(malo);
        check('el MISMO código, escrito otra vez, vuelve a comprobarse solo', await esperarNoes());

        await puerta.locator('#login-code').fill(codigo ?? '');
        await page.waitForSelector('.sidecart.is-open .acc-tiles', { timeout: 20000 }).catch(() => {});
        await sinVelo(page);
        check('el bueno entra en Mi cuenta', await page.locator('.sidecart.is-open .acc-tiles').isVisible().catch(() => false));
        const galletas = await contexto.cookies();
        check('sin la casilla, NINGUNA cookie de recuerdo (`#858`)', ! galletas.some((c) => c.name.startsWith('remember_web_')), galletas.map((c) => c.name).join(', '));
        await captura(page, '3b-cuenta');

        await page.locator('.acc-tile', { hasText: 'Cambiar contraseña' }).first().click();
        const salida = puerta.locator('.form__hint button', { hasText: 'Recupera tu contraseña' });
        await salida.waitFor({ timeout: 8000 }).catch(() => {});
        await salida.click().catch(() => {});
        await puerta.locator('#forgot-email').waitFor({ timeout: 8000 }).catch(() => {});
        const recuperar = await texto(puerta.locator('.auth__title').first());
        check('con sesión, el aviso de quien no tiene contraseña abre «Recupera tu contraseña» CON sus textos',
            recuperar === 'Recupera tu contraseña' && (await texto(puerta.locator('.auth__submit'))) === 'Enviar enlace', `«${recuperar}»`);
        await captura(page, '4-recuperar');
        await contexto.close();
    }

    // ── 5 · Las puertas por URL, sin sesión ─────────────────────────────────────────────────────────
    {
        const { contexto, page } = await nueva();
        for (const ruta of ['/recuperar-contrasena', '/registro']) {
            apiACero();
            await page.goto(`${BASE}${ruta}`, { waitUntil: 'domcontentloaded' });
            await cookies(page);
            await page.locator('.sidecart__panel #login-email').waitFor({ timeout: 20000 }).catch(() => {});
            check(`\`${ruta}\` sin sesión abre la PUERTA`,
                await page.locator('.sidecart__panel #login-email').isVisible().catch(() => false) && ! (await page.locator('#forgot-email, #reg-name').count()));
        }
        await captura(page, '5-registro');
        await contexto.close();
    }

    // ── 6 · La compra: entrar en el paso 5, con la casilla marcada ──────────────────────────────────
    {
        const { contexto, page } = await nueva();
        await aLaPuertaDeLaCompra(page);
        const paso = await texto(page.locator('.sidecart.is-open .wiz__title').first());
        check('«ir a pagar» sin sesión lleva a la puerta del paso 5', paso === 'Ya casi está' && await page.locator('.sidecart__panel #login-email').isVisible(), `«${paso}»`);
        await captura(page, '6-compra-puerta');
        await entrarConCodigo(page, CLIENTE, { recordar: true }).catch((e) => check('entrar con código en la compra', false, e.message));
        // ⚠️ La cuenta de pruebas tiene menores ASIGNABLES: tras identificarse, la compra vuelve al carrito a preguntar
        // «¿Para quién son estas entradas?» (`#202`·1), como tras cualquier entrada (`enterWith`). Sin menores, a «Pagar».
        await page.waitForSelector('.cart--summary, .sidecart__panel .cart__item', { timeout: 20000 }).catch(() => {});
        await sinVelo(page);
        const siguio = await page.locator('.cart--summary').isVisible().catch(() => false)
            || await page.getByText('¿Para quién son estas entradas?').first().isVisible().catch(() => false);
        check('con el código, la compra SIGUE: a «Pagar», o al carrito si hay menores que asignar (`#202`)',
            siguio && ! (await page.locator('.sidecart__panel .auth').count()), await texto(page.locator('.sidecart.is-open .wiz__title').first()));
        const recuerdo = (await contexto.cookies()).find((c) => c.name.startsWith('remember_web_'));
        const dias = recuerdo ? Math.round((recuerdo.expires * 1000 - Date.now()) / 86400000) : 0;
        check('con la casilla, queda la cookie de recuerdo, de ~90 días (`#858`)', dias >= 89 && dias <= 91, recuerdo ? `${dias} días` : 'sin cookie');
        await captura(page, '6b-pagar');
        await contexto.close();
    }

    // ── 7 · El alta en la compra, con un correo nuevo ───────────────────────────────────────────────
    {
        const { contexto, page } = await nueva();
        const nuevo = `sonda-a4a-compra-${width}-${Date.now()}@jumpweb.test`;
        await aLaPuertaDeLaCompra(page);
        const puerta = page.locator('.sidecart__panel');
        limitadoresACero(nuevo);
        await puerta.locator('#login-email').fill(nuevo);
        await puerta.locator('.auth__submit').click();
        await puerta.locator('#reg-name').waitFor({ timeout: 15000 }).catch(() => {});
        const sub = await texto(puerta.locator('.auth__sub').first());
        check('con un correo NUEVO, la cara del alta: lo dice, con el correo a la vista y sin contraseña ni teléfono',
            sub.startsWith('Aún no tienes cuenta con ese correo') && (await texto(puerta.locator('.auth__sent').first())).startsWith(nuevo)
                && ! (await puerta.locator('input[type="password"], input[type="tel"]').count()), `«${sub}»`);
        check('y a ese correo NO le llega ningún código: el alta no espera a nada (`#849`)', ! (await asuntosDe(nuevo)).some((s) => /código/.test(s)));
        await captura(page, '7-alta');
        await puerta.locator('.auth__sent .auth__link').click();
        check('«Cambiar el correo» vuelve a la puerta con el correo escrito', (await puerta.locator('#login-email').inputValue().catch(() => '')) === nuevo);
        await puerta.locator('.auth__submit').click();
        await puerta.locator('#reg-name').waitFor({ timeout: 15000 });
        await darseDeAlta(puerta);
        await page.waitForSelector('.cart--summary', { timeout: 20000 }).catch(() => {});
        await sinVelo(page);
        check('el alta sigue a «Pagar» al momento (pay-first, `#331`)', await page.locator('.cart--summary').isVisible().catch(() => false));
        await captura(page, '7b-pagar');
        await contexto.close();
    }

    // ── 8 · El alta en Mi cuenta, con otro correo nuevo ─────────────────────────────────────────────
    {
        const { contexto, page } = await nueva();
        const nuevo = `sonda-a4a-cuenta-${width}-${Date.now()}@jumpweb.test`;
        apiACero();
        await page.goto(`${BASE}/registro`, { waitUntil: 'domcontentloaded' });
        await cookies(page);
        const puerta = page.locator('.sidecart__panel');
        await puerta.locator('#login-email').waitFor({ timeout: 20000 });
        limitadoresACero(nuevo);
        await puerta.locator('#login-email').fill(nuevo);
        await puerta.locator('.auth__submit').click();
        await puerta.locator('#reg-name').waitFor({ timeout: 15000 });
        await darseDeAlta(puerta);
        await page.waitForSelector('.sidecart.is-open .acc-tiles', { timeout: 20000 }).catch(() => {});
        await sinVelo(page);
        check('el alta en Mi cuenta entra al momento, con «Debes verificar tu correo electrónico.»',
            await page.locator('.acc-tiles').isVisible().catch(() => false) && await puerta.getByText('Debes verificar tu correo electrónico.').first().isVisible().catch(() => false));
        await captura(page, '8-cuenta-nueva');
        await contexto.close();
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
    informe.anonimizadas = anonimizarLasDeLaSonda();
}

await writeFile(`${SALIDA}/sonda-cajon-a4a.json`, `${JSON.stringify(informe, null, 2)}\n`);

let fallos = 0;
for (const { ancho, checks } of informe.anchos) {
    console.log(`── ${ancho} px`);
    for (const { nombre, ok, detalle } of checks) {
        if (! ok) fallos += 1;
        console.log(`  ${ok ? '✓' : '✗'} ${nombre}${! ok && detalle ? `  → ${detalle}` : ''}`);
    }
}
console.log(`\n${fallos === 0 ? '✓' : '✗'} ${fallos} fallos · ${informe.anonimizadas} cuentas de la sonda anonimizadas · ${SALIDA}/sonda-cajon-a4a.json`);
process.exit(fallos === 0 ? 0 : 1);
