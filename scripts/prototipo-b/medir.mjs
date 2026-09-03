/**
 * SONDA del artefacto B — mide la portada móvil primero en los cinco anchos de aceptación de
 * `DECISIONES #432` (320 · 360 · 375 · 390 · 414) y a 1280 de control, con el criterio del guion §2:
 *   · pantalla en la que aparece cada respuesta (edad · precio · juegos · cumple · cómo funciona · visítanos)
 *   · pantallas totales · desbordamiento horizontal
 *   · CADA LÍNEA DE DATO por separado (`[data-linea]`): tiene que caber en UNA línea (la corrección
 *     al instrumento de §6.7: un control puede tener dos filas por diseño y aun así una fila que no
 *     debe partirse). Medido con `Range.getClientRects()` sobre los nodos de texto, no con el alto.
 *   · pulsables a dos líneas (a, button, summary) · objetivos táctiles < 44 · contraste efectivo
 *   · fuentes: que las cuatro familias estén CARGADAS antes de medir (#335)
 * Capturas: 390 y 320 de página entera; 1280 a media escala.
 * Instrumento, gitignorado. Se corre en el contenedor: cd /root/e2e && node medir.mjs
 */
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
const URL = process.env.URL ?? 'http://localhost/storage/prototipo-b/index.html';
const OUT = process.env.OUT ?? '/var/www/html/storage/app/audit/prototipo-b';
mkdirSync(OUT, { recursive: true });

const VIEWS = [[320, 568], [360, 740], [375, 812], [390, 844], [414, 896], [1280, 800]];

const medir = () => {
  const vh = innerHeight;
  const vis = (el) => { if (!el) return false; const r = el.getBoundingClientRect(); const s = getComputedStyle(el); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
  const topAbs = (el) => el ? Math.round(el.getBoundingClientRect().top + scrollY) : null;
  const sc = (el) => el ? +(topAbs(el) / vh).toFixed(1) : null;
  const first = (sel) => [...document.querySelectorAll(sel)].find(vis) || null;
  // líneas de texto de un elemento: filas distintas de los rects de sus nodos de texto
  // ⚠️ No por cubos de 4 px: dos fuentes en la MISMA línea (Bungee y Hanken en un chip) tienen tops
  // distintos y caían en cubos distintos. Se agrupan por SOLAPE vertical: nueva línea solo si el
  // rect empieza por debajo de donde acaba la línea anterior.
  const filas = (el) => {
    const rects = []; const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT); let n;
    while ((n = w.nextNode())) { if (!n.nodeValue.trim()) continue; const r = document.createRange(); r.selectNodeContents(n); for (const b of r.getClientRects()) if (b.width > 0 && b.height > 0) rects.push({ top: b.top, bottom: b.bottom }); }
    rects.sort((a, b) => a.top - b.top);
    let lineas = 0, fin = -Infinity;
    for (const r of rects) { if (r.top >= fin - 3) { lineas++; fin = r.bottom; } else fin = Math.max(fin, r.bottom); }
    return lineas;
  };
  const rotas = [];
  for (const el of document.querySelectorAll('[data-linea]')) { if (!vis(el)) continue; const f = filas(el); if (f >= 2) rotas.push({ txt: el.innerText.trim().slice(0, 40), filas: f, w: Math.round(el.getBoundingClientRect().width) }); }
  const dosLineas = [];
  for (const el of document.querySelectorAll('a, button, summary')) {
    if (!vis(el)) continue; const txt = (el.innerText || '').trim(); if (!txt || txt.length > 80) continue;
    if (el.closest('.zc, .cta, summary, .foot__links')) continue; // controles de varias filas por diseño; el enlace del pie es una tira
    if (filas(el) >= 2) dosLineas.push({ txt: txt.slice(0, 36), cls: (el.className || '').toString().slice(0, 40) });
  }
  const tap = [];
  for (const el of document.querySelectorAll('a, button, summary, input')) {
    if (!vis(el)) continue; const s = getComputedStyle(el); if (s.display === 'inline' && el.tagName === 'A') continue;
    if (el.closest('.proto')) continue; // el sello del prototipo no es producto
    const r = el.getBoundingClientRect(); if (Math.min(r.width, r.height) < 44) tap.push({ txt: (el.innerText || el.getAttribute('aria-label') || el.tagName).trim().slice(0, 30), w: Math.round(r.width), h: Math.round(r.height) });
  }
  const parseRgb = (c) => { const m = c.match(/rgba?\(([^)]+)\)/); if (!m) return null; const p = m[1].split(',').map(parseFloat); return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 }; };
  const lum = ({ r, g, b }) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
  const ratio = (a, b) => { const l1 = lum(a), l2 = lum(b); return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05); };
  const blend = (fg, bg) => ({ r: fg.r * fg.a + bg.r * (1 - fg.a), g: fg.g * fg.a + bg.g * (1 - fg.a), b: fg.b * fg.a + bg.b * (1 - fg.a), a: 1 });
  const effBg = (el) => { let n = el, acc = null; while (n && n !== document.documentElement) { const st = getComputedStyle(n); if (st.backgroundImage && st.backgroundImage !== 'none' && !acc) return null; const c = parseRgb(st.backgroundColor); if (c && c.a > 0) { acc = acc ? blend(acc, c) : c; if (c.a >= 1) return acc; } n = n.parentElement; } const root = parseRgb(getComputedStyle(document.body).backgroundColor); const base = root && root.a > 0 ? root : { r: 255, g: 255, b: 255, a: 1 }; return acc ? blend(acc, base) : base; };
  const fallos = []; const sobreFoto = []; const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let n, k = 0; const seen = new Set();
  while ((n = w.nextNode()) && k < 800) { const t = n.nodeValue.trim(); if (t.length < 2) continue; const el = n.parentElement; if (!el || seen.has(el) || !vis(el) || ['SCRIPT', 'STYLE', 'NOSCRIPT'].includes(el.tagName)) continue;
    // Texto SOBRE UNA FOTO (la etiqueta de edad y el distintivo): su fondo efectivo es la imagen y esta sonda no lo puede medir; se cuenta aparte.
    if (el.closest('.ride__foto, .bd__foto')) { sobreFoto.push(t.slice(0, 24)); continue; }
    seen.add(el); k++; const s = getComputedStyle(el); const fg = parseRgb(s.color); const bg = effBg(el); if (!fg || !bg) continue; const r = ratio(fg.a < 1 ? blend(fg, bg) : fg, bg); const px = parseFloat(s.fontSize); const large = px >= 24 || (px >= 18.66 && parseInt(s.fontWeight, 10) >= 700); if (r < (large ? 3 : 4.5)) fallos.push({ txt: t.slice(0, 30), cls: (el.className || '').toString().slice(0, 36), ratio: +r.toFixed(2), px }); }
  const fonts = [...document.fonts].filter((f) => f.status === 'loaded').map((f) => f.family.replace(/"/g, ''));
  const secs = [...document.querySelectorAll('main > section, main > header')].map((s) => ({ id: s.id, h: Math.round(s.getBoundingClientRect().height), sc: +(s.getBoundingClientRect().height / vh).toFixed(2) }));
  return {
    docH: document.documentElement.scrollHeight, screens: +(document.documentElement.scrollHeight / vh).toFixed(1),
    overflow: Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth),
    q: { edad: sc(first('.zc__dato')), precio: sc(first('.tk__eur')), juegos: sc(first('.ride')), cumple: sc(first('.pk .btn')), funciona: sc(first('.steps')), visita: sc(first('.vc')), dudas: sc(first('.faq')) },
    rotas, dosLineas, tap, contraste: { checked: k, fallos, sobreFoto: sobreFoto.length }, fonts: [...new Set(fonts)], secs,
  };
};

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const res = [];
for (const [w, h] of VIEWS) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, locale: 'es-ES', deviceScaleFactor: w >= 1280 ? 0.5 : 1, hasTouch: w < 900, isMobile: w < 900 });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e).slice(0, 160)));
  page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text().slice(0, 120)); });
  const resp = await page.goto(URL, { waitUntil: 'networkidle', timeout: 90000 });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForFunction(() => ['Bungee', 'Hanken Grotesk', 'JetBrains Mono', 'Permanent Marker'].every((f) => document.fonts.check(`16px "${f}"`)), null, { timeout: 15000 }).catch(() => errors.push('fuentes: no cargaron las cuatro'));
  await page.waitForTimeout(600);
  const m = await page.evaluate(medir);
  // segunda medida con la OTRA zona (Kids), que tiene tres billetes y ocho juegos
  await page.click('.zsw__chip[data-zone-btn="kids"]');
  await page.waitForTimeout(300);
  const mk = await page.evaluate(medir);
  await page.click('.zsw__chip[data-zone-btn="jump"]');
  await page.waitForTimeout(200);
  // D-G3: con el cumpleaños ANTES de los juegos, ¿en qué pantalla cae su botón y su primera tarjeta?
  await page.click('#orden');
  await page.waitForTimeout(200);
  const dg3 = await page.evaluate(() => { const vh = innerHeight; const sc = (s) => { const e = document.querySelector(s); return e ? +((e.getBoundingClientRect().top + scrollY) / vh).toFixed(1) : null; }; return { packs: sc('.packs'), cumpleBtn: sc('.pk .btn'), juegos: sc('.ride') }; });
  await page.click('#orden');
  await page.waitForTimeout(200);
  let shot = null;
  if ([320, 390, 1280].includes(w)) { shot = `${OUT}/b@${w}.png`; await page.screenshot({ path: shot, fullPage: true }).catch(() => { shot = null; }); }
  if (w === 390) { await page.evaluate(() => window.scrollTo(0, 0)); await page.screenshot({ path: `${OUT}/b@390-top.png` }); await page.evaluate(() => document.querySelector('#pricing').scrollIntoView()); await page.waitForTimeout(400); await page.screenshot({ path: `${OUT}/b@390-precios.png` }); }
  res.push({ w, h, status: resp.status(), errors, shot, jump: m, kids: mk, dg3 });
  const f = (x) => `${x.screens} pant · ovf ${x.overflow} · edad ${x.q.edad} · precio ${x.q.precio} · juegos ${x.q.juegos} · cumple ${x.q.cumple} · funciona ${x.q.funciona} · visita ${x.q.visita} · dudas ${x.q.dudas} · rotas ${x.rotas.length} · 2ln ${x.dosLineas.length} · tap<44 ${x.tap.length} · contr ${x.contraste.fallos.length}/${x.contraste.checked} (+${x.contraste.sobreFoto} sobre foto)`;
  console.log(`${String(w).padStart(4)}×${h} http ${resp.status()} | jump: ${f(m)} | kids: ${f(mk)} | cumple-antes: packs ${dg3.packs} · botón ${dg3.cumpleBtn} · juegos ${dg3.juegos} | fuentes ${m.fonts.join(',')}${errors.length ? ' | ERR ' + errors.join(' ‖ ') : ''}`);
  for (const [k, x] of [['jump', m], ['kids', mk]]) {
    if (x.rotas.length) console.log(`     ${k} rotas:`, x.rotas.map((r) => `«${r.txt}» ${r.filas} filas @${r.w}px`).join(' · '));
    if (x.dosLineas.length) console.log(`     ${k} 2ln:`, x.dosLineas.map((r) => `«${r.txt}»`).join(' · '));
    if (x.tap.length) console.log(`     ${k} tap<44:`, x.tap.map((r) => `«${r.txt}» ${r.w}×${r.h}`).join(' · '));
    if (x.contraste.fallos.length) console.log(`     ${k} contr:`, x.contraste.fallos.map((r) => `«${r.txt}» ${r.ratio} ${r.px}px .${r.cls}`).join(' · '));
  }
  if (w === 390) console.log('     secciones (390, jump):', m.secs.map((s) => `${s.id} ${s.sc}`).join(' · '));
  await ctx.close();
}
await browser.close();
writeFileSync(`${OUT}/medidas.json`, JSON.stringify(res, null, 1));
console.log('→', `${OUT}/medidas.json`);
