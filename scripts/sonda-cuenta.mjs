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
 *   6. LAS RESERVAS (T5b, `DECISIONES #775`), con las de `sonda-cuenta-datos.php` (un Jump en plazo y un cumpleaños con
 *      señal): «Tu próxima reserva» con su plazo, «Ver el pago» del libro, «Cambiar o cancelar» (lo que se puede, el
 *      mensaje escrito, «Llamar» y WhatsApp con el teléfono del parque), «Otras reservas» → «Tu reserva» con la señal
 *      pagada, lo del día y la promesa de devolverla; y el historial con «Ver más»;
 *   7. HOY (si da tiempo, antes de las 20:00 del parque): el QR grande de entrada, los calcetines con el aviso escrito en
 *      el panel, el plazo ya pasado (y su aviso en «Cambiar o cancelar») y «Cómo llegar» en Tu QR;
 *   8. la consola queda limpia y ninguna respuesta de la API falla.
 * Sale con 1 si algo falla. Dentro del contenedor, contra su puerto 80, con la cuenta de pruebas de `sonda-isla.mjs`:
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-cuenta.mjs http://localhost
 *
 * ⚠️ Renueva el carné de la cuenta de pruebas (es lo que se prueba): no la uses para otra cosa que dependa de su QR.
 * ⚠️ Los textos que espera son los de la instalación local: el aviso de los calcetines (`reservation_note` del
 *    complemento 110) y la promesa de la señal de los packs (`deposit_refundable_in_time`), puestos en su panel.
 */
/* global URL, console, document, window -- Node y, dentro de `evaluate`, el navegador */
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
/** Las reservas de la sonda (`sonda-cuenta-datos.php`): `montar`, `hoy` o `borrar`. Devuelve su línea JSON. */
const datos = (modo) => JSON.parse(tinker(`$modo = '${modo}'; require base_path('scripts/sonda-cuenta-datos.php');`).split('\n').pop());
const limitadoresACero = () => tinker(`$id = App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->value('id'); foreach ([md5('api'.'user:'.$id), md5('api'.'ip:127.0.0.1'), 'login-ip|127.0.0.1', Illuminate\\Support\\Str::transliterate('${CLIENTE.email}|127.0.0.1')] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear($k); }`);

/** Un recorrido entero, apuntando en `informe`; si algo revienta a mitad, lo apuntado se queda (y su página, para la captura). */
async function recorrer(navegador, ventana, informe) {
    const [ancho, alto] = ventana.split('x').map(Number);
    const contexto = await navegador.newContext({ viewport: { width: ancho, height: alto }, locale: 'es-ES', acceptDownloads: true });
    const pagina = await contexto.newPage();
    const errores = [];
    const malas = [];
    Object.assign(informe, { pagina, contexto });
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
    // El texto de un trozo, en una línea (`\s` ya incluye los espacios duros que `Intl` pone en los importes).
    const texto = async (loc) => ((await loc.innerText({ timeout: 4000 }).catch(() => '')) ?? '').replace(/\s+/g, ' ').trim();
    const volver = async () => { await capa().getByRole('button', { name: 'Volver' }).click(); await pagina.waitForTimeout(500); };
    // «Escribirnos por WhatsApp» abre otra pestaña: se anota a dónde, sin salir de la página.
    const interceptarVentanas = () => pagina.evaluate(() => { window.__abiertas = []; window.open = (u) => { window.__abiertas.push(String(u)); return null; }; });
    const ventanas = () => pagina.evaluate(() => window.__abiertas ?? []);
    // Una CARGA de verdad: de `/kids#mi-cuenta/qr` a `/kids#mi-cuenta` el navegador solo cambia el ancla.
    const cargar = async (ruta) => { await pagina.goto('about:blank'); await pagina.goto(`${base}${ruta}`, { waitUntil: 'load' }); };

    // Las reservas de la sonda, sin «hoy» (el recorrido de T5a espera Tu QR compacto).
    datos('montar');

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
    await captura('5-enlace-qr');

    // ── 6 · Las reservas (T5b) ───────────────────────────────────────────────────────────────────────
    const sitio = await (await pagina.request.get(`${base}/api/v1/site`, { headers: { Accept: 'application/json' } })).json();
    const telefono = String(sitio?.contact?.phone ?? '');
    const digitos = telefono.replace(/\D/g, '');

    // Cada carga de Mi cuenta pide ocho cosas a la API: la sonda carga más en un minuto que nadie de verdad.
    limitadoresACero();
    await cargar('/kids#mi-cuenta');
    const proxima = capa().locator('#proxima');
    await proxima.waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(500);
    let t = await texto(proxima);
    check('«Tu próxima reserva»: la hora, qué y cuántos, y el número', t.startsWith('Tu próxima reserva') && t.includes('11:30') && t.includes('Jump · 1 hora · 2 entradas') && t.includes('Nº R-SNDCJUMP'), t.slice(0, 140));
    check('en plazo, hasta cuándo (24 h antes, en la zona del parque)', /Puedes cambiar o cancelar hasta el .+ a las 11:30\./.test(t), t);
    check('la línea de arriba nombra la próxima, con qué y cuántos', (await texto(capa().locator('h1 + button'))).includes('Jump · 1 hora · 2 entradas'));
    await proxima.getByRole('button', { name: 'Ver el pago' }).click();
    await pagina.waitForTimeout(300);
    t = await texto(proxima);
    check('«Ver el pago» despliega el resumen del LIBRO: la línea y el total, sin señal', /Jump · 1 hora · 2 entradas 28 €/.test(t) && /Total 28 €/.test(t) && ! t.includes('Señal pagada'), t.slice(-120));
    await captura('6-proxima');

    await interceptarVentanas();
    await proxima.getByRole('button', { name: 'Cambiar o cancelar' }).click();
    await pagina.waitForTimeout(600);
    t = await texto(capa());
    const mensaje = await texto(capa().locator('blockquote'));
    check('«Cambiar o cancelar»: qué y hasta cuándo, SIN prometer la señal (una entrada no la tiene)',
        (await banda()) === 'Cambiar o cancelar' && t.includes('número R-SNDCJUMP') && t.includes('hasta 24 h antes: escríbenos') && ! t.includes('te devolvemos la señal'), t.slice(0, 220));
    check('el mensaje, ya escrito y enseñado', /^Hola, quiero cambiar o cancelar mi reserva R-SNDCJUMP del .+ a las 11:30\.$/.test(mensaje), mensaje);
    const llamar = capa().getByRole('link', { name: `Llamar al ${telefono}` });
    check('«Llamar» con el teléfono del parque (`/site`), internacional', digitos !== '' && (await llamar.getAttribute('href').catch(() => '')) === `tel:+${digitos}`, telefono);
    await captura('7-cambiar');
    await capa().getByRole('button', { name: 'Escribirnos por WhatsApp' }).click();
    await pagina.waitForTimeout(300);
    const [wa = ''] = await ventanas();
    check('«Escribirnos por WhatsApp» abre wa.me con ESE mensaje', wa.startsWith(`https://wa.me/${digitos}?text=`) && decodeURIComponent(wa.split('?text=')[1] ?? '') === mensaje, wa);
    await volver();
    check('la flecha de «Cambiar o cancelar» vuelve a Mi cuenta', (await banda()) === 'Mi cuenta');

    const otras = capa().locator('#otras');
    const otra = otras.getByRole('button', { name: /Pack Cumpleaños KIDS · 10 invitados$/ });
    check('«Otras reservas»: la otra, que se toca entera, con su nombre accesible entero', await otra.isVisible().catch(() => false) && / a las 17:00, Pack Cumpleaños KIDS · 10 invitados$/.test(await otra.getAttribute('aria-label').catch(() => '')));
    await otra.click();
    await pagina.waitForTimeout(600);
    const reserva = capa().locator('#reserva');
    t = await texto(reserva);
    check('«Tu reserva»: la banda y, bajo la tarjeta, la señal pagada, lo del día de la fiesta y el plazo de 3 días',
        (await banda()) === 'Tu reserva' && t.includes('Señal pagada: 50 €') && t.includes('El día de la fiesta: 119,50 €') && /Puedes cambiar o cancelar hasta el .+ a las 17:00\./.test(t), t.slice(0, 260));
    await reserva.getByRole('button', { name: 'Ver el pago' }).click();
    await pagina.waitForTimeout(300);
    t = await texto(reserva);
    check('su pago: la línea, el total, la señal pagada y lo del día (del libro)', /Pack Cumpleaños KIDS · 10 invitados 169,50 €/.test(t) && /Total 169,50 €/.test(t) && /Señal pagada 50 €/.test(t) && /El día de la fiesta 119,50 €/.test(t), t.slice(-200));
    await captura('8-tu-reserva');
    await reserva.getByRole('button', { name: 'Cambiar o cancelar' }).click();
    await pagina.waitForTimeout(600);
    t = await texto(capa());
    check('su «Cambiar o cancelar» PROMETE la señal (el interruptor del producto, `#775`) y nombra el cumpleaños', t.includes('hasta 3 días antes, y te devolvemos la señal') && t.includes('número R-SNDCCUMPLE'), t.slice(0, 240));
    await volver();
    check('desde «Tu reserva», la flecha de «Cambiar o cancelar» vuelve a ella', (await banda()) === 'Tu reserva');
    await volver();
    check('y la de «Tu reserva», a Mi cuenta', (await banda()) === 'Mi cuenta');

    const verHistorial = otras.getByRole('button', { name: 'Ver el historial' });
    await verHistorial.click();
    await pagina.waitForTimeout(300);
    // Las filas del historial no se tocan: su nombre accesible va en un texto oculto a la vista, con su estado.
    const filas = () => otras.locator('div:has(> span[aria-hidden="true"] + span[aria-hidden="true"])');
    const primeras = await filas().count();
    const oculto = await filas().first().locator('span').first().textContent().catch(() => '');
    check('«Ver el historial» despliega diez, cada una con su día, qué y su estado para el lector de pantalla', primeras === 10 && /^.+ a las \d{2}:\d{2}, .+, (Pasada|Cancelada|Devuelta|Sin pagar)$/.test(oculto), `${primeras} · «${oculto}»`);
    await otras.getByRole('button', { name: 'Ver más' }).click();
    await pagina.waitForTimeout(900);
    check('«Ver más» suma los diez siguientes', (await filas().count()) === 20, String(await filas().count()));
    await otras.getByRole('button', { name: 'Ocultar el historial' }).click();
    await captura('9-historial');

    // ── 7 · Hoy ──────────────────────────────────────────────────────────────────────────────────────
    const { hoy } = datos('hoy');

    if (! hoy) {
        console.log(`· ${ventana} · pasadas las 20:00 del parque no hay «hoy» que montar: el paso 7 no se ha comprobado`);
    } else {
        limitadoresACero();
        await cargar('/kids#mi-cuenta');
        await proxima.waitFor({ timeout: 15000 }).catch(() => {});
        await pagina.waitForTimeout(600);
        const qrHoy = capa().locator('#mi-qr');
        const png = await qrHoy.locator('img').first().evaluate((img) => img.complete && img.naturalWidth > 0).catch(() => false);
        check('con la reserva HOY, Tu QR sale GRANDE de entrada (su PNG y «Guardar en el móvil»), sin «Enseñar mi QR»',
            png && await qrHoy.getByRole('link', { name: 'Guardar en el móvil' }).isVisible() && ! (await capa().getByRole('button', { name: 'Enseñar mi QR' }).count()));
        t = await texto(proxima);
        check('la próxima es la de hoy, con los calcetines con el aviso del panel', t.includes('Kids · 1 hora · 2 entradas') && t.includes('Nº R-SNDCHOY') && t.includes('Tenéis 2 pares de calcetines comprados; os los damos en la puerta.'), t.slice(0, 200));
        check('pasado el plazo, lo dice (con su tramo) y no promete nada', t.includes('Quedan menos de 24 h: ya no se puede cambiar ni cancelar.') && ! t.includes('Puedes cambiar'));
        await proxima.getByRole('button', { name: 'Ver el pago' }).click();
        await pagina.waitForTimeout(300);
        t = await texto(proxima);
        check('su pago: la entrada y los calcetines, y el total del libro', /Kids · 1 hora · 2 entradas 16 €/.test(t) && /Calcetines antideslizantes · 2 unidades 4 €/.test(t) && /Total 20 €/.test(t), t.slice(-160));
        await captura('10-hoy');
        await proxima.getByRole('button', { name: 'Cambiar o cancelar' }).click();
        await pagina.waitForTimeout(600);
        t = await texto(capa());
        check('su «Cambiar o cancelar» avisa de que el plazo pasó, y deja el mensaje igual', await capa().locator('[role=status]').filter({ hasText: 'Quedan menos de 24 h' }).isVisible() && t.includes('Hola, quiero cambiar o cancelar mi reserva R-SNDCHOY'));
        await volver();
        await cargar('/kids#mi-cuenta/qr');
        await capa().waitFor({ timeout: 15000 }).catch(() => {});
        const llegar = capa().getByRole('link', { name: 'Cómo llegar' });
        await llegar.waitFor({ timeout: 8000 }).catch(() => {});
        check('Tu QR, con la reserva hoy, ofrece «Cómo llegar» con la ruta del parque (`/site`)', (await llegar.getAttribute('href').catch(() => '')) === (sitio?.address?.maps_url ?? '—'));
        await captura('11-como-llegar');
    }

    // ── 8 · Limpio ───────────────────────────────────────────────────────────────────────────────────
    const deCodigo = (codigo) => errores.filter((e) => e.includes(`status of ${codigo}`));
    const inesperados = errores.filter((e) => ! /status of (401|404)/.test(e))
        .concat(deCodigo(401).slice(esperados[401]), deCodigo(404).slice(esperados[404]));
    check('consola limpia', inesperados.length === 0, inesperados.join(' | '));
    check('ninguna respuesta de la API falla', malas.length === 0, malas.join(', '));
}

const navegador = await chromium.launch();
let fallos = 0;

try {
    for (const ventana of ['1280x860', '390x844']) {
        // Cada recorrido empieza sin sesión: el anterior entró.
        const informe = { ventana, checks: [] };

        try {
            await recorrer(navegador, ventana, informe);
        } catch (error) {
            const foto = `storage/app/audit/sonda-cuenta-${ventana.split('x')[0]}-roto.png`;
            await informe.pagina?.screenshot({ path: foto }).catch(() => {});
            informe.checks.push({ nombre: 'el recorrido llega al final', ok: false, detalle: `${String(error.message).split('\n')[0]} (${foto})` });
        } finally {
            await informe.contexto?.close().catch(() => {});
        }

        for (const c of informe.checks) {
            if (! c.ok) fallos += 1;
            console.log(`${c.ok ? '✓' : '✗'} ${ventana} · ${c.nombre}${c.detalle ? ` — ${c.detalle}` : ''}`);
        }
    }
} finally {
    await navegador.close();
    datos('borrar');
}

console.log(fallos === 0 ? '\n✓ Mi cuenta en la isla, en vivo: todo en su sitio' : `\n✗ ${fallos} fallos`);
process.exit(fallos === 0 ? 0 : 1);
