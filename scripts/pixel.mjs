/**
 * PÍXEL — el juez de «idéntico al diseño» (`specs/isla-y-landing-nueva.md` §4.8).
 *
 * Pinta DOS páginas en el mismo navegador, con el mismo tamaño de ventana, y cuenta los píxeles que no
 * coinciden. «A» es la referencia (el diseño, o la versión de antes) y «B» lo que se juzga. Un cambio que
 * promete ser idéntico tiene que dar CERO; lo que no, deja la imagen de la diferencia para mirarla.
 *
 * ⚠️ **No es la huella** (`huella-maquetacion.mjs`): aquélla compara geometría y estilo computado del MISMO
 * DOM, y por eso no sirve cuando A y B son marcados distintos (el prototipo React del diseño contra nuestra
 * vista Blade). Aquí solo cuenta lo que se ve.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/pixel.mjs --a <url> --b <url> [--viewport 390x844,1440x900] [--dpr 1]
 *                              [--umbral 0] [--completa] [--salida storage/app/pixel/<nombre>]
 *                              [--reloj 2026-09-23T16:05:00+02:00] [--lote lista.json] [--reintentos 2]
 *
 * Sale con código 1 si una ventana supera el umbral, si las dos capturas no miden lo mismo, o si una página
 * no carga. Deja `a.png`, `b.png` y `dif.png` por ventana: en `dif.png` lo distinto va en rojo sobre A en gris.
 *
 * **Por lotes**: `--lote lista.json`, con `[{ "nombre", "a", "b", "viewport": "700x430", "completa": true,
 * "clics": ["[aria-label=\"Menú\"]"] }]` (los `clics` son selectores de Playwright que se tocan antes de la foto).
 * Juzga todos los pares en un solo navegador, deja cada uno en `<salida>/<nombre>/` y acaba con el resumen.
 * Antes de creerse una diferencia, el mismo lote con B = A dice qué páginas no son deterministas.
 *
 * ── LAS TRAMPAS, TODAS PAGADAS EN ESTE REPO ─────────────────────────────────────────────────────
 *  1. **Las fuentes** (`#323`): capturar antes de `document.fonts.ready` captura la de respaldo.
 *  2. **El puntero virtual arranca en (0,0)** (`#478`) y deja la primera pieza en `:hover`: se aparta.
 *  3. **Una captura solo vale con la pieza ASENTADA**: se esperan dos fotogramas seguidos con la misma altura
 *     del documento, y el movimiento se congela (`reducedMotion`).
 *  4. **Los dos lados se pintan en el MISMO navegador**: dos procesos distintos pueden suavizar distinto, y
 *     entonces la diferencia es del instrumento, no del cambio.
 *  5. **Las páginas con RELOJ no pintan igual dos veces** (medido el 24-09 con el diseño de Play Jump Park:
 *     la compra y Cumpleaños dan 7, 206 y 816 píxeles distintos CONTRA SÍ MISMAS, porque dicen «hoy» y «tu
 *     hora queda guardada hasta las 18:42»). `--reloj 2026-09-23T16:05:00+02:00` fija `Date` en las dos
 *     capturas (`clock.setFixedTime`: los temporizadores siguen corriendo, solo la hora deja de moverse).
 *  6. **Un prototipo puede tener estados de un instante** (foco o resaltado que pone un temporizador al
 *     montar). `--reintentos N` repite un par que falla hasta N veces. ⚠️ Es riguroso SOLO para demostrar
 *     identidad: un 0 exige que coincidan TODOS los píxeles, también los del estado inestable, así que si B
 *     difiriera de verdad no igualaría a A en ningún intento. Cada línea dice en qué intento cuadró.
 *  7. **El reloj de la RED decide fracciones de píxel** (medido el 24-09): los prototipos miden al montar, y
 *     la isla ignora cambios de menos de medio píxel, así que la fuente que llega de Google a los 215 ms y la
 *     local que llega en 0,5 ms dejaban el borde de un botón 2 píxeles distinto. Lo externo (Google Fonts,
 *     unpkg, jsDelivr) se sirve desde una caché en MEMORIA: se baja una vez de la red, con sus bytes y sus
 *     cabeceras, y todas las capturas lo reciben igual y al instante. Una pasada de calentamiento la llena.
 */
import { chromium } from 'playwright-core';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';

function argumentos(argv) {
    const a = { viewport: '390x844,1440x900', dpr: '1', umbral: '0', completa: false, salida: 'storage/app/pixel/ultima' };
    for (let i = 0; i < argv.length; i++) {
        const k = argv[i];
        if (k === '--completa') { a.completa = true; continue; }
        if (!k.startsWith('--') || argv[i + 1] === undefined) throw new Error(`argumento suelto: ${k}`);
        a[k.slice(2)] = argv[++i];
    }
    if (!a.lote && (!a.a || !a.b)) throw new Error('faltan --a y --b (las dos URLs), o --lote');
    return a;
}

/**
 * Espera a que la página deje de moverse. No basta con la altura del documento (medido el 24-09): la isla
 * del diseño MIDE su contenido y ajusta su ancho por JavaScript, y dos capturas seguidas la cogían con un
 * píxel de diferencia en el borde del botón. Se exige que la GEOMETRÍA ENTERA (el rectángulo de cada
 * elemento) no cambie en cinco fotogramas seguidos, sin animaciones vivas; y antes, que las imágenes
 * diferidas estén cargadas y decodificadas y los vídeos quietos en su primer fotograma.
 * ⚠️ **Dentro de los `iframe` también**: las hojas de revisión del diseño montan sus fichas en marcos, y un
 * marco sin asentar es una captura a medias. Se recorren todos los documentos del mismo origen.
 */
async function asentar(page) {
    await page.mouse.move(-1, -1).catch(() => {});
    await page.evaluate(async () => {
        const docs = [document];
        for (let i = 0; i < docs.length; i++) {
            for (const f of docs[i].querySelectorAll('iframe')) { try { if (f.contentDocument) docs.push(f.contentDocument); } catch { /* otro origen */ } }
        }
        await Promise.all(docs.map((d) => d.fonts.ready));
        for (const d of docs) for (const img of d.images) img.loading = 'eager';
        await Promise.all(docs.flatMap((d) => [...d.images]).map((img) => (img.complete ? img.decode().catch(() => {}) : new Promise((ok) => { img.onload = img.onerror = ok; }))));
        await Promise.all(docs.flatMap((d) => [...d.querySelectorAll('video')]).map((v) => new Promise((ok) => {
            v.pause();
            if (v.readyState === 0 || v.currentTime === 0) return ok();
            v.addEventListener('seeked', ok, { once: true });
            v.currentTime = 0;
        })));
    });
    await page.evaluate(() => new Promise((listo) => {
        const documentos = () => {
            const docs = [document];
            for (let i = 0; i < docs.length; i++) {
                for (const f of docs[i].querySelectorAll('iframe')) { try { if (f.contentDocument) docs.push(f.contentDocument); } catch { /* otro origen */ } }
            }
            return docs;
        };
        const firma = (docs) => {
            let s = 0;
            for (const d of docs) {
                for (const el of d.querySelectorAll('*')) {
                    const r = el.getBoundingClientRect();
                    s = (s * 31 + Math.round(r.x * 4) + 7 * Math.round(r.y * 4) + 13 * Math.round(r.width * 4) + 17 * Math.round(r.height * 4)) % 2147483647;
                }
            }
            return s;
        };
        // ⚠️ Quieta un SEGUNDO seguido, no unos fotogramas (medido el 24-09): la isla del diseño se vuelve a
        // medir con un `setTimeout` de 420 ms tras cada cambio de estado, y cinco fotogramas (≈80 ms) la
        // capturaban unas veces antes de esa medida y otras después: 124–34.170 píxeles contra sí misma.
        const QUIETUD_MS = 1000;
        let antes = null, desde = performance.now();
        const paso = () => {
            const docs = documentos();
            const ahora = firma(docs);
            const quietas = docs.every((d) => d.getAnimations().every((a) => a.playState !== 'running'));
            if (ahora !== antes || !quietas) desde = performance.now();
            antes = ahora;
            if (performance.now() - desde >= QUIETUD_MS) listo(); else requestAnimationFrame(paso);
        };
        requestAnimationFrame(paso);
    }));
}

/**
 * ⚠️ **La página completa se captura con la ventana YA de su tamaño, nunca con `fullPage`** (medido el 24-09).
 * `fullPage` agranda la ventana EN el momento de la foto; la isla del diseño ve entonces «un botón de la
 * página a la vista», cede el suyo y se transforma a mitad de la captura: 4–7 % de píxeles distintos entre
 * dos capturas de la misma página. Aquí se agranda primero (alto del documento, y ancho si el contenido
 * desborda, como en las hojas de revisión), se vuelve a asentar y se captura sin tocar nada.
 */
/** Lo externo de las páginas, una vez por proceso (trampa 7). */
const EXTERNOS = /^https:\/\/(fonts\.googleapis\.com|fonts\.gstatic\.com|unpkg\.com|cdn\.jsdelivr\.net)\//;
const memoria = new Map();
async function servirExternos(context) {
    await context.route(EXTERNOS, async (route) => {
        const url = route.request().url();
        if (!memoria.has(url)) {
            const r = await route.fetch();
            // El cuerpo ya viene descomprimido: sus cabeceras de compresión y longitud mentirían.
            const headers = Object.fromEntries(Object.entries(r.headers()).filter(([k]) => !['content-encoding', 'content-length'].includes(k)));
            memoria.set(url, { status: r.status(), headers, body: await r.body() });
        }
        const c = memoria.get(url);
        await route.fulfill({ status: c.status, headers: c.headers, body: c.body });
    });
}

async function capturar(navegador, ajustes, url, completa, clics = []) {
    // ⚠️ Un contexto NUEVO por captura (medido el 24-09): con A y B en el mismo contexto, la segunda visita
    // heredaba el `localStorage` y la caché de la primera —el diseño guarda allí el aviso de cookies y el
    // cálculo— y pintaba 124 píxeles distintos, siempre los mismos. Cada captura es una primera visita.
    const context = await navegador.newContext(ajustes);
    if (arg.reloj) await context.clock.setFixedTime(new Date(arg.reloj));
    await servirExternos(context);
    const page = await context.newPage();
    const respuesta = await page.goto(url, { waitUntil: 'load', timeout: 60000 });
    if (!respuesta || !respuesta.ok()) throw new Error(`no carga (${respuesta ? respuesta.status() : 'sin respuesta'}): ${url}`);
    await asentar(page);
    // Lo que se toca antes de la foto (abrir el menú, un panel): cada toque, y la página se vuelve a asentar.
    for (const selector of clics) {
        await page.click(selector);
        await page.mouse.move(-1, -1);
        await asentar(page);
    }
    if (completa) {
        const ventana = page.viewportSize();
        const [ancho, alto] = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.scrollHeight]);
        await page.setViewportSize({ width: Math.max(ventana.width, ancho), height: Math.min(Math.max(ventana.height, alto), 16000) });
        await asentar(page);
    }
    const png = await page.screenshot({ animations: 'disabled', caret: 'hide' });
    await context.close();
    return png;
}

/** Compara dentro del navegador: sin dependencias, con el mismo decodificador para los dos lados. */
async function comparar(context, pngA, pngB) {
    const page = await context.newPage();
    const r = await page.evaluate(async ([a, b]) => {
        const cargar = (src) => new Promise((ok, mal) => { const i = new Image(); i.onload = () => ok(i); i.onerror = mal; i.src = src; });
        const [ia, ib] = await Promise.all([cargar(a), cargar(b)]);
        const res = { a: [ia.width, ia.height], b: [ib.width, ib.height] };
        if (ia.width !== ib.width || ia.height !== ib.height) return { ...res, mide: false };
        const w = ia.width, h = ia.height;
        const lienzo = (img) => { const c = document.createElement('canvas'); c.width = w; c.height = h; const x = c.getContext('2d'); x.drawImage(img, 0, 0); return x.getImageData(0, 0, w, h); };
        const da = lienzo(ia).data, db = lienzo(ib).data;
        const c = document.createElement('canvas'); c.width = w; c.height = h;
        const cx = c.getContext('2d'); const out = cx.createImageData(w, h);
        let distintos = 0;
        for (let p = 0; p < da.length; p += 4) {
            const igual = da[p] === db[p] && da[p + 1] === db[p + 1] && da[p + 2] === db[p + 2] && da[p + 3] === db[p + 3];
            if (igual) {
                const g = Math.round(0.3 * da[p] + 0.59 * da[p + 1] + 0.11 * da[p + 2]) * 0.35 + 165;
                out.data[p] = out.data[p + 1] = out.data[p + 2] = g; out.data[p + 3] = 255;
            } else {
                distintos++;
                out.data[p] = 230; out.data[p + 1] = 0; out.data[p + 2] = 0; out.data[p + 3] = 255;
            }
        }
        cx.putImageData(out, 0, 0);
        return { ...res, mide: true, distintos, total: w * h, dif: c.toDataURL('image/png') };
    }, [`data:image/png;base64,${pngA.toString('base64')}`, `data:image/png;base64,${pngB.toString('base64')}`]);
    await page.close();
    return r;
}

const arg = argumentos(process.argv.slice(2));
const umbral = Number(arg.umbral);
const aVentana = (v) => { const [width, height] = v.split('x').map(Number); return { width, height }; };
const sha = (buf) => createHash('sha256').update(buf).digest('hex').slice(0, 16);

/** Un par A/B en una ventana: captura las dos, compara, guarda las imágenes y dice si pasa. */
async function juzgar(navegador, { nombre, a, b, viewport, completa, clics = [] }) {
    const ajustes = { viewport, deviceScaleFactor: Number(arg.dpr), reducedMotion: 'reduce' };
    const context = await navegador.newContext();
    const etiqueta = `${nombre ? `${nombre} ` : ''}${viewport.width}x${viewport.height} @${arg.dpr}x`;
    try {
        let pngA, pngB, r, intento = 0;
        const maximo = 1 + Number(arg.reintentos ?? 0);
        do {
            intento++;
            [pngA, pngB] = [await capturar(navegador, ajustes, a, completa, clics), await capturar(navegador, ajustes, b, completa, clics)];
            r = await comparar(context, pngA, pngB);
        } while (intento < maximo && !(r.mide && r.distintos <= umbral));
        const cuando = maximo > 1 ? ` · intento ${intento} de ${maximo}` : '';

        const dir = `${arg.salida}/${nombre ? `${nombre}/` : ''}${viewport.width}x${viewport.height}`;
        await mkdir(dir, { recursive: true });
        await writeFile(`${dir}/a.png`, pngA);
        await writeFile(`${dir}/b.png`, pngB);
        if (r.dif) await writeFile(`${dir}/dif.png`, Buffer.from(r.dif.split(',')[1], 'base64'));

        if (!r.mide) {
            console.log(`✗ ${etiqueta}: no miden lo mismo — A ${r.a.join('×')}, B ${r.b.join('×')}${cuando}`);
            return false;
        }
        const ok = r.distintos <= umbral;
        console.log(`${ok ? '✓' : '✗'} ${etiqueta}: ${r.distintos} de ${r.total} píxeles distintos (${(100 * r.distintos / r.total).toFixed(4)} %) · A ${r.a.join('×')} ${sha(pngA)} · B ${sha(pngB)}${cuando}`);
        return ok;
    } catch (e) {
        console.log(`✗ ${etiqueta}: ${e.message.split('\n')[0]}`);
        return false;
    } finally {
        await context.close();
    }
}

const pares = arg.lote
    ? JSON.parse(await readFile(arg.lote, 'utf8')).map((p) => ({ ...p, viewport: aVentana(p.viewport ?? '1280x900'), completa: p.completa ?? true }))
    : arg.viewport.split(',').map((v) => ({ a: arg.a, b: arg.b, viewport: aVentana(v), completa: arg.completa }));

const navegador = await chromium.launch();
const fallidos = [];
try {
    // Calentamiento: la primera visita llena la caché de lo externo y no cuenta (trampa 7).
    for (const url of new Set(pares.flatMap((p) => [p.a, p.b]).slice(0, 2))) {
        await capturar(navegador, { viewport: pares[0].viewport, reducedMotion: 'reduce' }, url, false).catch(() => {});
    }
    for (const par of pares) {
        if (!(await juzgar(navegador, par))) fallidos.push(par.nombre ?? `${par.viewport.width}x${par.viewport.height}`);
    }
} finally {
    await navegador.close();
}
if (arg.lote) console.log(`\n${fallidos.length ? '✗' : '✓'} ${pares.length - fallidos.length} de ${pares.length} pares idénticos${fallidos.length ? ` · fallan: ${fallidos.join(', ')}` : ''}`);
process.exit(fallidos.length ? 1 : 0);
