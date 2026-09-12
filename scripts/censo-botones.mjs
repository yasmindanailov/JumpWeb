/**
 * CENSO DE BOTONES · cuántas FORMAS distintas de botón tiene el producto.
 *
 * No cuenta clases: cuenta APARIENCIAS. Dos clases con el mismo relleno, borde, radio y peso
 * son el mismo botón aunque se llamen distinto; y una sola clase con dos apariencias son dos
 * botones aunque se llame igual. `#321` dejó escrito que hay UNA familia (`.btn`) — esto mide
 * si sigue siendo cierto a la vista.
 */
import { chromium } from 'playwright-core';

const PAGINAS = (process.env.PAGINAS || '/,/precios,/cumpleanos,/normas,/contacto,/bar,/atracciones').split(',');
const BASE = process.env.BASE || 'http://127.0.0.1:8081';
const ABRIR_CAJON = process.env.CAJON === '1';

const b = await chromium.launch({ args: ['--no-sandbox'] });
const formas = new Map();

for (const ruta of PAGINAS) {
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await p.goto(BASE + ruta, { waitUntil: 'networkidle' });
  await p.evaluate(() => document.fonts.ready);
  if (ABRIR_CAJON) {
    await p.evaluate(() => { const e = document.querySelector('.book-bar__cta, .cta-med'); if (e) e.click(); });
    await p.waitForTimeout(1200);
  }
  const r = await p.evaluate(() => {
    const out = [];
    for (const el of document.querySelectorAll('a, button, .btn, [role=button]')) {
      const cs = getComputedStyle(el), rc = el.getBoundingClientRect();
      if (cs.display === 'none' || rc.width < 40 || rc.height < 24) continue;
      // Un botón es lo que TIENE APARIENCIA de botón: relleno propio o borde visible.
      const relleno = cs.backgroundColor !== 'rgba(0, 0, 0, 0)' && cs.backgroundColor !== 'transparent';
      const bw = parseFloat(cs.borderTopWidth) || 0;
      if (!relleno && bw < 1) continue;
      out.push({
        clave: [relleno ? cs.backgroundColor : 'sin relleno', bw ? `borde ${Math.round(bw)}px ${cs.borderTopColor}` : 'sin borde',
                `radio ${Math.round(parseFloat(cs.borderRadius))}`, `alto ${Math.round(rc.height)}`,
                `peso ${cs.fontWeight}`].join(' · '),
        cls: (el.getAttribute('class') || '').trim().split(/\s+/)[0] || el.tagName.toLowerCase(),
      });
    }
    return out;
  });
  for (const x of r) {
    const a = formas.get(x.clave) || { n: 0, clases: new Set() };
    a.n++; a.clases.add(x.cls); formas.set(x.clave, a);
  }
  await p.close();
}
await b.close();

const f = [...formas.entries()].sort((a, b2) => b2[1].n - a[1].n);
console.log(`\n== FORMAS DE BOTÓN · ${PAGINAS.length} páginas${ABRIR_CAJON ? ' + cajón abierto' : ''} ==\n`);
for (const [k, v] of f) {
  console.log(`${String(v.n).padStart(3)}×  ${k}`);
  console.log(`      clases: ${[...v.clases].slice(0, 6).join(', ')}`);
}
console.log(`\n  ▶ ${f.length} APARIENCIAS distintas, sobre ${f.reduce((a, [, v]) => a + v.n, 0)} botones`);
