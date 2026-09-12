/**
 * MEDIDOR DE SECCIÓN · el alto de una sección de la portada y lo que pesa en pantallas.
 *
 * ⚠️ Existe porque el carril de diseño escribe una cifra de alto en cada tanda («la sección pesa
 * 824 px en móvil y la portada baja a 12,01 pantallas») y hasta ahora esa cifra se sacaba con un
 * `page.evaluate` escrito a mano cada vez. `doc/reglas.md` es explícito: **«una cifra en una nota
 * se mide en el DOM o no se escribe»**, y el error recurrente del proyecto es sumar altos y
 * olvidar los márgenes. Aquí el alto es `offsetHeight` + los márgenes verticales COLAPSADOS que
 * el navegador ya ha resuelto, o sea la distancia real entre la sección anterior y la siguiente.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────
 *   docker compose exec -d -T laravel.test bash -lc 'socat TCP-LISTEN:8081,fork,reuseaddr TCP:127.0.0.1:80'
 *   docker compose exec -u sail -T laravel.test bash -lc \
 *     'PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers node scripts/medir-seccion.mjs "#faq"'
 *
 * ── LAS TRAMPAS QUE HEREDA ───────────────────────────────────────────────────────────────────
 *  1. **El banner de cookies es `fixed`** y no cambia el alto del documento, pero sí tapa. Se
 *     oculta, y se dice: es una intervención del instrumento sobre el sujeto.
 *  2. **Las fuentes**: una medida antes de `document.fonts.ready` mide la fuente de respaldo y
 *     devuelve otro alto (la trampa de `#323`).
 *  3. **El puntero virtual arranca en (0,0)** y deja la primera pieza en HOVER (`#478`).
 *  4. **El desborde horizontal se mide sobre `documentElement`**, no sobre la sección: una barra
 *     dentro de un carril propio no es desborde de la página (`#346`).
 */
import { chromium } from 'playwright-core';

/*
 * ⚠️ **`--url=` lo añade `#531`, y no es comodidad**: la Fase 3 mide PÁGINAS (`/precios`, `/normas`,
 * `/servicios`…) y este medidor iba clavado a la portada, así que cada tanda de página se escribía
 * su propio `page.evaluate` a mano — que es justo lo que este fichero existe para evitar. La ruta
 * por defecto sigue siendo `/`, así que las llamadas de la Fase 2 no cambian.
 *
 * ⚠️⚠️ **Y `#531` lo añadió A MEDIAS: parseaba `--url=` y dejaba el `goto` clavado en `/`.** La
 * variable existía, no la leía nadie y **el navegador seguía abriendo la portada**, así que entre
 * `#531` y `#535` cualquier medida pedida sobre una página INTERIOR devolvió la de la portada. No
 * fallaba: los selectores de la página salían «NO EXISTE» y el alto del documento era plausible
 * —11 pantallas es un número creíble para una página larga—, que es exactamente cómo una cifra
 * falsa pasa por buena. *Un argumento parseado no es un argumento aplicado.*
 * ▶ Por eso ahora la sonda IMPRIME la URL que ha cargado: un instrumento que no dice sobre qué ha
 * medido no permite descubrir que midió otra cosa.
 */
const args = process.argv.slice(2);
const ruta = (args.find((a) => a.startsWith('--url=')) ?? '--url=/').slice('--url='.length);
const selectores = args.filter((a) => !a.startsWith('--'));

if (selectores.length === 0) {
    console.error('Uso: node scripts/medir-seccion.mjs "<selector>" [más selectores…] [--url=/precios]');
    process.exit(1);
}

const nav = await chromium.launch({ args: ['--no-sandbox'] });

for (const [w, h, etiqueta] of [[390, 844, 'MÓVIL 390×844'], [1280, 900, 'ESCRITORIO 1280×900']]) {
    const ctx = await nav.newContext({ viewport: { width: w, height: h } });
    const page = await ctx.newPage();
    await page.goto(`http://localhost:8081${ruta}`, { waitUntil: 'networkidle' });
    await page.addStyleTag({ content: '.cookie-banner,[class*="cookie"]{display:none!important}' });
    await page.evaluate(() => document.fonts.ready);
    await page.mouse.move(-50, -50);

    const datos = await page.evaluate(([sels, vh]) => {
        const out = { secciones: [], doc: 0, desborde: 0 };
        const de = document.documentElement;
        out.doc = Math.max(de.scrollHeight, document.body.scrollHeight);
        out.desborde = Math.max(0, de.scrollWidth - de.clientWidth);
        for (const s of sels) {
            const el = document.querySelector(s);
            if (!el) { out.secciones.push({ sel: s, alto: null }); continue; }
            const cs = getComputedStyle(el);
            const alto = el.offsetHeight + parseFloat(cs.marginTop) + parseFloat(cs.marginBottom);
            out.secciones.push({ sel: s, alto: Math.round(alto), caja: Math.round(el.offsetHeight) });
        }
        out.pantallas = Math.round((out.doc / vh) * 100) / 100;
        return out;
    }, [selectores, h]);

    console.log(`\n══ ${etiqueta} · ${page.url()} ══`);
    for (const s of datos.secciones) {
        console.log(s.alto === null
            ? `  ✗ NO EXISTE  ${s.sel}`
            : `  ${s.sel.padEnd(18)} ${String(s.alto).padStart(5)} px  (caja ${s.caja})`);
    }
    console.log(`  documento ${datos.doc} px · ${datos.pantallas} pantallas · desborde horizontal ${datos.desborde}`);
    await ctx.close();
}
await nav.close();
