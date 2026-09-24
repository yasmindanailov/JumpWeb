/**
 * SONDA DEL PANEL DE EXPERIMENTOS — T5b (`docs/specs/analitica.md` §4.4) en el navegador real: la tarjeta en «Ajustes»,
 * el alta de un experimento con sus variantes desde el formulario, la lista con su estado, el widget «Experimentos» en
 * «Analítica → Conversión» y el borrado con confirmación. Capturas para el ojo del owner.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   docker compose exec -u sail -T -e SONDA_PANEL_EMAIL=… -e SONDA_PANEL_PASSWORD=… laravel.test \
 *       node scripts/sonda-experimentos-panel.mjs [etiqueta]
 *   (credenciales de un ADMIN del panel, solo por entorno; ⚠️ el login del panel tiene limitador: una vez por corrida).
 *   Crea el experimento `sonda-panel` y lo borra al final; si una corrida se corta, se quita por tinker.
 */
import { chromium } from 'playwright-core';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const EMAIL = process.env.SONDA_PANEL_EMAIL ?? '';
const PASSWORD = process.env.SONDA_PANEL_PASSWORD ?? '';
const LABEL = process.argv[2] ?? 'experimentos-panel';
const KEY = 'sonda-panel';
const DIR = 'storage/app/audit';

if (EMAIL === '' || PASSWORD === '') {
    console.error('SONDA_PANEL_EMAIL y SONDA_PANEL_PASSWORD son obligatorias (solo por entorno).');
    process.exit(2);
}

const results = [];
const check = (name, ok, detail = '') => results.push({ comprobación: name, ok: ok ? '✓' : '✗', detalle: String(detail).slice(0, 110) });
const shot = (page, name) => page.screenshot({ path: `${DIR}/${LABEL}-${name}.png`, fullPage: true });

/** Los widgets de Filament cargan al entrar en pantalla: se recorre la página en pasadas hasta que aparezca lo pedido. */
async function scrollUntil(page, locator, passes = 8) {
    for (let i = 0; i < passes; i++) {
        if (await locator.count()) return true;
        await page.mouse.wheel(0, 900);
        await page.waitForTimeout(400);
    }

    return (await locator.count()) > 0;
}

await mkdir(DIR, { recursive: true });
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale: 'es-ES' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(String(e?.message ?? e)));

try {
    // 1 · Login del admin.
    await page.goto(`${BASE}/admin/login`, { waitUntil: 'load' });
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL((u) => ! u.pathname.endsWith('/admin/login'), { timeout: 15000 });
    check('login del admin', ! page.url().endsWith('/admin/login'), page.url());

    // 2 · La tarjeta en «Ajustes».
    await page.goto(`${BASE}/admin/ajustes`, { waitUntil: 'load' });
    const card = page.getByRole('link', { name: /Experimentos/ }).first();
    check('«Ajustes» tiene la tarjeta «Experimentos»', (await card.count()) > 0);
    await shot(page, 'ajustes');
    await card.click();
    await page.waitForURL(/\/admin\/experimentos/, { timeout: 15000 });
    check('la tarjeta lleva a la lista de experimentos', /\/admin\/experimentos/.test(page.url()), page.url());

    // 3 · El alta desde el formulario: clave, nombre, activo y dos variantes con peso.
    await page.getByRole('link', { name: /Crear experimento/ }).first().click();
    await page.waitForURL(/\/admin\/experimentos\/create/, { timeout: 15000 });
    // ⚠️ Los campos se localizan por el id que Filament les da: `form.<ruta del estado>` (`form.key`, `form.name`, el
    // interruptor `form.active`, y en el repetidor `form.variants.<uuid>.key`/`.weight`). Ni `getByLabel` (el rótulo lleva
    // el asterisco dentro y no casa) ni `data.<ruta>` (medido con un volcado del DOM el 24-09).
    await page.locator('[id="form.key"]').fill(KEY);
    await page.locator('[id="form.name"]').fill('Sonda del panel');
    const active = page.locator('[id="form.active"]').first();
    if ((await active.count()) && (await active.getAttribute('aria-checked')) !== 'true') await active.click();
    const variantKeys = page.locator('input[id^="form.variants."][id$=".key"]');
    const weights = page.locator('input[id^="form.variants."][id$=".weight"]');
    check('el formulario nace con dos variantes', (await variantKeys.count()) === 2, `${await variantKeys.count()} variantes`);
    await variantKeys.nth(0).fill('a');
    await weights.nth(0).fill('3');
    await variantKeys.nth(1).fill('b');
    await weights.nth(1).fill('1');
    await shot(page, 'alta');
    await page.getByRole('button', { name: /^Crear$/ }).first().click();
    await page.waitForURL((u) => /\/admin\/experimentos$/.test(u.pathname), { timeout: 20000 });
    const row = page.locator('tr', { hasText: KEY }).first();
    check('la lista muestra el experimento creado', (await row.count()) > 0);
    check('con su estado «Vivo» y sus variantes «a 3 · b 1»', (await row.count()) > 0 && /Vivo/.test(await row.innerText()) && /a 3 · b 1/.test(await row.innerText()), (await row.count()) ? (await row.innerText()).replace(/\s+/g, ' ').slice(0, 100) : '');
    await shot(page, 'lista');

    // 4 · El widget en «Analítica → Conversión».
    await page.goto(`${BASE}/admin/analitica?pestana=traffic`, { waitUntil: 'load' });
    const heading = page.getByText('Experimentos', { exact: true });
    check('«Conversión» tiene la sección «Experimentos»', await scrollUntil(page, heading));
    if (await heading.count()) {
        await heading.first().scrollIntoViewIfNeeded();
        await heading.first().click();
        await page.waitForTimeout(600);
    }
    const body = await page.locator('body').innerText();
    check('la sección dice qué mide (nota con «Wilson» y «consintieron») o que no hay exposiciones', /Wilson/.test(body) || /Ningún experimento con exposiciones/.test(body));
    await shot(page, 'conversion');

    // 5 · El borrado con confirmación.
    await page.goto(`${BASE}/admin/experimentos`, { waitUntil: 'load' });
    await page.locator('tr', { hasText: KEY }).first().click();
    await page.waitForURL(/\/admin\/experimentos\/\d+\/edit/, { timeout: 15000 });
    check('con el experimento VIVO la clave va bloqueada', await page.locator('[id="form.key"]').isDisabled());
    await page.getByRole('button', { name: /Borrar experimento/ }).first().click();
    await page.getByRole('button', { name: /^Borrar$/ }).last().click();
    await page.waitForURL((u) => /\/admin\/experimentos$/.test(u.pathname), { timeout: 20000 });
    check('borrar con confirmación lo quita de la lista', (await page.locator('tr', { hasText: KEY }).count()) === 0);

    check('sin errores de JavaScript en el panel', errors.length === 0, errors.join(' | '));
} catch (e) {
    // Una excepción a medias deja la tabla con lo que sí se comprobó, y una captura de dónde se quedó.
    check('la sonda terminó sin excepción', false, e?.message ?? String(e));
    await shot(page, 'excepcion').catch(() => {});
} finally {
    await browser.close();
}

console.table(results);
const failed = results.filter((r) => r.ok !== '✓').length;
console.log(`${results.length - failed}/${results.length} · capturas en ${DIR}/${LABEL}-*.png`);
process.exit(failed === 0 ? 0 : 1);
