/**
 * SONDA DEL ARMAZÓN DEL CAJÓN — la geometría de las tres bandas, medida en el navegador.
 *
 * Hermana de `scripts/sonda-cajon.mjs`, que mide TALLA DE LETRA y reflujo. Ésta mide otra cosa: la
 * **caja** del armazón —la banda de fases, la zona que scrollea y el pie— contra lo que fija el
 * artboard `Armazon Cajon PJP` (parada 01) y las doce decisiones cerradas de la parada 05.
 *
 * ⚠️ **Mide REPOSO, nunca `:hover` ni `:active`.** La trampa está pagada en `#551`: una sonda que
 * fuerza el hover devuelve el valor de una transición a medias, o sea un color que no existe en el
 * sistema. Los estados se auditan LEYENDO la hoja, que es donde se declaran; aquí se mide lo que el
 * navegador resuelve sin que nadie toque nada.
 *
 * ⚠️ **El color de un pseudo-elemento no se lee del elemento**: el relleno de la barra de fase vive
 * en `.bk-seg__bar::after`, así que `getComputedStyle` va con su segundo argumento. Sin él sale el
 * fondo del carril —la pista— y parece que la barra no se pinta.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   Requiere Chromium y el puente, como la sonda hermana (su cabecera tiene la receta):
 *     docker compose exec -u sail -T laravel.test node scripts/sonda-armazon.mjs antes
 *   La etiqueta nombra la salida: `storage/app/audit/armazon-<etiqueta>.json` (gitignorada).
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = 'http://localhost:8081';
const ETIQUETA = process.argv[2] ?? 'antes';
const SALIDA = 'storage/app/audit';

const MOVIL = { width: 390, height: 844, hasTouch: true, isMobile: true };
const ESCRITORIO = { width: 1280, height: 900 };

/** La cuenta de sonda (memoria del proyecto; no está en el repo ni en un seeder). */
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' };

/**
 * Lo que se mide dentro del panel. Corre en el navegador.
 *
 * Devuelve `null` en cada pieza que no está en esa pantalla: **ausente es un dato**, no un fallo —el
 * pie no existe con la cesta vacía y la banda de fases solo vive en dos pasos—.
 */
const MEDIR = () => {
    const panel = document.querySelector('.sidecart__panel');
    if (! panel) return { error: 'sin panel' };

    const caja = (el) => {
        if (! el) return null;
        const r = el.getBoundingClientRect();
        return { x: +r.x.toFixed(1), y: +r.y.toFixed(1), w: +r.width.toFixed(1), h: +r.height.toFixed(1) };
    };

    const estilo = (el, props, pseudo = null) => {
        if (! el) return null;
        const cs = getComputedStyle(el, pseudo);
        const out = {};
        for (const p of props) out[p] = cs[p];
        return out;
    };

    const uno = (sel) => panel.querySelector(sel);

    const progress = uno('.bk-progress');
    const foot = uno('.bk-foot');
    const scroll = uno('.purchase__scroll');
    const cta = uno('.bk-cta');
    const cartbar = uno('.cartbar');
    const back = uno('.bk-back');

    /**
     * El área táctil EFECTIVA de «Volver»: su caja unida a la de su pseudo ampliador.
     * ⚠️ Medir solo el `<button>` da 20 px de alto y acusa a una pieza que el pseudo ya arregla —la
     * trampa de `#307`, que llamó defecto a un control verificado en `#264`.
     */
    const areaBack = back ? (() => {
        const r = back.getBoundingClientRect();
        const cs = getComputedStyle(back, '::before');
        if (cs.content === 'none') return { w: +r.width.toFixed(1), h: +r.height.toFixed(1), pseudo: false };
        const px = (v) => parseFloat(v) || 0;
        return {
            w: +(r.width - px(cs.left) - px(cs.right)).toFixed(1),
            h: +(r.height - px(cs.top) - px(cs.bottom)).toFixed(1),
            pseudo: true,
        };
    })() : null;

    return {
        panel: caja(panel),
        progress: progress ? {
            caja: caja(progress),
            estilo: estilo(progress, ['backgroundColor', 'paddingTop', 'paddingBottom', 'paddingLeft', 'rowGap', 'borderBottomWidth']),
            fases: [...progress.querySelectorAll('.bk-seg__item')].map((i) => ({
                rotulo: i.querySelector('.bk-seg__label')?.textContent.trim() ?? '',
                estado: [...i.classList].find((c) => c.startsWith('is-')) ?? '',
                barra: caja(i.querySelector('.bk-seg__bar')),
                // El relleno vive en el pseudo: sin el segundo argumento sale la PISTA.
                relleno: estilo(i.querySelector('.bk-seg__bar'), ['backgroundColor', 'transform'], '::after'),
            })),
            contador: progress.querySelector('.bk-step-count')?.textContent.trim() ?? null,
            contexto: progress.querySelector('.bk-context')?.textContent.trim() ?? null,
            // El cuadradito del contexto: el artboard lo dibuja en cian a propósito.
            contextoMarca: estilo(progress.querySelector('.bk-context .jj-block'), ['backgroundColor']),
        } : null,
        back: back ? { caja: caja(back), area: areaBack, estilo: estilo(back, ['color', 'fontSize']) } : null,
        scroll: scroll ? (() => {
            /**
             * ⚠️⚠️ **`scrollHeight` NO mide el contenido de una caja recortada, y aquí la caja lo
             * está** (`flex:1` + `overflow-y:auto`): cuando el contenido es MÁS CORTO que la caja
             * devuelve el `clientHeight`, o sea el recorte — y el hueco sale **0** en las seis
             * pantallas, que es una cifra creíble y falsa. La trampa está escrita en `doc/spa.md`
             * («cómo se miden los altos de un documento como éste») y esta sonda la pagó igual.
             * ▶ Se suman **hijos + huecos + relleno**, que es lo que de verdad pide el contenido.
             */
            const cs = getComputedStyle(scroll);
            const px = (v) => parseFloat(v) || 0;
            const hijos = [...scroll.children];
            const suma = hijos.reduce((t, h) => t + h.getBoundingClientRect().height, 0)
                + Math.max(0, hijos.length - 1) * px(cs.rowGap)
                + px(cs.paddingTop) + px(cs.paddingBottom);

            return {
                caja: caja(scroll),
                estilo: estilo(scroll, ['paddingTop', 'paddingLeft', 'overflowY', 'rowGap']),
                pide: +suma.toFixed(1),
                // El HUECO vertical que el artboard declara y deja escrito: caja − lo que pide.
                hueco: +(scroll.clientHeight - suma).toFixed(1),
            };
        })() : null,
        foot: foot ? {
            caja: caja(foot),
            estilo: estilo(foot, ['backgroundColor', 'paddingTop', 'paddingLeft', 'rowGap', 'borderTopWidth']),
            fila: estilo(foot.querySelector('.bk-foot__row'), ['flexDirection', 'alignItems', 'columnGap']),
            rotulo: (() => {
                const l = foot.querySelector('.bk-foot__l');
                return l ? { texto: l.textContent.trim(), ...estilo(l, ['fontSize', 'color']) } : null;
            })(),
            importe: (() => {
                const v = foot.querySelector('.bk-foot__v');
                return v ? { texto: v.textContent.trim(), ...estilo(v, ['fontSize', 'fontFamily']) } : null;
            })(),
            nota: foot.querySelector('.bk-foot__note')?.textContent.trim() ?? null,
        } : null,
        cta: cta ? {
            caja: caja(cta),
            texto: cta.textContent.trim(),
            deshabilitado: cta.disabled,
            vende: cta.classList.contains('bk-cta--sells'),
            estilo: estilo(cta, ['backgroundColor', 'color', 'fontSize', 'fontWeight', 'paddingTop', 'paddingLeft', 'borderRadius', 'flexGrow']),
            // ¿Ocupa la fila entera del pie, o comparte con el total? Es la divergencia E1.
            anchoPie: foot ? +(cta.getBoundingClientRect().width / foot.querySelector('.bk-foot__row').getBoundingClientRect().width * 100).toFixed(1) : null,
        } : null,
        cartbar: cartbar ? {
            caja: caja(cartbar),
            estilo: estilo(cartbar, ['backgroundColor', 'color', 'paddingTop', 'paddingLeft', 'borderRadius']),
            cuenta: estilo(cartbar.querySelector('.cartbar__count'), ['backgroundColor', 'color']),
        } : null,
        breakdown: (() => {
            const b = uno('.bk-paybreakdown');
            return b ? { caja: caja(b), filas: [...b.querySelectorAll('.bk-paybreakdown__row')].map((r) => r.textContent.trim()) } : null;
        })(),
    };
};

const esperarCajon = (page) => page.waitForSelector('.sidecart.is-open .purchase[data-engine="spa"]', { timeout: 20000 });

/**
 * Abre la primera categoría del catálogo si está cerrada (la puerta de `#553`).
 *
 * Se pregunta por `aria-expanded`, que es el estado que el propio componente publica, y no por si
 * el item se ve: eso último confunde «cerrado» con «aún no ha terminado de abrirse».
 */
async function abrirCategoria(page) {
    const cabecera = page.locator('.catalog-acc__head').first();
    if (! await cabecera.count()) return;
    if (await cabecera.getAttribute('aria-expanded') === 'false') {
        await cabecera.click();
        await page.locator('.catalog__item').first().waitFor({ state: 'visible' });
    }
}

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

async function recorrer(context, viewport, nombreViewport, informe) {
    const page = await context.newPage();
    await page.setViewportSize(viewport);
    page.setDefaultTimeout(12000);

    let paso = 'arranque';
    page.on('pageerror', (e) => console.error(`   JS en ${paso}: ${e.message.split('\n')[0]}`));

    /** Se espera por CONDICIÓN —velo fuera y panel quieto—, nunca por reloj (`#551`, `#552`). */
    const medir = async (pantalla) => {
        paso = pantalla;
        // ⚠️⚠️ **EL PUNTERO SE APARTA ANTES DE MEDIR.** No basta con «no forzar el hover»: tras un
        // clic el ratón se queda donde pulsó, y el CTA del paso siguiente ocupa el mismo sitio que
        // el del anterior — así que la pantalla se mide **con el botón en `:hover`**. Medido: en el
        // carrito «Ir a pagar» salía en `rgb(26,169,222)` (cian) donde en reposo es tinta. Es la
        // trampa de `#551` por otra puerta, y esta sonda la pagó teniéndola escrita en su cabecera.
        await page.mouse.move(2, 2);
        await page.waitForSelector('.jj-loading', { state: 'hidden' }).catch(() => {});
        await page.waitForFunction(() => ! document.querySelector('.sidecart__panel .jj-spinner')).catch(() => {});
        await page.waitForFunction(() => new Promise((listo) => {
            const panel = document.querySelector('.sidecart__panel');
            if (! panel) return listo(false);
            const antes = panel.getBoundingClientRect().width;
            requestAnimationFrame(() => requestAnimationFrame(() => {
                listo(Math.abs(panel.getBoundingClientRect().width - antes) < 0.5);
            }));
        })).catch(() => {});
        const datos = await page.evaluate(MEDIR);
        informe.push({ pantalla, viewport: nombreViewport, ...datos });
        // ⚠️ Captura de VENTANA y no de elemento: una de elemento cose los `fixed` y enseña piezas que
        // no están ahí (la trampa de `#303`, pagada dos veces en este repo).
        await page.screenshot({ path: `${SALIDA}/armazon-${ETIQUETA}/${pantalla}@${viewport.width}.png` });
        return datos;
    };

    await page.goto(`${BASE}/entradas`, { waitUntil: 'domcontentloaded' });
    await aceptarCookies(page);
    await esperarCajon(page);
    await page.waitForSelector('.catalog__item');
    await medir('01-catalogo-vacio');

    // ⚠️⚠️ **Desde `#553` las dos categorías salen CERRADAS**, y sus productos se PINTAN igual: los
    // esconde el CSS para poder animarlos. O sea que `waitForSelector('.catalog__item')` pasa en
    // verde y el clic de después agota el tiempo con el cajón perfectamente sano. Hay que abrir la
    // puerta primero. *Un instrumento que el cambio rompe y nadie vuelve a correr sigue diciendo lo
    // que medía antes.*
    await abrirCategoria(page);
    await page.locator('.catalog__item:visible').first().click();
    await page.waitForSelector('.daystrip__day, .purchase__empty');
    await medir('02-fecha');

    await page.locator('.daystrip__day').first().click();
    await page.waitForSelector('.purchase__chip');
    await medir('03-hora');

    await page.locator('.purchase__chip:not(.is-full)').first().click();
    await page.waitForSelector('.qtybox');
    await medir('04-hora-elegida');

    await page.locator('.bk-cta').click();
    await page.waitForSelector('.cart__item');
    await medir('06-carrito');

    // La barra-carrito solo existe en el CATÁLOGO con algo dentro, que es el estado 1 de los siete
    // del pie. `addToCart()` lleva a la CESTA, así que se vuelve al catálogo por donde vuelve el
    // cliente —«+ añadir otra»—, dentro del SPA: recargar `/entradas` reabre el cajón en el paso
    // donde estaba, no en el catálogo, y entonces la barra «no aparece» sin que falte nada.
    await page.locator('.purchase__add-more').click();
    await page.waitForSelector('.cartbar');
    await medir('05-catalogo-con-cesta');

    await page.locator('.cartbar').click();
    await page.waitForSelector('.cart__item');

    await page.locator('.bk-cta').click();
    await page.waitForSelector('.auth, .purchase__note');
    await medir('07-identificarse');

    // El paso 08 es donde vive la decisión del ANCLA del pie (¿el total, o lo que se cobra ahora?) y
    // la única banda de desglose del cajón. Sin sesión no se llega, así que se entra por la pantalla
    // de identificarse, que es por donde entra el cliente.
    paso = 'entrar';
    if (await page.locator('#login-email').count()) {
        await page.fill('#login-email', CLIENTE.email);
        await page.fill('#login-password', CLIENTE.password);
        await page.locator('.auth button[type="submit"]').first().click();
    }
    await page.waitForSelector('.bk-paybreakdown, .bk-cta--sells, .pay__summary', { timeout: 20000 }).catch(() => {});
    await medir('08-pagar');

    await page.close();
}

const navegador = await chromium.launch();
const informe = [];

for (const [nombre, viewport] of [['movil', MOVIL], ['escritorio', ESCRITORIO]]) {
    const context = await navegador.newContext({ viewport, reducedMotion: 'no-preference' });
    await mkdir(`${SALIDA}/armazon-${ETIQUETA}`, { recursive: true });
    try {
        await recorrer(context, viewport, nombre, informe);
    } catch (e) {
        informe.push({ pantalla: 'ERROR', viewport: nombre, error: e.message.split('\n')[0] });
        console.error(`✗ ${nombre}: ${e.message.split('\n')[0]}`);
    }
    await context.close();
}

await navegador.close();
await writeFile(`${SALIDA}/armazon-${ETIQUETA}.json`, JSON.stringify(informe, null, 2));

console.log(`\n${'pantalla'.padEnd(24)} ${'vp'.padEnd(11)} ${'fases'.padStart(5)} ${'contador'.padStart(12)} ${'cta h×%'.padStart(12)} ${'hueco'.padStart(6)}`);
for (const f of informe) {
    if (f.error) { console.log(`${f.pantalla.padEnd(24)} ${f.viewport.padEnd(11)} ${f.error}`); continue; }
    console.log(
        `${f.pantalla.padEnd(24)} ${f.viewport.padEnd(11)} ` +
        `${String(f.progress ? f.progress.fases.length : '—').padStart(5)} ` +
        `${String(f.progress?.contador ?? '—').padStart(12)} ` +
        `${String(f.cta ? `${f.cta.caja.h}×${f.cta.anchoPie}%` : (f.cartbar ? `barra ${f.cartbar.caja.h}` : '—')).padStart(12)} ` +
        `${String(f.scroll?.hueco ?? '—').padStart(6)}`,
    );
}
console.log(`\n→ ${SALIDA}/armazon-${ETIQUETA}.json`);
