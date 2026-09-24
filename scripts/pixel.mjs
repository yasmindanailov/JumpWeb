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
 *
 * Sale con código 1 si una ventana supera el umbral, si las dos capturas no miden lo mismo, o si una página
 * no carga. Deja `a.png`, `b.png` y `dif.png` por ventana: en `dif.png` lo distinto va en rojo sobre A en gris.
 *
 * ── LAS TRAMPAS, TODAS PAGADAS EN ESTE REPO ─────────────────────────────────────────────────────
 *  1. **Las fuentes** (`#323`): capturar antes de `document.fonts.ready` captura la de respaldo.
 *  2. **El puntero virtual arranca en (0,0)** (`#478`) y deja la primera pieza en `:hover`: se aparta.
 *  3. **Una captura solo vale con la pieza ASENTADA**: se esperan dos fotogramas seguidos con la misma altura
 *     del documento, y el movimiento se congela (`reducedMotion`).
 *  4. **Los dos lados se pintan en el MISMO navegador**: dos procesos distintos pueden suavizar distinto, y
 *     entonces la diferencia es del instrumento, no del cambio.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';

function argumentos(argv) {
    const a = { viewport: '390x844,1440x900', dpr: '1', umbral: '0', completa: false, salida: 'storage/app/pixel/ultima' };
    for (let i = 0; i < argv.length; i++) {
        const k = argv[i];
        if (k === '--completa') { a.completa = true; continue; }
        if (!k.startsWith('--') || argv[i + 1] === undefined) throw new Error(`argumento suelto: ${k}`);
        a[k.slice(2)] = argv[++i];
    }
    if (!a.a || !a.b) throw new Error('faltan --a y --b (las dos URLs)');
    return a;
}

async function asentar(page) {
    await page.mouse.move(-1, -1).catch(() => {});
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => new Promise((listo) => {
        let antes = -1, iguales = 0;
        const paso = () => {
            const alto = document.documentElement.scrollHeight;
            iguales = alto === antes ? iguales + 1 : 0;
            antes = alto;
            if (iguales >= 2) listo(); else requestAnimationFrame(paso);
        };
        requestAnimationFrame(paso);
    }));
}

async function capturar(context, url, completa) {
    const page = await context.newPage();
    const respuesta = await page.goto(url, { waitUntil: 'networkidle' });
    if (!respuesta || !respuesta.ok()) throw new Error(`no carga (${respuesta ? respuesta.status() : 'sin respuesta'}): ${url}`);
    await asentar(page);
    const png = await page.screenshot({ fullPage: completa, animations: 'disabled', caret: 'hide' });
    await page.close();
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
const ventanas = arg.viewport.split(',').map((v) => { const [width, height] = v.split('x').map(Number); return { width, height }; });
const sha = (buf) => createHash('sha256').update(buf).digest('hex').slice(0, 16);

const navegador = await chromium.launch();
let fallo = false;
try {
    for (const viewport of ventanas) {
        const context = await navegador.newContext({ viewport, deviceScaleFactor: Number(arg.dpr), reducedMotion: 'reduce' });
        const [pngA, pngB] = [await capturar(context, arg.a, arg.completa), await capturar(context, arg.b, arg.completa)];
        const r = await comparar(context, pngA, pngB);
        await context.close();

        const dir = `${arg.salida}/${viewport.width}x${viewport.height}`;
        await mkdir(dir, { recursive: true });
        await writeFile(`${dir}/a.png`, pngA);
        await writeFile(`${dir}/b.png`, pngB);
        if (r.dif) await writeFile(`${dir}/dif.png`, Buffer.from(r.dif.split(',')[1], 'base64'));

        if (!r.mide) {
            fallo = true;
            console.log(`✗ ${viewport.width}x${viewport.height}: no miden lo mismo — A ${r.a.join('×')}, B ${r.b.join('×')}`);
            continue;
        }
        const pct = (100 * r.distintos / r.total).toFixed(4);
        const ok = r.distintos <= umbral;
        if (!ok) fallo = true;
        console.log(`${ok ? '✓' : '✗'} ${viewport.width}x${viewport.height} @${arg.dpr}x: ${r.distintos} de ${r.total} píxeles distintos (${pct} %) · A ${r.a.join('×')} ${sha(pngA)} · B ${sha(pngB)} → ${dir}`);
    }
} finally {
    await navegador.close();
}
process.exit(fallo ? 1 : 0);
