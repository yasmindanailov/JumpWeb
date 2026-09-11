/**
 * COMPARADOR DE SECCIÓN · los valores del artboard contra lo que el navegador calcula.
 *
 * ⚠️⚠️ **ESTE INSTRUMENTO VIAJA EN EL REPO A PROPÓSITO**, como `sonda-geometria.mjs` y
 * `comparar-con-mockup.mjs`: es lo que convierte «se parece» en una lista de números. Sin él,
 * «idéntico al mockup» se comprueba mirando — y mirar costó cuatro rondas en la sección 01
 * (`#478`) y una corrección del owner en la 05 (`#486`).
 *
 * ▶ **La tabla es POR SECCIÓN y se acumulan aquí**, para que una sección cerrada se pueda volver a
 * comprobar el día que alguien toque un token compartido. Cada fila es
 * `[selector, propiedad, valor del artboard, nota]`, y **la nota no es adorno: una fila con nota es
 * una divergencia DECLARADA y una sin nota es un defecto**. Sin esa distinción el informe mezcla lo
 * que alguien decidió con lo que a alguien se le olvidó.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────
 *   docker compose exec -d -T laravel.test bash -lc 'socat TCP-LISTEN:8081,fork,reuseaddr TCP:127.0.0.1:80'
 *   docker compose exec -u sail -T laravel.test bash -lc \
 *     'PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers node scripts/comparar-seccion.mjs 05'
 *
 * ── ❗❗ LAS DOS TRAMPAS QUE YA PAGÓ ──────────────────────────────────────────────────────────
 *  1. **El banner de cookies es `fixed` y se cuela por encima de la sección.** Aceptarlo no vale
 *     —cargaría el iframe del mapa y cambiaría la página—, así que se oculta, y **se dice**: es una
 *     intervención del instrumento sobre el sujeto.
 *  2. **Un `clamp()` no devuelve el número redondo.** La escala tipográfica da `18.0001px` donde el
 *     artboard escribe 18, y comparar cadenas **acusaba al producto sano**. Las longitudes se
 *     comparan con tolerancia de medio píxel; el resto, exacto.
 *
 *  3. **Un borde SUBPÍXEL no se puede verificar por aquí.** Chrome **trunca `border-width` a un
 *     entero de píxeles CSS** en el valor calculado: `1.5px` sale `1px` y `0.5px` también. Medido
 *     con control en un documento de prueba, y **da igual el `deviceScaleFactor`** —se probó a 1 y
 *     a 2 y devuelve lo mismo—. El informe acusaba al producto sano, que sí declara 1,5.
 *     ▶ Por eso esas filas esperan el valor TRUNCADO: lo que verifican es que el filete exista.
 *
 * ⚠️ **Y lo que este comparador NO demuestra**: solo mira los valores que su tabla enumera. Un
 * «0 sin explicar» dice que lo comprobado coincide, no que la sección sea idéntica en todo.
 */
import { chromium } from 'playwright-core';

// [selector, propiedad, esperado, nota]. `nota` no vacía = divergencia DECLARADA, no defecto.
const SECCIONES = {};

SECCIONES["05"] = { nombre: "05 · Antes de venir", raiz: "#before", movil: [
    ['.sec-head__eyebrow', 'fontSize', '12px', ''],
    ['.sec-head__eyebrow', 'letterSpacing', '1.92px', ''],
    ['.sec-head__eyebrow', 'textTransform', 'uppercase', ''],
    ['.sec-head__title', 'fontSize', '34px', ''],
    ['.sec-head__lede', 'fontSize', '18px', ''],
    ['.before__screen', 'backgroundColor', 'rgb(255, 255, 255)', ''],
    ['.before__screen', 'borderTopLeftRadius', '10px', ''],
    ['.before__screen', 'paddingTop', '20px', ''],
    ['.sqr', 'width', '112px', ''],
    ['.sqr__mark', 'width', '38.08px', 'el artboard escribe 38 sobre 112; aquí es el 34 %, que escala'],
    ['.before__name', 'fontSize', '13px', ''],
    ['.before__name', 'fontWeight', '800', ''],
    ['.before__socks', 'marginTop', '22px', ''],
    ['.before__socks', 'paddingTop', '18px', ''],
    ['.before__i', 'width', '24px', ''],
    ['.before__socks', 'fontSize', '16px', ''],
    ['.before__rules', 'minHeight', '48px', ''],
    ['.before__rules', 'fontSize', '16px', ''],
    ['.before__rules', 'fontWeight', '800', ''],
    ['.before__rules', 'borderTopWidth', '2px', ''],
    ['.before__cta', 'minHeight', '48px', ''],
    ['.before__cta', 'borderTopLeftRadius', '10px', ''],
    ['.before__cta', 'fontSize', '16px', 'PARKED por el owner: la familia `.btn` está a 14/600 y moverla llega al cajón (Fase 4)'],
    ['.before__cta', 'fontWeight', '800', 'ídem'],
    ['.before__cta', 'color', 'rgb(10, 92, 147)', 'ídem: el fantasma de la familia lee `--fg` (tinta) y el sistema pide Azul Muro'],
    ['.before__cta', 'borderTopWidth', '1.5px', 'ídem: 1 px de tinta al 22 % contra 1,5 del azul'],
], escritorio: [
    ['.sec-head__title', 'fontSize', '52px', ''],
    ['.sec-head__lede', 'fontSize', '21px', ''],
    ['.before__code', 'borderTopLeftRadius', '16px', ''],
    ['.before__code', 'paddingTop', '32px', ''],
    ['.before__device', 'width', '340px', ''],
    ['.before__device', 'borderTopWidth', '2px', ''],
    ['.before__device', 'borderTopLeftRadius', '16px', ''],
    ['.before__device', 'paddingTop', '12px', ''],
    ['.before__screen', 'paddingTop', '26px', ''],
    ['.sqr', 'width', '260px', ''],
    ['.sqr__mark', 'width', '88.4px', 'el artboard escribe 88 sobre 260; aquí es el 34 %'],
    ['.before__name', 'fontSize', '15px', ''],
    ['.before__where', 'fontSize', '17px', ''],
    ['.before__what-title', 'fontSize', '36px', ''],
    ['.before__what-lede', 'fontSize', '21px', ''],
    ['.before__row', 'paddingTop', '14px', ''],
    ['.before__row-key', 'fontSize', '12px', ''],
    ['.before__row-val', 'fontSize', '17px', ''],
    ['.before__row-val', 'fontWeight', '700', ''],
    ['.before__tick', 'width', '26px', ''],
    ['.before__all', 'fontSize', '17px', ''],
    ['.before__foot', 'marginTop', '32px', ''],
    ['.before__foot', 'paddingTop', '20px', ''],
] };

SECCIONES["07"] = { nombre: "07 · Visítanos", raiz: "#info", movil: [
    ['.sec-head__eyebrow', 'fontSize', '12px', ''],
    ['.sec-head__title', 'fontSize', '34px', ''],
    ['.sec-head__lede', 'fontSize', '18px', ''],
    ['.sec-head__lede', 'fontWeight', '500', ''],
    ['.visit__when', 'backgroundColor', 'rgb(255, 255, 255)', ''],
    ['.visit__when', 'borderTopLeftRadius', '16px', ''],
    ['.visit__when', 'borderTopWidth', '1px', ''],
    ['.visit__when', 'paddingTop', '20px', ''],
    ['.visit__today', 'fontSize', '12px', ''],
    ['.visit__today', 'textTransform', 'uppercase', ''],
    ['.visit__dot', 'width', '10px', ''],
    ['.visit__state', 'fontSize', '34px', ''],
    ['.visit__line', 'fontSize', '16px', ''],
    ['.visit__rows', 'paddingTop', '16px', ''],
    ['.visit__rows', 'borderTopWidth', '1px', ''],
    ['.visit__rows > div', 'paddingTop', '8px', ''],
    ['.visit__rows > div', 'borderTopLeftRadius', '10px', ''],
    ['.visit__rows dt', 'fontSize', '15.5px', 'el artboard escribe 15,5 y la escala del producto no tiene ese escalón: 15'],
    ['.visit__rows dt', 'fontWeight', '700', ''],
    ['.visit__rows dd', 'fontSize', '15px', ''],
    ['.visit__place', 'marginTop', '12px', ''],
    ['.visit__credit', 'fontSize', '15px', ''],
], escritorio: [
    ['.sec-head__title', 'fontSize', '52px', ''],
    ['.sec-head__lede', 'fontSize', '21px', ''],
    ['.visit', 'columnGap', '32px', ''],
    ['.visit__when', 'paddingTop', '24px', ''],
    ['.visit__state', 'fontSize', '36px', ''],
    ['.visit__line', 'fontSize', '17px', ''],
    ['.visit__rows > div', 'paddingTop', '10px', ''],
    ['.visit__rows dt', 'fontSize', '15.5px', 'ídem: el componente de horario no declara talla de escritorio'],
    ['.visit__rows dd', 'fontSize', '15px', ''],
    ['.visit__place', 'marginTop', '20px', ''],
    ['.visit__place', 'paddingTop', '20px', ''],
    // ⚠️ Declarado 1,5 en la hoja; Chrome lo trunca a 1 (ver la trampa 3). Se verifica que EXISTE.
    ['.visit__place', 'borderTopWidth', '1px', ''],
    ['.visit__addr-1', 'fontSize', '17px', ''],
    ['.visit__addr-1', 'fontWeight', '700', ''],
] };

// ⚠️ La 06 va SIN la chapa del 4,8: esa cifra solo existe si viene de Google, y su mitad es la
// T2i·b. Cuando entre, sus valores se añaden a esta misma tabla (nota Bungee 52 en Lima, estrellas
// de 24 recortadas caja a caja, recuento 15/700 sobre tinta).
SECCIONES["06"] = { nombre: "06 · Reseñas (opiniones propias)", raiz: "#reviews", movil: [
    ['.sec-head__eyebrow', 'fontSize', '12px', ''],
    ['.sec-head__eyebrow', 'letterSpacing', '1.92px', ''],
    ['.sec-head__title', 'fontSize', '34px', ''],
    ['.sec-head__lede', 'fontSize', '18px', ''],
    ['.rev__card', 'backgroundColor', 'rgb(255, 255, 255)', ''],
    ['.rev__card', 'borderTopLeftRadius', '16px', ''],
    ['.rev__card', 'borderTopWidth', '1px', ''],
    ['.rev__card', 'paddingTop', '18px', ''],
    ['.rev__who', 'gap', '12px', ''],
    ['.rev__ini', 'width', '56px', ''],
    ['.rev__ini', 'height', '56px', ''],
    ['.rev__ini', 'borderTopLeftRadius', '999px', ''],
    ['.rev__ini', 'backgroundColor', 'rgb(232, 233, 229)', ''],
    ['.rev__ini', 'fontSize', '22px', ''],
    ['.rev__author', 'fontSize', '16px', ''],
    ['.rev__author', 'fontWeight', '700', ''],
    ['.rev__when', 'fontSize', '11px', ''],
    ['.rev__when', 'marginTop', '4px', ''],
    ['.rev__stars', 'fontSize', '15px', ''],
    ['.rev__stars', 'letterSpacing', '0.9px', ''],
    ['.rev__stars-on', 'color', 'rgb(138, 110, 0)', ''],
    ['.rev__text', 'fontSize', '16px', ''],
    ['.rev__text', 'marginTop', '14px', ''],
    ['.rev__text', 'minHeight', '93px', ''],
    ['.rev__nav', 'marginTop', '4px', ''],
    ['.rev__nav', 'gap', '4px', ''],
    ['.rev__arrow', 'width', '48px', ''],
    ['.rev__arrow', 'height', '48px', ''],
    ['.rev__arrow', 'borderTopLeftRadius', '999px', ''],
    // ⚠️ Declarado 1,5 en la hoja; Chrome lo trunca a 1 (trampa 3). Se verifica que EXISTE.
    ['.rev__arrow', 'borderTopWidth', '1px', ''],
    ['.rev__arrow', 'fontSize', '19px', ''],
    ['.rev__dot', 'width', '44px', ''],
    ['.rev__dot', 'height', '48px', ''],
], escritorio: [
    ['.sec-head__title', 'fontSize', '52px', ''],
    ['.sec-head__lede', 'fontSize', '21px', ''],
    // Las dos cifras que `Escritorio PJP` 5b midió en el DOM: la chapa ocupa 352 y la opinión 736.
    // Sin chapa, esas cuatro columnas las lleva la cabecera — el mismo reparto que la 08.
    ['.sec-head', 'width', '352px', ''],
    ['.rev', 'width', '736px', ''],
    // Valores del COMPONENTE: no escalan con la superficie. Aquí para que nadie los «suba».
    ['.rev__text', 'fontSize', '16px', ''],
    ['.rev__author', 'fontSize', '16px', ''],
] };

SECCIONES["08"] = { nombre: "08 · Dudas", raiz: "#faq", movil: [
    ['.sec-head__eyebrow', 'fontSize', '12px', ''],
    ['.sec-head__eyebrow', 'letterSpacing', '1.92px', ''],
    ['.sec-head__eyebrow', 'color', 'rgb(98, 106, 114)', ''],
    ['.sec-head__title', 'fontSize', '34px', ''],
    ['.sec-head__title', 'lineHeight', '32.3px', ''],
    ['.sec-head__lede', 'fontSize', '18px', ''],
    ['.sec-head__lede', 'marginTop', '16px', ''],
    // ⚠️ El artboard separa la cabecera del acordeón con 20; el producto usa el ritmo de cabecera
    // COMPARTIDO por las ocho secciones (28/40, `#479`, del `ritmo.cabecera` del sistema). Bajarlo
    // aquí desalinea esta sección con las siete restantes; subir las siete es otra tanda.
    ['.sec-head', 'marginBottom', '20px', 'el ritmo de cabecera es de las OCHO secciones: 28 en móvil'],
    ['.faq', 'backgroundColor', 'rgb(255, 255, 255)', ''],
    ['.faq', 'borderTopLeftRadius', '16px', ''],
    ['.faq', 'borderTopWidth', '1px', ''],
    // ⚠️⚠️ El artboard escribe Línea `#D6D8D4`; el producto usa el ROL `--line`, que es tinta al
    // 10 % (§2: va el rol, nunca el hex). Es la MISMA cadena que visten las cuatro secciones ya
    // cerradas —`.visit__when` la primera—, así que cambiarla aquí las mueve a todas.
    ['.faq', 'borderTopColor', 'rgb(214, 216, 212)', 'Línea es el rol `--line` (tinta 10 %), compartido con las secciones ya cerradas'],
    ['.faq__q', 'minHeight', '64px', ''],
    ['.faq__q', 'paddingTop', '16px', ''],
    ['.faq__q', 'paddingLeft', '18px', ''],
    ['.faq__q', 'gap', '14px', ''],
    ['.faq__q', 'color', 'rgb(16, 20, 24)', ''],
    ['.faq__p', 'fontSize', '18px', ''],
    ['.faq__p', 'fontWeight', '700', ''],
    ['.faq__p', 'lineHeight', '23.4px', ''],
    ['.faq__sign', 'width', '32px', ''],
    ['.faq__sign', 'height', '32px', ''],
    ['.faq__sign', 'borderTopLeftRadius', '999px', ''],
    ['.faq__sign', 'backgroundColor', 'rgb(244, 244, 241)', ''],
    ['.faq__sign', 'color', 'rgb(10, 92, 147)', ''],
    ['.faq__item + .faq__item', 'borderTopWidth', '1px', ''],
    ['.faq__item + .faq__item', 'borderTopColor', 'rgb(232, 233, 229)', ''],
    ['.faq__a', 'fontSize', '16px', ''],
    ['.faq__a', 'lineHeight', '24px', ''],
    ['.faq__a', 'color', 'rgb(98, 106, 114)', ''],
    ['.faq__a-p', 'paddingLeft', '18px', ''],
    ['.faq__a-p', 'paddingBottom', '20px', ''],
    ['.faq__a-p', 'paddingTop', '0px', ''],
], escritorio: [
    ['.sec-head__title', 'fontSize', '52px', ''],
    ['.sec-head__lede', 'fontSize', '21px', ''],
    ['.sec-head__lede', 'marginTop', '20px', ''],
    // Las dos cifras que el artboard midió en el DOM: `span 4` = 352 y `span 8` = 736 sobre la
    // columna de 1120 con 32 de gap. Si alguien cambia la rejilla a `1fr 2fr` salen 362,67 y
    // 725,33 y estas dos filas lo dicen.
    ['.sec-head', 'width', '352px', ''],
    ['.faq', 'width', '736px', ''],
    ['.sec-head', 'marginBottom', '0px', ''],
    ['.faq__q', 'paddingTop', '18px', ''],
    ['.faq__q', 'paddingLeft', '24px', ''],
    ['.faq__q', 'gap', '16px', ''],
    // ⚠️ Estas dos NO llevan nota porque NO divergen: el artboard de escritorio escribe 18 y 16
    // igual que el de móvil. Están aquí justamente para vigilar que nadie las «suba» a la escala
    // (Entradilla 21 · Cuerpo 17) creyendo que se les olvidó escalar: son valores del COMPONENTE.
    ['.faq__p', 'fontSize', '18px', ''],
    ['.faq__a', 'fontSize', '16px', ''],
    ['.faq__a-p', 'paddingLeft', '24px', ''],
    ['.faq__a-p', 'paddingBottom', '22px', ''],
] };

/*
 * **`/precios`, la PÁGINA** (`#531`, Fase 3 · T3b). Artboard `Precios Pagina PJP` 1a/1b.
 * ⚠️ Es la primera entrada con `ruta`: lo que se compara no es una sección de la portada.
 * ⚠️ Las dos divergencias de TALLA están declaradas y son la misma decisión de siempre: el sistema
 * manda sobre el dibujo cuando el dibujo baja del suelo de lectura (cuerpo 16, etiqueta 12).
 */
SECCIONES["precios"] = { nombre: "/precios · todas las tarifas", ruta: "/precios", raiz: "main", movil: [
    ['.week__day', 'height', '56px', ''],
    ['.week__initial', 'fontSize', '20px', ''],
    ['.rate-zone__name', 'fontSize', '26px', ''],
    ['.rate-zone__dot', 'width', '12px', ''],
    ['.rate-table', 'borderTopLeftRadius', '16px', ''],
    ['.rate-table__col', 'fontSize', '10.5px', 'la etiqueta del sistema es 12 y su pliego dice «mono nunca bajo 10»: la escala no tiene 10,5'],
    ['.rate-table__name', 'fontSize', '15.5px', 'cuerpo 16 en móvil, que es su propia regla dura; el artboard dibuja 15,5'],
    ['.rate-table__cell--normal', 'fontSize', '20px', ''],
    ['.rate-table__cell--normal', 'color', 'rgb(98, 116, 17)', ''],
    ['.rate-table__cell--special', 'fontSize', '15px', ''],
    ['.rate-table__what', 'paddingTop', '14px', ''],
    ['.rate-table__what', 'paddingLeft', '16px', ''],
    ['.rate-note', 'paddingTop', '20px', ''],
    ['.rate-note__title', 'fontSize', '22px', ''],
    ['.holidays__row', 'paddingTop', '12px', ''],
    ['.extras__list', 'borderTopLeftRadius', '16px', ''],
], escritorio: [
    ['.week__day', 'height', '72px', ''],
    ['.week__initial', 'fontSize', '22px', ''],
    ['.rate-zone__name', 'fontSize', '36px', ''],
    ['.rate-zone__dot', 'width', '14px', ''],
    ['.rate-page__zones', 'columnGap', '32px', ''],
    ['.rate-table__what', 'paddingTop', '16px', ''],
    ['.rate-table__what', 'paddingLeft', '24px', ''],
    ['.rate-table__cell--normal', 'fontSize', '26px', ''],
    ['.rate-note', 'width', '544px', ''],
    ['.rate-note', 'paddingTop', '28px', ''],
    ['.rate-note__title', 'fontSize', '26px', ''],
    ['.holidays__row', 'paddingTop', '14px', ''],
] };

const clave = process.argv[2] || "05";
const S = SECCIONES[clave];
if (!S) {
    console.error(`No hay tabla para la sección «${clave}». Hay: ${Object.keys(SECCIONES).join(", ")}`);
    process.exit(1);
}

const nav = await chromium.launch({ args: ['--no-sandbox'] });

let sinExplicar = 0;

for (const [w, h, n, tabla] of [[390, 844, `MÓVIL 390 · ${S.nombre}`, S.movil], [1280, 900, `ESCRITORIO 1280 · ${S.nombre}`, S.escritorio]]) {
    const ctx = await nav.newContext({ viewport: { width: w, height: h } });
    const page = await ctx.newPage();
    // ⚠️ La RUTA la dice la sección desde `#531`: la Fase 3 compara PÁGINAS (`/precios`, `/normas`…)
    // y esto iba clavado a la portada. Sin `ruta`, sigue siendo `/` — las tablas de la Fase 2 no
    // cambian.
    await page.goto('http://localhost:8081' + (S.ruta ?? '/'), { waitUntil: 'networkidle' });
    await page.addStyleTag({ content: '.cookie-banner,[class*="cookie"]{display:none!important}' });
    await page.evaluate(() => document.fonts.ready);
    await page.mouse.move(-50, -50);

    console.log(`\n══ ${n} ══`);
    let iguales = 0, declaradas = 0, defectos = 0;

    for (const [sel, prop, esperado, nota] of tabla) {
        const real = await page.evaluate(([s, p]) => {
            const el = document.querySelector(s);
            return el ? getComputedStyle(el)[p] : null;
        }, [S.raiz + ' ' + sel, prop]);

        if (real === null) { console.log(`  ✗ NO EXISTE  ${sel}`); defectos++; continue; }
        const num = (v) => (typeof v === 'string' && /^-?[\d.]+px$/.test(v) ? parseFloat(v) : null);
        const a = num(real), b = num(esperado);
        const ok = (a !== null && b !== null) ? Math.abs(a - b) < 0.5 : real === esperado;
        if (ok) { iguales++; continue; }
        if (nota) { declaradas++; console.log(`  ▶ declarada  ${sel} { ${prop} } → ${real} (artboard ${esperado})\n               ${nota}`); }
        else { defectos++; console.log(`  ✗ DIVERGE    ${sel} { ${prop} } → ${real} · artboard ${esperado}`); }
    }
    console.log(`  ── ${iguales} idénticas · ${declaradas} divergencias declaradas · ${defectos} sin explicar`);
    sinExplicar += defectos;
    await ctx.close();
}
await nav.close();

// Veredicto por CÓDIGO DE SALIDA, no por leer el informe: así se puede encadenar.
process.exit(sinExplicar === 0 ? 0 : 1);
