/**
 * SONDA DE COLOR · cuánta superficie de una página es COLOR y cuánta es neutra.
 *
 * Mide por MUESTREO con `elementFromPoint`, que es quien resuelve la pila de capas como la ve
 * el navegador (un `background` declarado no dice qué se ve encima). Para cada punto sube por
 * ancestros hasta el primer fondo opaco y lo clasifica.
 *
 * TRAMPAS QUE HEREDA (todas escritas ya en el repo):
 *  1. `elementFromPoint` trabaja en coordenadas de VIEWPORT: hay que desplazarse (#264).
 *  2. Los `fixed` se cosen encima de todo (#303): se marcan aparte, no se ocultan.
 *  3. Las fuentes: medir antes de `fonts.ready` mide la de respaldo (#323).
 *  4. El puntero virtual arranca en (0,0) y deja la primera pieza en HOVER (#478).
 */
import { chromium } from 'playwright-core';

const URL   = process.env.URL   || 'http://127.0.0.1:8081/';
const ANCHO = Number(process.env.ANCHO || 1440);
const ALTO  = Number(process.env.ALTO  || 900);
const PASO  = Number(process.env.PASO  || 12);   // rejilla de muestreo, en px

const b = await chromium.launch({ args: ['--no-sandbox'] });
const p = await b.newPage({ viewport: { width: ANCHO, height: ALTO } });
await p.goto(URL, { waitUntil: 'networkidle' });
await p.evaluate(() => document.fonts.ready);
await p.mouse.move(-10, -10);                     // trampa 4
// El banner de cookies es `fixed` y tapa: se acepta si ya está resuelto.
await p.evaluate(() => {
  const c = document.querySelector('.cookie-banner, [data-cookie-banner]');
  if (c) c.setAttribute('data-sonda-oculto', '1'), c.style.display = 'none';
});
await p.waitForTimeout(400);

const res = await p.evaluate(async ({ paso }) => {
  const sleep = ms => new Promise(r => setTimeout(r, ms));

  // ⚠️ `color-mix()` computa como `color(srgb r g b / a)` con los canales en 0-1, NO como
  // `rgb()`. Un parser que solo entienda `rgb()` deja de ver TODO lo derivado y mide una web
  // sin color sobre una web teñida — pasó al consolidar `#537`: las reglas estaban aplicadas y
  // la sonda daba 1,2 %. Se aceptan las dos formas.
  const parseRGB = s => {
    const t = String(s);
    let m = t.match(/color\(srgb\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)(?:\s*\/\s*([0-9.]+))?\)/);
    if (m) return { r: +m[1] * 255, g: +m[2] * 255, b: +m[3] * 255, a: m[4] === undefined ? 1 : +m[4] };
    m = t.match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    const v = m[1].split(',').map(x => parseFloat(x));
    return { r: v[0], g: v[1], b: v[2], a: v.length > 3 ? v[3] : 1 };
  };
  // Saturación y claridad HSL a partir de RGB 0-255.
  const hsl = ({ r, g, b }) => {
    const R = r / 255, G = g / 255, B = b / 255;
    const mx = Math.max(R, G, B), mn = Math.min(R, G, B), l = (mx + mn) / 2;
    if (mx === mn) return { h: 0, s: 0, l };
    const d = mx - mn;
    const s = l > 0.5 ? d / (2 - mx - mn) : d / (mx + mn);
    let h;
    if (mx === R) h = ((G - B) / d + (G < B ? 6 : 0));
    else if (mx === G) h = (B - R) / d + 2;
    else h = (R - G) / d + 4;
    return { h: h * 60, s, l };
  };

  // Sube hasta el primer fondo que realmente pinta.
  // ⚠️ Un MEDIO (img/video/canvas/svg) pinta sin `background-image`: la primera versión de esta
  // sonda los atravesaba y devolvía el fondo del contenedor — daba «0,0 % foto» en una portada
  // con vídeo de hero y cinco fotos. Se clasifican por ETIQUETA, antes de mirar ningún fondo.
  const MEDIO = new Set(['IMG', 'VIDEO', 'CANVAS', 'SVG', 'PICTURE']);
  const fondoEfectivo = (el) => {
    let n = el;
    while (n && n !== document.documentElement) {
      if (MEDIO.has(n.tagName) || (n.tagName === 'svg')) return { tipo: 'medio', el: n };
      const cs = getComputedStyle(n);
      const bi = cs.backgroundImage;
      if (bi && bi !== 'none') {
        if (/url\(/.test(bi)) return { tipo: 'medio', el: n };
        return { tipo: 'grad', el: n, color: cs.backgroundColor };
      }
      const c = parseRGB(cs.backgroundColor);
      if (c && c.a > 0.5) return { tipo: 'color', el: n, color: cs.backgroundColor };
      n = n.parentElement;
    }
    const cs = getComputedStyle(document.body);
    return { tipo: 'color', el: document.body, color: cs.backgroundColor };
  };

  const secciones = [...document.querySelectorAll('main section, main .section, main > *')]
    .filter(e => e.offsetHeight > 80)
    .map(e => ({
      id: e.id || e.className.toString().split(' ').filter(Boolean).slice(0, 2).join('.') || e.tagName.toLowerCase(),
      el: e,
    }));

  const out = [];
  for (const s of secciones) {
    const r0 = s.el.getBoundingClientRect();
    const top = r0.top + window.scrollY, alto = r0.height, ancho = r0.width, izq = r0.left + window.scrollX;
    const cuentas = new Map();
    let total = 0, fotos = 0, fijos = 0;

    for (let y = top + paso / 2; y < top + alto; y += paso) {
      // Desplaza para que la fila caiga en viewport (trampa 1).
      const destino = Math.max(0, Math.min(y - window.innerHeight / 2,
                      document.documentElement.scrollHeight - window.innerHeight));
      if (Math.abs(window.scrollY - destino) > 4) { window.scrollTo(0, destino); await sleep(8); }
      const vy = y - window.scrollY;
      if (vy < 0 || vy > window.innerHeight - 1) continue;

      for (let x = izq + paso / 2; x < izq + ancho; x += paso) {
        const el = document.elementFromPoint(x, vy);
        if (!el) continue;
        total++;
        // ¿Es una capa `fixed` cosida encima? (trampa 2)
        let n = el, esFijo = false;
        while (n && n !== document.documentElement) {
          if (getComputedStyle(n).position === 'fixed') { esFijo = true; break; }
          n = n.parentElement;
        }
        if (esFijo) { fijos++; continue; }

        const f = fondoEfectivo(el);
        if (f.tipo === 'medio') { fotos++; continue; }
        const c = parseRGB(f.color);
        if (!c) continue;
        const k = `rgb(${Math.round(c.r)},${Math.round(c.g)},${Math.round(c.b)})`;
        cuentas.set(k, (cuentas.get(k) || 0) + 1);
      }
    }

    const filas = [...cuentas.entries()].sort((a, b) => b[1] - a[1]).map(([col, n]) => {
      const c = parseRGB(col), h = hsl(c);
      return { col, n, s: +h.s.toFixed(3), l: +h.l.toFixed(3), hue: Math.round(h.h) };
    });
    // Tres cubos, y la separación IMPORTA: la tinta del sistema (#101418) tiene s=0,20 y un
    // clasificador que solo mire saturación la cuenta como «color» — con eso el hero salía al
    // 88 % de color siendo un negro azulado. Color VIVO es lo que el ojo lee como color.
    const esVivo   = f => f.s >= 0.35 && f.l > 0.20 && f.l < 0.88;
    // ⚠️ Un TINTE pastel es color a la vista y mi primer umbral lo descartaba: la superficie
    // teñida que el sistema declara (`tintePapel.cian` #D5EAEE) da s=0,42 pero l=0,88, así que
    // caía en «papel» junto al #F4F4F1 — y con ella toda una familia de reparto salía medida
    // como si no existiera. El papel neutro está en s≤0,12 y la Nube en 0,08: 0,15 los separa.
    const esTinte  = f => !esVivo(f) && f.s >= 0.15 && f.l > 0.20;
    const esTinta  = f => f.l <= 0.20;
    const vivo  = filas.filter(esVivo).reduce((a, f) => a + f.n, 0);
    const tinte = filas.filter(esTinte).reduce((a, f) => a + f.n, 0);
    const tinta = filas.filter(f => !esVivo(f) && !esTinte(f) && esTinta(f)).reduce((a, f) => a + f.n, 0);
    out.push({ id: s.id, alto: Math.round(alto), total, fotos, fijos, vivo, tinte, tinta, filas: filas.slice(0, 6) });
  }
  return { out, altoDoc: document.documentElement.scrollHeight };
}, { paso: PASO });

const pct = (n, d) => d ? (100 * n / d).toFixed(1).padStart(6) : '   0.0';
const DETALLE = process.env.DETALLE === '1';
console.log(`\n== ${URL}  ${ANCHO}x${ALTO}  ·  documento ${res.altoDoc} px ==`);
console.log('sección'.padEnd(24), 'alto'.padStart(6), 'muestras'.padStart(9),
            '%VIVO'.padStart(7), '%tinte'.padStart(7), '%tinta'.padStart(7), '%medio'.padStart(7), '%papel'.padStart(7));
let T = 0, V = 0, N = 0, K = 0, F = 0, X = 0;
for (const s of res.out) {
  const papel = s.total - s.vivo - s.tinte - s.tinta - s.fotos - s.fijos;
  T += s.total; V += s.vivo; N += s.tinte; K += s.tinta; F += s.fotos; X += s.fijos;
  console.log(s.id.padEnd(24), String(s.alto).padStart(6), String(s.total).padStart(9),
              pct(s.vivo, s.total), pct(s.tinte, s.total), pct(s.tinta, s.total), pct(s.fotos, s.total), pct(papel, s.total));
  if (DETALLE) for (const f of s.filas) {
    const tag = (f.s >= 0.35 && f.l > 0.20 && f.l < 0.88) ? 'VIVO'
              : (f.s >= 0.15 && f.l > 0.20) ? 'tinte' : (f.l <= 0.20 ? 'tinta' : 'papel');
    console.log('   ', f.col.padEnd(18), String(f.n).padStart(6), `s=${f.s}`.padEnd(9), `l=${f.l}`.padEnd(9), `h=${f.hue}`.padEnd(7), tag);
  }
}
console.log('-'.repeat(78));
// ⚠️ El total resta los `fijos` igual que cada fila. Sin eso decía «100 % papel» sobre una
// página cuyas filas daban 0,0 en las cuatro columnas — una incoherencia interna que delató
// que /entradas no es una página: es una PUERTA que abre el cajón (`fixed`) sobre la portada.
const papelT = T - V - N - K - F - X;
console.log('TOTAL'.padEnd(24), ''.padStart(6), String(T).padStart(9),
            pct(V, T), pct(N, T), pct(K, T), pct(F, T), pct(papelT, T));
console.log(`   ▶ COLOR (vivo + tinte) = ${(100*(V+N)/T).toFixed(1)}%`);
if (X / T > 0.5) console.log(`\n⚠️  ${pct(X, T)}% de la página la tapa una capa fija: ` +
  'no es una página de contenido, es una puerta que abre algo encima.');
await b.close();
