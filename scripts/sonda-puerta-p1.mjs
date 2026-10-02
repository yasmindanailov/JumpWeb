/**
 * SONDA DE LA PUERTA NUEVA, P1 (`docs/specs/puerta-nueva.md` §4.4): lo que una prueba de PHP no ve, en un navegador de
 * verdad y en los tamaños del brief —la tablet horizontal 1080 × 810 (la referencia), 1194 × 834, 1366 × 1024 y el móvil
 * en vertical—, con las fichas del mockup montadas en la BD local (`storage/app/audit/ojo-puerta.php`, fuera de git).
 * (La sonda de la encuesta en la puerta de `#741` sigue en `sonda-puerta.mjs`; su encuesta se rehace en la P1b.)
 *
 * En cada tamaño y ficha: el veredicto con su palabra, el nombre, ningún correo entero en el HTML (la cola ve la
 * pantalla), el foco de vuelta en el campo, todo lo que se toca ≥ 44 px y, en la tablet horizontal y el caso común, la
 * columna de veredicto y tareas SIN desplazar. Y una vez: la doble lectura no busca dos veces, el velo sale a los 60 s
 * (el reloj de la página adelantado) y los estados sin ficha.
 *
 * ⚠️ SOLO EN LOCAL. Una sola sesión de panel (el login tiene limitador).
 *
 *   docker compose exec -u sail -T laravel.test php storage/app/audit/ojo-puerta.php
 *   docker compose exec -u sail -T -e BASE=http://localhost:8081 laravel.test node scripts/sonda-puerta-p1.mjs
 *
 * Sale con 1 si algún punto falla; el informe y las fotos, en `storage/app/audit/sonda-puerta-p1*`.
 */
import process from 'node:process';
import { readFileSync, writeFileSync } from 'node:fs';
import { chromium } from 'playwright-core';

const BASE = process.env.BASE ?? 'http://localhost:8081';
const OUT = 'storage/app/audit';
const { tokens } = JSON.parse(readFileSync(`${OUT}/ojo-puerta.json`, 'utf8'));
const TAMANOS = [[1080, 810], [1194, 834], [1366, 1024], [390, 844]];
const COMUNES = ['ana', 'mostrador', 'carlos', 'marta', 'tomas'];
const VEREDICTO = { ana: 'Listos para saltar', mostrador: 'Falta firmar el descargo', carlos: 'Falta firmar el descargo', marta: 'Listos para saltar', elena: 'Listos para saltar', jorge: 'Falta firmar el descargo', irene: 'Listos para saltar', tomas: 'Listos para saltar', david: 'Listos para saltar' };

const informe = { cuando: new Date().toISOString(), checks: [] };
const check = (nombre, ok, detalle = '') => informe.checks.push({ nombre, ok: Boolean(ok), detalle: String(detalle) });

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1080, height: 810 }, locale: 'es-ES' });
const page = await context.newPage();
await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
// El empleado de la puerta (rol `puerta`, `#320`) que monta `ojo-puerta.php`: el admin está obligado a los dos pasos.
await page.fill('input[type="email"]', 'ojo-puerta-empleado@jumpweb.test');
await page.fill('input[type="password"]', 'Sonda-puerta-2026!');
await Promise.all([page.waitForURL((u) => ! u.pathname.endsWith('/login'), { timeout: 20000 }), page.click('button[type="submit"]')]);
check('entra en el panel', ! page.url().endsWith('/login'), page.url());

let peticiones = 0;
page.on('request', (r) => { if (r.url().includes('/livewire') && r.method() === 'POST') peticiones += 1; });

const abrir = async () => {
    await page.goto(`${BASE}/admin/puerta/validar`, { waitUntil: 'networkidle' });
    const campo = await page.locator('#input').count();
    if (campo === 0) {
        check('la puerta abre con su campo', false, `${page.url()} · ${(await page.locator('body').innerText()).slice(0, 200).replace(/\s+/g, ' ')}`);
        writeFileSync(`${OUT}/sonda-puerta-p1.json`, `${JSON.stringify(informe, null, 2)}\n`);
        console.log(`✗ la puerta no abre: ${informe.checks.at(-1).detalle}`);
        await browser.close();
        process.exit(1);
    }
};

/** Teclea como el lector (el código y un Enter) y espera a que la respuesta pinte. */
const escanear = async (codigo) => {
    await page.fill('#input', codigo);
    await page.press('#input', 'Enter');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(450);
};

/** Todo lo VISIBLE que se toca, con su alto. */
const tocables = () => page.evaluate(() => [...document.querySelectorAll('.ppu button, .ppu [role="button"], .ppu input:not([type="radio"]):not([type="checkbox"]), .ppu label.gate-q__btn')]
    .filter((el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden'; })
    .map((el) => ({ que: (el.textContent || el.getAttribute('placeholder') || el.tagName).trim().slice(0, 30), alto: Math.round(el.getBoundingClientRect().height) })));

for (const [ancho, alto] of TAMANOS) {
    await page.setViewportSize({ width: ancho, height: alto });
    await abrir();
    check(`${ancho}: la página no se desplaza como documento`, await page.evaluate(() => document.documentElement.scrollHeight <= window.innerHeight + 1), await page.evaluate(() => `${document.documentElement.scrollHeight} de ${window.innerHeight}`));

    for (const [ficha, token] of Object.entries(tokens)) {
        await escanear(token);
        const html = await page.content();
        const texto = await page.locator('[data-gate-profile] [role="status"]').first().textContent().catch(() => '');
        check(`${ancho} · ${ficha}: el veredicto dice «${VEREDICTO[ficha]}»`, texto.includes(VEREDICTO[ficha]), texto.trim().slice(0, 60));
        check(`${ancho} · ${ficha}: ningún correo entero en el HTML`, ! html.includes(`ojo-puerta-${ficha}@jumpweb.test`));
        check(`${ancho} · ${ficha}: el foco vuelve al campo`, await page.evaluate(() => document.activeElement?.id === 'input'));
        const bajos = (await tocables()).filter((t) => t.alto < 44);
        check(`${ancho} · ${ficha}: todo lo que se toca ≥ 44 px`, bajos.length === 0, JSON.stringify(bajos));
        // Ninguna tarjeta pisa a otra (a 390 «Sus hijos» llegó a montarse encima de la reserva y la suite no lo veía).
        const pisan = await page.evaluate(() => {
            const cajas = [...document.querySelectorAll('.ppu-ficha .ppu-ver, .ppu-ficha .ppu-card')].map((el) => el.getBoundingClientRect());
            const out = [];
            cajas.forEach((a, i) => cajas.slice(i + 1).forEach((b, j) => {
                if (a.left < b.right - 1 && b.left < a.right - 1 && a.top < b.bottom - 1 && b.top < a.bottom - 1) out.push([i, i + j + 1]);
            }));
            return out;
        });
        check(`${ancho} · ${ficha}: ninguna tarjeta pisa a otra`, pisan.length === 0, JSON.stringify(pisan));
        if (ancho === 1080 && COMUNES.includes(ficha)) {
            const col = await page.evaluate(() => { const c = document.querySelector('[data-gate-col="main"]'); return c ? [c.scrollHeight, c.clientHeight] : [0, 0]; });
            check(`1080 · ${ficha}: veredicto y tareas sin desplazar`, col[0] <= col[1] + 1, `${col[0]} de ${col[1]}`);
        }
        await page.screenshot({ path: `${OUT}/sonda-puerta-p1-${ficha}-${ancho}.png` });
    }
}

// La encuesta PREGUNTA A PREGUNTA (la P1b), con Elena —verde y sin participación—: la PRIMERA pregunta con el aviso del
// anonimato; un toque pasa a la siguiente; «Ahora no» cierra con lo contestado («Guardado.»). En amarillo, ni se pinta.
// (Tras el recorrido de tamaños, que solo ESCANEA: la encuesta no se escribe hasta el primer toque. El montaje borra las
// participaciones de sus clientes, así que en cada pasada se vuelve a ofrecer.)
await page.setViewportSize({ width: 1080, height: 810 });
await abrir();
await escanear(tokens.jorge);
check('encuesta · en ámbar no se pinta', (await page.locator('[data-gate-survey]').count()) === 0);
await escanear(tokens.elena);
const tarjeta = page.locator('[data-gate-survey]');
check('encuesta · Elena (verde) la ve preguntando la PRIMERA, con el aviso del anonimato', (await tarjeta.getAttribute('data-gate-survey')) === 'asking' && (await page.locator('[data-gate-survey-notice]').count()) === 1 && (await page.locator('[data-gate-question]').count()) === 1);
const primera = await page.locator('[data-gate-question]').getAttribute('data-gate-question');
await page.locator('[data-gate-survey-option]').first().click();
await page.waitForLoadState('networkidle');
await page.waitForTimeout(300);
const segunda = await page.locator('[data-gate-question]').getAttribute('data-gate-question').catch(() => null);
check('encuesta · un toque guarda y pasa a la siguiente (sin el aviso)', segunda !== null && segunda !== primera && (await page.locator('[data-gate-survey-notice]').count()) === 0, `${primera} → ${segunda}`);
check('encuesta · el foco vuelve al campo tras el toque', await page.evaluate(() => document.activeElement?.id === 'input'));
await page.screenshot({ path: `${OUT}/sonda-puerta-p1-encuesta-1080.png` });
await page.locator('[data-gate-survey-skip]').click();
await page.waitForLoadState('networkidle');
await page.waitForTimeout(300);
check('encuesta · «Ahora no» cierra con lo contestado: «Guardado.»', (await tarjeta.getAttribute('data-gate-survey')) === 'answered' && (await tarjeta.textContent()).includes('Guardado.'));
await page.screenshot({ path: `${OUT}/sonda-puerta-p1-encuesta-guardada-1080.png` });
// ⚠️ Más de 3 s antes de volver a escanearla: el mismo código antes es una DOBLE LECTURA y no busca (la primera vuelta de
// esta sonda lo confundió con que la encuesta se volvía a ofrecer).
await page.waitForTimeout(3200);
await escanear(tokens.elena);
check('encuesta · ya no se vuelve a ofrecer', (await page.locator('[data-gate-profile]').count()) === 1 && (await page.locator('[data-gate-survey]').count()) === 0);

// Una vez, en la tablet: la doble lectura, el velo y los estados sin ficha.
await page.setViewportSize({ width: 1080, height: 810 });
await page.clock.install();
await abrir();
await escanear(tokens.ana);
const tras = peticiones;
await page.fill('#input', tokens.ana);
await page.press('#input', 'Enter');
await page.waitForTimeout(600);
check('la doble lectura (el mismo código en < 3 s) no busca otra vez', peticiones === tras, `${peticiones - tras} peticiones de más`);
check('y deja el campo vacío', (await page.inputValue('#input')) === '');

await page.clock.fastForward(61000);
// La entrada del velo dura 420 ms (tiempo REAL: el reloj falso no mueve las animaciones de CSS): se espera a que acabe.
await page.waitForTimeout(700);
const velo = await page.locator('[data-gate-veil]').evaluate((el) => {
    const s = getComputedStyle(el);
    const fondo = s.backgroundColor.match(/[\d.]+/g) ?? [];
    return { visible: el.getBoundingClientRect().height > 0, opacidad: s.opacity, alfa: fondo.length === 4 ? Number(fondo[3]) : 1 };
}).catch(() => ({ visible: false }));
check('a los 60 s, el velo tapa la ficha, OPACO (la cola no lee nada detrás)', velo.visible && velo.opacidad === '1' && velo.alfa === 1, JSON.stringify(velo));
await page.screenshot({ path: `${OUT}/sonda-puerta-p1-velo-1080.png` });

for (const [que, entrada, palabra] of [['no-encontrado', 'nadie-ojo-puerta@correo.es', 'No encontrado'], ['mal-escrito', 'ana@correo', 'Escribe un correo o un teléfono válido']]) {
    await escanear(entrada);
    const t = await page.locator('[data-gate-status]').first().textContent().catch(() => '');
    check(`sin ficha · ${que}: «${palabra}»`, t.includes(palabra), t.trim().slice(0, 60));
    await page.screenshot({ path: `${OUT}/sonda-puerta-p1-${que}-1080.png` });
}
check('lo mal escrito no se repite debajo (se queda en el campo)', (await page.locator('[data-gate-query]').count()) === 0 && (await page.inputValue('#input')) === 'ana@correo');

await browser.close();
writeFileSync(`${OUT}/sonda-puerta-p1.json`, `${JSON.stringify(informe, null, 2)}\n`);
let fallos = 0;
for (const { nombre, ok, detalle } of informe.checks) {
    if (! ok) {
        fallos += 1;
        console.log(`  ✗ ${nombre}  → ${detalle}`);
    }
}
console.log(`\n${fallos === 0 ? '✓' : '✗'} ${informe.checks.length - fallos}/${informe.checks.length} · ${OUT}/sonda-puerta-p1.json`);
process.exit(fallos === 0 ? 0 : 1);
