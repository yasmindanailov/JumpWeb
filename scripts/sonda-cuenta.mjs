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
 *   7. HOY (si da tiempo, antes de las 20:00 del parque): en la página, la isla con «Hoy a las …» y «Ver mi QR»; en Mi
 *      cuenta, el QR grande de entrada, los calcetines con el aviso escrito en el panel, el plazo ya pasado (y su aviso en
 *      «Cambiar o cancelar») y «Cómo llegar» en Tu QR;
 *   8. ANTES DE VENIR (T5c, `DECISIONES #776`), con el cumpleaños como próxima: en la página, el punto de alerta y
 *      «Siguiente: …» en el menú; en Mi cuenta, el chip que baja al bloque, la siguiente tarea entera, la invitación en
 *      fila (que se comparte por WhatsApp con el mensaje de la lista), las autorizaciones y los extras con sus enlaces, y
 *      la siguiente, que lleva a la lista de invitados de esa fiesta;
 *   9. LOS HIJOS (T5d, `DECISIONES #777`), con una entrada como próxima y la cuenta sin hijos: «Siguiente: Añade a tus
 *      hijos» en la isla de la página; su tarea en «Antes de venir», que abre la pantalla de alta; lo que falta al guardar;
 *      dos hijos con la fecha tecleada, sus relaciones y la casilla (y el descargo leído); «Guardado»; sus chips con
 *      «firmado» y «Todo listo»; la ficha de uno, con su firma y su PDF, y quitarlo tras preguntar; y la puerta
 *      `/mi-cuenta/hijos`;
 *   10. LOS AJUSTES (T5e, `DECISIONES #778`): los cuatro plegables cerrados y «Cerrar sesión»; «Tus datos» con los de la
 *      cuenta, «Guardar los cambios» solo al cambiar algo (se guarda y se deja como estaba); el correo nuevo que se pide
 *      con la contraseña y se cancela; la contraseña (una que no es, bajo su campo; la buena, de vuelta a Mi cuenta con
 *      «Acceso» abierto); cerrar las otras sesiones; un interruptor ida y vuelta, sin contraseña; el PDF del descargo;
 *      «Descargar mis datos» que DESCARGA; borrar la cuenta con una reserva por celebrar (lo dice, sin botón) y sin ella
 *      (la contraseña, la casilla y el botón, que no se pulsa); los recibos del libro; `#mi-cuenta/privacidad`; y, al
 *      final, «Cerrar sesión», que sale a la portada sin sesión. Antes, LOS AVISOS (T5e·2, `#779`): «Vincular Google»
 *      hasta la puerta de Google (se corta ahí) y la vuelta cancelada, cuyo aviso del servidor sale en Mi cuenta; y, con
 *      el correo sin confirmar y la analítica pendiente, sus dos avisos, el reenvío con su espera y «Entendido» (la
 *      cuenta se deja como estaba);
 *   11. la consola queda limpia y ninguna respuesta de la API falla (salvo el «no» buscado de la contraseña).
 * Sale con 1 si algo falla. Dentro del contenedor, contra su puerto 80, con la cuenta de pruebas de `sonda-isla.mjs`:
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-cuenta.mjs http://localhost
 *
 * ⚠️ Renueva el carné de la cuenta de pruebas (es lo que se prueba): no la uses para otra cosa que dependa de su QR.
 * ⚠️ Los textos que espera son los de la instalación local: el aviso de los calcetines (`reservation_note` del
 *    complemento 110) y la promesa de la señal de los packs (`deposit_refundable_in_time`), puestos en su panel.
 */
/* global URL, console, document, window, getComputedStyle -- Node y, dentro de `evaluate`, el navegador */
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
/** Las reservas de la sonda (`sonda-cuenta-datos.php`): `montar`, `hoy`, `fiesta` o `borrar`. Devuelve su línea JSON. */
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
    // Y uno más, buscado (T5e): el 422 de «Cambiar la contraseña» con una actual que no es.
    const esperados = { 401: 0, 404: 0, 422: 0 };
    let buscar422 = 0;
    pagina.on('pageerror', (e) => errores.push(e.message));
    pagina.on('console', (m) => { if (m.type() === 'error') errores.push(m.text()); });
    pagina.on('response', (r) => {
        const ruta = new URL(r.url()).pathname;
        if (r.status() === 401 && /\/api\/v1\/me(\/[a-z-]+)?$/.test(ruta)) esperados[401] += 1;
        else if (r.status() === 404 && ruta === '/api/v1/auth/google/pending') esperados[404] += 1;
        else if (r.status() === 422 && ruta === '/api/v1/me/password' && buscar422 > 0) { buscar422 -= 1; esperados[422] += 1; }
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
    // `exact`: desde la T5e, Ajustes trae «Cerrar sesión» y «Cerrar sesión en otros dispositivos» (su «Cerrar»).
    await capa().getByRole('button', { name: 'Cerrar', exact: true }).click();
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
        // La isla de la PÁGINA (T5c): «Hoy a las…» manda, con «Ver mi QR» y el punto «vivo» en el menú.
        await cargar('/kids');
        await pagina.waitForTimeout(700);
        const islaHoy = pagina.locator('[data-situation="reserva-hoy"]');
        check('en la página, la isla dice «Hoy a las …» con «Ver mi QR» y el punto vivo en su menú',
            /Hoy a las \d{2}:\d{2}/.test(await texto(islaHoy)) && await islaHoy.getByRole('button', { name: 'Ver mi QR' }).isVisible() && await islaHoy.locator('[style*="--isla-vivo"]').count() > 0,
            await texto(islaHoy));
        await captura('10a-isla-hoy');
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

    // ── 8 · Antes de venir (T5c) ─────────────────────────────────────────────────────────────────────
    const { fiesta } = datos('fiesta');
    const lista = `${base}/reserva/${fiesta}/datos-invitados`;
    limitadoresACero();

    // En la página: el punto de alerta en el menú y, dentro, la nota de «Mi cuenta».
    await cargar('/kids');
    await pagina.waitForTimeout(700);
    check('en la página, con una tarea pendiente, el punto de alerta en el menú de la isla', await pagina.locator('[data-situation] [style*="--isla-alerta"]').count() > 0);
    await abrirMenu();
    check('y en su menú, «Mi cuenta» dice «Siguiente: Formulario de invitados»', await pagina.getByText('Siguiente: Formulario de invitados').first().isVisible());
    await captura('12-isla-tarea');

    await cargar('/kids#mi-cuenta');
    const bloqueAntes = capa().locator('#antes');
    await bloqueAntes.waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(500);
    const chip = capa().getByRole('button', { name: 'Siguiente: Formulario de invitados' });
    check('arriba, el chip «Siguiente: Formulario de invitados»', await chip.isVisible().catch(() => false));
    t = await texto(bloqueAntes);
    check('«Antes de venir»: «0 de 2 hecho» y, entera, la siguiente con su plazo y «Rellenar»',
        t.startsWith('Antes de venir 0 de 2 hecho') && /SIGUIENTE Formulario de invitados, hasta el \S+ \d+: quién viene, edades y alergias\. Rellenar/.test(t), t.slice(0, 200));
    check('en fila, la invitación con sus confirmados (del servidor)', t.includes('Invitación 2 de 10 confirmados'), t);
    const faltan = bloqueAntes.getByRole('link', { name: 'Ver quién falta' });
    const extras = bloqueAntes.getByRole('link', { name: 'Añadir extras' });
    check('las autorizaciones, dichas sin denominador inventado, con «Ver quién falta» a la lista',
        t.includes('Autorizaciones: aún no hay ninguna firmada.') && (await faltan.getAttribute('href').catch(() => '')) === lista);
    check('los extras, ofrecidos por su número y su plazo (ocho nombres saturaban), con «Añadir extras» a la lista',
        /Y si quieres: 8 extras para la fiesta, que se añaden hasta el \S+ \d+\. Se pagan el día de la fiesta\./.test(t) && (await extras.getAttribute('href').catch(() => '')) === lista);
    await captura('13-antes-de-venir');

    // Dónde queda el bloque respecto de la caja que se desplaza: arriba (a 12 px), o tan arriba como deje el final.
    const posicion = () => bloqueAntes.evaluate((el) => {
        let c = el.parentElement;
        while (c && ! (c.scrollHeight > c.clientHeight && /(auto|scroll)/.test(getComputedStyle(c).overflowY))) c = c.parentElement;
        if (! c) return { caja: null };
        const arriba = Math.round(el.getBoundingClientRect().top - c.getBoundingClientRect().top);

        return { caja: c.hasAttribute('data-isla-scroll') ? 'data-isla-scroll' : c.tagName, arriba, scroll: Math.round(c.scrollTop), max: c.scrollHeight - c.clientHeight };
    }).catch(() => ({ caja: null }));
    await chip.click();
    await pagina.waitForTimeout(700);
    const p = await posicion();
    check('el chip baja la capa hasta «Antes de venir»', p.caja !== null && ((p.arriba >= 0 && p.arriba <= 20) || p.scroll >= p.max - 2), JSON.stringify(p));
    await captura('13b-antes-bloque');

    await interceptarVentanas();
    await bloqueAntes.getByRole('button', { name: /Invitación/ }).click();
    await pagina.waitForTimeout(300);
    const [invitacionWa = ''] = await ventanas();
    check('tocar la invitación la comparte por WhatsApp con el mensaje de la lista (quién cumple, cuándo, el enlace)',
        invitacionWa.startsWith('https://wa.me/?text=') && /^Vera cumple 7 años.+Contesta aquí: https?:\/\/\S+\/invitacion\/\w{12}$/.test(decodeURIComponent(invitacionWa.split('?text=')[1] ?? '')),
        decodeURIComponent(invitacionWa.split('?text=')[1] ?? '').slice(0, 160));

    await Promise.all([
        pagina.waitForURL(lista, { timeout: 15000 }).catch(() => {}),
        bloqueAntes.getByRole('button', { name: /Formulario de invitados/ }).click(),
    ]);
    check('tocar la siguiente lleva a la lista de invitados de ESA fiesta', pagina.url() === lista, pagina.url());

    // ── 9 · Los hijos (T5d) ──────────────────────────────────────────────────────────────────────────
    // El Jump como próxima (una ENTRADA) y la cuenta sin hijos: «Añade a tus hijos» es su tarea.
    datos('montar');
    limitadoresACero();
    await cargar('/kids');
    await pagina.waitForTimeout(700);
    await abrirMenu();
    check('en la página, con una entrada y sin hijos, «Mi cuenta» dice «Siguiente: Añade a tus hijos»', await pagina.getByText('Siguiente: Añade a tus hijos').first().isVisible());

    await cargar('/kids#mi-cuenta');
    const antesH = capa().locator('#antes');
    const quien = capa().locator('#quien');
    await antesH.waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(500);
    t = await texto(antesH);
    check('«Antes de venir» de una entrada: «Añade a tus hijos», para el día de la reserva, con «Añadir»',
        /SIGUIENTE Para el \S+ \d+ Añade a tus hijos: nombre y fecha de nacimiento, y firmas por ellos\. .+ Añadir/.test(t), t);
    t = await texto(quien);
    check('«Quién viene contigo»: «Tus hijos», «Añadir» y que los adultos se registran ellos', t === 'Quién viene contigo Tus hijos Añadir Los adultos se registran ellos, desde casa o en el mostrador.', t);

    await antesH.getByRole('button', { name: /Añade a tus hijos/ }).click();
    await pagina.waitForTimeout(600);
    check('tocar la tarea abre «Añade a tus hijos» en la capa', (await banda()) === 'Añade a tus hijos', await banda());
    await capa().getByRole('button', { name: 'Guardar' }).click();
    await pagina.waitForTimeout(400);
    t = await texto(capa());
    check('«Guardar» sin nada: lo que falta, en su sitio (nombre, fecha, relación y la casilla)',
        t.includes('Escribe su nombre.') && t.includes('Revisa la fecha: día, mes y año.') && t.includes('Elige qué eres suyo.') && t.includes('Marca la casilla para guardar.'), t.slice(0, 260));
    await captura('14-hijos-errores');

    // ⚠️ `input[...]`: el campo pone el mismo prefijo en los `id` de su error y su pista, y `nth(1)` era el error del primero.
    const nombre = (i) => capa().locator('input[id^="pmc-n-"]').nth(i);
    const fecha = (i) => capa().locator('input[id^="pmc-f-"]').nth(i);
    const relacion = (i, valor) => capa().locator(`label:has(input[type=radio][value="${valor}"])`).nth(i);
    await nombre(0).fill('Vera');
    await fecha(0).pressSequentially('07032019');
    await relacion(0, 'mother').click();
    const conPista = await texto(capa());
    check('la fecha se escribe con las barras solas y dice su edad', (await fecha(0).inputValue()) === '07/03/2019' && conPista.includes('7 años'), await fecha(0).inputValue());
    await capa().getByRole('button', { name: 'Añadir otro hijo' }).click();
    await nombre(1).fill('Pol');
    await fecha(1).pressSequentially('01052021');
    await relacion(1, 'father').click();
    await capa().getByText('Acepto el descargo de responsabilidad en su nombre.').click();
    await capa().getByRole('button', { name: 'Leer el descargo' }).click();
    await pagina.waitForTimeout(400);
    check('«Leer el descargo» lo abre en la capa', (await banda()) === 'Descargo de responsabilidad');
    await volver();
    check('y su flecha vuelve al formulario, con lo escrito', (await banda()) === 'Añade a tus hijos' && (await nombre(1).inputValue()) === 'Pol');
    await captura('15-hijos-formulario');
    await capa().getByRole('button', { name: 'Guardar' }).click();
    // `first()`: la cabecera del desenlace repite su título para el lector de pantalla.
    const guardado = capa().getByText('Guardado. En la puerta salen con tu QR.').first();
    await guardado.waitFor({ timeout: 8000 }).catch(() => {});
    check('«Guardar» declara a los dos, firmando por ellos, y dice «Guardado»', await guardado.isVisible());

    await volver();
    await pagina.waitForTimeout(800);
    t = await texto(quien);
    check('de vuelta, «Quién viene contigo» los enseña con su edad y «firmado»', /Vera, 7 años firmado/.test(t) && /Pol, 5 años firmado/.test(t), t);
    t = await texto(capa().locator('#antes'));
    check('y «Antes de venir» ya dice «Todo listo para el …»', /Todo listo para el \S+ \d+/.test(t), t);
    await captura('16-quien');

    await quien.getByRole('button', { name: /^Vera, 7 años, firmado$/ }).click();
    await pagina.waitForTimeout(600);
    t = await texto(capa());
    check('su ficha: la banda con su nombre, su firma y el PDF del descargo',
        (await banda()) === 'Vera' && /Descargo firmado el \d{2}\/\d{2}\/\d{4} · versión \d+/.test(t) && await capa().getByRole('link', { name: 'Descargar el descargo firmado' }).isVisible(), t.slice(0, 220));
    await capa().getByRole('button', { name: 'Quitar de tu cuenta' }).click();
    check('«Quitar de tu cuenta» pregunta en el sitio', await capa().getByText('¿Quitar a Vera de tu cuenta?').isVisible());
    await captura('17-quitar');
    await capa().getByRole('button', { name: 'Sí, quitar' }).click();
    await pagina.waitForTimeout(900);
    check('y al confirmar la quita, vuelve a Mi cuenta y lo dice arriba',
        (await banda()) === 'Mi cuenta' && await capa().getByText('Vera ya no está en tu cuenta').isVisible() && ! (await texto(quien)).includes('Vera'));

    await cargar('/mi-cuenta/hijos');
    await capa().waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(600);
    check('la puerta `/mi-cuenta/hijos` abre «Añade a tus hijos» en la isla, no el lateral', (await banda()) === 'Añade a tus hijos' && ! (await lateralAbierto()), await banda());

    // ── 10 · Ajustes (T5e) ───────────────────────────────────────────────────────────────────────────
    // Con el Jump y el cumpleaños como reservas (el Jump, la próxima: impide borrar la cuenta). Lo que se cambia, se deja
    // como estaba; el correo nuevo se pide y se cancela; la contraseña se «cambia» a la misma.
    datos('montar');
    limitadoresACero();
    const deLaSonda = (campo) => tinker(`echo App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->value('${campo}');`);
    await cargar('/kids#mi-cuenta');
    const ajustes = capa().locator('#ajustes');
    await ajustes.waitFor({ timeout: 15000 }).catch(() => {});
    await ajustes.scrollIntoViewIfNeeded().catch(() => {});
    const plegable = (nombre) => ajustes.getByRole('button', { name: nombre, exact: true });
    const abiertos = async () => Promise.all(['Tus datos', 'Acceso', 'Privacidad', 'Recibos'].map((n) => plegable(n).getAttribute('aria-expanded').catch(() => null)));
    check('«Ajustes» al final: Tus datos, Acceso, Privacidad y Recibos, plegados, y «Cerrar sesión»',
        (await abiertos()).join() === 'false,false,false,false' && await ajustes.getByRole('button', { name: 'Cerrar sesión', exact: true }).isVisible(), (await abiertos()).join());
    await captura('18-ajustes');

    await plegable('Tus datos').click();
    const nombreAj = capa().locator('#mc-aj-nombre');
    const telefonoAj = capa().locator('#mc-aj-telefono');
    await nombreAj.waitFor({ timeout: 8000 }).catch(() => {});
    const telefonoDeAntes = await telefonoAj.inputValue().catch(() => '');
    const idiomas = await capa().locator('#mc-aj-idioma option').evaluateAll((os) => os.map((o) => o.value)).catch(() => []);
    check('«Tus datos», con los de la cuenta y los idiomas de la instalación (no los dos del mockup)',
        (await nombreAj.inputValue()).startsWith(CLIENTE.nombre) && telefonoDeAntes === deLaSonda('phone') && idiomas.join() === 'es,en,fr', `${await nombreAj.inputValue()} · ${telefonoDeAntes} · ${idiomas}`);
    const guardarCambios = capa().getByRole('button', { name: 'Guardar los cambios' });
    check('sin cambios, no hay «Guardar los cambios»', ! (await guardarCambios.isVisible().catch(() => false)));
    const telefonoNuevo = `${telefonoDeAntes.slice(0, -1)}${telefonoDeAntes.endsWith('9') ? '8' : '9'}`;
    await telefonoAj.fill(telefonoNuevo);
    check('al cambiar algo, sale', await guardarCambios.isVisible());
    await guardarCambios.click();
    await pagina.waitForTimeout(1000);
    check('«Guardar los cambios» lo guarda en el servidor y lo confirma arriba', deLaSonda('phone') === telefonoNuevo && await capa().getByText('Guardado', { exact: true }).first().isVisible(), deLaSonda('phone'));
    await telefonoAj.fill(telefonoDeAntes);
    await guardarCambios.click();
    await pagina.waitForTimeout(1000);
    check('y se deja como estaba', deLaSonda('phone') === telefonoDeAntes);

    await ajustes.getByRole('button', { name: 'Cambiar el correo', exact: true }).click();
    await pagina.waitForTimeout(500);
    check('«Cambiar» del correo abre su paso, con la contraseña (la pide el servidor)', (await banda()) === 'Correo' && await capa().locator('#mc-correo-clave').isVisible(), await banda());
    await capa().locator('#mc-correo-nuevo').fill(CLIENTE.email);
    await capa().locator('#mc-correo-clave').fill(CLIENTE.password);
    await capa().getByRole('button', { name: 'Enviar el enlace' }).click();
    await pagina.waitForTimeout(300);
    check('el mismo correo se dice antes de preguntar', await capa().getByText('Es el correo que ya tienes.').isVisible());
    await capa().locator('#mc-correo-nuevo').fill('probe-card-nuevo@jumpweb.test');
    await capa().getByRole('button', { name: 'Enviar el enlace' }).click();
    await capa().getByText(/Te hemos enviado un enlace a probe-card-nuevo/).first().waitFor({ timeout: 8000 }).catch(() => {});
    t = await texto(capa());
    check('con la contraseña, lo pide: a dónde, con cuál se sigue entrando y cuánto le queda',
        /Te hemos enviado un enlace a probe-card-nuevo@jumpweb\.test\. Hasta que lo abras, sigues entrando con probe-card@jumpweb\.test\. El enlace caduca en (59|60) min\./.test(t) && deLaSonda('pending_email') === 'probe-card-nuevo@jumpweb.test', t.slice(0, 260));
    await captura('19-correo');
    await capa().getByRole('button', { name: 'Cancelar el cambio' }).click();
    await pagina.waitForTimeout(900);
    check('«Cancelar el cambio» lo cancela y lo dice', await capa().getByText('Cambio de correo cancelado').isVisible() && deLaSonda('pending_email') === '');
    await volver();
    check('su flecha vuelve a Mi cuenta con «Tus datos» abierto', (await banda()) === 'Mi cuenta' && (await plegable('Tus datos').getAttribute('aria-expanded')) === 'true');

    await plegable('Acceso').click();
    await pagina.waitForTimeout(300);
    check('«Acceso»: la contraseña, «Vincular Google» (aquí se ofrece) y cerrar las otras sesiones; sin Apple',
        await ajustes.getByText('Vincular Google').isVisible() && await ajustes.getByText('Cerrar sesión en otros dispositivos').isVisible() && ! (await ajustes.getByText(/Apple/).count()));
    await ajustes.getByRole('button', { name: 'Cambiar la contraseña' }).click();
    await pagina.waitForTimeout(500);
    check('«Cambiar la contraseña» abre su paso, con la salida de quien entró con Google', (await banda()) === 'Cambiar la contraseña' && await capa().getByText(/entraste con Google y no tienes/).isVisible());
    await capa().locator('#mc-clave-actual').fill('No-es-esta-2026');
    await capa().locator('#mc-clave-nueva').fill(CLIENTE.password);
    buscar422 = 1;
    await capa().getByRole('button', { name: 'Guardar la contraseña' }).click();
    await pagina.waitForTimeout(1000);
    check('una actual que no es: el «no» del servidor, bajo su campo', (await capa().locator('#mc-clave-actual').getAttribute('aria-invalid')) === 'true' && (await banda()) === 'Cambiar la contraseña', (await texto(capa())).slice(0, 200));
    await captura('20-clave');
    await capa().locator('#mc-clave-actual').fill(CLIENTE.password);
    await capa().getByRole('button', { name: 'Guardar la contraseña' }).click();
    await pagina.waitForTimeout(1200);
    check('con la buena, la guarda, vuelve a Mi cuenta —con «Acceso» abierto— y lo dice arriba',
        (await banda()) === 'Mi cuenta' && await capa().getByText('Contraseña guardada').isVisible() && (await plegable('Acceso').getAttribute('aria-expanded')) === 'true', await banda());

    await ajustes.getByRole('button', { name: 'Cerrar sesión en otros dispositivos', exact: true }).click();
    await pagina.waitForTimeout(500);
    check('«Cerrar» abre «Cerrar sesión en otros dispositivos», que pide la contraseña', (await banda()) === 'Cerrar sesión en otros dispositivos' && await capa().locator('#mc-otras-clave').isVisible(), await banda());
    await capa().locator('#mc-otras-clave').fill(CLIENTE.password);
    await capa().getByRole('button', { name: 'Cerrar las otras sesiones' }).click();
    await pagina.waitForTimeout(1200);
    check('las cierra y lo dice arriba; esta sesión sigue', (await banda()) === 'Mi cuenta' && await capa().getByText('Hemos cerrado la sesión en tus otros dispositivos').isVisible());

    await plegable('Privacidad').click();
    const encuesta = capa().locator('#mc-aj-encuestas');
    await encuesta.waitFor({ state: 'attached', timeout: 8000 }).catch(() => {});
    await pagina.waitForTimeout(300);
    const enServidor = { novedades: deLaSonda('marketing_opt_in') === '1', analitica: deLaSonda('analytics_opt_out') !== '1', encuestas: deLaSonda('surveys_opt_out') !== '1' };
    check('«Privacidad»: novedades, la navegación y la encuesta, como las tiene el servidor',
        (await capa().locator('#mc-aj-novedades').isChecked()) === enServidor.novedades && (await capa().locator('#mc-aj-analitica').isChecked()) === enServidor.analitica && (await encuesta.isChecked()) === enServidor.encuestas, JSON.stringify(enServidor));
    await capa().locator('label[for="mc-aj-encuestas"]').click();
    await pagina.waitForTimeout(1000);
    check('un interruptor se aplica al momento, sin contraseña, y lo confirma', (deLaSonda('surveys_opt_out') === '1') === enServidor.encuestas && await capa().getByText('Guardado', { exact: true }).first().isVisible());
    await capa().locator('label[for="mc-aj-encuestas"]').click();
    await pagina.waitForTimeout(1000);
    check('y se deja como estaba', (deLaSonda('surveys_opt_out') !== '1') === enServidor.encuestas);
    t = await texto(ajustes);
    const pdf = await ajustes.getByRole('link', { name: 'Descargar tu descargo firmado' }).getAttribute('href').catch(() => '');
    check('«Tu descargo firmado»: cuándo, qué versión y su PDF', /Tu descargo firmado Firmado el \d{2}\/\d{2}\/\d{4} · versión \d+/.test(t) && /\/api\/v1\/me\/waiver\/\d+\/pdf$/.test(pdf ?? ''), `${pdf} · ${t.slice(0, 160)}`);
    const [bajada] = await Promise.all([
        pagina.waitForEvent('download', { timeout: 10000 }).catch(() => null),
        ajustes.getByRole('button', { name: 'Descargar mis datos', exact: true }).click(),
    ]);
    await pagina.waitForTimeout(500);
    check('«Descargar mis datos» DESCARGA el documento y lo dice', /^mis-datos-\d{4}-\d{2}-\d{2}\.json$/.test(bajada?.suggestedFilename() ?? '') && await capa().getByText('Tus datos: descargado').isVisible(), bajada?.suggestedFilename() ?? 'sin descarga');
    await captura('21-privacidad');

    await ajustes.getByRole('button', { name: 'Borrar mi cuenta' }).click();
    await pagina.waitForTimeout(600);
    t = await texto(capa());
    check('«Borrar mi cuenta» con una reserva por celebrar: lo dice, y sin un botón que solo puede fallar',
        (await banda()) === 'Borrar tu cuenta' && /Tienes una reserva el \S+ \d+ a las 11:30\. Mientras tengas una por celebrar, la cuenta no se puede borrar/.test(t) && ! (await capa().getByRole('button', { name: 'Borrar mi cuenta' }).isVisible().catch(() => false)), t.slice(0, 320));
    await captura('22-borrar-con-reserva');
    await capa().getByRole('button', { name: 'Dejarlo como está' }).click();
    await pagina.waitForTimeout(500);
    check('«Dejarlo como está» vuelve a Mi cuenta', (await banda()) === 'Mi cuenta');

    await plegable('Recibos').click();
    await pagina.waitForTimeout(1200);
    t = await texto(ajustes);
    check('«Recibos»: el de cada pedido con cuándo, qué y su número, sus líneas del libro y su total (con señal, lo del parque)',
        /[a-zé]{3} \d{1,2} [a-z]{3} · Jump [^€]* · Nº R-SNDCJUMP Pagado online 28\s?€ Total 28\s?€/.test(t)
            && /· Nº R-SNDCCUMPLE Pagado online 50\s?€ A pagar en el parque el día de la visita 119,50\s?€ Total 169,50\s?€/.test(t), t.slice(0, 520));
    await captura('23-recibos');

    // Sin reservas por celebrar, borrar ofrece su formulario; y `#mi-cuenta/privacidad` abre Mi cuenta con ese plegable.
    datos('borrar');
    await cargar('/kids#mi-cuenta/privacidad');
    await ajustes.waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(900);
    check('`#mi-cuenta/privacidad` abre Mi cuenta con «Privacidad» abierto', (await plegable('Privacidad').getAttribute('aria-expanded')) === 'true' && (await plegable('Acceso').getAttribute('aria-expanded')) === 'false');
    await ajustes.getByRole('button', { name: 'Borrar mi cuenta' }).click();
    await pagina.waitForTimeout(600);
    const borrarBoton = capa().getByRole('button', { name: 'Borrar mi cuenta' });
    check('sin reservas: la contraseña, «Entiendo…» y el botón en el contenido, apagado hasta marcarla (no naranja: sin acción en la isla)',
        await capa().locator('#mc-borrar-clave').isVisible() && await borrarBoton.isDisabled() && (await banda()) === 'Borrar tu cuenta');
    await capa().getByText('Entiendo que no se puede deshacer.').click();
    check('marcada, se enciende (aquí no se pulsa)', await borrarBoton.isEnabled());
    await captura('24-borrar');
    await volver();

    // ── 10b · Los avisos (T5e·2) ─────────────────────────────────────────────────────────────────────
    // La vuelta de Google que NO salió: «Vincular Google» de verdad hasta la puerta de Google (se corta ahí: sin salir a
    // internet) y la vuelta con `error`, como cuando se cancela allí. El servidor deja su `status` en la página nueva y Mi
    // cuenta lo dice arriba (hasta la T5e·2 se perdía en silencio).
    // ⚠️ Playwright no intercepta la petición a la que lleva una redirección: se intercepta la IDA del servidor y se lee su
    // redirección sin seguirla (el reto queda en la sesión, que no cambia de identificador).
    let alGoogle = '';
    await pagina.route('**/auth/google/vincular**', async (ruta) => {
        const r = await ruta.fetch({ maxRedirects: 0 });

        alGoogle = r.headers().location ?? '';
        await ruta.abort();
    });
    limitadoresACero();
    await cargar('/kids#mi-cuenta/acceso');
    await ajustes.waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(900);
    check('`#mi-cuenta/acceso` abre Mi cuenta con «Acceso» abierto', (await plegable('Acceso').getAttribute('aria-expanded')) === 'true');
    await ajustes.getByRole('button', { name: 'Vincular Google', exact: true }).click();
    await pagina.waitForTimeout(1800);
    await pagina.unroute('**/auth/google/vincular**');
    const reto = new URL(alGoogle || 'http://sin-google/').searchParams.get('state') ?? '';
    check('«Vincular Google» va a Google por el servidor, con su reto', reto !== '', alGoogle.slice(0, 90));
    await pagina.goto(`${base}/auth/google/callback?state=${encodeURIComponent(reto)}&error=access_denied`, { waitUntil: 'load' });
    await capa().waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(1200);
    check('cancelada en Google, vuelve a la página en Mi cuenta —«Acceso» abierto— y el aviso del servidor sale arriba',
        new URL(pagina.url()).pathname === '/kids' && await capa().getByText('No has terminado de entrar con Google').first().isVisible() && (await plegable('Acceso').getAttribute('aria-expanded')) === 'true', pagina.url());
    await captura('25-aviso-del-servidor');

    // Sin el correo confirmado y con el aviso de la analítica pendiente: los dos arriba, cada uno con lo suyo.
    const verificadaAntes = deLaSonda('email_verified_at');
    const avisadaAntes = deLaSonda('analytics_notified_at');
    const vistaAntes = deLaSonda('analytics_notice_seen_at');
    const aSql = (v) => (v === '' ? 'null' : `'${v}'`);
    tinker(`App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->update(['email_verified_at' => null, 'analytics_notified_at' => now(), 'analytics_notice_seen_at' => null]);`);
    limitadoresACero();
    await cargar('/kids#mi-cuenta');
    await capa().waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(1000);
    t = await texto(capa());
    check('sin el correo confirmado, arriba: confirmarlo, con los reenvíos que quedan y «Reenviar el correo»',
        t.includes('Confirma tu correo con el enlace que te enviamos. Reenvíos que quedan: 4. Reenviar el correo'), t.slice(0, 320));
    check('y, aparte, el de la analítica, con su botón como lo nombra su frase y «Entendido»',
        t.includes('Puedes oponerte en «Privacidad y datos». Privacidad y datos Entendido'), t.slice(0, 480));
    await captura('26-avisos');
    await capa().getByRole('button', { name: 'Reenviar el correo' }).click();
    await pagina.waitForTimeout(1300);
    check('«Reenviar el correo» lo reenvía, lo confirma y espera su minuto',
        await capa().getByText('Te hemos reenviado el correo').isVisible() && await capa().getByRole('button', { name: /^Reenviar en \d+ s$/ }).isDisabled());
    await capa().getByRole('button', { name: 'Entendido', exact: true }).click();
    await pagina.waitForTimeout(1000);
    check('«Entendido» lo despide en el servidor, y se va', deLaSonda('analytics_notice_seen_at') !== '' && ! (await capa().getByText('Puedes oponerte en').isVisible().catch(() => false)));
    tinker(`App\\Domain\\Identity\\Models\\User::where('email', '${CLIENTE.email}')->update(['email_verified_at' => ${aSql(verificadaAntes)}, 'analytics_notified_at' => ${aSql(avisadaAntes)}, 'analytics_notice_seen_at' => ${aSql(vistaAntes)}]);`);
    await cargar('/kids#mi-cuenta');
    await capa().waitFor({ timeout: 15000 }).catch(() => {});
    await pagina.waitForTimeout(900);
    check('con todo al día, ningún aviso (la cuenta, como estaba)', ! (await capa().getByText('Confirma tu correo').isVisible().catch(() => false)) && deLaSonda('email_verified_at') === verificadaAntes);

    await ajustes.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();
    await pagina.waitForURL((u) => new URL(u).pathname === '/', { timeout: 15000 }).catch(() => {});
    await pagina.waitForLoadState('load');
    await pagina.goto(`${base}/kids`, { waitUntil: 'load' });
    await pagina.waitForTimeout(600);
    await abrirMenu();
    check('«Cerrar sesión» sale a la portada, y la página ya no tiene sesión', await pagina.getByText('Entrar o crear cuenta').first().isVisible(), pagina.url());

    // ── 11 · Limpio ──────────────────────────────────────────────────────────────────────────────────
    const deCodigo = (codigo) => errores.filter((e) => e.includes(`status of ${codigo}`));
    const inesperados = errores.filter((e) => ! /status of (401|404|422)/.test(e))
        .concat(deCodigo(401).slice(esperados[401]), deCodigo(404).slice(esperados[404]), deCodigo(422).slice(esperados[422]));
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
