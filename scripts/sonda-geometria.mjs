/**
 * SONDA DE GEOMETRÍA de la web pública — el OJO que la suite no tiene.
 *
 * ⚠️⚠️ **ESTA SONDA ESTÁ VERSIONADA A PROPÓSITO, y es la lección de `#475`.** La anterior vivía en
 * `/root/e2e/tap44.mjs`, fuera del repo, y **se perdió**: `#470`→`#474` movieron el táctil, el aire
 * y la columna **sin poder verlo renderizado**, y el extractor de iconos de `#257` desapareció por
 * lo mismo. Un instrumento que no viaja en el repo se vuelve a escribir, y al reescribirlo se
 * vuelven a pagar sus trampas.
 *
 * Mide lo que la Fase 1 del carril de diseño movió y ninguna guarda de PHP puede ver:
 *   · el objetivo TÁCTIL (`--tap-min`, hoy 48) y los SOLAPES que subirlo puede crear
 *   · el ancho de COLUMNA (`--col-max`, hoy 1120)
 *   · el AIRE entre secciones (`--sec-air` / `--sec-air-mobile`, hoy 144 / 96)
 *   · el DESBORDE horizontal, que es como se nota que una de las tres se pasó de largo
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────
 *   1. Dentro del contenedor hace falta el navegador y el puente de puerto:
 *        docker compose exec -T laravel.test bash -lc 'npx --yes playwright@1.49.0 install-deps chromium'
 *        docker compose exec -u sail -T laravel.test bash -lc 'npx --yes playwright@1.49.0 install chromium'
 *        docker compose exec -u sail -T laravel.test bash -lc 'npm install --no-save playwright-core@1.49.0'
 *        docker compose exec -d -T laravel.test bash -lc 'socat TCP-LISTEN:8081,fork,reuseaddr TCP:127.0.0.1:80'
 *   2. docker compose exec -u sail -T laravel.test node scripts/sonda-geometria.mjs
 *
 * ⚠️ **El puente 8081→80 no es un capricho**: `APP_URL` es `http://localhost:8081`, así que `asset()`
 * emite URLs ABSOLUTAS a ese puerto. Sin el puente, dentro del contenedor los assets y el `<use>`
 * externo del kit salen cross-origin **con el producto sano** (`hueco-ilustracion.md` §16).
 * ⚠️ `playwright-core` se instala con `--no-save`: es una herramienta de verificación y no tiene por
 * qué entrar en las dependencias que el despliegue reconstruye.
 *
 * ── ❗❗ LAS CUATRO TRAMPAS, TODAS PAGADAS YA (`VERIFICACION-E2E-CAJON.md` §5.duovicies) ────────
 *  1. **Recortar en coordenadas de viewport** contra un ancestro que NO contiene la caja del control
 *     devuelve áreas **negativas**, y un negativo pasa el filtro de «menor que 48» como si fuera un
 *     defecto. Salieron anchos de −652. ▶ Aquí solo se recorta contra ancestros con `overflow` que
 *     de verdad contienen la caja.
 *  2. **Un pseudo-elemento no está donde dicen su `top` y su `left`: está donde lo deja su
 *     `transform`.** Sin aplicarlo se perdía el `translate(-50%,-50%)` y salían altos de 58 donde
 *     son 44, más siete solapes inventados. ▶ Se lee la `matrix()` computada, que ya trae los
 *     porcentajes resueltos a px.
 *  3. **`elementFromPoint` es FILTRO, no medida.** Usarlo para medir dio 178 falsos en §5.novodecies.
 *  4. **Un barrido por fracciones del alto no compara con el de antes si la página cambió de alto.**
 *     ▶ Por eso aquí no hay barrido: se enumeran los controles del documento entero.
 *
 * ⚠️ **Y su ALCANCE tiene un agujero declarado** (`#407`): sólo llega a lo PÚBLICO, así que deja
 * fuera el paso de datos del cajón, el post-form y el justificante — tres formularios que se
 * rellenan en el móvil y que ya tuvieron sus campos a 42 sin que nadie lo viera.
 */
import { chromium } from 'playwright-core';

const BASE = 'http://localhost:8081';

/** Las vistas públicas renderizables sin sesión ni enlace firmado. */
const VISTAS = [
    '/', '/entradas', '/precios', '/cumpleanos', '/servicios', '/normas', '/contacto',
    '/cookies', '/privacidad', '/aviso-legal', '/condiciones', '/login', '/registro',
];

const MOVIL = { width: 390, height: 844, hasTouch: true, isMobile: true };
const ESCRITORIO = { width: 1280, height: 900 };

/**
 * Lo que la Fase 1 dejó escrito y esta pasada tiene que confirmar.
 *
 * ⚠️ **`TAP` es parametrizable para poder correr el CONTROL**, que aquí no es opcional: la primera
 * pasada dio **639 controles bajo 48** y esa cifra no significa nada suelta. Corriendo la misma
 * sonda a **44** tiene que salir lo que `#264` midió y dejó escrito (**1 control**, el enlace en
 * línea del texto de cookies). Si sale otra cosa, el defecto es del instrumento.
 *   TAP=44 node scripts/sonda-geometria.mjs
 */
const ESPERADO = {
    tap: Number(process.env.TAP ?? 48),
    columna: 1120,
    aire: 144,
    aireMovil: 96,
};

const SELECTOR_CONTROL = 'a[href], button, input:not([type=hidden]), select, textarea, [role="button"], [role="switch"], [tabindex="0"]';

/**
 * Mide en el NAVEGADOR. Todo lo que sigue corre dentro de la página, así que no puede usar nada
 * de este fichero: va entero como una función serializada.
 */
async function medir(page, esperado, selector) {
    return page.evaluate(({ esperado, selector }) => {
        // ── Trampa 2 · el rectángulo de un pseudo está donde lo deja su transform ──────────────
        const rectPseudo = (el, cual) => {
            const cs = getComputedStyle(el, cual);
            if (cs.content === 'none' || cs.display === 'none' || cs.position !== 'absolute') return null;

            const base = el.getBoundingClientRect();
            const num = (v) => (v.endsWith('px') ? parseFloat(v) : NaN);
            const w = num(cs.width), h = num(cs.height);
            if (!isFinite(w) || !isFinite(h) || w <= 0 || h <= 0) return null;

            // `top`/`left` resueltos son relativos a la caja de posicionamiento del elemento.
            let x = base.left + (isFinite(num(cs.left)) ? num(cs.left) : 0);
            let y = base.top + (isFinite(num(cs.top)) ? num(cs.top) : 0);

            // La matriz computada ya trae los % del translate resueltos a px.
            const m = cs.transform;
            if (m && m !== 'none') {
                const n = m.match(/matrix\(([^)]+)\)/);
                if (n) {
                    const [a, b, c, d, tx, ty] = n[1].split(',').map(Number);
                    x += tx;
                    y += ty;
                    return { left: x, top: y, width: w * a, height: h * d };
                }
            }
            return { left: x, top: y, width: w, height: h };
        };

        const union = (a, b) => (!a ? b : !b ? a : {
            left: Math.min(a.left, b.left),
            top: Math.min(a.top, b.top),
            right: Math.max(a.left + a.width, b.left + b.width),
            bottom: Math.max(a.top + a.height, b.top + b.height),
        });
        const norm = (r) => (r && 'right' in r
            ? { left: r.left, top: r.top, width: r.right - r.left, height: r.bottom - r.top }
            : r);

        const controles = [];
        for (const el of document.querySelectorAll(selector)) {
            // Trampa 3 · visibilidad como FILTRO, nunca como medida.
            if (!el.checkVisibility || !el.checkVisibility({ contentVisibilityAuto: true, opacityProperty: true, visibilityProperty: true })) continue;
            const caja = el.getBoundingClientRect();
            if (caja.width === 0 || caja.height === 0) continue;

            // ⚠️⚠️ **`checkVisibility()` NO ve que algo esté FUERA DE LA VENTANA, y eso llenó la
            // primera medición de falsos**: el cajón de compra vive en el DOM desplazado con
            // `transform`, así que sus ~40 controles («Más info», el aspa de cerrar, los campos)
            // salían como visibles y cortos en TODAS las vistas. No son alcanzables sin abrirlo.
            // ▶ Se filtra por el eje HORIZONTAL únicamente: el vertical no vale, porque lo que está
            // bajo el pliegue sí se alcanza con scroll y tiene que seguir midiéndose.
            if (caja.left >= window.innerWidth - 0.5 || caja.right <= 0.5) continue;

            let area = { left: caja.left, top: caja.top, width: caja.width, height: caja.height };
            for (const cual of ['::before', '::after']) {
                const p = rectPseudo(el, cual);
                if (p) area = norm(union(area, p));
            }

            // ── Trampa 1 · solo se recorta contra un ancestro que CONTIENE la caja ─────────────
            let padre = el.parentElement;
            while (padre) {
                const cs = getComputedStyle(padre);
                if (cs.overflow !== 'visible' && cs.overflow !== '') {
                    const pc = padre.getBoundingClientRect();
                    const contiene = pc.left <= caja.left + 0.5 && pc.top <= caja.top + 0.5
                        && pc.right >= caja.right - 0.5 && pc.bottom >= caja.bottom - 0.5;
                    if (contiene) {
                        const l = Math.max(area.left, pc.left), t = Math.max(area.top, pc.top);
                        const r = Math.min(area.left + area.width, pc.right);
                        const b = Math.min(area.top + area.height, pc.bottom);
                        if (r > l && b > t) area = { left: l, top: t, width: r - l, height: b - t };
                    }
                }
                padre = padre.parentElement;
            }

            const etiqueta = (el.getAttribute('aria-label') || el.textContent || '').trim().slice(0, 40)
                || el.className?.toString().slice(0, 40) || el.tagName;

            // ── WCAG 2.5.8 exime el enlace EN LÍNEA dentro de un bloque de texto ───────────────
            // Es la excepción que `#264` dejó escrita —«el que queda es el enlace en línea del texto
            // de cookies, que WCAG exime»—, y sin ella la sonda acusa a cada `<a>` de un párrafo.
            // El criterio es de HECHO, no de nombre: un `<a>` cuyo padre tiene texto propio además
            // del enlace está dentro de una frase.
            let enLinea = false;
            if (el.tagName === 'A' && el.parentElement) {
                const padreTexto = (el.parentElement.textContent || '').trim().length;
                const propio = (el.textContent || '').trim().length;
                enLinea = padreTexto > propio + 2 && getComputedStyle(el).display.startsWith('inline');
            }

            // ¿De qué pieza cuelga? Distinguir la PÁGINA del cajón es lo que convierte una lista de
            // 49 cortos en un diagnóstico: el cajón se viste en la Fase 4, la página en la 2.
            const cajon = el.closest('#sidecart, .sidecart, .sidebar, [data-sidecart], .purchase, .acct, .addons, .addons-mini');
            const host = cajon ? 'cajón' : (el.closest('.foot, footer') ? 'pie' : (el.closest('.nav, .menu, header') ? 'armazón' : 'página'));

            controles.push({
                etiqueta,
                tag: el.tagName.toLowerCase(),
                clase: (el.className?.toString() || '').slice(0, 60),
                enLinea,
                host,
                w: Math.round(area.width * 10) / 10,
                h: Math.round(area.height * 10) / 10,
                area,
            });
        }

        // Cortos: por debajo del mínimo táctil en cualquiera de sus dos lados, sin los exentos.
        const cortos = controles
            .filter((c) => !c.enLinea && (c.w < esperado.tap - 0.5 || c.h < esperado.tap - 0.5))
            .map(({ etiqueta, tag, clase, w, h, area, host }) => ({
                etiqueta, tag, clase, w, h, host,
                // ⚠️ La POSICIÓN va en el informe a propósito: sin ella no se puede distinguir un
                // control de la página de uno que vive dentro de un panel cerrado, y esta sonda
                // perdió dos pasadas confundiéndolos.
                x: Math.round(area.left), y: Math.round(area.top + window.scrollY),
            }));
        const exentos = controles.filter((c) => c.enLinea && (c.w < esperado.tap - 0.5 || c.h < esperado.tap - 0.5)).length;

        // Solapes entre áreas efectivas (solo entre controles distintos y visibles).
        const solapes = [];
        for (let i = 0; i < controles.length; i++) {
            for (let j = i + 1; j < controles.length; j++) {
                const a = controles[i].area, b = controles[j].area;
                const w = Math.min(a.left + a.width, b.left + b.width) - Math.max(a.left, b.left);
                const h = Math.min(a.top + a.height, b.top + b.height) - Math.max(a.top, b.top);
                if (w > 1 && h > 1) solapes.push({ a: controles[i].etiqueta, b: controles[j].etiqueta, px: Math.round(w * h) });
            }
        }

        // Tokens resueltos y geometría de página.
        const raiz = getComputedStyle(document.documentElement);
        const wrap = document.querySelector('.wrap');
        // ⚠️⚠️ **La PRIMERA `.section` no vale para medir el aire, y la primera versión de esta sonda
        // cayó ahí**: la que va detrás del hero suma `--hero-air` (64px) por una decisión medida de
        // `#303`, así que daba 136/72 donde el token dice 72 y parecía un defecto del producto.
        // Se mide la SEGUNDA en adelante, que es donde el aire es el del ritmo y nada más.
        const secciones = [...document.querySelectorAll('.section')].map((s) => {
            const cs = getComputedStyle(s);
            return { top: parseFloat(cs.paddingTop), bottom: parseFloat(cs.paddingBottom) };
        });

        return {
            total: controles.length,
            cortos,
            exentos,
            solapes,
            tokens: {
                tap: raiz.getPropertyValue('--tap-min').trim(),
                col: raiz.getPropertyValue('--col-max').trim(),
                aire: raiz.getPropertyValue('--sec-air').trim(),
                aireMovil: raiz.getPropertyValue('--sec-air-mobile').trim(),
            },
            wrap: wrap ? Math.round(wrap.getBoundingClientRect().width) : null,
            secciones,
            desborde: Math.max(0, Math.round(document.documentElement.scrollWidth - document.documentElement.clientWidth)),
        };
    }, { esperado, selector });
}

const navegador = await chromium.launch();
let fallos = 0;
const resumen = [];

for (const [nombre, vp] of [['MÓVIL 390', MOVIL], ['ESCRITORIO 1280', ESCRITORIO]]) {
    console.log(`\n${'═'.repeat(78)}\n  ${nombre}\n${'═'.repeat(78)}`);
    const ctx = await navegador.newContext({ viewport: { width: vp.width, height: vp.height }, hasTouch: !!vp.hasTouch, isMobile: !!vp.isMobile });

    for (const ruta of VISTAS) {
        const page = await ctx.newPage();
        let r;
        try {
            const resp = await page.goto(BASE + ruta, { waitUntil: 'networkidle', timeout: 30000 });
            if (!resp || resp.status() >= 400) {
                console.log(`  ⚠ ${ruta.padEnd(22)} HTTP ${resp ? resp.status() : '—'} · se salta`);
                await page.close();
                continue;
            }
            // Las fuentes cambian la caja de un control: medir antes es medir otra página
            // (la trampa de `#323` y la de `#400`).
            await page.evaluate(() => document.fonts.ready);
            r = await medir(page, ESPERADO, SELECTOR_CONTROL);
        } catch (e) {
            console.log(`  ✗ ${ruta.padEnd(22)} ${String(e).slice(0, 60)}`);
            fallos++;
            await page.close();
            continue;
        }

        const marca = (ok) => (ok ? '✓' : '✗');
        const desbOk = r.desborde === 0;
        // ⚠️⚠️ **El mínimo táctil solo se juzga con PUNTERO GRUESO, y no juzgarlo así fue el primer
        // error de esta sonda**: `[data-tap]` amplía el área **solo bajo `pointer: coarse`** (`#264`),
        // así que en escritorio los controles miden su caja de verdad y salían todos «cortos».
        // Contarlos ahí no es medir accesibilidad táctil: es medir un ratón.
        const juzgaTap = vp.hasTouch === true;
        const cortosOk = !juzgaTap || r.cortos.length === 0;
        if (!desbOk || !cortosOk) fallos++;

        console.log(
            `  ${marca(cortosOk && desbOk)} ${ruta.padEnd(22)} controles ${String(r.total).padStart(3)} · ` +
            (juzgaTap
                ? `bajo ${ESPERADO.tap}: ${String(r.cortos.length).padStart(2)} (exentos ${r.exentos}) · `
                : 'táctil n/a · ') +
            `solapes ${String(r.solapes.length).padStart(2)} · ` +
            `desborde ${r.desborde}px` + (r.wrap ? ` · .wrap ${r.wrap}px` : '')
        );

        if (juzgaTap) {
            const porHost = {};
            for (const c of r.cortos) porHost[c.host] = (porHost[c.host] || 0) + 1;
            if (r.cortos.length) console.log(`        reparto: ${Object.entries(porHost).map(([k, v]) => `${k} ${v}`).join(' · ')}`);
            for (const c of r.cortos.slice(0, 8)) {
                console.log(`        · ${String(c.w).padStart(6)}×${String(c.h).padEnd(5)} [${c.host}] @${c.x},${c.y}  <${c.tag}> ${JSON.stringify(c.etiqueta)}`);
            }
            if (r.cortos.length > 8) console.log(`        … y ${r.cortos.length - 8} más`);
        }

        resumen.push({ vp: nombre, ruta, juzgaTap, ...r });
        await page.close();
    }
    await ctx.close();
}

// ── Lo que la Fase 1 dijo que dejaba escrito ────────────────────────────────────────────────
console.log(`\n${'═'.repeat(78)}\n  LAS CIFRAS DE LA FASE 1, RESUELTAS EN EL NAVEGADOR\n${'═'.repeat(78)}`);
const t = resumen[0]?.tokens ?? {};
const esc = resumen.find((x) => x.vp.startsWith('ESCRITORIO') && x.ruta === '/');
const mov = resumen.find((x) => x.vp.startsWith('MÓVIL') && x.ruta === '/');
const linea = (rot, real, quiere) => {
    const ok = String(real) === String(quiere);
    if (!ok) fallos++;
    console.log(`  ${ok ? '✓' : '✗'} ${rot.padEnd(28)} ${String(real).padStart(10)}   (esperado ${quiere})`);
};
linea('--tap-min', t.tap, `${ESPERADO.tap}px`);
linea('--col-max', t.col, `${ESPERADO.columna}px`);
linea('--sec-air', t.aire, `${ESPERADO.aire}px`);
linea('--sec-air-mobile', t.aireMovil, `${ESPERADO.aireMovil}px`);
// La [1], no la [0]: ver la nota de `secciones` dentro de `medir()`.
if (esc?.secciones?.length > 1) linea('.section padding (1280)', `${esc.secciones[1].top}/${esc.secciones[1].bottom}`, `${ESPERADO.aire / 2}/${ESPERADO.aire / 2}`);
if (mov?.secciones?.length > 1) linea('.section padding (390)', `${mov.secciones[1].top}/${mov.secciones[1].bottom}`, `${ESPERADO.aireMovil / 2}/${ESPERADO.aireMovil / 2}`);
if (esc?.secciones?.length) console.log(`  ▶ la 1.ª .section suma --hero-air a propósito: ${esc.secciones[0].top}/${esc.secciones[0].bottom} (1280)`);

const totalCortos = resumen.filter((r) => r.juzgaTap).reduce((s, r) => s + r.cortos.length, 0);
const totalDesb = resumen.filter((r) => r.desborde > 0).length;
console.log(`\n  Controles bajo ${ESPERADO.tap}px en total: ${totalCortos}`);
console.log(`  Vistas con desborde horizontal: ${totalDesb}`);

await navegador.close();
console.log(fallos === 0 ? '\n✓ SIN HALLAZGOS\n' : `\n✗ ${fallos} comprobación(es) con hallazgo\n`);
process.exit(fallos === 0 ? 0 : 1);
