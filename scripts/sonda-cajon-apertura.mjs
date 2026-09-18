/**
 * SONDA DE LA APERTURA DEL CAJÓN — F4 · T2 (`docs/specs/cajon-empaquetable.md` §4.2).
 *
 * La T2 mudó el store `purchase` de Alpine a un controlador sin framework (`resources/js/cajon/controller.js`)
 * publicado en `window.JumpWeb.cajon`. Lo que NINGÚN test de Node puede decir es lo único que importa: que en un
 * navegador real **la carcasa se mueva**. El modo de fallo es silencioso y muy concreto — Alpine solo se entera
 * de un cambio si la escritura pasa por su PROXY reactivo: un `open()` sobre el objeto crudo pone `isOpen` a
 * `true`, no falla nada, y el panel no aparece.
 *
 * Por eso cada vía se comprueba mirando el DOM, no el estado:
 *   A · los abridores REALES de la landing (`@click="$store.purchase.open()"` y compañía), con un clic de verdad;
 *   B · la API nueva, `window.JumpWeb.cajon.open()` / `openWith()` / `close()`;
 *   C · los atributos `data-jw-open*`, inyectando un enlace como lo escribiría quien diseña una landing;
 *   D · el cajón que NACE abierto (`/entradas`), que no pasa por `open()` nunca (la trampa de `#59(b)`).
 * En todas: la clase `is-open` en `.sidecart`, el motor montado dentro de `#sidecart-spa`, el cerrojo de scroll
 * puesto y quitado, los eventos `jw:cajon:*`, y que el modo que publica el motor llega a la clase del panel.
 *
 *   docker compose exec -u sail -T laravel.test node scripts/sonda-cajon-apertura.mjs [etiqueta]
 *
 * Montar el ojo y sus trampas: la skill `/sonda` y la cabecera de `scripts/sonda-cajon.mjs`.
 * ⚠️ `ERR_CONNECTION_REFUSED` es el puente `socat` caído, no el producto. Sale con código 1 si algo falla.
 */
import { chromium } from 'playwright-core';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const BASE = 'http://localhost:8081';
const ETIQUETA = process.argv[2] || 'apertura';
const SALIDA = 'storage/app/audit';
const MOVIL = { width: 390, height: 844 };

const filas = [];
const anotar = (via, que, ok, detalle = '') => filas.push({ via, que, ok: !! ok, detalle: String(detalle) });

/** El estado que se VE, leído del DOM y no del store. */
const estado = (page) => page.evaluate(() => {
    const cajon = document.querySelector('.sidecart');
    const panel = document.querySelector('.sidecart__panel');
    const hueco = document.getElementById('sidecart-spa');

    return {
        abierto: !! cajon?.classList.contains('is-open'),
        visible: !! panel && getComputedStyle(panel).visibility !== 'hidden' && panel.getBoundingClientRect().width > 0,
        modo: [...(panel?.classList ?? [])].filter((c) => c.startsWith('is-')).join(' '),
        // El velo de carga (`.purchase-loading`) lo retira Vue al montar: si sigue ahí, el motor no llegó.
        motor: !! hueco && hueco.children.length > 0 && ! hueco.querySelector(':scope > .purchase-loading'),
        scrollBloqueado: document.documentElement.classList.contains('no-scroll'),
        esElProxy: window.JumpWeb?.cajon === window.Alpine?.store('purchase'),
        eventos: window.__jw ?? [],
    };
});

const esperar = (page, abierto) => page.waitForFunction(
    (quiere) => document.querySelector('.sidecart')?.classList.contains('is-open') === quiere, abierto, { timeout: 8000 },
);

/**
 * ⚠️ **Capturar SOLO con el panel ASENTADO** (la trampa de `#554`/`#565`, pagada otra vez aquí el 2026-09-18):
 * las primeras capturas de esta sonda pillaban el panel a medio entrar —incluso con `reducedMotion`— y dos
 * corridas del MISMO código daban imágenes distintas. Un panel a medio camino no es ningún estado.
 * Asentado = dentro de la ventana y con la misma caja en dos fotogramas seguidos, y las fuentes cargadas.
 */
const asentado = async (page) => {
    await page.waitForFunction(() => new Promise((resolve) => {
        const panel = document.querySelector('.sidecart__panel');
        const caja = () => JSON.stringify(panel.getBoundingClientRect());
        const antes = caja();

        requestAnimationFrame(() => requestAnimationFrame(() => resolve(
            panel.getBoundingClientRect().right <= window.innerWidth + 1 && caja() === antes,
        )));
    }), null, { timeout: 8000, polling: 100 });
    await page.evaluate(() => document.fonts.ready);
    await page.mouse.move(2, 2);
    await page.waitForTimeout(400);
};

const esperarMotor = (page) => page.waitForFunction(() => {
    const h = document.getElementById('sidecart-spa');

    return h && h.children.length > 0 && ! h.querySelector(':scope > .purchase-loading');
}, null, { timeout: 15000 });

/**
 * ⚠️ **La sonda puede agotar el limitador de la API** (60 por minuto y por IP): pagado el 2026-09-18, cuando tres
 * corridas seguidas dejaron el catálogo con un 429 y la captura de `/entradas` salió con «No hay días
 * disponibles» — que parecía una diferencia entre el código viejo y el nuevo y era de la sonda. Se cuentan y, si
 * hay alguno, la corrida se da por NO válida: `SOLO=D` y esperar un minuto.
 */
let limitadas = 0;

async function pagina(context, ruta) {
    const page = await context.newPage();
    page.on('response', (r) => { if (r.status() === 429) limitadas += 1; });
    await page.addInitScript(() => {
        window.__jw = [];
        for (const tipo of ['open', 'close']) {
            document.addEventListener(`jw:cajon:${tipo}`, (e) => window.__jw.push({ tipo, detalle: e.detail }));
        }
    });
    await page.goto(`${BASE}${ruta}`, { waitUntil: 'load' });
    // Alpine lo arranca Livewire: hasta `alpine:init` el store no existe y `JumpWeb.cajon` es el objeto crudo.
    await page.waitForFunction(() => !! window.Alpine?.store?.('purchase') && !! window.JumpWeb?.cajon, null, { timeout: 10000 });

    return page;
}

/** Pulsa el primer abridor de la landing cuyo `@click` contiene ese texto; visible si lo hay, y si no, el primero. */
async function pulsarAbridor(page, fragmento) {
    return page.evaluate((texto) => {
        const candidatos = [...document.querySelectorAll('*')].filter((el) => [...el.attributes].some(
            (a) => (a.name === '@click' || a.name.startsWith('x-on:click')) && a.value.includes(texto),
        ));
        const visible = candidatos.find((el) => el.offsetParent !== null && el.getBoundingClientRect().width > 0);
        const elegido = visible ?? candidatos[0];

        if (! elegido) return { encontrados: 0 };

        elegido.click();

        return { encontrados: candidatos.length, visible: !! visible, etiqueta: elegido.tagName.toLowerCase(), texto: (elegido.textContent || '').trim().slice(0, 40) };
    }, fragmento);
}

async function cerrar(page, via) {
    await page.evaluate(() => document.querySelector('.sidecart__close')?.click());
    await esperar(page, false);
    const e = await estado(page);
    anotar(via, 'cierra con el botón de la carcasa y suelta el scroll', ! e.abierto && ! e.scrollBloqueado, JSON.stringify({ abierto: e.abierto, scroll: e.scrollBloqueado }));
}

const navegador = await chromium.launch();
const context = await navegador.newContext({ viewport: MOVIL, reducedMotion: 'reduce' });
await mkdir(`${SALIDA}/cajon-${ETIQUETA}`, { recursive: true });

/** `SOLO=D` (o `SOLO=A,E`) corre solo esas secciones: cada una abre páginas y gasta del limitador de la API. */
const corre = (seccion) => ! process.env.SOLO || process.env.SOLO.split(',').includes(seccion);
let page; let e; let clic;

try {
    // ── A · los abridores REALES de la landing (Alpine) ────────────────────────────────────────
    if (corre('A')) {
        page = await pagina(context, '/');
        e = await estado(page);
        anotar('A', '`window.JumpWeb.cajon` ES el proxy reactivo del store de Alpine', e.esElProxy);
        anotar('A', 'la portada nace con el cajón cerrado', ! e.abierto && ! e.scrollBloqueado);

        clic = await pulsarAbridor(page, '$store.purchase.open()');
        await esperar(page, true); await esperarMotor(page);
        e = await estado(page);
        anotar('A', '`$store.purchase.open()` abre, monta el motor y bloquea el scroll', e.abierto && e.visible && e.motor && e.scrollBloqueado, JSON.stringify(clic));
        anotar('A', 'el modo que publica el MOTOR llega a la clase del panel', e.modo.includes('is-catalog'), e.modo);
        await asentado(page);
        await page.screenshot({ path: `${SALIDA}/cajon-${ETIQUETA}/A-open@390.png` });
        await cerrar(page, 'A');

        clic = await pulsarAbridor(page, "$store.purchase.openAccount($event, 'register')");
        await esperar(page, true); await esperarMotor(page);
        await page.waitForFunction(() => !! document.querySelector('#sidecart-spa input[type="password"], #sidecart-spa input[type="email"]'), null, { timeout: 8000 });
        e = await estado(page);
        anotar('A', "`openAccount($event, 'register')` abre EN la zona de alta", e.abierto && e.motor, `${JSON.stringify(clic)} · ${e.modo}`);
        await cerrar(page, 'A');

        clic = await pulsarAbridor(page, "$store.purchase.openWith({ type: 'product'");
        if (clic.encontrados > 0) {
            await esperar(page, true); await esperarMotor(page);
            await page.waitForFunction(() => document.querySelector('.sidecart__panel')?.classList.contains('is-booking'), null, { timeout: 10000 });
            e = await estado(page);
            anotar('A', "`openWith({ type: 'product' })` abre EN el producto (modo booking)", e.abierto && e.modo.includes('is-booking'), `${JSON.stringify(clic)} · ${e.modo}`);
            await cerrar(page, 'A');
        } else {
            anotar('A', "`openWith({ type: 'product' })`: la portada no tiene ese abridor", true, 'sin abridor que pulsar (no es un fallo)');
        }
        await page.close();
    }

    // ── B · la API nueva ───────────────────────────────────────────────────────────────────────
    if (corre('B')) {
        page = await pagina(context, '/');
        await page.evaluate(() => window.JumpWeb.cajon.open());
        await esperar(page, true); await esperarMotor(page);
        e = await estado(page);
        anotar('B', '`window.JumpWeb.cajon.open()` MUEVE la carcasa (pasa por el proxy)', e.abierto && e.visible && e.motor && e.scrollBloqueado);
        await page.evaluate(() => window.JumpWeb.cajon.close());
        await esperar(page, false);
        e = await estado(page);
        anotar('B', '`window.JumpWeb.cajon.close()` la cierra y suelta el scroll', ! e.abierto && ! e.scrollBloqueado);
        anotar('B', 'la página se entera por los eventos `jw:cajon:open` y `jw:cajon:close`', e.eventos.map((x) => x.tipo).join(',') === 'open,close', JSON.stringify(e.eventos));

        await page.evaluate(() => window.JumpWeb.cajon.openWith({ type: 'packs' }));
        await esperar(page, true); await esperarMotor(page);
        e = await estado(page);
        anotar('B', "`openWith({ type: 'packs' })` abre con el motor YA montado", e.abierto && e.motor, e.modo);
        await page.close();
    }

    // ── C · los atributos, como los escribiría quien diseña una landing ────────────────────────
    if (corre('C')) {
        page = await pagina(context, '/');
        await page.evaluate(() => {
            const a = document.createElement('a');
            a.href = '/entradas'; a.id = 'jw-prueba'; a.setAttribute('data-jw-open', ''); a.textContent = 'Reservar';
            a.style.cssText = 'position:fixed;left:8px;top:8px;z-index:99999;padding:12px;background:#fff';
            document.body.appendChild(a);
        });
        await page.click('#jw-prueba');
        await esperar(page, true); await esperarMotor(page);
        e = await estado(page);
        anotar('C', '`data-jw-open` abre el cajón SIN navegar al `href`', e.abierto && e.motor && new URL(page.url()).pathname === '/', page.url());
        await cerrar(page, 'C');

        await page.evaluate(() => { const a = document.getElementById('jw-prueba'); a.removeAttribute('data-jw-open'); a.setAttribute('data-jw-open-account', 'login'); a.href = '/login'; });
        await page.click('#jw-prueba');
        await esperar(page, true); await esperarMotor(page);
        await page.waitForFunction(() => !! document.querySelector('#sidecart-spa input[type="password"]'), null, { timeout: 8000 });
        e = await estado(page);
        anotar('C', '`data-jw-open-account="login"` abre EN la zona de entrar', e.abierto && e.motor && new URL(page.url()).pathname === '/', e.modo);
        await page.close();
    }

    // ── E · la CARCASA con dueño sin framework (F4 · T3a): cierre, Escape y foco ────────────────
    if (corre('E')) {
        page = await pagina(context, '/');
        e = await estado(page);
        const alpineEnLaCarcasa = await page.evaluate(() => [...document.querySelectorAll('.sidecart, .sidecart__backdrop, .sidecart__panel, .sidecart__close')]
            .flatMap((el) => [...el.attributes].map((a) => a.name)).filter((n) => n.startsWith('x-') || n.startsWith(':') || n.startsWith('@')));
        anotar('E', 'la carcasa no lleva ni un atributo de Alpine', alpineEnLaCarcasa.length === 0, alpineEnLaCarcasa.join(' '));

        await page.keyboard.press('Escape');
        e = await estado(page);
        anotar('E', 'Escape con el cajón CERRADO no anuncia un cierre que no ocurrió', e.eventos.length === 0, JSON.stringify(e.eventos));

        await page.evaluate(() => window.JumpWeb.cajon.open());
        await esperar(page, true); await esperarMotor(page);
        await page.waitForFunction(() => document.querySelector('.sidecart')?.contains(document.activeElement), null, { timeout: 5000 });
        anotar('E', 'al abrir, el foco entra DENTRO del panel', true);

        // Tab hasta dar la vuelta: el foco no puede salir nunca de la carcasa.
        let fuera = 0;
        for (let i = 0; i < 40; i += 1) {
            await page.keyboard.press('Tab');
            if (! await page.evaluate(() => document.querySelector('.sidecart').contains(document.activeElement))) fuera += 1;
        }
        for (let i = 0; i < 6; i += 1) {
            await page.keyboard.press('Shift+Tab');
            if (! await page.evaluate(() => document.querySelector('.sidecart').contains(document.activeElement))) fuera += 1;
        }
        anotar('E', 'Tab y Shift+Tab no escapan del panel (46 pulsaciones)', fuera === 0, `${fuera} veces fuera`);

        await page.keyboard.press('Escape');
        await esperar(page, false);
        e = await estado(page);
        anotar('E', 'Escape cierra y suelta el scroll', ! e.abierto && ! e.scrollBloqueado);

        await page.close();

        // El telón se pulsa en ESCRITORIO: a 390 px el panel ocupa todo el ancho y no queda telón que tocar.
        const escritorio = await navegador.newContext({ viewport: { width: 1280, height: 800 }, reducedMotion: 'reduce' });
        page = await pagina(escritorio, '/');
        await page.evaluate(() => window.JumpWeb.cajon.open());
        await esperar(page, true);
        // ⚠️ Medir el panel cuando ha TERMINADO de entrar: recién abierto su `left` es el ancho de la ventana
        // (sigue fuera, a la derecha) y el detalle de la fila diría «panel a 1280 px», que no es ninguna posición.
        await page.waitForFunction(() => document.querySelector('.sidecart__panel').getBoundingClientRect().right <= window.innerWidth + 1, null, { timeout: 5000 });
        const izquierda = await page.evaluate(() => document.querySelector('.sidecart__panel').getBoundingClientRect().left);
        if (izquierda > 40) {
            await page.mouse.click(Math.round(izquierda / 2), 400);
            await esperar(page, false);
            e = await estado(page);
            anotar('E', 'un clic en el telón cierra (1280 px)', ! e.abierto && ! e.scrollBloqueado, `panel a ${Math.round(izquierda)} px del borde`);
        } else {
            anotar('E', 'un clic en el telón cierra (1280 px)', false, `no hay telón que pulsar: el panel empieza en ${izquierda}`);
        }
        await page.close();
        await escritorio.close();
    }

    // ── F · una página AJENA: sin carcasa, sin `data-boot` y sin Alpine (F4 · T3b) ─────────────
    // Es el ensayo de lo que promete la T5: una landing que no pinta el producto monta el cajón. Se sirve
    // interceptando una ruta del MISMO origen —no `setContent`, que deja la página en `about:blank` y dejaría
    // fuera las cookies y la API—, con lo único que tendrá una landing de instancia: dos hojas y el paquete.
    if (corre('F')) {
        const entrada = JSON.parse(await readFile('public/build/manifest.json', 'utf8'))['resources/js/app.js'].file;
        // ⚠️ La página ajena carga las MISMAS hojas y fuentes que el producto, tomadas de su propio HTML. Sin
        // ellas la comparación mentiría hacia el lado fácil: medido el 2026-09-18, sin la hoja de fuentes el
        // texto cambia de métrica y un botón del bloque de cuenta se sale del panel. Lo que esta sección mide
        // es que el cajón MONTE donde no hay producto; que se vea igual es cosa del tema de la instancia
        // (F5) y de la hoja propia del paquete (T4).
        const cabeza = (await (await context.request.get(`${BASE}/`)).text())
            .match(/<link[^>]+rel="(?:stylesheet|preconnect)"[^>]*>/g)?.join('\n') ?? '';
        page = await context.newPage();
        await page.route(`${BASE}/landing-ajena-de-prueba`, (ruta) => ruta.fulfill({
            status: 200,
            contentType: 'text/html; charset=utf-8',
            body: `<!doctype html><html lang="es"><head><meta charset="utf-8">${cabeza}
                <script type="module" src="/build/${entrada}"></script></head>
                <body><h1>Landing de otra instancia</h1><a href="/entradas" data-jw-open>Reservar</a></body></html>`,
        }));
        await page.goto(`${BASE}/landing-ajena-de-prueba`, { waitUntil: 'load' });
        await page.waitForFunction(() => !! window.JumpWeb?.cajon, null, { timeout: 10000 });

        const antes = await page.evaluate(() => ({
            carcasa: !! document.querySelector('.sidecart'),
            alpine: !! window.Alpine,
            boot: !! document.querySelector('[data-boot]'),
        }));
        anotar('F', 'la página ajena NO trae carcasa, ni `data-boot`, ni Alpine', ! antes.carcasa && ! antes.alpine && ! antes.boot, JSON.stringify(antes));
        anotar('F', 'y aun así tiene la API del cajón (`window.JumpWeb.cajon`)', true);

        await page.click('a[data-jw-open]');
        await esperar(page, true);
        await esperarMotor(page);
        e = await estado(page);
        anotar('F', '`data-jw-open` CONSTRUYE la carcasa, la abre y monta el motor', e.abierto && e.visible && e.motor && e.scrollBloqueado, e.modo);
        anotar('F', 'sin navegar al `href` de la puerta', new URL(page.url()).pathname === '/landing-ajena-de-prueba', page.url());

        // ⚠️ El catálogo llega por su propia petición DESPUÉS de montar: medir en cuanto Vue monta da cero
        // botones y parece que el motor no pinta nada. Se espera a que haya algo con que operar.
        await page.waitForFunction(() => document.querySelectorAll('#sidecart-spa button, #sidecart-spa a').length > 0, null, { timeout: 15000 });

        const pintado = await page.evaluate(() => {
            const titulo = document.querySelector('.sidecart__title');
            const catalogo = document.querySelectorAll('#sidecart-spa button, #sidecart-spa a').length;

            return { titulo: titulo?.textContent ?? '', ancho: Math.round(document.querySelector('.sidecart__panel').getBoundingClientRect().width), catalogo };
        });
        anotar('F', 'la carcasa construida lleva su rótulo y el motor pinta el catálogo dentro', pintado.titulo !== '' && pintado.catalogo > 0, JSON.stringify(pintado));

        await asentado(page);
        await page.screenshot({ path: `${SALIDA}/cajon-${ETIQUETA}/F-ajena@390.png` });

        await page.evaluate(() => document.querySelector('.sidecart__close').click());
        await esperar(page, false);
        e = await estado(page);
        anotar('F', 'y se cierra, soltando el scroll de la página ajena', ! e.abierto && ! e.scrollBloqueado);
        await page.close();
    }

    // ── D · el cajón que NACE abierto (no pasa por `open()`) ───────────────────────────────────
    if (corre('D')) {
        page = await pagina(context, '/entradas');
        await esperarMotor(page);
        e = await estado(page);
        anotar('D', '`/entradas` nace abierto, con el motor montado y el scroll bloqueado', e.abierto && e.motor && e.scrollBloqueado, e.modo);

        // `[DECIDIDO owner]` `#634`: naciendo abierto, el foco entra en el panel — si no, quien llega con
        // teclado o lector de pantalla se encuentra el diálogo delante y el foco detrás del telón.
        const dentro = await page.evaluate(() => {
            const root = document.querySelector('.sidecart');

            return { dentro: root.contains(document.activeElement), quien: `${document.activeElement?.tagName?.toLowerCase()}.${(document.activeElement?.className || '').toString().split(' ')[0]}` };
        });
        anotar('D', 'naciendo abierto, el foco entra DENTRO del panel (`#634`)', dentro.dentro, dentro.quien);
        await asentado(page);
        await page.screenshot({ path: `${SALIDA}/cajon-${ETIQUETA}/D-entradas@390.png` });
        await page.close();
    }
} catch (error) {
    anotar('ERROR', error.message.split('\n')[0], false);
}

anotar('SONDA', 'ninguna petición chocó con el limitador de la API (si no, las capturas no valen)', limitadas === 0, `${limitadas} respuestas 429`);

await navegador.close();
await writeFile(`${SALIDA}/cajon-${ETIQUETA}.json`, JSON.stringify(filas, null, 2));

for (const f of filas) console.log(`${f.ok ? '✓' : '✗'} ${f.via.padEnd(5)} ${f.que}${f.ok && ! f.detalle ? '' : `\n        ${f.detalle}`}`);
const malas = filas.filter((f) => ! f.ok).length;
console.log(`\n${filas.length - malas}/${filas.length} comprobaciones · ${SALIDA}/cajon-${ETIQUETA}.json`);
process.exit(malas === 0 && filas.length > 0 ? 0 : 1);
