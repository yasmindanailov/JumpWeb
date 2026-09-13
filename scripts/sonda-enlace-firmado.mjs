/**
 * SONDA DE LAS PÁGINAS DE ENLACE FIRMADO — el post-form y el justificante (`docs/specs/celebracion-e-invitacion.md`).
 *
 * Hermana de `scripts/sonda-cajon.mjs`: la sonda de la web solo llega a lo público y la del cajón no
 * entra aquí, así que estas dos páginas no las medía ningún instrumento versionado.
 *
 * ── QUÉ MIDE, POR PÁGINA Y ANCHO ─────────────────────────────────────────────────────────────────
 *   · las tallas de letra COMPUTADAS de los nodos con texto propio, y cuáles bajan de 15 px;
 *   · los controles (`a`, `button`, `summary`, `input`, `select`, `textarea`) por debajo de 48 px de
 *     alto —⚠️ la CAJA, no el área: un control dentro de un `label` que lo agranda sale acusado—;
 *   · los radios y las sombras distintas que se pintan dentro de la página;
 *   · los nodos pintados con el color de ZONA (`--zone-1`), por fondo, texto o borde;
 *   · el alto total, y una captura de página entera.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   Chromium y el puente 8081→80 como en `scripts/sonda-cajon.mjs` (cabecera, paso 1). Las URLs firmadas
 *   las imprime el fixture local (`storage/app/probe-postform.php`, no versionado):
 *     docker compose exec -T -u sail laravel.test php artisan tinker storage/app/probe-postform.php
 *     docker compose exec -T -u sail -e GUESTS='…' -e AUTH='…' -e OUT=storage/app/sonda-t1/antes \
 *       laravel.test node scripts/sonda-enlace-firmado.mjs
 *
 * ⚠️ Se espera a `document.fonts.ready` y se comprueba que la familia de rótulo cargó: una captura con
 * la fuente de respaldo enseña otra página (`#323`, `#335`).
 */
import { chromium } from 'playwright-core';
import { mkdirSync, writeFileSync } from 'node:fs';

const pages = [['guests', process.env.GUESTS], ['auth', process.env.AUTH]].filter(([, u]) => u);
const out = process.env.OUT ?? 'storage/app/sonda-enlace-firmado';
const widths = (process.env.WIDTHS ?? '390,1280').split(',').map(Number);
mkdirSync(out, { recursive: true });

const browser = await chromium.launch();
const report = {};

for (const [name, url] of pages) {
    for (const width of widths) {
        const page = await browser.newPage({ viewport: { width, height: 844 } });
        await page.goto(url, { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        await page.mouse.move(0, 0);

        const m = await page.evaluate(() => {
            const zone = getComputedStyle(document.documentElement).getPropertyValue('--zone-1').trim();
            const probe = document.createElement('i');
            probe.style.color = zone;
            document.body.appendChild(probe);
            const zoneRgb = getComputedStyle(probe).color;
            probe.remove();

            const sizes = {};
            const small = [];
            const radii = {};
            const shadows = {};
            const zoned = [];
            const all = document.querySelectorAll('body *');
            for (const el of all) {
                const cs = getComputedStyle(el);
                if (cs.display === 'none' || cs.visibility === 'hidden') continue;
                const own = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim() !== '');
                const tag = `${el.tagName.toLowerCase()}.${(el.getAttribute('class') ?? '').split(' ')[0]}`;
                if (own) {
                    sizes[cs.fontSize] = (sizes[cs.fontSize] ?? 0) + 1;
                    if (parseFloat(cs.fontSize) < 15) small.push(`${tag} ${cs.fontSize} «${el.textContent.trim().slice(0, 28)}»`);
                }
                if (cs.borderTopLeftRadius !== '0px') radii[cs.borderTopLeftRadius] = (radii[cs.borderTopLeftRadius] ?? 0) + 1;
                if (cs.boxShadow !== 'none') shadows[cs.boxShadow] = (shadows[cs.boxShadow] ?? 0) + 1;
                if ([cs.color, cs.backgroundColor, cs.borderTopColor].includes(zoneRgb) && zoneRgb !== 'rgb(0, 0, 0)') zoned.push(tag);
            }
            const controls = [...document.querySelectorAll('a, button, summary, input:not([type=hidden]), select, textarea')]
                .filter((el) => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden')
                .map((el) => [el, el.getBoundingClientRect()])
                .filter(([, r]) => r.height < 48)
                .map(([el, r]) => `${el.tagName.toLowerCase()}.${(el.getAttribute('class') ?? '').split(' ')[0]} ${Math.round(r.width)}×${Math.round(r.height)} «${(el.textContent || el.getAttribute('name') || '').trim().slice(0, 24)}»`);

            return {
                display: document.fonts.check('16px ' + getComputedStyle(document.documentElement).getPropertyValue('--font-display')),
                height: document.documentElement.scrollHeight,
                sizes, small, controls, radii, shadows, zoned: [...new Set(zoned)],
            };
        });

        await page.screenshot({ path: `${out}/${name}-${width}.png`, fullPage: true });
        report[`${name}@${width}`] = m;
        await page.close();
    }
}

await browser.close();
writeFileSync(`${out}/report.json`, JSON.stringify(report, null, 2));
for (const [k, v] of Object.entries(report)) {
    console.log(`== ${k} · alto ${v.height} · fuente de rótulo ${v.display ? 'cargada' : 'NO cargada'}`);
    console.log('  tallas', JSON.stringify(v.sizes));
    console.log('  <15px', v.small.length, JSON.stringify(v.small.slice(0, 12)));
    console.log('  controles <48', v.controls.length, JSON.stringify(v.controls.slice(0, 12)));
    console.log('  radios', JSON.stringify(v.radii), '· sombras', Object.keys(v.shadows).length, '· color de zona', JSON.stringify(v.zoned));
}
