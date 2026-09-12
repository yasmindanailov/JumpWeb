/**
 * SONDA DE CONTRASTE · mide el ratio REAL de todo el texto y los bordes de una página.
 *
 * ⚠️ Mide sobre la PILA de fondos, no sobre el del `<body>`: el fondo de un texto es el de su
 * ancestro más cercano que pinte. Medir contra el fondo de la página es cómo un texto ilegible
 * pasa por bueno — la trampa que `#505` pagó en los correos.
 *
 * Umbrales WCAG 2.1: 4,5 texto normal · 3,0 texto ≥24px o bold ≥19px · 3,0 para bordes y
 * controles (1.4.11). La fórmula es la del propio sistema del cliente (`tokens-pjp.js`).
 */
import { chromium } from 'playwright-core';

const URL   = process.env.URL   || 'http://127.0.0.1:8081/';
const ANCHO = Number(process.env.ANCHO || 1440);

const b = await chromium.launch({ args: ['--no-sandbox'] });
const p = await b.newPage({ viewport: { width: ANCHO, height: 900 } });
await p.goto(URL, { waitUntil: 'networkidle' });
await p.evaluate(() => document.fonts.ready);
await p.mouse.move(-10, -10);

const fallos = await p.evaluate(() => {
  const lum = ({ r, g, b }) => {
    const c = [r, g, b].map(v => v / 255).map(v => v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  };
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

  const ratio = (a, b2) => {
    const x = lum(a), y = lum(b2);
    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
  };
  // El fondo efectivo: sube hasta el primer ancestro que pinte de verdad.
  const fondo = el => {
    let n = el;
    while (n && n !== document.documentElement) {
      const c = rgb(getComputedStyle(n).backgroundColor);
      if (c && c.a > 0.5) return c;
      n = n.parentElement;
    }
    return { r: 255, g: 255, b: 255, a: 1 };
  };
  const hex = c => '#' + [c.r, c.g, c.b].map(v => Math.round(v).toString(16).padStart(2, '0')).join('').toUpperCase();

  const out = [];
  for (const el of document.querySelectorAll('body *')) {
    const cs = getComputedStyle(el), r = el.getBoundingClientRect();
    if (cs.display === 'none' || cs.visibility === 'hidden' || r.width < 2 || r.height < 2) continue;
    // Solo elementos con texto PROPIO (no contenedores que heredan hijos).
    const txt = [...el.childNodes].filter(n => n.nodeType === 3 && n.textContent.trim()).map(n => n.textContent.trim()).join(' ');
    const cls = (el.getAttribute('class') || '').trim().split(/\s+/)[0] || el.tagName.toLowerCase();

    if (txt) {
      const fg = rgb(cs.color), bg = fondo(el);
      if (fg && fg.a > 0.5) {
        const px = parseFloat(cs.fontSize), peso = parseInt(cs.fontWeight) || 400;
        const grande = px >= 24 || (peso >= 700 && px >= 19);
        const min = grande ? 3.0 : 4.5;
        const rt = ratio(fg, bg);
        if (rt < min) out.push({ tipo: 'texto', cls, txt: txt.slice(0, 34), rt: +rt.toFixed(2), min,
                                 fg: hex(fg), bg: hex(bg), px: Math.round(px), peso });
      }
    }
    // Bordes visibles: WCAG 1.4.11 pide 3,0 contra lo que tienen al lado.
    const bw = parseFloat(cs.borderTopWidth);
    if (bw >= 1 && r.height > 20 && r.width > 40) {
      const bc = rgb(cs.borderTopColor);
      const alrededor = el.parentElement ? fondo(el.parentElement) : { r: 255, g: 255, b: 255 };
      if (bc && bc.a > 0.5) {
        const rt = ratio(bc, alrededor);
        if (rt < 3.0) out.push({ tipo: 'borde', cls, txt: '(borde)', rt: +rt.toFixed(2), min: 3.0,
                                 fg: hex(bc), bg: hex(alrededor), px: 0, peso: 0 });
      }
    }
  }
  // Agrupa por clase + color: lo que importa es la REGLA, no cada aparición.
  const g = new Map();
  for (const f of out) {
    const k = `${f.tipo}|${f.cls}|${f.fg}|${f.bg}`;
    const a = g.get(k) || { ...f, n: 0 };
    a.n++; g.set(k, a);
  }
  return [...g.values()].sort((a, b2) => a.rt - b2.rt);
});

console.log(`\n== CONTRASTE · ${URL} · ${ANCHO}px ==`);
if (!fallos.length) console.log('  ✓ sin fallos de contraste');
console.log('tipo'.padEnd(7), 'clase'.padEnd(24), 'ratio'.padStart(6), 'pide'.padStart(5), 'veces'.padStart(6), ' color / fondo');
for (const f of fallos) {
  console.log(f.tipo.padEnd(7), f.cls.slice(0, 24).padEnd(24), String(f.rt).padStart(6),
              String(f.min).padStart(5), String(f.n).padStart(6), ` ${f.fg} / ${f.bg}`,
              f.px ? ` ${f.px}px/${f.peso}  «${f.txt}»` : '');
}
console.log(`\n  ${fallos.length} reglas con contraste insuficiente`);
await b.close();
