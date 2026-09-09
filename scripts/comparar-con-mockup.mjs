/**
 * COMPARADOR PÍXEL A PÍXEL · el artboard del canvas contra el producto.
 *
 * ⚠️⚠️ **ESTE INSTRUMENTO ESTÁ VERSIONADO A PROPÓSITO**, como `sonda-geometria.mjs`: es lo que
 * convierte «se parece» en una lista de números. Sin él, «idéntico al mockup» se comprueba mirando,
 * y mirar dio **cuatro rondas de correcciones** en la sección 01 — el velo 74 px fuera de la
 * tarjeta, el contenido 38 px más abajo, el peso del nombre sintetizado y la opacidad del velo, que
 * el artboard cambia por zona y nadie había visto.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────
 *   1. Aislar el artboard en un HTML servible (ver `storage/app/mockup-4a.html` como ejemplo): se
 *      extrae el bloque de la opción cerrada, se sustituyen los `<image-slot>` del canvas por un
 *      div del mismo alto y se enlazan sus fuentes.
 *   2. docker compose exec -u sail -T laravel.test bash -lc \
 *        'PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers node scripts/comparar-con-mockup.mjs'
 *
 * ❗❗ **LAS TRES TRAMPAS QUE ESTE COMPARADOR YA PAGÓ**, y por las que da 4 divergencias y no 40:
 *  1. **El puntero virtual arranca en (0,0)**, así que tras el scroll una tarjeta quedaba en HOVER y
 *     su sombra salía como divergencia. Se aparta el ratón antes de medir.
 *  2. **El mismo ROL vive en soportes distintos**: en el artboard el eje y el hueco de la vecina son
 *     un contenedor sin texto propio, y en el nuestro es al revés. Comparar su tipografía o su caja
 *     empareja elementos que no son el mismo — eso es una divergencia del INSTRUMENTO, no del
 *     producto, y por eso `NO_COMPARABLE` las excluye **con su motivo escrito**.
 *  3. **El ancho del sello lo manda el DATO** (el artboard escribe «10 € finde» y la BD de esta
 *     instalación «10 € Viernes, findes y festivos»): compararlo mide el copy, no el diseño.
 *
 * Extrae de cada tarjeta el PERFIL de sus piezas —caja relativa a la tarjeta más los estilos que
 * definen su aspecto— y las empareja por ROL, no por posición en el DOM: los dos marcados son
 * distintos (el artboard es todo inline, el nuestro son clases) y comparar por orden daría parejas
 * falsas.
 *
 * ⚠️ Lo que NO se compara: alturas que dependen del TEXTO. El artboard trae el copy de PlayJump y
 * nosotros el de la BD, así que un párrafo de tres líneas contra uno de cuatro diverge por dato y no
 * por diseño. Se comparan cajas estructurales, tipografía, color, borde, radio y sangrado.
 */
import { chromium } from 'playwright-core';

const PERFIL = `(() => {
  const rol = (el) => {
    const s = getComputedStyle(el);
    const bg = s.backgroundColor;
    const t = (el.textContent || '').trim();
    if (s.borderTopStyle === 'dashed') return 'frontera';
    if (s.borderLeftStyle === 'solid' && parseFloat(s.borderLeftWidth) >= 2 && el.clientWidth < 90 && el.clientHeight > 80) return 'eje';
    if (/^rgb\\(245, 196, 0\\)|^rgb\\(255, 225, 74\\)/.test(bg)) return 'sello';
    if (parseFloat(s.opacity) > 0 && parseFloat(s.opacity) < 1 && bg !== 'rgba(0, 0, 0, 0)') return 'velo';
    if (/^\\d+[.,]\\d\\d m$/.test(t) && s.borderStyle.startsWith('solid')) return 'chapa';
    if (/Bungee/.test(s.fontFamily) && /€/.test(t) && t.length < 10) return 'precio';
    if (/Bungee/.test(s.fontFamily) && /^(jump|kids)$/i.test(t)) return 'nombre';
    if (/Mono/.test(s.fontFamily) && /^desde$/i.test(t)) return 'desde';
    if (/Mono/.test(s.fontFamily) && /(encima|debajo|above|below)/i.test(t) && t.length < 30 && el.children.length === 0) return 'vecina';
    if (/Mono/.test(s.fontFamily) && /^altura|^height|^taille/i.test(t) && t.length < 30) return 'ejeRotulo';
    if (el.tagName === 'IMG' || (el.previousElementSibling === null && s.aspectRatio !== 'auto')) return 'foto';
    return null;
  };

  const out = {};
  for (const card of document.querySelectorAll('[data-cmp]')) {
    const rc = card.getBoundingClientRect();
    const cs = getComputedStyle(card);
    const zona = card.getAttribute('data-cmp');
    const piezas = {};
    piezas.__tarjeta = {
      w: Math.round(rc.width),
      borde: cs.borderTopWidth + ' ' + cs.borderTopStyle,
      radio: cs.borderTopLeftRadius,
      sombra: cs.boxShadow,
      fondo: cs.backgroundColor,
    };
    for (const el of card.querySelectorAll('*')) {
      const r = rol(el);
      if (!r || piezas[r]) continue;
      const b = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      piezas[r] = {
        l: Math.round(b.left - rc.left), r: Math.round(rc.right - b.right),
        w: Math.round(b.width), h: Math.round(b.height),
        fs: s.fontSize, ff: (s.fontFamily || '').split(',')[0].replace(/"/g, ''),
        fw: s.fontWeight, ls: s.letterSpacing, color: s.color,
        bg: s.backgroundColor, op: s.opacity,
        borde: parseFloat(s.borderTopWidth) ? s.borderTopWidth + ' ' + s.borderTopStyle : '—',
        radio: s.borderTopLeftRadius, pad: s.padding, tr: s.transform === 'none' ? '—' : 'sí',
      };
    }
    out[zona] = piezas;
  }
  return out;
})()`;

const b = await chromium.launch();

// ── el MOCKUP ────────────────────────────────────────────────────────────────────────────────
let ctx = await b.newContext({ viewport: { width: 390, height: 1400 }, deviceScaleFactor: 2 });
let p = await ctx.newPage();
await p.goto('file:///var/www/html/storage/app/mockup-4a.html', { waitUntil: 'networkidle' });
await p.evaluate(() => document.fonts.ready);
await p.waitForTimeout(600);
await p.evaluate(() => {
  document.querySelectorAll('.marco > a').forEach((a) => a.setAttribute('data-cmp', a.getAttribute('href').replace('#zona-', '')));
});
const mock = await p.evaluate(PERFIL);
await ctx.close();

// ── el NUESTRO ───────────────────────────────────────────────────────────────────────────────
ctx = await b.newContext({ viewport: { width: 390, height: 900 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
p = await ctx.newPage();
await p.goto('http://localhost:8081/', { waitUntil: 'networkidle' });
await p.locator('.cookie-btn').first().click().catch(() => {});
await p.waitForTimeout(300);
await p.evaluate(() => document.fonts.ready);
await p.locator('#zones').scrollIntoViewIfNeeded();
// ⚠️ **El puntero virtual arranca en (0,0) y ahí cae una tarjeta tras el scroll**, así que su sombra
// se leía en HOVER y salía como divergencia. Se aparta antes de medir.
await p.mouse.move(389, 1);
await p.waitForTimeout(700);
await p.evaluate(() => {
  document.querySelectorAll('.zone-card').forEach((a) => a.setAttribute('data-cmp', a.dataset.zone));
});
const nuestro = await p.evaluate(PERFIL);
await b.close();

// ── el INFORME ───────────────────────────────────────────────────────────────────────────────
// Solo se comparan claves de ESTILO y sangrados; las alturas dependen del texto y se omiten.
const COMPARAR = ['l', 'r', 'w', 'fs', 'ff', 'fw', 'ls', 'color', 'bg', 'op', 'borde', 'radio', 'pad', 'tr'];
const SOLO_ESTILO = new Set(['h']);
/* Claves que NO se comparan por pieza, con su motivo. No es tolerancia: es que el soporte del rol es
   distinto en los dos marcados y compararlas empareja elementos que no son el mismo. */
const NO_COMPARABLE = {
  eje: ['fs', 'ff', 'fw', 'color', 'ls'],       // en el mockup es un contenedor sin texto propio
  vecina: ['l', 'r', 'w', 'pad'],               // allí el sangrado lo pone el padre; aquí, el propio
  sello: ['l', 'w'], desde: ['l', 'r', 'w'], precio: ['l', 'w'], // su ancho lo manda el DATO (ver informe)
};
let divergencias = 0;

for (const zona of Object.keys(mock)) {
  const m = mock[zona];
  const n = nuestro[zona];
  console.log(`\n${'═'.repeat(84)}\n  ${zona.toUpperCase()}\n${'═'.repeat(84)}`);
  if (!n) { console.log('  ✗ no encuentro esa tarjeta en el producto'); divergencias++; continue; }

  for (const pieza of Object.keys(m)) {
    const a = m[pieza], c = n[pieza];
    if (!c) { console.log(`  ✗ ${pieza.padEnd(12)} NO EXISTE en el producto`); divergencias++; continue; }
    const difs = [];
    for (const k of Object.keys(a)) {
      if (SOLO_ESTILO.has(k) || (COMPARAR.length && !COMPARAR.includes(k) && pieza !== '__tarjeta')) continue;
      if ((NO_COMPARABLE[pieza] || []).includes(k)) continue;
      if (String(a[k]) !== String(c[k])) difs.push(`${k}: ${a[k]}  →  ${c[k]}`);
    }
    if (difs.length === 0) console.log(`  ✓ ${pieza.padEnd(12)} idéntica`);
    else { console.log(`  ✗ ${pieza.padEnd(12)} ${difs.join('   ·   ')}`); divergencias += difs.length; }
  }
  for (const pieza of Object.keys(n)) {
    if (!m[pieza]) { console.log(`  ⚠ ${pieza.padEnd(12)} está en el producto y NO en el mockup`); }
  }
}
console.log(`\n── ${divergencias} divergencia(s) ──\n`);
