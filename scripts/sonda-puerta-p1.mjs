/**
 * SONDA DE LA PUERTA NUEVA, P1 (`docs/specs/puerta-nueva.md` §4.4): lo que una prueba de PHP no ve, en un navegador de
 * verdad y en los tamaños del brief —la tablet horizontal 1080 × 810 (la referencia), 1194 × 834, 1366 × 1024 y el móvil
 * en vertical—, con las fichas del mockup montadas en la BD local (`storage/app/audit/ojo-puerta.php`, fuera de git).
 * Y la encuesta de la P1b entera, con la de VARIAS, y que un toque no rehace la ficha (la de `#741`, `sonda-puerta.mjs`, se
 * retiró con ella).
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
const VEREDICTO = { ana: 'Listos para saltar', mostrador: 'Falta firmar el descargo', carlos: 'Falta firmar el descargo', marta: 'Listos para saltar', elena: 'Listos para saltar', jorge: 'Falta firmar el descargo', irene: 'Listos para saltar', tomas: 'Listos para saltar', david: 'Listos para saltar', sofia: 'Listos para saltar', javier: 'Listos para saltar' };

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

/**
 * Teclea como el lector (el código y un Enter) y espera a que la respuesta pinte. ⚠️ Espera a que la ficha de ANTES ya no
 * esté —cada lectura la sustituye: su clave es la lectura (la P1b)— y no un tiempo fijo: con once fichas y cuatro tamaños,
 * una respuesta lenta dejó leer la de Jorge como si fuera la de Irene (02-10).
 */
const escanear = async (codigo) => {
    const antes = await page.evaluateHandle(() => document.querySelector('[data-gate-profile], .ppu-cuerpo.centro'));
    await page.fill('#input', codigo);
    await page.press('#input', 'Enter');
    await page.waitForLoadState('networkidle');
    await page.waitForFunction((el) => el === null || ! el.isConnected, antes, { timeout: 8000 }).catch(() => null);
    await page.waitForTimeout(450);
};

/** Todo lo VISIBLE que se toca, con su alto. */
const tocables = () => page.evaluate(() => [...document.querySelectorAll('.ppu button, .ppu [role="button"], .ppu input')]
    .filter((el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden'; })
    .map((el) => ({ que: (el.textContent || el.getAttribute('placeholder') || el.tagName).trim().slice(0, 30), alto: Math.round(el.getBoundingClientRect().height) })));

// La P3: con el campo vacío, una de las dos reseñas de prueba del montaje (la del mockup y la de Marcos, con las palabras de
// prueba) en lugar del lector, entera dentro de la pantalla y sin desplazarla. Y por TURNO (`#910`): cada vez que la pantalla
// vuelve a quedar vacía —abrir o refrescar, «Nueva búsqueda»— sale la otra.
const RESENAS = {
    '«Los monitores, un diez: Irene estuvo pendiente de los peques toda la tarde.»': 'Laura M., en Google · hace 3 días.',
    '«Un equipo de diez: nos ayudaron con todo y los peques salieron felices y agotados.»': 'Marcos P., en Google · hace 5 días.',
};
const vistas = [];
const resenaVacia = () => page.evaluate(() => {
    const r = document.querySelector('[data-gate-empty] [data-gate-review]');
    if (! r) return null;
    const caja = r.getBoundingClientRect();
    return {
        cita: r.querySelector('blockquote')?.textContent.trim(), autor: r.querySelector('.ppu-resena__a')?.textContent.trim(),
        dentro: caja.left >= 0 && caja.right <= window.innerWidth + 0.5 && caja.top >= 0 && caja.bottom <= window.innerHeight + 0.5,
        // La marca oficial de Google (`#780`): el fichero CARGADO y a su alto, dentro de la tarjeta.
        marca: (() => { const m = r.querySelector('[data-gate-review-brand]'); const b = m?.getBoundingClientRect(); return Boolean(m && m.complete && m.naturalWidth > 0 && Math.round(b.height) === 24 && b.right <= caja.right); })(),
        lector: document.querySelectorAll('[data-gate-empty] .ppu-vacio__ico').length,
    };
});

for (const [ancho, alto] of TAMANOS) {
    await page.setViewportSize({ width: ancho, height: alto });
    await abrir();
    check(`${ancho}: la página no se desplaza como documento`, await page.evaluate(() => document.documentElement.scrollHeight <= window.innerHeight + 1), await page.evaluate(() => `${document.documentElement.scrollHeight} de ${window.innerHeight}`));
    const vacia = await resenaVacia();
    check(`${ancho}: con el campo vacío, la reseña del día (cita, autor y la marca de Google) en lugar del lector, entera en pantalla`, vacia !== null && RESENAS[vacia.cita] === vacia.autor && vacia.marca && vacia.dentro && vacia.lector === 0, JSON.stringify(vacia));
    vistas.push(vacia?.cita ?? null);
    await page.screenshot({ path: `${OUT}/sonda-puerta-p1-vacio-${ancho}.png` });

    for (const [ficha, token] of Object.entries(tokens)) {
        await escanear(token);
        const html = await page.content();
        const texto = await page.locator('[data-gate-profile] [role="status"]').first().textContent().catch(() => '');
        check(`${ancho} · ${ficha}: el veredicto dice «${VEREDICTO[ficha]}»`, texto.includes(VEREDICTO[ficha]), texto.trim().slice(0, 60));
        check(`${ancho} · ${ficha}: ningún correo entero en el HTML`, ! /ojo-puerta-(?!empleado)[a-z0-9-]+@jumpweb\.test/.test(html));
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
// `#910`: cada tamaño ABRE la pantalla de nuevo (como un refresco), así que las dos reseñas se turnan de una a la siguiente.
check('reseña · al refrescar sale la siguiente: las dos se turnan', vistas.every((c, i) => c !== null && (i === 0 || c !== vistas[i - 1])) && new Set(vistas).size === 2, JSON.stringify(vistas));

// La encuesta PREGUNTA A PREGUNTA (la P1b), con Elena —verde y sin participación—: la PRIMERA con el aviso del anonimato y
// «1 de N»; un toque pasa a la siguiente SIN REHACER LA FICHA (los mismos nodos, sin pitido y sin que el veredicto vuelva a
// entrar: la «recarga» que vio el owner el 02-10); la de VARIAS marca y desmarca de una en una sin ir al servidor (el fallo
// de `#741`: un toque las marcaba todas) y la última cierra con «Guardado.». Con Irene, «Ahora no» tras un toque cierra con
// lo contestado. En ámbar, ni se pinta. (Tras el recorrido de tamaños, que solo ESCANEA: la encuesta no se escribe hasta el
// primer toque. El montaje borra las participaciones de sus clientes, así que en cada pasada se vuelve a ofrecer.)
await page.setViewportSize({ width: 1080, height: 810 });
await abrir();
await page.evaluate(() => {
    window.__sonidos = [];
    window.__entradas = [];
    window.addEventListener('puerta-sonido', (e) => window.__sonidos.push(e.detail));
    document.addEventListener('animationstart', (e) => window.__entradas.push(`${e.animationName} ${String(e.target.className)}`), true);
});
await escanear(tokens.jorge);
check('encuesta · en ámbar no se pinta', (await page.locator('[data-gate-survey]').count()) === 0);
await escanear(tokens.elena);
const tarjeta = page.locator('[data-gate-survey]');
const pregunta = () => page.locator('[data-gate-question]').getAttribute('data-gate-question').catch(() => null);
const tipo = () => page.locator('[data-gate-question]').getAttribute('data-gate-question-type').catch(() => null);
/** Un toque, y la espera a que la tarjeta pase a la siguiente (o se cierre). */
const tocar = async (boton) => {
    const antes = await pregunta();
    await boton.click();
    await page.waitForFunction((p) => (document.querySelector('[data-gate-question]')?.dataset.gateQuestion ?? null) !== p
        || document.querySelector('[data-gate-survey]')?.dataset.gateSurvey !== 'asking', antes, { timeout: 8000 }).catch(() => null);
    await page.waitForTimeout(300);
};
const progreso = await page.locator('[data-gate-survey-progress]').textContent().catch(() => '');
check('encuesta · Elena (verde) la ve preguntando la PRIMERA, con el aviso del anonimato y «1 de N»', (await tarjeta.getAttribute('data-gate-survey')) === 'asking' && (await page.locator('[data-gate-survey-notice]').count()) === 1 && (await page.locator('[data-gate-question]').count()) === 1 && /^1 de \d+$/.test(progreso.trim()), progreso.trim());
const primera = await pregunta();
await page.evaluate(() => {
    document.querySelector('[data-gate-profile]').__marca = 1;
    document.querySelector('[data-gate-profile] [data-gate-status]').__marca = 1;
    window.__sonidos = [];
    window.__entradas = [];
});
await tocar(page.locator('[data-gate-survey-option]').first());
const segunda = await pregunta();
check('encuesta · un toque guarda y pasa a la siguiente (sin el aviso)', segunda !== null && segunda !== primera && (await page.locator('[data-gate-survey-notice]').count()) === 0, `${primera} → ${segunda}`);
const quieta = await page.evaluate(() => ({
    ficha: document.querySelector('[data-gate-profile]')?.__marca === 1,
    veredicto: document.querySelector('[data-gate-profile] [data-gate-status]')?.__marca === 1,
    sonidos: window.__sonidos,
    entradas: window.__entradas.filter((a) => ! a.includes('ppu-enc__q')),
}));
check('encuesta · el toque NO rehace la ficha: los mismos nodos, sin pitido y solo entra la pregunta', quieta.ficha && quieta.veredicto && quieta.sonidos.length === 0 && quieta.entradas.length === 0, JSON.stringify(quieta));
check('encuesta · el foco vuelve al campo tras el toque', await page.evaluate(() => document.activeElement?.id === 'input'));
await page.screenshot({ path: `${OUT}/sonda-puerta-p1-encuesta-1080.png` });

for (let i = 0; i < 8 && (await tipo()) !== 'multi' && (await tarjeta.getAttribute('data-gate-survey')) === 'asking'; i += 1) {
    await tocar(page.locator('[data-gate-survey-option]').first());
}
const enVarias = (await tipo()) === 'multi';
check('encuesta · la encuesta local llega a una pregunta de VARIAS', enVarias, String(await tipo()));
if (enVarias) {
    const opciones = page.locator('[data-gate-survey-toggle]');
    const marcadas = () => page.locator('[data-gate-survey-toggle][aria-pressed="true"]').count();
    const antes = peticiones;
    await opciones.first().click();
    const una = await marcadas();
    await opciones.first().click();
    const ninguna = await marcadas();
    await opciones.nth(1).click();
    await opciones.last().click();
    const dos = await marcadas();
    check('encuesta · VARIAS: un toque marca UNA, otro la desmarca, y dos marcan dos', una === 1 && ninguna === 0 && dos === 2, `${una} · ${ninguna} · ${dos} de ${await opciones.count()}`);
    check('encuesta · VARIAS: marcar y desmarcar no van al servidor', peticiones === antes, `${peticiones - antes} peticiones`);
    await page.screenshot({ path: `${OUT}/sonda-puerta-p1-encuesta-varias-1080.png` });
    await tocar(page.locator('[data-gate-survey-next]'));
}
for (let i = 0; i < 8 && (await tarjeta.getAttribute('data-gate-survey')) === 'asking'; i += 1) {
    const t = await tipo();
    await tocar(t === 'multi' || t === 'text' ? page.locator('[data-gate-survey-next]') : page.locator('[data-gate-survey-option]').first());
}
check('encuesta · tras la última, «Guardado.»', (await tarjeta.getAttribute('data-gate-survey')) === 'answered' && (await tarjeta.textContent()).includes('Guardado.'));
await page.screenshot({ path: `${OUT}/sonda-puerta-p1-encuesta-guardada-1080.png` });

await escanear(tokens.irene);
await tocar(page.locator('[data-gate-survey-option]').first());
await tocar(page.locator('[data-gate-survey-skip]'));
check('encuesta · «Ahora no» tras un toque cierra con lo contestado: «Guardado.»', (await tarjeta.getAttribute('data-gate-survey')) === 'answered' && (await tarjeta.textContent()).includes('Guardado.'));

// `#819`: «Ahora no» SIN nada contestado, con David —verde y sin participación—: la tarjeta se va (el mockup) y la ficha se
// queda; al volver a escanearle el mismo día (más de 3 s después: si no, es una doble lectura), no vuelve a salir.
await escanear(tokens.david);
const conTarjeta = (await page.locator('[data-gate-survey="asking"]').count()) === 1;
await page.locator('[data-gate-survey-skip]').click();
await page.waitForFunction(() => document.querySelector('[data-gate-survey]') === null, null, { timeout: 8000 }).catch(() => null);
await page.waitForTimeout(300);
check('encuesta · «Ahora no» sin nada contestado: la tarjeta se va y la ficha se queda', conTarjeta && (await page.locator('[data-gate-survey]').count()) === 0 && (await page.locator('[data-gate-profile]').count()) === 1);
await page.screenshot({ path: `${OUT}/sonda-puerta-p1-encuesta-ahora-no-1080.png` });
await page.waitForTimeout(3200);
await escanear(tokens.david);
check('encuesta · y el mismo día no vuelve a salir', (await page.locator('[data-gate-profile]').count()) === 1 && (await page.locator('[data-gate-survey]').count()) === 0);
// ⚠️ Más de 3 s antes de volver a escanearla: el mismo código antes es una DOBLE LECTURA y no busca (la primera vuelta de
// esta sonda lo confundió con que la encuesta se volvía a ofrecer).
await page.waitForTimeout(3200);
await escanear(tokens.elena);
check('encuesta · ya no se vuelve a ofrecer', (await page.locator('[data-gate-profile]').count()) === 1 && (await page.locator('[data-gate-survey]').count()) === 0);

// LA P2 (`puerta-nueva.md` §4.4): las pulseras con la configuración de PlayJump que monta `ojo-puerta.php` en la local (sus
// colores del mockup, la rueda desde las 11:00 cada 30 min, la ilimitada gris, los packs rojos con su zona de salto y los
// calcetines que se entregan). Lo que mide el navegador: el COLOR de la loseta, la tinta de la cifra, la frase y que el dibujo
// de los calcetines sea de trazo (sin la hoja de la web, salía relleno de negro).
await page.waitForTimeout(3200);
const pulseras = async (ficha) => {
    await escanear(tokens[ficha]);
    return page.evaluate(() => ({
        filas: [...document.querySelectorAll('.ppu-ent')].map((el) => {
            const s = getComputedStyle(el.querySelector('.ppu-cant'));
            return { texto: el.querySelector('.ppu-ent__t')?.textContent.replace(/\s+/g, ' ').trim(), fondo: s.backgroundColor, blanca: s.color === 'rgb(255, 255, 255)' };
        }),
        entregas: [...document.querySelectorAll('[data-gate-handed]')].map((el) => {
            const path = el.querySelector('.icon svg path');
            return { texto: el.textContent.replace(/\s+/g, ' ').trim(), relleno: path ? getComputedStyle(path).fill : null };
        }),
    }));
};
const ana = await pulseras('ana');
check('pulseras · Ana: «KIDS pulseras naranjas» sobre su naranja, la cifra en tinta', ana.filas.length === 1 && ana.filas[0].texto === 'KIDS pulseras naranjas' && ana.filas[0].fondo === 'rgb(255, 106, 19)' && ! ana.filas[0].blanca, JSON.stringify(ana.filas));
check('pulseras · Ana: «2 pares de calcetines», con el dibujo de TRAZO', ana.entregas.length === 1 && ana.entregas[0].texto === '2 pares de calcetines' && ana.entregas[0].relleno === 'none', JSON.stringify(ana.entregas));
await page.waitForTimeout(3200);
const david = await pulseras('david');
check('pulseras · David: la ilimitada gris (fija), Kids 17:00 naranja y Jump 18:00 amarilla (la rueda del mockup)', JSON.stringify(david.filas.map((f) => f.texto)) === JSON.stringify(['KIDS pulsera gris', 'KIDS pulseras naranjas', 'JUMP pulsera amarilla']), JSON.stringify(david.filas));
check('pulseras · David: los calcetines de sus tres reservas, sumados', david.entregas.length === 1 && david.entregas[0].texto === '4 pares de calcetines', JSON.stringify(david.entregas));
await page.waitForTimeout(3200);
const sofia = await pulseras('sofia');
check('pulseras · Sofía: el cumpleaños en la fila de KIDS (su zona de salto), rojo y la cifra en blanco', sofia.filas.length === 1 && sofia.filas[0].texto === 'KIDS pulseras rojas' && sofia.filas[0].fondo === 'rgb(224, 38, 59)' && sofia.filas[0].blanca, JSON.stringify(sofia.filas));
await page.screenshot({ path: `${OUT}/sonda-puerta-p1-pulseras-sofia-1080.png` });

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
// La P3: bajo la píldora del velo, la reseña del día, visible; y una sola vez en la página (nunca en la ficha).
const enVelo = await page.evaluate(() => ({
    enVelo: document.querySelectorAll('[data-gate-veil] [data-gate-review]').length,
    total: document.querySelectorAll('[data-gate-review]').length,
    alto: Math.round(document.querySelector('[data-gate-veil] [data-gate-review]')?.getBoundingClientRect().height ?? 0),
}));
check('el velo lleva la reseña del día bajo su píldora, y la ficha no', enVelo.enVelo === 1 && enVelo.total === 1 && enVelo.alto > 0, JSON.stringify(enVelo));
await page.screenshot({ path: `${OUT}/sonda-puerta-p1-velo-1080.png` });
// `#910`: «Nueva búsqueda» vacía la pantalla y sale la SIGUIENTE (la ficha abierta no movió el turno: su velo enseñaba la de antes).
const enElVelo = await page.locator('[data-gate-veil] blockquote').textContent().catch(() => null);
await page.locator('[data-gate-veil]').click();
await page.getByRole('button', { name: 'Nueva búsqueda' }).click();
await page.waitForLoadState('networkidle');
await page.waitForTimeout(450);
const trasVaciar = await page.locator('[data-gate-empty] blockquote').textContent().catch(() => null);
check('reseña · con «Nueva búsqueda» sale la siguiente', enElVelo !== null && trasVaciar !== null && enElVelo.trim() !== trasVaciar.trim() && Object.hasOwn(RESENAS, trasVaciar.trim()), `${enElVelo?.trim()} → ${trasVaciar?.trim()}`);

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
