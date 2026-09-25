/**
 * SONDA DEL VISOR — la CONDUCTA del visor de atracciones de Kids y Jump, el del diseño (React) contra el nuestro (el
 * JS de la instancia), sobre las páginas del banco (`scripts/banco-entradas.php`, pieza `zona`; `isla-y-landing-nueva.md`
 * §4.12 T4c·6). El banco juzga lo que se VE; esto, lo que una foto no ve: abrir, el teclado, cerrar con Esc y desde el
 * fondo, la acción, adónde vuelve el foco y el `body` bloqueado mientras está abierto.
 *
 *   docker compose exec -u sail -T -e PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers laravel.test \
 *       node scripts/sonda-visor.mjs [http://127.0.0.1:8131]
 *
 * Sale con 1 si un paso difiere. Dos diferencias se INFORMAN y no se comparan, las dos a propósito:
 *  · el ANCLA tras la acción: en el diseño el salto a `#precio` lo hace la PÁGINA (`irA`, en `pagina.jsx`), no el
 *    componente que monta el banco; el nuestro lo lleva el visor (`data-pj-visor-accion`);
 *  · el FOCO tras usar un control DENTRO del visor (una marca): el diseño lo devuelve a «Cerrar» en cada cambio de
 *    plano, efecto de las dependencias de su `useEffect` (`[open, index, n]`), no una decisión que diga su código
 *    —que solo promete el foco a «Cerrar» al abrir y de vuelta al cerrar—; copiarlo haría que un segundo Intro sobre
 *    «siguiente» CERRASE el visor en vez de avanzar. El nuestro deja el foco en el control que se usó.
 */
import { chromium } from 'playwright-core';

const base = process.argv[2] ?? 'http://127.0.0.1:8131';

async function recorrer(pagina) {
    const pasos = [];
    const anotar = async (paso, informar = []) => pasos.push([paso, informar, await pagina.evaluate(() => {
        const d = document.querySelector('[role=dialog]');
        const abierto = !!d && !d.hidden && getComputedStyle(d).display !== 'none';
        const a = document.activeElement;
        const foco = !a || a === document.body ? 'body' : (a.getAttribute('aria-label') || a.textContent.trim())
            .replace(/^(Ver la foto|Ver el vídeo): /, '').replace(/^(Ir al vídeo|Ir a la foto) /, 'marca ');
        return {
            abierto,
            rotulo: abierto ? d.getAttribute('aria-label').replace(/^(Vídeo|Foto) /, '') : null,
            foco,
            overflow: document.body.style.overflow,
            ancla: location.hash,
        };
    })]);
    const tarjeta = pagina.locator('figure button[aria-label^="Ver la foto"]').nth(1);
    const clic = (l) => l.click({ timeout: 3000 });
    // Un paso que no se puede dar (el visor que no se cerró tapa la tarjeta siguiente) es un RESULTADO, no un fallo de
    // la sonda: se anota y se deja de recorrer, y la comparación lo enseña en su sitio.
    try {
        await clic(tarjeta); await pagina.waitForTimeout(120); await anotar('abrir la 2.ª');
        await pagina.keyboard.press('ArrowRight'); await pagina.waitForTimeout(80); await anotar('tecla →');
        await pagina.keyboard.press('ArrowUp'); await pagina.keyboard.press('ArrowUp'); await pagina.waitForTimeout(80); await anotar('tecla ↑ ×2');
        await pagina.keyboard.press('Escape'); await pagina.waitForTimeout(80); await anotar('Esc');
        await clic(tarjeta); await pagina.waitForTimeout(120);
        await clic(pagina.locator('[role=dialog] button[aria-label$=" 8"]')); await pagina.waitForTimeout(80); await anotar('la última marca', ['foco']);
        await pagina.mouse.click(5, 5); await pagina.waitForTimeout(80); await anotar('clic en el fondo');
        await clic(tarjeta); await pagina.waitForTimeout(120);
        await clic(pagina.locator('[role=dialog] button:has-text("Reservar")')); await pagina.waitForTimeout(80); await anotar('la acción');
    } catch (error) {
        pasos.push(['(no se pudo seguir)', [], { error: error.message.split('\n')[0] }]);
    }

    return pasos;
}

const navegador = await chromium.launch();
const lados = {};
for (const lado of ['a', 'b']) {
    const contexto = await navegador.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
    const pagina = await contexto.newPage();
    await pagina.goto(`${base}/${lado}/kids-zona.html`, { waitUntil: 'load' });
    await pagina.waitForTimeout(2500);
    lados[lado] = await recorrer(pagina);
    await contexto.close();
}
await navegador.close();

let distintos = 0;
const total = Math.max(lados.a.length, lados.b.length);
for (let i = 0; i < total; i++) {
    const [paso, informar, a] = lados.a[i] ?? lados.b[i];
    const b = (lados.b[i] ?? [null, [], { error: 'sin este paso' }])[2];
    const fuera = ['ancla', ...informar];
    const comparable = (e) => JSON.stringify(Object.fromEntries(Object.entries(e).filter(([k]) => !fuera.includes(k))));
    const igual = comparable(a) === comparable(b);
    distintos += igual ? 0 : 1;
    const avisos = fuera.filter((k) => a[k] !== b[k]).map((k) => `${k} A «${a[k]}», B «${b[k]}»`);
    console.log(`${igual ? '✓' : '✗'} ${paso}: ${comparable(a)}${igual ? '' : `\n    B ${comparable(b)}`}${avisos.length ? ` · (informado) ${avisos.join('; ')}` : ''}`);
}
console.log(`\n${distintos ? '✗' : '✓'} ${total - distintos} de ${total} pasos iguales`);
process.exit(distintos ? 1 : 0);
