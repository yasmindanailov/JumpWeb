/**
 * SONDA DEL FOCO en el área de cuenta del cajón — «Menores a cargo» (`docs/specs/menores-a-cargo.md` §4.1).
 *
 * Mide lo que **ningún test de servidor ni de `node --test` puede ver**: si el navegador lanza un error
 * al plegar el formulario de alta y **dónde queda el foco** después. Es la promesa de `#217` («plegar
 * devuelve el foco al disparador»), y estuvo ROTA en producción desde v1.1.0 hasta `DECISIONES #707`:
 * la plantilla tenía su `ref="addBtn"` y el `<script setup>` no lo declaraba, así que cada plegado
 * lanzaba `addBtn is not defined` y el foco caía al `<body>` — al principio del documento.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   Chromium dentro del contenedor (cabecera de `scripts/sonda-cajon.mjs`, paso 1):
 *     docker compose exec -u sail -T laravel.test node scripts/sonda-foco-cuenta.mjs
 *   Sale ≠ 0 si hay error de navegador o si el foco no vuelve. `BASE` por defecto `http://localhost`,
 *   que es como ve el mundo el Chromium del contenedor (el del owner es `:8081`).
 *
 * ⚠️ **Se pliega con el «Cancelar» de DENTRO, no con el disparador**: cerrando con el disparador el
 * foco se queda en él por el propio clic, y el defecto —que el foco no VUELVE— queda tapado por la
 * casualidad. Medido: con el disparador salía «sano» y con «Cancelar» salía `<BODY>`.
 */
import { chromium } from 'playwright-core';

const BASE = process.env.BASE ?? 'http://localhost';
const CLIENTE = { email: 'probe-card@jumpweb.test', password: 'Probe-card-2026!' };

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });

const errores = [];
page.on('pageerror', (e) => errores.push(String(e.message ?? e)));
page.on('console', (m) => m.type() === 'error' && errores.push('console: ' + m.text()));

await page.goto(`${BASE}/mi-cuenta`, { waitUntil: 'networkidle' });
await page.waitForSelector('.sidecart.is-open .acc-tiles, .sidecart.is-open #login-email', { timeout: 20000 });

if (await page.locator('#login-email').count()) {
    await page.fill('#login-email', CLIENTE.email);
    await page.fill('#login-password', CLIENTE.password);
    await page.click('.sidecart__panel button[type="submit"]');
}
await page.waitForSelector('.acc-tiles .acc-tile', { timeout: 20000 });

// La baldosa de menores a cargo, por su texto y no por su posición: el índice se reordena.
let entrada = null;
const baldosas = await page.locator('.acc-tile').count();
for (let i = 0; i < baldosas; i++) {
    const t = page.locator('.acc-tile').nth(i);
    if (/menor|dependent/i.test(await t.innerText())) {
        entrada = t;
        break;
    }
}
if (entrada === null) {
    console.log('ERROR: no se encontró la baldosa de menores a cargo');
    await browser.close();
    process.exit(2);
}

await entrada.click();
await page.waitForSelector('#acct-dep-add-btn', { timeout: 20000 });

errores.length = 0;
await page.click('#acct-dep-add-btn');
await page.waitForSelector('#acct-dep-add', { timeout: 10000 });
await page.click('#acct-dep-add .btn--ghost');
await page.waitForTimeout(600);

const foco = await page.evaluate(() => ({
    id: document.activeElement?.id || null,
    etiqueta: document.activeElement?.tagName ?? null,
}));

const vuelve = foco.id === 'acct-dep-add-btn';
console.log(`foco tras plegar → <${foco.etiqueta}> id="${foco.id}"   (lo correcto: BUTTON acct-dep-add-btn)`);
console.log('errores del navegador: ' + (errores.join(' | ') || 'ninguno'));
console.log(vuelve && errores.length === 0 ? 'VERDE' : 'ROJO');

await browser.close();
process.exit(vuelve && errores.length === 0 ? 0 : 1);
