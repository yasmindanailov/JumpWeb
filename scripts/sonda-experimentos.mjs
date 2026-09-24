/**
 * SONDA DE LOS EXPERIMENTOS — la asignación del servidor vista desde el navegador real (`docs/specs/analitica.md`
 * §4.4, T5a): que la PRIMERA vista de un visitante nuevo ya trae su variante en el `data-boot` y la cookie que la va a
 * conservar; que recargar y `GET /sidebar/session` dicen la misma; que visitantes distintos caen en variantes
 * distintas; y que `experiment_exposed` sale por `JumpWeb.track` hacia el libro.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────────
 *   El experimento se pone por tinker ANTES (y se quita después):
 *     Experiment::create(['key' => 'sonda', 'name' => 'Sonda', 'active' => true,
 *         'variants' => [['key' => 'a', 'weight' => 1], ['key' => 'b', 'weight' => 1]]]);
 *   y después:
 *     docker compose exec -u sail -T -e SONDA_EXPERIMENT_KEY=sonda laravel.test node scripts/sonda-experimentos.mjs [etiqueta]
 *   La sonda imprime el `visitor_id` con el que mandó la exposición: la fila de `analytics_events` se comprueba por
 *   tinker (`where('name', 'experiment_exposed')->where('visitor_id', …)`), porque el libro es del servidor.
 */
import { chromium } from 'playwright-core';
import { mkdir, writeFile } from 'node:fs/promises';

const BASE = process.env.SONDA_BASE ?? 'http://localhost';
const KEY = process.env.SONDA_EXPERIMENT_KEY ?? '';
const LABEL = process.argv[2] ?? 'experimentos';
const VISITORS = 12;
// Un UA de escritorio normal: el libro marca `is_bot` por UA conocido, y el de Chromium headless lo es.
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

if (KEY === '') {
    console.error('SONDA_EXPERIMENT_KEY es obligatoria (la clave del experimento puesto por tinker).');
    process.exit(2);
}

const results = [];
const check = (name, ok, detail = '') => results.push({ comprobación: name, ok: ok ? '✓' : '✗', detalle: String(detail).slice(0, 120) });

async function bootOf(page) {
    return page.evaluate(() => {
        const raw = document.getElementById('sidecart-spa')?.dataset.boot;

        return raw ? JSON.parse(raw) : null;
    });
}

async function freshVisit(browser) {
    const context = await browser.newContext({ userAgent: UA });
    const page = await context.newPage();
    await page.goto(`${BASE}/`, { waitUntil: 'load' });
    const boot = await bootOf(page);
    const cookie = (await context.cookies()).find((c) => c.name === 'visitor_id');

    return { context, page, boot, cookie };
}

const browser = await chromium.launch();

try {
    // 1 · N visitantes nuevos: todos con variante en la primera vista y con la cookie acuñada en ESA respuesta.
    const seen = new Map();
    const visits = [];

    for (let i = 0; i < VISITORS; i++) {
        const visit = await freshVisit(browser);
        const variant = visit.boot?.experiments?.[KEY];

        if (typeof variant === 'string') seen.set(variant, (seen.get(variant) ?? 0) + 1);
        visits.push({ variant, cookie: visit.cookie?.value ?? null });
        if (i > 0) await visit.context.close();
        else visits.first = visit;
    }

    check('la primera vista de cada visitante nuevo trae su variante', visits.every((v) => typeof v.variant === 'string'), JSON.stringify(visits.map((v) => v.variant)));
    check('y la cookie del visitante acuñada en esa misma respuesta', visits.every((v) => v.cookie), `${visits.filter((v) => v.cookie).length}/${VISITORS}`);
    check(`visitantes distintos caen en variantes distintas (${VISITORS} sorteos)`, seen.size >= 2, JSON.stringify([...seen]));

    // 2 · El mismo visitante: recargar y la sesión de la API dicen lo mismo, y la cookie no se renueva.
    const { context, page, boot, cookie } = visits.first;
    const variant = boot?.experiments?.[KEY];

    await page.reload({ waitUntil: 'load' });
    const again = await bootOf(page);
    check('recargar conserva la variante', again?.experiments?.[KEY] === variant, `${variant} → ${again?.experiments?.[KEY]}`);

    const cookieAfter = (await context.cookies()).find((c) => c.name === 'visitor_id');
    check('la cookie no cambia al recargar (13 meses, sin renovarse)', cookieAfter?.value === cookie?.value, cookie?.value ?? '');

    const session = await page.evaluate(async () => {
        const r = await fetch('/api/v1/sidebar/session?lang=es', { credentials: 'include', headers: { Accept: 'application/json' } });

        return { status: r.status, body: await r.json(), cache: r.headers.get('cache-control') };
    });
    check('GET /sidebar/session da la MISMA variante al mismo visitante', session.status === 200 && session.body?.experiments?.[KEY] === variant, JSON.stringify(session.body?.experiments));
    check('y es no-store', String(session.cache).includes('no-store'), session.cache ?? '');

    // 3 · La exposición sale por el buzón/tracker y se vacía al salir de la página (`pagehide` → flush con keepalive).
    // ⚠️ `evaluate` admite UN argumento: los dos van en un objeto.
    const tracked = await page.evaluate(({ key, v }) => {
        const fn = window.JumpWeb?.track;

        if (typeof fn !== 'function') return false;
        fn('experiment_exposed', { key, variant: v });

        return true;
    }, { key: KEY, v: variant });
    check('`JumpWeb.track` existe y acepta `experiment_exposed`', tracked);

    await page.goto(`${BASE}/entradas`, { waitUntil: 'load' });
    await page.waitForTimeout(1500);
    check('la página siguiente cargó (la salida vacía la cola del tracker)', page.url().includes('/entradas'), `visitor_id=${cookie?.value}`);

    await mkdir('storage/app/audit', { recursive: true });
    await writeFile(`storage/app/audit/experimentos-${LABEL}.json`, JSON.stringify({ key: KEY, variant, visitor: cookie?.value ?? null, reparto: [...seen], results }, null, 2));
    await context.close();
} finally {
    await browser.close();
}

console.table(results);
const failed = results.filter((r) => r.ok !== '✓').length;
console.log(`${results.length - failed}/${results.length} · visitor_id de la exposición: ${results.find((r) => r.comprobación.startsWith('la página siguiente'))?.detalle ?? ''}`);
process.exit(failed === 0 ? 0 : 1);
