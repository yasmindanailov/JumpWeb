/**
 * SONDA DEL OPT-IN TRAS COMPRAR — la casilla de comunicaciones en la pantalla de «reserva creada» del cajón
 * (`docs/specs/analitica.md` §4.3 «Comunicaciones» y §4.6, T4c), en el navegador real: que aparece DESMARCADA a un
 * cliente con sesión, sin opt-in y sin retirada previa; que marcarla escribe el consentimiento (`GET /me`), y una
 * captura para el ojo del owner.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   La pantalla se abre con el PASE de la vuelta de la pasarela (`HomeController::maybeConsumeRedsysReturn`): una
 *   entrada de un solo uso en la caché `database`, atada al titular. Se pone por tinker ANTES (y la sonda la gasta):
 *     $t = Str::random(40);
 *     RedsysReturnController::handoff()->put(RedsysReturnController::cacheKey($t),
 *         ['user_id' => <id>, 'order_code' => '<código de un pedido pagado del titular>', 'outcome' => 'authorized'],
 *         now()->addMinutes(5));
 *   y después:
 *     docker compose exec -u sail -T -e SONDA_PANEL_EMAIL=… -e SONDA_PANEL_PASSWORD=… -e SONDA_REDSYS_TOKEN=$t \
 *         laravel.test node scripts/sonda-optin-compra.mjs [etiqueta]
 *   (las credenciales son de un CLIENTE de prueba con `marketing_opt_in = false` y sin retirada previa).
 *   ⚠️ La sonda deja el opt-in DADO y una fila en `consents`: se devuelve la cuenta a como estaba por tinker.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const EMAIL = process.env.SONDA_PANEL_EMAIL ?? '';
const PASSWORD = process.env.SONDA_PANEL_PASSWORD ?? '';
const TOKEN = process.env.SONDA_REDSYS_TOKEN ?? '';
const ETIQUETA = process.argv[2] ?? 'ojo';
const SALIDA = 'storage/app/audit';

if (EMAIL === '' || PASSWORD === '' || TOKEN === '') {
    console.error('SONDA_PANEL_EMAIL, SONDA_PANEL_PASSWORD y SONDA_REDSYS_TOKEN son obligatorias (solo por entorno).');
    process.exit(2);
}

await mkdir(SALIDA, { recursive: true });
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale: 'es-ES', reducedMotion: 'reduce' });
const page = await ctx.newPage();
const comprobaciones = [];
const ok = (nombre, cond, detalle = '') => comprobaciones.push({ nombre, ok: Boolean(cond), detalle: String(detalle).slice(0, 200) });
const me = async () => page.evaluate(async () => (await (await fetch('/api/v1/me', { credentials: 'include', headers: { Accept: 'application/json' } })).json()));

await page.goto(`${BASE}/login`, { waitUntil: 'load' });
await page.fill('input[type="email"], input[name="email"]', EMAIL);
await page.fill('input[type="password"], input[name="password"]', PASSWORD);
await Promise.all([page.waitForURL((u) => ! u.pathname.endsWith('/login'), { timeout: 20000 }).catch(() => null), page.click('button[type="submit"]')]);
ok('login del cliente', ! page.url().endsWith('/login'), page.url());
ok('precondición: sin opt-in', (await me())?.marketing_opt_in === false);

await page.goto(`${BASE}/?redsys=${encodeURIComponent(TOKEN)}`, { waitUntil: 'load' });
const pantalla = page.locator('.purchase__done').first();
await pantalla.waitFor({ timeout: 20000 }).catch(() => null);
ok('el pase de la vuelta abre el cajón en «reserva creada»', await pantalla.count() === 1, JSON.stringify(await page.evaluate(() => ({ boot: document.querySelector('[data-boot]')?.getAttribute('data-boot')?.slice(0, 160) ?? null, cajon: document.querySelector('#sidecart-spa') !== null }))));
const casilla = page.locator('[data-marketing-offer] input[role="switch"]').first();
await casilla.waitFor({ timeout: 20000 }).catch(() => null);
ok('la pantalla de «reserva creada» ofrece la casilla del opt-in', await casilla.count() === 1);
if (await casilla.count() !== 1) {
    await page.screenshot({ path: `${SALIDA}/optin-compra-${ETIQUETA}-sin-casilla.png` });
    await browser.close();
    await writeFile(`${SALIDA}/optin-compra-${ETIQUETA}.json`, JSON.stringify({ base: BASE, etiqueta: ETIQUETA, comprobaciones }, null, 2));
    console.table(comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 120) })));
    process.exit(1);
}
ok('y la ofrece DESMARCADA', await casilla.count() === 1 && ! (await casilla.isChecked()));
ok('con los rótulos de «Privacidad» (ni una clave más)', await page.locator('[data-marketing-offer]').getByText('novedades y ofertas', { exact: false }).count() >= 1);
await page.mouse.move(0, 0);
await page.waitForTimeout(700);
await page.screenshot({ path: `${SALIDA}/optin-compra-${ETIQUETA}.png` });

await casilla.click();
await page.waitForTimeout(1500);
ok('marcarla escribe el opt-in en la cuenta (GET /me)', (await me())?.marketing_opt_in === true);
ok('y la casilla queda marcada', await casilla.isChecked());
await page.mouse.move(0, 0);
await page.waitForTimeout(500);
await page.screenshot({ path: `${SALIDA}/optin-compra-${ETIQUETA}-marcada.png` });

await browser.close();
await writeFile(`${SALIDA}/optin-compra-${ETIQUETA}.json`, JSON.stringify({ base: BASE, etiqueta: ETIQUETA, comprobaciones }, null, 2));
console.table(comprobaciones.map((c) => ({ comprobación: c.nombre, ok: c.ok ? '✓' : '✗', detalle: c.detalle.slice(0, 80) })));
process.exit(comprobaciones.every((c) => c.ok) ? 0 : 1);
