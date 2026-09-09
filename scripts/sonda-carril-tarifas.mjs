/**
 * SONDA DEL CARRIL DE TARIFAS — ¿ASOMA la tarjeta siguiente? (`DECISIONES #479`)
 *
 * ⚠️⚠️ **VERSIONADA A PROPÓSITO, como `sonda-geometria.mjs` y `comparar-con-mockup.mjs`.** Mide la
 * única propiedad de la sección 02 que no ve ni la suite ni una captura de un solo ancho: **que la
 * tarjeta vecina se vea cortada por el borde de la pantalla**.
 *
 * ▶ **Por qué esto es una propiedad y no un adorno.** El canvas retiró de la entradilla de 02 la
 * frase «arrastra si quieres más» con un motivo escrito en `doc/voz.md`: *«ahí el carril sí
 * scrollea, y la señal la da la tarjeta que asoma, no el texto»*. Sin asoma, la sección promete
 * tres tarifas, enseña una y **no queda nada que invite a arrastrar**.
 *
 * ❗❗ **LA ARITMÉTICA DEL ARTBOARD NO CIERRA A 390 px, y por eso hace falta medirlo.** Su turno 10a
 * fija la tarjeta en **352**; con el sangrado (16), el hueco (12) y la vecina **al 94 %** —que la
 * desplaza otros ~10 px hacia dentro— su borde cae en **390,6**: fuera de la anchura de referencia
 * del propio sistema. El producto usa `min(352px, calc(100vw - 58px))`, así que **desde ~414 px la
 * tarjeta mide los 352 dibujados** y por debajo encoge lo justo.
 *
 * ── CÓMO SE CORRE ────────────────────────────────────────────────────────────────────────────
 *   La misma receta que `sonda-geometria.mjs` (navegador dentro del contenedor + puente 8081→80):
 *     docker compose exec -u sail -T laravel.test bash -lc \
 *       'PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers node scripts/sonda-carril-tarifas.mjs'
 *
 * ⚠️ **El puntero virtual arranca en (0,0)** y ahí cae una tarjeta tras el scroll, así que su hover
 * movería el foco y con él la escala de las vecinas. Se aparta antes de medir — la trampa que
 * `comparar-con-mockup.mjs` ya pagó.
 * ⚠️ **Se mide el borde IZQUIERDO de la segunda tarjeta**, no el derecho de la primera: lo que
 * decide si se ve algo es dónde empieza la vecina **ya escalada**, y `getBoundingClientRect()` la
 * devuelve con su `transform` aplicado.
 * ⚠️ **Y se mide en varios anchos porque la regla es una FUNCIÓN**: con un solo punto no se
 * distingue «asoma siempre» de «asoma justo aquí». Es la lección de `TypeScaleTest`.
 */
import { chromium } from 'playwright-core';

const BASE = 'http://localhost:8081';
/* 320 y 390 son los dos extremos del móvil que el sistema acepta; 414 es donde la tarjeta ya cabe
   a su tamaño de artboard; 768 y 1024 son los saltos de retícula; 1440 es el escritorio de
   referencia. Por encima de 1024 no hay carril, así que ahí lo que se comprueba es que la rejilla
   no deja desborde. */
const ANCHOS = [320, 390, 414, 768, 1024, 1440];
const ASOMA_MIN = 12;

const b = await chromium.launch();
let fallos = 0;

for (const w of ANCHOS) {
    const ctx = await b.newContext({ viewport: { width: w, height: 900 }, deviceScaleFactor: 1 });
    const p = await ctx.newPage();
    await p.goto(BASE + '/', { waitUntil: 'networkidle' });
    await p.locator('.cookie-btn').first().click().catch(() => {});
    await p.evaluate(() => document.fonts.ready);
    await p.evaluate(() => document.querySelector('#pricing').scrollIntoView({ block: 'start' }));
    await p.mouse.move(w - 1, 1);
    await p.waitForTimeout(400);

    const m = await p.evaluate((ancho) => {
        const rail = document.querySelector('#pricing .rates__panel:not([style*="display: none"]) .rates__rail');
        if (!rail) return { error: 'no hay carril visible' };

        const cards = [...rail.querySelectorAll('.rate-card')];
        if (cards.length < 2) return { error: `solo ${cards.length} tarjeta(s): la sección no puede asomar nada` };

        const primera = cards[0].getBoundingClientRect();
        const segunda = cards[1].getBoundingClientRect();

        return {
            carril: getComputedStyle(rail).display,
            ancho: Math.round(primera.width),
            asoma: Math.round(ancho - segunda.left),
            desborde: Math.round(document.documentElement.scrollWidth - document.documentElement.clientWidth),
        };
    }, w);

    if (m.error) {
        console.log(`${String(w).padStart(5)} px  ✗ ${m.error}`);
        fallos++;
        await ctx.close();
        continue;
    }

    // En escritorio no hay carril: es una rejilla, y ahí «asomar» no significa nada.
    const enCarril = m.carril === 'flex';
    const okAsoma = ! enCarril || m.asoma >= ASOMA_MIN;
    const okDesborde = m.desborde === 0;

    console.log(
        `${String(w).padStart(5)} px  ${okAsoma && okDesborde ? '✓' : '✗'}  ` +
        `${enCarril ? 'carril ' : 'rejilla'}  tarjeta ${String(m.ancho).padStart(4)}  ` +
        `asoma ${String(m.asoma).padStart(4)} px  desborde ${m.desborde}`,
    );

    if (! okAsoma) {
        console.log(`        └─ la vecina asoma menos de ${ASOMA_MIN} px: no hay señal de que haya más tarifas.`);
        fallos++;
    }
    if (! okDesborde) {
        console.log('        └─ el carril a sangre está empujando la página: el margen negativo no cuadra con el canal.');
        fallos++;
    }

    await ctx.close();
}

await b.close();
console.log(`\n── ${fallos} problema(s) ──`);
process.exit(fallos === 0 ? 0 : 1);
