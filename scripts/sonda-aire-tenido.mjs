/**
 * SONDA DE AIRE · para cada caja con fondo teñido, la distancia REAL de su texto al borde.
 *
 * ⚠️ Existe porque teñir una pieza es convertirla en CAJA, y una caja sin aire se lee como un
 * defecto — pasó al teñir `.before__row`, que es una FILA de una `<dl>` con `padding: 14px 0`:
 * el color llegaba pegado a la letra. El `padding` propio NO basta como medida: el aire puede
 * ponerlo un hijo (`.faq__item` tiene padding 0 y su botón interno sí lo trae). Lo que se mide
 * es la distancia del rectángulo del TEXTO al de la caja.
 */
import { chromium } from 'playwright-core';

const URL = process.env.URL || 'http://127.0.0.1:8081/?vestido=c';
const MIN = Number(process.env.MIN || 10);

const b = await chromium.launch({ args: ['--no-sandbox'] });
const p = await b.newPage({ viewport: { width: Number(process.env.ANCHO || 1440), height: 900 } });
await p.goto(URL, { waitUntil: 'networkidle' });
await p.evaluate(() => document.fonts.ready);

const filas = await p.evaluate((MIN) => {
  // ⚠️ `color-mix()` computa como `color(srgb r g b / a)` con los canales en 0-1, NO como
  // `rgb()`. Un parser que solo entienda `rgb()` deja de ver TODO lo derivado y mide una web
  // sin color sobre una web teñida — pasó al consolidar `#537`: las reglas estaban aplicadas y
  // la sonda daba 1,2 %. Se aceptan las dos formas.
  const rgb = s => {
    const t = String(s);
    let m = t.match(/color\(srgb\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)(?:\s*\/\s*([0-9.]+))?\)/);
    if (m) return { r: +m[1] * 255, g: +m[2] * 255, b: +m[3] * 255, a: m[4] === undefined ? 1 : +m[4] };
    m = t.match(/rgba?\(([^)]+)\)/);
    if (!m) return null;
    const v = m[1].split(',').map(x => parseFloat(x));
    return { r: v[0], g: v[1], b: v[2], a: v.length > 3 ? v[3] : 1 };
  };

  const hsl = c => { const R=c.r/255,G=c.g/255,B=c.b/255, mx=Math.max(R,G,B), mn=Math.min(R,G,B), l=(mx+mn)/2;
    return { s: mx===mn?0:(l>.5?(mx-mn)/(2-mx-mn):(mx-mn)/(mx+mn)), l }; };

  const out = [];
  for (const el of document.querySelectorAll('body *')) {
    const cs = getComputedStyle(el), r = el.getBoundingClientRect();
    if (cs.display === 'none' || r.width < 40 || r.height < 20) continue;
    const bg = rgb(cs.backgroundColor);
    if (!bg || bg.a < 0.5) continue;
    const h = hsl(bg);
    if (h.s < 0.15) continue;                       // no está teñida
    // El rectángulo del texto de DENTRO, medido con Range (la tinta, no la caja).
    let min = Infinity, quien = '';
    const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
    let n;
    while ((n = w.nextNode())) {
      if (!n.textContent.trim()) continue;
      // ⚠️ Un texto CLIPEADO no cuenta: el acordeón cerrado guarda su respuesta con
      // `height:0; overflow:hidden` y su Range sigue devolviendo el rectángulo completo —
      // daba «−46 px de aire» sobre una tarjeta perfectamente aireada. Se descarta el texto
      // cuyo ancestro no le deje sitio, que es el mismo motivo por el que no se ve.
      let clip = false;
      for (let a = n.parentElement; a && a !== el.parentElement; a = a.parentElement) {
        const ac = getComputedStyle(a), ar = a.getBoundingClientRect();
        if ((ac.overflow !== 'visible' || ac.overflowY !== 'visible') && ar.height < 4) { clip = true; break; }
      }
      if (clip) continue;
      const rg = document.createRange(); rg.selectNodeContents(n);
      const tr = rg.getBoundingClientRect();
      if (tr.width < 2) continue;
      const d = Math.min(tr.left - r.left, r.right - tr.right, tr.top - r.top, r.bottom - tr.bottom);
      if (d < min) { min = d; quien = n.textContent.trim().slice(0, 26); }
    }
    if (min === Infinity) continue;
    const cls = (el.getAttribute('class') || '').trim().split(/\s+/)[0] || el.tagName.toLowerCase();
    out.push({ cls, aire: Math.round(min), quien, ok: min >= MIN });
  }
  const g = new Map();
  for (const f of out) { const a = g.get(f.cls) || { ...f, n: 0 }; if (f.aire < a.aire) a.aire = f.aire, a.quien = f.quien, a.ok = f.ok; a.n++; g.set(f.cls, a); }
  return [...g.values()].sort((a, b2) => a.aire - b2.aire);
}, MIN);

console.log(`\n== AIRE DE LAS CAJAS TEÑIDAS · ${URL} ==`);
console.log('clase'.padEnd(24), 'aire'.padStart(5), 'veces'.padStart(6), '  texto más cercano');
for (const f of filas) {
  console.log((f.ok ? '  ' : '✗ ') + f.cls.slice(0, 22).padEnd(22), String(f.aire).padStart(5),
              String(f.n).padStart(6), `  «${f.quien}»`);
}
const malas = filas.filter(f => !f.ok).length;
console.log(`\n  ${filas.length} cajas teñidas · ${malas} por debajo de ${MIN}px de aire`);
await b.close();
