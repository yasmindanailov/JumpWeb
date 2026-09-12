/**
 * CENSO DE CANDIDATOS A COLOR · qué piezas REPETIDAS de la web pintan hoy en neutro.
 *
 * El sistema del cliente declara `proporcion: '60/30/10'` —neutro / cian identidad / naranja
 * acción— y acota dónde entra: «el color nunca es fondo de sección; entra en TARJETAS, DATOS y
 * BOTONES», más «iconografía y subrayados» del rol del cian.
 *
 * ⚠️ La primera versión censaba por NOMBRE de clase (`.icon`, `[class*="card"]`) y daba 22
 * iconos donde el set tiene 63: aquí los iconos se emiten con clase semántica propia
 * (`arrow-ico`, `faq__sign-i`, `chev`). Es la trampa que `pasada-de-vestido.md` §2.1 ya había
 * pagado: *buscar por nombre supone conocer los nombres*. Se censa por COMPORTAMIENTO —qué
 * pinta el elemento— y se agrupa por su primera clase, que es lo que permite repartir.
 */
import { chromium } from 'playwright-core';

const PAGINAS = (process.env.PAGINAS || '/,/atracciones,/precios,/cumpleanos,/normas,/contacto,/bar').split(',');
const BASE = process.env.BASE || 'http://127.0.0.1:8081';
const TOPE = Number(process.env.TOPE || 30);

const b = await chromium.launch({ args: ['--no-sandbox'] });
const acc = new Map();   // clase -> { tipo, n, color, paginas:Set }

for (const ruta of PAGINAS) {
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await p.goto(BASE + ruta, { waitUntil: 'networkidle' });
  await p.evaluate(() => document.fonts.ready);

  const filas = await p.evaluate(() => {
    const hsl = s => {
      const m = String(s).match(/rgba?\(([^)]+)\)/); if (!m) return null;
      const v = m[1].split(',').map(Number);
      if (v.length > 3 && v[3] < 0.5) return null;
      const R = v[0]/255, G = v[1]/255, B = v[2]/255;
      const mx = Math.max(R,G,B), mn = Math.min(R,G,B), l = (mx+mn)/2;
      const s2 = mx === mn ? 0 : (l > .5 ? (mx-mn)/(2-mx-mn) : (mx-mn)/(mx+mn));
      return { s: s2, l };
    };
    const vivo = c => { const h = hsl(c); return !!h && h.s >= 0.35 && h.l > 0.20 && h.l < 0.88; };

    const out = [];
    for (const el of document.querySelectorAll('body *')) {
      const cs = getComputedStyle(el), r = el.getBoundingClientRect();
      if (cs.display === 'none' || cs.visibility === 'hidden' || r.width < 4 || r.height < 4) continue;
      // ¿Es una pieza de una de las cuatro familias donde el sistema deja entrar color?
      const tag = el.tagName;
      const cls = (el.getAttribute('class') || '').trim().split(/\s+/)[0] || `<${tag.toLowerCase()}>`;
      let tipo = null;
      if (tag === 'svg') tipo = 'icono';
      else if (tag === 'BUTTON' || (tag === 'A' && /btn|cta/i.test(cls))) tipo = 'boton';
      else if (cs.borderTopWidth !== '0px' && r.height > 40 && r.width > 80) tipo = 'tarjeta';
      else if (r.height <= 40 && r.width <= 240 && (cs.backgroundColor !== 'rgba(0, 0, 0, 0)' || cs.borderTopWidth !== '0px')) tipo = 'chapa';
      if (!tipo) continue;
      const tieneColor = vivo(cs.color) || vivo(cs.backgroundColor) || vivo(cs.borderTopColor) || vivo(cs.fill);
      out.push({ tipo, cls, tieneColor });
    }
    return out;
  });

  for (const f of filas) {
    const k = `${f.tipo}|${f.cls}`;
    const a = acc.get(k) || { tipo: f.tipo, cls: f.cls, n: 0, color: 0, paginas: new Set() };
    a.n++; if (f.tieneColor) a.color++; a.paginas.add(ruta);
    acc.set(k, a);
  }
  await p.close();
}
await b.close();

const filas = [...acc.values()].sort((a, b2) => b2.n - a.n);
console.log('\nPIEZAS REPETIDAS QUE HOY PINTAN EN NEUTRO (las siete páginas, 1440px)\n');
console.log('tipo'.padEnd(9), 'clase'.padEnd(28), 'veces'.padStart(6), 'color'.padStart(6), 'págs'.padStart(5));
let mostradas = 0;
for (const f of filas) {
  if (f.color === f.n) continue;              // ya lleva color en todas: no es candidata
  if (mostradas++ >= TOPE) break;
  console.log(f.tipo.padEnd(9), f.cls.slice(0, 28).padEnd(28),
              String(f.n).padStart(6), String(f.color).padStart(6), String(f.paginas.size).padStart(5));
}
const tot = filas.reduce((a, f) => a + f.n, 0);
const col = filas.reduce((a, f) => a + f.color, 0);
console.log('\n' + '-'.repeat(62));
console.log(`piezas de las cuatro familias: ${tot} · con color ${col} (${(100*col/tot).toFixed(1)}%) · en NEUTRO ${tot - col}`);
