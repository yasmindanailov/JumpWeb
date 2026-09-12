/**
 * SONDA DE COLOR DEL CAJÓN — la grieta 01: ¿qué color pinta cada papel del cajón, y de dónde sale?
 *
 * Hermana de `scripts/sonda-cajon.mjs` (que mide TALLA y reflujo, la grieta 00). Ésta mide COLOR:
 * el valor COMPUTADO, no el declarado, porque la grieta 01 es precisamente un token cuyo valor
 * depende del ÁMBITO — `--zone-1` vale `var(--brand)` en `:root` y se re-escopa EN LÍNEA sobre el
 * elemento de cada zona (`ThemeSettings::zoneStyle()`).
 *
 * ⚠️ **Está versionada a propósito** (la lección de `#475`): la sonda de la auditoría del cajón
 * vivía en `storage/app/`, gitignorada, y se perdió con sus cien medidas y sus cinco trampas.
 *
 * ── QUÉ MIDE, POR PANTALLA ───────────────────────────────────────────────────────────────────────
 *   · el ÁMBITO de `--zone-1`: su valor resuelto en `:root`, en `.sidecart__panel` y en cada
 *     elemento que lo lee — es lo único que dice si dentro del cajón el token es un DATO o la marca;
 *   · por cada papel del censo (acción · cifra · enlace · casilla · identidad · icono), el color
 *     computado que acaba pintando;
 *   · el CONTRASTE del rótulo contra su propio relleno, con la pila de fondos (la trampa de `#505`:
 *     el fondo de un texto es el de su ancestro más cercano con fondo, no el del `<body>`).
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   Chromium y el puente, una vez por contenedor (receta completa en `sonda-cajon.mjs`):
 *     docker compose exec -u sail -T laravel.test node node_modules/playwright-core/cli.js install chromium
 *     docker compose exec -d -T laravel.test bash -lc 'socat TCP-LISTEN:8081,fork,reuseaddr TCP:127.0.0.1:80'
 *   docker compose exec -u sail -T laravel.test node scripts/color-del-cajon.mjs antes
 *   … y tras el cambio: `node scripts/color-del-cajon.mjs despues`
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = 'http://localhost:8081';
const ETIQUETA = process.argv[2] ?? 'antes';
const SALIDA = 'storage/app/audit';

/** La cuenta de sonda (memoria del proyecto; no está en el repo ni en un seeder). */
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' };

const MOVIL = { width: 390, height: 844, hasTouch: true, isMobile: true };

/**
 * Los SUJETOS: un selector por papel del censo, con el papel que hace y la propiedad que lo pinta.
 *
 * ⚠️ Se nombra el PAPEL y no «el botón azul»: lo que esta tanda cambia es a qué rol responde cada
 * pieza, así que el informe tiene que poder leerse sin saber de qué color era antes.
 */
const SUJETOS = [
    { sel: '.bk-cta', papel: 'acción · avanza la compra', prop: 'background-color' },
    { sel: '.cartbar', papel: 'acción · lleva al carrito', prop: 'background-color' },
    { sel: '.cartbar__count', papel: 'cifra · cuántas líneas', prop: 'background-color' },
    // ⚠️ Era `.btn--zone` hasta `#551`; la variante se retiró del producto y su sustituta es
    // `.btn--ink`. Un sujeto que no existe sale en la lista de «no alcanzados» y se lee como un hueco
    // del recorrido, no como lo que es: una pieza muerta.
    { sel: '.btn--ink', papel: 'secundario · primario de cuenta y auth', prop: 'background-color' },
    { sel: '.bk-cta--sells', papel: 'ACCIÓN · el único que cobra («Pagar»)', prop: 'background-color' },
    { sel: '.bk-back', papel: 'enlace · volver', prop: 'color' },
    { sel: '.bk-seg__item.is-current .bk-seg__bar', papel: 'progreso · fase actual', prop: 'background-color' },
    { sel: '.bk-context', papel: 'contexto · qué estoy comprando', prop: 'color' },
    { sel: '.catalog__badge', papel: 'chapa · destacado del catálogo', prop: 'background-color' },
    { sel: '.catalog-acc__icon', papel: 'icono · cabecera de categoría', prop: 'background-color' },
    { sel: '.purchase__chip.is-active', papel: 'IDENTIDAD · el chip de zona', prop: 'background-color' },
    { sel: '.daystrip__day.is-selected', papel: 'selección · el día elegido', prop: 'background-color' },
    { sel: '.cal__day.is-selected', papel: 'selección · el día del calendario', prop: 'background-color' },
    { sel: '.cart__when', papel: 'dato · cuándo es la visita', prop: 'color' },
    { sel: '.auth__switch button', papel: 'enlace · cambiar de formulario', prop: 'color' },
    { sel: '.auth__link', papel: 'enlace · he olvidado mi contraseña', prop: 'color' },
    { sel: '.acct__count', papel: 'cifra · la píldora del contador', prop: 'background-color' },
    { sel: '.acct__avatar', papel: 'inicial · el avatar', prop: 'color' },
    { sel: '.acct__alert', papel: 'aviso · lo que falta', prop: 'background-color' },
    { sel: '.acct__btn--primary', papel: 'acción · primario de la tira', prop: 'background-color' },
    { sel: '.acc-tile__ico svg', papel: 'icono · las ocho zonas', prop: 'color' },
    { sel: '.check input', papel: 'casilla · el tilde', prop: 'accent-color' },
];

/** Lo que corre EN EL NAVEGADOR: ámbito del token y color computado de cada sujeto. */
const MEDIR = (sujetos) => {
    const panel = document.querySelector('.sidecart__panel');
    if (! panel) return { error: 'sin panel' };

    const visible = (el) => {
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden';
    };

    const rgb = (v) => {
        const m = /rgba?\(([^)]+)\)/.exec(v || '');
        if (! m) return null;
        const p = m[1].split(/[,\s/]+/).filter(Boolean).map(Number);
        return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
    };

    /**
     * El fondo EFECTIVO de un elemento: se sube por los ancestros hasta encontrar uno opaco.
     * ⚠️ La trampa de `#505`: medir el contraste contra el fondo del `<body>` da por bueno un texto
     * ilegible cuando vive dentro de una caja teñida.
     */
    const fondoEfectivo = (el) => {
        for (let n = el; n; n = n.parentElement) {
            const c = rgb(getComputedStyle(n).backgroundColor);
            if (c && c.a > 0.95) return c;
        }
        return { r: 255, g: 255, b: 255, a: 1 };
    };

    const lum = (c) => {
        const f = (x) => {
            const s = x / 255;
            return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
        };
        return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
    };

    const ratio = (a, b) => {
        if (! a || ! b) return null;
        const x = lum(a), y = lum(b);
        return Math.round(((Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)) * 100) / 100;
    };

    // ── EL ÁMBITO DEL TOKEN ──────────────────────────────────────────────────────────────────────
    const ambito = {
        root: getComputedStyle(document.documentElement).getPropertyValue('--zone-1').trim(),
        panel: getComputedStyle(panel).getPropertyValue('--zone-1').trim(),
        action: getComputedStyle(document.documentElement).getPropertyValue('--action').trim(),
        interactive: getComputedStyle(panel).getPropertyValue('--interactive').trim(),
        fg: getComputedStyle(panel).getPropertyValue('--fg').trim(),
    };

    /**
     * ¿Alguien DENTRO del panel re-escopa `--zone-1`? Es la pregunta que decide si la grieta 01 es
     * «un dato repinta el botón» o «acción ≠ marca».
     * ⚠️ Se compara el valor RESUELTO en cada nodo contra el del panel, no se busca un `style=`:
     * el re-escopado puede venir de una regla, de un atributo o de un ancestro.
     */
    const reescopan = [];
    for (const el of panel.querySelectorAll('*')) {
        const v = getComputedStyle(el).getPropertyValue('--zone-1').trim();
        if (v && v !== ambito.panel) {
            reescopan.push({
                sel: el.className && typeof el.className === 'string'
                    ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.')
                    : el.tagName.toLowerCase(),
                valor: v,
            });
        }
    }

    // ── LOS SUJETOS ──────────────────────────────────────────────────────────────────────────────
    const medidos = [];
    for (const s of sujetos) {
        const el = [...panel.querySelectorAll(s.sel)].find(visible);
        if (! el) continue;

        const cs = getComputedStyle(el);
        const valor = cs.getPropertyValue(s.prop).trim();
        const relleno = s.prop === 'background-color' ? rgb(valor) : null;
        const tinta = rgb(cs.color);

        medidos.push({
            sel: s.sel,
            papel: s.papel,
            prop: s.prop,
            valor,
            zona1Aqui: cs.getPropertyValue('--zone-1').trim(),
            // Contraste del rótulo contra su propio relleno si lo tiene; si no, contra su pila.
            contraste: ratio(tinta, relleno && relleno.a > 0.95 ? relleno : fondoEfectivo(el)),
        });
    }

    return { ambito, reescopan, medidos };
};

const esperarCajon = (page, zona = 'embudo') => page.waitForSelector(
    zona === 'embudo'
        ? '.sidecart.is-open .purchase[data-engine="spa"]'
        : '.sidecart.is-open .acc-tiles, .sidecart.is-open #login-email',
    { timeout: 20000 },
);

/** El aviso de cookies tapa el cajón en móvil: nueve botones y seis ocultos (ver `sonda-cajon.mjs`). */
async function aceptarCookies(page) {
    const banner = page.locator('.cookie-consent-root');
    try {
        await banner.waitFor({ state: 'visible', timeout: 3000 });
    } catch {
        return;
    }
    const boton = banner.locator('button:visible', { hasText: /^acept/i }).first();
    if (await boton.count()) {
        await boton.click({ timeout: 3000 });
        await banner.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {});
    }
}

async function recorrer(context, informe) {
    const page = await context.newPage();
    page.setDefaultTimeout(12000);

    let paso = 'arranque';
    page.on('pageerror', (e) => console.error(`   JS en ${paso}: ${e.message.split('\n')[0]}`));

    /**
     * ⚠️⚠️ **DOS TRAMPAS DEL INSTRUMENTO, las dos medidas y las dos con cifras creíbles.**
     *
     * 1. **El ratón se queda donde pulsó.** Tras pulsar la barra del carrito el puntero acaba encima del
     *    CTA del pie, así que lo que se medía era su `:hover` — un cian sobre un botón que reposa en
     *    tinta. Se aparta el ratón antes de medir.
     * 2. **La transición estaba a medias.** `.bk-cta` interpola el fondo en `--dur-sale`, y la sonda
     *    llegaba en mitad del recorrido: salía `rgb(24,145,190)`, que no es ningún valor del sistema
     *    —es el punto intermedio entre la tinta y la marca— y se lee como un color inventado.
     *    ▶ No se arregla esperando por reloj: el contexto corre con **movimiento reducido**, y ahí el
     *    propio CSS del cajón declara `transition: none` para estas piezas. El color pasa a ser el
     *    valor EN REPOSO, que es el único que describe una decisión.
     */
    const medir = async (pantalla) => {
        paso = pantalla;
        await page.mouse.move(0, 0);
        const datos = await page.evaluate(MEDIR, SUJETOS);
        informe.push({ pantalla, ...datos });
        return datos;
    };

    // ── EL EMBUDO ────────────────────────────────────────────────────────────────────────────────
    await page.goto(`${BASE}/entradas`, { waitUntil: 'domcontentloaded' });
    await aceptarCookies(page);
    await esperarCajon(page);
    await page.waitForSelector('.catalog__item');
    await medir('01-catalogo');

    await page.locator('.catalog__item').first().click();
    await page.waitForSelector('.daystrip__day, .purchase__empty');
    await medir('02-fecha');

    await page.locator('.daystrip__day').first().click();
    await page.waitForSelector('.purchase__chip');
    await medir('03-hora');

    // ⚠️⚠️ **Los pasos se montan con `v-else-if`**: solo uno vive a la vez, así que el día ELEGIDO
    // de la tira y del calendario **no se ven desde el paso de la hora** — hay que volver. La
    // primera pasada midió `03-hora` esperando encontrarlos ahí y los dio por inexistentes.
    // *Un sujeto que el recorrido no alcanza no es un sujeto que no exista.*
    await page.locator('.bk-back').first().click();
    await page.waitForSelector('.daystrip__day.is-selected', { timeout: 6000 }).catch(() => {});
    await medir('03b-fecha-elegida');

    if (await page.locator('.cal-more').count()) {
        await page.locator('.cal-more').click();
        await page.waitForSelector('.cal__day', { timeout: 6000 }).catch(() => {});
        await medir('03c-calendario');
    }

    await page.locator('.daystrip__day.is-selected, .daystrip__day').first().click();
    await page.waitForSelector('.purchase__chip');
    await page.locator('.purchase__chip:not(.is-full)').first().click();
    await page.waitForSelector('.qtybox');
    await medir('04-hora-elegida');

    await page.locator('.bk-cta').click();
    await page.waitForSelector('.cartbar, .cart__item');
    if (await page.locator('.cartbar').count()) {
        await page.locator('.cartbar').click();
    }
    await page.waitForSelector('.cart__item, .purchase__empty');
    await medir('05-carrito');

    // ⚠️ **La BARRA del carrito vive en el paso 1, no en el 4** (`foot.js::cartBar`): es el pie del
    // catálogo cuando ya hay cesta. Así que para verla hay que VOLVER al catálogo con algo dentro —
    // medirla «al añadir» no la alcanza, porque el embudo salta al carrito.
    await page.locator('.bk-back').first().click();
    await page.waitForSelector('.cartbar', { timeout: 6000 }).catch(() => {});
    await medir('05b-catalogo-con-cesta');

    if (await page.locator('.cartbar').count()) {
        await page.locator('.cartbar').click();
        await page.waitForSelector('.cart__item');
    }

    await page.locator('.bk-cta').click();
    await page.waitForSelector('.auth, .purchase__note');
    await medir('06-identificarse');

    // ── EL ÁREA DE CUENTA ────────────────────────────────────────────────────────────────────────
    await page.goto(`${BASE}/mi-cuenta`, { waitUntil: 'domcontentloaded' });
    await esperarCajon(page, 'cuenta');

    if (await page.locator('#login-email').count()) {
        paso = 'entrar';
        await page.fill('#login-email', CLIENTE.email);
        await page.fill('#login-password', CLIENTE.password);
        await page.click('.sidecart__panel button[type="submit"]');
    }

    await page.waitForSelector('.acc-tiles .acc-tile', { timeout: 20000 });
    await medir('10-cuenta-indice');

    // ⚠️ La TIRA de cuenta (`.acct`) solo se despliega en modo `catalog` y `result` —en los demás se
    // colapsa a `0fr`—, y su variante de MIEMBRO (con la píldora del contador y el aviso de lo que
    // falta) necesita sesión. Así que hay que volver al catálogo YA identificado: sin esta vuelta,
    // `.acct__count` y `.acct__alert` no existen en todo el recorrido.
    await page.goto(`${BASE}/entradas`, { waitUntil: 'domcontentloaded' });
    await esperarCajon(page);
    await page.waitForSelector('.catalog__item');
    await medir('01b-catalogo-con-sesion');

    const zonas = await page.locator('.acc-tile').count();
    for (let i = 0; i < zonas; i++) {
        await page.goto(`${BASE}/mi-cuenta`, { waitUntil: 'domcontentloaded' });
        await esperarCajon(page, 'cuenta');
        await page.waitForSelector('.acc-tiles .acc-tile');
        const tile = page.locator('.acc-tile').nth(i);
        const rotulo = (await tile.locator('.acc-tile__name').innerText()).trim().toLowerCase().replace(/[^a-z0-9]+/g, '-');
        await tile.click();
        // Se espera a que el velo se vaya, no a un reloj (la trampa medida en `sonda-cajon.mjs`).
        await page.waitForSelector('.jj-loading', { state: 'hidden' }).catch(() => {});
        await page.waitForFunction(() => ! document.querySelector('.sidecart__panel .jj-spinner')).catch(() => {});
        await medir(`1${i + 1}-zona-${rotulo}`);
    }

    await page.close();
}

const navegador = await chromium.launch();
const informe = [];
/** Movimiento REDUCIDO a propósito: ver la nota de `medir()` — es lo que congela las transiciones de
 *  color del pie y hace que lo medido sea el valor en reposo. Aquí no se mide movimiento, se mide color. */
const context = await navegador.newContext({ viewport: MOVIL, reducedMotion: 'reduce' });

await mkdir(SALIDA, { recursive: true });

try {
    await recorrer(context, informe);
} catch (e) {
    informe.push({ pantalla: 'ERROR', error: e.message.split('\n')[0] });
    console.error(`✗ ${e.message.split('\n')[0]}`);
}

await context.close();
await navegador.close();
await writeFile(`${SALIDA}/color-cajon-${ETIQUETA}.json`, JSON.stringify(informe, null, 2));

// ── EL INFORME ───────────────────────────────────────────────────────────────────────────────────
const primero = informe.find((f) => f.ambito);
if (primero) {
    console.log('\nÁMBITO DEL TOKEN');
    console.log(`  --zone-1 en :root            ${primero.ambito.root}`);
    console.log(`  --zone-1 en .sidecart__panel ${primero.ambito.panel}`);
    console.log(`  --action                     ${primero.ambito.action || '(sin color de acción)'}`);
    console.log(`  --interactive en el panel     ${primero.ambito.interactive}`);
    console.log(`  --fg en el panel              ${primero.ambito.fg}`);
}

const reescopan = informe.flatMap((f) => f.reescopan ?? []);
console.log(`\nNODOS DEL PANEL QUE RE-ESCOPAN --zone-1: ${reescopan.length}`);
for (const r of reescopan.slice(0, 12)) console.log(`  ${r.sel} → ${r.valor}`);

// Un sujeto, una fila: el primer sitio donde se ve, con su color y su contraste.
const visto = new Map();
for (const f of informe) {
    for (const m of f.medidos ?? []) {
        if (! visto.has(m.sel)) visto.set(m.sel, { ...m, pantalla: f.pantalla });
    }
}

console.log(`\n${'selector'.padEnd(42)} ${'papel'.padEnd(34)} ${'valor'.padEnd(22)} contraste`);
for (const m of visto.values()) {
    console.log(
        `${m.sel.padEnd(42)} ${m.papel.padEnd(34)} ${String(m.valor).padEnd(22)} ` +
        `${m.contraste === null ? '—' : m.contraste}`,
    );
}

const faltan = SUJETOS.filter((s) => ! visto.has(s.sel)).map((s) => s.sel);
if (faltan.length) console.log(`\n⚠️ sujetos que el recorrido NO alcanzó: ${faltan.join(' · ')}`);
console.log(`\ninforme: ${SALIDA}/color-cajon-${ETIQUETA}.json`);
